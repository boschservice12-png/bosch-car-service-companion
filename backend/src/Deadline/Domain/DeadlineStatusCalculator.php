<?php

declare(strict_types=1);

namespace App\Deadline\Domain;

use DateTimeImmutable;

/**
 * Calculează starea unei scadențe pe baza datei de expirare și a pragului
 * „expiră curând”. Calculul se face pe zile calendaristice în fusul de referință
 * (Europe/Bucharest) — stocarea rămâne UTC, doar comparația de zile e locală.
 *
 * Praguri implicite configurabile: 60 / 30 / 7 / 0 / după expirare.
 */
final class DeadlineStatusCalculator
{
    /** Prag implicit (în zile) pentru starea DUE_SOON. */
    public const DEFAULT_DUE_SOON_DAYS = 60;

    public function calculate(
        ?DateTimeImmutable $expiresAt,
        DateTimeImmutable $now,
        int $dueSoonDays = self::DEFAULT_DUE_SOON_DAYS
    ): DeadlineState {
        if ($expiresAt === null) {
            return DeadlineState::UNKNOWN;
        }

        $today = $this->atMidnight($now);
        $expiry = $this->atMidnight($expiresAt);

        if ($expiry < $today) {
            return DeadlineState::EXPIRED;
        }

        $daysLeft = (int) $today->diff($expiry)->format('%a');

        if ($daysLeft <= $dueSoonDays) {
            return DeadlineState::DUE_SOON;
        }

        return DeadlineState::VALID;
    }

    /**
     * Câte zile au mai rămas până la expirare (negativ dacă a expirat,
     * null dacă data e necunoscută).
     */
    public function daysLeft(?DateTimeImmutable $expiresAt, DateTimeImmutable $now): ?int
    {
        if ($expiresAt === null) {
            return null;
        }

        $today = $this->atMidnight($now);
        $expiry = $this->atMidnight($expiresAt);

        $diff = (int) $today->diff($expiry)->format('%a');

        return $expiry < $today ? -$diff : $diff;
    }

    private function atMidnight(DateTimeImmutable $dt): DateTimeImmutable
    {
        return $dt->setTime(0, 0, 0);
    }
}
