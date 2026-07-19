<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Identity\Domain\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Slice vertical „taxe și impozite" end-to-end (CLIENT + ADMIN):
 *  - clientul adaugă o taxă (an, tip, sumă, scadență) și o poate EDITA (PATCH);
 *  - plata se marchează declarativ (parțială cu `amount` sau integrală), FĂRĂ
 *    niciun fișier încărcat (fără bon fiscal — răspunsul nu conține documente);
 *  - o taxă plătită integral nu se mai editează și nu se șterge (corecția
 *    trece prin service, care o readuce la neplătită);
 *  - un alt client NU are acces (403), nici la editare/ștergere;
 *  - clientul își poate șterge taxa neplătită (DELETE → 204);
 *  - operațiunile ajung în audit.
 *
 * @group functional
 */
final class TaxClientAdminTest extends WebTestCase
{
    public function testTaxEditPaymentLockIsolationAndDelete(): void
    {
        $client = static::createClient();
        $ownerEmail = 'own-'.uniqid().'@example.test';
        $otherEmail = 'oth-'.uniqid().'@example.test';
        $adminEmail = 'ad-'.uniqid().'@bcsc.ro';
        $this->createAdmin($adminEmail, 'Parola1234');

        $this->register($client, $ownerEmail);
        $this->register($client, $otherEmail);
        $this->login($client, $ownerEmail, 'Parola1234');

        // CLIENT: adaugă o taxă anuală.
        $client->request('POST', '/api/taxes', server: $this->json(), content: json_encode([
            'year' => 2026,
            'type' => 'VEHICLE_TAX',
            'amount' => 480.5,
            'dueDate' => '2026-03-31',
        ]));
        self::assertResponseStatusCodeSame(201);
        $tax = json_decode((string) $client->getResponse()->getContent(), true);
        $taxId = $tax['id'];
        self::assertSame('OVERDUE', $tax['status'], 'Scadență depășită fără plată → OVERDUE (derivat).');
        self::assertArrayNotHasKey('documents', $tax, 'Fluxul de taxe nu are documente — nu se încarcă nimic.');

        // CLIENT: editează taxa (suma, scadența și tipul).
        $client->request('PATCH', "/api/taxes/$taxId", server: $this->json(), content: json_encode([
            'amount' => 520,
            'dueDate' => '2027-06-30',
            'type' => 'OTHER',
        ]));
        self::assertResponseIsSuccessful();
        $edited = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertEqualsWithDelta(520.0, $edited['amount'], 0.001);
        self::assertSame('2027-06-30', $edited['dueDate']);
        self::assertSame('OTHER', $edited['type']);
        self::assertSame(2026, $edited['year'], 'Câmpurile netrimise rămân neschimbate.');
        self::assertSame('UNPAID', $edited['status'], 'Cu noua scadență în viitor, nu mai e restantă.');

        // ALT CLIENT: fără acces — nici citire, nici editare, nici ștergere.
        $this->login($client, $otherEmail, 'Parola1234');
        $client->request('GET', "/api/taxes/$taxId");
        self::assertResponseStatusCodeSame(403);
        $client->request('PATCH', "/api/taxes/$taxId", server: $this->json(), content: json_encode(['amount' => 1]));
        self::assertResponseStatusCodeSame(403);
        $client->request('DELETE', "/api/taxes/$taxId");
        self::assertResponseStatusCodeSame(403);

        // CLIENT: plată parțială declarativă (fără niciun fișier), apoi integrală.
        $this->login($client, $ownerEmail, 'Parola1234');
        $client->request('POST', "/api/taxes/$taxId/pay", server: $this->json(), content: json_encode(['amount' => 200]));
        self::assertResponseIsSuccessful();
        $partial = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame('PARTIALLY_PAID', $partial['status']);
        self::assertEqualsWithDelta(200.0, $partial['paidAmount'], 0.001);

        $client->request('POST', "/api/taxes/$taxId/pay", server: $this->json(), content: json_encode([]));
        self::assertResponseIsSuccessful();
        $paid = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame('PAID', $paid['status']);
        self::assertEqualsWithDelta(520.0, $paid['paidAmount'], 0.001);

        // CLIENT: taxa plătită e blocată la editare și ștergere.
        $client->request('PATCH', "/api/taxes/$taxId", server: $this->json(), content: json_encode(['amount' => 10]));
        self::assertResponseStatusCodeSame(422);
        $client->request('DELETE', "/api/taxes/$taxId");
        self::assertResponseStatusCodeSame(422);

        // ADMIN: corecția — readuce taxa la neplătită, cu notă.
        $this->login($client, $adminEmail, 'Parola1234');
        $client->request('PATCH', "/api/admin/taxes/$taxId", server: $this->json(), content: json_encode([
            'status' => 'UNPAID', 'note' => 'Plata nu s-a confirmat la trezorerie.',
        ]));
        self::assertResponseIsSuccessful();

        // CLIENT: vede nota, poate edita din nou și poate șterge taxa.
        $this->login($client, $ownerEmail, 'Parola1234');
        $client->request('GET', "/api/taxes/$taxId");
        self::assertResponseIsSuccessful();
        $seen = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame('Plata nu s-a confirmat la trezorerie.', $seen['note']);

        $client->request('PATCH', "/api/taxes/$taxId", server: $this->json(), content: json_encode(['year' => 2027]));
        self::assertResponseIsSuccessful();
        self::assertSame(2027, json_decode((string) $client->getResponse()->getContent(), true)['year']);

        $client->request('DELETE', "/api/taxes/$taxId");
        self::assertResponseStatusCodeSame(204);
        $client->request('GET', "/api/taxes/$taxId");
        self::assertResponseStatusCodeSame(404);

        // AUDIT.
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        foreach (['tax.created', 'tax.updated', 'tax.payment_registered', 'tax.paid', 'tax.status_changed', 'tax.deleted'] as $action) {
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
