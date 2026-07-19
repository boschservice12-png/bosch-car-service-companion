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
 * Slice vertical „asistență rutieră" end-to-end (CLIENT + ADMIN):
 *  - clientul deschide o cerere (locație, problemă, mobilitate, siguranță, telefon, foto);
 *  - un alt client NU are acces (403);
 *  - service-ul o preia (FORWARDED) și clientul vede noua stare;
 *  - descărcarea atașamentelor e autorizată prin proprietar;
 *  - anularea de client e permisă doar cât timp cererea e nouă;
 *  - operațiunile ajung în audit.
 *
 * @group functional
 */
final class RoadsideClientAdminTest extends WebTestCase
{
    private const PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAAC0lEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    public function testRoadsideRequestFlowWithIsolationAndStatus(): void
    {
        $client = static::createClient();
        $ownerEmail = 'own-'.uniqid().'@example.test';
        $otherEmail = 'oth-'.uniqid().'@example.test';
        $adminEmail = 'ad-'.uniqid().'@bcsc.ro';
        $this->createAdmin($adminEmail, 'Parola1234');

        $this->register($client, $ownerEmail);
        $this->register($client, $otherEmail);
        $this->login($client, $ownerEmail, 'Parola1234');

        // Atașament (foto de la fața locului).
        $client->request('POST', '/api/documents', files: ['file' => $this->tempUpload('loc.png', base64_decode(self::PNG_BASE64), 'image/png')]);
        self::assertResponseStatusCodeSame(201);
        $documentId = json_decode((string) $client->getResponse()->getContent(), true)['id'];

        // CLIENT: deschide cererea de asistență.
        $client->request('POST', '/api/roadside-requests', server: $this->json(), content: json_encode([
            'location' => 'DN13, km 12, lângă Sighișoara',
            'problem' => 'Pană de cauciuc, roata dreapta față.',
            'mobility' => 'NOT_DRIVABLE',
            'safety' => 'AT_RISK',
            'phone' => '+40711223344',
            'documentIds' => [$documentId],
        ]));
        self::assertResponseStatusCodeSame(201);
        $req = json_decode((string) $client->getResponse()->getContent(), true);
        $requestId = $req['id'];
        self::assertSame('SUBMITTED', $req['status']);
        self::assertSame('NOT_DRIVABLE', $req['mobility']);
        self::assertCount(1, $req['documents']);
        $this->scan($documentId);

        // CLIENT: descărcare autorizată a atașamentului.
        $client->request('GET', "/api/roadside-requests/$requestId/documents/$documentId");
        self::assertResponseIsSuccessful();
        self::assertSame('image/png', $client->getResponse()->headers->get('Content-Type'));

        // ALT CLIENT: fără acces.
        $this->login($client, $otherEmail, 'Parola1234');
        $client->request('GET', '/api/roadside-requests');
        self::assertResponseIsSuccessful();
        self::assertCount(0, json_decode((string) $client->getResponse()->getContent(), true));
        $client->request('GET', "/api/roadside-requests/$requestId");
        self::assertResponseStatusCodeSame(403);
        $client->request('GET', "/api/roadside-requests/$requestId/documents/$documentId");
        self::assertResponseStatusCodeSame(403);

        // ADMIN: vede cererea și o preia (FORWARDED, marcaj intern + telefon).
        $this->login($client, $adminEmail, 'Parola1234');
        $client->request('GET', '/api/admin/roadside-requests');
        self::assertResponseIsSuccessful();
        $adminList = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertContains($requestId, array_column($adminList, 'id'));

        // Tranziție nepermisă (SUBMITTED → FORWARDED direct) → 409, fără modificare.
        $client->request('PATCH', "/api/admin/roadside-requests/$requestId", server: $this->json(), content: json_encode([
            'status' => 'FORWARDED', 'note' => 'Preluat, sunăm clientul.',
        ]));
        self::assertResponseStatusCodeSame(409);

        // Fluxul corect conform specificației: VALIDATED → FORWARDED.
        $client->request('PATCH', "/api/admin/roadside-requests/$requestId", server: $this->json(), content: json_encode([
            'status' => 'VALIDATED', 'note' => null,
        ]));
        self::assertResponseIsSuccessful();
        $client->request('PATCH', "/api/admin/roadside-requests/$requestId", server: $this->json(), content: json_encode([
            'status' => 'FORWARDED', 'note' => 'Preluat, sunăm clientul.',
        ]));
        self::assertResponseIsSuccessful();
        self::assertSame('FORWARDED', json_decode((string) $client->getResponse()->getContent(), true)['status']);

        // CLIENT: vede noua stare și numărul de telefon rămâne pe cerere.
        $this->login($client, $ownerEmail, 'Parola1234');
        $client->request('GET', "/api/roadside-requests/$requestId");
        self::assertResponseIsSuccessful();
        $seen = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame('FORWARDED', $seen['status']);
        self::assertSame('+40711223344', $seen['phone']);

        // CLIENT: nu mai poate anula o cerere preluată.
        $client->request('POST', "/api/roadside-requests/$requestId/cancel");
        self::assertResponseStatusCodeSame(422);

        // CLIENT: poate anula o cerere nouă.
        $client->request('POST', '/api/roadside-requests', server: $this->json(), content: json_encode([
            'location' => 'Acasă', 'problem' => 'Nu pornește.', 'mobility' => 'NOT_DRIVABLE', 'safety' => 'SAFE', 'phone' => '+40711223344',
        ]));
        self::assertResponseStatusCodeSame(201);
        $secondId = json_decode((string) $client->getResponse()->getContent(), true)['id'];
        $client->request('POST', "/api/roadside-requests/$secondId/cancel");
        self::assertResponseIsSuccessful();
        self::assertSame('CANCELLED', json_decode((string) $client->getResponse()->getContent(), true)['status']);

        // AUDIT.
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        foreach (['roadside.created', 'roadside.status_changed', 'roadside.cancelled'] as $action) {
            $count = (int) $em->getConnection()->fetchOne('SELECT COUNT(*) FROM audit_logs WHERE action = ?', [$action]);
            self::assertGreaterThanOrEqual(1, $count, "Acțiunea $action trebuie să apară în audit.");
        }
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
