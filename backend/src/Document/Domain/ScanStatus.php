<?php

declare(strict_types=1);

namespace App\Document\Domain;

enum ScanStatus: string
{
    case PENDING = 'PENDING';
    case CLEAN = 'CLEAN';
    case INFECTED = 'INFECTED';
}
