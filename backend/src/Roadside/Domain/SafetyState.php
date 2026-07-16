<?php

declare(strict_types=1);

namespace App\Roadside\Domain;

/** Situația de siguranță a ocupanților / vehiculului la locul evenimentului. */
enum SafetyState: string
{
    case SAFE = 'SAFE';
    case AT_RISK = 'AT_RISK';

    public function label(): string
    {
        return match ($this) {
            self::SAFE => 'În siguranță',
            self::AT_RISK => 'Situație periculoasă',
        };
    }
}
