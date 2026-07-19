<?php

declare(strict_types=1);

namespace App\Vehicle\Application;

use App\Audit\Application\AuditRecorder;
use App\Customer\Domain\CustomerProfile;
use App\Vehicle\Domain\Vehicle;
use App\Vehicle\Domain\VehicleRepository;
use App\Vehicle\Domain\Vin;
use App\Vehicle\Presentation\Dto\CreateVehicleRequest;
use App\Vehicle\Presentation\Dto\UpdateVehicleRequest;

final class VehicleService
{
    public function __construct(
        private readonly VehicleRepository $vehicles,
        private readonly AuditRecorder $audit,
    ) {
    }

    /**
     * Creează un vehicul și îl atribuie clientului curent ca proprietar activ.
     * VIN-ul este validat/normalizat prin value object (InvalidArgumentException
     * → tratată de listener ca 422).
     */
    public function create(CreateVehicleRequest $req, CustomerProfile $owner): Vehicle
    {
        $vin = new Vin($req->vin);
        // Duplicat de business: același VIN activ nu poate exista de două ori
        // (backstop la nivel de bază: ux_vehicles_vin_active). → 409 Conflict.
        if ($this->vehicles->findActiveByVin($vin->value()) !== null) {
            throw new \DomainException('Există deja un vehicul înregistrat cu acest VIN. Dacă vehiculul vă aparține, contactați service-ul.');
        }

        $vehicle = new Vehicle($vin, $req->plateNumber);
        $vehicle->updateDetails($req->make, $req->model, $req->year);

        $this->vehicles->save($vehicle);
        $this->vehicles->assignOwner($vehicle, $owner);

        $this->audit->record('vehicle.created', 'Vehicle', (string) $vehicle->id(), null, [
            'vin' => $vehicle->vin(),
            'plateNumber' => $vehicle->plateNumber(),
        ]);

        return $vehicle;
    }

    public function update(Vehicle $vehicle, UpdateVehicleRequest $req): Vehicle
    {
        $before = [
            'plateNumber' => $vehicle->plateNumber(),
            'make' => $vehicle->make(),
            'model' => $vehicle->model(),
            'year' => $vehicle->year(),
        ];

        $vehicle->updateDetails(
            $req->make ?? $vehicle->make(),
            $req->model ?? $vehicle->model(),
            $req->year ?? $vehicle->year(),
        );

        if ($req->plateNumber !== null && $req->plateNumber !== '') {
            $vehicle->changePlateNumber($req->plateNumber);
        }

        $this->vehicles->save($vehicle);

        $this->audit->record('vehicle.updated', 'Vehicle', (string) $vehicle->id(), $before, [
            'plateNumber' => $vehicle->plateNumber(),
            'make' => $vehicle->make(),
            'model' => $vehicle->model(),
            'year' => $vehicle->year(),
        ]);

        return $vehicle;
    }
}
