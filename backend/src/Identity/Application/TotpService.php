<?php

declare(strict_types=1);

namespace App\Identity\Application;

/**
 * TOTP (RFC 6238) în PHP pur — fără dependențe externe. Compatibil cu Google
 * Authenticator / Microsoft Authenticator / Authy (HMAC-SHA1, 6 cifre, pas 30s).
 *
 * Secretul este codificat Base32 (RFC 4648) pentru URI-ul de provisioning.
 */
final class TotpService
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    private const DIGITS = 6;
    private const PERIOD = 30;

    /** Generează un secret nou (Base32, 160 biți — recomandarea RFC 4226). */
    public function generateSecret(): string
    {
        return $this->base32Encode(random_bytes(20));
    }

    /** URI otpauth:// pentru codul QR din aplicația de autentificare. */
    public function provisioningUri(string $secret, string $accountName, string $issuer = 'Bosch Car Service'): string
    {
        return sprintf(
            'otpauth://totp/%s:%s?secret=%s&issuer=%s&algorithm=SHA1&digits=%d&period=%d',
            rawurlencode($issuer),
            rawurlencode($accountName),
            $secret,
            rawurlencode($issuer),
            self::DIGITS,
            self::PERIOD,
        );
    }

    /**
     * Verifică un cod față de secret, tolerând o fereastră de ±$window pași
     * (implicit ±1 = ±30s) pentru derapajul de ceas. Comparație în timp constant.
     */
    public function verify(string $secret, string $code, int $window = 1): bool
    {
        $code = trim($code);
        if (!preg_match('/^\d{6}$/', $code)) {
            return false;
        }

        $counter = intdiv(time(), self::PERIOD);
        for ($offset = -$window; $offset <= $window; ++$offset) {
            if (hash_equals($this->codeForCounter($secret, $counter + $offset), $code)) {
                return true;
            }
        }

        return false;
    }

    /** Codul TOTP valabil la un moment dat (secunde Unix). Expus pentru testare. */
    public function codeAt(string $secret, int $timestamp): string
    {
        return $this->codeForCounter($secret, intdiv($timestamp, self::PERIOD));
    }

    private function codeForCounter(string $secret, int $counter): string
    {
        $key = $this->base32Decode($secret);
        $binCounter = pack('N*', 0).pack('N*', $counter); // 8 octeți big-endian
        $hash = hash_hmac('sha1', $binCounter, $key, true);

        $offset = \ord($hash[19]) & 0x0F;
        $truncated = ((\ord($hash[$offset]) & 0x7F) << 24)
            | ((\ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((\ord($hash[$offset + 2]) & 0xFF) << 8)
            | (\ord($hash[$offset + 3]) & 0xFF);

        return str_pad((string) ($truncated % (10 ** self::DIGITS)), self::DIGITS, '0', STR_PAD_LEFT);
    }

    private function base32Encode(string $bytes): string
    {
        $buffer = 0;
        $bitsLeft = 0;
        $output = '';
        foreach (str_split($bytes) as $byte) {
            $buffer = ($buffer << 8) | \ord($byte);
            $bitsLeft += 8;
            while ($bitsLeft >= 5) {
                $bitsLeft -= 5;
                $output .= self::ALPHABET[($buffer >> $bitsLeft) & 0x1F];
            }
        }
        if ($bitsLeft > 0) {
            $output .= self::ALPHABET[($buffer << (5 - $bitsLeft)) & 0x1F];
        }

        return $output;
    }

    private function base32Decode(string $secret): string
    {
        $secret = rtrim(strtoupper($secret), '=');
        $buffer = 0;
        $bitsLeft = 0;
        $output = '';
        foreach (str_split($secret) as $char) {
            $value = strpos(self::ALPHABET, $char);
            if ($value === false) {
                continue;
            }
            $buffer = ($buffer << 5) | $value;
            $bitsLeft += 5;
            if ($bitsLeft >= 8) {
                $bitsLeft -= 8;
                $output .= \chr(($buffer >> $bitsLeft) & 0xFF);
            }
        }

        return $output;
    }
}
