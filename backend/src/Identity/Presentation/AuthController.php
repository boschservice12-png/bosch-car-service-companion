<?php

declare(strict_types=1);

namespace App\Identity\Presentation;

use App\Identity\Domain\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class AuthController extends AbstractController
{
    /**
     * Rută interceptată de firewall-ul `json_login` (username_path=email,
     * password_path=password). Corpul metodei nu este executat.
     */
    #[Route('/api/auth/login', name: 'api_auth_login', methods: ['POST'])]
    public function login(): never
    {
        throw new \LogicException('Interceptat de firewall (json_login).');
    }

    /** Rută interceptată de firewall-ul `logout`. */
    #[Route('/api/auth/logout', name: 'api_auth_logout', methods: ['POST'])]
    public function logout(): never
    {
        throw new \LogicException('Interceptat de firewall (logout).');
    }

    #[Route('/api/me', name: 'api_me', methods: ['GET'])]
    public function me(Request $request): JsonResponse
    {
        $user = $this->getUser();
        \assert($user instanceof User);

        $profile = $user->customerProfile();
        $requiresOtp = $user->isServiceAdmin()
            && $user->totpEnabled()
            && (!$request->hasSession() || $request->getSession()->get(TwoFactorController::SESSION_VERIFIED) !== true);

        return $this->json([
            'id' => (string) $user->id(),
            'email' => $user->getEmail(),
            'role' => $user->isServiceAdmin() ? 'SERVICE_ADMIN' : 'CLIENT',
            'name' => $profile?->fullName() ?: null,
            'totpEnabled' => $user->totpEnabled(),
            'requiresOtp' => $requiresOtp,
        ]);
    }
}
