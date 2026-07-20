<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\DamageClaim\Domain\DamageClaim;
use App\DamageClaim\Domain\DamageClaimStatus;
use App\Identity\Domain\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Dosar de daună — decizie de produs: clientul se conectează EXCLUSIV la
 * platforma oficială amiabila.com; aplicația NU expune API de client pentru
 * dosare (nici creare, nici listare). Service-ul (admin) urmărește dosarele:
 *  - rutele de client nu mai există (404);
 *  - adminul vede dosarul și îi schimbă starea (SUBMITTED → IN_REVIEW);
 *  - clientul obișnuit nu are acces la portalul admin (403);
 *  - schimbările ajung în audit.
 *
 * @group functional
 */
final class DamageClaimClientAdminTest extends ApiTestCase
{
    public function testClientApiRemovedAdminTracksClaims(): void
    {
        $client = static::createClient();
        $ownerEmail = 'own-'.uniqid().'@example.test';
        $adminEmail = 'ad-'.uniqid().'@bcsc.ro';
        $this->createAdmin($adminEmail, 'Parola1234');
        $this->register($client, $ownerEmail);

        // Dosarul există în sistem (deschis prin amiabila.com, urmărit de service).
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $owner = $em->getRepository(User::class)->findOneBy(['email' => $ownerEmail]);
        self::assertInstanceOf(User::class, $owner);
        $claim = new DamageClaim(
            $owner,
            null,
            new \DateTimeImmutable('-2 days'),
            'Intersecție Târgu Mureș',
            'Coliziune ușoară în parcare, aripă dreapta spate.',
            'Allianz-Țiriac',
            'POL-123456',
        );
        $em->persist($claim);
        $em->flush();
        $claimId = (string) $claim->id();

        // CLIENT: nu există API de dosare — nici creare, nici listare (404).
        $this->login($client, $ownerEmail, 'Parola1234');
        $client->request('POST', '/api/damage-claims', server: $this->json(), content: json_encode([
            'incidentDescription' => 'Încercare de creare în aplicație.',
        ]));
        self::assertResponseStatusCodeSame(404, 'Dosarul se deschide doar pe amiabila.com.');
        $client->request('GET', '/api/damage-claims');
        self::assertResponseStatusCodeSame(404);

        // CLIENT: portalul admin rămâne interzis.
        $client->request('GET', '/api/admin/damage-claims');
        self::assertResponseStatusCodeSame(403);

        // ADMIN: vede dosarul și îl preia.
        $this->login($client, $adminEmail, 'Parola1234');
        $client->request('GET', '/api/admin/damage-claims');
        self::assertResponseIsSuccessful();
        self::assertContains($claimId, array_column(json_decode((string) $client->getResponse()->getContent(), true), 'id'));

        $client->request('PATCH', "/api/admin/damage-claims/$claimId", server: $this->json(), content: json_encode([
            'status' => 'IN_REVIEW', 'note' => 'Am transmis dosarul către asigurător.',
        ]));
        self::assertResponseIsSuccessful();
        $updated = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame('IN_REVIEW', $updated['status']);
        self::assertSame('Allianz-Țiriac', $updated['insurer']);

        // ADMIN: fotografiile dosarului se descarcă prin ruta de admin
        // (portalul o folosea, dar ruta lipsea — regresie remediată).
        /** @var \App\Document\Domain\StorageAdapter $storage */
        $storage = static::getContainer()->get(\App\Document\Domain\StorageAdapter::class);
        $storageKey = 'dc/test-'.uniqid().'.txt';
        $tmp = tempnam(sys_get_temp_dir(), 'dcdoc');
        file_put_contents((string) $tmp, 'fotografie dauna');
        $storage->store((string) $tmp, $storageKey, 'text/plain');
        // Cererile HTTP dintre timp au lăsat instanțele vechi detașate —
        // lucrăm pe entități reîncărcate din EntityManagerul curent.
        $em->clear();
        $freshOwner = $em->getRepository(User::class)->findOneBy(['email' => $ownerEmail]);
        $freshClaim = $em->find(DamageClaim::class, $claim->id());
        self::assertInstanceOf(DamageClaim::class, $freshClaim);
        $document = new \App\Document\Domain\Document($storageKey, 'text/plain', 16, $freshOwner, 'dauna-admin.jpg');
        $document->markClean();
        $freshClaim->attach($document);
        $em->persist($document);
        $em->flush();
        $docId = (string) $document->id();

        $client->request('GET', "/api/admin/damage-claims/$claimId/documents/$docId");
        self::assertResponseIsSuccessful();
        self::assertSame('fotografie dauna', (string) $client->getResponse()->getContent());
        self::assertStringContainsString('attachment', (string) $client->getResponse()->headers->get('Content-Disposition'));

        // Un document care NU aparține dosarului → 404.
        $client->request('GET', "/api/admin/damage-claims/$claimId/documents/".\Symfony\Component\Uid\Uuid::v7());
        self::assertResponseStatusCodeSame(404);

        // Starea s-a schimbat efectiv și acțiunea e în audit.
        $em->clear();
        $fresh = $em->find(DamageClaim::class, $claim->id());
        self::assertInstanceOf(DamageClaim::class, $fresh);
        self::assertSame(DamageClaimStatus::IN_REVIEW, $fresh->status());
        $count = (int) $em->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM audit_logs WHERE action = ?',
            ['damage_claim.status_changed'],
        );
        self::assertGreaterThanOrEqual(1, $count);
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
