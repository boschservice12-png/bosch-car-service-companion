<?php

declare(strict_types=1);

namespace App\Identity\Presentation;

use App\Audit\Application\AuditRecorder;
use App\Identity\Application\TotpService;
use App\Identity\Domain\User;
use App\Shared\Presentation\ValidationFailedException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Înrolarea și starea 2FA (TOTP) pentru admini. Accesibil doar SERVICE_ADMIN
 * (vezi access_control ^/api/2fa). Nu este blocat de enforcement-ul 2FA, ca
 * adminul să se poată înrola înainte de a activa 2FA.
 */
#[Route('/api/2fa')]
final class TwoFactorController extends AbstractController
{
    public function __construct(
        private readonly TotpService $totp,
        private readonly EntityManagerInterface $em,
        private readonly AuditRecorder $audit,
    ) {
    }

    #[Route('/status', name: 'api_2fa_status', methods: ['GET'])]
    public function status(): JsonResponse
    {
        return $this->json(['enabled' => $this->currentUser()->totpEnabled()]);
    }

    /** Pornește înrolarea: generează un secret și returnează URI-ul pentru QR. */
    #[Route('/setup', name: 'api_2fa_setup', methods: ['POST'])]
    public function setup(): JsonResponse
    {
        $user = $this->currentUser();
        if ($user->totpEnabled()) {
            throw ValidationFailedException::fromArray(['totp' => ['2FA este deja activat.']]);
        }

        $secret = $this->totp->generateSecret();
        $user->startTotpEnrollment($secret);
        $this->em->flush();

        return $this->json([
            'secret' => $secret,
            'provisioningUri' => $this->totp->provisioningUri($secret, $user->getEmail()),
        ]);
    }

    /** Confirmă înrolarea cu un cod valid → activează 2FA. */
    #[Route('/confirm', name: 'api_2fa_confirm', methods: ['POST'])]
    public function confirm(Request $request): JsonResponse
    {
        $user = $this->currentUser();
        $secret = $user->totpSecret();
        $code = $this->code($request);

        if ($secret === null) {
            throw ValidationFailedException::fromArray(['totp' => ['Începeți mai întâi înrolarea (setup).']]);
        }
        if (!$this->totp->verify($secret, $code)) {
            throw ValidationFailedException::fromArray(['code' => ['Cod invalid.']]);
        }

        $user->confirmTotpEnrollment();
        $this->em->flush();
        $this->audit->record('admin.2fa_enabled', 'User', (string) $user->id());

        return $this->json(['enabled' => true]);
    }

    /** Dezactivează 2FA (necesită un cod valid curent). */
    #[Route('/disable', name: 'api_2fa_disable', methods: ['POST'])]
    public function disable(Request $request): JsonResponse
    {
        $user = $this->currentUser();
        $secret = $user->totpSecret();
        if (!$user->totpEnabled() || $secret === null || !$this->totp->verify($secret, $this->code($request))) {
            throw ValidationFailedException::fromArray(['code' => ['Cod invalid sau 2FA inactiv.']]);
        }

        $user->disableTotp();
        $this->em->flush();
        $this->audit->record('admin.2fa_disabled', 'User', (string) $user->id());

        return $this->json(['enabled' => false]);
    }

    private function code(Request $request): string
    {
        /** @var array<string, mixed> $data */
        $data = json_decode($request->getContent(), true) ?: [];

        return \is_string($data['code'] ?? null) ? $data['code'] : '';
    }

    private function currentUser(): User
    {
        $user = $this->getUser();
        \assert($user instanceof User);

        return $user;
    }
}
