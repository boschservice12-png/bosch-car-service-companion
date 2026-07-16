<?php

declare(strict_types=1);

namespace App\DamageClaim\Domain;

use App\Identity\Domain\User;
use Symfony\Component\Uid\Uuid;

interface DamageClaimRepository
{
    public function save(DamageClaim $claim): void;

    public function get(Uuid $id): ?DamageClaim;

    /** @return DamageClaim[] */
    public function findForCustomer(User $customer): array;

    /** @return DamageClaim[] */
    public function findAllForAdmin(): array;
}
