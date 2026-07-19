<?php

declare(strict_types=1);

namespace App\Tax\Presentation;

use App\Document\Domain\Document;
use App\Document\Domain\DocumentRepository;
use App\Document\Domain\StorageAdapter;
use App\Identity\Domain\User;
use App\Shared\Presentation\ResolvesAttachments;
use App\Shared\Presentation\ValidationFailedException;
use App\Tax\Application\TaxService;
use App\Tax\Domain\TaxItem;
use App\Tax\Domain\TaxItemRepository;
use App\Tax\Domain\TaxType;
use App\Tax\Presentation\Dto\CreateTaxItemRequest;
use App\Vehicle\Domain\Vehicle;
use App\Vehicle\Domain\VehicleRepository;
use App\Vehicle\Presentation\VehicleVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Taxe și impozite din perspectiva CLIENTULUI. Un client vede și gestionează doar
 * propriile taxe (TaxItemVoter): le adaugă, le marchează plătite și atașează bizonjatul.
 */
final class ClientTaxController extends AbstractController
{
    use ResolvesAttachments;

    public function __construct(
        private readonly TaxItemRepository $items,
        private readonly TaxService $service,
        private readonly TaxSerializer $serializer,
        private readonly VehicleRepository $vehicles,
        private readonly ValidatorInterface $validator,
    ) {
    }

    #[Route('/api/taxes', name: 'api_tax_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        return $this->json($this->serializer->serializeList($this->items->findForCustomer($this->currentUser()), withCustomer: false));
    }

    #[Route('/api/taxes', name: 'api_tax_create', methods: ['POST'])]
    public function create(#[MapRequestPayload] CreateTaxItemRequest $req): JsonResponse
    {
        $this->assertValid($req);

        $vehicle = null;
        if ($req->vehicleId !== null) {
            $vehicle = $this->requireVehicle($req->vehicleId);
            $this->denyAccessUnlessGranted(VehicleVoter::VIEW, $vehicle);
        }

        $item = $this->service->create(
            $this->currentUser(),
            $vehicle,
            (int) $req->year,
            TaxType::from($req->type),
            (int) round((float) $req->amount * 100),
            $this->parseDate($req->dueDate),
        );

        return $this->json($this->serializer->serialize($item, withCustomer: false), 201);
    }

    #[Route('/api/taxes/{id}', name: 'api_tax_get', methods: ['GET'])]
    public function get(string $id): JsonResponse
    {
        $item = $this->requireItem($id);
        $this->denyAccessUnlessGranted(TaxItemVoter::VIEW, $item);

        return $this->json($this->serializer->serialize($item, withCustomer: false));
    }

    /** Marchează taxa ca plătită, opțional cu bizonjatul atașat (`documentIds`). */
    #[Route('/api/taxes/{id}/pay', name: 'api_tax_pay', methods: ['POST'])]
    public function pay(string $id, Request $request, DocumentRepository $documents): JsonResponse
    {
        $item = $this->requireItem($id);
        $this->denyAccessUnlessGranted(TaxItemVoter::VIEW, $item);

        /** @var array{documentIds?: string[], amount?: float|int|string} $payload */
        $payload = json_decode($request->getContent(), true) ?: [];
        $ids = \is_array($payload['documentIds'] ?? null) ? $payload['documentIds'] : [];
        $receipts = $this->resolveAttachments($ids, $documents);

        // Fără sumă → plată integrală; cu sumă (RON) → plată parțială/cumulativă.
        $updated = isset($payload['amount']) && is_numeric($payload['amount'])
            ? $this->service->registerPayment($item, (int) round(((float) $payload['amount']) * 100), $receipts)
            : $this->service->markPaid($item, $receipts);

        return $this->json($this->serializer->serialize($updated, withCustomer: false));
    }

    #[Route('/api/taxes/{id}/documents/{docId}', name: 'api_tax_document', methods: ['GET'])]
    public function document(string $id, string $docId, DocumentRepository $documents, StorageAdapter $storage): Response
    {
        $item = $this->requireItem($id);
        $this->denyAccessUnlessGranted(TaxItemVoter::VIEW, $item);

        if (!Uuid::isValid($docId)) {
            throw $this->createNotFoundException('Document inexistent.');
        }
        $document = $documents->get(Uuid::fromString($docId));
        if ($document === null || !$item->hasDocument($document)) {
            throw $this->createNotFoundException('Documentul nu aparține acestei taxe.');
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
            throw ValidationFailedException::fromArray(['dueDate' => ['Dată invalidă (format: AAAA-LL-ZZ).']]);
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

    private function requireItem(string $id): TaxItem
    {
        if (!Uuid::isValid($id) || ($t = $this->items->get(Uuid::fromString($id))) === null) {
            throw $this->createNotFoundException('Taxă inexistentă.');
        }

        return $t;
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
