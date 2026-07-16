<?php

declare(strict_types=1);

namespace App\Tests\Unit\Deadline;

use App\Deadline\Domain\DeadlineState;
use App\Deadline\Domain\DeadlineStatusCalculator;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class DeadlineStatusCalculatorTest extends TestCase
{
    private DeadlineStatusCalculator $calc;
    private DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->calc = new DeadlineStatusCalculator();
        $this->now = new DateTimeImmutable('2026-07-15 10:00:00');
    }

    public function testUnknownWhenNoDate(): void
    {
        self::assertSame(DeadlineState::UNKNOWN, $this->calc->calculate(null, $this->now));
    }

    public function testExpiredWhenPast(): void
    {
        $expires = new DateTimeImmutable('2026-07-14');
        self::assertSame(DeadlineState::EXPIRED, $this->calc->calculate($expires, $this->now));
    }

    public function testDueSoonWithinThreshold(): void
    {
        $expires = new DateTimeImmutable('2026-08-01'); // 17 zile
        self::assertSame(DeadlineState::DUE_SOON, $this->calc->calculate($expires, $this->now, 30));
    }

    public function testValidBeyondThreshold(): void
    {
        $expires = new DateTimeImmutable('2026-12-01');
        self::assertSame(DeadlineState::VALID, $this->calc->calculate($expires, $this->now, 30));
    }

    public function testDueSoonOnExpiryDay(): void
    {
        $expires = new DateTimeImmutable('2026-07-15');
        self::assertSame(DeadlineState::DUE_SOON, $this->calc->calculate($expires, $this->now, 7));
    }

    public function testDaysLeftIsNegativeWhenExpired(): void
    {
        $expires = new DateTimeImmutable('2026-07-10');
        self::assertSame(-5, $this->calc->daysLeft($expires, $this->now));
    }
}
