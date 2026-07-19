<?php

declare(strict_types=1);

namespace App\QuoteRequest\Domain;

/**
 * Mașina de stare pentru cererile de ofertă.
 *
 * DRAFT -> SUBMITTED
 * SUBMITTED -> IN_REVIEW
 * IN_REVIEW -> NEEDS_INFORMATION | REPLIED | CLOSED
 * NEEDS_INFORMATION -> IN_REVIEW | CLOSED
 * REPLIED -> ACCEPTED | DECLINED | CLOSED
 * ACCEPTED -> CLOSED
 * DECLINED -> CLOSED
 */
enum QuoteRequestStatus: string
{
    case DRAFT = 'DRAFT';
    case SUBMITTED = 'SUBMITTED';
    case IN_REVIEW = 'IN_REVIEW';
    case NEEDS_INFORMATION = 'NEEDS_INFORMATION';
    case REPLIED = 'REPLIED';
    case ACCEPTED = 'ACCEPTED';
    case DECLINED = 'DECLINED';
    case CLOSED = 'CLOSED';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Ciornă',
            self::SUBMITTED => 'Trimisă',
            self::IN_REVIEW => 'În analiză',
            self::NEEDS_INFORMATION => 'Informații necesare',
            self::REPLIED => 'Ofertă trimisă',
            self::ACCEPTED => 'Acceptată',
            self::DECLINED => 'Refuzată',
            self::CLOSED => 'Închisă',
        };
    }

    public function isTerminal(): bool
    {
        return $this->allowedTransitions() === [];
    }

    /** @return self[] Tranzițiile permise din starea curentă. */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::DRAFT => [self::SUBMITTED],
            self::SUBMITTED => [self::IN_REVIEW],
            self::IN_REVIEW => [self::NEEDS_INFORMATION, self::REPLIED, self::CLOSED],
            self::NEEDS_INFORMATION => [self::IN_REVIEW, self::CLOSED],
            self::REPLIED => [self::ACCEPTED, self::DECLINED, self::CLOSED],
            self::ACCEPTED => [self::CLOSED],
            self::DECLINED => [self::CLOSED],
            self::CLOSED => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }
}
