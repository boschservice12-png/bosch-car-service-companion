<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Identity\Domain\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Slice vertical „scadențe" end-to-end, din perspectiva CLIENT și ADMIN:
 * clientul adaugă o scadență, adminul o vede în portal și o validează, iar
 * clientul vede scadența marcată „validată". Plus izolarea admin/client.
 *
 * @group functional
 */
final class ScadenteClientAdminTest extends WebTestCase
{
    public function testClientAddsDeadlineAdminValidatesClientSeesVerified(): void
    {
        $client = static::createClient();
        $clientEmail = 'cl-'.uniqid().'@example.test';
        $adminEmail = 'ad-'.uniqid().'@bcsc.ro';
        $this->createAdmin($adminEmail, 'Parola1234');

        // CLIENT: cont, vehicul, scadență ITP.
        $this->register($client, $clientEmail);
        $this->login($client, $clientEmail, 'Parola1234');
        $client->request('POST', '/api/vehicles', server: $this->json(), content: json_encode(['vin' => 'WBA3A5C50EF123456', 'plateNumber' => 'MS20AAA']));
        self::assertResponseStatusCodeSame(201);
        $vehicleId = json_decode((string) $client->getResponse()->getContent(), true)['id'];

        $client->request('POST', "/api/vehicles/$vehicleId/deadlines", server: $this->json(), content: json_encode([
            'type' => 'ITP', 'expiresAt' => (new \DateTimeImmutable('+20 days'))->format('Y-m-d'),
        ]));
        self::assertResponseStatusCodeSame(201);
        $deadlineId = json_decode((string) $client->getResponse()->getContent(), true)['id'];
        self::assertFalse(json_decode((string) $client->getResponse()->getContent(), true)['verified']);

        // Clientul NU are acces la portalul admin.
        $client->request('GET', '/api/admin/vehicles');
        self::assertResponseStatusCodeSame(403);

        // ADMIN (sesiune separată): vede vehiculul clientului în portal.
        $this->login($client, $adminEmail, 'Parola1234');
        $client->request('GET', '/api/admin/vehicles');
        self::assertResponseIsSuccessful();
        $vehicles = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertContains($vehicleId, array_column($vehicles, 'id'), 'Adminul trebuie să vadă vehiculul clientului.');

        // ADMIN validează scadența.
        $client->request('PATCH', "/api/deadlines/$deadlineId", server: $this->json(), content: json_encode(['verify' => true]));
        self::assertResponseIsSuccessful();
        self::assertTrue(json_decode((string) $client->getResponse()->getContent(), true)['verified']);

        // CLIENT: revine și vede scadența „validată" (source SERVICE).
        $this->login($client, $clientEmail, 'Parola1234');
        $client->request('GET', "/api/vehicles/$vehicleId/deadlines");
        self::assertResponseIsSuccessful();
        $deadlines = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertTrue($deadlines[0]['verified'], 'Clientul trebuie să vadă scadența validată de service.');
        self::assertSame('SERVICE', $deadlines[0]['source']);

        // CLIENT modifică data → validarea service-ului se pierde (datele
        // clientului nu pot purta ștampila service-ului).
        $client->request('PATCH', "/api/deadlines/$deadlineId", server: $this->json(), content: json_encode([
            'expiresAt' => (new \DateTimeImmutable('+90 days'))->format('Y-m-d'),
        ]));
        self::assertResponseIsSuccessful();
        $edited = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertFalse($edited['verified'], 'Data schimbată de client anulează validarea.');
        self::assertSame('CLIENT', $edited['source']);
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
