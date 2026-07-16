<?php

declare(strict_types=1);

namespace App\Communication\Application;

use App\Audit\Application\AuditRecorder;
use App\Communication\Domain\Conversation;
use App\Communication\Domain\ConversationRepository;
use App\Communication\Domain\ConversationStatus;
use App\Communication\Domain\ConversationType;
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
        ConversationType $type,
        string $subject,
        ?Vehicle $vehicle,
        string $body,
        array $attachments,
    ): Conversation {
        $conversation = new Conversation($customer, $type, $subject, $vehicle);
        $this->appendMessage($conversation, $customer, MessageAuthorRole::CLIENT, $body, $attachments);
        $this->conversations->save($conversation);

        $this->audit->record('conversation.started', 'Conversation', (string) $conversation->id(), null, [
            'type' => $type->value,
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
        $this->conversations->save($conversation);

        $this->audit->record('conversation.message_posted', 'Conversation', (string) $conversation->id(), null, [
            'messageId' => (string) $message->id(),
            'authorRole' => $role->value,
        ]);

        return $message;
    }

    /**
     * Service-ul răspunde unei cereri de ofertă cu o sumă (bani). Adaugă și un mesaj
     * din partea service-ului cu textul ofertei.
     */
    public function quote(Conversation $conversation, User $admin, int $amountBani, ?string $body): Conversation
    {
        if (!$conversation->isQuote()) {
            throw ValidationFailedException::fromArray(['quote' => ['Doar cererile de ofertă pot primi o sumă.']]);
        }
        if ($amountBani < 0) {
            throw ValidationFailedException::fromArray(['amount' => ['Suma nu poate fi negativă.']]);
        }

        $conversation->setQuote($amountBani);
        $text = $body !== null && trim($body) !== ''
            ? $body
            : sprintf('Ofertă: %s RON.', number_format($amountBani / 100, 2, ',', '.'));
        $this->appendMessage($conversation, $admin, MessageAuthorRole::ADMIN, $text, []);
        $this->conversations->save($conversation);

        $this->audit->record('conversation.quoted', 'Conversation', (string) $conversation->id(), null, [
            'amountBani' => $amountBani,
        ]);

        return $conversation;
    }

    public function respondToQuote(Conversation $conversation, User $customer, bool $accept): Conversation
    {
        if (!$conversation->isQuote() || $conversation->status() !== ConversationStatus::QUOTED) {
            throw ValidationFailedException::fromArray([
                'quote' => ['Nu există o ofertă în așteptare pentru această cerere.'],
            ]);
        }

        if ($accept) {
            $conversation->acceptQuote();
        } else {
            $conversation->declineQuote();
        }
        $this->appendMessage(
            $conversation,
            $customer,
            MessageAuthorRole::CLIENT,
            $accept ? 'Ofertă acceptată.' : 'Ofertă refuzată.',
            [],
        );
        $this->conversations->save($conversation);

        $this->audit->record(
            $accept ? 'conversation.quote_accepted' : 'conversation.quote_declined',
            'Conversation',
            (string) $conversation->id(),
        );

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
