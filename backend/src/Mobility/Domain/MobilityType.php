<?php

declare(strict_types=1);

namespace App\Mobility\Domain;

/** Tipul de mobilitate solicitat pe durata reparației. */
enum MobilityType: string
{
    case REPLACEMENT_CAR = 'REPLACEMENT_CAR';
    case TAXI = 'TAXI';
    case RIDE_HOME = 'RIDE_HOME';
    case OTHER = 'OTHER';

    public function label(): string
    {
        return match ($this) {
            self::REPLACEMENT_CAR => 'Mașină de înlocuire',
            self::TAXI => 'Taxi',
            self::RIDE_HOME => 'Transport acasă',
            self::OTHER => 'Altă solicitare',
        };
    }
}
