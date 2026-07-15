<?php

declare(strict_types=1);

namespace App\QuoteRequest\Domain;

use DomainException;

final class InvalidTransitionException extends DomainException
{
    public static function between(QuoteRequestStatus $from, QuoteRequestStatus $to): self
    {
        return new self(sprintf(
            'Tranziție nepermisă: %s -> %s.',
            $from->value,
            $to->value
        ));
    }
}
