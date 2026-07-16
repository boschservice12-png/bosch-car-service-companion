<?php

declare(strict_types=1);

namespace App\Identity\Presentation;

use Symfony\Component\Security\Core\Exception\AuthenticationException;

/**
 * Aruncată în timpul autentificării când un admin cu 2FA activat nu a furnizat
 * un cod TOTP valid. Handler-ul de eșec o transformă într-un răspuns distinct
 * („totp_required") ca frontend-ul să solicite codul.
 */
final class TwoFactorRequiredException extends AuthenticationException
{
    public function getMessageKey(): string
    {
        return 'Este necesar un cod de autentificare în doi pași (2FA).';
    }
}
