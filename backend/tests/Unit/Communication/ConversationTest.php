<?php

declare(strict_types=1);

namespace App\Tests\Unit\Communication;

use App\Communication\Domain\Conversation;
use App\Communication\Domain\ConversationStatus;
use App\Communication\Domain\Message;
use App\Communication\Domain\MessageAuthorRole;
use App\Identity\Domain\User;
use App\Shared\Domain\InvalidStateTransition;
use PHPUnit\Framework\TestCase;

/** Stările conversației conform specificației (domeniu pur, fără DB). */
final class ConversationTest extends TestCase
{
    public function testStatusFollowsWhoIsExpectedToReply(): void
    {
        $conversation = new Conversation($this->user(), 'Programare revizie');
        self::assertSame(ConversationStatus::OPEN, $conversation->status());

        $conversation->markWaitingClient();
        self::assertSame(ConversationStatus::WAITING_CLIENT, $conversation->status());

        $conversation->markWaitingService();
        self::assertSame(ConversationStatus::WAITING_SERVICE, $conversation->status());
    }

    public function testCloseAndReopen(): void
    {
        $conversation = new Conversation($this->user(), 'Întrebare');
        $conversation->close();
        self::assertSame(ConversationStatus::CLOSED, $conversation->status());

        // Pe o conversație închisă nu se mai schimbă starea prin mesaje.
        $this->expectException(InvalidStateTransition::class);
        $conversation->markWaitingService();
    }

    public function testReopenOnlyFromClosed(): void
    {
        $conversation = new Conversation($this->user(), 'Întrebare');
        $conversation->close();
        $conversation->reopen();
        self::assertSame(ConversationStatus::OPEN, $conversation->status());

        // Redeschiderea unei conversații deja deschise este respinsă.
        $this->expectException(InvalidStateTransition::class);
        $conversation->reopen();
    }

    public function testAddMessageUpdatesCountAndLastMessageAt(): void
    {
        $conversation = new Conversation($this->user(), 'Întrebare');
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
