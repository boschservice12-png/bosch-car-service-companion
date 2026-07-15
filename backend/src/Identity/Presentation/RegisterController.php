<?php

declare(strict_types=1);

namespace App\Identity\Presentation;

use App\Identity\Application\RegisterUser;
use App\Identity\Presentation\Dto\RegisterRequest;
use App\Shared\Presentation\ValidationFailedException;
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
    ): JsonResponse {
        $violations = $validator->validate($req);
        if (count($violations) > 0) {
            throw ValidationFailedException::fromViolations($violations);
        }

        $user = $registerUser($req->email, $req->password, $req->firstName, $req->lastName);

        return $this->json([
            'id' => (string) $user->id(),
            'email' => $user->getEmail(),
            'role' => 'CLIENT',
        ], 201);
    }
}
