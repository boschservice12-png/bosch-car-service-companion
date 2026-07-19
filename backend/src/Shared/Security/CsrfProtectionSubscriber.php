<?php

declare(strict_types=1);

namespace App\Shared\Security;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * P0-05 — protecție CSRF „double submit cookie" pe TOATE cererile care
 * modifică stare (/api + POST/PUT/PATCH/DELETE), inclusiv login/logout:
 *
 *  - browserul primește un cookie NE-HttpOnly `bcsc_csrf` (emis la
 *    GET /api/csrf); frontendul îl citește și îl trimite înapoi în antetul
 *    `X-CSRF-Token` la fiecare cerere modificatoare;
 *  - un site străin poate declanșa cererea (cookie-ul pleacă automat), dar
 *    NU poate citi cookie-ul și deci nu poate seta antetul → 403;
 *  - opțional, CSRF_TRUSTED_ORIGINS (listă separată prin virgulă) impune și
 *    un allowlist pe antetul Origin (recomandat în producție, unde originile
 *    frontendurilor sunt cunoscute);
 *  - la logout cookie-ul se șterge; următoarea sesiune primește alt token
 *    (rotire per sesiune de browser). Tokenul nu acordă acces prin el
 *    însuși — perechea cookie+antet doar dovedește că cererea vine din
 *    aplicația noastră, nu dintr-un formular străin.
 *
 * Verificarea rulează ÎNAINTEA firewall-ului (prioritate 30 > 8), deci și
 * autentificarea este protejată.
 */
final class CsrfProtectionSubscriber implements EventSubscriberInterface
{
    public const COOKIE = 'bcsc_csrf';
    private const PROTECTED_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    public function __construct(private readonly string $trustedOrigins = '')
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onRequest', 30],
            KernelEvents::RESPONSE => ['onResponse', 0],
        ];
    }

    public function onRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        $request = $event->getRequest();
        if (!str_starts_with($request->getPathInfo(), '/api')) {
            return;
        }
        if (!\in_array($request->getMethod(), self::PROTECTED_METHODS, true)) {
            return;
        }

        // Allowlist de origini (activ doar dacă este configurat).
        $origin = $request->headers->get('Origin');
        $allowed = array_filter(array_map(trim(...), explode(',', $this->trustedOrigins)));
        if ($origin !== null && $allowed !== [] && !\in_array(rtrim($origin, '/'), $allowed, true)) {
            throw new AccessDeniedHttpException('Origine neacceptată (CSRF).');
        }

        $cookie = (string) $request->cookies->get(self::COOKIE, '');
        $header = (string) $request->headers->get('X-CSRF-Token', '');
        if ($cookie === '' || $header === '' || !hash_equals($cookie, $header)) {
            throw new AccessDeniedHttpException('Token CSRF lipsă sau invalid.');
        }
    }

    public function onResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        $request = $event->getRequest();
        $route = $request->attributes->get('_route');

        // Emiterea tokenului: GET /api/csrf setează cookie-ul dacă lipsește.
        if ($route === 'api_csrf' && (string) $request->cookies->get(self::COOKIE, '') === '') {
            $event->getResponse()->headers->setCookie(self::mint($request));
        }

        // Rotire la logout: cookie-ul se șterge; sesiunea următoare ia altul.
        if ($route === 'api_auth_logout') {
            $event->getResponse()->headers->clearCookie(self::COOKIE, '/', null, $request->isSecure(), false, 'lax');
        }
    }

    private static function mint(Request $request): Cookie
    {
        return Cookie::create(
            self::COOKIE,
            bin2hex(random_bytes(16)),
            0,
            '/',
            null,
            $request->isSecure(),
            false,   // NE-HttpOnly: frontendul trebuie să îl poată citi
            false,
            Cookie::SAMESITE_LAX,
        );
    }
}
