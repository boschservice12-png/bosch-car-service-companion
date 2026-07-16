<?php

declare(strict_types=1);

namespace App\Tax\Application;

use App\Audit\Application\AuditRecorder;
use App\Document\Domain\Document;
use App\Identity\Domain\User;
use App\Tax\Domain\PaymentStatus;
use App\Tax\Domain\TaxItem;
use App\Tax\Domain\TaxItemRepository;
use App\Tax\Domain\TaxType;
use App\Vehicle\Domain\Vehicle;

final class TaxService
{
    public function __construct(
        private readonly TaxItemRepository $items,
        private readonly AuditRecorder $audit,
    ) {
    }

    public function create(
        User $customer,
        ?Vehicle $vehicle,
        int $year,
        TaxType $type,
        int $amountBani,
        ?\DateTimeImmutable $dueDate,
    ): TaxItem {
        $item = new TaxItem($customer, $vehicle, $year, $type, $amountBani, $dueDate);
        $this->items->save($item);

        $this->audit->record('tax.created', 'TaxItem', (string) $item->id(), null, [
            'year' => $year,
            'type' => $type->value,
        ]);

        return $item;
    }

    /**
     * Marchează plata (opțional cu un bizonjat atașat). Idempotent: dacă e deja plătită,
     * doar atașează bizonjatul.
     *
     * @param Document[] $receipts
     */
    public function markPaid(TaxItem $item, array $receipts): TaxItem
    {
        foreach ($receipts as $document) {
            $item->attach($document);
        }
        if (!$item->isPaid()) {
            $item->markPaid();
        }
        $this->items->save($item);

        $this->audit->record('tax.paid', 'TaxItem', (string) $item->id(), null, [
            'paidAt' => $item->paidAt()?->format(DATE_ATOM),
        ]);

        return $item;
    }

    public function setStatus(TaxItem $item, PaymentStatus $status, ?string $note): TaxItem
    {
        $before = ['status' => $item->status()->value];
        if ($status === PaymentStatus::PAID) {
            if (!$item->isPaid()) {
                $item->markPaid();
            }
        } else {
            $item->markUnpaid();
        }
        if ($note !== null) {
            $item->setNote($note);
        }
        $this->items->save($item);

        $this->audit->record('tax.status_changed', 'TaxItem', (string) $item->id(), $before, [
            'status' => $status->value,
        ]);

        return $item;
    }
}
