<?php

declare(strict_types=1);

namespace App\Vehicle\Presentation;

use App\Identity\Domain\User;
use App\Shared\Presentation\ValidationFailedException;
use App\Vehicle\Application\VehicleService;
use App\Vehicle\Domain\Vehicle;
use App\Vehicle\Domain\VehicleRepository;
use App\Vehicle\Presentation\Dto\CreateVehicleRequest;
use App\Vehicle\Presentation\Dto\UpdateVehicleRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/vehicles')]
final class VehicleController extends AbstractController
{
    public function __construct(
        private readonly VehicleRepository $vehicles,
        private readonly VehicleService $service,
        private readonly ValidatorInterface $validator,
    ) {
    }

    #[Route('', name: 'api_vehicles_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $profile = $this->currentProfile();
        $vehicles = $this->vehicles->findActiveForCustomer($profile);

        return $this->json(array_map($this->serialize(...), $vehicles));
    }

    #[Route('', name: 'api_vehicles_create', methods: ['POST'])]
    public function create(#[MapRequestPayload] CreateVehicleRequest $req): JsonResponse
    {
        $this->assertValid($req);
        $vehicle = $this->service->create($req, $this->currentProfile());

        return $this->json($this->serialize($vehicle), 201);
    }

    #[Route('/{id}', name: 'api_vehicles_get', methods: ['GET'])]
    public function get(string $id): JsonResponse
    {
        $vehicle = $this->requireVehicle($id);
        $this->denyAccessUnlessGranted(VehicleVoter::VIEW, $vehicle);

        return $this->json($this->serialize($vehicle));
    }

    #[Route('/{id}', name: 'api_vehicles_update', methods: ['PATCH'])]
    public function update(string $id, #[MapRequestPayload] UpdateVehicleRequest $req): JsonResponse
    {
        $this->assertValid($req);
        $vehicle = $this->requireVehicle($id);
        $this->denyAccessUnlessGranted(VehicleVoter::EDIT, $vehicle);

        $updated = $this->service->update($vehicle, $req);

        return $this->json($this->serialize($updated));
    }

    private function requireVehicle(string $id): Vehicle
    {
        if (!Uuid::isValid($id)) {
            throw $this->createNotFoundException('Vehicul inexistent.');
        }
        $vehicle = $this->vehicles->get(Uuid::fromString($id));
        if ($vehicle === null) {
            throw $this->createNotFoundException('Vehicul inexistent.');
        }

        return $vehicle;
    }

    private function currentProfile(): \App\Customer\Domain\CustomerProfile
    {
        $user = $this->getUser();
        \assert($user instanceof User);
        $profile = $user->customerProfile();
        if ($profile === null) {
            throw $this->createAccessDeniedException('Contul nu are un profil de client.');
        }

        return $profile;
    }

    private function assertValid(object $dto): void
    {
        $violations = $this->validator->validate($dto);
        if (count($violations) > 0) {
            throw ValidationFailedException::fromViolations($violations);
        }
    }

    /** @return array<string, mixed> */
    private function serialize(Vehicle $v): array
    {
        return [
            'id' => (string) $v->id(),
            'vin' => $v->vin(),
            'plateNumber' => $v->plateNumber(),
            'make' => $v->make(),
            'model' => $v->model(),
            'year' => $v->year(),
        ];
    }
}
