<?php

declare(strict_types=1);

namespace App\Notification\Domain;

use App\Identity\Domain\User;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'notifications')]
#[ORM\Index(name: 'ix_notif_user', columns: ['user_id'])]
#[ORM\Index(name: 'ix_notif_dedup', columns: ['dedup_key'])]
class Notification
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(length: 64)]
    private string $type;

    /** @var array<string, mixed> */
    #[ORM\Column(type: 'json')]
    private array $payload;

    #[ORM\Column(length: 16)]
    private string $channel;

    /** Starea reală de livrare (vezi NotificationStatus). Implicit PENDING. */
    #[ORM\Column(length: 32, enumType: NotificationStatus::class, options: ['default' => 'PENDING'])]
    private NotificationStatus $status = NotificationStatus::PENDING;

    /** Cheie de deduplicare (idempotență la reîncercări). */
    #[ORM\Column(length: 191, nullable: true)]
    private ?string $dedupKey = null;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $attempts = 0;

    #[ORM\Column(type: 'datetimetz_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastAttemptAt = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $provider = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $failureReason = null;

    /** Adminul care a confirmat trimiterea manuală (dacă e cazul). */
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'sent_by_id', nullable: true, onDelete: 'SET NULL')]
    private ?User $sentBy = null;

    #[ORM\Column(type: 'datetimetz_immutable', nullable: true)]
    private ?\DateTimeImmutable $readAt = null;

    #[ORM\Column(type: 'datetimetz_immutable', nullable: true)]
    private ?\DateTimeImmutable $sentAt = null;

    #[ORM\Column(type: 'datetimetz_immutable')]
    private \DateTimeImmutable $createdAt;

    /** @param array<string, mixed> $payload */
    public function __construct(User $user, string $type, array $payload, string $channel, ?string $dedupKey = null)
    {
        $this->id = Uuid::v7();
        $this->user = $user;
        $this->type = $type;
        $this->payload = $payload;
        $this->channel = $channel;
        $this->dedupKey = $dedupKey;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function user(): User
    {
        return $this->user;
    }

    public function channel(): string
    {
        return $this->channel;
    }

    public function status(): NotificationStatus
    {
        return $this->status;
    }

    public function dedupKey(): ?string
    {
        return $this->dedupKey;
    }

    public function attempts(): int
    {
        return $this->attempts;
    }

    public function failureReason(): ?string
    {
        return $this->failureReason;
    }

    public function provider(): ?string
    {
        return $this->provider;
    }

    public function sentAt(): ?\DateTimeImmutable
    {
        return $this->sentAt;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** O încercare de livrare începe: PROCESSING + contor. */
    public function markProcessing(): void
    {
        $this->status = NotificationStatus::PROCESSING;
        ++$this->attempts;
        $this->lastAttemptAt = new \DateTimeImmutable();
    }

    /** Livrare reușită printr-un furnizor automat. */
    public function markSent(string $provider): void
    {
        $this->status = NotificationStatus::SENT;
        $this->provider = $provider;
        $this->sentAt = new \DateTimeImmutable();
        $this->failureReason = null;
    }

    /**
     * Confirmare MANUALĂ de către un admin (WhatsApp/telefon/email trimis de om).
     * Singura altă cale către SENT în afară de un furnizor automat.
     */
    public function markManuallySent(User $admin, string $channel, ?string $note = null): void
    {
        $this->status = NotificationStatus::SENT;
        $this->provider = 'manual';
        $this->channel = $channel;
        $this->sentBy = $admin;
        $this->sentAt = new \DateTimeImmutable();
        if ($note !== null && $note !== '') {
            $this->payload = [...$this->payload, 'manualNote' => $note];
        }
        $this->failureReason = null;
    }

    public function markFailed(string $reason, ?string $provider = null): void
    {
        $this->status = NotificationStatus::FAILED;
        $this->failureReason = $reason;
        $this->provider = $provider;
    }

    public function markManualActionRequired(?string $reason = null): void
    {
        $this->status = NotificationStatus::MANUAL_ACTION_REQUIRED;
        $this->failureReason = $reason;
    }

    public function markSkipped(?string $reason = null): void
    {
        $this->status = NotificationStatus::SKIPPED;
        $this->failureReason = $reason;
    }
}
