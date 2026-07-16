<?php

declare(strict_types=1);

namespace App\Deadline\Domain;

/** Tipurile de scadență urmărite (model comun `vehicle_deadlines`). */
enum DeadlineType: string
{
    case ITP = 'ITP';
    case RCA = 'RCA';
    case ROAD_TAX = 'ROAD_TAX';
    case ROADSIDE_ASSISTANCE = 'ROADSIDE_ASSISTANCE';

    /** Etichetă în limba română pentru UI. */
    public function label(): string
    {
        return match ($this) {
            self::ITP => 'ITP',
            self::RCA => 'RCA',
            self::ROAD_TAX => 'Rovinietă / taxă de drum',
            self::ROADSIDE_ASSISTANCE => 'Asistență rutieră',
        };
    }
}
