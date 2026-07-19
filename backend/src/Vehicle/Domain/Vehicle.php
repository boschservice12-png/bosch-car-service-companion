<?php

declare(strict_types=1);

namespace App\Vehicle\Domain;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'vehicles')]
#[ORM\Index(name: 'ix_vehicles_vin', columns: ['vin'])]
#[ORM\UniqueConstraint(name: 'ux_vehicles_vin_active', columns: ['vin'], options: ['where' => '(deleted_at IS NULL)'])]
class Vehicle
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    /** VIN normalizat (uppercase, 17 caractere) — validat prin value object Vin. */
    #[ORM\Column(length: 17)]
    private string $vin;

    #[ORM\Column(length: 16)]
    private string $plateNumber;

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $make = null;

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $model = null;

    #[ORM\Column(nullable: true)]
    private ?int $year = null;

    #[ORM\Column(type: 'datetimetz_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetimetz_immutable')]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(type: 'datetimetz_immutable', nullable: true)]
    private ?\DateTimeImmutable $deletedAt = null;

    public function __construct(Vin $vin, string $plateNumber)
    {
        $this->id = Uuid::v7();
        $this->vin = $vin->value();
        $this->plateNumber = strtoupper(trim($plateNumber));
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function vin(): string
    {
        return $this->vin;
    }

    public function plateNumber(): string
    {
        return $this->plateNumber;
    }

    public function make(): ?string
    {
        return $this->make;
    }

    public function model(): ?string
    {
        return $this->model;
    }

    public function year(): ?int
    {
        return $this->year;
    }

    public function updateDetails(?string $make, ?string $model, ?int $year): void
    {
        $this->make = $make;
        $this->model = $model;
        $this->year = $year;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function changeVin(Vin $vin): void
    {
        $this->vin = $vin->value();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function changePlateNumber(string $plateNumber): void
    {
        $this->plateNumber = strtoupper(trim($plateNumber));
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function isDeleted(): bool
    {
        return $this->deletedAt !== null;
    }
}
