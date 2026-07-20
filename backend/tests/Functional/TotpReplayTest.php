<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Identity\Application\TotpReplayGuard;
use App\Identity\Application\TotpService;
use App\Identity\Domain\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Blocul 5 — protecție anti-replay TOTP.
 *
 * Un cod TOTP corect e acceptat o SINGURĂ dată: reutilizarea aceluiași cod
 * (același pas de timp) în aceeași fereastră e respinsă. Consumarea pasului e
 * atomică, deci din două cereri paralele cu același cod cel mult una reușește.
 * Codurile de rezervă rămân de unică folosință (acoperite în Admin2faFlowTest).
 *
 * @group functional
 */
final class TotpReplayTest extends ApiTestCase
{
    public function testSameTotpCodeAcceptedOnceThenRejected(): void
    {
        $client = static::createClient();
        $email = 'rpl-'.uniqid().'@bcsc.ro';
        $secret = $this->createAdminWithTotp($email, 'Parola1234');

        /** @var TotpService $totp */
        $totp = static::getContainer()->get(TotpService::class);

        $login = $this->login($client, $email, 'Parola1234');
        self::assertTrue($login['requiresOtp']);

        $code = $totp->codeAt($secret, time());

        // Prima folosire a codului → acceptat.
        $client->request('POST', '/api/auth/2fa/verify', server: $this->json(), content: json_encode(['code' => $code]));
        self::assertResponseIsSuccessful('Primul cod TOTP corect este acceptat.');

        // A doua folosire A ACELUIAȘI cod → respinsă (replay), mesaj generic.
        $client->request('POST', '/api/auth/logout');
        $this->refreshCsrf($client);
        $this->login($client, $email, 'Parola1234');
        $client->request('POST', '/api/auth/2fa/verify', server: $this->json(), content: json_encode(['code' => $code]));
        self::assertResponseStatusCodeSame(422, 'Același cod TOTP nu se acceptă a doua oară.');

        // Replay-ul lasă urmă în audit.
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $count = (int) $em->getConnection()->fetchOne(
            "SELECT COUNT(*) FROM audit_logs WHERE action = 'identity.2fa_replay_rejected'",
        );
        self::assertGreaterThanOrEqual(1, $count);
    }

    /**
     * Consumarea atomică a pasului (sursa de adevăr pentru concurență): un pas
     * e acceptat o dată; același sau unul mai vechi → respins; unul mai nou →
     * acceptat. Două „cereri paralele" (aici: două consumări succesive cu
     * același pas) → cel mult una reușește.
     */
    public function testStepGuardIsMonotonicAndSingleUse(): void
    {
        $client = static::createClient();
        $email = 'grd-'.uniqid().'@bcsc.ro';
        $this->createAdminWithTotp($email, 'Parola1234');

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        /** @var TotpReplayGuard $guard */
        $guard = static::getContainer()->get(TotpReplayGuard::class);
        $user = $em->getRepository(User::class)->findOneBy(['email' => $email]);
        self::assertInstanceOf(User::class, $user);

        self::assertTrue($guard->consume($user, 100), 'Un pas nou este acceptat.');
        self::assertFalse($guard->consume($user, 100), 'Același pas (paralel/replay) este respins.');
        self::assertFalse($guard->consume($user, 99), 'Un pas mai vechi este respins.');
        self::assertTrue($guard->consume($user, 101), 'Un pas mai nou este acceptat.');
    }

    private function createAdminWithTotp(string $email, string $password): string
    {
        $c = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $c->get(EntityManagerInterface::class);
        /** @var UserPasswordHasherInterface $hasher */
        $hasher = $c->get(UserPasswordHasherInterface::class);
        /** @var TotpService $totp */
        $totp = $c->get(TotpService::class);

        $admin = new User($email, User::ROLE_SERVICE_ADMIN);
        $admin->setPasswordHash($hasher->hashPassword($admin, $password));
        $secret = $totp->generateSecret();
        $admin->setPendingTotpSecret($secret);
        $admin->enableTotp([password_hash('AAAA-BBBB', PASSWORD_DEFAULT)]);
        $em->persist($admin);
        $em->flush();

        return $secret;
    }

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
