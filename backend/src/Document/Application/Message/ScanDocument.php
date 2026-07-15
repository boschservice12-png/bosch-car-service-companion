<?php

declare(strict_types=1);

namespace App\Document\Application\Message;

/** Mesaj asincron: scanează antimalware documentul dat. */
final class ScanDocument
{
    public function __construct(public readonly string $documentId)
    {
    }
}
