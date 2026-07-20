<?php

declare(strict_types=1);

namespace App\Identity\Application;

use App\Identity\Domain\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Protecție anti-replay pentru TOTP: un pas de timp (contorul RFC 6238) poate
 * fi acceptat cel mult O DATĂ per utilizator. Consumarea se face printr-un
 * UPDATE condițional ATOMIC — sursa de adevăr pentru concurență:
 *
 *   UPDATE users SET last_totp_step = :step
 *   WHERE id = :id AND (last_totp_step IS NULL OR last_totp_step < :step)
 *
 * Dacă update-ul atinge 1 rând → pasul e nou, acceptat. Dacă atinge 0 rânduri →
 * pasul e egal sau mai vechi (deja consumat, inclusiv de o cerere paralelă) →
 * replay respins. Baza de date serializează cele două cereri concurente, deci
 * din două cereri cu ACELAȘI cod cel mult una reușește.
 */
final class TotpReplayGuard
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    /**
     * Consumă pasul pentru utilizator. Întoarce true dacă a fost acceptat (nou),
     * false dacă e replay (egal sau mai vechi decât ultimul acceptat).
     */
    public function consume(User $user, int $step): bool
    {
        $connection = $this->em->getConnection();
        $affected = $connection->executeStatement(
            'UPDATE users SET last_totp_step = :step
             WHERE id = :id AND (last_totp_step IS NULL OR last_totp_step < :step)',
            ['step' => $step, 'id' => $user->id()],
            ['id' => 'uuid'],
        );

        if ($affected === 1) {
            // Ținem entitatea încărcată sincronă cu baza (fără un al doilea flush).
            $user->recordTotpStep($step);

            return true;
        }

        return false;
    }
}
