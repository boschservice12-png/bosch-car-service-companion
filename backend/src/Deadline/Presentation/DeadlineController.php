<?php

declare(strict_types=1);

namespace App\Deadline\Presentation;

use App\Deadline\Application\DeadlineService;
use App\Deadline\Domain\DeadlineSource;
use App\Deadline\Domain\DeadlineStatusCalculator;
use App\Deadline\Domain\VehicleDeadline;
use App\Deadline\Domain\VehicleDeadlineRepository;
use App\Deadline\Presentation\Dto\CreateDeadlineRequest;
use App\Deadline\Presentation\Dto\UpdateDeadlineRequest;
use App\Document\Domain\DocumentRepository;
use App\Document\Presentation\DocumentVoter;
use App\Identity\Domain\User;
use App\Settings\Application\SettingsProvider;
use App\Shared\Presentation\ValidationFailedException;
use App\Vehicle\Domain\Vehicle;
use App\Vehicle\Domain\VehicleRepository;
use App\Vehicle\Presentation\VehicleVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class DeadlineController extends AbstractController
{
    public function __construct(
        private readonly VehicleRepository $vehicles,
        private readonly VehicleDeadlineRepository $deadlines,
        private readonly DeadlineService $service,
        private readonly DeadlineStatusCalculator $calculator,
        private readonly SettingsProvider $settings,
        private readonly ValidatorInterface $validator,
    ) {
    }

    #[Route('/api/vehicles/{id}/deadlines', name: 'api_vehicle_deadlines_list', methods: ['GET'])]
    public function list(string $id): JsonResponse
    {
        $vehicle = $this->requireVehicle($id);
        $this->denyAccessUnlessGranted(VehicleVoter::VIEW, $vehicle);

        $items = array_map($this->serialize(...), $this->deadlines->findForVehicle($vehicle));

        return $this->json($items);
    }

    #[Route('/api/vehicles/{id}/deadlines', name: 'api_vehicle_deadlines_create', methods: ['POST'])]
    public function create(string $id, #[MapRequestPayload] CreateDeadlineRequest $req): JsonResponse
    {
        $this->assertValid($req);
        $vehicle = $this->requireVehicle($id);
        $this->denyAccessUnlessGranted(VehicleVoter::EDIT, $vehicle);

        $source = $this->isGranted(User::ROLE_SERVICE_ADMIN) ? DeadlineSource::SERVICE : DeadlineSource::CLIENT;
        $deadline = $this->service->create($vehicle, $req, $source);

        return $this->json($this->serialize($deadline), 201);
    }

    #[Route('/api/deadlines/{id}', name: 'api_deadlines_update', methods: ['PATCH'])]
    public function update(string $id, #[MapRequestPayload] UpdateDeadlineRequest $req): JsonResponse
    {
        $this->assertValid($req);
        $deadline = $this->requireDeadline($id);
        $this->denyAccessUnlessGranted(VehicleVoter::EDIT, $deadline->vehicle());

        $admin = $this->isGranted(User::ROLE_SERVICE_ADMIN) ? $this->currentUser() : null;
        $updated = $this->service->update($deadline, $req, $admin);

        return $this->json($this->serialize($updated));
    }

    #[Route('/api/deadlines/{id}/documents', name: 'api_deadlines_attach_document', methods: ['POST'])]
    public function attachDocument(string $id, Request $request, DocumentRepository $documents): JsonResponse
    {
        $deadline = $this->requireDeadline($id);
        $this->denyAccessUnlessGranted(VehicleVoter::EDIT, $deadline->vehicle());

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

        $updated = $this->service->attachDocument($deadline, $document);

        return $this->json($this->serialize($updated));
    }

    private function requireVehicle(string $id): Vehicle
    {
        if (!Uuid::isValid($id) || ($v = $this->vehicles->get(Uuid::fromString($id))) === null) {
            throw $this->createNotFoundException('Vehicul inexistent.');
        }

        return $v;
    }

    private function requireDeadline(string $id): VehicleDeadline
    {
        if (!Uuid::isValid($id) || ($d = $this->deadlines->get(Uuid::fromString($id))) === null) {
            throw $this->createNotFoundException('Scadență inexistentă.');
        }

        return $d;
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

    /** @return array<string, mixed> */
    private function serialize(VehicleDeadline $d): array
    {
        $now = new \DateTimeImmutable();
        $dueSoonDays = max([1, ...$this->settings->notificationThresholds()]);
        $state = $this->calculator->calculate($d->expiresAt(), $now, $dueSoonDays);

        return [
            'id' => (string) $d->id(),
            'type' => $d->type()->value,
            'typeLabel' => $d->type()->label(),
            'validFrom' => $d->validFrom()?->format('Y-m-d'),
            'expiresAt' => $d->expiresAt()?->format('Y-m-d'),
            'state' => $state->value,
            'stateLabel' => $state->label(),
            'daysLeft' => $this->calculator->daysLeft($d->expiresAt(), $now),
            'source' => $d->source()->value,
            'verified' => $d->isVerified(),
            'note' => $d->note(),
            'documentId' => $d->document() !== null ? (string) $d->document()->id() : null,
        ];
    }
}
