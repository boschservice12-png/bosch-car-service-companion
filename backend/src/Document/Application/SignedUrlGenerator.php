<?php

declare(strict_types=1);

namespace App\Document\Application;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Generează și validează URL-uri temporare semnate pentru descărcarea
 * documentelor. Semnătura HMAC leagă id-ul documentului de un timestamp de
 * expirare; URL-ul este în plus protejat de firewall (autentificare) și de
 * autorizarea la nivel de obiect (DocumentVoter) în controller.
 */
final class SignedUrlGenerator
{
    public function __construct(
        private readonly string $secret,
        private readonly UrlGeneratorInterface $urls,
    ) {
    }

    /** @return array{url: string, expiresAt: int} */
    public function generate(string $documentId, int $ttlSeconds): array
    {
        $expires = time() + $ttlSeconds;
        $sig = $this->sign($documentId, $expires);

        $url = $this->urls->generate(
            'api_documents_raw',
            ['id' => $documentId, 'expires' => $expires, 'sig' => $sig],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        return ['url' => $url, 'expiresAt' => $expires];
    }

    public function isValid(string $documentId, int $expires, string $sig): bool
    {
        if ($expires < time()) {
            return false;
        }

        return hash_equals($this->sign($documentId, $expires), $sig);
    }

    private function sign(string $documentId, int $expires): string
    {
        return hash_hmac('sha256', $documentId.'|'.$expires, $this->secret);
    }
}
