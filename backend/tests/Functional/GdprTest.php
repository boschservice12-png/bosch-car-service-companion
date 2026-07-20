<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Customer\Application\GdprService;
use App\Identity\Domain\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * P1-06 — drepturile GDPR și politica de retenție:
 *  - exportul conține datele clientului (cont, vehicule, taxe, conversații);
 *  - cererea de ștergere cere parola corectă, blochează contul imediat și
 *    închide sesiunea;
 *  - purjarea NU atinge contul în perioada de grație, iar după grație îl
 *    anonimizează ireversibil (email/nume/telefon), închide legătura de
 *    proprietate (vehiculul rămâne în evidență) și șterge conversațiile;
 *  - contul anonimizat nu poate fi revendicat prin re-înregistrare;
 *  - retenția jurnalului de audit șterge doar intrările mai vechi de prag.
 *
 * @group functional
 */
final class GdprTest extends ApiTestCase
{
    public function testExportDeletionGraceAndPurge(): void
    {
        $client = static::createClient();
        $email = 'gdpr-'.uniqid().'@example.test';

        // Cont + vehicul + taxă + conversație.
        $client->request('POST', '/api/auth/register', server: $this->json(), content: json_encode([
            'email' => $email, 'password' => 'Parola1234', 'consent' => true,
            'firstName' => 'Gigi', 'lastName' => 'Gdpr',
        ]));
        self::assertResponseStatusCodeSame(201);
        $this->login($client, $email);

        $vin = 'WBA3A5C50EF'.str_pad((string) random_int(100000, 999999), 6, '0');
        $client->request('POST', '/api/vehicles', server: $this->json(), content: json_encode([
            'vin' => $vin, 'plateNumber' => 'MS 91 GDP',
        ]));
        self::assertResponseStatusCodeSame(201);
        $client->request('POST', '/api/taxes', server: $this->json(), content: json_encode([
            'year' => 2026, 'type' => 'VEHICLE_TAX', 'amount' => 300,
        ]));
        self::assertResponseStatusCodeSame(201);
        $client->request('POST', '/api/conversations', server: $this->json(), content: json_encode([
            'subject' => 'Întrebare GDPR', 'body' => 'Text personal în mesaj.',
        ]));
        self::assertResponseStatusCodeSame(201);
        // Cerere de asistență rutieră — locația e text liber personal.
        $client->request('POST', '/api/roadside-requests', server: $this->json(), content: json_encode([
            'location' => 'Str. Personală 7, Târgu Mureș', 'problem' => 'Pană lângă casă',
            'mobility' => 'DRIVABLE', 'safety' => 'SAFE', 'phone' => '0740 000 000',
        ]));
        self::assertResponseStatusCodeSame(201);

        // Dosar de daună + document încărcat de client (fișier real în storage).
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        /** @var \App\Identity\Domain\User $user */
        $user = $em->getRepository(User::class)->findOneBy(['email' => $email]);
        /** @var \App\Document\Domain\StorageAdapter $storage */
        $storage = static::getContainer()->get(\App\Document\Domain\StorageAdapter::class);
        $storageKey = 'gd/gdpr-test-'.uniqid().'.txt';
        $tmp = tempnam(sys_get_temp_dir(), 'gdpr');
        file_put_contents((string) $tmp, 'poza de dauna');
        $storage->store((string) $tmp, $storageKey, 'text/plain');
        self::assertTrue($storage->exists($storageKey));
        $document = new \App\Document\Domain\Document($storageKey, 'text/plain', 13, $user, 'dauna.jpg');
        $claim = new \App\DamageClaim\Domain\DamageClaim(
            $user, null, new \DateTimeImmutable('-3 days'),
            'Intersecția de lângă serviciu', 'Tamponare ușoară, vinovat terț.',
            'Asigurătorul SA', 'RO-123456',
        );
        $claim->attach($document);
        $em->persist($document);
        $em->persist($claim);
        $em->flush();

        // Export: conține contul și datele operaționale.
        $client->request('GET', '/api/me/export');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('attachment', (string) $client->getResponse()->headers->get('Content-Disposition'));
        $export = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame($email, $export['account']['email']);
        self::assertSame('Gigi Gdpr', $export['account']['name']);
        self::assertSame($vin, $export['vehicles'][0]['vin']);
        self::assertCount(1, $export['taxes']);
        self::assertSame('Întrebare GDPR', $export['conversations'][0]['subject']);
        self::assertCount(1, $export['roadsideRequests']);
        self::assertSame('Str. Personală 7, Târgu Mureș', $export['roadsideRequests'][0]['location']);
        self::assertCount(1, $export['damageClaims'], 'Dosarele de daună fac parte din export.');
        self::assertSame('Asigurătorul SA', $export['damageClaims'][0]['insurer']);
        self::assertSame(['dauna.jpg'], $export['damageClaims'][0]['documents']);

        // Ștergere cu parolă greșită → 422; cu parola corectă → cont blocat.
        $client->request('POST', '/api/me/delete', server: $this->json(), content: json_encode(['password' => 'gresita9']));
        self::assertResponseStatusCodeSame(422);
        $client->request('POST', '/api/me/delete', server: $this->json(), content: json_encode(['password' => 'Parola1234']));
        self::assertResponseIsSuccessful();

        // Sesiunea s-a închis, iar loginul e refuzat (cont dezactivat).
        $client->request('GET', '/api/me');
        self::assertResponseStatusCodeSame(401);
        $client->request('POST', '/api/auth/login', server: $this->json(), content: json_encode([
            'email' => $email, 'password' => 'Parola1234',
        ]));
        self::assertResponseStatusCodeSame(401, 'Contul cu ștergere cerută nu se mai poate autentifica.');

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        /** @var GdprService $gdpr */
        $gdpr = static::getContainer()->get(GdprService::class);

        // În perioada de grație purjarea nu atinge contul.
        $result = $gdpr->purge(graceDays: 30);
        self::assertSame(0, $result['purgedUsers'], 'Grația de 30 de zile este respectată.');

        // Simulăm expirarea grației și purjăm.
        $em->createQuery('UPDATE '.User::class.' u SET u.deletionRequestedAt = :dt WHERE u.email = :email')
            ->setParameter('dt', new \DateTimeImmutable('-31 days'), 'datetimetz_immutable')
            ->setParameter('email', $email)
            ->execute();
        $em->clear();
        $result = $gdpr->purge(graceDays: 30);
        self::assertSame(1, $result['purgedUsers']);

        // Identitatea a dispărut; vehiculul rămâne, fără proprietar activ.
        self::assertNull($em->getRepository(User::class)->findOneBy(['email' => $email]));
        $anon = $em->getConnection()->fetchAssociative(
            "SELECT u.email, p.first_name, p.last_name, p.phone FROM users u JOIN customer_profiles p ON p.user_id = u.id WHERE u.email LIKE 'sters-%@anonim.local'",
        );
        self::assertNotFalse($anon);
        self::assertSame('Cont', $anon['first_name']);
        $vehicleCount = (int) $em->getConnection()->fetchOne('SELECT COUNT(*) FROM vehicles WHERE vin = ?', [$vin]);
        self::assertSame(1, $vehicleCount, 'Vehiculul rămâne în evidența service-ului.');
        $activeOwners = (int) $em->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM vehicle_ownerships o JOIN vehicles v ON v.id = o.vehicle_id WHERE v.vin = ? AND o.active = true',
            [$vin],
        );
        self::assertSame(0, $activeOwners, 'Legătura de proprietate s-a închis.');
        $convCount = (int) $em->getConnection()->fetchOne(
            "SELECT COUNT(*) FROM conversations c WHERE c.subject = 'Întrebare GDPR'",
        );
        self::assertSame(0, $convCount, 'Conversațiile clientului s-au șters.');

