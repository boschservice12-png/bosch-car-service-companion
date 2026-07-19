<?php

declare(strict_types=1);

namespace App\Communication\Presentation;

use App\Communication\Domain\Conversation;
use App\Communication\Domain\Message;
use App\Document\Domain\Document;

/** Serializează conversații și mesaje pentru API. */
final class ConversationSerializer
{
    /**
     * @param Conversation[] $conversations
     *
     * @return array<int, array<string, mixed>>
     */
    public function serializeList(array $conversations, bool $withCustomer): array
    {
        return array_map(fn (Conversation $c): array => $this->summary($c, $withCustomer), $conversations);
    }

    /** @return array<string, mixed> */
    public function summary(Conversation $c, bool $withCustomer): array
    {
        $messages = $c->messages();
        $last = $messages !== [] ? end($messages) : null;

        $data = [
            'id' => (string) $c->id(),
            'subject' => $c->subject(),
            'status' => $c->status()->value,
            'statusLabel' => $c->status()->label(),
            'vehicleId' => $c->vehicle() !== null ? (string) $c->vehicle()->id() : null,
            'vehiclePlate' => $c->vehicle()?->plateNumber(),
            'messageCount' => \count($messages),
            'lastMessagePreview' => $last !== null ? mb_substr($last->body(), 0, 120) : null,
            'createdAt' => $c->createdAt()->format(DATE_ATOM),
            'lastMessageAt' => $c->lastMessageAt()->format(DATE_ATOM),
        ];

        if ($withCustomer) {
            $customer = $c->customer();
            $data['customerName'] = $customer->customerProfile()?->fullName() ?: $customer->getUserIdentifier();
        }

        return $data;
    }

    /** @return array<string, mixed> */
    public function detail(Conversation $c, bool $withCustomer): array
    {
        $data = $this->summary($c, $withCustomer);
        $data['messages'] = array_map($this->serializeMessage(...), $c->messages());

        return $data;
    }

    /** @return array<string, mixed> */

    /** @return array<int, array<string, mixed>> Doar mesajele (GET /messages). */
    public function messages(\App\Communication\Domain\Conversation $c): array
    {
        return array_map($this->serializeMessage(...), $c->messages());
    }

    private function serializeMessage(Message $m): array
    {
        return [
            'id' => (string) $m->id(),
            'authorRole' => $m->authorRole()->value,
            'authorLabel' => $m->authorRole()->label(),
            'body' => $m->body(),
            'createdAt' => $m->createdAt()->format(DATE_ATOM),
            'attachments' => array_map($this->serializeDocument(...), $m->attachments()),
        ];
    }

    /** @return array<string, mixed> */
    private function serializeDocument(Document $document): array
    {
        return [
            'id' => (string) $document->id(),
            'originalName' => $document->originalName(),
            'mimeType' => $document->mimeType(),
            'sizeBytes' => $document->sizeBytes(),
            'scanStatus' => $document->scanStatus()->value,
            'servable' => $document->isServable(),
        ];
    }
}
