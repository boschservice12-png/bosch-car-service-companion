<?php

declare(strict_types=1);

namespace App\ServiceHistory\Application;

use App\Audit\Application\AuditRecorder;
use App\Document\Domain\Document;
use App\Identity\Domain\User;
use App\ServiceHistory\Domain\ServiceRecord;
use App\ServiceHistory\Domain\ServiceRecordRepository;
use App\ServiceHistory\Domain\ServiceRecordStatus;
use App\ServiceHistory\Presentation\Dto\ServiceRecordRequest;
use App\Shared\Presentation\ValidationFailedException;
use App\Vehicle\Domain\Vehicle;

final class ServiceRecordService
{
    public function __construct(
        private readonly ServiceRecordRepository $records,
        private readonly AuditRecorder $audit,
    ) {
    }

    public function create(Vehicle $vehicle, ?User $admin, ServiceRecordRequest $dto): ServiceRecord
    {
        $record = new ServiceRecord($vehicle, $admin);
        $this->applyRequest($record, $dto);
        $this->records->save($record);

        $this->audit->record('service_record.created', 'ServiceRecord', (string) $record->id(), null, [
            'vehicleId' => (string) $vehicle->id(),
            'status' => $record->status()->value,
        ]);

        return $record;
    }

    /**
     * Actualizează o ciornă. O înregistrare PUBLICATĂ nu poate fi rescrisă —
     * pentru a o modifica se creează o corecție (createCorrection).
     */
    public function update(ServiceRecord $record, ServiceRecordRequest $dto): ServiceRecord
    {
        if ($record->isPublished()) {
            throw ValidationFailedException::fromArray([
                'status' => ['O înregistrare publicată nu poate fi modificată. Creați o corecție.'],
            ]);
        }

        $before = $this->snapshot($record);
        $this->applyRequest($record, $dto);
        $this->records->save($record);

        $this->audit->record('service_record.updated', 'ServiceRecord', (string) $record->id(), $before, $this->snapshot($record));

        return $record;
    }

    public function publish(ServiceRecord $record): ServiceRecord
    {
        if ($record->isPublished()) {
            throw ValidationFailedException::fromArray(['status' => ['Înregistrarea este deja publicată.']]);
        }

        $missing = $record->missingForPublish();
        if ($missing !== []) {
            throw ValidationFailedException::fromArray([
                'publish' => ['Completați câmpurile obligatorii înainte de publicare: '.implode(', ', $missing).'.'],
            ]);
        }

        $record->publish();
        $this->records->save($record);

        $this->audit->record('service_record.published', 'ServiceRecord', (string) $record->id(), null, [
            'publishedAt' => $record->publishedAt()?->format(DATE_ATOM),
        ]);

        // Publicarea unei corecții marchează originalul drept CORRECTED (specificație).
        $original = $record->correctionOf();
        if ($original !== null && $original->status() === ServiceRecordStatus::PUBLISHED) {
            $original->markCorrected();
            $this->records->save($original);
            $this->audit->record('service_record.marked_corrected', 'ServiceRecord', (string) $original->id(), null, [
                'correctedBy' => (string) $record->id(),
                'reason' => $record->correctionReason(),
            ]);
        }

        return $record;
    }

    /**
     * Creează o corecție a unei înregistrări publicate: o ciornă nouă care referă
     * originalul, pornind de la valorile acestuia. Originalul rămâne neschimbat și
     * vizibil; corecția devine o intrare separată după publicare.
     */
    public function createCorrection(ServiceRecord $original, ?User $admin, string $reason): ServiceRecord
    {
        if (!$original->isPublished()) {
            throw ValidationFailedException::fromArray([
                'correction' => ['Doar o înregistrare publicată poate fi corectată.'],
            ]);
        }
        if (trim($reason) === '') {
            throw ValidationFailedException::fromArray([
                'reason' => ['Motivul corecției este obligatoriu.'],
            ]);
        }

        $correction = new ServiceRecord($original->vehicle(), $admin, $original, trim($reason));
        $correction->applyDetails(
            $original->serviceDate(),
            $original->odometerKm(),
            $original->workType(),
            $original->workDescription(),
            $original->partsSummary(),
            $original->laborBani(),
            $original->totalBani(),
            $original->warranty(),
        );
        $this->records->save($correction);

        $this->audit->record('service_record.correction_created', 'ServiceRecord', (string) $correction->id(), null, [
            'correctionOf' => (string) $original->id(),
            'reason' => trim($reason),
        ]);

        return $correction;
    }

    public function attachDocument(ServiceRecord $record, Document $document): ServiceRecord
    {
        if ($record->isPublished()) {
            throw ValidationFailedException::fromArray([
                'status' => ['Nu se pot atașa documente la o înregistrare publicată. Creați o corecție.'],
            ]);
        }

        $record->attachDocument($document);
        $this->records->save($record);

        $this->audit->record('service_record.document_attached', 'ServiceRecord', (string) $record->id(), null, [
            'documentId' => (string) $document->id(),
        ]);

        return $record;
    }

    private function applyRequest(ServiceRecord $record, ServiceRecordRequest $dto): void
    {
        $serviceDate = $dto->serviceDate !== null ? $this->parseDate($dto->serviceDate) : $record->serviceDate();
        $laborBani = $dto->laborCost !== null ? (int) round($dto->laborCost * 100) : $record->laborBani();
        $totalBani = $dto->totalAmount !== null ? (int) round($dto->totalAmount * 100) : $record->totalBani();

        $record->applyDetails(
            $serviceDate,
            $dto->odometerKm ?? $record->odometerKm(),
            $dto->workType ?? $record->workType(),
            $dto->workDescription ?? $record->workDescription(),
            $dto->partsSummary ?? $record->partsSummary(),
            $laborBani,
            $totalBani,
            $dto->warranty ?? $record->warranty(),
        );
    }

    private function parseDate(string $value): ?\DateTimeImmutable
    {
        if ($value === '') {
            return null;
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if ($date === false) {
            throw ValidationFailedException::fromArray(['serviceDate' => ['Dată invalidă (format așteptat: AAAA-LL-ZZ).']]);
        }

        return $date;
    }

    /** @return array<string, mixed> */
    private function snapshot(ServiceRecord $record): array
    {
        return [
            'serviceDate' => $record->serviceDate()?->format('Y-m-d'),
            'odometerKm' => $record->odometerKm(),
            'workType' => $record->workType(),
            'laborBani' => $record->laborBani(),
            'totalBani' => $record->totalBani(),
        ];
    }
}
