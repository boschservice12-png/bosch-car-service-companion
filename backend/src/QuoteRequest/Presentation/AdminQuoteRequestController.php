<?php

declare(strict_types=1);

namespace App\QuoteRequest\Presentation;

use App\Document\Domain\Document;
use App\Document\Domain\DocumentRepository;
use App\Identity\Domain\User;
use App\QuoteRequest\Application\QuoteRequestService;
use App\QuoteRequest\Domain\QuoteRequest;
use App\QuoteRequest\Domain\QuoteRequestRepository;
use App\QuoteRequest\Domain\QuoteRequestStatus;
use App\QuoteRequest\Presentation\Dto\QuoteResponseRequest;
use App\QuoteRequest\Presentation\Dto\UpdateQuoteRequestStatusRequest;
use App\Shared\Presentation\ValidationFailedException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Cererile de ofertă din perspectiva SERVICE-ului: analiză, cerere de
 * informații, răspuns cu ofertă (text + document opțional), închidere.
 */
#[Route('/api/admin/quote-requests')]
final class AdminQuoteRequestController extends AbstractController
{
    public function __construct(
        private readonly QuoteRequestRepository $requests,
        private readonly QuoteRequestService $service,
        private readonly QuoteRequestSerializer $serializer,
        private readonly ValidatorInterface $validator,
    ) {
    }

    #[Route('', name: 'api_admin_quote_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        return $this->json($this->serializer->serializeList($this->requests->findAllForAdmin(), withCustomer: true));
    }

    #[Route('/{id}', name: 'api_admin_quote_get', methods: ['GET'])]
    public function get(string $id): JsonResponse
    {
        $request = $this->requireRequest($id);
        $this->denyAccessUnlessGranted(QuoteRequestVoter::VIEW, $request);

        return $this->json($this->serializer->serialize($request, withCustomer: true));
    }

    #[Route('/{id}/status', name: 'api_admin_quote_status', methods: ['PATCH'])]
    public function updateStatus(string $id, #[MapRequestPayload] UpdateQuoteRequestStatusRequest $req): JsonResponse
    {
        $this->assertValid($req);
        $request = $this->requireRequest($id);
        $this->denyAccessUnlessGranted(QuoteRequestVoter::VIEW, $request);

        $updated = $this->service->updateStatus($request, QuoteRequestStatus::from($req->status));

        return $this->json($this->serializer->serialize($updated, withCustomer: true));
    }

    /** Răspunsul service-ului: text + document opțional → REPLIED. */
    #[Route('/{id}/response', name: 'api_admin_quote_response', methods: ['POST'])]
    public function respond(string $id, #[MapRequestPayload] QuoteResponseRequest $req, DocumentRepository $documents): JsonResponse
    {
        $this->assertValid($req);
        $request = $this->requireRequest($id);
        $this->denyAccessUnlessGranted(QuoteRequestVoter::VIEW, $request);

        $document = null;
        if ($req->documentId !== null && $req->documentId !== '') {
            $document = $this->requireServableDocument($req->documentId, $documents);
        }

        $updated = $this->service->respond($request, $this->currentUser(), $req->message, $document);

        return $this->json($this->serializer->serialize($updated, withCustomer: true));
    }

    private function requireServableDocument(string $id, DocumentRepository $documents): Document
    {
        if (!Uuid::isValid($id) || ($d = $documents->get(Uuid::fromString($id))) === null) {
            throw ValidationFailedException::fromArray(['documentId' => ['Document inexistent.']]);
        }
        if (!$d->isServable()) {
            throw ValidationFailedException::fromArray(['documentId' => ['Documentul nu este disponibil (scanare în curs sau respins).']]);
        }

        return $d;
    }

    private function requireRequest(string $id): QuoteRequest
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
