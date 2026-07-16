<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Document\Application\Message\ScanDocument;
use App\Document\Application\ScanDocumentHandler;
use App\Identity\Domain\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Slice vertical „taxe și impozite" end-to-end (CLIENT + ADMIN):
 *  - clientul adaugă o taxă (an, tip, sumă, scadență) și o marchează plătită cu bizonjat;
 *  - un alt client NU are acces (403);
 *  - descărcarea bizonjatului e autorizată prin proprietar;
 *  - service-ul poate ajusta starea de plată;
 *  - operațiunile ajung în audit.
 *
 * @group functional
 */
final class TaxClientAdminTest extends WebTestCase
{
    private const PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAAC0lEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    public function testTaxFlowWithPaymentReceiptIsolationAndAdminStatus(): void
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
        self::assertSame('UNPAID', $tax['status']);
        self::assertSame(2026, $tax['year']);
        self::assertEqualsWithDelta(480.5, $tax['amount'], 0.001);

        // CLIENT: încarcă bizonjatul și marchează plata.
        $client->request('POST', '/api/documents', files: ['file' => $this->tempUpload('bon.png', base64_decode(self::PNG_BASE64), 'image/png')]);
        self::assertResponseStatusCodeSame(201);
        $documentId = json_decode((string) $client->getResponse()->getContent(), true)['id'];
        $this->scan($documentId);

        $client->request('POST', "/api/taxes/$taxId/pay", server: $this->json(), content: json_encode(['documentIds' => [$documentId]]));
        self::assertResponseIsSuccessful();
        $paid = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame('PAID', $paid['status']);
        self::assertCount(1, $paid['documents']);

        // CLIENT: descărcare autorizată a bizonjatului.
        $client->request('GET', "/api/taxes/$taxId/documents/$documentId");
        self::assertResponseIsSuccessful();
        self::assertSame('image/png', $client->getResponse()->headers->get('Content-Type'));

        // ALT CLIENT: fără acces.
        $this->login($client, $otherEmail, 'Parola1234');
        $client->request('GET', '/api/taxes');
        self::assertResponseIsSuccessful();
        self::assertCount(0, json_decode((string) $client->getResponse()->getContent(), true));
        $client->request('GET', "/api/taxes/$taxId");
        self::assertResponseStatusCodeSame(403);
        $client->request('GET', "/api/taxes/$taxId/documents/$documentId");
        self::assertResponseStatusCodeSame(403);

        // ADMIN: vede taxa și o poate readuce la neplătită (corecție).
        $this->login($client, $adminEmail, 'Parola1234');
        $client->request('GET', '/api/admin/taxes');
        self::assertResponseIsSuccessful();
        self::assertContains($taxId, array_column(json_decode((string) $client->getResponse()->getContent(), true), 'id'));

        $client->request('PATCH', "/api/admin/taxes/$taxId", server: $this->json(), content: json_encode([
            'status' => 'UNPAID', 'note' => 'Plata nu s-a confirmat la trezorerie.',
        ]));
        self::assertResponseIsSuccessful();
        self::assertSame('UNPAID', json_decode((string) $client->getResponse()->getContent(), true)['status']);

        // CLIENT: vede noua stare și nota service-ului.
        $this->login($client, $ownerEmail, 'Parola1234');
        $client->request('GET', "/api/taxes/$taxId");
        self::assertResponseIsSuccessful();
        $seen = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame('UNPAID', $seen['status']);
        self::assertSame('Plata nu s-a confirmat la trezorerie.', $seen['note']);

        // AUDIT.
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        foreach (['tax.created', 'tax.paid', 'tax.status_changed'] as $action) {
            $count = (int) $em->getConnection()->fetchOne('SELECT COUNT(*) FROM audit_logs WHERE action = ?', [$action]);
            self::assertGreaterThanOrEqual(1, $count, "Acțiunea $action trebuie să apară în audit.");
        }
    }

    private function scan(string $documentId): void
    {
        /** @var ScanDocumentHandler $handler */
        $handler = static::getContainer()->get(ScanDocumentHandler::class);
        $handler(new ScanDocument($documentId));
    }

    private function tempUpload(string $name, string $contents, string $mime): UploadedFile
    {
        $path = sys_get_temp_dir().'/bcsc_'.uniqid().'_'.$name;
        file_put_contents($path, $contents);

        return new UploadedFile($path, $name, $mime, null, true);
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
