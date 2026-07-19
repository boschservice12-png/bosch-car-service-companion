<?php

declare(strict_types=1);

namespace App\QuoteRequest\Presentation;

use App\QuoteRequest\Domain\QuoteRequest;
use App\QuoteRequest\Domain\QuoteResponse;

final class QuoteRequestSerializer
{
    /**
     * @param QuoteRequest[] $requests
     *
     * @return array<int, array<string, mixed>>
     */
    public function serializeList(array $requests, bool $withCustomer): array
    {
        return array_map(fn (QuoteRequest $q): array => $this->serialize($q, $withCustomer), $requests);
    }

    /** @return array<string, mixed> */
    public function serialize(QuoteRequest $q, bool $withCustomer): array
    {
        $data = [
            'id' => (string) $q->id(),
            'vehicleId' => $q->vehicle() !== null ? (string) $q->vehicle()->id() : null,
            'vehiclePlate' => $q->vehicle()?->plateNumber(),
            'mileage' => $q->mileage(),
            'symptomDescription' => $q->symptomDescription(),
            'occurrenceConditions' => $q->occurrenceConditions(),
            'vehicleDrivable' => $q->vehicleDrivable(),
            'warningLights' => $q->warningLights(),
            'preferredContactMethod' => $q->preferredContactMethod(),
            'preferredInterval' => $q->preferredInterval(),
            'status' => $q->status()->value,
            'statusLabel' => $q->status()->label(),
            'responses' => array_map($this->serializeResponse(...), $q->responses()),
            'createdAt' => $q->createdAt()->format(DATE_ATOM),
            'updatedAt' => $q->updatedAt()->format(DATE_ATOM),
        ];

        if ($withCustomer) {
            $customer = $q->customer();
            $data['customerName'] = $customer->customerProfile()?->fullName() ?: $customer->getUserIdentifier();
        }

        return $data;
    }

    /** @return array<string, mixed> */
    private function serializeResponse(QuoteResponse $r): array
    {
        $document = $r->document();

        return [
            'id' => (string) $r->id(),
            'message' => $r->message(),
            'document' => $document !== null ? [
                'id' => (string) $document->id(),
                'originalName' => $document->originalName(),
                'mimeType' => $document->mimeType(),
                'servable' => $document->isServable(),
            ] : null,
            'createdAt' => $r->createdAt()->format(DATE_ATOM),
        ];
    }
}
