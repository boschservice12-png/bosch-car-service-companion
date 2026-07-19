<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\DamageClaim\Domain\DamageClaimStatus;
use App\Mobility\Domain\MobilityStatus;
use App\Roadside\Domain\RoadsideStatus;
use App\Tax\Domain\PaymentStatus;
use App\Tax\Domain\TaxItem;
use App\Tax\Domain\TaxType;
use App\Identity\Domain\User;
use PHPUnit\Framework\TestCase;

/**
 * Mașinile de stări conform specificației funcționale: fluxurile permise trec,
 * scurtăturile și revenirile din stări terminale sunt respinse.
 *
 * @group unit
 */
final class StateMachineTransitionsTest extends TestCase
{
    public function testRoadsideFollowsSpecFlow(): void
    {
        $s = RoadsideStatus::SUBMITTED;
        self::assertTrue($s->canTransitionTo(RoadsideStatus::VALIDATED));
        self::assertTrue($s->canTransitionTo(RoadsideStatus::CANCELLED));
        self::assertFalse($s->canTransitionTo(RoadsideStatus::FORWARDED), 'Fără validare nu se direcționează.');
        self::assertFalse($s->canTransitionTo(RoadsideStatus::COMPLETED));

        self::assertTrue(RoadsideStatus::VALIDATED->canTransitionTo(RoadsideStatus::FORWARDED));
        self::assertTrue(RoadsideStatus::FORWARDED->canTransitionTo(RoadsideStatus::IN_PROGRESS));
        self::assertTrue(RoadsideStatus::IN_PROGRESS->canTransitionTo(RoadsideStatus::COMPLETED));

        self::assertTrue(RoadsideStatus::COMPLETED->isTerminal());
        self::assertTrue(RoadsideStatus::CANCELLED->isTerminal());
        self::assertFalse(RoadsideStatus::COMPLETED->canTransitionTo(RoadsideStatus::SUBMITTED), 'Din terminal nu se revine.');
    }

    public function testMobilityFollowsSpecFlow(): void
    {
        self::assertTrue(MobilityStatus::SUBMITTED->canTransitionTo(MobilityStatus::IN_REVIEW));
        self::assertFalse(MobilityStatus::SUBMITTED->canTransitionTo(MobilityStatus::CONFIRMED), 'Fără analiză nu se confirmă.');
        self::assertTrue(MobilityStatus::IN_REVIEW->canTransitionTo(MobilityStatus::CONTACTED));
        self::assertTrue(MobilityStatus::IN_REVIEW->canTransitionTo(MobilityStatus::UNAVAILABLE));
        self::assertTrue(MobilityStatus::CONTACTED->canTransitionTo(MobilityStatus::CONFIRMED));
        self::assertTrue(MobilityStatus::CONFIRMED->canTransitionTo(MobilityStatus::COMPLETED));

        foreach ([MobilityStatus::UNAVAILABLE, MobilityStatus::COMPLETED, MobilityStatus::CANCELLED] as $terminal) {
            self::assertTrue($terminal->isTerminal(), $terminal->value.' este terminală.');
        }
    }

    public function testDamageClaimFollowsSpecFlow(): void
    {
        self::assertTrue(DamageClaimStatus::SUBMITTED->canTransitionTo(DamageClaimStatus::DOCUMENTS_MISSING));
        self::assertTrue(DamageClaimStatus::DOCUMENTS_MISSING->canTransitionTo(DamageClaimStatus::IN_REVIEW));
        self::assertTrue(DamageClaimStatus::IN_REVIEW->canTransitionTo(DamageClaimStatus::DOCUMENTS_MISSING), 'Se pot cere documente și din analiză.');
        self::assertTrue(DamageClaimStatus::IN_REVIEW->canTransitionTo(DamageClaimStatus::CONTACTED));
        self::assertTrue(DamageClaimStatus::CONTACTED->canTransitionTo(DamageClaimStatus::FILE_OPENED));
        self::assertTrue(DamageClaimStatus::FILE_OPENED->canTransitionTo(DamageClaimStatus::CLOSED));

        self::assertFalse(DamageClaimStatus::SUBMITTED->canTransitionTo(DamageClaimStatus::FILE_OPENED), 'Dosarul nu se deschide fără analiză.');
        self::assertTrue(DamageClaimStatus::CLOSED->isTerminal());
    }

    public function testTaxPartialPaymentAndOverdueDerivation(): void
    {
        $user = new User('client@example.test');
        $item = new TaxItem($user, null, 2026, TaxType::VEHICLE_TAX, 48000, new \DateTimeImmutable('+30 days'));

        self::assertSame(PaymentStatus::UNPAID, $item->effectiveStatus());

        $item->registerPayment(20000);
        self::assertSame(PaymentStatus::PARTIALLY_PAID, $item->status());
        self::assertSame(20000, $item->paidAmountBani());

        $item->registerPayment(28000);
        self::assertSame(PaymentStatus::PAID, $item->status());
        self::assertSame(48000, $item->paidAmountBani());

        // Scadență depășită fără plată integrală → OVERDUE (derivat), stocată rămâne UNPAID.
        $overdue = new TaxItem($user, null, 2025, TaxType::VEHICLE_TAX, 10000, new \DateTimeImmutable('-1 day'));
        self::assertSame(PaymentStatus::OVERDUE, $overdue->effectiveStatus());
        self::assertSame(PaymentStatus::UNPAID, $overdue->status());

        // Plata integrală elimină restanța.
        $overdue->registerPayment(10000);
        self::assertSame(PaymentStatus::PAID, $overdue->effectiveStatus());
    }
}
