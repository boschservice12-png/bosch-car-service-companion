<?php

declare(strict_types=1);

namespace App\Roadside\Presentation;

use App\Identity\Domain\User;
use App\Roadside\Application\RoadsideService;
use App\Roadside\Domain\RoadsideRequest;
use App\Roadside\Domain\RoadsideRequestRepository;
use App\Roadside\Domain\RoadsideStatus;
use App\Roadside\Presentation\Dto\UpdateRoadsideStatusRequest;
use App\Shared\Presentation\ValidationFailedException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Portal service (ADMIN) — sub /api/admin (ROLE_SERVICE_ADMIN). Adminul vede toate
 * cererile de asistență și le gestionează starea (inclusiv marcajul intern FORWARDED).
 */
#[Route('/api/admin/roadside-requests')]
final class AdminRoadsideController extends AbstractController
{
    public function __construct(
        private readonly RoadsideRequestRepository $requests,
        private readonly RoadsideService $service,
        private readonly RoadsideSerializer $serializer,
        private readonly ValidatorInterface $validator,
    ) {
    }

    #[Route('', name: 'api_admin_roadside_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        return $this->json($this->serializer->serializeList($this->requests->findAllForAdmin(), withCustomer: true));
    }

    #[Route('/{id}', name: 'api_admin_roadside_get', methods: ['GET'])]
    public function get(string $id): JsonResponse
    {
        return $this->json($this->serializer->serialize($this->requireRequest($id), withCustomer: true));
    }

    #[Route('/{id}', name: 'api_admin_roadside_update', methods: ['PATCH'])]
    public function update(string $id, #[MapRequestPayload] UpdateRoadsideStatusRequest $req): JsonResponse
    {
        $this->assertValid($req);
        $request = $this->requireRequest($id);
        $updated = $this->service->updateStatus($request, RoadsideStatus::from($req->status), $req->note);

        return $this->json($this->serializer->serialize($updated, withCustomer: true));
    }

    private function requireRequest(string $id): RoadsideRequest
    {
        if (!Uuid::isValid($id) || ($r = $this->requests->get(Uuid::fromString($id))) === null) {
            throw $this->createNotFoundException('Cerere inexistentă.');
        }

        return $r;
    }

    private function assertValid(object $dto): void
    {
        $violations = $this->validator->validate($dto);
        if (count($violations) > 0) {
            throw ValidationFailedException::fromViolations($violations);
        }
    }
}
