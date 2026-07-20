<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Identity\Application\TotpService;
use App\Identity\Domain\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * P1-04 — limitele de rată chiar se aplică (în test: 10/min mesaje, 10/min upload):
 *  - peste limită → 429 cu antet Retry-After;
 *  - limita este per utilizator — alt client nu este afectat;
 *  - cererile de sub limită funcționează normal.
 *
 * @group functional
 */
final class RateLimitTest extends ApiTestCase
{
    private const PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAAC0lEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    public function testMessageBurstIsLimitedPerUser(): void
    {
        $client = static::createClient();
        $email = 'rl-'.uniqid().'@example.test';
        $otherEmail = 'rlo-'.uniqid().'@example.test';
        $this->register($client, $email);
        $this->register($client, $otherEmail);
        $this->login($client, $email);

        // Prima cerere: pornește conversația (consumă 1 din limită).
        $client->request('POST', '/api/conversations', server: $this->json(), content: json_encode([
            'subject' => 'Test limită', 'body' => 'Mesajul 1',
        ]));
        self::assertResponseStatusCodeSame(201);
        $conversationId = json_decode((string) $client->getResponse()->getContent(), true)['id'];

        // Următoarele 9 mesaje trec (total 10 = limita de test).
        for ($i = 2; $i <= 10; ++$i) {
            $client->request('POST', "/api/conversations/$conversationId/messages", server: $this->json(), content: json_encode([
                'body' => "Mesajul $i",
            ]));
            self::assertResponseIsSuccessful("Mesajul $i este sub limită.");
        }

        // Al 11-lea → 429 + Retry-After.
        $client->request('POST', "/api/conversations/$conversationId/messages", server: $this->json(), content: json_encode([
            'body' => 'Peste limită',
        ]));
        self::assertResponseStatusCodeSame(429);
        self::assertNotNull($client->getResponse()->headers->get('Retry-After'), 'Răspunsul 429 are Retry-After.');

        // Alt utilizator NU este afectat de limita primului.
        $this->login($client, $otherEmail);
        $client->request('POST', '/api/conversations', server: $this->json(), content: json_encode([
            'subject' => 'Alt client', 'body' => 'Merge normal',
        ]));
        self::assertResponseStatusCodeSame(201, 'Limita este per utilizator, nu globală.');
    }

    public function testUploadBurstIsLimited(): void
    {
        $client = static::createClient();
        $email = 'rlu-'.uniqid().'@example.test';
        $this->register($client, $email);
        $this->login($client, $email);

        for ($i = 1; $i <= 10; ++$i) {
            $client->request('POST', '/api/documents', files: ['file' => $this->tempUpload("f$i.png")]);
            self::assertResponseStatusCodeSame(201, "Upload-ul $i este sub limită.");
        }

        $client->request('POST', '/api/documents', files: ['file' => $this->tempUpload('peste.png')]);
        self::assertResponseStatusCodeSame(429);
        self::assertNotNull($client->getResponse()->headers->get('Retry-After'));
    }

    /**
     * Revendicarea contului importat folosește numărul de înmatriculare drept
     * dovadă — încercările pe ACELAȘI email sunt limitate (anti forță-brută),
     * indiferent de IP; alte emailuri nu sunt afectate.
     */
    public function testRegisterAttemptsAreLimitedPerEmail(): void
    {
        $client = static::createClient();
        $email = 'rlr-'.uniqid().'@example.test';
        $this->register($client, $email); // consumă 1 din limita de 5

        for ($i = 2; $i <= 5; ++$i) {
            $client->request('POST', '/api/auth/register', server: $this->json(), content: json_encode([
                'email' => $email, 'password' => 'AltaParola9', 'consent' => true,
            ]));
            self::assertResponseStatusCodeSame(422, "Încercarea $i e sub limită (respinsă ca duplicat).");
        }

        $client->request('POST', '/api/auth/register', server: $this->json(), content: json_encode([
            'email' => $email, 'password' => 'AltaParola9', 'consent' => true,
        ]));
        self::assertResponseStatusCodeSame(429, 'A 6-a încercare pe același email este blocată.');
        self::assertNotNull($client->getResponse()->headers->get('Retry-After'));

        // Limita este per email țintă — un alt email se înregistrează normal.
        $this->register($client, 'rlr2-'.uniqid().'@example.test');
    }

