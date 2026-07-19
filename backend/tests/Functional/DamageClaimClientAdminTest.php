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
 * Slice vertical „dosar de daună" end-to-end (CLIENT + ADMIN):
 *  - clientul deschide un dosar (eveniment, asigurător, poliță, fotografii);
 *  - un alt client NU are acces (403);
 *  - service-ul îl preia (IN_PROGRESS) și clientul vede noua stare;
 *  - descărcarea fotografiilor/documentelor e autorizată prin proprietar;
 *  - anularea de client e permisă doar cât timp dosarul e nou;
 *  - operațiunile ajung în audit.
 *
 * @group functional
 */
final class DamageClaimClientAdminTest extends WebTestCase
{
    private const PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAAC0lEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    public function testDamageClaimFlowWithIsolationAndStatus(): void
    {
        $client = static::createClient();
        $ownerEmail = 'own-'.uniqid().'@example.test';
        $otherEmail = 'oth-'.uniqid().'@example.test';
        $adminEmail = 'ad-'.uniqid().'@bcsc.ro';
        $this->createAdmin($adminEmail, 'Parola1234');

        $this->register($client, $ownerEmail);
        $this->register($client, $otherEmail);
        $this->login($client, $ownerEmail, 'Parola1234');

        // Fotografie de la locul evenimentului.
        $client->request('POST', '/api/documents', files: ['file' => $this->tempUpload('avarie.png', base64_decode(self::PNG_BASE64), 'image/png')]);
        self::assertResponseStatusCodeSame(201);
        $documentId = json_decode((string) $client->getResponse()->getContent(), true)['id'];

        // CLIENT: deschide dosarul de daună.
        $client->request('POST', '/api/damage-claims', server: $this->json(), content: json_encode([
            'incidentDate' => (new \DateTimeImmutable('-2 days'))->format('Y-m-d'),
            'incidentLocation' => 'Intersecție Târgu Mureș',
            'incidentDescription' => 'Coliziune ușoară în parcare, aripă dreapta spate.',
            'insurer' => 'Allianz-Țiriac',
            'policyNumber' => 'POL-123456',
            'documentIds' => [$documentId],
        ]));
        self::assertResponseStatusCodeSame(201);
        $claim = json_decode((string) $client->getResponse()->getContent(), true);
        $claimId = $claim['id'];
        self::assertSame('SUBMITTED', $claim['status']);
        self::assertSame('Allianz-Țiriac', $claim['insurer']);
        self::assertSame('POL-123456', $claim['policyNumber']);
        self::assertCount(1, $claim['documents']);
        $this->scan($documentId);

        // CLIENT: descărcare autorizată a fotografiei.
        $client->request('GET', "/api/damage-claims/$claimId/documents/$documentId");
        self::assertResponseIsSuccessful();
        self::assertSame('image/png', $client->getResponse()->headers->get('Content-Type'));

        // ALT CLIENT: fără acces.
        $this->login($client, $otherEmail, 'Parola1234');
        $client->request('GET', '/api/damage-claims');
        self::assertResponseIsSuccessful();
        self::assertCount(0, json_decode((string) $client->getResponse()->getContent(), true));
        $client->request('GET', "/api/damage-claims/$claimId");
        self::assertResponseStatusCodeSame(403);
        $client->request('GET', "/api/damage-claims/$claimId/documents/$documentId");
        self::assertResponseStatusCodeSame(403);

        // ADMIN: vede și preia dosarul.
        $this->login($client, $adminEmail, 'Parola1234');
        $client->request('GET', '/api/admin/damage-claims');
        self::assertResponseIsSuccessful();
        self::assertContains($claimId, array_column(json_decode((string) $client->getResponse()->getContent(), true), 'id'));

        $client->request('PATCH', "/api/admin/damage-claims/$claimId", server: $this->json(), content: json_encode([
            'status' => 'IN_REVIEW', 'note' => 'Am transmis dosarul către asigurător.',
        ]));
        self::assertResponseIsSuccessful();
        self::assertSame('IN_REVIEW', json_decode((string) $client->getResponse()->getContent(), true)['status']);

        // CLIENT: vede noua stare și nota.
        $this->login($client, $ownerEmail, 'Parola1234');
        $client->request('GET', "/api/damage-claims/$claimId");
        self::assertResponseIsSuccessful();
        $seen = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame('IN_REVIEW', $seen['status']);

        // CLIENT: nu mai poate anula un dosar în lucru.
        $client->request('POST', "/api/damage-claims/$claimId/cancel");
        self::assertResponseStatusCodeSame(422);

        // CLIENT: poate anula un dosar nou.
        $client->request('POST', '/api/damage-claims', server: $this->json(), content: json_encode([
            'incidentDescription' => 'Zgârietură minoră, verific dacă deschid dosar.',
        ]));
        self::assertResponseStatusCodeSame(201);
        $secondId = json_decode((string) $client->getResponse()->getContent(), true)['id'];
        $client->request('POST', "/api/damage-claims/$secondId/cancel");
        self::assertResponseIsSuccessful();
        self::assertSame('CLOSED', json_decode((string) $client->getResponse()->getContent(), true)['status']);

        // AUDIT.
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        foreach (['damage_claim.created', 'damage_claim.status_changed', 'damage_claim.cancelled'] as $action) {
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
