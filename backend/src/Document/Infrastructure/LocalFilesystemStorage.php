<?php

declare(strict_types=1);

namespace App\Document\Infrastructure;

use App\Document\Domain\StorageAdapter;
use RuntimeException;

/**
 * Storage privat pe disc pentru dezvoltare. Rădăcina este în afara `public/`,
 * deci fișierele NU sunt accesibile direct prin web server. Structura de chei
 * folosește subdirectoare (primele 2 caractere) pentru a evita directoare uriașe.
 */
final class LocalFilesystemStorage implements StorageAdapter
{
    public function __construct(private readonly string $rootDir)
    {
    }

    public function store(string $sourcePath, string $key, string $mimeType): void
    {
        $target = $this->pathFor($key);
        $dir = \dirname($target);
        if (!is_dir($dir) && !mkdir($dir, 0770, true) && !is_dir($dir)) {
            throw new RuntimeException('Nu s-a putut crea directorul de storage.');
        }
        if (!copy($sourcePath, $target)) {
            throw new RuntimeException('Nu s-a putut salva fișierul în storage.');
        }
        @chmod($target, 0640);
    }

    public function read(string $key): string
    {
        $path = $this->pathFor($key);
        if (!is_file($path)) {
            throw new RuntimeException('Fișier inexistent în storage.');
        }
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException('Nu s-a putut citi fișierul din storage.');
        }

        return $contents;
    }

    public function exists(string $key): bool
    {
        return is_file($this->pathFor($key));
    }

    public function delete(string $key): void
    {
        $path = $this->pathFor($key);
        if (is_file($path)) {
            @unlink($path);
        }
    }

    private function pathFor(string $key): string
    {
        // Cheia este generată intern (hex) — validăm împotriva path traversal.
        if (preg_match('#^[a-zA-Z0-9/_.-]+$#', $key) !== 1 || str_contains($key, '..')) {
            throw new RuntimeException('Cheie de storage invalidă.');
        }

        return rtrim($this->rootDir, '/').'/'.$key;
    }
}
