<?php

declare(strict_types=1);

namespace App\Mobility\Domain;

/** Tipul de mobilitate solicitat pe durata reparației — conform specificației. */
enum MobilityType: string
{
    case REPLACEMENT_CAR = 'REPLACEMENT_CAR';
    case TAXI = 'TAXI';
    case PERSON_TRANSPORT = 'PERSON_TRANSPORT';
    case ACCOMMODATION = 'ACCOMMODATION';
    case OTHER = 'OTHER';

    public function label(): string
    {
        return match ($this) {
            self::REPLACEMENT_CAR => 'Mașină de înlocuire',
            self::TAXI => 'Taxi',
            self::PERSON_TRANSPORT => 'Transport persoane',
            self::ACCOMMODATION => 'Cazare',
            self::OTHER => 'Altă solicitare',
        };
    }
}
