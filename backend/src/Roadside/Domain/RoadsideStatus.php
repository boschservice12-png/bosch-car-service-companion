<?php

declare(strict_types=1);

namespace App\Roadside\Domain;

/**
 * Starea unei cereri de asistență rutieră — conform specificației funcționale:
 * SUBMITTED → VALIDATED → FORWARDED → IN_PROGRESS → COMPLETED, cu CANCELLED
 * posibil din orice stare ne-terminală. Tranzițiile sunt impuse de mașina de
 * stări (o tranziție nepermisă → 409).
 */
enum RoadsideStatus: string
{
    case SUBMITTED = 'SUBMITTED';
    case VALIDATED = 'VALIDATED';
    case FORWARDED = 'FORWARDED';
    case IN_PROGRESS = 'IN_PROGRESS';
    case COMPLETED = 'COMPLETED';
    case CANCELLED = 'CANCELLED';

    public function label(): string
    {
        return match ($this) {
            self::SUBMITTED => 'Trimisă',
            self::VALIDATED => 'Validată',
            self::FORWARDED => 'Direcționată',
            self::IN_PROGRESS => 'În curs',
            self::COMPLETED => 'Finalizată',
            self::CANCELLED => 'Anulată',
        };
    }

    /** @return self[] Stările în care se poate trece direct din starea curentă. */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::SUBMITTED => [self::VALIDATED, self::CANCELLED],
            self::VALIDATED => [self::FORWARDED, self::IN_PROGRESS, self::CANCELLED],
            self::FORWARDED => [self::IN_PROGRESS, self::COMPLETED, self::CANCELLED],
            self::IN_PROGRESS => [self::COMPLETED, self::CANCELLED],
            self::COMPLETED, self::CANCELLED => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return \in_array($target, $this->allowedTransitions(), true);
    }

    public function isTerminal(): bool
    {
        return $this->allowedTransitions() === [];
    }
}
