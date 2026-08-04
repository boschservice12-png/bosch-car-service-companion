<?php

declare(strict_types=1);

namespace App\Document\Infrastructure;

use App\Document\Domain\MalwareScanner;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Scaner antimalware pentru PRODUCȚIE — trimite fișierul către daemon-ul clamd
 * prin protocolul INSTREAM (TCP). Dacă daemon-ul este indisponibil, aruncă
 * excepție (fail-closed): un fișier nescanat NU devine servibil.
 */
final class ClamAvScanner implements MalwareScanner
{
    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly LoggerInterface $logger,
        private readonly int $timeoutSeconds = 30,
        // Sonda de readiness are propriul timeout, mult mai scurt: un clamd mort
        // nu are voie să țină `/api/health/ready` 30 de secunde în așteptare.
        private readonly int $probeTimeoutSeconds = 2,
    ) {
    }

    /**
     * PING/PONG pe protocolul clamd — nu transferă niciun fișier. Orice altceva
     * decât „PONG" (conexiune refuzată, timeout, daemon care încă își încarcă
     * bazele de semnături) înseamnă indisponibil.
     */
    public function isAvailable(): bool
    {
        $socket = @fsockopen($this->host, $this->port, $errno, $errstr, (float) $this->probeTimeoutSeconds);
        if ($socket === false) {
            $this->logger->warning('malware_scan.probe_unavailable', ['error' => $errstr]);

            return false;
        }

        try {
            stream_set_timeout($socket, $this->probeTimeoutSeconds);
            fwrite($socket, "zPING\0");
            $response = trim((string) fgets($socket));
        } catch (\Throwable) {
            return false;
        } finally {
            fclose($socket);
        }

        return $response === 'PONG';
    }

    public function isClean(string $sourcePath): bool
    {
        $socket = @fsockopen($this->host, $this->port, $errno, $errstr, (float) $this->timeoutSeconds);
        if ($socket === false) {
            $this->logger->error('malware_scan.unavailable', ['error' => $errstr]);
            throw new RuntimeException('Scanerul antimalware este indisponibil.');
        }

        try {
            fwrite($socket, "zINSTREAM\0");
            $stream = fopen($sourcePath, 'rb');
            if ($stream === false) {
                throw new RuntimeException('Nu s-a putut citi fișierul pentru scanare.');
            }
            while (!feof($stream)) {
                $chunk = fread($stream, 8192);
                if ($chunk === false || $chunk === '') {
                    break;
                }
                fwrite($socket, pack('N', \strlen($chunk)).$chunk);
            }
            fclose($stream);
            fwrite($socket, pack('N', 0)); // terminator
            $response = trim((string) fgets($socket));
        } finally {
            fclose($socket);
        }

        // Răspuns clamd: "stream: OK" sau "stream: <semnătură> FOUND".
        return str_ends_with($response, 'OK');
    }
}
