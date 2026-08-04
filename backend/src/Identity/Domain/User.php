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

    /**
     * Coduri de rezervă 2FA — DOAR hash-uri (password_hash); fiecare cod e
     * de unică folosință. Textul în clar se afișează o singură dată, la înrolare.
     *
     * @var list<string>|null
     */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $totpRecoveryCodes = null;

    /**
     * Ultimul pas de timp TOTP acceptat (contorul RFC 6238 = time/30). Un cod
     * dintr-un pas deja folosit sau mai vechi NU se mai acceptă (anti-replay).
     * Reținem pasul, nu hash-ul codului: aceeași valoare TOTP reapare ciclic.
     */
    #[ORM\Column(type: 'bigint', nullable: true)]
    private ?int $lastTotpStep = null;

    #[ORM\Column]
    private bool $isActive = true;

    /** P1-06: cererea de ștergere GDPR — anonimizare după perioada de grație. */
    #[ORM\Column(type: 'datetimetz_immutable', nullable: true)]
    private ?\DateTimeImmutable $deletionRequestedAt = null;

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

    public function deletionRequestedAt(): ?\DateTimeImmutable
    {
        return $this->deletionRequestedAt;
    }

    /** Cerere de ștergere: contul se blochează imediat; datele se anonimizează după grație. */
    public function requestDeletion(): void
    {
        $this->deletionRequestedAt = new \DateTimeImmutable();
        $this->isActive = false;
    }

    /** Răzgândire în perioada de grație (prin operator). */
    public function cancelDeletion(): void
    {
        $this->deletionRequestedAt = null;
        $this->isActive = true;
    }

    /**
     * P1-06: anonimizare ireversibilă — identitatea dispare, dar înregistrările
     * operaționale ale service-ului (vehicule, istoric) rămân, nelegate de o
     * persoană identificabilă. Contul nu se mai poate folosi sau revendica.
     */
    public function anonymize(): void
    {
        $this->email = sprintf('sters-%s@anonim.local', substr((string) $this->id, 0, 13));
        $this->passwordHash = '';
        $this->phone = null;
        $this->isActive = false;
        $this->disableTotp();
    }

    public function isAnonymized(): bool
    {
        return str_ends_with($this->email, '@anonim.local');
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

    public function totpSecret(): ?string
    {
        return $this->totpSecret;
    }

    /** Secret în așteptare (înrolare începută); 2FA devine activ abia la confirmare. */
    public function setPendingTotpSecret(string $secret): void
    {
        if ($this->totpEnabled) {
            throw new \DomainException('2FA este deja activ — dezactivați-l înainte de o nouă înrolare.');
        }
        $this->totpSecret = $secret;
    }

    /** @param list<string> $recoveryCodeHashes */
    public function enableTotp(array $recoveryCodeHashes): void
    {
        if ($this->totpSecret === null) {
            throw new \LogicException('Nu există un secret TOTP în așteptare.');
        }
        $this->totpEnabled = true;
        $this->totpRecoveryCodes = $recoveryCodeHashes;
    }

    public function disableTotp(): void
    {
        $this->totpSecret = null;
        $this->totpEnabled = false;
        $this->totpRecoveryCodes = null;
        $this->lastTotpStep = null;
    }

    public function lastTotpStep(): ?int
    {
        return $this->lastTotpStep;
    }

    /**
     * Marchează un pas TOTP drept consumat în entitatea încărcată. Sursa de
     * adevăr pentru concurență este UPDATE-ul condițional atomic din
     * TotpReplayGuard; aici doar ținem entitatea sincronă după acceptare.
     */
    public function recordTotpStep(int $step): void
    {
        $this->lastTotpStep = $step;
    }

    /**
     * Consumă un cod de rezervă (de unică folosință): la potrivire, hash-ul
     * este eliminat definitiv din listă.
     */
    public function consumeRecoveryCode(string $plainCode): bool
    {
        foreach ($this->totpRecoveryCodes ?? [] as $index => $hash) {
            if (password_verify($plainCode, $hash)) {
                $remaining = $this->totpRecoveryCodes;
                unset($remaining[$index]);
                $this->totpRecoveryCodes = array_values($remaining);

                return true;
            }
        }

        return false;
    }

    public function remainingRecoveryCodes(): int
    {
        return \count($this->totpRecoveryCodes ?? []);
    }

    #[\Deprecated]
    public function eraseCredentials(): void
    {
        // Nu stocăm credențiale temporare în clar (nimic de șters).
    }
}
