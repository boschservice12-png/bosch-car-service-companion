<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * P0-05 — protecția CSRF (double submit cookie) pe cererile modificatoare:
 *  - fără token → 403 (inclusiv pe login/register — un formular străin
 *    nu poate seta antetul X-CSRF-Token);
 *  - token nepotrivit cu cookie-ul → 403;
 *  - GET /api/csrf emite cookie-ul; cu perechea corectă cererea reușește;
 *  - la logout cookie-ul se șterge (rotire per sesiune de browser).
 *
 * Testul folosește intenționat WebTestCase „gol" (fără ApiTestCase, care
 * pre-încarcă tokenul pentru celelalte teste).
 *
 * @group functional
 */
final class CsrfProtectionTest extends WebTestCase
{
    public function testModifyingRequestsRequireMatchingCsrfPair(): void
    {
        $client = static::createClient();
        $email = 'csrf-'.uniqid().'@example.test';
        $payload = json_encode(['email' => $email, 'password' => 'Parola1234', 'consent' => true]);
        $json = ['CONTENT_TYPE' => 'application/json'];

        // 1) Fără niciun token → 403 (chiar și register/login sunt protejate).
        $client->request('POST', '/api/auth/register', server: $json, content: $payload);
        self::assertResponseStatusCodeSame(403, 'POST fără token CSRF este respins.');

        // 2) GET-urile nu sunt blocate de CSRF (nu modifică stare).
        $client->request('GET', '/api/health');
        self::assertResponseIsSuccessful();

        // 3) Emiterea tokenului: GET /api/csrf setează cookie-ul bcsc_csrf.
        $client->request('GET', '/api/csrf');
        self::assertResponseIsSuccessful();
        $cookie = $client->getCookieJar()->get('bcsc_csrf');
        self::assertNotNull($cookie, 'GET /api/csrf emite cookie-ul CSRF.');
        $token = $cookie->getValue();
        self::assertNotEmpty($token);

        // 4) Cookie prezent, dar antet GREȘIT → 403.
        $client->setServerParameter('HTTP_X_CSRF_TOKEN', 'alt-token');
        $client->request('POST', '/api/auth/register', server: $json, content: $payload);
        self::assertResponseStatusCodeSame(403, 'Antetul trebuie să corespundă cookie-ului.');

        // 5) Perechea corectă → cererea reușește.
        $client->setServerParameter('HTTP_X_CSRF_TOKEN', $token);
        $client->request('POST', '/api/auth/register', server: $json, content: $payload);
        self::assertResponseStatusCodeSame(201);

        $client->request('POST', '/api/auth/login', server: $json, content: json_encode([
            'email' => $email, 'password' => 'Parola1234',
        ]));
        self::assertResponseIsSuccessful();

        // 6) Logout: cookie-ul CSRF se șterge → următoarea cerere modificatoare
        //    fără reînnoire este respinsă (rotire de token).
        $client->request('POST', '/api/auth/logout');
        self::assertResponseStatusCodeSame(204);
        self::assertNull($client->getCookieJar()->get('bcsc_csrf'), 'Logout șterge cookie-ul CSRF.');

        $client->request('POST', '/api/auth/login', server: $json, content: json_encode([
            'email' => $email, 'password' => 'Parola1234',
        ]));
        self::assertResponseStatusCodeSame(403, 'După logout e nevoie de token nou.');

        // 7) Reînnoire: /api/csrf → token NOU, diferit de cel vechi.
        $client->request('GET', '/api/csrf');
        $fresh = $client->getCookieJar()->get('bcsc_csrf');
        self::assertNotNull($fresh);
        self::assertNotSame($token, $fresh->getValue(), 'Tokenul se rotește după logout.');
        $client->setServerParameter('HTTP_X_CSRF_TOKEN', $fresh->getValue());
        $client->request('POST', '/api/auth/login', server: $json, content: json_encode([
            'email' => $email, 'password' => 'Parola1234',
        ]));
        self::assertResponseIsSuccessful();
    }
}
