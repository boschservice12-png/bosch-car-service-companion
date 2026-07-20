<?php

declare(strict_types=1);

namespace App\Vehicle\Presentation;

use App\Identity\Domain\User;
use App\Shared\Presentation\ValidationFailedException;
use App\Shared\Security\ApiRateLimiter;
use App\Vehicle\Application\VehicleActivationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Activarea unui vehicul de către CLIENT cu un cod primit de la service.
 * Numărul de înmatriculare / VIN NU dau acces — doar acest cod. Limitat la rată
 * (per utilizator + IP) împotriva ghicirii prin forță brută.
 */
final class MeVehicleActivationController extends AbstractController
{
    public function __construct(
        private readonly VehicleActivationService $activation,
        private readonly ApiRateLimiter $rateLimiter,
    ) {
    }

    #[Route('/api/me/vehicles/activate', name: 'api_me_vehicle_activate', methods: ['POST'])]
    public function activate(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $this->rateLimiter->checkActivation($request, $user);

        /** @var array{token?: string} $payload */
        $payload = json_decode($request->getContent(), true) ?? [];
        $token = \is_string($payload['token'] ?? null) ? trim($payload['token']) : '';
        if ($token === '') {
            throw ValidationFailedException::fromArray(['token' => ['Codul de activare este obligatoriu.']]);
        }

        $vehicle = $this->activation->activate($token, $user);

        return $this->json([
            'id' => (string) $vehicle->id(),
            'vin' => $vehicle->vin(),
            'plateNumber' => $vehicle->plateNumber(),
            'make' => $vehicle->make(),
            'model' => $vehicle->model(),
        ], 200);
    }
}