    /**
     * Codul TOTP are 6 cifre — verificarea este limitată la 5 încercări;
     * un cod corect golește contorul (adminul legitim nu acumulează eșecuri).
     */
    public function testTwoFactorVerifyBruteForceIsLimited(): void
    {
        $client = static::createClient();
        $adminEmail = 'rl2fa-'.uniqid().'@bcsc.ro';
        $this->createAdmin($adminEmail, 'Parola1234');
        /** @var TotpService $totp */
        $totp = static::getContainer()->get(TotpService::class);
        $this->login($client, $adminEmail);

        $client->request('POST', '/api/auth/2fa/setup', server: $this->json(), content: json_encode(['password' => 'Parola1234']));
        self::assertResponseIsSuccessful();
        $secret = json_decode((string) $client->getResponse()->getContent(), true)['secret'];
        $client->request('POST', '/api/auth/2fa/enable', server: $this->json(), content: json_encode([
            'code' => $totp->codeAt($secret, time()),
        ]));
        self::assertResponseIsSuccessful();

        $client->request('POST', '/api/auth/logout');
        $this->refreshCsrf($client);
        $this->login($client, $adminEmail);

        // 4 eșecuri, apoi codul corect → verificat; contorul se golește.
        for ($i = 1; $i <= 4; ++$i) {
            $client->request('POST', '/api/auth/2fa/verify', server: $this->json(), content: json_encode(['code' => '000000']));
            self::assertResponseStatusCodeSame(422, "Eșecul $i e sub limită.");
        }
        $client->request('POST', '/api/auth/2fa/verify', server: $this->json(), content: json_encode([
            'code' => $totp->codeAt($secret, time()),
        ]));
        self::assertResponseIsSuccessful();

        // Sesiune nouă: 5 eșecuri consecutive → a 6-a încercare e blocată cu 429.
        $client->request('POST', '/api/auth/logout');
        $this->refreshCsrf($client);
        $this->login($client, $adminEmail);
        for ($i = 1; $i <= 5; ++$i) {
            $client->request('POST', '/api/auth/2fa/verify', server: $this->json(), content: json_encode(['code' => '000000']));
            self::assertResponseStatusCodeSame(422, "Eșecul $i e sub limită (contorul fusese golit).");
        }
        $client->request('POST', '/api/auth/2fa/verify', server: $this->json(), content: json_encode(['code' => '000000']));
        self::assertResponseStatusCodeSame(429, 'Forța brută pe codul TOTP este oprită.');
        self::assertNotNull($client->getResponse()->headers->get('Retry-After'));
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

    private function refreshCsrf(KernelBrowser $client): void
    {
        $client->getCookieJar()->set(new \Symfony\Component\BrowserKit\Cookie(
            \App\Shared\Security\CsrfProtectionSubscriber::COOKIE,
            self::CSRF_TOKEN,
        ));
        $client->setServerParameter('HTTP_X_CSRF_TOKEN', self::CSRF_TOKEN);
    }

    private function tempUpload(string $name): UploadedFile
    {
        $path = sys_get_temp_dir().'/bcsc_rl_'.uniqid().'_'.$name;
        file_put_contents($path, base64_decode(self::PNG_BASE64));

        return new UploadedFile($path, $name, 'image/png', null, true);
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

    private function login(KernelBrowser $client, string $email): void
    {
        $client->request('POST', '/api/auth/login', server: $this->json(), content: json_encode([
            'email' => $email, 'password' => 'Parola1234',
        ]));
        self::assertResponseIsSuccessful();
    }
}
