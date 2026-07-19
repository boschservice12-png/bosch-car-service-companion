<?php

declare(strict_types=1);

namespace App\Mobility\Domain;

/**
 * Starea unei solicitări de mobilitate — conform specificației funcționale:
 * SUBMITTED → IN_REVIEW → CONTACTED → CONFIRMED → COMPLETED, cu UNAVAILABLE
 * (service-ul nu poate asigura) și CANCELLED (clientul renunță) ca ieșiri.
 */
enum MobilityStatus: string
{
    case SUBMITTED = 'SUBMITTED';
    case IN_REVIEW = 'IN_REVIEW';
    case CONTACTED = 'CONTACTED';
    case CONFIRMED = 'CONFIRMED';
    case UNAVAILABLE = 'UNAVAILABLE';
    case COMPLETED = 'COMPLETED';
    case CANCELLED = 'CANCELLED';

    public function label(): string
    {
        return match ($this) {
            self::SUBMITTED => 'Trimisă',
            self::IN_REVIEW => 'În analiză',
            self::CONTACTED => 'Contactat',
            self::CONFIRMED => 'Confirmată',
            self::UNAVAILABLE => 'Indisponibilă',
            self::COMPLETED => 'Finalizată',
            self::CANCELLED => 'Anulată',
        };
    }

    /** @return self[] Stările în care se poate trece direct din starea curentă. */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::SUBMITTED => [self::IN_REVIEW, self::CANCELLED],
            self::IN_REVIEW => [self::CONTACTED, self::CONFIRMED, self::UNAVAILABLE, self::CANCELLED],
            self::CONTACTED => [self::CONFIRMED, self::UNAVAILABLE, self::CANCELLED],
            self::CONFIRMED => [self::COMPLETED, self::CANCELLED],
            self::UNAVAILABLE, self::COMPLETED, self::CANCELLED => [],
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
