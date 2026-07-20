<?php

declare(strict_types=1);

namespace App\Deadline\Application;

use App\Audit\Application\AuditRecorder;
use App\Deadline\Domain\DeadlineSource;
use App\Deadline\Domain\DeadlineType;
use App\Deadline\Domain\VehicleDeadline;
use App\Deadline\Domain\VehicleDeadlineRepository;
use App\Vehicle\Domain\VehicleRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Pasul 3 de import: raportul de alerte din evidența service-ului (ASM,
 * „report ITP / RCA") → scadențele vehiculelor. Coloane: „Denumire alertă",
 * „Data alertei", „Nr. înmatriculare". Doar alertele ITP și RCA devin
 * scadențe; restul (anvelope, custodie, revizii comerciale) se numără ca
 * sărite. Vehiculul se identifică prin numărul de înmatriculare (normalizat,
 * fără spații); alertele repetate actualizează scadența existentă (sursă
 * IMPORT), iar datele identice se sar — importul e idempotent.
 */
final class DeadlineImportService
{
    private const MAX_REPORTED_ERRORS = 200;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly VehicleRepository $vehicles,
        private readonly VehicleDeadlineRepository $deadlines,
        private readonly AuditRecorder $audit,
    ) {
    }

    /**
     * @param list<list<string>> $rows
     *
     * @return array<string, mixed>
     */
    public function import(array $rows): array
    {
        if ($rows === [] || $this->headerIndex($rows) === null) {
            throw new \InvalidArgumentException(
                'Antet incomplet — sunt necesare coloanele: denumire alertă, data alertei, nr. înmatriculare.',
            );
        }
        $headerRow = $this->headerIndex($rows);
        $map = $this->headerMap($rows[$headerRow]);

        // Vehiculele existente, indexate după numărul normalizat (fără spații).
        $byPlate = [];
        foreach ($this->vehicles->findAllActive() as $vehicle) {
            $byPlate[$this->normPlate($vehicle->plateNumber())] = $vehicle;
        }

        $report = [
            'totalRows' => 0,
            'deadlinesCreated' => 0,
            'deadlinesUpdated' => 0,
            'rowsSkipped' => 0,
            'nonItpRcaSkipped' => 0,
            'errorCount' => 0,
            'errors' => [],
        ];

        $connection = $this->em->getConnection();
        $connection->beginTransaction();
        try {
            foreach (\array_slice($rows, $headerRow + 1) as $i => $row) {
                $rowNo = $headerRow + $i + 2;
                $cell = static fn (string $key): string => trim($row[$map[$key] ?? -1] ?? '');
                $alert = $cell('alert');
                $plate = $cell('plate');
                $date = $cell('date');

                // Rândurile de subsol ale raportului („40 rânduri", data generării)
                // nu au număr de înmatriculare — se ignoră fără zgomot.
                if ($plate === '' && ($alert === '' || $date === '')) {
                    continue;
                }
                ++$report['totalRows'];

                $type = $this->alertType($alert);
                if ($type === null) {
                    ++$report['nonItpRcaSkipped'];
                    continue;
                }

                try {
                    $this->importRow($type, $plate, $date, $byPlate, $report);
                } catch (\InvalidArgumentException $e) {
                    ++$report['errorCount'];
                    if (\count($report['errors']) < self::MAX_REPORTED_ERRORS) {
                        $report['errors'][] = ['row' => $rowNo, 'message' => $e->getMessage()];
                    }
                }
            }

            $this->em->flush();
            $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollBack();
            throw $e;
        }

        $this->audit->record('import.deadlines', 'Import', null, null, [
            'totalRows' => $report['totalRows'],
            'created' => $report['deadlinesCreated'],
            'updated' => $report['deadlinesUpdated'],
            'errors' => $report['errorCount'],
        ]);

        return $report;
    }

    /** @param array<string, \App\Vehicle\Domain\Vehicle> $byPlate @param array<string, mixed> $report */
    private function importRow(DeadlineType $type, string $plate, string $date, array $byPlate, array &$report): void
    {
        $expiresAt = $this->parseDate($date);
        if ($expiresAt === null) {
            throw new \InvalidArgumentException(sprintf('Data alertei lipsește sau e nevalidă: „%s".', $date));
        }

        $vehicle = $byPlate[$this->normPlate($plate)] ?? null;
        if ($vehicle === null) {
            throw new \InvalidArgumentException(sprintf(
                'Vehiculul „%s" nu există în evidență — importați întâi lista de parteneri.',
                $plate,
            ));
        }

        $existing = null;
        foreach ($this->deadlines->findForVehicle($vehicle) as $deadline) {
            if ($deadline->type() === $type) {
                $existing = $deadline;
                break;
            }
        }

        if ($existing === null) {
            $this->em->persist(new VehicleDeadline($vehicle, $type, $expiresAt, DeadlineSource::IMPORT));
            ++$report['deadlinesCreated'];

            return;
        }

        if ($existing->expiresAt()?->format('Y-m-d') === $expiresAt->format('Y-m-d')) {
            ++$report['rowsSkipped'];

            return;
        }

        $existing->update($existing->validFrom(), $expiresAt, $existing->note());
        ++$report['deadlinesUpdated'];
    }

    private function alertType(string $alert): ?DeadlineType
    {
        $key = $this->normalize($alert);
        if (str_contains($key, 'itp') || str_contains($key, 'inspectietehnica')) {
            return DeadlineType::ITP;
        }
        if (str_contains($key, 'rca')) {
            return DeadlineType::RCA;
        }
        if (str_contains($key, 'rovinieta') || str_contains($key, 'taxadedrum')) {
            return DeadlineType::ROAD_TAX;
        }

        return null;
    }

    /** Antetul poate să nu fie pe primul rând (rapoartele ASM au un titlu deasupra). */
    private function headerIndex(array $rows): ?int
    {
        foreach (\array_slice($rows, 0, 5, true) as $i => $row) {
            $map = $this->headerMap($row);
            if (isset($map['alert'], $map['date'], $map['plate'])) {
                return $i;
            }
        }

        return null;
    }

    /** @param list<string> $header @return array<string, int> */
    private function headerMap(array $header): array
    {
        $aliases = [
            'alert' => ['denumirealerta', 'alerta', 'tipalerta'],
            'date' => ['dataalertei', 'dataalerta', 'data'],
            'plate' => ['nrinmatriculare', 'numarinmatriculare', 'inmatriculare'],
        ];
        $map = [];
        foreach ($header as $index => $label) {
            $key = $this->normalize($label);
            foreach ($aliases as $field => $names) {
                if (\in_array($key, $names, true) && !isset($map[$field])) {
                    $map[$field] = $index;
                }
            }
        }

        return $map;
    }

    private function parseDate(string $value): ?\DateTimeImmutable
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if (preg_match('/^\d{4,6}(\.\d+)?$/', $value) === 1) {
            $days = (int) floor((float) $value);
            if ($days > 20000 && $days < 80000) {
                return (new \DateTimeImmutable('1899-12-30'))->modify(sprintf('+%d days', $days));
            }
        }
        foreach (['!d.m.Y', '!d/m/Y', '!d-m-Y', '!Y-m-d'] as $format) {
            $date = \DateTimeImmutable::createFromFormat($format, $value);
            if ($date !== false) {
                return $date;
            }
        }

        return null;
    }

    private function normPlate(string $plate): string
    {
        return strtoupper((string) preg_replace('/\s+/', '', $plate));
    }

    private function normalize(string $label): string
    {
        $label = mb_strtolower(trim($label));
        $label = strtr($label, ['ă' => 'a', 'â' => 'a', 'î' => 'i', 'ș' => 's', 'ş' => 's', 'ț' => 't', 'ţ' => 't']);

        return (string) preg_replace('/[^a-z0-9]/', '', $label);
    }
}
