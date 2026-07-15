<?php

declare(strict_types=1);

namespace App\Shared\Presentation;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

/**
 * CORS restrictiv: doar originile din `app.cors_allow_origin`. Permite cookie-uri
 * (credentials) pentru sesiunea bazată pe cookie httpOnly.
 */
final class CorsListener
{
    /** @var string[] */
    private array $allowed;

    public function __construct(string $allowedOrigins)
    {
        $this->allowed = array_filter(array_map('trim', explode(',', $allowedOrigins)));
    }

    public function onRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if ($request->getMethod() !== 'OPTIONS') {
            return;
        }

        $origin = $request->headers->get('Origin');
        if ($origin === null || !in_array($origin, $this->allowed, true)) {
            return;
        }

        $event->setResponse(new Response('', 204, $this->headers($origin)));
    }

    public function onResponse(ResponseEvent $event): void
    {
        $origin = $event->getRequest()->headers->get('Origin');
        if ($origin !== null && in_array($origin, $this->allowed, true)) {
            $event->getResponse()->headers->add($this->headers($origin));
        }
    }

    /** @return array<string, string> */
    private function headers(string $origin): array
    {
        return [
            'Access-Control-Allow-Origin' => $origin,
            'Access-Control-Allow-Credentials' => 'true',
            'Access-Control-Allow-Methods' => 'GET, POST, PATCH, DELETE, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, X-Requested-With',
            'Vary' => 'Origin',
        ];
    }
}
