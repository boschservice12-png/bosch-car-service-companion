<?php

declare(strict_types=1);

namespace App\Roadside\Presentation;

use App\Document\Domain\Document;
use App\Document\Domain\DocumentRepository;
use App\Document\Domain\StorageAdapter;
use App\Identity\Domain\User;
use App\Roadside\Application\RoadsideService;
use App\Roadside\Domain\MobilityState;
use App\Roadside\Domain\RoadsideRequest;
use App\Roadside\Domain\RoadsideRequestRepository;
use App\Roadside\Domain\SafetyState;
use App\Roadside\Presentation\Dto\CreateRoadsideRequest;
use App\Shared\Presentation\ValidationFailedException;
use App\Vehicle\Domain\Vehicle;
use App\Vehicle\Domain\VehicleRepository;
use App\Vehicle\Presentation\VehicleVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Asistență rutieră din perspectiva CLIENTULUI. Un client vede și gestionează
 * doar propriile cereri (RoadsideRequestVoter).
 */
final class ClientRoadsideController extends AbstractController
{
    public function __construct(
        private readonly RoadsideRequestRepository $requests,
        private readonly RoadsideService $service,
        private readonly RoadsideSerializer $serializer,
        private readonly VehicleRepository $vehicles,
        private readonly ValidatorInterface $validator,
    ) {
    }

    #[Route('/api/roadside-requests', name: 'api_roadside_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        return $this->json($this->serializer->serializeList($this->requests->findForCustomer($this->currentUser()), withCustomer: false));
    }

    /** Cerere scrisă — FĂRĂ fișiere/foto (decizie de produs: clientul nu încarcă nimic). */
    #[Route('/api/roadside-requests', name: 'api_roadside_create', methods: ['POST'])]
    public function create(#[MapRequestPayload] CreateRoadsideRequest $req): JsonResponse
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
            $req->location,
            $req->problem,
            MobilityState::from($req->mobility),
            SafetyState::from($req->safety),
            $req->phone,
        );

        return $this->json($this->serializer->serialize($request, withCustomer: false), 201);
    }

    #[Route('/api/roadside-requests/{id}', name: 'api_roadside_get', methods: ['GET'])]
    public function get(string $id): JsonResponse
    {
        $request = $this->requireRequest($id);
        $this->denyAccessUnlessGranted(RoadsideRequestVoter::VIEW, $request);

        return $this->json($this->serializer->serialize($request, withCustomer: false));
    }

    #[Route('/api/roadside-requests/{id}/cancel', name: 'api_roadside_cancel', methods: ['POST'])]
    public function cancel(string $id): JsonResponse
    {
        $request = $this->requireRequest($id);
        if (!$request->customer()->id()->equals($this->currentUser()->id())) {
            throw $this->createAccessDeniedException('Doar clientul poate anula cererea.');
        }
        $updated = $this->service->cancelByClient($request);

        return $this->json($this->serializer->serialize($updated, withCustomer: false));
    }

    #[Route('/api/roadside-requests/{id}/documents/{docId}', name: 'api_roadside_document', methods: ['GET'])]
    public function document(string $id, string $docId, DocumentRepository $documents, StorageAdapter $storage): Response
    {
        $request = $this->requireRequest($id);
        $this->denyAccessUnlessGranted(RoadsideRequestVoter::VIEW, $request);

        if (!Uuid::isValid($docId)) {
            throw $this->createNotFoundException('Document inexistent.');
        }
        $document = $documents->get(Uuid::fromString($docId));
        if ($document === null || !$request->hasDocument($document)) {
            throw $this->createNotFoundException('Documentul nu aparține acestei cereri.');
        }
        if (!$document->isServable()) {
            throw $this->createNotFoundException('Document indisponibil (în curs de scanare sau respins).');
        }

        return $this->fileResponse($document, $storage);
    }

    private function fileResponse(Document $document, StorageAdapter $storage): Response
    {
        $contents = $storage->read($document->storageKey());
        $filename = $document->originalName() ?? ('document.'.pathinfo($document->storageKey(), PATHINFO_EXTENSION));

        return new Response($contents, 200, [
            'Content-Type' => $document->mimeType(),
            'Content-Disposition' => sprintf('attachment; filename="%s"', addslashes($filename)),
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function requireVehicle(string $id): Vehicle
    {
        if (!Uuid::isValid($id) || ($v = $this->vehicles->get(Uuid::fromString($id))) === null) {
            throw $this->createNotFoundException('Vehicul inexistent.');
        }

        return $v;
    }

    private function requireRequest(string $id): RoadsideRequest
    {
        if (!Uuid::isValid($id) || ($r = $this->requests->get(Uuid::fromString($id))) === null) {
            throw $this->createNotFoundException('Cerere inexistentă.');
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
