<?php

declare(strict_types=1);

namespace App\QuoteRequest\Application;

use App\Audit\Application\AuditRecorder;
use App\Document\Domain\Document;
use App\Identity\Domain\User;
use App\QuoteRequest\Domain\QuoteRequest;
use App\QuoteRequest\Domain\QuoteRequestRepository;
use App\QuoteRequest\Domain\QuoteRequestStatus;
use App\QuoteRequest\Domain\QuoteResponse;
use App\Vehicle\Domain\Vehicle;

/**
 * Fluxul cererii de ofertă (funcționalitatea 7): clientul creează (ciornă sau
 * trimisă direct), service-ul analizează, cere informații sau răspunde cu
 * ofertă; clientul acceptă sau refuză. Toate tranzițiile trec prin mașina de
 * stări; fiecare pas este auditat.
 */
final class QuoteRequestService
{
    public function __construct(
        private readonly QuoteRequestRepository $requests,
        private readonly AuditRecorder $audit,
    ) {
    }

    public function create(
        User $customer,
        ?Vehicle $vehicle,
        ?int $mileage,
        string $symptomDescription,
        ?string $occurrenceConditions,
        bool $vehicleDrivable,
        ?string $warningLights,
        ?string $preferredContactMethod,
        ?string $preferredInterval,
        bool $asDraft,
    ): QuoteRequest {
        $request = new QuoteRequest(
            $customer,
            $vehicle,
            $mileage,
            $symptomDescription,
            $occurrenceConditions,
            $vehicleDrivable,
            $warningLights,
            $preferredContactMethod,
            $preferredInterval,
            $asDraft,
        );
        $this->requests->save($request);

        $this->audit->record('quote_request.created', 'QuoteRequest', (string) $request->id(), null, [
            'status' => $request->status()->value,
        ]);

        return $request;
    }

    /** Clientul trimite ciorna (DRAFT → SUBMITTED). */
    public function submit(QuoteRequest $request): QuoteRequest
    {
        return $this->transition($request, QuoteRequestStatus::SUBMITTED, 'quote_request.submitted');
    }

    /** Service-ul schimbă starea (IN_REVIEW, NEEDS_INFORMATION, CLOSED). */
    public function updateStatus(QuoteRequest $request, QuoteRequestStatus $status): QuoteRequest
    {
        return $this->transition($request, $status, 'quote_request.status_changed');
    }

    /** Service-ul răspunde cu ofertă: text + document opțional → REPLIED. */
    public function respond(QuoteRequest $request, User $admin, string $message, ?Document $document): QuoteRequest
    {
        // Tranziția întâi: dintr-o stare greșită nu se creează niciun răspuns.
        $before = ['status' => $request->status()->value];
        $request->changeStatus(QuoteRequestStatus::REPLIED);
        $request->addResponse(new QuoteResponse($request, $admin, $message, $document));
        $this->requests->save($request);

        $this->audit->record('quote_request.replied', 'QuoteRequest', (string) $request->id(), $before, [
            'status' => QuoteRequestStatus::REPLIED->value,
            'withDocument' => $document !== null,
        ]);

        return $request;
    }

    /** Clientul completează informațiile cerute (NEEDS_INFORMATION → IN_REVIEW). */
    public function resubmit(QuoteRequest $request): QuoteRequest
    {
        return $this->transition($request, QuoteRequestStatus::IN_REVIEW, 'quote_request.resubmitted');
    }

    public function accept(QuoteRequest $request): QuoteRequest
    {
        return $this->transition($request, QuoteRequestStatus::ACCEPTED, 'quote_request.accepted');
    }

    public function decline(QuoteRequest $request): QuoteRequest
    {
        return $this->transition($request, QuoteRequestStatus::DECLINED, 'quote_request.declined');
    }

    private function transition(QuoteRequest $request, QuoteRequestStatus $status, string $auditAction): QuoteRequest
    {
        $before = ['status' => $request->status()->value];
        $request->changeStatus($status);
        $this->requests->save($request);

        $this->audit->record($auditAction, 'QuoteRequest', (string) $request->id(), $before, [
            'status' => $status->value,
        ]);

        return $request;
    }
}
