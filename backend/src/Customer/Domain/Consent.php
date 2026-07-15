<?php

declare(strict_types=1);

namespace App\Customer\Domain;

use App\Identity\Domain\User;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Evidența consimțămintelor GDPR. Fiecare acord/retragere este înregistrat cu
 * versiunea textului informării de confidențialitate în vigoare la acel moment.
 */
#[ORM\Entity]
#[ORM\Table(name: 'consents')]
class Consent
{
    public const TYPE_DATA_PROCESSING = 'data_processing';
    public const TYPE_GPS_LOCATION = 'gps_location';

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(length: 64)]
    private string $type;

    #[ORM\Column]
    private bool $granted;

    #[ORM\Column(length: 32)]
    private string $textVersion;

    #[ORM\Column(type: 'datetimetz_immutable', nullable: true)]
    private ?\DateTimeImmutable $grantedAt;

    #[ORM\Column(type: 'datetimetz_immutable', nullable: true)]
    private ?\DateTimeImmutable $revokedAt = null;

    public function __construct(User $user, string $type, bool $granted, string $textVersion)
    {
        $this->id = Uuid::v7();
        $this->user = $user;
        $this->type = $type;
        $this->granted = $granted;
        $this->textVersion = $textVersion;
        $this->grantedAt = $granted ? new \DateTimeImmutable() : null;
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function type(): string
    {
        return $this->type;
    }

    public function isGranted(): bool
    {
        return $this->granted;
    }

    public function revoke(): void
    {
        $this->granted = false;
        $this->revokedAt = new \DateTimeImmutable();
    }
}
