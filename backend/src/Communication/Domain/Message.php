<?php

declare(strict_types=1);

namespace App\Communication\Domain;

use App\Document\Domain\Document;
use App\Identity\Domain\User;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/** Un mesaj dintr-o conversație, cu eventuale documente/foto atașate. */
#[ORM\Entity]
#[ORM\Table(name: 'messages')]
#[ORM\Index(name: 'ix_messages_conversation', columns: ['conversation_id'])]
class Message
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Conversation::class, inversedBy: 'messages')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Conversation $conversation;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'sender_id', nullable: true, onDelete: 'SET NULL')]
    private ?User $sender;

    #[ORM\Column(length: 16, enumType: MessageAuthorRole::class)]
    private MessageAuthorRole $authorRole;

    #[ORM\Column(type: 'text')]
    private string $body;

    /** @var Collection<int, Document> */
    #[ORM\ManyToMany(targetEntity: Document::class)]
    #[ORM\JoinTable(name: 'message_attachments')]
    private Collection $attachments;

    #[ORM\Column(type: 'datetimetz_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(Conversation $conversation, ?User $sender, MessageAuthorRole $authorRole, string $body)
    {
        $this->id = Uuid::v7();
        $this->conversation = $conversation;
        $this->sender = $sender;
        $this->authorRole = $authorRole;
        $this->body = $body;
        $this->attachments = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function conversation(): Conversation
    {
        return $this->conversation;
    }

    public function authorRole(): MessageAuthorRole
    {
        return $this->authorRole;
    }

    public function body(): string
    {
        return $this->body;
    }

    /** @return Document[] */
    public function attachments(): array
    {
        return $this->attachments->toArray();
    }

    public function hasAttachment(Document $document): bool
    {
        return $this->attachments->contains($document);
    }

    public function attach(Document $document): void
    {
        if (!$this->attachments->contains($document)) {
            $this->attachments->add($document);
        }
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
