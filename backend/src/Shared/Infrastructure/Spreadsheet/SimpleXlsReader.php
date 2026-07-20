<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Spreadsheet;

/**
 * Cititor .xls (Excel binar, BIFF8) minimal, în PHP pur — fără dependențe.
 * Exporturile reale din evidența service-ului (ASM / WPS) vin în acest format
 * vechi, nu ca .xlsx. Citește prima foaie și întoarce rândurile ca liste de
 * stringuri; numerele (inclusiv datele calendaristice, ca numere seriale
 * Excel) se întorc ca text — conversia în date o fac serviciile de import.
 *
 * Suportă înregistrările uzuale: SST (+CONTINUE), LABELSST, LABEL, NUMBER,
 * RK, MULRK, BLANK/MULBLANK, FORMULA cu rezultat numeric sau STRING.
 * Nu suportă criptare, foi multiple (doar prima) sau stiluri.
 */
final class SimpleXlsReader
{
    private const CFB_MAGIC = "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1";
    private const ENDOFCHAIN = 0xFFFFFFFE;
    private const FREESECT = 0xFFFFFFFF;

    /** @return list<list<string>> Rânduri → celule (stringuri, în ordinea coloanelor). */
    public function rows(string $path): array
    {
        $raw = @file_get_contents($path);
        if ($raw === false || !str_starts_with($raw, self::CFB_MAGIC)) {
            throw new \InvalidArgumentException('Fișierul nu este un XLS valid (container corupt).');
        }

        $stream = $this->workbookStream($raw);

        return $this->parseWorkbook($stream);
    }

    // ------------------------------------------------------------- OLE2/CFB

    private function workbookStream(string $raw): string
    {
        $u16 = static fn (int $o): int => unpack('v', substr($raw, $o, 2))[1];
        $u32 = static fn (int $o): int => unpack('V', substr($raw, $o, 4))[1];

        $sectorSize = 1 << $u16(30);
        $miniSize = 1 << $u16(32);
        $dirStart = $u32(48);
        $miniCutoff = $u32(56);
        $miniFatStart = $u32(60);
        $difatStart = $u32(68);
        $numDifat = $u32(72);

        $sectorData = static fn (int $sector): string => substr($raw, 512 + $sector * $sectorSize, $sectorSize);

        // FAT: din cele 109 intrări DIFAT din antet + eventuale sectoare DIFAT.
        $fatSectors = [];
        for ($i = 0; $i < 109; ++$i) {
            $s = $u32(76 + $i * 4);
            if ($s !== self::FREESECT && $s !== self::ENDOFCHAIN) {
                $fatSectors[] = $s;
            }
        }
        $next = $difatStart;
        for ($i = 0; $i < $numDifat && $next !== self::ENDOFCHAIN && $next !== self::FREESECT; ++$i) {
            $data = $sectorData($next);
            $count = intdiv($sectorSize, 4) - 1;
            for ($j = 0; $j < $count; ++$j) {
                $s = unpack('V', substr($data, $j * 4, 4))[1];
                if ($s !== self::FREESECT && $s !== self::ENDOFCHAIN) {
                    $fatSectors[] = $s;
                }
            }
            $next = unpack('V', substr($data, $sectorSize - 4, 4))[1];
        }
        $fat = [];
        foreach ($fatSectors as $s) {
            foreach (array_values(unpack('V*', $sectorData($s))) as $entry) {
                $fat[] = $entry;
            }
        }

        $readChain = static function (int $start, array $table, callable $dataFn) {
            $out = '';
            $sector = $start;
            $guard = 0;
            while ($sector !== self::ENDOFCHAIN && $sector !== self::FREESECT) {
                if (++$guard > 1_000_000 || !isset($table[$sector])) {
                    break;
                }
                $out .= $dataFn($sector);
                $sector = $table[$sector];
            }

            return $out;
        };

        // Directorul: găsim intrarea Workbook/Book + root (pentru mini-stream).
        $dir = $readChain($dirStart, $fat, $sectorData);
        $workbookStart = null;
        $workbookSize = 0;
        $rootStart = null;
        for ($off = 0; $off + 128 <= \strlen($dir); $off += 128) {
            $nameLen = unpack('v', substr($dir, $off + 64, 2))[1];
            if ($nameLen < 2) {
                continue;
            }
            $name = mb_convert_encoding(substr($dir, $off, $nameLen - 2), 'UTF-8', 'UTF-16LE');
            $type = \ord($dir[$off + 66]);
            $start = unpack('V', substr($dir, $off + 116, 4))[1];
            $size = unpack('V', substr($dir, $off + 120, 4))[1];
            if ($type === 5) {
                $rootStart = $start;
            } elseif (\in_array($name, ['Workbook', 'Book'], true)) {
                $workbookStart = $start;
                $workbookSize = $size;
            }
        }
        if ($workbookStart === null) {
            throw new \InvalidArgumentException('Fișierul nu conține un registru Excel (Workbook).');
        }

        if ($workbookSize >= $miniCutoff) {
            return substr($readChain($workbookStart, $fat, $sectorData), 0, $workbookSize);
        }

        // Stream mic: locuiește în mini-stream-ul root-ului, cu miniFAT propriu.
        $miniFat = [];
        foreach (array_values(unpack('V*', $readChain($miniFatStart, $fat, $sectorData))) as $entry) {
            $miniFat[] = $entry;
        }
        $miniStream = $readChain((int) $rootStart, $fat, $sectorData);
        $miniData = static fn (int $sector): string => substr($miniStream, $sector * $miniSize, $miniSize);

        return substr($readChain($workbookStart, $miniFat, $miniData), 0, $workbookSize);
    }

