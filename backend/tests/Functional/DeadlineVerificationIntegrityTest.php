<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Identity\Domain\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Blocul 2 — integritatea verificării și a sursei scadenței.
 *
 * Regula de proveniență (documentată în docs/PILOT_READINESS.md):
 *  - o modificare de CLIENT a unui câmp relevant (validFrom / expiresAt /
 *    document) → source = CLIENT, verificarea se anulează, CHIAR DACĂ rândul
 *    nu era încă validat (un rând SERVICE nevalidat nu rămâne SERVICE);
 *  - modificarea DOAR a notei NU rupe verificarea;
 *  - un PATCH fără schimbare reală nu resetează nimic;
 *  - editările adminului nu validează automat — doar `verify: true` validează.
 *
 * @group functional
 */
final class DeadlineVerificationIntegrityTest extends ApiTestCase
{
    private const PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAAC0lEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    private string $adminEmail;
    private string $clientEmail;
    private string $vehicleId;

    public function testServiceUnverifiedDeadlineEditedByClientBecomesClient(): void
    {
        $client = $this->bootstrapClientOwnedVehicle();

        // Admin creează o scadență SERVICE, NEvalidată.
        $deadlineId = $this->adminCreateServiceDeadline($client, '2026-11-01');
        $d = $this->getDeadline($client, $deadlineId);
        self::assertSame('SERVICE', $d['source']);
        self::assertFalse($d['verified']);

        // Clientul modifică data expirării → sursa devine CLIENT (bug-ul remediat).
        $this->login($client, $this->clientEmail);
        $client->request('PATCH', "/api/deadlines/$deadlineId", server: $this->json(), content: json_encode([
            'expiresAt' => '2026-12-01',
        ]));
        self::assertResponseIsSuccessful();
        $after = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame('CLIENT', $after['source'], 'Un rând SERVICE nevalidat editat de client NU rămâne SERVICE.');
        self::assertFalse($after['verified']);
    }

    public function testServiceVerifiedDeadlineEditedByClientLosesVerification(): void
    {
        $client = $this->bootstrapClientOwnedVehicle();
        $deadlineId = $this->adminCreateServiceDeadline($client, '2026-11-01');

        // Admin validează explicit → SERVICE + verified.
        $this->login($client, $this->adminEmail);
        $client->request('PATCH', "/api/deadlines/$deadlineId", server: $this->json(), content: json_encode(['verify' => true]));
        self::assertResponseIsSuccessful();
        $verified = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame('SERVICE', $verified['source']);
        self::assertTrue($verified['verified']);

        // Clientul schimbă data → verificarea se pierde, source = CLIENT.
        $this->login($client, $this->clientEmail);
        $client->request('PATCH', "/api/deadlines/$deadlineId", server: $this->json(), content: json_encode([
            'expiresAt' => '2027-01-15',
        ]));
        self::assertResponseIsSuccessful();
        $after = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame('CLIENT', $after['source']);
        self::assertFalse($after['verified']);
    }

    public function testAdminEditWithoutVerifyDoesNotVerifyAndKeepsService(): void
    {
        $client = $this->bootstrapClientOwnedVehicle();
        $deadlineId = $this->adminCreateServiceDeadline($client, '2026-11-01');

        // Admin schimbă data FĂRĂ verify → rămâne SERVICE, tot NEvalidat.
        $this->login($client, $this->adminEmail);
        $client->request('PATCH', "/api/deadlines/$deadlineId", server: $this->json(), content: json_encode([
            'expiresAt' => '2026-12-20',
        ]));
        self::assertResponseIsSuccessful();
        $after = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame('SERVICE', $after['source'], 'Editarea adminului nu schimbă proveniența.');
        self::assertFalse($after['verified'], 'Editarea adminului NU validează automat.');
    }

