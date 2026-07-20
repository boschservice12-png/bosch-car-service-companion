<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Identity\Domain\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Lanțul complet de import din exporturile REALE ale evidenței (ASM), în
 * format .xls binar (BIFF8), cu antetele lor exacte:
 *  1. „lista parteneri" — Partener / Nr. înmatriculare / Serie şasiu /
 *     Tip autovehicul (marcă+model împreună) / Telefon / Mobil / E-mail;
 *  2. „PersonalManopere" — Data (număr serial Excel!) / Tip interventie /
 *     Serie şasiu → carnetul de service;
 *  3. „report ITP/RCA" — Denumire alertă / Data alertei / Nr. înmatriculare
 *     → scadențe (doar ITP/RCA; anvelopele se sar; subsolul se ignoră).
 *
 * @group functional
 */
final class AsmXlsImportTest extends ApiTestCase
{
    public function testAsmThreeFileChain(): void
    {
        $client = static::createClient();
        $adminEmail = 'ad-'.uniqid().'@bcsc.ro';
        $this->createAdmin($adminEmail, 'Parola1234');
        $this->login($client, $adminEmail);

        // ---- Pasul 1: parteneri (.xls) ----
        $client->request('POST', '/api/admin/import/clients', files: ['file' => $this->fixture('asm_parteneri.xls')]);
        self::assertResponseIsSuccessful();
        $r1 = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame(2, $r1['totalRows']);
        self::assertSame(2, $r1['ownersCreated']);
        self::assertSame(2, $r1['vehiclesCreated']);
        self::assertSame(0, $r1['errorCount']);

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $row = $em->getConnection()->fetchAssociative(
            "SELECT v.make, v.model, p.phone, p.address FROM vehicles v
             JOIN vehicle_ownerships o ON o.vehicle_id = v.id AND o.active = true
             JOIN customer_profiles p ON p.id = o.customer_profile_id
             WHERE v.plate_number = 'MS 77 ASM'",
        );
        self::assertNotFalse($row);
        self::assertSame('DACIA', $row['make'], '„Tip autovehicul" se desparte în marcă…');
        self::assertSame('Logan II', $row['model'], '…și model.');
        self::assertSame('0744555666', $row['phone'], 'Mobilul are prioritate față de telefonul fix (WhatsApp).');
        self::assertSame('Târgu Mureș, MURES', $row['address'], 'Localitatea + județul devin adresa.');

        // ---- Pasul 2: manopere (.xls, date ca numere seriale Excel) ----
        $client->request('POST', '/api/admin/import/service-history', files: ['file' => $this->fixture('asm_manopere.xls')]);
        self::assertResponseIsSuccessful();
        $r2 = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame([], $r2['errors'], 'Fără erori la manopere: '.json_encode($r2));
        self::assertSame(3, $r2['recordsPublished'], 'Toate cele 3 lucrări intră în carnet.');

        $dates = $em->getConnection()->fetchFirstColumn(
            "SELECT sr.service_date FROM service_records sr JOIN vehicles v ON v.id = sr.vehicle_id
             WHERE v.plate_number = 'MS 77 ASM' ORDER BY sr.service_date",
        );
        self::assertCount(2, $dates);
        self::assertStringStartsWith('2026-07-01', (string) $dates[0], 'Serialul Excel 46204 = 1 iulie 2026.');

        // ---- Pasul 3: report ITP/RCA (.xls) ----
        $client->request('POST', '/api/admin/import/deadlines', files: ['file' => $this->fixture('asm_report_itp_rca.xls')]);
        self::assertResponseIsSuccessful();
        $r3 = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame(4, $r3['totalRows'], 'Subsolul („4 rânduri") nu se numără.');
        self::assertSame(2, $r3['deadlinesCreated'], 'ITP + RCA pentru MS 77 ASM.');
        self::assertSame(1, $r3['nonItpRcaSkipped'], 'Custodia de anvelope se sare.');
        self::assertSame(1, $r3['errorCount'], 'B 99 XYZ nu există în evidență.');
        self::assertStringContainsString('B 99 XYZ', $r3['errors'][0]['message']);

        $deadlines = $em->getConnection()->fetchAllAssociative(
            "SELECT d.type, d.expires_at, d.source FROM vehicle_deadlines d JOIN vehicles v ON v.id = d.vehicle_id
             WHERE v.plate_number = 'MS 77 ASM' ORDER BY d.type",
        );
        self::assertCount(2, $deadlines);
        self::assertSame('ITP', $deadlines[0]['type']);
        self::assertStringStartsWith('2026-10-05', (string) $deadlines[0]['expires_at'], 'Serialul 46300 = 5 octombrie 2026.');
        self::assertSame('IMPORT', $deadlines[0]['source']);

        // Idempotență: re-importul raportului nu creează nimic nou.
        $client->request('POST', '/api/admin/import/deadlines', files: ['file' => $this->fixture('asm_report_itp_rca.xls')]);
        $again = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame(0, $again['deadlinesCreated']);
        self::assertSame(2, $again['rowsSkipped'], 'Datele identice se sar.');

        // Clientul revendicat vede scadențele importate (lanț complet).
        $client->request('POST', '/api/auth/register', server: $this->json(), content: json_encode([
            'email' => 'popescu.asm@example.test', 'password' => 'Parola1234', 'consent' => true,
            'plateNumber' => 'ms77asm',
        ]));
        self::assertResponseStatusCodeSame(201, 'Contul importat se revendică cu numărul de înmatriculare.');
        $this->login($client, 'popescu.asm@example.test');
        $client->request('GET', '/api/vehicles');
        $vehicles = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertCount(1, $vehicles);
        $client->request('GET', "/api/vehicles/{$vehicles[0]['id']}/deadlines");
        self::assertCount(2, json_decode((string) $client->getResponse()->getContent(), true));
    }

