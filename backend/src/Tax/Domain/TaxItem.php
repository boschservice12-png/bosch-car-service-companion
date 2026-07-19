<?php

declare(strict_types=1);

namespace App\Tax\Domain;

use App\Identity\Domain\User;
use App\Vehicle\Domain\Vehicle;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * O taxă/impozit anual asociat unui client (și, de regulă, unui vehicul): an, tip,
 * sumă, scadență și stare de plată. Aparține clientului (autorizare la nivel de
 * obiect); atât clientul, cât și service-ul pot urmări starea de plată. Nu se
 * încarcă niciun fișier (bon fiscal etc.) — evidența este pur declarativă.
 * Suma este stocată în bani (întreg) pentru precizie și portabilitate.
 */
#[ORM\Entity]
#[ORM\Table(name: 'tax_items')]
#[ORM\Index(name: 'ix_tax_customer', columns: ['customer_id'])]
#[ORM\Index(name: 'ix_tax_status', columns: ['status'])]
class TaxItem
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'customer_id', nullable: false)]
    private User $customer;

    #[ORM\ManyToOne(targetEntity: Vehicle::class)]
    #[ORM\JoinColumn(name: 'vehicle_id', nullable: true, onDelete: 'SET NULL')]
    private ?Vehicle $vehicle;

    #[ORM\Column]
    private int $year;

    #[ORM\Column(length: 20, enumType: TaxType::class)]
    private TaxType $type;

    /** Suma în bani (RON * 100). */
    #[ORM\Column]
    private int $amountBani;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $dueDate;

    #[ORM\Column(length: 16, enumType: PaymentStatus::class)]
    private PaymentStatus $status = PaymentStatus::UNPAID;

    #[ORM\Column(type: 'datetimetz_immutable', nullable: true)]
    private ?\DateTimeImmutable $paidAt = null;

    /** Suma plătită până acum, în bani (null = nicio plată înregistrată). */
    #[ORM\Column(nullable: true)]
    private ?int $paidAmountBani = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $note = null;

    #[ORM\Column(type: 'datetimetz_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetimetz_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        User $customer,
        ?Vehicle $vehicle,
        int $year,
        TaxType $type,
        int $amountBani,
        ?\DateTimeImmutable $dueDate,
    ) {
        $this->id = Uuid::v7();
        $this->customer = $customer;
        $this->vehicle = $vehicle;
        $this->year = $year;
        $this->type = $type;
        $this->amountBani = $amountBani;
        $this->dueDate = $dueDate;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function customer(): User
    {
        return $this->customer;
    }

    public function vehicle(): ?Vehicle
    {
        return $this->vehicle;
    }

    public function year(): int
    {
        return $this->year;
    }

    public function type(): TaxType
    {
        return $this->type;
    }

    public function amountBani(): int
    {
        return $this->amountBani;
    }

    public function dueDate(): ?\DateTimeImmutable
    {
        return $this->dueDate;
    }

    public function status(): PaymentStatus
    {
        return $this->status;
    }

    public function paidAt(): ?\DateTimeImmutable
    {
        return $this->paidAt;
    }

    public function note(): ?string
    {
        return $this->note;
    }

    public function isPaid(): bool
    {
        return $this->status === PaymentStatus::PAID;
    }

    public function paidAmountBani(): ?int
    {
        return $this->paidAmountBani;
    }

    /** Plată integrală. */
    public function markPaid(): void
    {
        $this->status = PaymentStatus::PAID;
        $this->paidAmountBani = $this->amountBani;
        $this->paidAt = new \DateTimeImmutable();
        $this->touch();
    }

    /**
     * Înregistrează o plată (parțială sau integrală). Suma se acumulează;
     * la atingerea totalului starea devine PAID, altfel PARTIALLY_PAID.
     */
    public function registerPayment(int $amountBani): void
    {
        if ($amountBani <= 0) {
            throw new \InvalidArgumentException('Suma plătită trebuie să fie pozitivă.');
        }
        $this->paidAmountBani = min($this->amountBani, ($this->paidAmountBani ?? 0) + $amountBani);
        $this->paidAt = new \DateTimeImmutable();
        $this->status = $this->paidAmountBani >= $this->amountBani
            ? PaymentStatus::PAID
            : PaymentStatus::PARTIALLY_PAID;
        $this->touch();
    }

    /**
     * Editarea datelor de bază de către proprietar (an, tip, sumă, scadență,
     * vehicul). Dacă există plăți înregistrate, starea se recalculează față de
     * noua sumă (plata acumulată se plafonează la total).
     */
    public function updateDetails(?Vehicle $vehicle, int $year, TaxType $type, int $amountBani, ?\DateTimeImmutable $dueDate): void
    {
        if ($amountBani < 0) {
            throw new \InvalidArgumentException('Suma nu poate fi negativă.');
        }
        $this->vehicle = $vehicle;
        $this->year = $year;
        $this->type = $type;
        $this->amountBani = $amountBani;
        $this->dueDate = $dueDate;
        if ($this->paidAmountBani !== null) {
            $this->paidAmountBani = min($this->paidAmountBani, $amountBani);
            $this->status = $this->paidAmountBani >= $amountBani
                ? PaymentStatus::PAID
                : PaymentStatus::PARTIALLY_PAID;
        }
        $this->touch();
    }

    public function markUnpaid(): void
    {
        $this->status = PaymentStatus::UNPAID;
        $this->paidAmountBani = null;
        $this->paidAt = null;
        $this->touch();
    }

    /**
     * Starea afișată: OVERDUE când scadența a trecut fără plată integrală
     * (starea stocată rămâne UNPAID/PARTIALLY_PAID — OVERDUE e derivată).
     */
    public function effectiveStatus(?\DateTimeImmutable $now = null): PaymentStatus
    {
        if ($this->status === PaymentStatus::PAID) {
            return PaymentStatus::PAID;
        }
        $now ??= new \DateTimeImmutable();
        if ($this->dueDate !== null && $this->dueDate < $now) {
            return PaymentStatus::OVERDUE;
        }

        return $this->status;
    }

    public function setNote(?string $note): void
    {
        $this->note = $note;
        $this->touch();
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
