<?php

declare(strict_types=1);

namespace App\Communication\Domain;

use App\Identity\Domain\User;
use App\Shared\Domain\InvalidStateTransition;
use App\Vehicle\Domain\Vehicle;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Un fir de conversație client ↔ service (mesagerie). Starea urmează
 * specificația (OPEN / WAITING_CLIENT / WAITING_SERVICE / CLOSED); cererile de
 * ofertă au modulul propriu (QuoteRequest). Conversația aparține unui client
 * (autorizare la nivel de obiect: doar proprietarul sau un admin o pot vedea).
 */
#[ORM\Entity]
#[ORM\Table(name: 'conversations')]
#[ORM\Index(name: 'ix_conversations_customer', columns: ['customer_id'])]
#[ORM\Index(name: 'ix_conversations_status', columns: ['status'])]
class Conversation
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'customer_id', nullable: false)]
    private User $customer;

    #[ORM\Column(length: 200)]
    private string $subject;

    #[ORM\ManyToOne(targetEntity: Vehicle::class)]
    #[ORM\JoinColumn(name: 'vehicle_id', nullable: true, onDelete: 'SET NULL')]
    private ?Vehicle $vehicle;

    #[ORM\Column(length: 20, enumType: ConversationStatus::class)]
    private ConversationStatus $status = ConversationStatus::OPEN;

    /** @var Collection<int, Message> */
    #[ORM\OneToMany(mappedBy: 'conversation', targetEntity: Message::class, cascade: ['persist'])]
    #[ORM\OrderBy(['createdAt' => 'ASC'])]
    private Collection $messages;

    #[ORM\Column(type: 'datetimetz_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetimetz_immutable')]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(type: 'datetimetz_immutable')]
    private \DateTimeImmutable $lastMessageAt;

    public function __construct(User $customer, string $subject, ?Vehicle $vehicle = null)
    {
        $this->id = Uuid::v7();
        $this->customer = $customer;
        $this->subject = $subject;
        $this->vehicle = $vehicle;
        $this->messages = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
        $this->lastMessageAt = $this->createdAt;
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function customer(): User
    {
        return $this->customer;
    }

    public function subject(): string
    {
        return $this->subject;
    }

    public function vehicle(): ?Vehicle
    {
        return $this->vehicle;
    }

    public function status(): ConversationStatus
    {
        return $this->status;
    }

    /** @return Message[] */
    public function messages(): array
    {
        return $this->messages->toArray();
    }

    public function addMessage(Message $message): void
    {
        if (!$this->messages->contains($message)) {
            $this->messages->add($message);
        }
        $this->lastMessageAt = $message->createdAt();
        $this->touch();
    }

    /** Clientul a scris → e rândul service-ului să răspundă. */
    public function markWaitingService(): void
    {
        $this->assertNotClosed();
        $this->status = ConversationStatus::WAITING_SERVICE;
        $this->touch();
    }

    /** Service-ul a răspuns → e rândul clientului. */
    public function markWaitingClient(): void
    {
        $this->assertNotClosed();
        $this->status = ConversationStatus::WAITING_CLIENT;
        $this->touch();
    }

    /** Service-ul închide conversația. */
    public function close(): void
    {
        $this->assertNotClosed();
        $this->status = ConversationStatus::CLOSED;
        $this->touch();
    }

    /** Service-ul redeschide o conversație închisă. */
    public function reopen(): void
    {
        if ($this->status !== ConversationStatus::CLOSED) {
            throw InvalidStateTransition::between($this->status->value, ConversationStatus::OPEN->value);
        }
        $this->status = ConversationStatus::OPEN;
        $this->touch();
    }

    public function isClosed(): bool
    {
        return $this->status === ConversationStatus::CLOSED;
    }

    private function assertNotClosed(): void
    {
        if ($this->status === ConversationStatus::CLOSED) {
            throw InvalidStateTransition::between(ConversationStatus::CLOSED->value, 'MESSAGE');
        }
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function lastMessageAt(): \DateTimeImmutable
    {
        return $this->lastMessageAt;
    }

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
