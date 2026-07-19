<?php

declare(strict_types=1);

namespace App\ServiceHistory\Application;

use App\Shared\Infrastructure\Pdf\SimplePdf;
use App\ServiceHistory\Domain\ServiceRecord;
use App\Vehicle\Domain\Vehicle;

/**
 * PDF pentru istoricul de service (specificație: „se poate genera PDF pentru o
 * intrare sau pentru istoricul vehiculului"). Conținutul este determinist —
 * pentru aceleași date se generează exact același fișier.
 */
final class ServiceRecordPdfGenerator
{
    public function forRecord(ServiceRecord $record): string
    {
        $pdf = new SimplePdf();
        $this->header($pdf, $record->vehicle());
        $this->entry($pdf, $record);
        $this->footer($pdf);

        return $pdf->render();
    }

    /** @param ServiceRecord[] $records Cronologic (cele mai noi primele). */
    public function forVehicle(Vehicle $vehicle, array $records): string
    {
        $pdf = new SimplePdf();
        $this->header($pdf, $vehicle);
        if ($records === []) {
            $pdf->addLine('Nicio intrare publicată în istoricul de service.');
        }
        foreach ($records as $record) {
            $this->entry($pdf, $record);
        }
        $this->footer($pdf);

        return $pdf->render();
    }

    private function header(SimplePdf $pdf, Vehicle $vehicle): void
    {
        $pdf->addLine('BOSCH CAR SERVICE — SC SZKALICZKI SERVICE SRL', 14.0, true);
        $pdf->addLine('Istoric service — document generat din aplicație', 10.0);
        $pdf->addSpacer();
        $pdf->addLine(sprintf('Vehicul: %s · VIN %s', $vehicle->plateNumber(), $vehicle->vin()), 11.0, true);
        $pdf->addLine('Istoricul începe de la prima intrare în acest service.', 9.0);
        $pdf->addSpacer();
    }

    private function entry(SimplePdf $pdf, ServiceRecord $r): void
    {
        $pdf->addLine(str_repeat('-', 95), 8.0);
        $title = sprintf(
            '%s · %s%s',
            $r->serviceDate()?->format('d.m.Y') ?? 'fără dată',
            $r->workType() ?? 'Lucrare service',
            $r->status()->value === 'CORRECTED' ? ' · [CORECTAT — vezi intrarea de corecție]' : '',
        );
        $pdf->addLine($title, 11.0, true);
        if ($r->odometerKm() !== null) {
            $pdf->addLine(sprintf('Kilometraj: %s km', number_format($r->odometerKm(), 0, ',', '.')));
        }
        if ($r->workDescription() !== null && $r->workDescription() !== '') {
            $pdf->addWrapped('Lucrări: '.$r->workDescription());
        }
        if ($r->partsSummary() !== null && $r->partsSummary() !== '') {
            $pdf->addWrapped('Piese: '.$r->partsSummary());
        }
        $pdf->addLine(sprintf(
            'Manoperă: %s RON · Total: %s RON',
            number_format($r->laborBani() / 100, 2, ',', '.'),
            number_format($r->totalBani() / 100, 2, ',', '.'),
        ));
        if ($r->warranty() !== null && $r->warranty() !== '') {
            $pdf->addLine('Garanție: '.$r->warranty());
        }
        if ($r->correctionOf() !== null) {
            $pdf->addLine(sprintf(
                'Corecție a intrării din %s. Motiv: %s',
                $r->correctionOf()->serviceDate()?->format('d.m.Y') ?? '—',
                $r->correctionReason() ?? '—',
            ), 9.0);
        }
        if ($r->publishedAt() !== null) {
            $pdf->addLine('Publicat la: '.$r->publishedAt()->format('d.m.Y H:i'), 8.0);
        }
        $pdf->addSpacer();
    }

    private function footer(SimplePdf $pdf): void
    {
        $pdf->addSpacer();
        $pdf->addLine('Document informativ generat electronic; nu necesită semnătură.', 8.0);
    }
}
