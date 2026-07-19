<?php

declare(strict_types=1);

namespace App\Communication\Application;

use App\Audit\Application\AuditRecorder;
use App\Communication\Domain\Conversation;
use App\Communication\Domain\ConversationRepository;
use App\Communication\Domain\ConversationStatus;
use App\Communication\Domain\Message;
use App\Communication\Domain\MessageAuthorRole;
use App\Document\Domain\Document;
use App\Identity\Domain\User;
use App\Shared\Presentation\ValidationFailedException;
use App\Vehicle\Domain\Vehicle;

final class CommunicationService
{
    public function __construct(
        private readonly ConversationRepository $conversations,
        private readonly AuditRecorder $audit,
    ) {
    }

    /**
     * @param Document[] $attachments
     */
    public function start(
        User $customer,
        string $subject,
        ?Vehicle $vehicle,
        string $body,
        array $attachments,
    ): Conversation {
        $conversation = new Conversation($customer, $subject, $vehicle);
        $this->appendMessage($conversation, $customer, MessageAuthorRole::CLIENT, $body, $attachments);
        $this->conversations->save($conversation);

        $this->audit->record('conversation.started', 'Conversation', (string) $conversation->id(), null, [
            'vehicleId' => $vehicle !== null ? (string) $vehicle->id() : null,
        ]);

        return $conversation;
    }

    /**
     * @param Document[] $attachments
     */
    public function addMessage(
        Conversation $conversation,
        User $sender,
        MessageAuthorRole $role,
        string $body,
        array $attachments,
    ): Message {
        $message = $this->appendMessage($conversation, $sender, $role, $body, $attachments);
        // Starea reflectă cine urmează să răspundă (specificație).
        if ($role === MessageAuthorRole::ADMIN) {
            $conversation->markWaitingClient();
        } else {
            $conversation->markWaitingService();
        }
        $this->conversations->save($conversation);

        $this->audit->record('conversation.message_posted', 'Conversation', (string) $conversation->id(), null, [
            'messageId' => (string) $message->id(),
            'authorRole' => $role->value,
        ]);

        return $message;
    }

    /** Service-ul închide conversația (specificație: „închide și redeschide"). */
    public function close(Conversation $conversation): Conversation
    {
        $conversation->close();
        $this->conversations->save($conversation);
        $this->audit->record('conversation.closed', 'Conversation', (string) $conversation->id());

        return $conversation;
    }

    public function reopen(Conversation $conversation): Conversation
    {
        $conversation->reopen();
        $this->conversations->save($conversation);
        $this->audit->record('conversation.reopened', 'Conversation', (string) $conversation->id());

        return $conversation;
    }

    /**
     * @param Document[] $attachments
     */
    private function appendMessage(
        Conversation $conversation,
        ?User $sender,
        MessageAuthorRole $role,
        string $body,
        array $attachments,
    ): Message {
        $message = new Message($conversation, $sender, $role, $body);
        foreach ($attachments as $document) {
            $message->attach($document);
        }
        $conversation->addMessage($message);

        return $message;
    }
}
