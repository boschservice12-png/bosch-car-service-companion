<?php

declare(strict_types=1);

namespace App\Mobility\Presentation;

use App\Identity\Domain\User;
use App\Mobility\Application\MobilityService;
use App\Mobility\Domain\MobilityRequest;
use App\Mobility\Domain\MobilityRequestRepository;
use App\Mobility\Domain\MobilityType;
use App\Mobility\Presentation\Dto\CreateMobilityRequest;
use App\Shared\Presentation\ValidationFailedException;
use App\Vehicle\Domain\Vehicle;
use App\Vehicle\Domain\VehicleRepository;
use App\Vehicle\Presentation\VehicleVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Solicitări de mobilitate din perspectiva CLIENTULUI. Un client vede și
 * gestionează doar propriile solicitări (MobilityRequestVoter).
 */
final class ClientMobilityController extends AbstractController
{
    public function __construct(
        private readonly MobilityRequestRepository $requests,
        private readonly MobilityService $service,
        private readonly MobilitySerializer $serializer,
        private readonly VehicleRepository $vehicles,
        private readonly ValidatorInterface $validator,
    ) {
    }

    #[Route('/api/mobility-requests', name: 'api_mobility_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        return $this->json($this->serializer->serializeList($this->requests->findForCustomer($this->currentUser()), withCustomer: false));
    }

    #[Route('/api/mobility-requests', name: 'api_mobility_create', methods: ['POST'])]
    public function create(#[MapRequestPayload] CreateMobilityRequest $req): JsonResponse
    {
        $this->assertValid($req);

        $vehicle = null;
        if ($req->vehicleId !== null) {
            $vehicle = $this->requireVehicle($req->vehicleId);
            $this->denyAccessUnlessGranted(VehicleVoter::VIEW, $vehicle);
        }

        $request = $this->service->create(
            $this->currentUser(),
            $vehicle,
            MobilityType::from($req->type),
            $req->details,
            $this->parseDate($req->preferredDate),
        );

        return $this->json($this->serializer->serialize($request, withCustomer: false), 201);
    }

    #[Route('/api/mobility-requests/{id}', name: 'api_mobility_get', methods: ['GET'])]
    public function get(string $id): JsonResponse
    {
        $request = $this->requireRequest($id);
        $this->denyAccessUnlessGranted(MobilityRequestVoter::VIEW, $request);

        return $this->json($this->serializer->serialize($request, withCustomer: false));
    }

    #[Route('/api/mobility-requests/{id}/cancel', name: 'api_mobility_cancel', methods: ['POST'])]
    public function cancel(string $id): JsonResponse
    {
        $request = $this->requireRequest($id);
        if (!$request->customer()->id()->equals($this->currentUser()->id())) {
            throw $this->createAccessDeniedException('Doar clientul poate anula solicitarea.');
        }
        $updated = $this->service->cancelByClient($request);

        return $this->json($this->serializer->serialize($updated, withCustomer: false));
    }

    private function parseDate(?string $value): ?\DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if ($date === false) {
            throw ValidationFailedException::fromArray(['preferredDate' => ['Dată invalidă (format: AAAA-LL-ZZ).']]);
        }

        return $date;
    }

    private function requireVehicle(string $id): Vehicle
    {
        if (!Uuid::isValid($id) || ($v = $this->vehicles->get(Uuid::fromString($id))) === null) {
            throw $this->createNotFoundException('Vehicul inexistent.');
        }

        return $v;
    }

    private function requireRequest(string $id): MobilityRequest
    {
        if (!Uuid::isValid($id) || ($r = $this->requests->get(Uuid::fromString($id))) === null) {
            throw $this->createNotFoundException('Solicitare inexistentă.');
        }

        return $r;
    }

    private function currentUser(): User
    {
        $user = $this->getUser();
        \assert($user instanceof User);

        return $user;
    }

    private function assertValid(object $dto): void
    {
        $violations = $this->validator->validate($dto);
        if (count($violations) > 0) {
            throw ValidationFailedException::fromViolations($violations);
        }
    }
}
