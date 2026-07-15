<?php

declare(strict_types=1);

namespace App\Document\Infrastructure;

use App\Document\Domain\MalwareScanner;
use Psr\Log\LoggerInterface;

/**
 * Adapter de DEZVOLTARE — NU efectuează scanare reală. Marchează totul ca fiind
 * curat și loghează un avertisment explicit. NU trebuie folosit în producție:
 * `MalwareScanner` se leagă la `ClamAvScanner` în mediul de producție (vezi
 * config/services.yaml, secțiunea when@prod / documentația de securitate).
 */
final class PermissiveDevScanner implements MalwareScanner
{
    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    public function isClean(string $sourcePath): bool
    {
        $this->logger->warning('malware_scan.disabled', [
            'reason' => 'PermissiveDevScanner activ — scanare antimalware dezactivată (doar dev).',
        ]);

        return true;
    }
}
