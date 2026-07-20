<?php

declare(strict_types=1);

namespace App\Identity\Presentation;

use App\Identity\Application\RegisterUser;
use App\Identity\Presentation\Dto\RegisterRequest;
use App\Shared\Presentation\ValidationFailedException;
use App\Shared\Security\ApiRateLimiter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class RegisterController extends AbstractController
{
    #[Route('/api/auth/register', name: 'api_auth_register', methods: ['POST'])]
    public function register(
        #[MapRequestPayload] RegisterRequest $req,
        ValidatorInterface $validator,
        RegisterUser $registerUser,
        ApiRateLimiter $rateLimiter,
    ): JsonResponse {
        $violations = $validator->validate($req);
        if (count($violations) > 0) {
            throw ValidationFailedException::fromViolations($violations);
        }

        // Revendicarea folosește numărul de înmatriculare drept dovadă — fără
        // limită de încercări ar putea fi ghicit prin forță brută.
        $rateLimiter->checkRegister($req->email);

        $user = $registerUser($req->email, $req->password, $req->firstName, $req->lastName, $req->plateNumber);

        return $this->json([
            'id' => (string) $user->id(),
            'email' => $user->getEmail(),
            'role' => 'CLIENT',
        ], 201);
    }
}
