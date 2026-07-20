<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Identity\Domain\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * P1-03 (decizie de produs) — notificări prin WhatsApp-ul propriu al
 * service-ului + email, trimise de operator:
 *  - adminul vede scadențele care expiră în fereastra cerută (inclusiv cele
 *    expirate), cu numele/telefonul/emailul proprietarului;
 *  - o scadență îndepărtată NU apare în fereastra implicită;
 *  - consemnarea notificării (per canal) apare la următoarea listare și în
 *    audit; canal necunoscut → 422;
 *  - clientul nu are acces la aceste rute.
 *
 * @group functional
 */
final class AdminDeadlineNotificationsTest extends ApiTestCase
{
    public function testUpcomingListingAndManualNotificationMarking(): void
    {
        $client = static::createClient();
        $adminEmail = 'ad-'.uniqid().'@bcsc.ro';
        $ownerEmail = 'own-'.uniqid().'@example.test';
        $this->createAdmin($adminEmail, 'Parola1234');

        // CLIENT: vehicul + două scadențe (10 zile / 300 de zile).
        $client->request('POST', '/api/auth/register', server: $this->json(), content: json_encode([
            'email' => $ownerEmail, 'password' => 'Parola1234', 'consent' => true,
            'firstName' => 'Ana', 'lastName' => 'Notif',
        ]));
        self::assertResponseStatusCodeSame(201);
        $this->login($client, $ownerEmail);
        $vin = 'WBA3A5C50EF'.str_pad((string) random_int(100000, 999999), 6, '0');
        $client->request('POST', '/api/vehicles', server: $this->json(), content: json_encode([
            'vin' => $vin, 'plateNumber' => 'MS 81 NTF',
        ]));
        self::assertResponseStatusCodeSame(201);
        $vehicleId = json_decode((string) $client->getResponse()->getContent(), true)['id'];

        $soon = (new \DateTimeImmutable('+10 days'))->format('Y-m-d');
        $far = (new \DateTimeImmutable('+300 days'))->format('Y-m-d');
        foreach ([['ITP', $soon], ['RCA', $far]] as [$type, $date]) {
            $client->request('POST', "/api/vehicles/$vehicleId/deadlines", server: $this->json(), content: json_encode([
                'type' => $type, 'expiresAt' => $date,
            ]));
            self::assertResponseStatusCodeSame(201);
        }

        // CLIENT: fără acces la rutele de notificare.
        $client->request('GET', '/api/admin/deadlines/upcoming');
        self::assertResponseStatusCodeSame(403);

        // ADMIN: fereastra implicită (30 de zile) conține ITP-ul, nu și RCA-ul.
        $this->login($client, $adminEmail);
        $client->request('GET', '/api/admin/deadlines/upcoming');
        self::assertResponseIsSuccessful();
        $body = json_decode((string) $client->getResponse()->getContent(), true);
        $ours = array_values(array_filter($body['items'], fn (array $i) => $i['vehicle']['plateNumber'] === 'MS 81 NTF'));
        self::assertCount(1, $ours, 'Doar scadența apropiată intră în fereastra de 30 de zile.');
        $item = $ours[0];
        self::assertSame('ITP', $item['type']);
        self::assertSame(10, $item['daysLeft']);
        self::assertSame('Ana Notif', $item['owner']['name']);
        self::assertSame($ownerEmail, $item['owner']['email']);
        self::assertNull($item['lastNotifiedAt'], 'Nicio notificare consemnată încă.');

        // Fereastra extinsă include și RCA-ul.
        $client->request('GET', '/api/admin/deadlines/upcoming?days=365');
        $all = json_decode((string) $client->getResponse()->getContent(), true);
        $oursAll = array_filter($all['items'], fn (array $i) => $i['vehicle']['plateNumber'] === 'MS 81 NTF');
        self::assertCount(2, $oursAll);

        // Canal necunoscut → 422; consemnare validă → apare la listare + audit.
        $client->request('POST', "/api/admin/deadlines/{$item['id']}/notifications", server: $this->json(), content: json_encode(['channel' => 'sms']));
        self::assertResponseStatusCodeSame(422);
        $client->request('POST', "/api/admin/deadlines/{$item['id']}/notifications", server: $this->json(), content: json_encode(['channel' => 'whatsapp']));
        self::assertResponseIsSuccessful();
        // Dubla consemnare în aceeași zi rămâne idempotentă (fără eroare).
        $client->request('POST', "/api/admin/deadlines/{$item['id']}/notifications", server: $this->json(), content: json_encode(['channel' => 'whatsapp']));
        self::assertResponseIsSuccessful();

        $client->request('GET', '/api/admin/deadlines/upcoming');
        $after = json_decode((string) $client->getResponse()->getContent(), true);
        $marked = array_values(array_filter($after['items'], fn (array $i) => $i['id'] === $item['id']))[0];
        self::assertNotNull($marked['lastNotifiedAt'], 'Notificarea consemnată apare la listare.');

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $count = (int) $em->getConnection()->fetchOne(
            "SELECT COUNT(*) FROM audit_logs WHERE action = 'deadline.manual_notification'",
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

    private function login(KernelBrowser $client, string $email): void
    {
        $client->request('POST', '/api/auth/login', server: $this->json(), content: json_encode([
            'email' => $email, 'password' => 'Parola1234',
        ]));
        self::assertResponseIsSuccessful();
    }
}
