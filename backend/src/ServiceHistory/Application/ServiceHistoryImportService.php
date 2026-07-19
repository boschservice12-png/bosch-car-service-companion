<?php

declare(strict_types=1);

namespace App\ServiceHistory\Application;

use App\Audit\Application\AuditRecorder;
use App\Identity\Domain\User;
use App\ServiceHistory\Domain\ServiceRecord;
use App\Vehicle\Domain\Vehicle;
use App\Vehicle\Domain\Vin;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Import „istoric reparații" dintr-un tabel (Excel/CSV), legat de vehicul prin
 * VIN (și implicit de proprietar, prin legătura de proprietate existentă).
 *
 * Coloane: VIN (obligatoriu), dată, kilometraj, lucrare, descriere, piese,
 * manoperă, total, garanție, număr comandă — antetul în orice ordine.
 *
 * Reguli:
 *  - vehiculul TREBUIE să existe deja (importați întâi clienții + vehiculele);
 *  - rândurile complete (dată + km + lucrare + descriere) devin PUBLISHED —
 *    clientul le vede imediat; cele incomplete rămân DRAFT, de completat;
 *  - idempotent: după numărul comenzii (dacă există) sau după
 *    (vehicul, dată, total, lucrare) — reimportul nu creează dubluri;
 *  - sumele acceptă formate RON uzuale („1.250,50", „1250.50", „1250");
 *  - datele acceptă zz.ll.aaaa, zz/ll/aaaa, aaaa-ll-zz și seriale Excel.
 */
final class ServiceHistoryImportService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AuditRecorder $audit,
    ) {
    }

    /**
     * @param list<list<string>> $rows Primul rând = antetul.
     *
     * @return array<string, mixed>
     */
    public function import(array $rows, ?User $importedBy): array
    {
        if ($rows === []) {
            throw new \InvalidArgumentException('Tabelul este gol.');
        }

        $map = $this->headerMap($rows[0]);
        if (!isset($map['vin'])) {
            throw new \InvalidArgumentException('Antet incomplet — coloana VIN este obligatorie (plus: dată, kilometraj, lucrare, descriere, piese, manoperă, total, garanție, număr comandă).');
        }

        $report = [
            'totalRows' => 0,
            'recordsPublished' => 0,
            'recordsDraft' => 0,
            'recordsSkipped' => 0,
            'errors' => [],
        ];

        // Tot fișierul este atomic; $seen deduplică rândurile din ACELAȘI fișier
        // (interogarea DB nu vede entitățile încă neflush-uite).
        $seen = [];
        $connection = $this->em->getConnection();
        $connection->beginTransaction();
        try {
            foreach (\array_slice($rows, 1) as $i => $row) {
                $rowNo = $i + 2;
                if ($this->isEmptyRow($row)) {
                    continue;
                }
                ++$report['totalRows'];

                try {
                    $this->importRow($row, $map, $importedBy, $report, $seen);
                } catch (\InvalidArgumentException $e) {
                    $report['errors'][] = ['row' => $rowNo, 'message' => $e->getMessage()];
                }
            }

            $this->em->flush();
            $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollBack();
            throw $e;
        }

        $this->audit->record('import.service_history', 'Import', null, null, [
            'totalRows' => $report['totalRows'],
            'published' => $report['recordsPublished'],
            'draft' => $report['recordsDraft'],
            'skipped' => $report['recordsSkipped'],
            'errors' => \count($report['errors']),
        ]);

        return $report;
    }

    /**
     * @param list<string>         $row
     * @param array<string, int>   $map
     * @param array<string, mixed> $report
     * @param array<string, true>  $seen   Chei deja importate din fișierul curent.
     */
    private function importRow(array $row, array $map, ?User $importedBy, array &$report, array &$seen): void
    {
        $cell = static fn (string $key): string => trim($row[$map[$key] ?? -1] ?? '');

        $vinRaw = $cell('vin');
        if ($vinRaw === '') {
            throw new \InvalidArgumentException('VIN-ul este obligatoriu.');
        }
        $vin = new Vin($vinRaw);

        $vehicle = $this->findVehicleByVin($vin->value());
        if ($vehicle === null) {
            throw new \InvalidArgumentException(sprintf(
                'Vehiculul cu VIN %s nu există — importați întâi clienții și vehiculele.',
                $vin->value(),
            ));
        }

        $serviceDate = $this->parseDate($cell('date'));
        $odometer = $this->parseInt($cell('odometer'));
        $workType = $cell('worktype') ?: null;
        $description = $cell('description') ?: null;
        $parts = $cell('parts') ?: null;
        $laborBani = $this->parseMoneyBani($cell('labor'));
        $totalBani = $this->parseMoneyBani($cell('total'));
        $warranty = $cell('warranty') ?: null;
        $workOrder = $cell('workorder') ?: null;

        if ($serviceDate === null && $workType === null && $description === null) {
            throw new \InvalidArgumentException('Rând fără conținut: completați cel puțin data și lucrarea.');
        }

        // Idempotență: nu importa de două ori aceeași intrare — nici din fișierul
        // curent (cheia $seen), nici din importuri anterioare (interogarea DB).
        $key = $workOrder !== null
            ? sprintf('wo|%s|%s', (string) $vehicle->id(), $workOrder)
            : sprintf('dt|%s|%s|%d|%s|%s', (string) $vehicle->id(), $serviceDate?->format('Y-m-d') ?? '-', $totalBani ?? 0, $workType ?? '-', $description ?? '-');
        if (isset($seen[$key]) || $this->alreadyImported($vehicle, $workOrder, $serviceDate, $totalBani, $workType, $description)) {
            ++$report['recordsSkipped'];

            return;
        }
        $seen[$key] = true;

        $record = new ServiceRecord($vehicle, $importedBy);
        $record->applyDetails($serviceDate, $odometer, $workType, $description, $parts, $laborBani ?? 0, $totalBani ?? 0, $warranty);
        if ($workOrder !== null) {
            $record->setWorkOrderNumber($workOrder);
        }

        // Complet → publicat direct (clientul îl vede); incomplet → rămâne ciornă.
        if ($record->missingForPublish() === []) {
            $record->publish();
            ++$report['recordsPublished'];
        } else {
            ++$report['recordsDraft'];
        }

        $this->em->persist($record);
    }

    private function alreadyImported(Vehicle $vehicle, ?string $workOrder, ?\DateTimeImmutable $serviceDate, ?int $totalBani, ?string $workType, ?string $description): bool
    {
        $qb = $this->em->createQueryBuilder()
            ->select('COUNT(r.id)')
            ->from(ServiceRecord::class, 'r')
            ->where('r.vehicle = :vehicle')
            ->setParameter('vehicle', $vehicle);

        if ($workOrder !== null) {
            $qb->andWhere('r.workOrderNumber = :wo')->setParameter('wo', $workOrder);
        } elseif ($serviceDate !== null) {
            $qb->andWhere('r.serviceDate = :d')->setParameter('d', $serviceDate)
                ->andWhere('r.totalBani = :t')->setParameter('t', $totalBani ?? 0);
            if ($workType !== null) {
                $qb->andWhere('r.workType = :wt')->setParameter('wt', $workType);
            }
        } else {
            // Fără număr de comandă și fără dată: deduplicare după conținut
            // (lucrare + descriere + total), altfel fiecare reimport ar crea ciorne noi.
            $qb->andWhere('r.serviceDate IS NULL')
                ->andWhere('r.totalBani = :t')->setParameter('t', $totalBani ?? 0);
            foreach (['workType' => $workType, 'workDescription' => $description] as $field => $value) {
                if ($value !== null) {
                    $qb->andWhere(sprintf('r.%s = :%s', $field, $field))->setParameter($field, $value);
                } else {
                    $qb->andWhere(sprintf('r.%s IS NULL', $field));
                }
            }
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }

    private function findVehicleByVin(string $vin): ?Vehicle
    {
        return $this->em->createQueryBuilder()
            ->select('v')->from(Vehicle::class, 'v')
            ->where('v.vin = :vin')->andWhere('v.deletedAt IS NULL')
            ->setParameter('vin', $vin)
            ->setMaxResults(1)
            ->getQuery()->getOneOrNullResult();
    }

    /** Acceptă zz.ll.aaaa, zz/ll/aaaa, zz-ll-aaaa, aaaa-ll-zz și seriale Excel. */
    private function parseDate(string $value): ?\DateTimeImmutable
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        // Serial Excel (zile de la 1899-12-30) — apare la coloanele formatate ca dată.
        if (preg_match('/^\d{4,6}(\.\d+)?$/', $value) === 1) {
            $days = (int) floor((float) $value);
            if ($days > 20000 && $days < 80000) {
                return (new \DateTimeImmutable('1899-12-30'))->modify(sprintf('+%d days', $days));
            }
        }

        foreach (['!d.m.Y', '!d/m/Y', '!d-m-Y', '!Y-m-d', '!Y.m.d'] as $format) {
            $date = \DateTimeImmutable::createFromFormat($format, $value);
            if ($date !== false) {
                return $date;
            }
        }

        throw new \InvalidArgumentException(sprintf('Dată invalidă: „%s" (folosiți zz.ll.aaaa sau aaaa-ll-zz).', $value));
    }

    /** „1.250,50" / „1250.50" / „1 250,50" / „1250" → bani (RON * 100). */
    private function parseMoneyBani(string $value): ?int
    {
        $value = trim(str_ireplace(['ron', 'lei', ' ', "\xc2\xa0"], '', $value));
        if ($value === '') {
            return null;
        }

        $hasComma = str_contains($value, ',');
        $hasDot = str_contains($value, '.');
        if ($hasComma && $hasDot) {
            // „1.250,50" — punctul e separator de mii.
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        } elseif ($hasComma) {
            $value = str_replace(',', '.', $value);
        } elseif ($hasDot && preg_match('/\.\d{3}$/', $value) === 1) {
            // „1.250" — punct de mii, fără zecimale.
            $value = str_replace('.', '', $value);
        }

        if (!is_numeric($value)) {
            throw new \InvalidArgumentException(sprintf('Sumă invalidă: „%s".', $value));
        }

        return (int) round(((float) $value) * 100);
    }

    private function parseInt(string $value): ?int
    {
        $value = trim(str_replace(['.', ' ', ','], '', $value));
        if ($value === '') {
            return null;
        }
        if (preg_match('/^\d+$/', $value) !== 1) {
            throw new \InvalidArgumentException(sprintf('Kilometraj invalid: „%s".', $value));
        }

        return (int) $value;
    }

    /** @param list<string> $header @return array<string, int> */
    private function headerMap(array $header): array
    {
        $aliases = [
            'vin' => ['vin', 'seriesasiu', 'seriasasiului', 'alvazszam'],
            'date' => ['data', 'dataservice', 'datareparatie', 'datareparatiei', 'datum', 'date'],
            'odometer' => ['kilometraj', 'km', 'odometru', 'rulaj', 'kmora', 'kmallas'],
            'worktype' => ['lucrare', 'tiplucrare', 'categorie', 'munka', 'worktype', 'tip'],
            'description' => ['descriere', 'descrierelucrari', 'lucrari', 'leiras', 'description', 'detalii'],
            'parts' => ['piese', 'pieseschimbate', 'alkatresz', 'alkatreszek', 'parts'],
            'labor' => ['manopera', 'munkadij', 'labor'],
            'total' => ['total', 'totalron', 'suma', 'osszeg', 'vegosszeg'],
            'warranty' => ['garantie', 'garancia', 'warranty'],
            'workorder' => ['numarcomanda', 'nrcomanda', 'comanda', 'deviz', 'nrdeviz', 'munkalap', 'workorder'],
        ];

        $map = [];
        foreach ($header as $index => $label) {
            $key = $this->normalizeHeader($label);
            foreach ($aliases as $field => $names) {
                if (\in_array($key, $names, true) && !isset($map[$field])) {
                    $map[$field] = $index;
                }
            }
        }

        return $map;
    }

    private function normalizeHeader(string $label): string
    {
        $label = mb_strtolower(trim($label));
        $label = strtr($label, ['ă' => 'a', 'â' => 'a', 'î' => 'i', 'ș' => 's', 'ş' => 's', 'ț' => 't', 'ţ' => 't', 'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ö' => 'o', 'ő' => 'o', 'ú' => 'u', 'ü' => 'u', 'ű' => 'u']);

        return preg_replace('/[^a-z0-9]/', '', $label) ?? $label;
    }

    /** @param list<string> $row */
    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $cell) {
            if (trim($cell) !== '') {
                return false;
            }
        }

        return true;
    }
}
