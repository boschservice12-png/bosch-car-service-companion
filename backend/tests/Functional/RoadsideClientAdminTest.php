<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Identity\Domain\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Slice vertical „asistență rutieră" end-to-end (CLIENT + ADMIN):
 *  - clientul deschide o cerere (locație, problemă, mobilitate, siguranță,
 *    telefon) — FĂRĂ fișiere/foto (clientul nu încarcă nimic);
 *  - un alt client NU are acces (403);
 *  - service-ul o preia (FORWARDED) și clientul vede noua stare;
 *  - anularea de client e permisă doar cât timp cererea e nouă;
 *  - operațiunile ajung în audit.
 *
 * @group functional
 */
final class RoadsideClientAdminTest extends WebTestCase
{
    public function testRoadsideRequestFlowWithIsolationAndStatus(): void
    {
        $client = static::createClient();
        $ownerEmail = 'own-'.uniqid().'@example.test';
        $otherEmail = 'oth-'.uniqid().'@example.test';
        $adminEmail = 'ad-'.uniqid().'@bcsc.ro';
        $this->createAdmin($adminEmail, 'Parola1234');

        $this->register($client, $ownerEmail);
        $this->register($client, $otherEmail);
        $this->login($client, $ownerEmail, 'Parola1234');

        // CLIENT: deschide cererea de asistență — FĂRĂ fișiere/foto (decizie de produs).
        $client->request('POST', '/api/roadside-requests', server: $this->json(), content: json_encode([
            'location' => 'DN13, km 12, lângă Sighișoara',
            'problem' => 'Pană de cauciuc, roata dreapta față.',
            'mobility' => 'NOT_DRIVABLE',
            'safety' => 'AT_RISK',
            'phone' => '+40711223344',
        ]));
        self::assertResponseStatusCodeSame(201);
        $req = json_decode((string) $client->getResponse()->getContent(), true);
        $requestId = $req['id'];
        self::assertSame('SUBMITTED', $req['status']);
        self::assertSame('NOT_DRIVABLE', $req['mobility']);
        self::assertCount(0, $req['documents'], 'Cererea de asistență nu are fișiere încărcate de client.');

        // ALT CLIENT: fără acces.
        $this->login($client, $otherEmail, 'Parola1234');
        $client->request('GET', '/api/roadside-requests');
        self::assertResponseIsSuccessful();
        self::assertCount(0, json_decode((string) $client->getResponse()->getContent(), true));
        $client->request('GET', "/api/roadside-requests/$requestId");
        self::assertResponseStatusCodeSame(403);

        // ADMIN: vede cererea și o preia (FORWARDED, marcaj intern + telefon).
        $this->login($client, $adminEmail, 'Parola1234');
        $client->request('GET', '/api/admin/roadside-requests');
        self::assertResponseIsSuccessful();
        $adminList = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertContains($requestId, array_column($adminList, 'id'));

        // Tranziție nepermisă (SUBMITTED → FORWARDED direct) → 409, fără modificare.
        $client->request('PATCH', "/api/admin/roadside-requests/$requestId", server: $this->json(), content: json_encode([
            'status' => 'FORWARDED', 'note' => 'Preluat, sunăm clientul.',
        ]));
        self::assertResponseStatusCodeSame(409);

        // Fluxul corect conform specificației: VALIDATED → FORWARDED.
        $client->request('PATCH', "/api/admin/roadside-requests/$requestId", server: $this->json(), content: json_encode([
            'status' => 'VALIDATED', 'note' => null,
        ]));
        self::assertResponseIsSuccessful();
        $client->request('PATCH', "/api/admin/roadside-requests/$requestId", server: $this->json(), content: json_encode([
            'status' => 'FORWARDED', 'note' => 'Preluat, sunăm clientul.',
        ]));
        self::assertResponseIsSuccessful();
        self::assertSame('FORWARDED', json_decode((string) $client->getResponse()->getContent(), true)['status']);

        // CLIENT: vede noua stare și numărul de telefon rămâne pe cerere.
        $this->login($client, $ownerEmail, 'Parola1234');
        $client->request('GET', "/api/roadside-requests/$requestId");
        self::assertResponseIsSuccessful();
        $seen = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame('FORWARDED', $seen['status']);
        self::assertSame('+40711223344', $seen['phone']);

        // CLIENT: nu mai poate anula o cerere preluată.
        $client->request('POST', "/api/roadside-requests/$requestId/cancel");
        self::assertResponseStatusCodeSame(422);

        // CLIENT: poate anula o cerere nouă.
        $client->request('POST', '/api/roadside-requests', server: $this->json(), content: json_encode([
            'location' => 'Acasă', 'problem' => 'Nu pornește.', 'mobility' => 'NOT_DRIVABLE', 'safety' => 'SAFE', 'phone' => '+40711223344',
        ]));
        self::assertResponseStatusCodeSame(201);
        $secondId = json_decode((string) $client->getResponse()->getContent(), true)['id'];
        $client->request('POST', "/api/roadside-requests/$secondId/cancel");
        self::assertResponseIsSuccessful();
        self::assertSame('CANCELLED', json_decode((string) $client->getResponse()->getContent(), true)['status']);

        // AUDIT.
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        foreach (['roadside.created', 'roadside.status_changed', 'roadside.cancelled'] as $action) {
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
