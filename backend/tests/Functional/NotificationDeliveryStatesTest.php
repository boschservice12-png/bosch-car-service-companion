<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Audit\Application\AuditRecorder;
use App\Identity\Domain\User;
use App\Notification\Application\Message\SendNotification;
use App\Notification\Application\SendNotificationHandler;
use App\Notification\Domain\Notification;
use App\Notification\Domain\NotificationDelivery;
use App\Notification\Domain\NotificationDeliveryResult;
use App\Notification\Domain\NotificationRepository;
use App\Notification\Domain\NotificationStatus;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Blocul 4 — stări de livrare adevărate.
 *
 * O notificare NU e SENT doar pentru că există un rând. Fără furnizor automat
 * (pilot) starea e MANUAL_ACTION_REQUIRED sau SKIPPED. Un furnizor automat duce
 * la SENT (succes) sau FAILED (eșec, retryable). SENT „manual" doar prin acțiune
 * explicită de admin.
 *
 * @group functional
 */
final class NotificationDeliveryStatesTest extends ApiTestCase
{
    private static ?string $runSuffix = null;

    /** Cheie dedup unică per rulare (baza de test persistă între rulări). */
    private static function k(string $base): string
    {
        self::$runSuffix ??= bin2hex(random_bytes(4));

        return $base.'-'.self::$runSuffix;
    }

    public function testProviderlessNotificationIsManualNotSentAndIdempotent(): void
    {
        static::createClient();
        $userId = $this->createUser('cli-'.uniqid().'@example.test');

        $handler = $this->handlerWith($this->realDelivery());
        $handler(new SendNotification($userId, 'deadline.reminder', [], 'push', self::k('dk1')));

        $n = $this->byDedup(self::k('dk1'));
        self::assertSame(NotificationStatus::MANUAL_ACTION_REQUIRED, $n->status(), 'Fără furnizor automat nu e SENT.');
        self::assertNull($n->provider());

        // Idempotență: același mesaj (dedupKey) a doua oară → tot O notificare.
        $handler(new SendNotification($userId, 'deadline.reminder', [], 'push', self::k('dk1')));
        self::assertSame(1, $this->countByDedup(self::k('dk1')), 'Reîncercarea nu creează un duplicat.');
    }

    public function testInternalRecipientIsSkipped(): void
    {
        static::createClient();
        $userId = $this->createUser('import-'.uniqid().'@clienti.local');

        $handler = $this->handlerWith($this->realDelivery());
        $handler(new SendNotification($userId, 'deadline.reminder', [], 'push', self::k('dk2')));

        self::assertSame(NotificationStatus::SKIPPED, $this->byDedup(self::k('dk2'))->status());
    }

    public function testAutomaticProviderSuccessIsSent(): void
    {
        static::createClient();
        $userId = $this->createUser('ok-'.uniqid().'@example.test');

        $fake = new class implements NotificationDelivery {
            public function deliver(Notification $n): NotificationDeliveryResult
            {
                return NotificationDeliveryResult::sent('test-provider');
            }
        };
        $this->handlerWith($fake)(new SendNotification($userId, 'x', [], 'email', self::k('dk3')));

        $n = $this->byDedup(self::k('dk3'));
        self::assertSame(NotificationStatus::SENT, $n->status());
        self::assertSame('test-provider', $n->provider());
        self::assertNotNull($n->sentAt());
    }

    public function testAutomaticProviderFailureIsFailedAndRetryable(): void
    {
        static::createClient();
        $userId = $this->createUser('fail-'.uniqid().'@example.test');

        $fake = new class implements NotificationDelivery {
            public function deliver(Notification $n): NotificationDeliveryResult
            {
                return NotificationDeliveryResult::failed('provider timeout', 'test-provider', true);
            }
        };

        try {
            $this->handlerWith($fake)(new SendNotification($userId, 'x', [], 'email', self::k('dk4')));
            self::fail('Un eșec retryable trebuie să arunce (ca Messenger să reîncerce).');
        } catch (\RuntimeException) {
            // așteptat
        }

        $n = $this->byDedup(self::k('dk4'));
        self::assertSame(NotificationStatus::FAILED, $n->status());
        self::assertSame('provider timeout', $n->failureReason());
        self::assertSame(1, $n->attempts());
    }

