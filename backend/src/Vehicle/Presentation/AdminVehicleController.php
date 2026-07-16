<?php

declare(strict_types=1);

namespace App\Vehicle\Presentation;

use App\Vehicle\Domain\Vehicle;
use App\Vehicle\Domain\VehicleRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Portal admin: listarea vehiculelor service-ului (toți clienții). Ruta este sub
 * /api/admin → protejată de access_control (ROLE_SERVICE_ADMIN).
 */
#[Route('/api/admin/vehicles')]
final class AdminVehicleController extends AbstractController
{
    public function __construct(private readonly VehicleRepository $vehicles)
    {
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
}
