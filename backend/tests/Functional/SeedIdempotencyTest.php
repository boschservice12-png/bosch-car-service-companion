<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * P0-08 — seed-ul demo trebuie să fie IDEMPOTENT: rulat de două ori (așa cum se
 * întâmplă la fiecare repornire a containerului demo) NU trebuie să dubleze
 * datele. Verificăm invariantul „exact un admin / un client / două vehicule /
 * doi proprietari activi" DUPĂ două rulări consecutive — proprietate care se
 * menține indiferent de starea inițială a bazei partajate de teste.
 *
 * @group functional
 */
final class SeedIdempotencyTest extends KernelTestCase
{
    private const ADMIN_EMAIL = 'admin@bcsc.ro';
    private const CLIENT_EMAIL = 'client@bcsc.ro';

    public function testRunningSeedTwiceDoesNotDuplicateData(): void
    {
        self::bootKernel();
        $application = new Application(self::$kernel);

        // Prima rulare: creează (sau, dacă alt test a semănat deja, e no-op).
        self::assertSame(0, $this->runSeed($application), 'Prima rulare a seed-ului reușește.');
        // A doua rulare: trebuie să fie un no-op idempotent, tot cu succes.
        self::assertSame(0, $this->runSeed($application), 'A doua rulare (idempotentă) reușește.');

        /** @var Connection $conn */
        $conn = self::getContainer()->get(EntityManagerInterface::class)->getConnection();

        self::assertSame(
            1,
            (int) $conn->fetchOne('SELECT COUNT(*) FROM users WHERE email = ?', [self::ADMIN_EMAIL]),
            'Exact un cont de admin după două rulări.',
        );
        self::assertSame(
            1,
            (int) $conn->fetchOne('SELECT COUNT(*) FROM users WHERE email = ?', [self::CLIENT_EMAIL]),
            'Exact un cont de client după două rulări.',
        );
        self::assertSame(
            2,
            (int) $conn->fetchOne(
                "SELECT COUNT(*) FROM vehicles WHERE plate_number IN ('MS01POP', 'MS02POP')",
            ),
            'Cele două vehicule demo nu se dublează.',
        );
        self::assertSame(
            2,
            (int) $conn->fetchOne(
                'SELECT COUNT(*) FROM vehicle_ownerships o
                 JOIN customer_profiles cp ON cp.id = o.customer_profile_id
                 JOIN users u ON u.id = cp.user_id
                 WHERE u.email = ? AND o.active = true',
                [self::CLIENT_EMAIL],
            ),
            'Clientul demo are exact doi proprietari activi (nu patru).',
        );
    }

    private function runSeed(Application $application): int
    {
        $tester = new CommandTester($application->find('app:demo:seed'));
        $tester->execute([]);

        return $tester->getStatusCode();
    }
}
