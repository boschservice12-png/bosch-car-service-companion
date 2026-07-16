<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Identity\Domain\User;
use Doctrine\ORM\EntityManagerInterface;
use OTPHP\TOTP;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * 2FA admin (TOTP): înrolare, enforcement pe /api/admin și cerința codului la
 * login pentru adminii cu 2FA activat.
 *
 * @group functional
 */
final class AdminTwoFactorTest extends WebTestCase
{
    public function testAdminMustEnrollThenLoginWithCode(): void
    {
        $client = static::createClient();
        $email = 'adm2fa-'.uniqid().'@bcsc.ro';
        $this->createAdmin($email, 'Parola1234');

        // 1) Fără 2FA activat: login reușește (ca adminul să se poată înrola)...
        $this->login($client, $email, 'Parola1234');
        self::assertResponseIsSuccessful();

        // ...dar operațiunile de admin sunt blocate până la activarea 2FA.
        $this->patchSetting($client, 'blocat');
        self::assertResponseStatusCodeSame(403, 'Admin fără 2FA nu are acces la /api/admin.');

        // 2) Înrolare: setup -> secret, apoi confirm cu un cod valid.
        $client->request('POST', '/api/2fa/setup');
        self::assertResponseIsSuccessful();
        /** @var array{secret: string} $setup */
        $setup = json_decode((string) $client->getResponse()->getContent(), true);
        $secret = $setup['secret'];

        $client->request('POST', '/api/2fa/confirm', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'code' => TOTP::createFromSecret($secret)->now(),
        ]));
        self::assertResponseIsSuccessful();
        self::assertTrue(json_decode((string) $client->getResponse()->getContent(), true)['enabled']);

        // 3) După activare, adminul poate face operațiuni de admin.
        $this->patchSetting($client, '+40712345678');
        self::assertResponseIsSuccessful();

        // 4) Logout, apoi login FĂRĂ cod -> 401 totp_required.
        $client->request('POST', '/api/auth/logout');
        $client->request('POST', '/api/auth/login', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'email' => $email, 'password' => 'Parola1234',
        ]));
        self::assertResponseStatusCodeSame(401);
        self::assertSame('totp_required', json_decode((string) $client->getResponse()->getContent(), true)['type']);

        // 5) Login CU cod valid -> succes.
        $client->request('POST', '/api/auth/login', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'email' => $email, 'password' => 'Parola1234', 'totpCode' => TOTP::createFromSecret($secret)->now(),
        ]));
        self::assertResponseIsSuccessful();
    }

    public function testWrongTotpCodeIsRejected(): void
    {
        $client = static::createClient();
        $email = 'adm2fa-'.uniqid().'@bcsc.ro';
        $this->createAdmin($email, 'Parola1234', enroll2fa: true);

        $client->request('POST', '/api/auth/login', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'email' => $email, 'password' => 'Parola1234', 'totpCode' => '000000',
        ]));
        self::assertResponseStatusCodeSame(401);
    }

    public function testClientLoginUnaffectedBy2fa(): void
    {
        $client = static::createClient();
        $email = 'cli-'.uniqid().'@example.test';
        $client->request('POST', '/api/auth/register', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'email' => $email, 'password' => 'Parola1234', 'consent' => true,
        ]));
        self::assertResponseStatusCodeSame(201);
        $this->login($client, $email, 'Parola1234');
        self::assertResponseIsSuccessful();
    }

    private function createAdmin(string $email, string $password, bool $enroll2fa = false): void
    {
        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);
        /** @var UserPasswordHasherInterface $hasher */
        $hasher = $container->get(UserPasswordHasherInterface::class);

        $admin = new User($email, User::ROLE_SERVICE_ADMIN);
        $admin->setPasswordHash($hasher->hashPassword($admin, $password));
        if ($enroll2fa) {
            $admin->startTotpEnrollment(TOTP::generate()->getSecret());
            $admin->confirmTotpEnrollment();
        }
        $em->persist($admin);
        $em->flush();
    }

    private function patchSetting(KernelBrowser $client, string $value): void
    {
        $client->request('PATCH', '/api/admin/settings/whatsapp.number', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'value' => $value,
        ]));
    }

    private function login(KernelBrowser $client, string $email, string $password): void
    {
        $client->request('POST', '/api/auth/login', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'email' => $email, 'password' => $password,
        ]));
    }
}
