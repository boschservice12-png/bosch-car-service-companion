<?php

declare(strict_types=1);

namespace App\Tests\Unit\Communication;

use App\Communication\Domain\Conversation;
use App\Communication\Domain\ConversationStatus;
use App\Communication\Domain\ConversationType;
use App\Communication\Domain\Message;
use App\Communication\Domain\MessageAuthorRole;
use App\Identity\Domain\User;
use PHPUnit\Framework\TestCase;

/** Reguli de stare pentru fluxul cererii de ofertă (domeniu pur, fără DB). */
final class ConversationTest extends TestCase
{
    public function testQuoteFlowTransitions(): void
    {
        $conversation = new Conversation($this->user(), ConversationType::QUOTE, 'Reparație frâne');
        self::assertSame(ConversationStatus::OPEN, $conversation->status());
        self::assertTrue($conversation->isQuote());

        $conversation->setQuote(125000);
        self::assertSame(ConversationStatus::QUOTED, $conversation->status());
        self::assertSame(125000, $conversation->quoteAmountBani());

        $conversation->acceptQuote();
        self::assertSame(ConversationStatus::ACCEPTED, $conversation->status());
    }

    public function testDeclineSetsDeclined(): void
    {
        $conversation = new Conversation($this->user(), ConversationType::QUOTE, 'Diagnoză');
        $conversation->setQuote(50000);
        $conversation->declineQuote();
        self::assertSame(ConversationStatus::DECLINED, $conversation->status());
    }

    public function testAddMessageUpdatesCountAndLastMessageAt(): void
    {
        $conversation = new Conversation($this->user(), ConversationType::GENERAL, 'Întrebare');
        self::assertCount(0, $conversation->messages());

        $message = new Message($conversation, $this->user(), MessageAuthorRole::CLIENT, 'Salut');
        $conversation->addMessage($message);

        self::assertCount(1, $conversation->messages());
        self::assertSame($message->createdAt(), $conversation->lastMessageAt());
    }

    private function user(): User
    {
        return new User('client-'.uniqid().'@example.test');
    }
}