    public function testMissingRecipientPersistsNothing(): void
    {
        static::createClient();
        $handler = $this->handlerWith($this->realDelivery());
        $handler(new SendNotification((string) \Symfony\Component\Uid\Uuid::v7(), 'x', [], 'push', self::k('dk5')));

        self::assertSame(0, $this->countByDedup(self::k('dk5')), 'Fără destinatar nu se persistă notificare.');
    }

    public function testAdminManualSendMarksSentClientForbidden(): void
    {
        $client = static::createClient();
        $adminEmail = 'ad-'.uniqid().'@bcsc.ro';
        $this->createAdmin($adminEmail, 'Parola1234');
        $clientEmail = 'cl-'.uniqid().'@example.test';
        $userId = $this->createUser($clientEmail); // recipient of the notification

        // Notificare fără furnizor → MANUAL_ACTION_REQUIRED.
        $this->handlerWith($this->realDelivery())(new SendNotification($userId, 'x', [], 'push', self::k('dk6')));
        $notifId = (string) $this->byDedup(self::k('dk6'))->id();

        // Un CLIENT nu poate confirma trimiterea (portal admin) → 403.
        $this->registerAndLoginClient($client, $clientEmail);
        $client->request('POST', "/api/admin/notifications/$notifId/manually-sent", server: $this->json(), content: json_encode(['channel' => 'whatsapp']));
        self::assertResponseStatusCodeSame(403);

        // Adminul confirmă trimiterea manuală → SENT.
        $this->login($client, $adminEmail, 'Parola1234');
        $client->request('POST', "/api/admin/notifications/$notifId/manually-sent", server: $this->json(), content: json_encode([
            'channel' => 'whatsapp', 'note' => 'Trimis de pe numărul service-ului.',
        ]));
        self::assertResponseIsSuccessful();
        self::assertSame('SENT', json_decode((string) $client->getResponse()->getContent(), true)['status']);
    }

    // --- helpers ---

    private function handlerWith(NotificationDelivery $delivery): SendNotificationHandler
    {
        $c = static::getContainer();

        return new SendNotificationHandler(
            $c->get(EntityManagerInterface::class),
            $c->get(NotificationRepository::class),
            $delivery,
            $c->get(AuditRecorder::class),
            new NullLogger(),
        );
    }

    private function realDelivery(): NotificationDelivery
    {
        return static::getContainer()->get(NotificationDelivery::class);
    }

    private function createUser(string $email): string
    {
        $c = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $c->get(EntityManagerInterface::class);
        /** @var UserPasswordHasherInterface $hasher */
        $hasher = $c->get(UserPasswordHasherInterface::class);
        $user = new User($email, User::ROLE_CLIENT);
        $user->setPasswordHash($hasher->hashPassword($user, 'Parola1234'));
        $em->persist($user);
        $em->flush();

        return (string) $user->id();
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

    private function byDedup(string $key): Notification
    {
        $n = static::getContainer()->get(NotificationRepository::class)->findByDedupKey($key);
        self::assertInstanceOf(Notification::class, $n, "Notificarea $key ar trebui să existe.");

        return $n;
    }

    private function countByDedup(string $key): int
    {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);

        return (int) $em->getConnection()->fetchOne('SELECT COUNT(*) FROM notifications WHERE dedup_key = ?', [$key]);
    }

    /** @return array<string, string> */
    private function json(): array
    {
        return ['CONTENT_TYPE' => 'application/json'];
    }

    private function registerAndLoginClient(KernelBrowser $client, string $email): void
    {
        // contul poate exista deja (creat ca destinatar) → îl activăm prin login direct nu e posibil
        // (parolă necunoscută pentru contul creat direct), deci creăm un client separat de test.
        $freshEmail = 'guard-'.uniqid().'@example.test';
        $client->request('POST', '/api/auth/register', server: $this->json(), content: json_encode([
            'email' => $freshEmail, 'password' => 'Parola1234', 'consent' => true,
        ]));
        self::assertResponseStatusCodeSame(201);
        $this->login($client, $freshEmail, 'Parola1234');
    }

    private function login(KernelBrowser $client, string $email, string $password): void
    {
        $client->request('POST', '/api/auth/login', server: $this->json(), content: json_encode([
            'email' => $email, 'password' => $password,
        ]));
        self::assertResponseIsSuccessful();
    }
}
