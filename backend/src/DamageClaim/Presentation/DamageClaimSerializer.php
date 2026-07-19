<?php

declare(strict_types=1);

namespace App\DamageClaim\Presentation;

use App\DamageClaim\Domain\DamageClaim;
use App\Document\Domain\Document;

final class DamageClaimSerializer
{
    /**
     * @param DamageClaim[] $claims
     *
     * @return array<int, array<string, mixed>>
     */
    public function serializeList(array $claims, bool $withCustomer): array
    {
        return array_map(fn (DamageClaim $c): array => $this->serialize($c, $withCustomer), $claims);
    }

    /** @return array<string, mixed> */
    public function serialize(DamageClaim $c, bool $withCustomer): array
    {
        $data = [
            'id' => (string) $c->id(),
            'vehicleId' => $c->vehicle() !== null ? (string) $c->vehicle()->id() : null,
            'vehiclePlate' => $c->vehicle()?->plateNumber(),
            'incidentDate' => $c->incidentDate()?->format('Y-m-d'),
            'incidentLocation' => $c->incidentLocation(),
            'incidentDescription' => $c->incidentDescription(),
            'insurer' => $c->insurer(),
            'policyNumber' => $c->policyNumber(),
            'status' => $c->status()->value,
            'statusLabel' => $c->status()->label(),
            'note' => $c->note(),
            'missingDocuments' => $c->missingDocuments(),
            'createdAt' => $c->createdAt()->format(DATE_ATOM),
            'documents' => array_map($this->serializeDocument(...), $c->documents()),
        ];

        if ($withCustomer) {
            $customer = $c->customer();
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
