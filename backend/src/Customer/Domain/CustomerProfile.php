<?php

declare(strict_types=1);

namespace App\Customer\Domain;

use App\Identity\Domain\User;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'customer_profiles')]
class CustomerProfile
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\OneToOne(inversedBy: 'customerProfile', targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $firstName = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $lastName = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $address = null;

    #[ORM\Column(type: 'datetimetz_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(User $user, ?string $firstName = null, ?string $lastName = null)
    {
        $this->id = Uuid::v7();
        $this->user = $user;
        $this->firstName = $firstName;
        $this->lastName = $lastName;
        $this->createdAt = new \DateTimeImmutable();
        $user->attachCustomerProfile($this);
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function user(): User
    {
        return $this->user;
    }

    public function fullName(): string
    {
        return trim(sprintf('%s %s', $this->firstName ?? '', $this->lastName ?? ''));
    }

    public function firstName(): ?string
    {
        return $this->firstName;
    }

    public function lastName(): ?string
    {
        return $this->lastName;
    }

    public function phone(): ?string
    {
        return $this->phone;
    }

    public function address(): ?string
    {
        return $this->address;
    }

    public function updateContact(?string $phone, ?string $address): void
    {
        $this->phone = $phone;
        $this->address = $address;
    }

    /** P1-06: anonimizare ireversibilă la purjarea GDPR. */
    public function anonymize(): void
    {
        $this->firstName = 'Cont';
        $this->lastName = 'Șters';
        $this->phone = null;
        $this->address = null;
    }
}
