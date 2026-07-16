<?php

declare(strict_types=1);

namespace App\DamageClaim\Presentation;

use App\DamageClaim\Application\DamageClaimService;
use App\DamageClaim\Domain\DamageClaim;
use App\DamageClaim\Domain\DamageClaimRepository;
use App\DamageClaim\Presentation\Dto\CreateDamageClaimRequest;
use App\Document\Domain\Document;
use App\Document\Domain\DocumentRepository;
use App\Document\Domain\StorageAdapter;
use App\Identity\Domain\User;
use App\Shared\Presentation\ResolvesAttachments;
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
 * Dosare de daună din perspectiva CLIENTULUI. Un client vede și gestionează doar
 * propriile dosare (DamageClaimVoter).
 */
final class ClientDamageClaimController extends AbstractController
{
    use ResolvesAttachments;

    public function __construct(
        private readonly DamageClaimRepository $claims,
        private readonly DamageClaimService $service,
        private readonly DamageClaimSerializer $serializer,
        private readonly VehicleRepository $vehicles,
        private readonly ValidatorInterface $validator,
    ) {
    }

    #[Route('/api/damage-claims', name: 'api_damage_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        return $this->json($this->serializer->serializeList($this->claims->findForCustomer($this->currentUser()), withCustomer: false));
    }

    #[Route('/api/damage-claims', name: 'api_damage_create', methods: ['POST'])]
    public function create(#[MapRequestPayload] CreateDamageClaimRequest $req, DocumentRepository $documents): JsonResponse
    {
        $this->assertValid($req);

        $vehicle = null;
        if ($req->vehicleId !== null) {
            $vehicle = $this->requireVehicle($req->vehicleId);
            $this->denyAccessUnlessGranted(VehicleVoter::VIEW, $vehicle);
        }
        $attachments = $this->resolveAttachments($req->documentIds, $documents);

        $claim = $this->service->create(
            $this->currentUser(),
            $vehicle,
            $this->parseDate($req->incidentDate),
            $req->incidentLocation,
            $req->incidentDescription,
            $req->insurer,
            $req->policyNumber,
            $attachments,
        );

        return $this->json($this->serializer->serialize($claim, withCustomer: false), 201);
    }

    #[Route('/api/damage-claims/{id}', name: 'api_damage_get', methods: ['GET'])]
    public function get(string $id): JsonResponse
    {
        $claim = $this->requireClaim($id);
        $this->denyAccessUnlessGranted(DamageClaimVoter::VIEW, $claim);

        return $this->json($this->serializer->serialize($claim, withCustomer: false));
    }

    #[Route('/api/damage-claims/{id}/cancel', name: 'api_damage_cancel', methods: ['POST'])]
    public function cancel(string $id): JsonResponse
    {
        $claim = $this->requireClaim($id);
        if (!$claim->customer()->id()->equals($this->currentUser()->id())) {
            throw $this->createAccessDeniedException('Doar clientul poate anula dosarul.');
        }
        $updated = $this->service->cancelByClient($claim);

        return $this->json($this->serializer->serialize($updated, withCustomer: false));
    }

    #[Route('/api/damage-claims/{id}/documents/{docId}', name: 'api_damage_document', methods: ['GET'])]
    public function document(string $id, string $docId, DocumentRepository $documents, StorageAdapter $storage): Response
    {
        $claim = $this->requireClaim($id);
        $this->denyAccessUnlessGranted(DamageClaimVoter::VIEW, $claim);

        if (!Uuid::isValid($docId)) {
            throw $this->createNotFoundException('Document inexistent.');
        }
        $document = $documents->get(Uuid::fromString($docId));
        if ($document === null || !$claim->hasDocument($document)) {
            throw $this->createNotFoundException('Documentul nu aparține acestui dosar.');
        }
        if (!$document->isServable()) {
            throw $this->createNotFoundException('Document indisponibil (în curs de scanare sau respins).');
        }

        $contents = $storage->read($document->storageKey());
        $filename = $document->originalName() ?? ('document.'.pathinfo($document->storageKey(), PATHINFO_EXTENSION));

        return new Response($contents, 200, [
            'Content-Type' => $document->mimeType(),
            'Content-Disposition' => sprintf('attachment; filename="%s"', addslashes($filename)),
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function parseDate(?string $value): ?\DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if ($date === false) {
            throw ValidationFailedException::fromArray(['incidentDate' => ['Dată invalidă (format: AAAA-LL-ZZ).']]);
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

    private function requireClaim(string $id): DamageClaim
    {
        if (!Uuid::isValid($id) || ($c = $this->claims->get(Uuid::fromString($id))) === null) {
            throw $this->createNotFoundException('Dosar inexistent.');
        }

        return $c;
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