    // ---------------------------------------------------------------- BIFF8

    /** @return list<list<string>> */
    private function parseWorkbook(string $stream): array
    {
        $len = \strlen($stream);
        $pos = 0;
        $sst = [];
        $firstSheetOffset = null;

        // Substream-ul global: SST + BOUNDSHEET (offsetul primei foi).
        while ($pos + 4 <= $len) {
            [$id, $size] = array_values(unpack('vid/vsize', substr($stream, $pos, 4)));
            $data = substr($stream, $pos + 4, $size);
            $pos += 4 + $size;

            if ($id === 0x00FC) { // SST — poate continua în înregistrări CONTINUE
                $chunks = [$data];
                while ($pos + 4 <= $len) {
                    [$cid, $csize] = array_values(unpack('vid/vsize', substr($stream, $pos, 4)));
                    if ($cid !== 0x003C) {
                        break;
                    }
                    $chunks[] = substr($stream, $pos + 4, $csize);
                    $pos += 4 + $csize;
                }
                $sst = $this->parseSst($chunks);
            } elseif ($id === 0x0085) { // BOUNDSHEET
                $offset = unpack('V', substr($data, 0, 4))[1];
                if ($firstSheetOffset === null) {
                    $firstSheetOffset = $offset;
                }
            } elseif ($id === 0x000A) { // EOF-ul substream-ului global
                break;
            }
        }
        if ($firstSheetOffset === null) {
            throw new \InvalidArgumentException('Fișierul XLS nu conține nicio foaie.');
        }

        // Substream-ul primei foi.
        $cells = [];
        $maxRow = -1;
        $pos = $firstSheetOffset;
        $pendingFormulaCell = null;
        while ($pos + 4 <= $len) {
            [$id, $size] = array_values(unpack('vid/vsize', substr($stream, $pos, 4)));
            $data = substr($stream, $pos + 4, $size);
            $pos += 4 + $size;

            switch ($id) {
                case 0x000A: // EOF — foaia s-a terminat
                    break 2;
                case 0x00FD: // LABELSST
                    [$r, $c] = array_values(unpack('vr/vc', substr($data, 0, 4)));
                    $isst = unpack('V', substr($data, 6, 4))[1];
                    $cells[$r][$c] = $sst[$isst] ?? '';
                    $maxRow = max($maxRow, $r);
                    break;
                case 0x0204: // LABEL (șir inline, moștenire)
                    [$r, $c] = array_values(unpack('vr/vc', substr($data, 0, 4)));
                    $cells[$r][$c] = $this->readUnicodeString($data, 6)[0];
                    $maxRow = max($maxRow, $r);
                    break;
                case 0x0203: // NUMBER
                    [$r, $c] = array_values(unpack('vr/vc', substr($data, 0, 4)));
                    $cells[$r][$c] = $this->numberToString(unpack('e', substr($data, 6, 8))[1]);
                    $maxRow = max($maxRow, $r);
                    break;
                case 0x027E: // RK
                    [$r, $c] = array_values(unpack('vr/vc', substr($data, 0, 4)));
                    $cells[$r][$c] = $this->numberToString($this->decodeRk(substr($data, 6, 4)));
                    $maxRow = max($maxRow, $r);
                    break;
                case 0x00BD: // MULRK
                    [$r, $cFirst] = array_values(unpack('vr/vc', substr($data, 0, 4)));
                    $count = intdiv($size - 6, 6);
                    for ($i = 0; $i < $count; ++$i) {
                        $cells[$r][$cFirst + $i] = $this->numberToString($this->decodeRk(substr($data, 4 + $i * 6 + 2, 4)));
                    }
                    $maxRow = max($maxRow, $r);
                    break;
                case 0x0006: // FORMULA — păstrăm rezultatul numeric sau așteptăm STRING
                    [$r, $c] = array_values(unpack('vr/vc', substr($data, 0, 4)));
                    $result = substr($data, 6, 8);
                    if (substr($result, 6, 2) === "\xFF\xFF") {
                        $kind = \ord($result[0]);
                        if ($kind === 0) { // rezultat text → urmează STRING
                            $pendingFormulaCell = [$r, $c];
                        }
                        // bool/eroare: le lăsăm goale
                    } else {
                        $cells[$r][$c] = $this->numberToString(unpack('e', $result)[1]);
                    }
                    $maxRow = max($maxRow, $r);
                    break;
                case 0x0207: // STRING (rezultatul formulei precedente)
                    if ($pendingFormulaCell !== null) {
                        [$r, $c] = $pendingFormulaCell;
                        $cells[$r][$c] = $this->readUnicodeString($data, 0, true)[0];
                        $pendingFormulaCell = null;
                    }
                    break;
                default:
                    // BLANK/MULBLANK/stiluri/dimensiuni — irelevante pentru valori.
                    break;
            }
        }

        $rows = [];
        for ($r = 0; $r <= $maxRow; ++$r) {
            $rowCells = $cells[$r] ?? [];
            if ($rowCells === []) {
                $rows[] = [];
                continue;
            }
            $maxCol = max(array_keys($rowCells));
            $row = [];
            for ($c = 0; $c <= $maxCol; ++$c) {
                $row[] = trim((string) ($rowCells[$c] ?? ''));
            }
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * SST: [total u32][unic u32] apoi șiruri XLUnicodeRichExtendedString,
     * care pot fi tăiate de granițe CONTINUE (fiecare continuare reia cu un
     * octet de flags propriu).
     *
     * @param list<string> $chunks
     *
     * @return list<string>
     */
    private function parseSst(array $chunks): array
    {
        $chunkIdx = 0;
        $pos = 8; // sărim peste cele două contoare
        $strings = [];

        $need = function (int $n) use (&$chunkIdx, &$pos, $chunks): string {
            $out = '';
            while ($n > 0) {
                $avail = \strlen($chunks[$chunkIdx]) - $pos;
                if ($avail <= 0) {
                    ++$chunkIdx;
                    $pos = 0;
                    continue;
                }
                $take = min($n, $avail);
                $out .= substr($chunks[$chunkIdx], $pos, $take);
                $pos += $take;
                $n -= $take;
            }

            return $out;
        };

        $total = unpack('V', substr($chunks[0], 4, 4))[1];
        for ($i = 0; $i < $total; ++$i) {
            // Antetul șirului nu este tăiat de CONTINUE (doar caracterele pot fi).
            if (\strlen($chunks[$chunkIdx]) - $pos < 3) {
                ++$chunkIdx;
                $pos = 0;
            }
            $cch = unpack('v', $need(2))[1];
            $flags = \ord($need(1));
            $richRuns = ($flags & 0x08) ? unpack('v', $need(2))[1] : 0;
            $extSize = ($flags & 0x04) ? unpack('V', $need(4))[1] : 0;

            $text = '';
            $remaining = $cch;
            $wide = (bool) ($flags & 0x01);
            while ($remaining > 0) {
                $avail = \strlen($chunks[$chunkIdx]) - $pos;
                if ($avail <= 0) {
                    // Continuarea reia cu propriul octet de flags (lățimea se poate schimba).
                    ++$chunkIdx;
                    $pos = 0;
                    $wide = (bool) (\ord($need(1)) & 0x01);
                    continue;
                }
                $charBytes = $wide ? 2 : 1;
                $fit = min($remaining, intdiv($avail, $charBytes));
                if ($fit === 0) { // caracter wide tăiat la graniță — ia din continuare
                    ++$chunkIdx;
                    $pos = 0;
                    $wide = (bool) (\ord($need(1)) & 0x01);
                    continue;
                }
                $bytes = $need($fit * $charBytes);
                $text .= $wide
                    ? mb_convert_encoding($bytes, 'UTF-8', 'UTF-16LE')
                    : mb_convert_encoding($bytes, 'UTF-8', 'ISO-8859-1');
                $remaining -= $fit;
            }

            if ($richRuns > 0) {
                $need($richRuns * 4);
            }
            if ($extSize > 0) {
                $need($extSize);
            }
            $strings[] = $text;
        }

        return $strings;
    }

    /** @return array{0: string, 1: int} [text, octeți consumați] */
    private function readUnicodeString(string $data, int $offset, bool $cch16 = true): array
    {
        $cch = $cch16 ? unpack('v', substr($data, $offset, 2))[1] : \ord($data[$offset]);
        $offset += $cch16 ? 2 : 1;
        $flags = \ord($data[$offset]);
        ++$offset;
        if ($flags & 0x01) {
            $text = mb_convert_encoding(substr($data, $offset, $cch * 2), 'UTF-8', 'UTF-16LE');
        } else {
            $text = mb_convert_encoding(substr($data, $offset, $cch), 'UTF-8', 'ISO-8859-1');
        }

        return [$text, 0];
    }

    private function decodeRk(string $bytes): float
    {
        $rk = unpack('V', $bytes)[1];
        $div100 = (bool) ($rk & 0x01);
        if ($rk & 0x02) { // întreg pe 30 de biți, cu semn
            $value = $rk >> 2;
            if ($value & 0x20000000) {
                $value -= 0x40000000;
            }
            $number = (float) $value;
        } else {
            $number = unpack('e', "\x00\x00\x00\x00".pack('V', $rk & 0xFFFFFFFC))[1];
        }

        return $div100 ? $number / 100 : $number;
    }

    private function numberToString(float $n): string
    {
        if (floor($n) === $n && abs($n) < 1e15) {
            return (string) (int) $n;
        }

        return rtrim(rtrim(number_format($n, 6, '.', ''), '0'), '.');
    }
}
