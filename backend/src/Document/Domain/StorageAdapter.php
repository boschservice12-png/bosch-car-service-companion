<?php

declare(strict_types=1);

namespace App\Document\Domain;

/**
 * Contract de stocare privată. Implementări:
 *  - LocalFilesystemStorage (dev): volum privat pe disc, în afara `public/`;
 *  - S3Storage (producție): bucket S3-compatible privat (fără acces public).
 *
 * Conținutul nu este niciodată expus direct — servirea trece prin URL temporar
 * semnat + verificare de autorizare (vezi DownloadUrlService / DocumentController).
 */
interface StorageAdapter
{
    /** Stochează fișierul local (path temporar) sub cheia dată. */
    public function store(string $sourcePath, string $key, string $mimeType): void;

    /** Returnează conținutul binar al fișierului. */
    public function read(string $key): string;

    public function exists(string $key): bool;

    public function delete(string $key): void;
}
