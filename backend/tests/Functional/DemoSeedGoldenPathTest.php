<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * „Golden path" de stabilizare: rulează seed-ul demo și verifică, printr-o singură
 * traversare, că toate modulele livrate (scadențe, istoric service, comunicare +
 * ofertă) sunt populate și vizibile corect din sesiunile CLIENT și ADMIN.
 *
 * @group functional
 */
final class DemoSeedGoldenPathTest extends WebTestCase
{
    private const CLIENT_EMAIL = 'client@bcsc.ro';
    private const ADMIN_EMAIL = 'admin@bcsc.ro';
    private const PASSWORD = 'Demo1234!';

    public function testSeedPopulatesAllModulesVisibleToClientAndAdmin(): void
    {
        $client = static::createClient();
        $this->runSeed();

        // CLIENT: vede cele două vehicule.
        $this->login($client, self::CLIENT_EMAIL);
        $client->request('GET', '/api/vehicles');
        self::assertResponseIsSuccessful();
        $vehicles = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertCount(2, $vehicles);
        $v1 = $this->byPlate($vehicles, 'MS01POP');

        // Scadențe: 3 pe primul vehicul, cu stări variate + una validată.
        $client->request('GET', "/api/vehicles/{$v1['id']}/deadlines");
        self::assertResponseIsSuccessful();
        $deadlines = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertCount(3, $deadlines);
        $states = array_column($deadlines, 'state');
        self::assertContains('EXPIRED', $states, 'Trebuie să existe o scadență expirată (rovinietă).');
        self::assertContains('DUE_SOON', $states, 'Trebuie să existe o scadență care expiră curând (RCA).');
        self::assertNotEmpty(array_filter($deadlines, static fn (array $d): bool => $d['verified'] === true));

        // Istoric service: original publicat + corecție (ambele vizibile).
        $client->request('GET', "/api/vehicles/{$v1['id']}/service-records");
        self::assertResponseIsSuccessful();
        $records = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertCount(2, $records);
        self::assertNotEmpty(array_filter($records, static fn (array $r): bool => $r['correctionOfId'] !== null), 'O intrare este corecție.');
        self::assertNotEmpty(array_filter($records, static fn (array $r): bool => $r['corrected'] === true), 'Originalul e marcat corectat.');

        // Comunicare: clientul demo are exact o cerere de ofertă, în stare QUOTED.
        $client->request('GET', '/api/conversations');
        self::assertResponseIsSuccessful();
        $conversations = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertCount(1, $conversations);
        self::assertSame('QUOTE', $conversations[0]['type']);
        self::assertSame('QUOTED', $conversations[0]['status']);
        self::assertEqualsWithDelta(1250, $conversations[0]['quoteAmount'], 0.001);
        $conversationId = $conversations[0]['id'];

        // Sprint 4: asistență rutieră (preluată), mobilitate (aprobată), dosar (în lucru), taxe.
        $client->request('GET', '/api/roadside-requests');
        self::assertResponseIsSuccessful();
        $roadside = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertCount(1, $roadside);
        self::assertSame('FORWARDED', $roadside[0]['status']);

        $client->request('GET', '/api/mobility-requests');
        self::assertResponseIsSuccessful();
        $mobility = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertCount(1, $mobility);
        self::assertSame('APPROVED', $mobility[0]['status']);

        $client->request('GET', '/api/damage-claims');
        self::assertResponseIsSuccessful();
        $damage = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertCount(1, $damage);
        self::assertSame('IN_PROGRESS', $damage[0]['status']);

        $client->request('GET', '/api/taxes');
        self::assertResponseIsSuccessful();
        $taxes = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertCount(2, $taxes);
        $taxStatuses = array_column($taxes, 'status');
        self::assertContains('PAID', $taxStatuses);
        self::assertContains('UNPAID', $taxStatuses);

        // ADMIN: vede (printre altele) vehiculele demo și conversația clientului demo.
        // Portalul admin agregă toți clienții, deci verificăm prezența, nu numărul exact.
        $this->login($client, self::ADMIN_EMAIL);
        $client->request('GET', '/api/admin/vehicles');
        self::assertResponseIsSuccessful();
        $adminPlates = array_column(json_decode((string) $client->getResponse()->getContent(), true), 'plateNumber');
        self::assertContains('MS01POP', $adminPlates);
        self::assertContains('MS02POP', $adminPlates);

        $client->request('GET', '/api/admin/conversations');
        self::assertResponseIsSuccessful();
        $adminConversations = json_decode((string) $client->getResponse()->getContent(), true);
        $found = array_filter($adminConversations, static fn (array $c): bool => $c['id'] === $conversationId);
        self::assertNotEmpty($found, 'Adminul trebuie să vadă conversația clientului demo.');
        self::assertNotEmpty(reset($found)['customerName']);
    }

    private function runSeed(): void
    {
        $application = new Application(self::$kernel);
        $tester = new CommandTester($application->find('app:demo:seed'));
        $tester->execute([]);
        self::assertSame(0, $tester->getStatusCode(), 'Seed-ul demo trebuie să ruleze cu succes.');
    }

    /**
     * @param array<int, array<string, mixed>> $vehicles
     *
     * @return array<string, mixed>
     */
    private function byPlate(array $vehicles, string $plate): array
    {
        foreach ($vehicles as $v) {
            if ($v['plateNumber'] === $plate) {
                return $v;
            }
        }
        self::fail("Vehiculul $plate nu a fost găsit.");
    }

    private function login(KernelBrowser $client, string $email): void
    {
        $client->request('POST', '/api/auth/login', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'email' => $email, 'password' => self::PASSWORD,
        ]));
        self::assertResponseIsSuccessful();
    }
}
