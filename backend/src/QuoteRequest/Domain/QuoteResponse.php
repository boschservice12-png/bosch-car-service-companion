<?php

declare(strict_types=1);

namespace App\QuoteRequest\Domain;

use App\Document\Domain\Document;
use App\Identity\Domain\User;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Răspunsul service-ului la o cerere de ofertă: text și, opțional, un document
 * (oferta detaliată). Conform specificației, adminul „poate trimite un răspuns
 * text și un document" — fără deviz automat.
 */
#[ORM\Entity]
#[ORM\Table(name: 'quote_responses')]
#[ORM\Index(name: 'ix_quote_response_request', columns: ['request_id'])]
class QuoteResponse
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: QuoteRequest::class, inversedBy: 'responses')]
    #[ORM\JoinColumn(name: 'request_id', nullable: false, onDelete: 'CASCADE')]
    private QuoteRequest $request;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'author_id', nullable: false)]
    private User $author;

    #[ORM\Column(type: 'text')]
    private string $message;

    #[ORM\ManyToOne(targetEntity: Document::class)]
    #[ORM\JoinColumn(name: 'document_id', nullable: true, onDelete: 'SET NULL')]
    private ?Document $document;

    #[ORM\Column(type: 'datetimetz_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(QuoteRequest $request, User $author, string $message, ?Document $document = null)
    {
        $this->id = Uuid::v7();
        $this->request = $request;
        $this->author = $author;
        $this->message = $message;
        $this->document = $document;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function request(): QuoteRequest
    {
        return $this->request;
    }

    public function author(): User
    {
        return $this->author;
    }

    public function message(): string
    {
        return $this->message;
    }

    public function document(): ?Document
    {
        return $this->document;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
