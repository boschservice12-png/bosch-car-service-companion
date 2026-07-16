<?php

declare(strict_types=1);

namespace App\Deadline\Application;

use App\Audit\Application\AuditRecorder;
use App\Deadline\Domain\DeadlineSource;
use App\Deadline\Domain\DeadlineType;
use App\Deadline\Domain\VehicleDeadline;
use App\Deadline\Domain\VehicleDeadlineRepository;
use App\Deadline\Presentation\Dto\CreateDeadlineRequest;
use App\Deadline\Presentation\Dto\UpdateDeadlineRequest;
use App\Document\Domain\Document;
use App\Identity\Domain\User;
use App\Shared\Presentation\ValidationFailedException;
use App\Vehicle\Domain\Vehicle;

final class DeadlineService
{
    public function __construct(
        private readonly VehicleDeadlineRepository $deadlines,
        private readonly AuditRecorder $audit,
    ) {
    }

    public function create(Vehicle $vehicle, CreateDeadlineRequest $req, DeadlineSource $source): VehicleDeadline
    {
        $expiresAt = $this->parseDate($req->expiresAt);
        $validFrom = $this->parseDate($req->validFrom);
        $this->assertDateOrder($validFrom, $expiresAt);

        $deadline = new VehicleDeadline(
            $vehicle,
            DeadlineType::from($req->type),
            $expiresAt,
            $source,
            $validFrom,
        );
        if ($req->note !== null && $req->note !== '') {
            $deadline->update($validFrom, $expiresAt, $req->note);
        }

        $this->deadlines->save($deadline);
        $this->audit->record('deadline.created', 'VehicleDeadline', (string) $deadline->id(), null, [
            'type' => $deadline->type()->value,
            'expiresAt' => $expiresAt?->format('Y-m-d'),
        ]);

        return $deadline;
    }

    public function update(VehicleDeadline $deadline, UpdateDeadlineRequest $req, ?User $admin): VehicleDeadline
    {
        $expiresAt = $req->expiresAt !== null ? $this->parseDate($req->expiresAt) : $deadline->expiresAt();
        $validFrom = $req->validFrom !== null ? $this->parseDate($req->validFrom) : $deadline->validFrom();
        $this->assertDateOrder($validFrom, $expiresAt);

        $before = [
            'expiresAt' => $deadline->expiresAt()?->format('Y-m-d'),
            'verified' => $deadline->isVerified(),
        ];

        $deadline->update($validFrom, $expiresAt, $req->note ?? $deadline->note());

        if ($req->verify === true) {
            if (!$admin instanceof User) {
                throw ValidationFailedException::fromArray(['verify' => ['Doar un administrator poate valida o scadență.']]);
            }
            $deadline->markVerified($admin);
        }

        $this->deadlines->save($deadline);
        $this->audit->record('deadline.updated', 'VehicleDeadline', (string) $deadline->id(), $before, [
            'expiresAt' => $deadline->expiresAt()?->format('Y-m-d'),
            'verified' => $deadline->isVerified(),
        ]);

        return $deadline;
    }

    public function attachDocument(VehicleDeadline $deadline, Document $document): VehicleDeadline
    {
        $deadline->attachDocument($document);
        $this->deadlines->save($deadline);
        $this->audit->record('deadline.document_attached', 'VehicleDeadline', (string) $deadline->id(), null, [
            'documentId' => (string) $document->id(),
        ]);

        return $deadline;
    }

    private function parseDate(?string $value): ?\DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if ($date === false) {
            throw ValidationFailedException::fromArray(['expiresAt' => ['Dată invalidă (format așteptat: AAAA-LL-ZZ).']]);
        }

        return $date;
    }

    private function assertDateOrder(?\DateTimeImmutable $validFrom, ?\DateTimeImmutable $expiresAt): void
    {
        if ($validFrom !== null && $expiresAt !== null && $expiresAt <= $validFrom) {
            throw ValidationFailedException::fromArray([
                'expiresAt' => ['Data expirării trebuie să fie ulterioară datei de început.'],
            ]);
        }
    }
}
