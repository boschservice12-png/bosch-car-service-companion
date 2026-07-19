<?php

declare(strict_types=1);

namespace App\Identity\Domain;

use App\Customer\Domain\CustomerProfile;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\EquatableInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'users')]
#[UniqueEntity(fields: ['email'], message: 'Există deja un cont cu acest email.')]
class User implements UserInterface, PasswordAuthenticatedUserInterface, EquatableInterface
{
    public const ROLE_CLIENT = 'ROLE_USER';
    public const ROLE_SERVICE_ADMIN = 'ROLE_SERVICE_ADMIN';

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\Column(length: 255, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Email]
    private string $email;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(length: 255)]
    private string $passwordHash = '';

    /** ROLE_USER (client) sau ROLE_SERVICE_ADMIN. */
    #[ORM\Column(length: 32)]
    private string $role = self::ROLE_CLIENT;

    #[ORM\Column(nullable: true)]
    private ?string $totpSecret = null;

    #[ORM\Column]
    private bool $totpEnabled = false;

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column(type: 'datetimetz_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\OneToOne(mappedBy: 'user', targetEntity: CustomerProfile::class, cascade: ['persist'])]
    private ?CustomerProfile $customerProfile = null;

    public function __construct(string $email, string $role = self::ROLE_CLIENT)
    {
        $this->id = Uuid::v7();
        $this->email = $email;
        $this->role = $role;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    /** @return string[] */
    public function getRoles(): array
    {
        return array_values(array_unique([$this->role, self::ROLE_CLIENT]));
    }

    public function getPassword(): string
    {
        return $this->passwordHash;
    }

    public function setPasswordHash(string $hash): void
    {
        $this->passwordHash = $hash;
    }

    public function isServiceAdmin(): bool
    {
        return $this->role === self::ROLE_SERVICE_ADMIN;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function deactivate(): void
    {
        $this->isActive = false;
    }

    public function activate(): void
    {
        $this->isActive = true;
    }

    /**
     * P0-07: dacă utilizatorul reîncărcat din bază este dezactivat (sau
     * identitatea diferă), sesiunea existentă devine invalidă la următoarea
     * cerere — un cont blocat își pierde accesul imediat, nu doar la re-login.
     */
    public function isEqualTo(UserInterface $user): bool
    {
        return $user instanceof self
            && $user->getUserIdentifier() === $this->getUserIdentifier()
            && $user->isActive()
            && $this->isActive;
    }

    public function customerProfile(): ?CustomerProfile
    {
        return $this->customerProfile;
    }

    public function attachCustomerProfile(CustomerProfile $profile): void
    {
        $this->customerProfile = $profile;
    }

    public function totpEnabled(): bool
    {
        return $this->totpEnabled;
    }

    #[\Deprecated]
    public function eraseCredentials(): void
    {
        // Nu stocăm credențiale temporare în clar (nimic de șters).
    }
}
