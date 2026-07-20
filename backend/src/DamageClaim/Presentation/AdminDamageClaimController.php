<?php

declare(strict_types=1);

namespace App\DamageClaim\Presentation;

use App\DamageClaim\Application\DamageClaimService;
use App\DamageClaim\Domain\DamageClaim;
use App\DamageClaim\Domain\DamageClaimRepository;
use App\DamageClaim\Domain\DamageClaimStatus;
use App\DamageClaim\Presentation\Dto\UpdateDamageClaimStatusRequest;
use App\Document\Domain\DocumentRepository;
use App\Document\Domain\StorageAdapter;
use App\Shared\Presentation\ValidationFailedException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Portal service (ADMIN) — sub /api/admin (ROLE_SERVICE_ADMIN). Adminul vede toate
 * dosarele de daună și le gestionează starea (asistență la deschidere/urmărire).
 */
#[Route('/api/admin/damage-claims')]
final class AdminDamageClaimController extends AbstractController
{
    public function __construct(
        private readonly DamageClaimRepository $claims,
        private readonly DamageClaimService $service,
        private readonly DamageClaimSerializer $serializer,
        private readonly ValidatorInterface $validator,
    ) {
    }

    #[Route('', name: 'api_admin_damage_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        return $this->json($this->serializer->serializeList($this->claims->findAllForAdmin(), withCustomer: true));
    }

    #[Route('/{id}', name: 'api_admin_damage_get', methods: ['GET'])]
    public function get(string $id): JsonResponse
    {
        return $this->json($this->serializer->serialize($this->requireClaim($id), withCustomer: true));
    }

    #[Route('/{id}', name: 'api_admin_damage_update', methods: ['PATCH'])]
    public function update(string $id, #[MapRequestPayload] UpdateDamageClaimStatusRequest $req): JsonResponse
    {
        $this->assertValid($req);
        $claim = $this->requireClaim($id);
        $updated = $this->service->updateStatus($claim, DamageClaimStatus::from($req->status), $req->note, $req->missingDocuments);

        return $this->json($this->serializer->serialize($updated, withCustomer: true));
    }

    /** Fotografiile/documentele dosarului — ruta era referită de portal, dar lipsea. */
    #[Route('/{id}/documents/{docId}', name: 'api_admin_damage_document', methods: ['GET'])]
    public function document(string $id, string $docId, DocumentRepository $documents, StorageAdapter $storage): Response
    {
        $claim = $this->requireClaim($id);

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

    private function requireClaim(string $id): DamageClaim
    {
        if (!Uuid::isValid($id) || ($c = $this->claims->get(Uuid::fromString($id))) === null) {
            throw $this->createNotFoundException('Dosar inexistent.');
        }

        return $c;
    }

    private function assertValid(object $dto): void
    {
        $violations = $this->validator->validate($dto);
        if (count($violations) > 0) {
            throw ValidationFailedException::fromViolations($violations);
        }
    }
}
