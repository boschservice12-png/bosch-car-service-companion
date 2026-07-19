<?php

declare(strict_types=1);

namespace App\QuoteRequest\Domain;

use App\Identity\Domain\User;
use Symfony\Component\Uid\Uuid;

interface QuoteRequestRepository
{
    public function save(QuoteRequest $request): void;

    public function get(Uuid $id): ?QuoteRequest;

    /** @return QuoteRequest[] */
    public function findForCustomer(User $customer): array;

    /** @return QuoteRequest[] Cererile vizibile service-ului (fără ciorne). */
    public function findAllForAdmin(): array;
}
