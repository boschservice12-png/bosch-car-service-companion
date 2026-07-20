<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Identity\Domain\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Blocul 3 — activare sigură a vehiculului cu cod (nu cu numărul de înmatriculare).
 *
 * @group functional
 */
final class VehicleActivationTest extends ApiTestCase
{
    private string $adminEmail;

    public function testValidCodeActivatesAndTransfersOwnership(): void
    {
        [$client, $vehicleId, $ownerAEmail] = $this->bootstrapCase();

        // Proprietar nou (client B) — nu are acces la vehicul înainte de activare.
        $bEmail = 'b-'.uniqid().'@example.test';
        $this->register($client, $bEmail);
        $this->login($client, $bEmail);
        $client->request('GET', "/api/vehicles/$vehicleId/deadlines");
        self::assertContains($client->getResponse()->getStatusCode(), [403, 404], 'Fără activare nu are acces (nici cu VIN/număr cunoscut).');

        // Adminul emite un cod pentru vehicul.
        $token = $this->issueToken($client, $vehicleId);

        // Client B activează cu codul → devine proprietar.
        $this->login($client, $bEmail);
        $client->request('POST', '/api/me/vehicles/activate', server: $this->json(), content: json_encode(['token' => $token]));
        self::assertResponseIsSuccessful();
        self::assertSame($vehicleId, json_decode((string) $client->getResponse()->getContent(), true)['id']);

        // B vede acum vehiculul; A l-a pierdut (un singur proprietar activ).
        $client->request('GET', '/api/vehicles');
        self::assertCount(1, json_decode((string) $client->getResponse()->getContent(), true));
        $this->login($client, $ownerAEmail);
        $client->request('GET', '/api/vehicles');
        self::assertCount(0, json_decode((string) $client->getResponse()->getContent(), true), 'Fostul proprietar pierde accesul.');

        // Codul e consumat — a doua folosire eșuează.
        $this->register($client, 'c-'.uniqid().'@example.test');
        $client->request('POST', '/api/me/vehicles/activate', server: $this->json(), content: json_encode(['token' => $token]));
        self::assertResponseStatusCodeSame(422, 'Un cod deja folosit nu se mai acceptă.');

        // Audit.
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        self::assertGreaterThanOrEqual(1, (int) $em->getConnection()->fetchOne("SELECT COUNT(*) FROM audit_logs WHERE action = 'vehicle.activation_used'"));
    }

    public function testInvalidRevokedAndExpiredCodesRejected(): void
    {
        [$client, $vehicleId] = $this->bootstrapCase();
        $userEmail = 'u-'.uniqid().'@example.test';
        $this->register($client, $userEmail);

        // Cod inexistent → 422.
        $this->login($client, $userEmail);
        $client->request('POST', '/api/me/vehicles/activate', server: $this->json(), content: json_encode(['token' => 'AAAA-BBBB-CCCC-DDDD']));
        self::assertResponseStatusCodeSame(422);

        // Cod revocat → 422.
        $token = $this->issueToken($client, $vehicleId);
        $client->request('POST', "/api/admin/vehicles/$vehicleId/activation-token/revoke", server: $this->json(), content: '{}');
        self::assertResponseIsSuccessful();
        $this->login($client, $userEmail);
        $client->request('POST', '/api/me/vehicles/activate', server: $this->json(), content: json_encode(['token' => $token]));
        self::assertResponseStatusCodeSame(422, 'Un cod revocat nu se acceptă.');

        // Cod expirat → 422 (împingem expirarea în trecut în baza de date).
        $token2 = $this->issueToken($client, $vehicleId);
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->getConnection()->executeStatement(
            "UPDATE vehicle_activation_tokens SET expires_at = ? WHERE used_at IS NULL AND revoked_at IS NULL",
            [(new \DateTimeImmutable('-1 day'))->format('Y-m-d H:i:s')],
        );
        $this->login($client, $userEmail);
        $client->request('POST', '/api/me/vehicles/activate', server: $this->json(), content: json_encode(['token' => $token2]));
        self::assertResponseStatusCodeSame(422, 'Un cod expirat nu se acceptă.');
    }

    public function testOnlyAdminCanIssueToken(): void
    {
        [$client, $vehicleId] = $this->bootstrapCase();
        $this->register($client, 'x-'.uniqid().'@example.test');
        // Client (non-admin) → 403 pe emiterea codului.
        $client->request('POST', "/api/admin/vehicles/$vehicleId/activation-token", server: $this->json(), content: '{}');
        self::assertResponseStatusCodeSame(403);
    }

    public function testActivationIsRateLimited(): void
    {
        [$client, $vehicleId] = $this->bootstrapCase();
        $userEmail = 'rl-'.uniqid().'@example.test';
        $this->register($client, $userEmail);
        $this->login($client, $userEmail);

        // Limita de test este 5/min pe activare (per utilizator+IP).
        for ($i = 1; $i <= 5; ++$i) {
            $client->request('POST', '/api/me/vehicles/activate', server: $this->json(), content: json_encode(['token' => "WRONG$i"]));
            self::assertResponseStatusCodeSame(422, "Încercarea $i e sub limită.");
        }
        $client->request('POST', '/api/me/vehicles/activate', server: $this->json(), content: json_encode(['token' => 'WRONG6']));
        self::assertResponseStatusCodeSame(429, 'A 6-a încercare e blocată.');
    }

    // --- helpers ---

    /** @return array{0: KernelBrowser, 1: string, 2: string} [client, vehicleId, ownerAEmail] */
    private function bootstrapCase(): array
    {
        $client = static::createClient();
        $this->adminEmail = 'ad-'.uniqid().'@bcsc.ro';
        $this->createAdmin($this->adminEmail, 'Parola1234');

        $ownerAEmail = 'a-'.uniqid().'@example.test';
        $this->register($client, $ownerAEmail);
        $this->login($client, $ownerAEmail);
        $vin = 'WBA3A5C50EF'.str_pad((string) random_int(100000, 999999), 6, '0');
        $client->request('POST', '/api/vehicles', server: $this->json(), content: json_encode([
            'vin' => $vin, 'plateNumber' => 'MS 33 ACT',
        ]));
        self::assertResponseStatusCodeSame(201);
        $vehicleId = json_decode((string) $client->getResponse()->getContent(), true)['id'];

        return [$client, $vehicleId, $ownerAEmail];
    }

    private function issueToken(KernelBrowser $client, string $vehicleId): string
    {
        $this->login($client, $this->adminEmail);
        $client->request('POST', "/api/admin/vehicles/$vehicleId/activation-token", server: $this->json(), content: '{}');
        self::assertResponseStatusCodeSame(201);

        return json_decode((string) $client->getResponse()->getContent(), true)['token'];
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
