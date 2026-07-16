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
        $vehicle = new Vehicle(new Vin($req->vin), $req->plateNumber);
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
