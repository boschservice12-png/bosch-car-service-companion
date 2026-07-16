<?php

declare(strict_types=1);

namespace App\ServiceHistory\Domain;

use App\Vehicle\Domain\Vehicle;
use Symfony\Component\Uid\Uuid;

interface ServiceRecordRepository
{
    public function save(ServiceRecord $record): void;

    public function get(Uuid $id): ?ServiceRecord;

    /**
     * Înregistrările unui vehicul, în ordine cronologică descrescătoare.
     *
     * @return ServiceRecord[]
     */
    public function findForVehicle(Vehicle $vehicle, bool $includeDrafts): array;
}
