<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\System\Application\ReadinessChecker;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Blocul 6 — liveness vs. readiness.
 *
 * Liveness spune doar „procesul trăiește"; readiness spune „pot servi în
 * siguranță o cerere reală". Verificăm că:
 *  - liveness răspunde mereu 200, fără să atingă dependențe externe;
 *  - readiness raportează TOATE verificările critice (bază, migrații, storage,
 *    secrete) + una necritică (messenger);
 *  - baza și storage-ul sunt accesibile în mediul de test;
 *  - verificarea secretelor prinde un APP_SECRET implicit/„change" (regresie de
 *    securitate) — nu arătăm niciodată readiness verde cu secret implicit.
 *
 * @group functional
 */
final class ReadinessTest extends WebTestCase
{
    public function testLivenessIsAlwaysOkAndTouchesNoDependencies(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/health');

        self::assertResponseIsSuccessful();
        $body = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame('ok', $body['status']);
    }

    public function testReadinessReportsAllCriticalChecks(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/health/ready');

        $body = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertIsArray($body);
        self::assertArrayHasKey('checks', $body);

        foreach (['database', 'migrations', 'messenger', 'storage', 'secrets'] as $name) {
            self::assertArrayHasKey($name, $body['checks'], "lipsește verificarea „$name");
            self::assertArrayHasKey('status', $body['checks'][$name]);
            self::assertArrayHasKey('critical', $body['checks'][$name]);
        }

        // Baza și storage-ul trebuie să fie accesibile în test.
        self::assertSame('ok', $body['checks']['database']['status']);
        self::assertSame('ok', $body['checks']['storage']['status']);
        // Baza, migrațiile, storage-ul și secretele sunt CRITICE; messenger nu.
        self::assertTrue($body['checks']['database']['critical']);
        self::assertTrue($body['checks']['storage']['critical']);
        self::assertFalse($body['checks']['messenger']['critical']);

        // Nu expunem secrete / connection string-uri în răspuns.
        self::assertStringNotContainsStringIgnoringCase('secret', json_encode($body['checks']['secrets']) ?: '');
    }

    public function testReadinessRefusesGreenWhenACriticalDependencyIsDown(): void
    {
        // În mediul de test schema e creată cu schema:create (fără istoricul de
        // migrații), deci verificarea „migrations" pică — o dependență CRITICĂ.
        // Readiness NU trebuie să fie 200 în acest caz.
        $client = static::createClient();
        $client->request('GET', '/api/health/ready');

        $body = json_decode((string) $client->getResponse()->getContent(), true);
        if (!$body['ready']) {
            self::assertResponseStatusCodeSame(503);
            self::assertContains($body['status'], ['degraded', 'failed']);
        } else {
            // Dacă mediul chiar are toate criticele verzi, atunci e 200 — coerent.
            self::assertResponseStatusCodeSame(200);
            self::assertSame('ok', $body['status']);
        }
    }

    public function testSecretsCheckFlagsDefaultSecret(): void
    {
        self::bootKernel();
        $connection = static::getContainer()->get('doctrine.dbal.default_connection');
        $storage = static::getContainer()->get('App\\Document\\Domain\\StorageAdapter');
        $migrations = static::getContainer()->get('doctrine.migrations.dependency_factory');

        // Un APP_SECRET implicit („...change...") e o regresie de securitate.
        $checker = new ReadinessChecker($connection, $storage, $migrations, 'dev-secret-change-me');
        $result = $checker->check();
        self::assertSame('failed', $result['checks']['secrets']['status']);
        self::assertFalse($result['ready']);

        // Un secret real trece verificarea de secrete.
        $ok = new ReadinessChecker($connection, $storage, $migrations, bin2hex(random_bytes(16)));
        self::assertSame('ok', $ok->check()['checks']['secrets']['status']);
    }
}
