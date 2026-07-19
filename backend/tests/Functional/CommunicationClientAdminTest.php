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
 * Comunicare client ↔ service end-to-end, pe stările din specificație:
 *  - clientul deschide o conversație (OPEN) cu mesaj + atașament;
 *  - service-ul răspunde → WAITING_CLIENT; clientul răspunde → WAITING_SERVICE;
 *  - service-ul închide → CLOSED; mesaj pe conversație închisă → 409; redeschidere → OPEN;
 *  - un alt client NU are acces (403); atașamentele rămân autorizate per proprietar;
 *  - operațiunile ajung în audit.
 *
 * @group functional
 */
final class CommunicationClientAdminTest extends WebTestCase
{
    private const PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAAC0lEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    public function testConversationFlowWithSpecStatesAttachmentsAndIsolation(): void
    {
        $client = static::createClient();
        $ownerEmail = 'own-'.uniqid().'@example.test';
        $otherEmail = 'oth-'.uniqid().'@example.test';
        $adminEmail = 'ad-'.uniqid().'@bcsc.ro';
        $this->createAdmin($adminEmail, 'Parola1234');

        $this->register($client, $ownerEmail);
        $this->register($client, $otherEmail);
        $this->login($client, $ownerEmail, 'Parola1234');

        // Vehiculul clientului (pentru cererea de reparație).
        $client->request('POST', '/api/vehicles', server: $this->json(), content: json_encode([
            'vin' => 'WBA3A5C50EF123456', 'plateNumber' => 'MS50MSG',
        ]));
        self::assertResponseStatusCodeSame(201);
        $vehicleId = json_decode((string) $client->getResponse()->getContent(), true)['id'];

        // Atașament încărcat de client.
        $client->request('POST', '/api/documents', files: ['file' => $this->tempUpload('defect.png', base64_decode(self::PNG_BASE64), 'image/png')]);
        self::assertResponseStatusCodeSame(201);
        $documentId = json_decode((string) $client->getResponse()->getContent(), true)['id'];

        // CLIENT: deschide o cerere de ofertă cu mesaj + atașament.
        $client->request('POST', '/api/conversations', server: $this->json(), content: json_encode([
            'subject' => 'Zgomot la frânare',
            'body' => 'Se aude un scârțâit la frânare. Vă rog un sfat.',
            'vehicleId' => $vehicleId,
            'documentIds' => [$documentId],
        ]));
        self::assertResponseStatusCodeSame(201);
        $conv = json_decode((string) $client->getResponse()->getContent(), true);
        $conversationId = $conv['id'];
        self::assertSame('OPEN', $conv['status']);
        self::assertCount(1, $conv['messages']);
        self::assertCount(1, $conv['messages'][0]['attachments']);
        $this->scan($documentId);

        // CLIENT: descărcare autorizată a atașamentului.
        $client->request('GET', "/api/conversations/$conversationId/documents/$documentId");
        self::assertResponseIsSuccessful();
        self::assertSame('image/png', $client->getResponse()->headers->get('Content-Type'));

        // ALT CLIENT: nu vede și nu descarcă.
        $this->login($client, $otherEmail, 'Parola1234');
        $client->request('GET', '/api/conversations');
        self::assertResponseIsSuccessful();
        self::assertCount(0, json_decode((string) $client->getResponse()->getContent(), true));
        $client->request('GET', "/api/conversations/$conversationId");
        self::assertResponseStatusCodeSame(403);
        $client->request('GET', "/api/conversations/$conversationId/documents/$documentId");
        self::assertResponseStatusCodeSame(403);

        // ADMIN: vede conversația în portal, răspunde și trimite oferta.
        $this->login($client, $adminEmail, 'Parola1234');
        $client->request('GET', '/api/admin/conversations');
        self::assertResponseIsSuccessful();
        $adminList = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertContains($conversationId, array_column($adminList, 'id'));

        $client->request('POST', "/api/admin/conversations/$conversationId/messages", server: $this->json(), content: json_encode([
            'body' => 'Bună ziua, putem programa o verificare.',
        ]));
        self::assertResponseIsSuccessful();
        self::assertSame('WAITING_CLIENT', json_decode((string) $client->getResponse()->getContent(), true)['status']);

        // Adminul poate și el descărca atașamentul (rol privilegiat).
        $client->request('GET', "/api/conversations/$conversationId/documents/$documentId");
        self::assertResponseIsSuccessful();

        // CLIENT: vede răspunsul (WAITING_CLIENT), citește mesajele și răspunde.
        $this->login($client, $ownerEmail, 'Parola1234');
        $client->request('GET', "/api/conversations/$conversationId");
        self::assertResponseIsSuccessful();
        self::assertSame('WAITING_CLIENT', json_decode((string) $client->getResponse()->getContent(), true)['status']);

        $client->request('GET', "/api/conversations/$conversationId/messages");
        self::assertResponseIsSuccessful();
        self::assertCount(2, json_decode((string) $client->getResponse()->getContent(), true));

        $client->request('POST', "/api/conversations/$conversationId/messages", server: $this->json(), content: json_encode([
            'body' => 'Mulțumesc, aștept programarea.',
        ]));
        self::assertResponseIsSuccessful();
        self::assertSame('WAITING_SERVICE', json_decode((string) $client->getResponse()->getContent(), true)['status']);

        // ADMIN: închide conversația; mesaj pe închisă → 409; redeschidere → OPEN.
        $this->login($client, $adminEmail, 'Parola1234');
        $client->request('POST', "/api/admin/conversations/$conversationId/close");
        self::assertResponseIsSuccessful();
        self::assertSame('CLOSED', json_decode((string) $client->getResponse()->getContent(), true)['status']);

        $this->login($client, $ownerEmail, 'Parola1234');
        $client->request('POST', "/api/conversations/$conversationId/messages", server: $this->json(), content: json_encode([
            'body' => 'Mai am o întrebare.',
        ]));
        self::assertResponseStatusCodeSame(409, 'Pe o conversație închisă nu se mai scrie.');

        $this->login($client, $adminEmail, 'Parola1234');
        $client->request('POST', "/api/admin/conversations/$conversationId/reopen");
        self::assertResponseIsSuccessful();
        self::assertSame('OPEN', json_decode((string) $client->getResponse()->getContent(), true)['status']);

        // AUDIT: pașii cheie au fost înregistrați.
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        foreach (['conversation.started', 'conversation.closed', 'conversation.reopened'] as $action) {
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
