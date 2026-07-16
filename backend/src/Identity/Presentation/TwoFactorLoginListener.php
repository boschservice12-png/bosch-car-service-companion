<?php

declare(strict_types=1);

namespace App\Identity\Presentation;

use App\Identity\Application\TotpService;
use App\Identity\Domain\User;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Http\Event\CheckPassportEvent;

/**
 * Al doilea factor (TOTP) pentru admini: după verificarea parolei, dacă
 * utilizatorul este SERVICE_ADMIN cu 2FA activat, cere un cod TOTP valid din
 * corpul cererii de login (`totpCode`). Prioritate negativă = rulează după
 * verificarea parolei (dacă parola e greșită, nu se ajunge aici).
 */
#[AsEventListener(event: CheckPassportEvent::class, priority: -100)]
final class TwoFactorLoginListener
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly TotpService $totp,
    ) {
    }

    public function __invoke(CheckPassportEvent $event): void
    {
        $user = $event->getPassport()->getUser();
        if (!$user instanceof User || !$user->isServiceAdmin() || !$user->totpEnabled()) {
            return;
        }

        $secret = $user->totpSecret();
        $code = $this->extractCode();
        if ($secret === null || $code === null || !$this->totp->verify($secret, $code)) {
            throw new TwoFactorRequiredException();
        }
    }

    private function extractCode(): ?string
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request === null) {
            return null;
        }
        /** @var array<string, mixed> $data */
        $data = json_decode($request->getContent(), true) ?: [];
        $code = $data['totpCode'] ?? null;

        return \is_string($code) && $code !== '' ? $code : null;
    }
}
