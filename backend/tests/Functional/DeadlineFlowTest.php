<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Flux scadențe: clientul adaugă o scadență, primește starea calculată, iar un
 * alt client nu poate accesa scadențele vehiculului străin.
 *
 * @group functional
 */
final class DeadlineFlowTest extends WebTestCase
{
    public function testClientAddsDeadlineAndSeesComputedState(): void
    {
        $client = static::createClient();
        $emailA = 'da-'.uniqid().'@example.test';
        $emailB = 'db-'.uniqid().'@example.test';
        $this->register($client, $emailA);
        $this->register($client, $emailB);

        // Client A: vehicul + scadență ITP care expiră peste 10 zile.
        $this->login($client, $emailA);
        $vehicleId = $this->createVehicle($client, 'WBA3A5C50EF123456', 'MS10AAA');

        $soon = (new \DateTimeImmutable('+10 days'))->format('Y-m-d');
        $client->request('POST', "/api/vehicles/$vehicleId/deadlines", server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'type' => 'ITP',
            'expiresAt' => $soon,
        ]));
        self::assertResponseStatusCodeSame(201);
        /** @var array{state: string, daysLeft: int, type: string} $created */
        $created = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame('ITP', $created['type']);
        self::assertSame('DUE_SOON', $created['state'], 'O scadență la 10 zile trebuie să fie DUE_SOON.');
        self::assertSame(10, $created['daysLeft']);

        // Scadență RCA deja expirată.
        $past = (new \DateTimeImmutable('-3 days'))->format('Y-m-d');
        $client->request('POST', "/api/vehicles/$vehicleId/deadlines", server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'type' => 'RCA',
            'expiresAt' => $past,
        ]));
        self::assertResponseStatusCodeSame(201);
        self::assertSame('EXPIRED', json_decode((string) $client->getResponse()->getContent(), true)['state']);

        // Lista conține 2 scadențe.
        $client->request('GET', "/api/vehicles/$vehicleId/deadlines");
        self::assertResponseIsSuccessful();
        self::assertCount(2, json_decode((string) $client->getResponse()->getContent(), true));

        // Client B nu poate vedea scadențele vehiculului lui A.
        $this->login($client, $emailB);
        $client->request('GET', "/api/vehicles/$vehicleId/deadlines");
        self::assertContains($client->getResponse()->getStatusCode(), [403, 404], 'Client B nu are acces la scadențele lui A.');
    }

    public function testRejectsExpiryBeforeValidFrom(): void
    {
        $client = static::createClient();
        $email = 'dv-'.uniqid().'@example.test';
        $this->register($client, $email);
        $this->login($client, $email);
        $vehicleId = $this->createVehicle($client, 'WVWZZZ1KZAW123459', 'MS11BBB');

        $client->request('POST', "/api/vehicles/$vehicleId/deadlines", server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'type' => 'ROAD_TAX',
            'validFrom' => '2026-06-01',
            'expiresAt' => '2026-05-01',
        ]));
        self::assertResponseStatusCodeSame(422, 'Expirarea înainte de valid_from trebuie respinsă.');
    }

    private function createVehicle(KernelBrowser $client, string $vin, string $plate): string
    {
        $client->request('POST', '/api/vehicles', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'vin' => $vin,
            'plateNumber' => $plate,
        ]));
        self::assertResponseStatusCodeSame(201);

        return json_decode((string) $client->getResponse()->getContent(), true)['id'];
    }

    private function register(KernelBrowser $client, string $email): void
    {
        $client->request('POST', '/api/auth/register', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'email' => $email, 'password' => 'Parola1234', 'consent' => true,
        ]));
        self::assertResponseStatusCodeSame(201);
    }

    private function login(KernelBrowser $client, string $email): void
    {
        $client->request('POST', '/api/auth/login', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'email' => $email, 'password' => 'Parola1234',
        ]));
        self::assertResponseIsSuccessful();
    }
}
