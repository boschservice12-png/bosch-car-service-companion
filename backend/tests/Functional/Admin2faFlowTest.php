<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Identity\Application\TotpService;
use App\Identity\Domain\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * P0-06 — fluxul complet 2FA pentru conturile de service:
 *  - înrolare: setup cere re-introducerea parolei; enable cere un cod valid și
 *    întoarce 8 coduri de rezervă (o singură dată);
 *  - login ulterior: requiresOtp=true, tot API-ul e blocat (403
 *    two_factor_required) până la verify cu cod TOTP sau cod de rezervă;
 *  - codul de rezervă e de unică folosință;
 *  - clientul (non-admin) nu poate folosi rutele 2FA;
 *  - resetarea din consolă (app:2fa:reset) readuce contul la parolă simplă
 *    și lasă urmă în audit.
 *
 * @group functional
 */
final class Admin2faFlowTest extends ApiTestCase
{
    public function testEnrollmentLoginChallengeRecoveryAndReset(): void
    {
        $client = static::createClient();
        $adminEmail = 'ad2fa-'.uniqid().'@bcsc.ro';
        $this->createAdmin($adminEmail, 'Parola1234');
        $totp = static::getContainer()->get(TotpService::class);

        // Login inițial: 2FA încă neactivat → nu se cere OTP.
        $login = $this->login($client, $adminEmail, 'Parola1234');
        self::assertFalse($login['requiresOtp'], 'Fără 2FA activ nu se cere OTP.');

        // Setup cu parolă greșită → 422; sesiunea singură nu ajunge.
        $client->request('POST', '/api/auth/2fa/setup', server: $this->json(), content: json_encode(['password' => 'gresita9']));
        self::assertResponseStatusCodeSame(422, 'Înrolarea cere re-introducerea parolei corecte.');

        // Setup corect → secret Base32 + URI de provisioning (QR).
        $client->request('POST', '/api/auth/2fa/setup', server: $this->json(), content: json_encode(['password' => 'Parola1234']));
        self::assertResponseIsSuccessful();
        $setup = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertMatchesRegularExpression('/^[A-Z2-7]{32}$/', $setup['secret']);
        self::assertStringStartsWith('otpauth://totp/', $setup['otpauthUri']);
        $secret = $setup['secret'];

        // Enable cu cod greșit → 422; 2FA rămâne inactiv.
        $client->request('POST', '/api/auth/2fa/enable', server: $this->json(), content: json_encode(['code' => '000000']));
        self::assertResponseStatusCodeSame(422);

        // Enable cu codul curent → activ + 8 coduri de rezervă (afișate o dată).
        $client->request('POST', '/api/auth/2fa/enable', server: $this->json(), content: json_encode([
            'code' => $totp->codeAt($secret, time()),
        ]));
        self::assertResponseIsSuccessful();
        $enabled = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertTrue($enabled['twoFactorEnabled']);
        self::assertCount(8, $enabled['recoveryCodes']);
        foreach ($enabled['recoveryCodes'] as $code) {
            self::assertMatchesRegularExpression('/^[A-Z2-9]{4}-[A-Z2-9]{4}$/', $code);
        }
        $recoveryCodes = $enabled['recoveryCodes'];

        // Sesiunea de înrolare e deja verificată → portalul funcționează.
        $client->request('GET', '/api/admin/vehicles');
        self::assertResponseIsSuccessful();

        // Re-login: acum se cere al doilea factor, iar API-ul e blocat.
        $client->request('POST', '/api/auth/logout');
        self::assertResponseStatusCodeSame(204);
        $this->refreshCsrf($client);
        $login = $this->login($client, $adminEmail, 'Parola1234');
        self::assertTrue($login['requiresOtp'], 'Cu 2FA activ, loginul cere OTP.');

        $client->request('GET', '/api/admin/vehicles');
        self::assertResponseStatusCodeSame(403);
        $blocked = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame('two_factor_required', $blocked['type']);

        // /api/me rămâne accesibil și anunță starea de așteptare.
        $client->request('GET', '/api/me');
        self::assertResponseIsSuccessful();
        self::assertTrue(json_decode((string) $client->getResponse()->getContent(), true)['requiresOtp']);

        // Verify cu cod greșit → 422; apoi cu codul TOTP curent → deblocat.
        $client->request('POST', '/api/auth/2fa/verify', server: $this->json(), content: json_encode(['code' => '123456']));
        self::assertResponseStatusCodeSame(422);
        $client->request('POST', '/api/auth/2fa/verify', server: $this->json(), content: json_encode([
            'code' => $totp->codeAt($secret, time()),
        ]));
        self::assertResponseIsSuccessful();
        $client->request('GET', '/api/admin/vehicles');
        self::assertResponseIsSuccessful();

        // Cod de rezervă: funcționează O SINGURĂ dată.
        $client->request('POST', '/api/auth/logout');
        $this->refreshCsrf($client);
        $this->login($client, $adminEmail, 'Parola1234');
        $client->request('POST', '/api/auth/2fa/verify', server: $this->json(), content: json_encode([
            'recoveryCode' => $recoveryCodes[0],
        ]));
        self::assertResponseIsSuccessful();
        $used = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame(7, $used['recoveryCodesLeft'], 'Codul folosit se consumă.');

        $client->request('POST', '/api/auth/logout');
        $this->refreshCsrf($client);
        $this->login($client, $adminEmail, 'Parola1234');
        $client->request('POST', '/api/auth/2fa/verify', server: $this->json(), content: json_encode([
            'recoveryCode' => $recoveryCodes[0],
        ]));
        self::assertResponseStatusCodeSame(422, 'Un cod de rezervă nu se refolosește.');

        // Resetare din consolă (operator) → contul revine la parolă simplă.
        $application = new Application(static::$kernel);
        $tester = new CommandTester($application->find('app:2fa:reset'));
        self::assertSame(0, $tester->execute(['email' => $adminEmail]));

        $client->request('POST', '/api/auth/logout');
        $this->refreshCsrf($client);
        $login = $this->login($client, $adminEmail, 'Parola1234');
        self::assertFalse($login['requiresOtp'], 'După resetare nu se mai cere OTP.');
        $client->request('GET', '/api/admin/vehicles');
        self::assertResponseIsSuccessful();

        // Audit: fiecare pas sensibil lasă urmă.
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        foreach (['identity.2fa_setup_started', 'identity.2fa_enabled', 'identity.2fa_recovery_code_used', 'identity.2fa_reset'] as $action) {
            $count = (int) $em->getConnection()->fetchOne('SELECT COUNT(*) FROM audit_logs WHERE action = ?', [$action]);
            self::assertGreaterThanOrEqual(1, $count, "Acțiunea $action trebuie să apară în audit.");
        }
    }

