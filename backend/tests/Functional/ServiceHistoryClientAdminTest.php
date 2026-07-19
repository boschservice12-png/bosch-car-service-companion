<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Document\Application\Message\ScanDocument;
use App\Document\Application\ScanDocumentHandler;
use App\Identity\Domain\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Slice vertical „istoric de service" end-to-end (CLIENT + ADMIN):
 *  - adminul creează o ciornă, o publică; datele ajung în DB;
 *  - clientul (sesiune separată) vede doar înregistrarea publicată a vehiculului său;
 *  - un alt client NU are acces (403);
 *  - corecția funcționează: originalul publicat nu poate fi rescris, iar corecția
 *    devine o intrare separată, ambele rămânând vizibile;
 *  - descărcarea documentelor este autorizată prin proprietarul vehiculului;
 *  - modificările sunt înregistrate în auditul aplicației.
 *
 * @group functional
 */
final class ServiceHistoryClientAdminTest extends ApiTestCase
{
    private const PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAAC0lEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    public function testAdminPublishesClientSeesIsolationAndCorrection(): void
    {
        $client = static::createClient();
        $ownerEmail = 'own-'.uniqid().'@example.test';
        $otherEmail = 'oth-'.uniqid().'@example.test';
        $adminEmail = 'ad-'.uniqid().'@bcsc.ro';
        $this->createAdmin($adminEmail, 'Parola1234');

        // CLIENT proprietar: cont + vehicul.
        $this->register($client, $ownerEmail);
        $this->register($client, $otherEmail);
        $this->login($client, $ownerEmail, 'Parola1234');
        $client->request('POST', '/api/vehicles', server: $this->json(), content: json_encode([
            'vin' => 'WBA3A5C50EF'.str_pad((string) random_int(100000, 999999), 6, '0'), 'plateNumber' => 'MS40SVC',
        ]));
        self::assertResponseStatusCodeSame(201);
        $vehicleId = json_decode((string) $client->getResponse()->getContent(), true)['id'];

        // ADMIN: creează o ciornă de service.
        $this->login($client, $adminEmail, 'Parola1234');
        $client->request('POST', "/api/admin/vehicles/$vehicleId/service-records", server: $this->json(), content: json_encode([
            'serviceDate' => '2026-05-10',
            'odometerKm' => 82000,
            'workType' => 'Revizie periodică',
            'workDescription' => 'Schimb ulei + filtre, verificare frâne.',
            'partsSummary' => 'Ulei 5W30 (5L), filtru ulei, filtru aer, filtru polen.',
            'laborCost' => 350.5,
            'totalAmount' => 1200,
            'warranty' => '12 luni / 20.000 km',
        ]));
        self::assertResponseStatusCodeSame(201);
        $record = json_decode((string) $client->getResponse()->getContent(), true);
        $recordId = $record['id'];
        self::assertSame('DRAFT', $record['status']);
        self::assertSame(350.5, $record['laborCost']);
        self::assertEqualsWithDelta(1200, $record['totalAmount'], 0.001);

        // ADMIN: încarcă și atașează un document (foto/PDF) pe ciornă.
        $client->request('POST', '/api/documents', files: ['file' => $this->tempUpload('factura.png', base64_decode(self::PNG_BASE64), 'image/png')]);
        self::assertResponseStatusCodeSame(201);
        $documentId = json_decode((string) $client->getResponse()->getContent(), true)['id'];
        $client->request('POST', "/api/admin/service-records/$recordId/documents", server: $this->json(), content: json_encode(['documentId' => $documentId]));
        self::assertResponseIsSuccessful();
        $this->scan($documentId);

        // CLIENT: ciorna NU este vizibilă înainte de publicare.
        $this->login($client, $ownerEmail, 'Parola1234');
        $client->request('GET', "/api/vehicles/$vehicleId/service-records");
        self::assertResponseIsSuccessful();
        self::assertCount(0, json_decode((string) $client->getResponse()->getContent(), true), 'Ciorna nu trebuie să fie vizibilă clientului.');

        // ADMIN: publică.
        $this->login($client, $adminEmail, 'Parola1234');
        $client->request('POST', "/api/admin/service-records/$recordId/publish");
        self::assertResponseIsSuccessful();
        self::assertSame('PUBLISHED', json_decode((string) $client->getResponse()->getContent(), true)['status']);

        // CLIENT: acum vede înregistrarea publicată, cu datele corecte.
        $this->login($client, $ownerEmail, 'Parola1234');
        $client->request('GET', "/api/vehicles/$vehicleId/service-records");
        self::assertResponseIsSuccessful();
        $list = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertCount(1, $list);
        self::assertSame('Revizie periodică', $list[0]['workType']);
        self::assertSame(82000, $list[0]['odometerKm']);
        self::assertCount(1, $list[0]['documents']);

        // CLIENT: descărcare autorizată a documentului atașat.
        $client->request('GET', "/api/service-records/$recordId/documents/$documentId");
        self::assertResponseIsSuccessful();
        self::assertSame('image/png', $client->getResponse()->headers->get('Content-Type'));

        // ALT CLIENT: nu are acces nici la listă, nici la document.
        $this->login($client, $otherEmail, 'Parola1234');
        $client->request('GET', "/api/vehicles/$vehicleId/service-records");
        self::assertResponseStatusCodeSame(403);
        $client->request('GET', "/api/service-records/$recordId/documents/$documentId");
        self::assertResponseStatusCodeSame(403);

        // ADMIN: o înregistrare publicată NU poate fi rescrisă „în tăcere".
        $this->login($client, $adminEmail, 'Parola1234');
        $client->request('PATCH', "/api/admin/service-records/$recordId", server: $this->json(), content: json_encode(['totalAmount' => 9999]));
        self::assertResponseStatusCodeSame(422, 'O înregistrare publicată nu poate fi suprascrisă.');

        // ADMIN: corecție fără motiv → 422 (motivul este obligatoriu, specificație).
        $client->request('POST', "/api/admin/service-records/$recordId/corrections");
        self::assertResponseStatusCodeSame(422, 'Corecția cere un motiv.');

        // ADMIN: corecție cu motiv → intrare nouă (ciornă) care referă originalul.
        $client->request('POST', "/api/admin/service-records/$recordId/corrections", server: $this->json(), content: json_encode([
            'reason' => 'Total greșit — factură refăcută.',
        ]));
        self::assertResponseStatusCodeSame(201);
        $correction = json_decode((string) $client->getResponse()->getContent(), true);
        $correctionId = $correction['id'];
        self::assertSame($recordId, $correction['correctionOfId']);
        self::assertSame('DRAFT', $correction['status']);

        // Corecția se editează (e ciornă) și se publică.
        $client->request('PATCH', "/api/admin/service-records/$correctionId", server: $this->json(), content: json_encode(['totalAmount' => 1500]));
        self::assertResponseIsSuccessful();
        $client->request('POST', "/api/admin/service-records/$correctionId/publish");
        self::assertResponseIsSuccessful();

        // CLIENT: vede AMBELE — originalul (marcat „corectat") și corecția.
        $this->login($client, $ownerEmail, 'Parola1234');
        $client->request('GET', "/api/vehicles/$vehicleId/service-records");
        self::assertResponseIsSuccessful();
        $both = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertCount(2, $both, 'Originalul și corecția rămân ambele vizibile.');

        $byId = [];
        foreach ($both as $r) {
            $byId[$r['id']] = $r;
        }
        self::assertTrue($byId[$recordId]['corrected'], 'Originalul este marcat drept corectat.');
        self::assertSame('CORRECTED', $byId[$recordId]['status'], 'Originalul trece în starea CORRECTED (specificație).');
        self::assertSame($recordId, $byId[$correctionId]['correctionOfId']);
        self::assertSame('Total greșit — factură refăcută.', $byId[$correctionId]['correctionReason']);
        self::assertEqualsWithDelta(1500, $byId[$correctionId]['totalAmount'], 0.001);

        // PDF: pentru o intrare și pentru întregul istoric (specificație).
        $client->request('GET', "/api/service-records/$correctionId/pdf");
        self::assertResponseIsSuccessful();
        self::assertSame('application/pdf', $client->getResponse()->headers->get('Content-Type'));
        self::assertStringStartsWith('%PDF', (string) $client->getResponse()->getContent());

        $client->request('GET', "/api/vehicles/$vehicleId/service-records/pdf");
        self::assertResponseIsSuccessful();
        $historyPdf = (string) $client->getResponse()->getContent();
        self::assertStringStartsWith('%PDF', $historyPdf);
        self::assertStringContainsString('CORECTAT', $historyPdf, 'Istoricul marchează intrarea corectată.');

        // AUDIT: publicările au fost înregistrate (original + corecție).
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $published = (int) $em->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM audit_logs WHERE action = ?',
            ['service_record.published'],
        );
        self::assertGreaterThanOrEqual(2, $published, 'Publicările trebuie să apară în audit.');
    }

    private function scan(string $documentId): void
    {
        /** @var ScanDocumentHandler $handler */
        $handler = static::getContainer()->get(ScanDocumentHandler::class);
        $handler(new ScanDocument($documentId));
    }

    private function tempUpload(string $name, string $contents, string $mime): UploadedFile
    {
        $path = sys_get_temp_dir().'/bcsc_'.uniqid().'_'.$name;
        file_put_contents($path, $contents);

        return new UploadedFile($path, $name, $mime, null, true);
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

    private function register(KernelBrowser $client, string $email): void
    {
        $client->request('POST', '/api/auth/register', server: $this->json(), content: json_encode([
            'email' => $email, 'password' => 'Parola1234', 'consent' => true,
        ]));
        self::assertResponseStatusCodeSame(201);
    }

    private function login(KernelBrowser $client, string $email, string $password): void
    {
        $client->request('POST', '/api/auth/login', server: $this->json(), content: json_encode([
            'email' => $email, 'password' => $password,
        ]));
        self::assertResponseIsSuccessful();
    }
}
