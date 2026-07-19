<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Identity\Application\TotpService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Verifică implementarea TOTP față de vectorii de test oficiali din RFC 6238
 * (Anexa B, algoritm SHA1) — dovada că matematica 2FA e conformă standardului,
 * deci compatibilă cu Google/Microsoft Authenticator, Authy etc.
 *
 * Secretul RFC este ASCII „12345678901234567890" (20 octeți) = Base32
 * „GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ". Codurile RFC au 8 cifre; implementarea
 * noastră folosește 6 → comparăm cu ultimele 6 cifre ale vectorilor oficiali.
 *
 * @group unit
 */
final class TotpServiceTest extends TestCase
{
    private const RFC_SECRET = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

    /**
     * @return array<string, array{0: int, 1: string}>
     */
    public static function rfcVectors(): array
    {
        // [timestamp Unix, ultimele 6 cifre ale codului RFC 6238 SHA1 pe 8 cifre]
        return [
            'T=59' => [59, '287082'],          // RFC: 94287082
            'T=1111111109' => [1111111109, '081804'],  // RFC: 07081804
            'T=1111111111' => [1111111111, '050471'],  // RFC: 14050471
            'T=1234567890' => [1234567890, '005924'],  // RFC: 89005924
            'T=2000000000' => [2000000000, '279037'],  // RFC: 69279037
            'T=20000000000' => [20000000000, '353130'], // RFC: 65353130
        ];
    }

    #[DataProvider('rfcVectors')]
    public function testMatchesRfc6238Vectors(int $timestamp, string $expected): void
    {
        $totp = new TotpService();
        self::assertSame($expected, $totp->codeAt(self::RFC_SECRET, $timestamp));
    }

    public function testVerifyAcceptsCurrentCodeAndRejectsWrongOne(): void
    {
        $totp = new TotpService();
        $secret = $totp->generateSecret();
        $current = $totp->codeAt($secret, time());

        self::assertTrue($totp->verify($secret, $current), 'Codul curent trebuie acceptat.');
        self::assertFalse($totp->verify($secret, '000000', 0), 'Un cod arbitrar nu trebuie acceptat.');
    }

    public function testVerifyToleratesClockDriftWithinWindow(): void
    {
        $totp = new TotpService();
        $secret = $totp->generateSecret();

        // Codul din pasul anterior (−30s) trebuie acceptat cu fereastra implicită ±1.
        $previous = $totp->codeAt($secret, time() - 30);
        self::assertTrue($totp->verify($secret, $previous), 'Derapajul de un pas trebuie tolerat.');

        // Dar nu și un pas prea îndepărtat (−90s) cu fereastra implicită.
        $farOff = $totp->codeAt($secret, time() - 90);
        self::assertFalse($totp->verify($secret, $farOff), 'Un derapaj prea mare nu trebuie tolerat.');
    }

    public function testVerifyRejectsMalformedInput(): void
    {
        $totp = new TotpService();
        $secret = $totp->generateSecret();

        self::assertFalse($totp->verify($secret, ''), 'Codul gol e respins.');
        self::assertFalse($totp->verify($secret, '12345'), 'Sub 6 cifre e respins.');
        self::assertFalse($totp->verify($secret, 'abcdef'), 'Non-numeric e respins.');
    }

    public function testGeneratedSecretIsBase32AndProvisioningUriIsWellFormed(): void
    {
        $totp = new TotpService();
        $secret = $totp->generateSecret();

        self::assertMatchesRegularExpression('/^[A-Z2-7]+$/', $secret, 'Secretul e Base32.');
        self::assertSame(32, \strlen($secret), '160 biți în Base32 = 32 caractere.');

        $uri = $totp->provisioningUri($secret, 'admin@service.ro');
        self::assertStringStartsWith('otpauth://totp/', $uri);
        self::assertStringContainsString('secret='.$secret, $uri);
        self::assertStringContainsString('algorithm=SHA1', $uri);
        self::assertStringContainsString('digits=6', $uri);
    }
}
