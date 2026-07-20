<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Identity\Domain\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Importul istoricului de reparații din Excel, legat de vehicul prin VIN:
 *  - rândurile complete devin PUBLISHED și clientul (proprietarul) le vede;
 *  - rândurile incomplete rămân DRAFT;
 *  - VIN necunoscut → eroare per rând (întâi se importă clienții);
 *  - reimportul este idempotent (număr comandă sau dată+total);
 *  - sume RO („1.250,50") și date zz.ll.aaaa acceptate.
 *
 * @group functional
 */
final class ServiceHistoryImportTest extends ApiTestCase
{
    public function testHistoryImportLinksByVinPublishesAndIsIdempotent(): void
    {
        $client = static::createClient();
        $adminEmail = 'ad-'.uniqid().'@bcsc.ro';
        $this->createAdmin($adminEmail, 'Parola1234');
        $this->login($client, $adminEmail, 'Parola1234');

        $vin = 'WBA3A5C50EF'.str_pad((string) random_int(100000, 999999), 6, '0');
        $ownerEmail = 'owner-'.uniqid().'@example.test';

        // Pasul 1: clientul + vehiculul (import propriu).
        $clientsCsv = "Proprietar;Numar inmatriculare;VIN;Marca;Model;Email\nPopescu Ion;MS 77 IST;$vin;BMW;Seria 3;$ownerEmail\n";
        $client->request('POST', '/api/admin/import/clients', files: ['file' => $this->upload($clientsCsv, 'clienti.csv')]);
        self::assertResponseIsSuccessful();

        // Pasul 2: istoricul de reparații (rândul WO-2025-0442 apare de DOUĂ ori
        // în același fișier — dublura trebuie sărită, nu importată de două ori).
        $historyCsv = implode("\n", [
            'VIN;Data;Kilometraj;Lucrare;Descriere;Piese;Manopera;Total;Garantie;Numar comanda',
            "$vin;18.11.2025;78400;Revizie;Schimb ulei si filtre;Ulei 5W30, filtru ulei;350,00;1.250,50;12 luni;WO-2025-0442",
            "$vin;18.11.2025;78400;Revizie;Schimb ulei si filtre;Ulei 5W30, filtru ulei;350,00;1.250,50;12 luni;WO-2025-0442",
            "$vin;03.04.2025;;Franare;;;;860;;WO-2025-0119",
            'WBA0000000000UNKN;01.01.2025;10000;Test;Vehicul inexistent;;;100;;WO-X',
        ])."\n";
        $client->request('POST', '/api/admin/import/service-history', files: ['file' => $this->upload($historyCsv, 'istoric.csv')]);
        self::assertResponseIsSuccessful();
        $report = json_decode((string) $client->getResponse()->getContent(), true);

        self::assertSame(4, $report['totalRows']);
        // Exporturile ASM nu au km/descriere — data + lucrarea ajung pentru
        // publicare (decizie de produs la importul „PersonalManopere").
        self::assertSame(2, $report['recordsPublished'], 'Rândurile cu dată + lucrare devin PUBLISHED (fără dubluri).');
        self::assertSame(1, $report['recordsSkipped'], 'Dublura din ACELAȘI fișier este sărită.');
        self::assertSame(0, $report['recordsDraft']);
        self::assertCount(1, $report['errors'], 'VIN necunoscut → eroare per rând.');
        self::assertStringContainsString('nu există', $report['errors'][0]['message']);

        // Reimport: idempotent (numărul comenzii deduplică).
        $client->request('POST', '/api/admin/import/service-history', files: ['file' => $this->upload($historyCsv, 'istoric.csv')]);
        self::assertResponseIsSuccessful();
        $second = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame(0, $second['recordsPublished']);
        self::assertSame(0, $second['recordsDraft']);
        self::assertSame(3, $second['recordsSkipped'], 'Reimportul sare intrările existente (și dublura).');

        // Suma și data au fost interpretate corect.
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $conn = $em->getConnection();
        $row = $conn->fetchAssociative(
            'SELECT total_bani, work_order_number, status FROM service_records r JOIN vehicles v ON v.id = r.vehicle_id WHERE v.vin = ? AND r.work_order_number = ?',
            [$vin, 'WO-2025-0442'],
        );
        self::assertNotFalse($row);
        self::assertSame(125050, (int) $row['total_bani'], '„1.250,50" → 125050 bani.');
        self::assertSame('PUBLISHED', $row['status']);

        // Proprietarul (clientul) vede intrarea publicată în istoricul lui.
        $this->setPassword($ownerEmail, 'Parola1234');
        $this->login($client, $ownerEmail, 'Parola1234');
        $client->request('GET', '/api/vehicles');
        self::assertResponseIsSuccessful();
        $vehicles = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertCount(1, $vehicles);
        $vehicleId = $vehicles[0]['id'];

        $client->request('GET', "/api/vehicles/$vehicleId/service-records");
        self::assertResponseIsSuccessful();
        $records = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertCount(2, $records, 'Clientul vede ambele intrări publicate.');
        $records = array_values(array_filter($records, fn (array $r) => $r['workOrderNumber'] === 'WO-2025-0442'));
        self::assertSame('WO-2025-0442', $records[0]['workOrderNumber']);
        self::assertEqualsWithDelta(1250.5, $records[0]['totalAmount'], 0.001);
    }

    private function setPassword(string $email, string $password): void
    {
        $c = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $c->get(EntityManagerInterface::class);
        /** @var UserPasswordHasherInterface $hasher */
        $hasher = $c->get(UserPasswordHasherInterface::class);
        $user = $em->getRepository(User::class)->findOneBy(['email' => $email]);
        self::assertInstanceOf(User::class, $user);
        $user->setPasswordHash($hasher->hashPassword($user, $password));
        $em->flush();
    }

    private function upload(string $contents, string $name): UploadedFile
    {
        $path = sys_get_temp_dir().'/bcsc_hist_'.uniqid().'_'.$name;
        file_put_contents($path, $contents);

        return new UploadedFile($path, $name, null, null, true);
    }

    private function createAdmin(string $email, string $password): void
    {
        $c = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $c->get(EntityManagerInterface::class);
        /** @var UserPasswordHasherInterface $hasher */
        $hasher = $c->get(UserPasswordHasherInterface::class);
        $admin = new User($email, User::ROLE_SERVICE_ADMIN);
        $admin->setPasswordHash($hasher->hashPassword($admin, $password));
        $em->persist($admin);
        $em->flush();
    }

    private function login(KernelBrowser $client, string $email, string $password): void
    {
        $client->request('POST', '/api/auth/login', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'email' => $email, 'password' => $password,
        ]));
        self::assertResponseIsSuccessful();
    }
}
