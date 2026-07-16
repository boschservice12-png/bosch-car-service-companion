<?php

declare(strict_types=1);

namespace App\Tax\Domain;

use App\Identity\Domain\User;
use Symfony\Component\Uid\Uuid;

interface TaxItemRepository
{
    public function save(TaxItem $item): void;

    public function get(Uuid $id): ?TaxItem;

    /** @return TaxItem[] */
    public function findForCustomer(User $customer): array;

    /** @return TaxItem[] */
    public function findAllForAdmin(): array;
}