    /**
     * Protecțiile importului de scadențe:
     *  - aceeași alertă pe două rânduri NU creează duplicate (harta din fișier
     *    acoperă entitățile încă neflush-uite);
     *  - un număr de înmatriculare purtat de DOUĂ vehicule active e ambiguu →
     *    eroare de rând, nu o alegere tăcută;
     *  - un .xls trunchiat/corupt → 422 (eroare de validare), nu 500.
     */
    public function testDeadlineImportDuplicateRowsAmbiguousPlateAndCorruptXls(): void
    {
        $client = static::createClient();
        $adminEmail = 'ad-'.uniqid().'@bcsc.ro';
        $this->createAdmin($adminEmail, 'Parola1234');
        $this->login($client, $adminEmail);

        $suffix = strtoupper(substr(uniqid(), -4));
        $csv = "Proprietar;Numar inmatriculare;VIN\n"
            ."Dup Unu;MS 88 $suffix;WVWZZZ1KZAW".random_int(100000, 999999)."\n"
            ."Dup Doi;MS 88 $suffix;WVWZZZ1KZAW".random_int(100000, 999999)."\n"
            ."Uni Om;MS 89 $suffix;WVWZZZ1KZAW".random_int(100000, 999999)."\n";
        $client->request('POST', '/api/admin/import/clients', files: ['file' => $this->inlineUpload($csv, 'clienti.csv')]);
        self::assertResponseIsSuccessful();

        $deadlineCsv = "Denumire alertă;Data alertei;Nr. înmatriculare\n"
            ."ITP;01.10.2026;MS 89 $suffix\n"
            ."ITP;15.11.2026;MS 89 $suffix\n"
            ."ITP;01.10.2026;MS 88 $suffix\n";
        $client->request('POST', '/api/admin/import/deadlines', files: ['file' => $this->inlineUpload($deadlineCsv, 'alerte.csv')]);
        self::assertResponseIsSuccessful();
        $report = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame(1, $report['deadlinesCreated'], 'Primul rând creează scadența.');
        self::assertSame(1, $report['deadlinesUpdated'], 'Al doilea rând ACTUALIZEAZĂ aceeași scadență (nu duplică).');
        self::assertSame(1, $report['errorCount'], 'Numărul purtat de două vehicule active e ambiguu.');
        self::assertStringContainsString('mai multor vehicule active', $report['errors'][0]['message']);

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $count = (int) $em->getConnection()->fetchOne(
            "SELECT COUNT(*) FROM vehicle_deadlines d JOIN vehicles v ON v.id = d.vehicle_id
             WHERE v.plate_number = ? AND d.type = 'ITP'",
            ["MS 89 $suffix"],
        );
        self::assertSame(1, $count, 'O singură scadență ITP, cu data ultimului rând.');

        // Fișier .xls trunchiat (antetul CFB e valid, restul lipsește) → 422.
        $truncated = substr((string) file_get_contents(\dirname(__DIR__).'/Fixtures/asm_report_itp_rca.xls'), 0, 600);
        $client->request('POST', '/api/admin/import/deadlines', files: ['file' => $this->inlineUpload($truncated, 'corupt.xls')]);
        self::assertResponseStatusCodeSame(422, 'Fișierul corupt e o eroare de validare, nu una de server.');
    }

    private function inlineUpload(string $content, string $name): UploadedFile
    {
        $tmp = sys_get_temp_dir().'/bcsc_in_'.uniqid().'_'.$name;
        file_put_contents($tmp, $content);

        return new UploadedFile($tmp, $name, null, null, true);
    }

    private function fixture(string $name): UploadedFile
    {
        $src = \dirname(__DIR__).'/Fixtures/'.$name;
        $tmp = sys_get_temp_dir().'/bcsc_fx_'.uniqid().'_'.$name;
        copy($src, $tmp);

        return new UploadedFile($tmp, $name, null, null, true);
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

    /** @return array<string, string> */
    private function json(): array
    {
        return ['CONTENT_TYPE' => 'application/json'];
    }

    private function login(KernelBrowser $client, string $email): void
    {
        $client->request('POST', '/api/auth/login', server: $this->json(), content: json_encode([
            'email' => $email, 'password' => 'Parola1234',
        ]));
        self::assertResponseIsSuccessful();
    }
}
