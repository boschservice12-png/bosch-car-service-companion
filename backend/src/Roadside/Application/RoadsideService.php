<?php

declare(strict_types=1);

namespace App\Roadside\Application;

use App\Audit\Application\AuditRecorder;
use App\Document\Domain\Document;
use App\Identity\Domain\User;
use App\Roadside\Domain\MobilityState;
use App\Roadside\Domain\RoadsideRequest;
use App\Roadside\Domain\RoadsideRequestRepository;
use App\Roadside\Domain\RoadsideStatus;
use App\Roadside\Domain\SafetyState;
use App\Shared\Presentation\ValidationFailedException;
use App\Vehicle\Domain\Vehicle;

final class RoadsideService
{
    public function __construct(
        private readonly RoadsideRequestRepository $requests,
        private readonly AuditRecorder $audit,
    ) {
    }

    /**
     * @param Document[] $attachments
     */
    public function create(
        User $customer,
        ?Vehicle $vehicle,
        string $location,
        string $problem,
        MobilityState $mobility,
        SafetyState $safety,
        string $phone,
        array $attachments,
    ): RoadsideRequest {
        $request = new RoadsideRequest($customer, $vehicle, $location, $problem, $mobility, $safety, $phone);
        foreach ($attachments as $document) {
            $request->attach($document);
        }
        $this->requests->save($request);

        $this->audit->record('roadside.created', 'RoadsideRequest', (string) $request->id(), null, [
            'mobility' => $mobility->value,
            'safety' => $safety->value,
        ]);

        return $request;
    }

    public function updateStatus(RoadsideRequest $request, RoadsideStatus $status, ?string $note): RoadsideRequest
    {
        $before = ['status' => $request->status()->value];
        $request->changeStatus($status, $note);
        $this->requests->save($request);

        $this->audit->record('roadside.status_changed', 'RoadsideRequest', (string) $request->id(), $before, [
            'status' => $status->value,
        ]);

        return $request;
    }

    /** Anulare de către client — permisă doar cât timp cererea e nouă. */
    public function cancelByClient(RoadsideRequest $request): RoadsideRequest
    {
        if (!$request->isOpen()) {
            throw ValidationFailedException::fromArray(['status' => ['Cererea nu mai poate fi anulată.']]);
        }
        $request->changeStatus(RoadsideStatus::CANCELLED, null);
        $this->requests->save($request);

        $this->audit->record('roadside.cancelled', 'RoadsideRequest', (string) $request->id());

        return $request;
    }
}
