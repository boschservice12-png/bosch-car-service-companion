<?php

declare(strict_types=1);

namespace App\Deadline\Domain;

/** Sursa unei scadențe: introdusă de client, validată de service sau importată. */
enum DeadlineSource: string
{
    case CLIENT = 'CLIENT';
    case SERVICE = 'SERVICE';
    case IMPORT = 'IMPORT';
}
