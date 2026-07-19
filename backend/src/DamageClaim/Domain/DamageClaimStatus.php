<?php

declare(strict_types=1);

namespace App\DamageClaim\Domain;

/**
 * Starea unui dosar de daună — conform specificației funcționale:
 * SUBMITTED → (DOCUMENTS_MISSING ↔ IN_REVIEW) → CONTACTED → FILE_OPENED → CLOSED.
 * CLOSED este singura stare terminală (anularea de către client închide dosarul).
 */
enum DamageClaimStatus: string
{
    case SUBMITTED = 'SUBMITTED';
    case DOCUMENTS_MISSING = 'DOCUMENTS_MISSING';
    case IN_REVIEW = 'IN_REVIEW';
    case CONTACTED = 'CONTACTED';
    case FILE_OPENED = 'FILE_OPENED';
    case CLOSED = 'CLOSED';

    public function label(): string
    {
        return match ($this) {
            self::SUBMITTED => 'Trimis',
            self::DOCUMENTS_MISSING => 'Documente lipsă',
            self::IN_REVIEW => 'În analiză',
            self::CONTACTED => 'Contactat',
            self::FILE_OPENED => 'Dosar deschis',
            self::CLOSED => 'Închis',
        };
    }

    /** @return self[] Stările în care se poate trece direct din starea curentă. */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::SUBMITTED => [self::DOCUMENTS_MISSING, self::IN_REVIEW, self::CLOSED],
            self::DOCUMENTS_MISSING => [self::IN_REVIEW, self::CLOSED],
            self::IN_REVIEW => [self::DOCUMENTS_MISSING, self::CONTACTED, self::FILE_OPENED, self::CLOSED],
            self::CONTACTED => [self::FILE_OPENED, self::CLOSED],
            self::FILE_OPENED => [self::CLOSED],
            self::CLOSED => [],
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
