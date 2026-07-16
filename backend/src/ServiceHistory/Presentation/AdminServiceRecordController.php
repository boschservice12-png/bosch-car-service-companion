<?php

declare(strict_types=1);

namespace App\ServiceHistory\Presentation;

use App\Document\Domain\DocumentRepository;
use App\Document\Presentation\DocumentVoter;
use App\Identity\Domain\User;
use App\ServiceHistory\Application\ServiceRecordService;
use App\ServiceHistory\Domain\ServiceRecord;
use App\ServiceHistory\Domain\ServiceRecordRepository;
use App\ServiceHistory\Presentation\Dto\ServiceRecordRequest;
use App\Shared\Presentation\ValidationFailedException;
use App\Vehicle\Domain\Vehicle;
use App\Vehicle\Domain\VehicleRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Portal service (ADMIN). Rutele sunt sub /api/admin → protejate de access_control
 * (ROLE_SERVICE_ADMIN). Adminul vede toate înregistrările (inclusiv ciornele) și
 * gestionează ciclul creare → publicare → corecție.
 */
#[Route('/api/admin')]
final class AdminServiceRecordController extends AbstractController
{
    public function __construct(
        private readonly VehicleRepository $vehicles,
        private readonly ServiceRecordRepository $records,
        private readonly ServiceRecordService $service,
        private readonly ServiceRecordSerializer $serializer,
        private readonly ValidatorInterface $validator,
    ) {
    }

    #[Route('/vehicles/{vehicleId}/service-records', name: 'api_admin_service_records_list', methods: ['GET'])]
    public function list(string $vehicleId): JsonResponse
    {
        $vehicle = $this->requireVehicle($vehicleId);

        return $this->json($this->serializer->serializeList($this->records->findForVehicle($vehicle, includeDrafts: true)));
    }

    #[Route('/vehicles/{vehicleId}/service-records', name: 'api_admin_service_records_create', methods: ['POST'])]
    public function create(string $vehicleId, #[MapRequestPayload] ServiceRecordRequest $req): JsonResponse
    {
        $this->assertValid($req);
        $vehicle = $this->requireVehicle($vehicleId);

        $record = $this->service->create($vehicle, $this->currentUser(), $req);

        return $this->json($this->serializer->serialize($record), 201);
    }

    #[Route('/service-records/{id}', name: 'api_admin_service_records_get', methods: ['GET'])]
    public function get(string $id): JsonResponse
    {
        $record = $this->requireRecord($id);
        $correctedIds = $this->correctedIdsForVehicle($record->vehicle());

        return $this->json($this->serializer->serialize($record, $correctedIds));
    }

    #[Route('/service-records/{id}', name: 'api_admin_service_records_update', methods: ['PATCH'])]
    public function update(string $id, #[MapRequestPayload] ServiceRecordRequest $req): JsonResponse
    {
        $this->assertValid($req);
        $record = $this->requireRecord($id);

        $updated = $this->service->update($record, $req);

        return $this->json($this->serializer->serialize($updated));
    }

    #[Route('/service-records/{id}/publish', name: 'api_admin_service_records_publish', methods: ['POST'])]
    public function publish(string $id): JsonResponse
    {
        $record = $this->requireRecord($id);
        $published = $this->service->publish($record);

        return $this->json($this->serializer->serialize($published));
    }

    #[Route('/service-records/{id}/corrections', name: 'api_admin_service_records_correct', methods: ['POST'])]
    public function correct(string $id): JsonResponse
    {
        $record = $this->requireRecord($id);
        $correction = $this->service->createCorrection($record, $this->currentUser());

        return $this->json($this->serializer->serialize($correction), 201);
    }

    #[Route('/service-records/{id}/documents', name: 'api_admin_service_records_attach', methods: ['POST'])]
    public function attachDocument(string $id, Request $request, DocumentRepository $documents): JsonResponse
    {
        $record = $this->requireRecord($id);

        /** @var array{documentId?: string} $payload */
        $payload = json_decode($request->getContent(), true) ?? [];
        $docId = $payload['documentId'] ?? null;
        if (!\is_string($docId) || !Uuid::isValid($docId)) {
            throw ValidationFailedException::fromArray(['documentId' => ['Identificator de document invalid.']]);
        }
        $document = $documents->get(Uuid::fromString($docId));
        if ($document === null) {
            throw $this->createNotFoundException('Document inexistent.');
        }
        $this->denyAccessUnlessGranted(DocumentVoter::VIEW, $document);

        $updated = $this->service->attachDocument($record, $document);

        return $this->json($this->serializer->serialize($updated));
    }

    private function requireVehicle(string $id): Vehicle
    {
        if (!Uuid::isValid($id) || ($v = $this->vehicles->get(Uuid::fromString($id))) === null) {
            throw $this->createNotFoundException('Vehicul inexistent.');
        }

        return $v;
    }

    private function requireRecord(string $id): ServiceRecord
    {
        if (!Uuid::isValid($id) || ($r = $this->records->get(Uuid::fromString($id))) === null) {
            throw $this->createNotFoundException('Înregistrare de service inexistentă.');
        }

        return $r;
    }

    /** @return string[] */
    private function correctedIdsForVehicle(Vehicle $vehicle): array
    {
        $ids = [];
        foreach ($this->records->findForVehicle($vehicle, includeDrafts: true) as $r) {
            if ($r->correctionOf() !== null) {
                $ids[] = (string) $r->correctionOf()->id();
            }
        }

        return array_values(array_unique($ids));
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
