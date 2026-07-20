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
 * Importul bazei de clienți din Excel (.xlsx real, construit în test) și CSV:
 * proprietar + număr + VIN + marcă/model (+ telefon/email opționale).
 *  - creează conturi, vehicule și legături de proprietate;
 *  - reimportul este idempotent (fără dubluri);
 *  - VIN invalid → eroare per rând, restul rândurilor trec;
 *  - același VIN cu alt proprietar → conflict raportat, nimic schimbat;
 *  - doar service-ul are acces la endpoint.
 *
 * @group functional
 */
final class ClientImportTest extends ApiTestCase
{
    public function testXlsxImportCreatesOwnersVehiclesAndIsIdempotent(): void
    {
        $client = static::createClient();
        $adminEmail = 'ad-'.uniqid().'@bcsc.ro';
        $this->createAdmin($adminEmail, 'Parola1234');
        $this->login($client, $adminEmail, 'Parola1234');

        $suffix = strtoupper(substr(uniqid(), -4));
        $vin1 = 'WBA3A5C50EF'.str_pad((string) random_int(100000, 999999), 6, '0');
        $vin2 = 'UU1DUSTER8'.str_pad((string) random_int(1000000, 9999999), 7, '0');
        $emailRow = 'popescu-'.uniqid().'@example.test';

        $rows = [
            ['Proprietar', 'Număr înmatriculare', 'VIN', 'Marcă', 'Model', 'Telefon', 'Email'],
            ['Popescu Ion', 'MS 10 '.$suffix, $vin1, 'BMW', 'Seria 3', '+40711111111', $emailRow],
            ['Ionescu Maria', 'MS 20 '.$suffix, $vin2, 'Dacia', 'Duster', '', ''],
            ['Rând Invalid', 'MS 30 '.$suffix, 'VINPREASCURT', 'Ford', 'Focus', '', ''],
        ];

        $xlsx = $this->buildXlsx($rows);
        $client->request('POST', '/api/admin/import/clients', files: ['file' => $this->upload($xlsx, 'clienti.xlsx')]);
        self::assertResponseIsSuccessful();
        $report = json_decode((string) $client->getResponse()->getContent(), true);

        self::assertSame(3, $report['totalRows']);
        self::assertSame(2, $report['ownersCreated']);
        self::assertSame(2, $report['vehiclesCreated']);
        self::assertSame(2, $report['ownershipsCreated']);
        // Decizie: VIN-ul nevalid NU e eroare — rândul rămâne doar în ASM.
        self::assertSame(1, $report['vinInvalidSkipped'], 'VIN-ul invalid se numără separat.');
        self::assertCount(0, $report['errors']);

        // Vehiculul și proprietarul există și sunt legați.
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $conn = $em->getConnection();
        self::assertSame(1, (int) $conn->fetchOne('SELECT COUNT(*) FROM vehicles WHERE vin = ?', [$vin1]));
        self::assertSame(1, (int) $conn->fetchOne('SELECT COUNT(*) FROM users WHERE email = ?', [$emailRow]));

        // Reimport: idempotent — nimic nou, doar actualizări.
        $client->request('POST', '/api/admin/import/clients', files: ['file' => $this->upload($this->buildXlsx($rows), 'clienti.xlsx')]);
        self::assertResponseIsSuccessful();
        $second = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame(0, $second['ownersCreated'], 'Reimportul nu creează proprietari noi.');
        self::assertSame(0, $second['vehiclesCreated'], 'Reimportul nu creează vehicule noi.');
        self::assertSame(2, $second['vehiclesUpdated']);
        self::assertSame(0, $second['ownershipsCreated']);
        self::assertSame(1, (int) $conn->fetchOne('SELECT COUNT(*) FROM vehicles WHERE vin = ?', [$vin1]), 'Fără dubluri de vehicul.');

        // Conflict: același VIN, alt proprietar → raportat și NIMIC nu se schimbă:
        // nici numărul de înmatriculare, nici vreun cont nou de proprietar.
        $usersBefore = (int) $conn->fetchOne('SELECT COUNT(*) FROM users');
        $conflict = [
            ['Proprietar', 'Numar inmatriculare', 'VIN'],
            ['Alt Proprietar', 'MS 99 '.$suffix, $vin1],
        ];
        $client->request('POST', '/api/admin/import/clients', files: ['file' => $this->upload($this->buildXlsx($conflict), 'conflict.xlsx')]);
        self::assertResponseIsSuccessful();
        $third = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertCount(1, $third['errors']);
        self::assertStringContainsString('Conflict', $third['errors'][0]['message']);
        self::assertSame(0, $third['vehiclesUpdated'], 'Rândul în conflict nu actualizează vehiculul.');
        self::assertSame(
            'MS 10 '.$suffix,
            (string) $conn->fetchOne('SELECT plate_number FROM vehicles WHERE vin = ?', [$vin1]),
            'Numărul de înmatriculare NU se schimbă la conflict.',
        );
        self::assertSame($usersBefore, (int) $conn->fetchOne('SELECT COUNT(*) FROM users'), 'Fără conturi orfane la conflict.');

        // Email de ADMIN în fișier → eroare per rând, vehiculul nu se creează.
        $adminRow = [
            ['Proprietar', 'Numar inmatriculare', 'VIN', 'Email'],
            ['Admin Deghizat', 'MS 77 '.$suffix, 'WVWZZZ1KZAW77'.str_pad((string) random_int(1000, 9999), 4, '0'), $adminEmail],
        ];
        $client->request('POST', '/api/admin/import/clients', files: ['file' => $this->upload($this->buildXlsx($adminRow), 'admin.xlsx')]);
        self::assertResponseIsSuccessful();
        $fourth = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertCount(1, $fourth['errors'], 'Emailul de admin este respins per rând.');
        self::assertStringContainsString('admin', $fourth['errors'][0]['message']);
        self::assertSame(0, $fourth['vehiclesCreated'], 'Rândul cu email de admin nu creează nimic.');
    }