    public function testClientAccountsCannotUseTwoFactorRoutes(): void
    {
        $client = static::createClient();
        $email = 'cl2fa-'.uniqid().'@example.test';
        $client->request('POST', '/api/auth/register', server: $this->json(), content: json_encode([
            'email' => $email, 'password' => 'Parola1234', 'consent' => true,
        ]));
        self::assertResponseStatusCodeSame(201);
        $this->login($client, $email, 'Parola1234');

        $client->request('POST', '/api/auth/2fa/setup', server: $this->json(), content: json_encode(['password' => 'Parola1234']));
        self::assertResponseStatusCodeSame(403, '2FA este rezervat conturilor de service.');
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

    /**
     * Logout șterge cookie-ul CSRF (rotire) — re-armăm perechea de test,
     * exact cum frontendul cere GET /api/csrf după logout.
     */
    private function refreshCsrf(KernelBrowser $client): void
    {
        $client->getCookieJar()->set(new \Symfony\Component\BrowserKit\Cookie(
            \App\Shared\Security\CsrfProtectionSubscriber::COOKIE,
            self::CSRF_TOKEN,
        ));
        $client->setServerParameter('HTTP_X_CSRF_TOKEN', self::CSRF_TOKEN);
    }

    /** @return array<string, string> */
    private function json(): array
    {
        return ['CONTENT_TYPE' => 'application/json'];
    }

    /** @return array<string, mixed> */
    private function login(KernelBrowser $client, string $email, string $password): array
    {
        $client->request('POST', '/api/auth/login', server: $this->json(), content: json_encode([
            'email' => $email, 'password' => $password,
        ]));
        self::assertResponseIsSuccessful();

        return json_decode((string) $client->getResponse()->getContent(), true);
    }
}
