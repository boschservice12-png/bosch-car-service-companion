<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Identity\Domain\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Slice vertical „mobilitate" end-to-end (CLIENT + ADMIN):
 *  - clientul cere o mașină de înlocuire / taxi / transport;
 *  - un alt client NU are acces (403);
 *  - service-ul aprobă / asigură (status);
 *  - anularea de client e permisă doar cât timp solicitarea e nouă;
 *  - operațiunile ajung în audit.
 *
 * @group functional
 */
final class MobilityClientAdminTest extends WebTestCase
{
    public function testMobilityRequestFlowWithIsolationAndStatus(): void
    {
        $client = static::createClient();
        $ownerEmail = 'own-'.uniqid().'@example.test';
        $otherEmail = 'oth-'.uniqid().'@example.test';
        $adminEmail = 'ad-'.uniqid().'@bcsc.ro';
        $this->createAdmin($adminEmail, 'Parola1234');

        $this->register($client, $ownerEmail);
        $this->register($client, $otherEmail);
        $this->login($client, $ownerEmail, 'Parola1234');

        // CLIENT: cere o mașină de înlocuire.
        $client->request('POST', '/api/mobility-requests', server: $this->json(), content: json_encode([
            'type' => 'REPLACEMENT_CAR',
            'details' => 'Am nevoie de o mașină pe durata reparației, 2-3 zile.',
            'preferredDate' => (new \DateTimeImmutable('+2 days'))->format('Y-m-d'),
        ]));
        self::assertResponseStatusCodeSame(201);
        $req = json_decode((string) $client->getResponse()->getContent(), true);
        $requestId = $req['id'];
        self::assertSame('SUBMITTED', $req['status']);
        self::assertSame('REPLACEMENT_CAR', $req['type']);

        // ALT CLIENT: fără acces.
        $this->login($client, $otherEmail, 'Parola1234');
        $client->request('GET', '/api/mobility-requests');
        self::assertResponseIsSuccessful();
        self::assertCount(0, json_decode((string) $client->getResponse()->getContent(), true));
        $client->request('GET', "/api/mobility-requests/$requestId");
        self::assertResponseStatusCodeSame(403);

        // ADMIN: vede și aprobă solicitarea.
        $this->login($client, $adminEmail, 'Parola1234');
        $client->request('GET', '/api/admin/mobility-requests');
        self::assertResponseIsSuccessful();
        self::assertContains($requestId, array_column(json_decode((string) $client->getResponse()->getContent(), true), 'id'));

        $client->request('PATCH', "/api/admin/mobility-requests/$requestId", server: $this->json(), content: json_encode([
            'status' => 'IN_REVIEW', 'note' => 'Rezervat Logan alb.',
        ]));
        self::assertResponseIsSuccessful();
        self::assertSame('IN_REVIEW', json_decode((string) $client->getResponse()->getContent(), true)['status']);

        // CLIENT: vede noua stare și nota service-ului.
        $this->login($client, $ownerEmail, 'Parola1234');
        $client->request('GET', "/api/mobility-requests/$requestId");
        self::assertResponseIsSuccessful();
        $seen = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame('IN_REVIEW', $seen['status']);
        self::assertSame('Rezervat Logan alb.', $seen['note']);

        // CLIENT: nu mai poate anula o solicitare aprobată.
        $client->request('POST', "/api/mobility-requests/$requestId/cancel");
        self::assertResponseStatusCodeSame(422);

        // CLIENT: poate anula o solicitare nouă.
        $client->request('POST', '/api/mobility-requests', server: $this->json(), content: json_encode([
            'type' => 'TAXI', 'details' => 'Un taxi până acasă azi.',
        ]));
        self::assertResponseStatusCodeSame(201);
        $secondId = json_decode((string) $client->getResponse()->getContent(), true)['id'];
        $client->request('POST', "/api/mobility-requests/$secondId/cancel");
        self::assertResponseIsSuccessful();
        self::assertSame('CANCELLED', json_decode((string) $client->getResponse()->getContent(), true)['status']);

        // AUDIT.
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        foreach (['mobility.created', 'mobility.status_changed', 'mobility.cancelled'] as $action) {
            $count = (int) $em->getConnection()->fetchOne('SELECT COUNT(*) FROM audit_logs WHERE action = ?', [$action]);
            self::assertGreaterThanOrEqual(1, $count, "Acțiunea $action trebuie să apară în audit.");
        }
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
