<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Verifică end-to-end, prin HTTP, că un client accesează DOAR propriile date:
 * înregistrare + login, creare vehicul, iar un al doilea client nu vede și nu
 * poate accesa vehiculul primului (autorizare la nivel de obiect).
 *
 * @group functional
 */
final class ClientIsolationTest extends ApiTestCase
{
    public function testClientCannotAccessAnotherClientsVehicle(): void
    {
        $client = static::createClient();

        $emailA = 'a-'.uniqid().'@example.test';
        $emailB = 'b-'.uniqid().'@example.test';

        $this->register($client, $emailA);
        $this->register($client, $emailB);

        // Client A: login + creează vehicul.
        $this->login($client, $emailA);
        $client->request('POST', '/api/vehicles', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'vin' => 'WBA3A5C50EF'.str_pad((string) random_int(100000, 999999), 6, '0'),
            'plateNumber' => 'MS01AAA',
            'make' => 'BMW',
        ]));
        self::assertResponseStatusCodeSame(201);
        /** @var array{id: string} $created */
        $created = json_decode((string) $client->getResponse()->getContent(), true);
        $vehicleId = $created['id'];

        // Client A vede exact 1 vehicul.
        $client->request('GET', '/api/vehicles');
        self::assertResponseIsSuccessful();
        self::assertCount(1, json_decode((string) $client->getResponse()->getContent(), true));

        // PATCH: schimbarea numărului de înmatriculare trebuie să persiste.
        $client->request('PATCH', '/api/vehicles/'.$vehicleId, server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'plateNumber' => 'MS99XYZ',
        ]));
        self::assertResponseIsSuccessful();
        $client->request('GET', '/api/vehicles/'.$vehicleId);
        /** @var array{plateNumber: string} $after */
        $after = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame('MS99XYZ', $after['plateNumber'], 'Numărul de înmatriculare trebuie actualizat prin PATCH.');

        // Client B: login. Nu vede niciun vehicul.
        $this->login($client, $emailB);
        $client->request('GET', '/api/vehicles');
        self::assertResponseIsSuccessful();
        self::assertCount(0, json_decode((string) $client->getResponse()->getContent(), true), 'Client B nu trebuie să vadă vehiculele lui A.');

        // Client B NU poate accesa vehiculul lui A (autorizare la nivel de obiect).
        $client->request('GET', '/api/vehicles/'.$vehicleId);
        self::assertContains(
            $client->getResponse()->getStatusCode(),
            [403, 404],
            'Accesul lui B la vehiculul lui A trebuie interzis.',
        );
    }

    public function testUnauthenticatedAccessIsRejected(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/vehicles');
        self::assertResponseStatusCodeSame(401);
    }

    public function testLogoutReturnsNoContentNotRedirect(): void
    {
        $client = static::createClient();
        $email = 'lo-'.uniqid().'@example.test';
        $this->register($client, $email);
        $this->login($client, $email);

        $client->request('POST', '/api/auth/logout');
        self::assertResponseStatusCodeSame(204, 'Logout trebuie să întoarcă 204, nu redirect.');
        self::assertFalse($client->getResponse()->isRedirection());

        // Sesiunea este invalidată — accesul protejat cere din nou autentificare.
        $client->request('GET', '/api/me');
        self::assertResponseStatusCodeSame(401);
    }

    public function testHealthIsPublic(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/health');
        self::assertResponseIsSuccessful();
    }

    private function register(KernelBrowser $client, string $email): void
    {
        $client->request('POST', '/api/auth/register', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'email' => $email,
            'password' => 'Parola1234',
            'firstName' => 'Test',
            'lastName' => 'Client',
            'consent' => true,
        ]));
        self::assertResponseStatusCodeSame(201, 'Înregistrarea trebuie să reușească.');
    }

    private function login(KernelBrowser $client, string $email): void
    {
        $client->request('POST', '/api/auth/login', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'email' => $email,
            'password' => 'Parola1234',
        ]));
        self::assertResponseIsSuccessful('Autentificarea trebuie să reușească.');
    }
}
