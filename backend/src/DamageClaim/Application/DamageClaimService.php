<?php

declare(strict_types=1);

namespace App\DamageClaim\Application;

use App\Audit\Application\AuditRecorder;
use App\DamageClaim\Domain\DamageClaim;
use App\DamageClaim\Domain\DamageClaimRepository;
use App\DamageClaim\Domain\DamageClaimStatus;

final class DamageClaimService
{
    public function __construct(
        private readonly DamageClaimRepository $claims,
        private readonly AuditRecorder $audit,
    ) {
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
}
