<?php

declare(strict_types=1);

namespace App\ServiceHistory\Presentation;

use App\Document\Domain\Document;
use App\ServiceHistory\Domain\ServiceRecord;

/**
 * Serializează înregistrări de service pentru API. Calculează, pentru o listă,
 * care înregistrări au fost corectate (sunt referite ca `correctionOf` de altele),
 * astfel încât atât originalul, cât și corecția rămân vizibile și marcate.
 */
final class ServiceRecordSerializer
{
    /**
     * @param ServiceRecord[] $records
     *
     * @return array<int, array<string, mixed>>
     */
    public function serializeList(array $records): array
    {
        $correctedIds = $this->correctedIds($records);

        return array_map(fn (ServiceRecord $r): array => $this->serialize($r, $correctedIds), $records);
    }

    /**
     * @param string[] $correctedIds
     *
     * @return array<string, mixed>
     */
    public function serialize(ServiceRecord $record, array $correctedIds = []): array
    {
        return [
            'id' => (string) $record->id(),
            'vehicleId' => (string) $record->vehicle()->id(),
            'status' => $record->status()->value,
            'statusLabel' => $record->status()->label(),
            'serviceDate' => $record->serviceDate()?->format('Y-m-d'),
            'odometerKm' => $record->odometerKm(),
            'workType' => $record->workType(),
            'workDescription' => $record->workDescription(),
            'partsSummary' => $record->partsSummary(),
            'laborCost' => round($record->laborBani() / 100, 2),
            'totalAmount' => round($record->totalBani() / 100, 2),
            'warranty' => $record->warranty(),
            'correctionOfId' => $record->correctionOf() !== null ? (string) $record->correctionOf()->id() : null,
            'corrected' => \in_array((string) $record->id(), $correctedIds, true),
            'publishedAt' => $record->publishedAt()?->format(DATE_ATOM),
            'createdAt' => $record->createdAt()->format(DATE_ATOM),
            'documents' => array_map($this->serializeDocument(...), $record->documents()),
        ];
    }

    /**
     * @param ServiceRecord[] $records
     *
     * @return string[]
     */
    private function correctedIds(array $records): array
    {
        $ids = [];
        foreach ($records as $r) {
            $target = $r->correctionOf();
            if ($target !== null) {
                $ids[] = (string) $target->id();
            }
        }

        return array_values(array_unique($ids));
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
