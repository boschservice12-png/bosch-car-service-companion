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
 * Slice vertical „documente pe scadențe" end-to-end:
 * clientul încarcă un document, îl ataşează scadenței proprii, documentul devine
 * servibil după scanare, iar descărcarea (URL semnat) e permisă doar proprietarului
 * și administratorului — un alt client primeşte 403. Verifică şi validarea tipului.
 *
 * @group functional
 */
final class DeadlineDocumentTest extends ApiTestCase
{
    // PNG 1x1 valid (finfo îl detectează ca image/png).
    private const PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAAC0lEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    public function testUploadAttachScanDownloadWithObjectLevelAuthorization(): void
    {
        $client = static::createClient();
        $ownerEmail = 'own-'.uniqid().'@example.test';
        $otherEmail = 'oth-'.uniqid().'@example.test';
        $adminEmail = 'ad-'.uniqid().'@bcsc.ro';
        $this->createAdmin($adminEmail, 'Parola1234');

        // CLIENT proprietar: cont, vehicul, scadență.
        $this->register($client, $ownerEmail);
        $this->register($client, $otherEmail);
        $this->login($client, $ownerEmail, 'Parola1234');

        $client->request('POST', '/api/vehicles', server: $this->json(), content: json_encode([
            'vin' => 'WBA3A5C50EF'.str_pad((string) random_int(100000, 999999), 6, '0'), 'plateNumber' => 'MS30DOC',
        ]));
        self::assertResponseStatusCodeSame(201);
        $vehicleId = json_decode((string) $client->getResponse()->getContent(), true)['id'];

        $client->request('POST', "/api/vehicles/$vehicleId/deadlines", server: $this->json(), content: json_encode([
            'type' => 'ITP', 'expiresAt' => (new \DateTimeImmutable('+40 days'))->format('Y-m-d'),
        ]));
        self::assertResponseStatusCodeSame(201);
        $deadlineId = json_decode((string) $client->getResponse()->getContent(), true)['id'];

        // Tip de fișier nepermis (.txt) → respins la upload.
        $client->request('POST', '/api/documents', files: ['file' => $this->tempUpload('note.txt', "salut\n", 'text/plain')]);
        self::assertResponseStatusCodeSame(422, 'Un fișier text trebuie respins.');

        // Upload document valid (PNG).
        $client->request('POST', '/api/documents', files: ['file' => $this->tempUpload('buletin.png', base64_decode(self::PNG_BASE64), 'image/png')]);
        self::assertResponseStatusCodeSame(201);
        $upload = json_decode((string) $client->getResponse()->getContent(), true);
        $documentId = $upload['id'];
        self::assertSame('PENDING', $upload['scanStatus'], 'Documentul pornește în scanare.');

        // Ataşare la scadență → serializarea scadenței conține documentul.
        $client->request('POST', "/api/deadlines/$deadlineId/documents", server: $this->json(), content: json_encode([
            'documentId' => $documentId,
        ]));
        self::assertResponseIsSuccessful();
        $deadline = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame($documentId, $deadline['documentId']);
        self::assertNotNull($deadline['document']);
        self::assertSame('buletin.png', $deadline['document']['originalName']);
        self::assertSame('PENDING', $deadline['document']['scanStatus']);
        self::assertFalse($deadline['document']['servable'], 'Înainte de scanare nu e servibil.');

        // Înainte de scanare, descărcarea e blocată (document neservibil).
        $client->request('GET', "/api/documents/$documentId/download-url");
        self::assertResponseStatusCodeSame(422);

        // Scanare antimalware (consumatorul async, rulat sincron în test).
        $this->scan($documentId);

        // După scanare: proprietarul primeşte URL semnat de descărcare.
        $this->login($client, $ownerEmail, 'Parola1234');
        $client->request('GET', "/api/documents/$documentId/download-url");
        self::assertResponseIsSuccessful();
        $signed = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertArrayHasKey('url', $signed);
        self::assertNotEmpty($signed['url']);

        // Scadența arată acum documentul ca servibil.
        $client->request('GET', "/api/vehicles/$vehicleId/deadlines");
        self::assertResponseIsSuccessful();
        $list = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertTrue($list[0]['document']['servable']);
        self::assertSame('CLEAN', $list[0]['document']['scanStatus']);

        // Descărcarea efectivă (raw, cu semnătura din URL) funcţionează.
        $client->request('GET', $this->relativePath($signed['url']));
        self::assertResponseIsSuccessful();
        self::assertSame('image/png', $client->getResponse()->headers->get('Content-Type'));

        // Alt client NU are acces la document (autorizare la nivel de obiect).
        $this->login($client, $otherEmail, 'Parola1234');
        $client->request('GET', "/api/documents/$documentId/download-url");
        self::assertResponseStatusCodeSame(403, 'Un alt client nu poate descărca documentul altcuiva.');

        // Administratorul are acces (rol privilegiat).
        $this->login($client, $adminEmail, 'Parola1234');
        $client->request('GET', "/api/documents/$documentId/download-url");
        self::assertResponseIsSuccessful();

        // ── P0-04: ruta de descărcare PRIN SCADENȚĂ, autorizată de business ──
        // ADMINUL încarcă și ataşează un document la scadența CLIENTULUI
        // (cazul care era blocat de voter-ul generic „doar cine a încărcat").
        $client->request('POST', '/api/documents', files: ['file' => $this->tempUpload('itp-service.png', base64_decode(self::PNG_BASE64), 'image/png')]);
        self::assertResponseStatusCodeSame(201);
        $adminDocId = json_decode((string) $client->getResponse()->getContent(), true)['id'];
        $client->request('POST', "/api/deadlines/$deadlineId/documents", server: $this->json(), content: json_encode([
            'documentId' => $adminDocId,
        ]));
        self::assertResponseIsSuccessful();

        // Înainte de scanare: 404 (neservibil) chiar și pentru proprietar.
        $this->login($client, $ownerEmail, 'Parola1234');
        $client->request('GET', "/api/deadlines/$deadlineId/documents/$adminDocId");
        self::assertResponseStatusCodeSame(404, 'Documentul nescanat nu se servește.');
        $this->scan($adminDocId);

        // PROPRIETARUL vehiculului descarcă documentul atașat de service.
        $client->request('GET', "/api/deadlines/$deadlineId/documents/$adminDocId");
        self::assertResponseIsSuccessful('Proprietarul poate descărca documentul atașat de service.');
        self::assertSame('image/png', $client->getResponse()->headers->get('Content-Type'));
        $cacheControl = (string) $client->getResponse()->headers->get('Cache-Control');
        self::assertStringContainsString('no-store', $cacheControl);
        self::assertStringContainsString('private', $cacheControl);

        // Document care NU aparține scadenței → 404.
        $client->request('GET', "/api/deadlines/$deadlineId/documents/$documentId");
        self::assertResponseStatusCodeSame(404, 'Doar documentul efectiv atașat scadenței se servește.');

        // ALT CLIENT: 403 pe ruta scadenței.
        $this->login($client, $otherEmail, 'Parola1234');
        $client->request('GET', "/api/deadlines/$deadlineId/documents/$adminDocId");
        self::assertResponseStatusCodeSame(403, 'Alt client nu poate descărca documentul scadenței.');
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

        // test: true → sare peste verificarea is_uploaded_file (upload simulat).
        return new UploadedFile($path, $name, $mime, null, true);
    }

    private function relativePath(string $url): string
    {
        $parts = parse_url($url);
        $path = $parts['path'] ?? $url;

        return isset($parts['query']) ? $path.'?'.$parts['query'] : $path;
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
