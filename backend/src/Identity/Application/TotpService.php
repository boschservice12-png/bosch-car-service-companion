<?php

declare(strict_types=1);

namespace App\Identity\Application;

use OTPHP\TOTP;

/**
 * Autentificare în doi pași bazată pe TOTP (RFC 6238), prin spomky-labs/otphp.
 * Compatibil cu aplicațiile standard (Google Authenticator, Aegis etc.) prin
 * `otpauth://` provisioning URI.
 */
final class TotpService
{
    private const ISSUER = 'Bosch Car Service';

    /** Verificarea acceptă o fereastră de toleranță (leeway) pentru drift de ceas. */
    private const LEEWAY_WINDOWS = 1;

    public function generateSecret(): string
    {
        return TOTP::generate()->getSecret();
    }

    public function provisioningUri(string $secret, string $label): string
    {
        $totp = TOTP::createFromSecret($secret);
        $totp->setLabel($label);
        $totp->setIssuer(self::ISSUER);

        return $totp->getProvisioningUri();
    }

    public function verify(string $secret, string $code): bool
    {
        $code = trim($code);
        if ($code === '' || $secret === '') {
            return false;
        }

        return TOTP::createFromSecret($secret)->verify($code, null, self::LEEWAY_WINDOWS);
    }
}
