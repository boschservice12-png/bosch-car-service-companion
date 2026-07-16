<?php

declare(strict_types=1);

namespace App\Identity\Presentation;

use App\Shared\Presentation\ApiProblem;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;
use Symfony\Component\Uid\Uuid;

final class LoginFailureHandler implements AuthenticationFailureHandlerInterface
{
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        // Caz special: parola e corectă, dar lipsește/este greșit codul 2FA.
        // Frontend-ul trebuie să solicite codul — răspuns distinct.
        if ($exception instanceof TwoFactorRequiredException) {
            $problem = new ApiProblem(
                'totp_required',
                'Introduceți codul de autentificare în doi pași (2FA).',
                401,
                (string) Uuid::v7(),
            );

            return new JsonResponse($problem->toArray(), 401, ['Content-Type' => 'application/problem+json']);
        }

        // Mesaj generic — nu dezvăluim dacă emailul există (anti-enumerare).
        $problem = new ApiProblem(
            'authentication_failed',
            'Email sau parolă incorecte.',
            401,
            (string) Uuid::v7(),
        );

        return new JsonResponse($problem->toArray(), 401, ['Content-Type' => 'application/problem+json']);
    }
}
