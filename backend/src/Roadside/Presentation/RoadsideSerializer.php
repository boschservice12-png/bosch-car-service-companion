<?php

declare(strict_types=1);

namespace App\Roadside\Presentation;

use App\Document\Domain\Document;
use App\Roadside\Domain\RoadsideRequest;

final class RoadsideSerializer
{
    /**
     * @param RoadsideRequest[] $requests
     *
     * @return array<int, array<string, mixed>>
     */
    public function serializeList(array $requests, bool $withCustomer): array
    {
        return array_map(fn (RoadsideRequest $r): array => $this->serialize($r, $withCustomer), $requests);
    }

    /** @return array<string, mixed> */
    public function serialize(RoadsideRequest $r, bool $withCustomer): array
    {
        $data = [
            'id' => (string) $r->id(),
            'vehicleId' => $r->vehicle() !== null ? (string) $r->vehicle()->id() : null,
            'vehiclePlate' => $r->vehicle()?->plateNumber(),
            'location' => $r->location(),
            'problem' => $r->problem(),
            'mobility' => $r->mobility()->value,
            'mobilityLabel' => $r->mobility()->label(),
            'safety' => $r->safety()->value,
            'safetyLabel' => $r->safety()->label(),
            'phone' => $r->phone(),
            'status' => $r->status()->value,
            'statusLabel' => $r->status()->label(),
            'note' => $r->note(),
            'createdAt' => $r->createdAt()->format(DATE_ATOM),
            'documents' => array_map($this->serializeDocument(...), $r->documents()),
        ];

        if ($withCustomer) {
            $customer = $r->customer();
            $data['customerName'] = $customer->customerProfile()?->fullName() ?: $customer->getUserIdentifier();
        }

        return $data;
    }

    /** @return array<string, mixed> */
    private function serializeDocument(Document $document): array
    {
        return [
            'id' => (string) $document->id(),
            'originalName' => $document->originalName(),
            'mimeType' => $document->mimeType(),
            'sizeBytes' => $document->sizeBytes(),
            'scanStatus' => $document->scanStatus()->value,
            'servable' => $document->isServable(),
        ];
    }
}
