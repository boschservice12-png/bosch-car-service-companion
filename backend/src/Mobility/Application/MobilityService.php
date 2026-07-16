<?php

declare(strict_types=1);

namespace App\Mobility\Application;

use App\Audit\Application\AuditRecorder;
use App\Identity\Domain\User;
use App\Mobility\Domain\MobilityRequest;
use App\Mobility\Domain\MobilityRequestRepository;
use App\Mobility\Domain\MobilityStatus;
use App\Mobility\Domain\MobilityType;
use App\Shared\Presentation\ValidationFailedException;
use App\Vehicle\Domain\Vehicle;

final class MobilityService
{
    public function __construct(
        private readonly MobilityRequestRepository $requests,
        private readonly AuditRecorder $audit,
    ) {
    }

    public function create(
        User $customer,
        ?Vehicle $vehicle,
        MobilityType $type,
        string $details,
        ?\DateTimeImmutable $preferredDate,
    ): MobilityRequest {
        $request = new MobilityRequest($customer, $vehicle, $type, $details, $preferredDate);
        $this->requests->save($request);

        $this->audit->record('mobility.created', 'MobilityRequest', (string) $request->id(), null, [
            'type' => $type->value,
        ]);

        return $request;
    }

    public function updateStatus(MobilityRequest $request, MobilityStatus $status, ?string $note): MobilityRequest
    {
        $before = ['status' => $request->status()->value];
        $request->changeStatus($status, $note);
        $this->requests->save($request);

        $this->audit->record('mobility.status_changed', 'MobilityRequest', (string) $request->id(), $before, [
            'status' => $status->value,
        ]);

        return $request;
    }

    public function cancelByClient(MobilityRequest $request): MobilityRequest
    {
        if (!$request->isOpen()) {
            throw ValidationFailedException::fromArray(['status' => ['Solicitarea nu mai poate fi anulată.']]);
        }
        $request->changeStatus(MobilityStatus::CANCELLED, null);
        $this->requests->save($request);

        $this->audit->record('mobility.cancelled', 'MobilityRequest', (string) $request->id());

        return $request;
    }
}
