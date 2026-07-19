<?php

declare(strict_types=1);

namespace App\Customer\Application;

use App\Audit\Application\AuditRecorder;
use App\Customer\Domain\CustomerProfile;
use App\Identity\Domain\User;
use App\Vehicle\Domain\Vehicle;
use App\Vehicle\Domain\VehicleRepository;
use App\Vehicle\Domain\Vin;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Import de proprietari + vehicule dintr-un tabel (Excel/CSV) — sursa de date a
 * service-ului. Coloane: proprietar, număr de înmatriculare, VIN, marcă, model,
 * opțional telefon și email.
 *
 * Reguli:
 *  - vehiculul se identifică prin VIN (normalizat uppercase); reimportul
 *    actualizează numărul/marca/modelul, nu creează dubluri (idempotent);
 *  - proprietarul se identifică prin email (dacă există); fără email, contul
 *    primește o adresă internă de tip import (clientul nu se poate autentifica
 *    până când service-ul nu completează emailul real);
 *  - dacă vehiculul are deja alt proprietar activ, rândul este raportat drept
 *    conflict și NU se schimbă nimic automat (decizia rămâne la service);
 *  - rândurile invalide (VIN greșit, câmpuri lipsă) sunt raportate per rând.
 */
final class OwnerVehicleImportService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly VehicleRepository $vehicles,
        private readonly AuditRecorder $audit,
    ) {
    }

    /**
     * @param list<list<string>> $rows Primul rând = antetul.
     *
     * @return array<string, mixed> Raport de import.
     */
    public function import(array $rows): array
    {
        if ($rows === []) {
            throw new \InvalidArgumentException('Tabelul este gol.');
        }

        $map = $this->headerMap($rows[0]);
        foreach (['owner', 'plate', 'vin'] as $required) {
            if (!isset($map[$required])) {
                throw new \InvalidArgumentException(
                    'Antet incomplet — sunt necesare coloanele: proprietar, număr înmatriculare, VIN (opțional: marcă, model, telefon, email).',
                );
            }
        }

        $report = [
            'totalRows' => 0,
            'ownersCreated' => 0,
            'vehiclesCreated' => 0,
            'vehiclesUpdated' => 0,
            'ownershipsCreated' => 0,
            'errors' => [],
        ];

        // Tot fișierul este atomic: o eroare neașteptată anulează întregul import
        // (erorile per rând sunt raportate și NU modifică nimic pentru acel rând).
        $connection = $this->em->getConnection();
        $connection->beginTransaction();
        try {
            foreach (\array_slice($rows, 1) as $i => $row) {
                $rowNo = $i + 2; // 1-bazat + antet
                if ($this->isEmptyRow($row)) {
                    continue;
                }
                ++$report['totalRows'];

                try {
                    $this->importRow($row, $map, $report);
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

        $this->audit->record('import.owners_vehicles', 'Import', null, null, [
            'totalRows' => $report['totalRows'],
            'ownersCreated' => $report['ownersCreated'],
            'vehiclesCreated' => $report['vehiclesCreated'],
            'errors' => \count($report['errors']),
        ]);

        return $report;
    }

    /**
     * @param list<string>          $row
     * @param array<string, int>    $map
     * @param array<string, mixed>  $report
     */
    private function importRow(array $row, array $map, array &$report): void
    {
        $cell = static fn (string $key): string => trim($row[$map[$key] ?? -1] ?? '');

        $ownerName = $cell('owner');
        $plate = $cell('plate');
        $vinRaw = $cell('vin');
        if ($ownerName === '' || $plate === '' || $vinRaw === '') {
            throw new \InvalidArgumentException('Proprietarul, numărul de înmatriculare și VIN-ul sunt obligatorii.');
        }
        $vin = new Vin($vinRaw); // validare + uppercase (aruncă la VIN invalid)

        $make = isset($map['make']) ? ($cell('make') ?: null) : null;
        $model = isset($map['model']) ? ($cell('model') ?: null) : null;
        $phone = isset($map['phone']) ? ($cell('phone') ?: null) : null;
        $email = isset($map['email']) ? mb_strtolower($cell('email')) : '';
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new \InvalidArgumentException(sprintf('Email invalid: „%s".', $email));
        }

        // --- Identificarea proprietarului VIZAT, fără a crea sau modifica nimic ---
        // (verificarea de conflict trebuie să vină ÎNAINTEA oricărei scrieri).
        $emailUser = null;
        if ($email !== '') {
            $emailUser = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);
            if ($emailUser !== null && $emailUser->isServiceAdmin()) {
                throw new \InvalidArgumentException(sprintf(
                    'Emailul „%s" aparține unui cont de serviciu (admin) — rândul a fost sărit.',
                    $email,
                ));
            }
        }

        $vehicle = $this->findVehicleByVin($vin->value());
        $currentOwner = $vehicle !== null ? $this->vehicles->findActiveOwner($vehicle) : null;

        $targetProfile = $emailUser?->customerProfile();
        if ($targetProfile === null && $emailUser === null
            && $currentOwner !== null && $this->sameName($currentOwner->fullName(), $ownerName)) {
            $targetProfile = $currentOwner;
        }

        // Conflict: vehiculul are deja ALT proprietar activ → nu se schimbă absolut nimic.
        if ($currentOwner !== null && ($targetProfile === null || !$currentOwner->id()->equals($targetProfile->id()))) {
            throw new \InvalidArgumentException(sprintf(
                'Conflict: vehiculul %s are deja alt proprietar activ (%s). Rândul a fost sărit — rezolvați manual.',
                $vin->value(),
                $currentOwner->fullName() ?: 'fără nume',
            ));
        }

        // --- De aici nu mai există conflict: vehicul (upsert după VIN) ---
        if ($vehicle === null) {
            $vehicle = new Vehicle($vin, $plate);
            $vehicle->updateDetails($make, $model, null);
            $this->em->persist($vehicle);
            ++$report['vehiclesCreated'];
        } else {
            $vehicle->changePlateNumber($plate);
            $vehicle->updateDetails($make ?? $vehicle->make(), $model ?? $vehicle->model(), $vehicle->year());
            ++$report['vehiclesUpdated'];
        }

        // --- Proprietar (existent, sau creat abia acum) ---
        if ($targetProfile === null) {
            if ($emailUser !== null) {
                // Utilizator existent fără profil de client → i se creează profilul.
                $targetProfile = new CustomerProfile($emailUser, ...$this->splitName($ownerName));
                $this->em->persist($targetProfile);
            } else {
                // Cont nou. Fără email → adresă internă de import (fără autentificare).
                $address = $email !== '' ? $email : sprintf('import-%s@clienti.local', bin2hex(random_bytes(6)));
                $user = new User($address);
                [$firstName, $lastName] = $this->splitName($ownerName);
                $targetProfile = new CustomerProfile($user, $firstName, $lastName);
                $this->em->persist($user);
                $this->em->persist($targetProfile);
                ++$report['ownersCreated'];
            }
        }
        $this->fillContact($targetProfile, $phone);

        // --- Legătura de proprietate ---
        if ($currentOwner === null) {
            $this->em->flush();
            $this->vehicles->assignOwner($vehicle, $targetProfile);
            ++$report['ownershipsCreated'];
        }
    }

    private function fillContact(CustomerProfile $profile, ?string $phone): void
    {
        if ($phone !== null && $phone !== '' && ($profile->phone() === null || $profile->phone() === '')) {
            $profile->updateContact($phone, $profile->address());
        }
    }

    /** @return array{0: ?string, 1: ?string} [prenume, nume] — convenția RO „Nume Prenume". */
    private function splitName(string $full): array
    {
        $parts = preg_split('/\s+/', trim($full)) ?: [];
        if (\count($parts) <= 1) {
            return [null, $full !== '' ? $full : null];
        }
        $lastName = array_shift($parts);

        return [implode(' ', $parts), $lastName];
    }

    /** Compară numele indiferent de ordinea cuvintelor („Ionescu Maria" = „Maria Ionescu"). */
    private function sameName(string $a, string $b): bool
    {
        $tokens = static function (string $s): array {
            $parts = preg_split('/\s+/', mb_strtolower(trim($s))) ?: [];
            sort($parts);

            return $parts;
        };

        return $tokens($a) === $tokens($b);
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

    /** @param list<string> $header @return array<string, int> */
    private function headerMap(array $header): array
    {
        $aliases = [
            'owner' => ['proprietar', 'numeproprietar', 'nume', 'client', 'tulajdonos', 'owner'],
            'plate' => ['numarinmatriculare', 'nrinmatriculare', 'numar', 'inmatriculare', 'rendszam', 'plate', 'platenumber'],
            'vin' => ['vin', 'seriesasiu', 'seriasasiului', 'alvazszam'],
            'make' => ['marca', 'marka', 'make'],
            'model' => ['model', 'modell'],
            'phone' => ['telefon', 'telefonszam', 'phone', 'nrtelefon'],
            'email' => ['email', 'emailcim', 'adresaemail'],
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
