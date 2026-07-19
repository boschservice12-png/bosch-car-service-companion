<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Customer\Domain\CustomerProfile;
use App\Identity\Domain\User;
use App\Vehicle\Domain\Vehicle;
use App\Vehicle\Domain\VehicleOwnership;
use App\Vehicle\Domain\Vin;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * P0-02 — integritate la nivel de business ȘI de bază de date:
 *  - același VIN activ nu poate exista de două ori (nici pentru același client,
 *    nici pentru un altul) → 409 Conflict, cu mesaj de business;
 *  - un vehicul nu poate avea doi proprietari activi simultan — indexul unic
 *    parțial ux_vehicle_active_owner respinge inserarea la nivel de bază.
 *
 * @group functional
 */
final class VehicleVinUniqueTest extends WebTestCase
{
    public function testDuplicateActiveVinRejectedWith409(): void
    {
        $client = static::createClient();
        $firstEmail = 'own-'.uniqid().'@example.test';
        $secondEmail = 'oth-'.uniqid().'@example.test';
        $this->register($client, $firstEmail);
        $this->register($client, $secondEmail);

        $vin = 'WBA3A5C50EF'.str_pad((string) random_int(100000, 999999), 6, '0');

        // Primul client înregistrează vehiculul.
        $this->login($client, $firstEmail);
        $client->request('POST', '/api/vehicles', server: $this->json(), content: json_encode([
            'vin' => $vin, 'plateNumber' => 'MS 61 UNQ',
        ]));
        self::assertResponseStatusCodeSame(201);

        // Același client, același VIN → 409.
        $client->request('POST', '/api/vehicles', server: $this->json(), content: json_encode([
            'vin' => $vin, 'plateNumber' => 'MS 62 UNQ',
        ]));
        self::assertResponseStatusCodeSame(409, 'VIN activ duplicat → 409 Conflict.');

        // Alt client, același VIN (chiar cu litere mici) → tot 409.
        $this->login($client, $secondEmail);
        $client->request('POST', '/api/vehicles', server: $this->json(), content: json_encode([
            'vin' => strtolower($vin), 'plateNumber' => 'MS 63 UNQ',
        ]));
        self::assertResponseStatusCodeSame(409, 'VIN-ul se normalizează uppercase — duplicatul e detectat.');
    }

    public function testSecondActiveOwnershipRejectedByDatabase(): void
    {
        static::bootKernel();
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $userA = new User('own-'.uniqid().'@example.test');
        $profileA = new CustomerProfile($userA, 'Ana', 'Unu');
        $userB = new User('oth-'.uniqid().'@example.test');
        $profileB = new CustomerProfile($userB, 'Bogdan', 'Doi');
        $vehicle = new Vehicle(new Vin('WBA3A5C50EF'.str_pad((string) random_int(100000, 999999), 6, '0')), 'MS 64 UNQ');
        foreach ([$userA, $profileA, $userB, $profileB, $vehicle] as $entity) {
            $em->persist($entity);
        }
        $em->flush();

        $em->persist(new VehicleOwnership($vehicle, $profileA));
        $em->flush();

        // Al doilea proprietar ACTIV pentru același vehicul → respins de index.
        $em->persist(new VehicleOwnership($vehicle, $profileB));
        $this->expectException(UniqueConstraintViolationException::class);
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
