<?php

declare(strict_types=1);

namespace App\Identity\Presentation;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Event\LogoutEvent;

/**
 * Deconectare pentru API: setează un răspuns 204 (fără conținut), fără redirect,
 * conform contractului OpenAPI. În Symfony 7 personalizarea răspunsului de logout
 * se face prin LogoutEvent (opțiunea `logout.success_handler` a fost eliminată).
 */
#[AsEventListener(event: LogoutEvent::class)]
final class LogoutResponseListener
{
    public function __invoke(LogoutEvent $event): void
    {
        $event->setResponse(new JsonResponse(null, Response::HTTP_NO_CONTENT));
    }
}
