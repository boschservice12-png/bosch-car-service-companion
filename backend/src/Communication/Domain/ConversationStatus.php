<?php

declare(strict_types=1);

namespace App\Communication\Domain;

/**
 * Starea unei conversații — conform specificației: OPEN, WAITING_CLIENT,
 * WAITING_SERVICE, CLOSED. Starea se schimbă prin mesaje (cine e așteptat să
 * răspundă) și prin închidere/redeschidere de către service. Fluxul de ofertă
 * NU mai trece pe aici — are modulul propriu (QuoteRequest).
 */
enum ConversationStatus: string
{
    case OPEN = 'OPEN';
    case WAITING_CLIENT = 'WAITING_CLIENT';
    case WAITING_SERVICE = 'WAITING_SERVICE';
    case CLOSED = 'CLOSED';

    public function label(): string
    {
        return match ($this) {
            self::OPEN => 'Deschisă',
            self::WAITING_CLIENT => 'Așteaptă clientul',
            self::WAITING_SERVICE => 'Așteaptă service-ul',
            self::CLOSED => 'Închisă',
        };
    }
}