        // TOATE datele personale ale clientului au dispărut, nu doar conversațiile.
        self::assertSame(0, (int) $em->getConnection()->fetchOne(
            "SELECT COUNT(*) FROM roadside_requests WHERE location LIKE 'Str. Personală%'",
        ), 'Cererile de asistență (text liber personal) s-au șters.');
        self::assertSame(0, (int) $em->getConnection()->fetchOne(
            "SELECT COUNT(*) FROM damage_claims WHERE insurer = 'Asigurătorul SA'",
        ), 'Dosarele de daună s-au șters.');
        self::assertSame(0, (int) $em->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM tax_items t JOIN users u ON u.id = t.customer_id WHERE u.email LIKE ?',
            ['sters-%@anonim.local'],
        ), 'Taxele clientului s-au șters.');
        self::assertSame(0, (int) $em->getConnection()->fetchOne(
            "SELECT COUNT(*) FROM documents WHERE original_name = 'dauna.jpg'",
        ), 'Documentele încărcate de client s-au șters din DB.');
        self::assertFalse($storage->exists($storageKey), 'Fișierul din storage s-a șters odată cu contul.');

        // Emailul vechi NU redevine folosibil... dar nici cel anonimizat.
        $client->request('POST', '/api/auth/register', server: $this->json(), content: json_encode([
            'email' => (string) $anon['email'], 'password' => 'Parola1234', 'consent' => true,
        ]));
        self::assertResponseStatusCodeSame(422, 'Un cont anonimizat nu se revendică.');

        // Retenția auditului: intrările vechi dispar, cele recente rămân.
        $em->createQuery('UPDATE '.\App\Audit\Domain\AuditLog::class." a SET a.createdAt = :dt WHERE a.action = 'user.deletion_requested'")
            ->setParameter('dt', new \DateTimeImmutable('-400 days'), 'datetimetz_immutable')
            ->execute();
        $result = $gdpr->purge(auditDays: 365);
        self::assertGreaterThanOrEqual(1, $result['deletedAuditLogs']);
        $old = (int) $em->getConnection()->fetchOne("SELECT COUNT(*) FROM audit_logs WHERE action = 'user.deletion_requested'");
        self::assertSame(0, $old);
        $recent = (int) $em->getConnection()->fetchOne("SELECT COUNT(*) FROM audit_logs WHERE action = 'user.purged'");
        self::assertGreaterThanOrEqual(1, $recent, 'Intrările recente de audit rămân.');
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
