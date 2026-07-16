<?php

declare(strict_types=1);

namespace App\Mobility\Domain;

use App\Identity\Domain\User;
use Symfony\Component\Uid\Uuid;

interface MobilityRequestRepository
{
    public function save(MobilityRequest $request): void;

    public function get(Uuid $id): ?MobilityRequest;

    /** @return MobilityRequest[] */
    public function findForCustomer(User $customer): array;

    /** @return MobilityRequest[] */
    public function findAllForAdmin(): array;
}
