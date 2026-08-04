<?php

declare(strict_types=1);

namespace App\Vehicle\Presentation;

use App\Identity\Domain\User;
use App\Vehicle\Application\VehicleActivationService;
use App\Vehicle\Domain\Vehicle;
use App\Vehicle\Domain\VehicleRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

/**
 * Portal admin: listarea vehiculelor service-ului (toți clienții). Ruta este sub
 * /api/admin → protejată de access_control (ROLE_SERVICE_ADMIN).
 */
#[Route('/api/admin/vehicles')]
final class AdminVehicleController extends AbstractController
{
    public function __construct(
        private readonly VehicleRepository $vehicles,
        private readonly VehicleActivationService $activation,
    ) {
    }

    #[Route('', name: 'api_admin_vehicles_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $items = array_map(function (Vehicle $v): array {
            $owner = $this->vehicles->findActiveOwner($v);

            return [
                'id' => (string) $v->id(),
                'vin' => $v->vin(),
                'plateNumber' => $v->plateNumber(),
                'make' => $v->make(),
                'model' => $v->model(),
                'year' => $v->year(),
                'ownerName' => $owner?->fullName() ?: null,
            ];
        }, $this->vehicles->findAllActive());

        return $this->json($items);
    }

    /**
     * Emite un cod de activare pentru vehicul. Codul se întoarce în CLAR o
     * SINGURĂ DATĂ (în baza de date rămâne doar hash-ul) — adminul îl comunică
     * clientului, care îl folosește la /api/me/vehicles/activate.
     */
    #[Route('/{id}/activation-token', name: 'api_admin_vehicle_activation_issue', methods: ['POST'])]
    public function issueToken(string $id): JsonResponse
    {
        $vehicle = $this->requireVehicle($id);
        $token = $this->activation->issue($vehicle, $this->currentUser());

        return $this->json(['token' => $token], 201);
    }

    /** Revocă toate codurile încă valabile ale vehiculului. */
    #[Route('/{id}/activation-token/revoke', name: 'api_admin_vehicle_activation_revoke', methods: ['POST'])]
    public function revokeToken(string $id): JsonResponse
    {
        $vehicle = $this->requireVehicle($id);
        $revoked = $this->activation->revoke($vehicle, $this->currentUser());

        return $this->json(['revoked' => $revoked]);
    }

    private function requireVehicle(string $id): Vehicle
    {
        if (!Uuid::isValid($id) || ($v = $this->vehicles->get(Uuid::fromString($id))) === null) {
            throw $this->createNotFoundException('Vehicul inexistent.');
        }

        return $v;
    }

    private function currentUser(): User
    {
        $user = $this->getUser();
        \assert($user instanceof User);

        return $user;
    }
}
