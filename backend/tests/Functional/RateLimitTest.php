<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

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
