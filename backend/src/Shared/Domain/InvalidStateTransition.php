<?php

declare(strict_types=1);

namespace App\Shared\Domain;

/**
 * Tranziție de stare nepermisă de mașina de stări a modulului. Este mapată de
 * ApiExceptionListener la un răspuns 409 Conflict — cererea e validă sintactic,
 * dar starea curentă nu permite operația.
 */
final class InvalidStateTransition extends \DomainException
{
    public static function between(string $from, string $to): self
    {
        return new self(sprintf('Tranziție nepermisă: %s → %s.', $from, $to));
    }
}
