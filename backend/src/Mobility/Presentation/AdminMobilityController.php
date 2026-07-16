<?php

declare(strict_types=1);

namespace App\Mobility\Presentation;

use App\Mobility\Application\MobilityService;
use App\Mobility\Domain\MobilityRequest;
use App\Mobility\Domain\MobilityRequestRepository;
use App\Mobility\Domain\MobilityStatus;
use App\Mobility\Presentation\Dto\UpdateMobilityStatusRequest;
use App\Shared\Presentation\ValidationFailedException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Portal service (ADMIN) — sub /api/admin (ROLE_SERVICE_ADMIN). Adminul vede toate
 * solicitările de mobilitate și le gestionează starea.
 */
#[Route('/api/admin/mobility-requests')]
final class AdminMobilityController extends AbstractController
{
    public function __construct(
        private readonly MobilityRequestRepository $requests,
        private readonly MobilityService $service,
        private readonly MobilitySerializer $serializer,
        private readonly ValidatorInterface $validator,
    ) {
    }

    #[Route('', name: 'api_admin_mobility_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        return $this->json($this->serializer->serializeList($this->requests->findAllForAdmin(), withCustomer: true));
    }

    #[Route('/{id}', name: 'api_admin_mobility_get', methods: ['GET'])]
    public function get(string $id): JsonResponse
    {
        return $this->json($this->serializer->serialize($this->requireRequest($id), withCustomer: true));
    }

    #[Route('/{id}', name: 'api_admin_mobility_update', methods: ['PATCH'])]
    public function update(string $id, #[MapRequestPayload] UpdateMobilityStatusRequest $req): JsonResponse
    {
        $this->assertValid($req);
        $request = $this->requireRequest($id);
        $updated = $this->service->updateStatus($request, MobilityStatus::from($req->status), $req->note);

        return $this->json($this->serializer->serialize($updated, withCustomer: true));
    }

    private function requireRequest(string $id): MobilityRequest
    {
        if (!Uuid::isValid($id) || ($r = $this->requests->get(Uuid::fromString($id))) === null) {
            throw $this->createNotFoundException('Solicitare inexistentă.');
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
