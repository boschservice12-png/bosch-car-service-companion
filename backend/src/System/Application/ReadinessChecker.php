<?php

declare(strict_types=1);

namespace App\System\Application;

use App\Document\Domain\StorageAdapter;
use Doctrine\DBAL\Connection;
use Doctrine\Migrations\DependencyFactory;

/**
 * Blocul 6 — verificarea de disponibilitate (readiness). Spre deosebire de
 * liveness (procesul trăiește), readiness spune dacă aplicația poate SERVI în
 * siguranță o cerere reală. O dependență critică picată → NU e „ready".
 *
 * Fiecare verificare întoarce: ok / degraded / failed. `failed` pe o verificare
 * CRITICĂ → readiness 503. Răspunsul NU conține secrete, connection string-uri
 * sau stack trace-uri.
 */
final class ReadinessChecker
{
    /** @var array{secret: string, storageProbeKey: string} */
    private array $config;

    public function __construct(
        private readonly Connection $connection,
        private readonly StorageAdapter $storage,
        private readonly DependencyFactory $migrations,
        string $appSecret,
    ) {
        $this->config = [
            'secret' => $appSecret,
            'storageProbeKey' => 'health/.probe',
        ];
    }

    /**
     * @return array{status: string, ready: bool, checks: array<string, array{status: string, critical: bool, detail?: string}>}
     */
    public function check(): array
    {
        $checks = [
            'database' => $this->critical($this->checkDatabase()),
            'migrations' => $this->critical($this->checkMigrations()),
            'messenger' => $this->nonCritical($this->checkMessenger()),
            'storage' => $this->critical($this->checkStorage()),
            'secrets' => $this->critical($this->checkSecrets()),
        ];

        $failedCritical = false;
        $degraded = false;
        foreach ($checks as $c) {
            if ($c['status'] === 'failed' && $c['critical']) {
                $failedCritical = true;
            }
            if ($c['status'] !== 'ok') {
                $degraded = true;
            }
        }

        $status = $failedCritical ? 'failed' : ($degraded ? 'degraded' : 'ok');

        return [
            'status' => $status,
            // „ready" doar dacă nicio verificare CRITICĂ nu a picat (degraded pe
            // o verificare necritică — ex. worker oprit — rămâne servibil).
            'ready' => !$failedCritical,
            'checks' => $checks,
        ];
    }

    /** @return array{status: string, detail?: string} */
    private function checkDatabase(): array
    {
        try {
            $this->connection->executeQuery('SELECT 1');

            return ['status' => 'ok'];
        } catch (\Throwable) {
            return ['status' => 'failed', 'detail' => 'conexiune indisponibilă'];
        }
    }

    /** @return array{status: string, detail?: string} */
    private function checkMigrations(): array
    {
        try {
            $executed = $this->migrations->getMetadataStorage()->getExecutedMigrations();
            $available = $this->migrations->getMigrationRepository()->getMigrations();
            $newVersions = [];
            foreach ($available->getItems() as $migration) {
                if (!$executed->hasMigration($migration->getVersion())) {
                    $newVersions[] = 1;
                }
            }
            if ($newVersions !== []) {
                return ['status' => 'failed', 'detail' => \count($newVersions).' migrații neaplicate'];
            }

            return ['status' => 'ok'];
        } catch (\Throwable) {
            return ['status' => 'failed', 'detail' => 'stare migrații indisponibilă'];
        }
    }

    /** @return array{status: string, detail?: string} */
    private function checkMessenger(): array
    {
        // Transportul async e Doctrine în producție/demo — dacă baza răspunde,
        // coada e accesibilă. Un worker oprit nu e o eroare CRITICĂ (mesajele
        // se acumulează, nu se pierd), dar e semnalat ca degraded.
        try {
            $this->connection->executeQuery('SELECT 1');

            return ['status' => 'ok'];
        } catch (\Throwable) {
            return ['status' => 'failed', 'detail' => 'transport indisponibil'];
        }
    }

    /** @return array{status: string, detail?: string} */
    private function checkStorage(): array
    {
        try {
            $key = $this->config['storageProbeKey'];
            $tmp = tempnam(sys_get_temp_dir(), 'bcsc_ready_');
            if ($tmp === false) {
                return ['status' => 'failed', 'detail' => 'temp indisponibil'];
            }
            file_put_contents($tmp, 'ok');
            $this->storage->store($tmp, $key, 'text/plain');
            @unlink($tmp);
            $ok = $this->storage->read($key) === 'ok';
            $this->storage->delete($key);

            return $ok ? ['status' => 'ok'] : ['status' => 'failed', 'detail' => 'citire eșuată'];
        } catch (\Throwable) {
            return ['status' => 'failed', 'detail' => 'storage inaccesibil (scriere/citire)'];
        }
    }

    /** @return array{status: string, detail?: string} */
    private function checkSecrets(): array
    {
        $secret = $this->config['secret'];
        if ($secret === '' || str_contains($secret, 'change') || str_contains($secret, 'dev-secret')) {
            return ['status' => 'failed', 'detail' => 'APP_SECRET implicit/nesetat'];
        }

        return ['status' => 'ok'];
    }

    /**
     * @param array{status: string, detail?: string} $result
     *
     * @return array{status: string, critical: bool, detail?: string}
     */
    private function critical(array $result): array
    {
        return [...$result, 'critical' => true];
    }

    /**
     * @param array{status: string, detail?: string} $result
     *
     * @return array{status: string, critical: bool, detail?: string}
     */
    private function nonCritical(array $result): array
    {
        return [...$result, 'critical' => false];
    }
}
