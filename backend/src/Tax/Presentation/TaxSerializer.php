<?php

declare(strict_types=1);

namespace App\Tax\Presentation;

use App\Tax\Domain\TaxItem;

final class TaxSerializer
{
    /**
     * @param TaxItem[] $items
     *
     * @return array<int, array<string, mixed>>
     */
    public function serializeList(array $items, bool $withCustomer): array
    {
        return array_map(fn (TaxItem $t): array => $this->serialize($t, $withCustomer), $items);
    }

    /** @return array<string, mixed> */
    public function serialize(TaxItem $t, bool $withCustomer): array
    {
        $data = [
            'id' => (string) $t->id(),
            'vehicleId' => $t->vehicle() !== null ? (string) $t->vehicle()->id() : null,
            'vehiclePlate' => $t->vehicle()?->plateNumber(),
            'year' => $t->year(),
            'type' => $t->type()->value,
            'typeLabel' => $t->type()->label(),
            'amount' => round($t->amountBani() / 100, 2),
            'dueDate' => $t->dueDate()?->format('Y-m-d'),
            'status' => $t->effectiveStatus()->value,
            'statusLabel' => $t->effectiveStatus()->label(),
            'paidAmount' => $t->paidAmountBani() !== null ? round($t->paidAmountBani() / 100, 2) : null,
            'paidAt' => $t->paidAt()?->format(DATE_ATOM),
            'note' => $t->note(),
            'createdAt' => $t->createdAt()->format(DATE_ATOM),
        ];

        if ($withCustomer) {
            $customer = $t->customer();
            $data['customerName'] = $customer->customerProfile()?->fullName() ?: $customer->getUserIdentifier();
        }

        return $data;
    }
}