    /**
     * Decizia de produs P1-02: clientul se înregistrează cu email + parolă și
     * își vede propriile date. Un cont creat de importul Excel (fără parolă)
     * este REVENDICAT la înregistrare — clientul își vede imediat vehiculele —
     * iar un cont deja activat nu poate fi suprascris prin re-înregistrare.
     */
    public function testImportedAccountIsClaimedByRegistration(): void
    {
        $client = static::createClient();
        $adminEmail = 'ad-'.uniqid().'@bcsc.ro';
        $this->createAdmin($adminEmail, 'Parola1234');
        $this->login($client, $adminEmail, 'Parola1234');

        $suffix = strtoupper(substr(uniqid(), -4));
        $vin = 'WVWZZZ1KZAW'.str_pad((string) random_int(100000, 999999), 6, '0');
        $ownerEmail = 'claim-'.uniqid().'@example.test';
        $csv = "Proprietar;Numar inmatriculare;VIN;Marca;Model;Email\n"
            ."Kiss Andrei;MS 71 $suffix;$vin;Volkswagen;Golf;$ownerEmail\n";
        $client->request('POST', '/api/admin/import/clients', files: ['file' => $this->upload($csv, 'clienti.csv')]);
        self::assertResponseIsSuccessful();

        // Înainte de înregistrare, contul importat nu se poate autentifica.
        $client->request('POST', '/api/auth/login', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'email' => $ownerEmail, 'password' => 'Parola1234',
        ]));
        self::assertResponseStatusCodeSame(401, 'Fără parolă setată, loginul e refuzat.');

        // Fără număr de înmatriculare → 422 (dovada proprietății e obligatorie).
        $client->request('POST', '/api/auth/register', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'email' => $ownerEmail, 'password' => 'Parola1234', 'consent' => true,
        ]));
        self::assertResponseStatusCodeSame(422, 'Contul importat nu se revendică fără numărul de înmatriculare.');

        // Cu număr greșit → 422, contul rămâne nerevendicat.
        $client->request('POST', '/api/auth/register', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'email' => $ownerEmail, 'password' => 'Parola1234', 'consent' => true, 'plateNumber' => 'B 99 XXX',
        ]));
        self::assertResponseStatusCodeSame(422, 'Un număr de înmatriculare străin nu deblochează contul.');
        $client->request('POST', '/api/auth/login', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'email' => $ownerEmail, 'password' => 'Parola1234',
        ]));
        self::assertResponseStatusCodeSame(401, 'După încercările eșuate, parola tot nu e setată.');

        // Cu numărul corect (litere mici, fără spații — se normalizează) → revendicat.
        $client->request('POST', '/api/auth/register', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'email' => $ownerEmail, 'password' => 'Parola1234', 'consent' => true,
            'plateNumber' => strtolower('ms71'.$suffix),
        ]));
        self::assertResponseStatusCodeSame(201);
        $this->login($client, $ownerEmail, 'Parola1234');

        // Clientul își vede imediat vehiculul importat, cu numele din evidență.
        $client->request('GET', '/api/vehicles');
        self::assertResponseIsSuccessful();
        $vehicles = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertCount(1, $vehicles);
        self::assertSame($vin, $vehicles[0]['vin']);

        $client->request('GET', '/api/me');
        $me = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame('Andrei Kiss', $me['name'], 'Numele din importul service-ului rămâne sursa de adevăr.');

        // Contul, odată activat, nu mai poate fi „re-revendicat" cu altă parolă.
        $client->request('POST', '/api/auth/register', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'email' => $ownerEmail, 'password' => 'AltaParola999', 'consent' => true,
        ]));
        self::assertResponseStatusCodeSame(422, 'Un cont activat nu se suprascrie prin re-înregistrare.');

        // Auditul consemnează revendicarea.
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $count = (int) $em->getConnection()->fetchOne("SELECT COUNT(*) FROM audit_logs WHERE action = 'user.import_account_claimed'");
        self::assertGreaterThanOrEqual(1, $count);
    }

    public function testCsvImportAndAccessControl(): void
    {
        $client = static::createClient();

        // CLIENT obișnuit: 403.
        $clientEmail = 'cl-'.uniqid().'@example.test';
        $this->register($client, $clientEmail);
        $this->login($client, $clientEmail, 'Parola1234');
        $csv = "Proprietar;Numar inmatriculare;VIN\nTest Om;MS 11 CSV;WBA3A5C50EF000111\n";
        $client->request('POST', '/api/admin/import/clients', files: ['file' => $this->upload($csv, 'clienti.csv')]);
        self::assertResponseStatusCodeSame(403, 'Importul este doar pentru service.');

        // ADMIN: CSV cu punct-și-virgulă funcționează.
        $adminEmail = 'ad-'.uniqid().'@bcsc.ro';
        $this->createAdmin($adminEmail, 'Parola1234');
        $this->login($client, $adminEmail, 'Parola1234');
        $vin = 'VF1RFB00X6'.str_pad((string) random_int(100000, 999999), 7, '0');
        $csv = "Proprietar;Numar inmatriculare;VIN;Marca;Model\nSzabo Csaba;MS 44 CSV;$vin;Renault;Clio\n";
        $client->request('POST', '/api/admin/import/clients', files: ['file' => $this->upload($csv, 'clienti.csv')]);
        self::assertResponseIsSuccessful();
        $report = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame(1, $report['ownersCreated']);
        self::assertSame(1, $report['vehiclesCreated']);
    }

    /** Construiește un .xlsx real (ZIP + XML minimal, cu inline strings). @param list<list<string>> $rows */
    private function buildXlsx(array $rows): string
    {
        $sheetRows = '';
        foreach ($rows as $r => $cells) {
            $sheetRows .= '<row r="'.($r + 1).'">';
            foreach ($cells as $c => $value) {
                $ref = $this->columnLetter($c).($r + 1);
                $sheetRows .= '<c r="'.$ref.'" t="inlineStr"><is><t>'.htmlspecialchars($value, ENT_XML1).'</t></is></c>';
            }
            $sheetRows .= '</row>';
        }

        $path = tempnam(sys_get_temp_dir(), 'bcsc_xlsx_');
        \assert($path !== false);
        $zip = new \ZipArchive();
        $zip->open($path, \ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheets><sheet name="Clienti" sheetId="1" r:id="rId1" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
        $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'.$sheetRows.'</sheetData></worksheet>');
        $zip->close();

        $contents = (string) file_get_contents($path);
        @unlink($path);

        return $contents;
    }

    private function columnLetter(int $index): string
    {
        $letter = '';
        ++$index;
        while ($index > 0) {
            $mod = ($index - 1) % 26;
            $letter = \chr(65 + $mod).$letter;
            $index = intdiv($index - 1, 26);
        }

        return $letter;
    }

    private function upload(string $contents, string $name): UploadedFile
    {
        $path = sys_get_temp_dir().'/bcsc_up_'.uniqid().'_'.$name;
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

    private function register(KernelBrowser $client, string $email): void
    {
        $client->request('POST', '/api/auth/register', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'email' => $email, 'password' => 'Parola1234', 'consent' => true,
        ]));
        self::assertResponseStatusCodeSame(201);
    }

    private function login(KernelBrowser $client, string $email, string $password): void
    {
        $client->request('POST', '/api/auth/login', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'email' => $email, 'password' => $password,
        ]));
        self::assertResponseIsSuccessful();
    }
}
