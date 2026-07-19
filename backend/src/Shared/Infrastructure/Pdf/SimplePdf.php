<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Pdf;

/**
 * Generator PDF minimal, fără dependențe externe: A4, font de bază Helvetica,
 * text pe linii cu paginare automată. Ieșirea este deterministă (fără
 * timestamp-uri sau ID-uri aleatoare), deci un PDF regenerat pentru același
 * conținut este identic bit cu bit — cerință pentru documente verificabile.
 *
 * Limitare asumată: fontul de bază folosește WinAnsi, care nu conține
 * diacriticele românești ș/ț — textul este transliterat la ASCII (ă→a, ș→s…).
 */
final class SimplePdf
{
    private const PAGE_W = 595.28; // A4 pt
    private const PAGE_H = 841.89;
    private const MARGIN = 50.0;
    private const LINE_H = 14.0;

    /** @var list<list<array{text: string, size: float, bold: bool}>> pagini → linii */
    private array $pages = [[]];

    public function addLine(string $text, float $size = 10.0, bool $bold = false): void
    {
        $linesPerPage = (int) floor((self::PAGE_H - 2 * self::MARGIN) / self::LINE_H);
        $current = \count($this->pages) - 1;
        if (\count($this->pages[$current]) >= $linesPerPage) {
            $this->pages[] = [];
            ++$current;
        }
        $this->pages[$current][] = ['text' => $this->ascii($text), 'size' => $size, 'bold' => $bold];
    }

    public function addSpacer(): void
    {
        $this->addLine('');
    }

    /** Împarte un text lung în linii de maxim ~$width caractere și le adaugă. */
    public function addWrapped(string $text, float $size = 10.0, int $width = 95): void
    {
        foreach (explode("\n", wordwrap($text, $width, "\n", true)) as $line) {
            $this->addLine($line, $size);
        }
    }

    public function render(): string
    {
        $objects = [];
        // 1: catalog, 2: pages, 3: font normal, 4: font bold; paginile de la 5.
        $pageCount = \count($this->pages);
        $kids = [];
        for ($i = 0; $i < $pageCount; ++$i) {
            $kids[] = sprintf('%d 0 R', 5 + 2 * $i);
        }

        $objects[1] = "<< /Type /Catalog /Pages 2 0 R >>";
        $objects[2] = sprintf('<< /Type /Pages /Kids [%s] /Count %d >>', implode(' ', $kids), $pageCount);
        $objects[3] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>";
        $objects[4] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>";

        foreach ($this->pages as $i => $lines) {
            $pageObj = 5 + 2 * $i;
            $contentObj = $pageObj + 1;
            $objects[$pageObj] = sprintf(
                '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2F %.2F] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents %d 0 R >>',
                self::PAGE_W,
                self::PAGE_H,
                $contentObj,
            );

            $stream = "BT\n";
            $y = self::PAGE_H - self::MARGIN;
            foreach ($lines as $line) {
                if ($line['text'] !== '') {
                    $stream .= sprintf(
                        "/%s %.1F Tf\n1 0 0 1 %.2F %.2F Tm\n(%s) Tj\n",
                        $line['bold'] ? 'F2' : 'F1',
                        $line['size'],
                        self::MARGIN,
                        $y,
                        $this->escape($line['text']),
                    );
                }
                $y -= self::LINE_H;
            }
            $stream .= "ET";
            $objects[$contentObj] = sprintf("<< /Length %d >>\nstream\n%s\nendstream", \strlen($stream), $stream);
        }

        // Serializare cu tabelă xref corectă.
        $out = "%PDF-1.4\n";
        $offsets = [];
        ksort($objects);
        foreach ($objects as $num => $body) {
            $offsets[$num] = \strlen($out);
            $out .= sprintf("%d 0 obj\n%s\nendobj\n", $num, $body);
        }
        $xrefPos = \strlen($out);
        $max = max(array_keys($objects));
        $out .= sprintf("xref\n0 %d\n0000000000 65535 f \n", $max + 1);
        for ($i = 1; $i <= $max; ++$i) {
            $out .= isset($offsets[$i])
                ? sprintf("%010d 00000 n \n", $offsets[$i])
                : "0000000000 65535 f \n";
        }
        $out .= sprintf("trailer\n<< /Size %d /Root 1 0 R >>\nstartxref\n%d\n%%%%EOF\n", $max + 1, $xrefPos);

        return $out;
    }

    private function escape(string $text): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    }

    private function ascii(string $text): string
    {
        static $map = [
            'ă' => 'a', 'â' => 'a', 'î' => 'i', 'ș' => 's', 'ş' => 's', 'ț' => 't', 'ţ' => 't',
            'Ă' => 'A', 'Â' => 'A', 'Î' => 'I', 'Ș' => 'S', 'Ş' => 'S', 'Ț' => 'T', 'Ţ' => 'T',
        ];
        $text = strtr($text, $map);

        // Orice alt caracter non-ASCII devine '?' (fontul de bază nu îl are).
        return preg_replace('/[^\x20-\x7E]/', '?', $text) ?? $text;
    }
}
