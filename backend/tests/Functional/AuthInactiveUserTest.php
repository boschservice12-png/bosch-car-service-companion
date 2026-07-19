<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Identity\Domain\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * P0-07 — conturile dezactivate NU au acces:
 *  - un cont cu isActive=false nu se poate autentifica, chiar cu parola corectă;
 *  - o sesiune DEJA deschisă își pierde accesul la următoarea cerere după
 *    dezactivare (EquatableInterface invalidează token-ul);
 *  - reactivarea redă accesul (tiltás visszafordítható);
 *  - regula se aplică și conturilor de ADMIN.
 *
 * @group functional
 */
final class AuthInactiveUserTest extends ApiTestCase
{
    public function testInactiveClientCannotLoginAndActiveSessionIsRevoked(): void
    {
        $client = static::createClient();
        $email = 'inact-'.uniqid().'@example.test';
        $this->register($client, $email);

        // Autentificare normală + acces.
        $this->login($client, $email);
        $client->request('GET', '/api/me');
        self::assertResponseIsSuccessful();

        // Service-ul dezactivează contul.
        $this->setActive($email, false);

        // Sesiunea EXISTENTĂ pierde accesul la următoarea cerere.
        $client->request('GET', '/api/me');
        self::assertResponseStatusCodeSame(401, 'Sesiunea contului dezactivat se invalidează imediat.');

        // Re-login cu parola corectă → refuzat.
        $client->request('POST', '/api/auth/login', server: $this->json(), content: json_encode([
            'email' => $email, 'password' => 'Parola1234',
        ]));
        self::assertResponseStatusCodeSame(401, 'Contul dezactivat nu se poate autentifica.');

        // Reactivare → accesul revine (măsura este reversibilă).
        $this->setActive($email, true);
        $this->login($client, $email);
        $client->request('GET', '/api/me');
        self::assertResponseIsSuccessful();
    }

    public function testInactiveAdminCannotLogin(): void
    {
        $client = static::createClient();
        $adminEmail = 'inad-'.uniqid().'@bcsc.ro';

        $c = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $c->get(EntityManagerInterface::class);
        /** @var UserPasswordHasherInterface $hasher */
        $hasher = $c->get(UserPasswordHasherInterface::class);
        $admin = new User($adminEmail, User::ROLE_SERVICE_ADMIN);
        $admin->setPasswordHash($hasher->hashPassword($admin, 'Parola1234'));
        $admin->deactivate();
        $em->persist($admin);
        $em->flush();

        $client->request('POST', '/api/auth/login', server: $this->json(), content: json_encode([
            'email' => $adminEmail, 'password' => 'Parola1234',
        ]));
        self::assertResponseStatusCodeSame(401, 'Adminul dezactivat nu se poate autentifica.');
    }

    private function setActive(string $email, bool $active): void
    {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $user = $em->getRepository(User::class)->findOneBy(['email' => $email]);
        self::assertInstanceOf(User::class, $user);
        $active ? $user->activate() : $user->deactivate();
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

    private function login(KernelBrowser $client, string $email): void
    {
        $client->request('POST', '/api/auth/login', server: $this->json(), content: json_encode([
            'email' => $email, 'password' => 'Parola1234',
        ]));
        self::assertResponseIsSuccessful();
    }
}
