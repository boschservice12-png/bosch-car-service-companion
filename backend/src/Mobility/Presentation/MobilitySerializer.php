<?php

declare(strict_types=1);

namespace App\Mobility\Presentation;

use App\Mobility\Domain\MobilityRequest;

final class MobilitySerializer
{
    /**
     * @param MobilityRequest[] $requests
     *
     * @return array<int, array<string, mixed>>
     */
    public function serializeList(array $requests, bool $withCustomer): array
    {
        return array_map(fn (MobilityRequest $r): array => $this->serialize($r, $withCustomer), $requests);
    }

    /** @return array<string, mixed> */
    public function serialize(MobilityRequest $r, bool $withCustomer): array
    {
        $data = [
            'id' => (string) $r->id(),
            'vehicleId' => $r->vehicle() !== null ? (string) $r->vehicle()->id() : null,
            'vehiclePlate' => $r->vehicle()?->plateNumber(),
            'type' => $r->type()->value,
            'typeLabel' => $r->type()->label(),
            'details' => $r->details(),
            'preferredDate' => $r->preferredDate()?->format('Y-m-d'),
            'status' => $r->status()->value,
            'statusLabel' => $r->status()->label(),
            'note' => $r->note(),
            'createdAt' => $r->createdAt()->format(DATE_ATOM),
        ];

        if ($withCustomer) {
            $customer = $r->customer();
            $data['customerName'] = $customer->customerProfile()?->fullName() ?: $customer->getUserIdentifier();
        }

        return $data;
    }
}
