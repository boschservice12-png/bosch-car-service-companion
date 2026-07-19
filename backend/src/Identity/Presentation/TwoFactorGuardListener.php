<?php

declare(strict_types=1);

namespace App\Identity\Presentation;

use App\Identity\Domain\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * P0-06 — gardianul 2FA. Rulează după firewall (prioritate 6 < 8):
 *
 *  1. un admin cu 2FA activ care NU a trecut încă de verify în sesiunea
 *     curentă primește 403 `two_factor_required` pe tot API-ul, cu excepția
 *     rutelor necesare deblocării (verify, logout, csrf, me, health, settings);
 *  2. în producție (parametrul admin_2fa_enforce_enrollment), un admin FĂRĂ
 *     2FA activ nu poate folosi rutele /api/admin până nu se înrolează —
 *     conturile privilegiate nu rămân protejate doar prin parolă (ADR-0002).
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 6)]
final class TwoFactorGuardListener
{
    private const PENDING_ALLOWED = [
        '/api/auth/2fa/verify',
        '/api/auth/logout',
        '/api/csrf',
        '/api/health',
        '/api/me',
        '/api/settings',
    ];

    public function __construct(
        private readonly Security $security,
        #[Autowire('%admin_2fa_enforce_enrollment%')]
        private readonly bool $enforceEnrollment,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();
        if (!str_starts_with($path, '/api')) {
            return;
        }

        $user = $this->security->getUser();
        if (!$user instanceof User || !$user->isServiceAdmin()) {
            return;
        }

        if ($user->totpEnabled()) {
            if (!$this->sessionVerified($request) && !\in_array($path, self::PENDING_ALLOWED, true)) {
                $event->setResponse(new JsonResponse([
                    'type' => 'two_factor_required',
                    'title' => 'Este necesară verificarea în doi factori.',
                    'status' => 403,
                ], 403, ['Content-Type' => 'application/problem+json']));
            }

            return;
        }

        if ($this->enforceEnrollment && str_starts_with($path, '/api/admin')) {
            $event->setResponse(new JsonResponse([
                'type' => 'two_factor_enrollment_required',
                'title' => 'Contul de service trebuie să activeze 2FA înainte de a folosi portalul.',
                'status' => 403,
            ], 403, ['Content-Type' => 'application/problem+json']));
        }
    }

    private function sessionVerified(Request $request): bool
    {
        return $request->hasSession()
            && $request->getSession()->get(TwoFactorController::SESSION_VERIFIED) === true;
    }
}
