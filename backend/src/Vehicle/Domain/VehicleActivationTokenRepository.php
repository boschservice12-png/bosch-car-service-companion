<?php

declare(strict_types=1);

namespace App\Vehicle\Domain;

interface VehicleActivationTokenRepository
{
    public function save(VehicleActivationToken $token): void;

    public function findByHash(string $tokenHash): ?VehicleActivationToken;

    /** @return VehicleActivationToken[] Tokenurile încă valabile ale unui vehicul. */
    public function findLiveForVehicle(Vehicle $vehicle, \DateTimeImmutable $now): array;
}
