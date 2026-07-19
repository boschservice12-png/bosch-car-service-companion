<?php

declare(strict_types=1);

namespace App\Identity\Presentation;

use App\Identity\Domain\User;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
use Symfony\Component\Security\Http\Authenticator\Token\PostAuthenticationToken;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

final class LoginSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    public function onAuthenticationSuccess(Request $request, TokenInterface $token): Response
    {
        $user = $token->getUser();
        \assert($user instanceof User);

        // P0-06: fiecare login pornește NEVERIFICAT — altfel un flag rămas în
        // sesiune (ex. re-login fără logout) ar sări peste pasul al doilea.
        if ($request->hasSession()) {
            $request->getSession()->remove(TwoFactorController::SESSION_VERIFIED);
        }

        return new JsonResponse([
            'id' => (string) $user->id(),
            'email' => $user->getEmail(),
            'role' => $user->isServiceAdmin() ? 'SERVICE_ADMIN' : 'CLIENT',
            'requiresOtp' => $user->isServiceAdmin() && $user->totpEnabled(),
        ]);
    }
}
