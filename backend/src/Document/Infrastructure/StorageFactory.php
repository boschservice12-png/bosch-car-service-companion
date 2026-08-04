<?php

declare(strict_types=1);

namespace App\Document\Infrastructure;

use App\Document\Domain\StorageAdapter;

/**
 * Blocul 6 — selectează implementarea de storage în funcție de STORAGE_DRIVER:
 *  - `local` (implicit): disc privat, pentru dev/demo cu volum persistent;
 *  - `s3`: bucket S3-compatibil (MinIO / AWS), pentru producție.
 *
 * Ambele implementări sunt construibile mereu (doar argumente scalare, fără
 * conexiune la construire), deci comutarea nu cere reconfigurarea containerului.
 */
final class StorageFactory
{
    public function __construct(
        private readonly LocalFilesystemStorage $local,
        private readonly S3Storage $s3,
        private readonly string $driver,
    ) {
    }

    public function create(): StorageAdapter
    {
        return 's3' === strtolower(trim($this->driver)) ? $this->s3 : $this->local;
    }
}
