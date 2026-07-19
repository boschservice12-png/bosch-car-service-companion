<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Spreadsheet;

/**
 * Cititor XLSX minimal, fără dependențe externe: deschide arhiva (un .xlsx este
 * un ZIP), citește prima foaie și șirurile partajate, și întoarce rândurile ca
 * liste de stringuri. Suficient pentru tabele simple de import (antet + rânduri
 * de date); nu suportă formule, stiluri sau foi multiple.
 */
final class SimpleXlsxReader
{
    /** @return list<list<string>> Rânduri → celule (stringuri, în ordinea coloanelor). */
    public function rows(string $path): array
    {
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            throw new \InvalidArgumentException('Fișierul nu este un XLSX valid (arhivă coruptă).');
        }

        try {
            $shared = $this->sharedStrings($zip);
            $sheetXml = $this->firstSheet($zip);
        } finally {
            $zip->close();
        }

        $doc = new \DOMDocument();
        if (!@$doc->loadXML($sheetXml)) {
            throw new \InvalidArgumentException('Fișierul nu este un XLSX valid (foaie ilizibilă).');
        }

        $rows = [];
        foreach ($doc->getElementsByTagName('row') as $rowEl) {
            \assert($rowEl instanceof \DOMElement);
            $cells = [];
            foreach ($rowEl->getElementsByTagName('c') as $cellEl) {
                \assert($cellEl instanceof \DOMElement);
                $col = $this->columnIndex($cellEl->getAttribute('r'));
                $cells[$col] = $this->cellValue($cellEl, $shared);
            }
            if ($cells === []) {
                $rows[] = [];
                continue;
            }
            // Umple golurile dintre coloane, ca indecșii să fie stabili.
            $max = max(array_keys($cells));
            $row = [];
            for ($i = 0; $i <= $max; ++$i) {
                $row[] = $cells[$i] ?? '';
            }
            $rows[] = $row;
        }

        return $rows;
    }

    /** @return list<string> */
    private function sharedStrings(\ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($xml === false) {
            return [];
        }
        $doc = new \DOMDocument();
        if (!@$doc->loadXML($xml)) {
            return [];
        }
        $strings = [];
        foreach ($doc->getElementsByTagName('si') as $si) {
            // Concatenează toate nodurile <t> (inclusiv rich text pe bucăți).
            $text = '';
            foreach ($si->getElementsByTagName('t') as $t) {
                $text .= $t->textContent;
            }
            $strings[] = $text;
        }

        return $strings;
    }

    private function firstSheet(\ZipArchive $zip): string
    {
        foreach (['xl/worksheets/sheet1.xml', 'xl/worksheets/sheet.xml'] as $name) {
            $xml = $zip->getFromName($name);
            if ($xml !== false) {
                return $xml;
            }
        }
        // Fallback: prima intrare care arată ca o foaie.
        for ($i = 0; $i < $zip->numFiles; ++$i) {
            $name = (string) $zip->getNameIndex($i);
            if (str_starts_with($name, 'xl/worksheets/') && str_ends_with($name, '.xml')) {
                $xml = $zip->getFromName($name);
                if ($xml !== false) {
                    return $xml;
                }
            }
        }

        throw new \InvalidArgumentException('XLSX fără nicio foaie de calcul.');
    }

    /** @param list<string> $shared */
    private function cellValue(\DOMElement $cell, array $shared): string
    {
        $type = $cell->getAttribute('t');

        if ($type === 'inlineStr') {
            $is = $cell->getElementsByTagName('is')->item(0);

            return $is !== null ? trim($is->textContent) : '';
        }

        $v = $cell->getElementsByTagName('v')->item(0);
        if ($v === null) {
            return '';
        }
        $raw = trim($v->textContent);

        if ($type === 's') {
            return trim($shared[(int) $raw] ?? '');
        }

        return $raw;
    }

    /** „B7" → 1 (index de coloană bazat pe 0). */
    private function columnIndex(string $cellRef): int
    {
        $letters = rtrim($cellRef, '0123456789');
        $index = 0;
        foreach (str_split($letters) as $ch) {
            $index = $index * 26 + (\ord($ch) - 64);
        }

        return max(0, $index - 1);
    }
}
