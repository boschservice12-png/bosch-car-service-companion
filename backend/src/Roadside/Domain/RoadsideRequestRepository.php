<?php

declare(strict_types=1);

namespace App\Roadside\Domain;

use App\Identity\Domain\User;
use Symfony\Component\Uid\Uuid;

interface RoadsideRequestRepository
{
    public function save(RoadsideRequest $request): void;

    public function get(Uuid $id): ?RoadsideRequest;

    /** @return RoadsideRequest[] */
    public function findForCustomer(User $customer): array;

    /** @return RoadsideRequest[] */
    public function findAllForAdmin(): array;
}
