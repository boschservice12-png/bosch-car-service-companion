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
    private const MAX_REPORTED_ERRORS = 200;

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

        // Performanță (fișiere reale de ~15.000 de rânduri): TOATE căutările
        // se fac pe hărți preîncărcate — zero interogări per rând, un singur
        // flush la final. Hărțile se actualizează pe măsură ce creăm entități,
        // deci rândurile duplicate din același fișier se rezolvă corect.
        $ctx = ['users' => [], 'vehicles' => [], 'owners' => []];
        foreach ($this->em->getRepository(User::class)->findAll() as $u) {
            $ctx['users'][mb_strtolower($u->getEmail())] = $u;
        }
        foreach ($this->em->createQuery('SELECT v FROM '.Vehicle::class.' v')->getResult() as $v) {
            $ctx['vehicles'][(string) $v->vin()] = $v;
        }
        $ownerships = $this->em->createQuery(
            'SELECT o, p, v FROM '.\App\Vehicle\Domain\VehicleOwnership::class.' o JOIN o.customerProfile p JOIN o.vehicle v WHERE o.active = true',
        )->getResult();
        foreach ($ownerships as $o) {
            $ctx['owners'][(string) $o->vehicle()->id()] = $o->customerProfile();
        }

        $report = [
            'totalRows' => 0,
            'ownersCreated' => 0,
            'vehiclesCreated' => 0,
            'vehiclesUpdated' => 0,
            'ownershipsCreated' => 0,
            'rowsWithoutVehicle' => 0,
            'vinInvalidSkipped' => 0,
            'errorCount' => 0,
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
                    $this->importRow($row, $map, $report, $ctx);
                } catch (\InvalidArgumentException $e) {
                    ++$report['errorCount'];
                    // Fișierele reale au mii de rânduri — raportul rămâne lizibil.
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

        $this->audit->record('import.owners_vehicles', 'Import', null, null, [
            'totalRows' => $report['totalRows'],
            'ownersCreated' => $report['ownersCreated'],
            'vehiclesCreated' => $report['vehiclesCreated'],
            'errors' => $report['errorCount'],
        ]);

        return $report;
    }

    /**
     * @param list<string>          $row
     * @param array<string, int>    $map
     * @param array<string, mixed>  $report
     */
    private function importRow(array $row, array $map, array &$report, array &$ctx): void
    {
        $cell = static fn (string $key): string => trim($row[$map[$key] ?? -1] ?? '');

        $ownerName = $cell('owner');
        $plate = $cell('plate');
        $vinRaw = $cell('vin');
        // Listele reale conțin și parteneri FĂRĂ vehicul — nu e o eroare,
        // doar nu avem ce importa pentru ei (se numără separat).
        if ($ownerName !== '' && $plate === '' && $vinRaw === '') {
            ++$report['rowsWithoutVehicle'];

            return;
        }
        if ($ownerName === '' || $plate === '' || $vinRaw === '') {
            throw new \InvalidArgumentException('Proprietarul, numărul de înmatriculare și VIN-ul sunt obligatorii.');
        }
        // Decizie de produs: datele rămân în ASM așa cum sunt — aplicația
        // înregistrează DOAR vehiculele cu VIN valid; restul se numără separat
        // (nu sunt erori de rezolvat, ci starea cunoscută a evidenței vechi).
        try {
            $vin = new Vin($vinRaw); // validare + uppercase
        } catch (\InvalidArgumentException) {
            ++$report['vinInvalidSkipped'];

            return;
        }

        $make = isset($map['make']) ? ($cell('make') ?: null) : null;
        $model = isset($map['model']) ? ($cell('model') ?: null) : null;
        // Exporturile ASM au marca+modelul într-o singură coloană („Tip autovehicul").
        if ($make === null && $model === null && ($combined = $cell('makemodel')) !== '') {
            $bits = preg_split('/\s+/', $combined, 2) ?: [];
            $make = $bits[0] ?? null;
            $model = $bits[1] ?? null;
        }
        // Datele reale depășesc uneori limitele coloanelor — trunchiem, nu picăm.
        $make = $make !== null ? mb_substr($make, 0, 80) : null;
        $model = $model !== null ? mb_substr($model, 0, 80) : null;
        // Mobilul e preferat (WhatsApp); telefonul fix rămâne rezervă.
        $phone = $cell('mobile') ?: ($cell('phone') ?: null);
        $phone = $phone !== null ? mb_substr($phone, 0, 32) : null;
        $postalAddress = trim(implode(', ', array_filter([$cell('city'), $cell('county')]))) ?: null;
        if (mb_strlen($plate) > 16) {
            throw new \InvalidArgumentException(sprintf('Număr de înmatriculare nevalid: „%s".', mb_substr($plate, 0, 24)));
        }
        $email = isset($map['email']) ? mb_strtolower($cell('email')) : '';
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            // În evidențele reale coloana E-mail conține adesea un telefon sau
            // resturi — nu picăm rândul: recuperăm ca telefon dacă seamănă,
            // altfel ignorăm valoarea.
            $digits = preg_replace('/\D/', '', $email) ?? '';
            if ($phone === null && \strlen($digits) >= 7 && preg_match('/^[\d\s+.\/-]+$/', $email) === 1) {
                $phone = mb_substr(trim($email), 0, 32);
            }
            $email = '';
        }

        // --- Identificarea proprietarului VIZAT, fără a crea sau modifica nimic ---
        // (verificarea de conflict trebuie să vină ÎNAINTEA oricărei scrieri).
        $emailUser = null;
        if ($email !== '') {
            $emailUser = $ctx['users'][$email] ?? null;
            if ($emailUser !== null && $emailUser->isServiceAdmin()) {
                throw new \InvalidArgumentException(sprintf(
                    'Emailul „%s" aparține unui cont de serviciu (admin) — rândul a fost sărit.',
                    $email,
                ));
            }
        }

        $vehicle = $ctx['vehicles'][$vin->value()] ?? null;
        $currentOwner = $vehicle !== null ? ($ctx['owners'][(string) $vehicle->id()] ?? null) : null;

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
            $ctx['vehicles'][$vin->value()] = $vehicle;
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
                $ctx['users'][mb_strtolower($address)] = $user;
                ++$report['ownersCreated'];
            }
        }
        $this->fillContact($targetProfile, $phone, $postalAddress);

        // --- Legătura de proprietate (fără flush per rând!) ---
        if ($currentOwner === null) {
            $this->em->persist(new \App\Vehicle\Domain\VehicleOwnership($vehicle, $targetProfile));
            $ctx['owners'][(string) $vehicle->id()] = $targetProfile;
            ++$report['ownershipsCreated'];
        }
    }

    private function fillContact(CustomerProfile $profile, ?string $phone, ?string $address = null): void
    {
        $newPhone = ($profile->phone() === null || $profile->phone() === '') && $phone !== null && $phone !== ''
            ? $phone : $profile->phone();
        $newAddress = ($profile->address() === null || $profile->address() === '') && $address !== null
            ? $address : $profile->address();
        if ($newPhone !== $profile->phone() || $newAddress !== $profile->address()) {
            $profile->updateContact($newPhone, $newAddress);
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
            'owner' => ['proprietar', 'numeproprietar', 'nume', 'client', 'tulajdonos', 'owner', 'partener'],
            'plate' => ['numarinmatriculare', 'nrinmatriculare', 'numar', 'inmatriculare', 'rendszam', 'plate', 'platenumber'],
            'vin' => ['vin', 'seriesasiu', 'seriasasiului', 'alvazszam'],
            'make' => ['marca', 'marka', 'make'],
            'model' => ['model', 'modell'],
            'phone' => ['telefon', 'telefonszam', 'phone', 'nrtelefon'],
            'mobile' => ['mobil', 'mobiltelefon', 'nrmobil'],
            'makemodel' => ['tipautovehicul', 'marcamodel', 'autovehicul'],
            'city' => ['localitate', 'oras', 'varos'],
            'county' => ['judet', 'megye'],
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
