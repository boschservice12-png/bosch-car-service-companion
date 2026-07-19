<?php

declare(strict_types=1);

namespace App\DamageClaim\Application;

use App\Audit\Application\AuditRecorder;
use App\DamageClaim\Domain\DamageClaim;
use App\DamageClaim\Domain\DamageClaimRepository;
use App\DamageClaim\Domain\DamageClaimStatus;
use App\Document\Domain\Document;
use App\Identity\Domain\User;
use App\Shared\Presentation\ValidationFailedException;
use App\Vehicle\Domain\Vehicle;

final class DamageClaimService
{
    public function __construct(
        private readonly DamageClaimRepository $claims,
        private readonly AuditRecorder $audit,
    ) {
    }

    /**
     * @param Document[] $attachments
     */
    public function create(
        User $customer,
        ?Vehicle $vehicle,
        ?\DateTimeImmutable $incidentDate,
        ?string $incidentLocation,
        string $incidentDescription,
        ?string $insurer,
        ?string $policyNumber,
        array $attachments,
    ): DamageClaim {
        $claim = new DamageClaim($customer, $vehicle, $incidentDate, $incidentLocation, $incidentDescription, $insurer, $policyNumber);
        foreach ($attachments as $document) {
            $claim->attach($document);
        }
        $this->claims->save($claim);

        $this->audit->record('damage_claim.created', 'DamageClaim', (string) $claim->id(), null, [
            'insurer' => $insurer,
        ]);

        return $claim;
    }

    /** @param string[]|null $missingDocuments */
    public function updateStatus(DamageClaim $claim, DamageClaimStatus $status, ?string $note, ?array $missingDocuments = null): DamageClaim
    {
        $before = ['status' => $claim->status()->value];
        $claim->changeStatus($status, $note, $missingDocuments);
        $this->claims->save($claim);

        $this->audit->record('damage_claim.status_changed', 'DamageClaim', (string) $claim->id(), $before, [
            'status' => $status->value,
        ]);

        return $claim;
    }

    public function cancelByClient(DamageClaim $claim): DamageClaim
    {
        if (!$claim->isOpen()) {
            throw ValidationFailedException::fromArray(['status' => ['Dosarul nu mai poate fi anulat.']]);
        }
        $claim->changeStatus(DamageClaimStatus::CLOSED, 'Anulat de client.');
        $this->claims->save($claim);

        $this->audit->record('damage_claim.cancelled', 'DamageClaim', (string) $claim->id());

        return $claim;
    }
}