    public function testNoteOnlyChangeAndUnchangedPatchPreserveVerification(): void
    {
        $client = $this->bootstrapClientOwnedVehicle();
        $deadlineId = $this->adminCreateServiceDeadline($client, '2026-11-01');
        $this->login($client, $this->adminEmail);
        $client->request('PATCH', "/api/deadlines/$deadlineId", server: $this->json(), content: json_encode(['verify' => true]));
        self::assertResponseIsSuccessful();

        // Client schimbă DOAR nota → verificarea rămâne (regula documentată).
        $this->login($client, $this->clientEmail);
        $client->request('PATCH', "/api/deadlines/$deadlineId", server: $this->json(), content: json_encode([
            'note' => 'O observație a clientului.',
        ]));
        self::assertResponseIsSuccessful();
        $noteOnly = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame('SERVICE', $noteOnly['source'], 'Modificarea DOAR a notei nu rupe verificarea.');
        self::assertTrue($noteOnly['verified']);

        // PATCH fără schimbare reală (aceeași dată) → nimic nu se resetează.
        $client->request('PATCH', "/api/deadlines/$deadlineId", server: $this->json(), content: json_encode([
            'expiresAt' => '2026-11-01',
        ]));
        self::assertResponseIsSuccessful();
        $noop = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame('SERVICE', $noop['source']);
        self::assertTrue($noop['verified'], 'Un PATCH fără schimbare reală nu anulează verificarea.');
    }

    public function testClientAttachingDocumentResetsVerification(): void
    {
        $client = $this->bootstrapClientOwnedVehicle();
        $deadlineId = $this->adminCreateServiceDeadline($client, '2026-11-01');
        $this->login($client, $this->adminEmail);
        $client->request('PATCH', "/api/deadlines/$deadlineId", server: $this->json(), content: json_encode(['verify' => true]));
        self::assertResponseIsSuccessful();

        // Clientul încarcă și atașează un document → source = CLIENT, verificarea cade.
        $this->login($client, $this->clientEmail);
        $client->request('POST', '/api/documents', files: ['file' => $this->upload('doc.png')]);
        self::assertResponseStatusCodeSame(201);
        $docId = json_decode((string) $client->getResponse()->getContent(), true)['id'];
        $client->request('POST', "/api/deadlines/$deadlineId/documents", server: $this->json(), content: json_encode([
            'documentId' => $docId,
        ]));
        self::assertResponseIsSuccessful();
        $after = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame('CLIENT', $after['source'], 'Documentul atașat de client rupe ștampila service-ului.');
        self::assertFalse($after['verified']);
    }

    // --- helpers ---

    private function bootstrapClientOwnedVehicle(): KernelBrowser
    {
        $client = static::createClient();
        $this->adminEmail = 'ad-'.uniqid().'@bcsc.ro';
        $this->clientEmail = 'cl-'.uniqid().'@example.test';
        $this->createAdmin($this->adminEmail, 'Parola1234');
        $this->register($client, $this->clientEmail);

        // Clientul își creează vehiculul (devine proprietar activ).
        $this->login($client, $this->clientEmail);
        $vin = 'WBA3A5C50EF'.str_pad((string) random_int(100000, 999999), 6, '0');
        $client->request('POST', '/api/vehicles', server: $this->json(), content: json_encode([
            'vin' => $vin, 'plateNumber' => 'MS 42 DLN',
        ]));
        self::assertResponseStatusCodeSame(201);
        $this->vehicleId = json_decode((string) $client->getResponse()->getContent(), true)['id'];

        return $client;
    }

    private function adminCreateServiceDeadline(KernelBrowser $client, string $expiresAt): string
    {
        $this->login($client, $this->adminEmail);
        $client->request('POST', "/api/vehicles/{$this->vehicleId}/deadlines", server: $this->json(), content: json_encode([
            'type' => 'ITP', 'expiresAt' => $expiresAt,
        ]));
        self::assertResponseStatusCodeSame(201);

        return json_decode((string) $client->getResponse()->getContent(), true)['id'];
    }

    /** @return array<string, mixed> */
    private function getDeadline(KernelBrowser $client, string $deadlineId): array
    {
        $this->login($client, $this->adminEmail);
        $client->request('GET', "/api/vehicles/{$this->vehicleId}/deadlines");
        self::assertResponseIsSuccessful();
        foreach (json_decode((string) $client->getResponse()->getContent(), true) as $d) {
            if ($d['id'] === $deadlineId) {
                return $d;
            }
        }
        self::fail('Scadența creată nu a fost găsită.');
    }

    private function upload(string $name): UploadedFile
    {
        $path = sys_get_temp_dir().'/bcsc_dln_'.uniqid().'_'.$name;
        file_put_contents($path, base64_decode(self::PNG_BASE64));

        return new UploadedFile($path, $name, 'image/png', null, true);
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

    private function login(KernelBrowser $client, string $email): void
    {
        $client->request('POST', '/api/auth/login', server: $this->json(), content: json_encode([
            'email' => $email, 'password' => 'Parola1234',
        ]));
        self::assertResponseIsSuccessful();
    }
}
