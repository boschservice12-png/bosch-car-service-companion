<?php

declare(strict_types=1);

namespace App\Communication\Presentation;

use App\Communication\Application\CommunicationService;
use App\Communication\Domain\Conversation;
use App\Communication\Domain\ConversationRepository;
use App\Communication\Domain\Message;
use App\Communication\Domain\MessageAuthorRole;
use App\Communication\Presentation\Dto\PostMessageRequest;
use App\Communication\Presentation\Dto\StartConversationRequest;
use App\Document\Domain\Document;
use App\Document\Domain\DocumentRepository;
use App\Document\Domain\StorageAdapter;
use App\Identity\Domain\User;
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
 * Comunicare client ↔ service și cereri de ofertă, din perspectiva CLIENTULUI.
 * Un client vede și continuă doar propriile conversații (ConversationVoter).
 */
final class ClientConversationController extends AbstractController
{
    use AttachmentResolver;

    public function __construct(
        private readonly ConversationRepository $conversations,
        private readonly CommunicationService $service,
        private readonly ConversationSerializer $serializer,
        private readonly VehicleRepository $vehicles,
        private readonly ValidatorInterface $validator,
    ) {
    }

    #[Route('/api/conversations', name: 'api_conversations_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $items = $this->conversations->findForCustomer($this->currentUser());

        return $this->json($this->serializer->serializeList($items, withCustomer: false));
    }

    #[Route('/api/conversations', name: 'api_conversations_start', methods: ['POST'])]
    public function start(#[MapRequestPayload] StartConversationRequest $req, DocumentRepository $documents): JsonResponse
    {
        $this->assertValid($req);

        $vehicle = null;
        if ($req->vehicleId !== null) {
            $vehicle = $this->requireVehicle($req->vehicleId);
            $this->denyAccessUnlessGranted(VehicleVoter::VIEW, $vehicle);
        }
        $attachments = $this->resolveAttachments($req->documentIds, $documents);

        $conversation = $this->service->start(
            $this->currentUser(),
            $req->subject,
            $vehicle,
            $req->body,
            $attachments,
        );

        return $this->json($this->serializer->detail($conversation, withCustomer: false), 201);
    }

    #[Route('/api/conversations/{id}', name: 'api_conversations_get', methods: ['GET'])]
    public function get(string $id): JsonResponse
    {
        $conversation = $this->requireConversation($id);
        $this->denyAccessUnlessGranted(ConversationVoter::VIEW, $conversation);

        return $this->json($this->serializer->detail($conversation, withCustomer: false));
    }

    /** Mesajele conversației (specificație: GET /api/conversations/{id}/messages). */
    #[Route('/api/conversations/{id}/messages', name: 'api_conversations_messages', methods: ['GET'])]
    public function messages(string $id): JsonResponse
    {
        $conversation = $this->requireConversation($id);
        $this->denyAccessUnlessGranted(ConversationVoter::VIEW, $conversation);

        return $this->json($this->serializer->messages($conversation));
    }

    #[Route('/api/conversations/{id}/messages', name: 'api_conversations_post_message', methods: ['POST'])]
    public function postMessage(string $id, #[MapRequestPayload] PostMessageRequest $req, DocumentRepository $documents): JsonResponse
    {
        $this->assertValid($req);
        $conversation = $this->requireConversation($id);
        $this->denyAccessUnlessGranted(ConversationVoter::VIEW, $conversation);

        $attachments = $this->resolveAttachments($req->documentIds, $documents);
        $this->service->addMessage($conversation, $this->currentUser(), MessageAuthorRole::CLIENT, $req->body, $attachments);

        return $this->json($this->serializer->detail($conversation, withCustomer: false));
    }

    #[Route('/api/conversations/{id}/documents/{docId}', name: 'api_conversations_document', methods: ['GET'])]
    public function document(string $id, string $docId, DocumentRepository $documents, StorageAdapter $storage): Response
    {
        $conversation = $this->requireConversation($id);
        $this->denyAccessUnlessGranted(ConversationVoter::VIEW, $conversation);

        if (!Uuid::isValid($docId)) {
            throw $this->createNotFoundException('Document inexistent.');
        }
        $document = $documents->get(Uuid::fromString($docId));
        if ($document === null || !$this->conversationHasAttachment($conversation, $document)) {
            throw $this->createNotFoundException('Documentul nu aparține acestei conversații.');
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

    private function conversationHasAttachment(Conversation $conversation, Document $document): bool
    {
        foreach ($conversation->messages() as $message) {
            \assert($message instanceof Message);
            if ($message->hasAttachment($document)) {
                return true;
            }
        }

        return false;
    }

    private function requireVehicle(string $id): Vehicle
    {
        if (!Uuid::isValid($id) || ($v = $this->vehicles->get(Uuid::fromString($id))) === null) {
            throw $this->createNotFoundException('Vehicul inexistent.');
        }

        return $v;
    }

    private function requireConversation(string $id): Conversation
    {
        if (!Uuid::isValid($id) || ($c = $this->conversations->get(Uuid::fromString($id))) === null) {
            throw $this->createNotFoundException('Conversație inexistentă.');
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
            throw \App\Shared\Presentation\ValidationFailedException::fromViolations($violations);
        }
    }
}
