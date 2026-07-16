<?php

declare(strict_types=1);

namespace App\Deadline\Domain;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Evidența notificărilor deja trimise pentru o scadență, la un anumit prag.
 * Constrângerea unică (deadline, threshold, channel) asigură idempotența:
 * nu se trimite de două ori aceeași notificare.
 */
#[ORM\Entity]
#[ORM\Table(name: 'deadline_notifications')]
#[ORM\UniqueConstraint(name: 'uq_deadline_threshold_channel', columns: ['deadline_id', 'threshold_days', 'channel'])]
class DeadlineNotification
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: VehicleDeadline::class)]
    #[ORM\JoinColumn(name: 'deadline_id', nullable: false, onDelete: 'CASCADE')]
    private VehicleDeadline $deadline;

    #[ORM\Column(name: 'threshold_days', type: 'integer')]
    private int $thresholdDays;

    #[ORM\Column(length: 16)]
    private string $channel;

    #[ORM\Column(type: 'datetimetz_immutable')]
    private \DateTimeImmutable $sentAt;

    public function __construct(VehicleDeadline $deadline, int $thresholdDays, string $channel)
    {
        $this->id = Uuid::v7();
        $this->deadline = $deadline;
        $this->thresholdDays = $thresholdDays;
        $this->channel = $channel;
        $this->sentAt = new \DateTimeImmutable();
    }

    public function id(): Uuid
    {
        return $this->id;
    }
}
