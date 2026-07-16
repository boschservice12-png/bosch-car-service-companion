<?php

declare(strict_types=1);

namespace App\Tests\Unit\QuoteRequest;

use App\QuoteRequest\Domain\QuoteRequestStatus as S;
use PHPUnit\Framework\TestCase;

final class QuoteRequestStatusTest extends TestCase
{
    public function testHappyPath(): void
    {
        self::assertTrue(S::DRAFT->canTransitionTo(S::SUBMITTED));
        self::assertTrue(S::SUBMITTED->canTransitionTo(S::IN_REVIEW));
        self::assertTrue(S::IN_REVIEW->canTransitionTo(S::REPLIED));
        self::assertTrue(S::REPLIED->canTransitionTo(S::ACCEPTED));
        self::assertTrue(S::ACCEPTED->canTransitionTo(S::CLOSED));
    }

    public function testIllegalTransitionsAreRejected(): void
    {
        self::assertFalse(S::DRAFT->canTransitionTo(S::CLOSED));
        self::assertFalse(S::SUBMITTED->canTransitionTo(S::ACCEPTED));
        self::assertFalse(S::CLOSED->canTransitionTo(S::IN_REVIEW));
    }

    public function testClosedIsTerminal(): void
    {
        self::assertSame([], S::CLOSED->allowedTransitions());
    }

    public function testNeedsInformationCanReturnToReview(): void
    {
        self::assertTrue(S::NEEDS_INFORMATION->canTransitionTo(S::IN_REVIEW));
    }
}
