<?php

declare(strict_types=1);

namespace App\Document\Infrastructure;

use App\Document\Domain\StorageAdapter;
use RuntimeException;

/**
 * Blocul 6 — storage S3-compatibil (MinIO / AWS S3) pentru producție. Semnătura
 * AWS Signature V4 e implementată direct (fără SDK, fără dependență nouă), cu
 * adresare path-style pentru compatibilitate MinIO. Se activează prin
 * STORAGE_DRIVER=s3.
 *
 * Conținutul rămâne privat (bucket fără acces public); servirea trece prin
 * URL-uri semnate + verificare de autorizare, exact ca la varianta locală.
 */
final class S3Storage implements StorageAdapter
{
    private readonly string $endpoint;

    public function __construct(
        string $endpoint,
        private readonly string $bucket,
        private readonly string $accessKey,
        private readonly string $secretKey,
        private readonly string $region = 'us-east-1',
    ) {
        $this->endpoint = rtrim($endpoint, '/');
    }

    public function store(string $sourcePath, string $key, string $mimeType): void
    {
        $body = file_get_contents($sourcePath);
        if ($body === false) {
            throw new RuntimeException('Nu s-a putut citi fișierul sursă pentru upload.');
        }
        [$status] = $this->request('PUT', $key, $body, ['content-type' => $mimeType]);
        if ($status < 200 || $status >= 300) {
            throw new RuntimeException('Upload S3 eșuat (HTTP '.$status.').');
        }
    }

    public function read(string $key): string
    {
        [$status, $body] = $this->request('GET', $key, '');
        if ($status === 404) {
            throw new RuntimeException('Fișier inexistent în storage.');
        }
        if ($status < 200 || $status >= 300) {
            throw new RuntimeException('Citire S3 eșuată (HTTP '.$status.').');
        }

        return $body;
    }

    public function exists(string $key): bool
    {
        [$status] = $this->request('HEAD', $key, '');

        return $status >= 200 && $status < 300;
    }

    public function delete(string $key): void
    {
        [$status] = $this->request('DELETE', $key, '');
        // 404 la ștergere e idempotent-ok (obiectul deja lipsește).
        if ($status >= 300 && $status !== 404) {
            throw new RuntimeException('Ștergere S3 eșuată (HTTP '.$status.').');
        }
    }

    /**
     * @param array<string, string> $extraHeaders
     *
     * @return array{0: int, 1: string}
     */
    private function request(string $method, string $key, string $body, array $extraHeaders = []): array
    {
        $this->validateKey($key);

        $host = (string) parse_url($this->endpoint, PHP_URL_HOST);
        $port = parse_url($this->endpoint, PHP_URL_PORT);
        $hostHeader = $port ? $host.':'.$port : $host;

        $encodedPath = '/'.rawurlencode($this->bucket).'/'.$this->encodeKey($key);
        $url = $this->endpoint.$encodedPath;

        $amzDate = gmdate('Ymd\THis\Z');
        $dateStamp = gmdate('Ymd');
        $payloadHash = hash('sha256', $body);

        $headers = array_merge([
            'host' => $hostHeader,
            'x-amz-content-sha256' => $payloadHash,
            'x-amz-date' => $amzDate,
        ], $extraHeaders);
        ksort($headers);

        $canonicalHeaders = '';
        foreach ($headers as $name => $value) {
            $canonicalHeaders .= strtolower($name).':'.trim($value)."\n";
        }
        $signedHeaders = implode(';', array_map('strtolower', array_keys($headers)));

        $canonicalRequest = implode("\n", [
            $method,
            $encodedPath,
            '', // fără query string
            $canonicalHeaders,
            $signedHeaders,
            $payloadHash,
        ]);

        $scope = $dateStamp.'/'.$this->region.'/s3/aws4_request';
        $stringToSign = implode("\n", [
            'AWS4-HMAC-SHA256',
            $amzDate,
            $scope,
            hash('sha256', $canonicalRequest),
        ]);

        $kDate = hash_hmac('sha256', $dateStamp, 'AWS4'.$this->secretKey, true);
        $kRegion = hash_hmac('sha256', $this->region, $kDate, true);
        $kService = hash_hmac('sha256', 's3', $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
        $signature = hash_hmac('sha256', $stringToSign, $kSigning);

        $authorization = 'AWS4-HMAC-SHA256 Credential='.$this->accessKey.'/'.$scope
            .', SignedHeaders='.$signedHeaders.', Signature='.$signature;

        $curlHeaders = ['Authorization: '.$authorization, 'Expect:'];
        foreach ($headers as $name => $value) {
            $curlHeaders[] = $name.': '.$value;
        }

        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('Nu s-a putut inițializa clientul S3.');
        }
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $curlHeaders,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_NOBODY => 'HEAD' === $method,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 5,
        ]);
        if ('PUT' === $method) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $response = curl_exec($ch);
        if (false === $response) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException('Conexiune S3 eșuată: '.$err);
        }
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        return [$status, \is_string($response) ? $response : ''];
    }

    /** Encodează fiecare segment al cheii păstrând slash-urile de separare. */
    private function encodeKey(string $key): string
    {
        return implode('/', array_map('rawurlencode', explode('/', $key)));
    }

    private function validateKey(string $key): void
    {
        if (1 !== preg_match('#^[a-zA-Z0-9/_.-]+$#', $key) || str_contains($key, '..')) {
            throw new RuntimeException('Cheie de storage invalidă.');
        }
    }
}
