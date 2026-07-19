<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Identity\Domain\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Cererea de ofertă (funcționalitatea 7) end-to-end, pe mașina de stări din
 * specificație: ciornă → trimisă → în analiză → informații necesare → revenire
 * → răspuns cu ofertă → acceptată; tranzițiile nepermise → 409; ciornele nu
 * apar în portalul service; izolare între clienți; audit.
 *
 * @group functional
 */
final class QuoteRequestClientAdminTest extends ApiTestCase
{
    public function testFullJourneyDraftToAcceptedWithGuardsAndIsolation(): void
    {
        $client = static::createClient();
        $ownerEmail = 'own-'.uniqid().'@example.test';
        $otherEmail = 'oth-'.uniqid().'@example.test';
        $adminEmail = 'ad-'.uniqid().'@bcsc.ro';
        $this->createAdmin($adminEmail, 'Parola1234');

        $this->register($client, $ownerEmail);
        $this->register($client, $otherEmail);
        $this->login($client, $ownerEmail, 'Parola1234');

        $client->request('POST', '/api/vehicles', server: $this->json(), content: json_encode([
            'vin' => 'WBA3A5C50EF'.str_pad((string) random_int(100000, 999999), 6, '0'), 'plateNumber' => 'MS70OFR',
        ]));
        self::assertResponseStatusCodeSame(201);
        $vehicleId = json_decode((string) $client->getResponse()->getContent(), true)['id'];

        // CLIENT: salvează o ciornă.
        $client->request('POST', '/api/quote-requests', server: $this->json(), content: json_encode([
            'vehicleId' => $vehicleId,
            'mileage' => 78400,
            'symptomDescription' => 'Zgomot metalic la frânare, în față.',
            'occurrenceConditions' => 'La frânări puternice.',
            'vehicleDrivable' => true,
            'warningLights' => 'Niciunul',
            'preferredContactMethod' => 'PHONE',
            'preferredInterval' => 'După 16:00',
            'draft' => true,
        ]));
        self::assertResponseStatusCodeSame(201);
        $created = json_decode((string) $client->getResponse()->getContent(), true);
        $requestId = $created['id'];
        self::assertSame('DRAFT', $created['status']);

        // ADMIN: ciorna NU apare în portal și nu e accesibilă direct.
        $this->login($client, $adminEmail, 'Parola1234');
        $client->request('GET', '/api/admin/quote-requests');
        self::assertResponseIsSuccessful();
        self::assertNotContains($requestId, array_column(json_decode((string) $client->getResponse()->getContent(), true), 'id'));
        $client->request('GET', "/api/admin/quote-requests/$requestId");
        self::assertResponseStatusCodeSame(403, 'Ciorna clientului este privată.');

        // CLIENT: trimite ciorna (DRAFT → SUBMITTED).
        $this->login($client, $ownerEmail, 'Parola1234');
        $client->request('POST', "/api/quote-requests/$requestId/submit");
        self::assertResponseIsSuccessful();
        self::assertSame('SUBMITTED', json_decode((string) $client->getResponse()->getContent(), true)['status']);

        // ALT CLIENT: izolare (403 la detaliu, listă goală).
        $this->login($client, $otherEmail, 'Parola1234');
        $client->request('GET', '/api/quote-requests');
        self::assertCount(0, json_decode((string) $client->getResponse()->getContent(), true));
        $client->request('GET', "/api/quote-requests/$requestId");
        self::assertResponseStatusCodeSame(403);
        $client->request('POST', "/api/quote-requests/$requestId/accept");
        self::assertResponseStatusCodeSame(403, 'Doar clientul propriu poate opera pe cerere.');

        // ADMIN: răspuns direct din SUBMITTED → 409 (întâi IN_REVIEW).
        $this->login($client, $adminEmail, 'Parola1234');
        $client->request('POST', "/api/admin/quote-requests/$requestId/response", server: $this->json(), content: json_encode([
            'message' => 'Ofertă: 1.250 RON.',
        ]));
        self::assertResponseStatusCodeSame(409);

        // ADMIN: preia în analiză, apoi cere informații.
        $client->request('PATCH', "/api/admin/quote-requests/$requestId/status", server: $this->json(), content: json_encode([
            'status' => 'IN_REVIEW',
        ]));
        self::assertResponseIsSuccessful();
        $client->request('PATCH', "/api/admin/quote-requests/$requestId/status", server: $this->json(), content: json_encode([
            'status' => 'NEEDS_INFORMATION',
        ]));
        self::assertResponseIsSuccessful();

        // CLIENT: completează informațiile și revine în analiză.
        $this->login($client, $ownerEmail, 'Parola1234');
        $client->request('POST', "/api/quote-requests/$requestId/resubmit");
        self::assertResponseIsSuccessful();
        self::assertSame('IN_REVIEW', json_decode((string) $client->getResponse()->getContent(), true)['status']);

        // ADMIN: răspunde cu oferta (text) → REPLIED.
        $this->login($client, $adminEmail, 'Parola1234');
        $client->request('POST', "/api/admin/quote-requests/$requestId/response", server: $this->json(), content: json_encode([
            'message' => 'Verificare + înlocuire plăcuțe față: 1.250,00 RON (piese + manoperă).',
        ]));
        self::assertResponseIsSuccessful();
        $replied = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame('REPLIED', $replied['status']);
        self::assertCount(1, $replied['responses']);

        // CLIENT: vede oferta și o acceptă; apoi orice tranziție admin → 409.
        $this->login($client, $ownerEmail, 'Parola1234');
        $client->request('POST', "/api/quote-requests/$requestId/accept");
        self::assertResponseIsSuccessful();
        self::assertSame('ACCEPTED', json_decode((string) $client->getResponse()->getContent(), true)['status']);

        $this->login($client, $adminEmail, 'Parola1234');
        $client->request('PATCH', "/api/admin/quote-requests/$requestId/status", server: $this->json(), content: json_encode([
            'status' => 'IN_REVIEW',
        ]));
        self::assertResponseStatusCodeSame(409, 'Din ACCEPTED nu se revine în analiză.');

        // AUDIT: pașii cheie sunt înregistrați.
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        foreach (['quote_request.created', 'quote_request.submitted', 'quote_request.replied', 'quote_request.accepted'] as $action) {
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
