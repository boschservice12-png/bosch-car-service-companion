<?php

declare(strict_types=1);

namespace App\Identity\Presentation;

use App\Identity\Domain\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Impune 2FA pentru operațiunile de admin: dacă un SERVICE_ADMIN fără 2FA
 * activat accesează o rută `/api/admin/*`, primește 403 și trebuie să își
 * activeze mai întâi 2FA (prin `/api/2fa/*`, care este exceptat). Astfel nu
 * există conturi privilegiate protejate doar cu parolă.
 */
#[AsEventListener(event: RequestEvent::class, priority: 4)]
final class AdminTwoFactorEnforcementListener
{
    public function __construct(private readonly Security $security)
    {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        if (!str_starts_with($event->getRequest()->getPathInfo(), '/api/admin')) {
            return;
        }

        $user = $this->security->getUser();
        if ($user instanceof User && $user->isServiceAdmin() && !$user->totpEnabled()) {
            throw new AccessDeniedException('Trebuie să activați autentificarea în doi pași (2FA) înainte de operațiunile de administrare.');
        }
    }
}
