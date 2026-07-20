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
            'validFrom' => $deadline->validFrom()?->format('Y-m-d'),
            'expiresAt' => $deadline->expiresAt()?->format('Y-m-d'),
            'source' => $deadline->source()->value,
            'verified' => $deadline->isVerified(),
        ];
        $wasVerified = $deadline->isVerified();
        $prevSource = $deadline->source();

        $datesChanged = $expiresAt?->format('Y-m-d') !== $deadline->expiresAt()?->format('Y-m-d')
            || $validFrom?->format('Y-m-d') !== $deadline->validFrom()?->format('Y-m-d');

        $deadline->update($validFrom, $expiresAt, $req->note ?? $deadline->note());

        // Regula de proveniență (documentată în docs/PILOT_READINESS.md):
        // o modificare de CLIENT a unui câmp relevant pentru verificare
        // (validFrom / expiresAt / document) rupe ștampila service-ului —
        // source devine CLIENT și verificarea se anulează, CHIAR DACĂ rândul
        // nu era încă validat (un rând SERVICE nevalidat editat de client NU
        // rămâne SERVICE). Modificarea DOAR a notei NU rupe verificarea (nota
        // e un comentariu liber, nu un fapt verificat). Editările adminului NU
        // validează automat — validarea se face doar prin `verify: true`.
        $reason = null;
        if ($admin === null && $datesChanged) {
            $deadline->resetVerification();
            if ($prevSource !== DeadlineSource::CLIENT || $wasVerified) {
                $reason = 'client_edited_dates';
            }
        }

        if ($req->verify === true) {
            if (!$admin instanceof User) {
                throw ValidationFailedException::fromArray(['verify' => ['Doar un administrator poate valida o scadență.']]);
            }
            $deadline->markVerified($admin);
        }

        $this->deadlines->save($deadline);
        $after = [
            'validFrom' => $deadline->validFrom()?->format('Y-m-d'),
            'expiresAt' => $deadline->expiresAt()?->format('Y-m-d'),
            'source' => $deadline->source()->value,
            'verified' => $deadline->isVerified(),
        ];
        if ($reason !== null) {
            $after['verificationClearedReason'] = $reason;
        }
        $this->audit->record('deadline.updated', 'VehicleDeadline', (string) $deadline->id(), $before, $after);

        return $deadline;
    }

    /**
     * Atașarea unui document. Documentul e un câmp relevant pentru verificare:
     * dacă îl atașează CLIENTUL, proveniența devine CLIENT și verificarea
     * service-ului se anulează (la fel ca la editarea datelor). Atașarea de
     * către admin nu schimbă proveniența.
     */
    public function attachDocument(VehicleDeadline $deadline, Document $document, ?User $admin = null): VehicleDeadline
    {
        $prevSource = $deadline->source();
        $wasVerified = $deadline->isVerified();
        $deadline->attachDocument($document);

        $reason = null;
        if ($admin === null && ($prevSource !== DeadlineSource::CLIENT || $wasVerified)) {
            $deadline->resetVerification();
            $reason = 'client_attached_document';
        }

        $this->deadlines->save($deadline);
        $this->audit->record('deadline.document_attached', 'VehicleDeadline', (string) $deadline->id(), [
            'source' => $prevSource->value,
            'verified' => $wasVerified,
        ], array_filter([
            'documentId' => (string) $document->id(),
            'source' => $deadline->source()->value,
            'verified' => $deadline->isVerified(),
            'verificationClearedReason' => $reason,
        ], static fn ($v) => $v !== null));

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
