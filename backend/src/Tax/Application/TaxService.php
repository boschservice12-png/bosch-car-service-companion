<?php

declare(strict_types=1);

namespace App\Tax\Application;

use App\Audit\Application\AuditRecorder;
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

    /** Editarea taxei de către proprietar: an, tip, sumă, scadență, vehicul. */
    public function update(
        TaxItem $item,
        ?Vehicle $vehicle,
        int $year,
        TaxType $type,
        int $amountBani,
        ?\DateTimeImmutable $dueDate,
    ): TaxItem {
        $before = [
            'year' => $item->year(),
            'type' => $item->type()->value,
            'amountBani' => $item->amountBani(),
            'dueDate' => $item->dueDate()?->format('Y-m-d'),
        ];
        $item->updateDetails($vehicle, $year, $type, $amountBani, $dueDate);
        $this->items->save($item);

        $this->audit->record('tax.updated', 'TaxItem', (string) $item->id(), $before, [
            'year' => $item->year(),
            'type' => $item->type()->value,
            'amountBani' => $item->amountBani(),
            'dueDate' => $item->dueDate()?->format('Y-m-d'),
        ]);

        return $item;
    }

    public function delete(TaxItem $item): void
    {
        $this->audit->record('tax.deleted', 'TaxItem', (string) $item->id(), [
            'year' => $item->year(),
            'type' => $item->type()->value,
            'amountBani' => $item->amountBani(),
        ], null);

        $this->items->remove($item);
    }

    /** Marchează plata integrală. Idempotent: dacă e deja plătită, nu se schimbă nimic. */
    public function markPaid(TaxItem $item): TaxItem
    {
        if (!$item->isPaid()) {
            $item->markPaid();
        }
        $this->items->save($item);

        $this->audit->record('tax.paid', 'TaxItem', (string) $item->id(), null, [
            'paidAt' => $item->paidAt()?->format(DATE_ATOM),
        ]);

        return $item;
    }

    /** Înregistrează o plată parțială sau integrală (sumele se acumulează). */
    public function registerPayment(TaxItem $item, int $amountBani): TaxItem
    {
        $before = ['status' => $item->status()->value, 'paidAmountBani' => $item->paidAmountBani()];
        $item->registerPayment($amountBani);
        $this->items->save($item);

        $this->audit->record('tax.payment_registered', 'TaxItem', (string) $item->id(), $before, [
            'status' => $item->status()->value,
            'paidAmountBani' => $item->paidAmountBani(),
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
