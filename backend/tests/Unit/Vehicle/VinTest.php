<?php

declare(strict_types=1);

namespace App\Tests\Unit\Vehicle;

use App\Vehicle\Domain\Vin;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class VinTest extends TestCase
{
    public function testNormalizesToUppercaseAndTrims(): void
    {
        $vin = new Vin('  wba3a5c50ef123456 ');
        self::assertSame('WBA3A5C50EF123456', $vin->value());
    }

    public function testRejectsWrongLength(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Vin('ABC123');
    }

    public function testRejectsDisallowedLetters(): void
    {
        $this->expectException(InvalidArgumentException::class);
        // conține litera O, interzisă
        new Vin('WBA3A5C50EF12345O');
    }

    public function testEquality(): void
    {
        self::assertTrue((new Vin('WBA3A5C50EF123456'))->equals(new Vin('wba3a5c50ef123456')));
    }
}
