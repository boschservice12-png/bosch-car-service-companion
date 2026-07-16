<?php

declare(strict_types=1);

namespace App\Communication\Domain;

use App\Identity\Domain\User;
use App\Vehicle\Domain\Vehicle;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Un fir de conversație client ↔ service. Poate fi mesagerie generală sau o cerere
 * de ofertă (reparație) cu flux de stări. Conversația aparține unui client
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

    #[ORM\Column(length: 16, enumType: ConversationType::class)]
    private ConversationType $type;

    #[ORM\Column(length: 200)]
    private string $subject;

    #[ORM\ManyToOne(targetEntity: Vehicle::class)]
    #[ORM\JoinColumn(name: 'vehicle_id', nullable: true, onDelete: 'SET NULL')]
    private ?Vehicle $vehicle;

    #[ORM\Column(length: 16, enumType: ConversationStatus::class)]
    private ConversationStatus $status = ConversationStatus::OPEN;

    /** Suma ofertei în bani (RON * 100), setată de service la cererile de ofertă. */
    #[ORM\Column(nullable: true)]
    private ?int $quoteAmountBani = null;

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

    public function __construct(User $customer, ConversationType $type, string $subject, ?Vehicle $vehicle = null)
    {
        $this->id = Uuid::v7();
        $this->customer = $customer;
        $this->type = $type;
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

    public function type(): ConversationType
    {
        return $this->type;
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

    public function quoteAmountBani(): ?int
    {
        return $this->quoteAmountBani;
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

    public function isQuote(): bool
    {
        return $this->type === ConversationType::QUOTE;
    }

    /** Service-ul răspunde cererii de ofertă cu o sumă → starea devine QUOTED. */
    public function setQuote(int $amountBani): void
    {
        $this->quoteAmountBani = $amountBani;
        $this->status = ConversationStatus::QUOTED;
        $this->touch();
    }

    public function acceptQuote(): void
    {
        $this->status = ConversationStatus::ACCEPTED;
        $this->touch();
    }

    public function declineQuote(): void
    {
        $this->status = ConversationStatus::DECLINED;
        $this->touch();
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
