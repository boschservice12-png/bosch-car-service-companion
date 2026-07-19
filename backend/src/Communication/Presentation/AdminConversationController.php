<?php

declare(strict_types=1);

namespace App\Communication\Presentation;

use App\Communication\Application\CommunicationService;
use App\Communication\Domain\Conversation;
use App\Communication\Domain\ConversationRepository;
use App\Communication\Domain\MessageAuthorRole;
use App\Communication\Presentation\Dto\PostMessageRequest;
use App\Document\Domain\DocumentRepository;
use App\Identity\Domain\User;
use App\Shared\Presentation\ValidationFailedException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Portal service (ADMIN). Rutele sunt sub /api/admin → protejate de access_control
 * (ROLE_SERVICE_ADMIN). Adminul vede toate conversațiile, răspunde și trimite oferte.
 */
#[Route('/api/admin/conversations')]
final class AdminConversationController extends AbstractController
{
    use AttachmentResolver;

    public function __construct(
        private readonly ConversationRepository $conversations,
        private readonly CommunicationService $service,
        private readonly ConversationSerializer $serializer,
        private readonly ValidatorInterface $validator,
    ) {
    }

    #[Route('', name: 'api_admin_conversations_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        return $this->json($this->serializer->serializeList($this->conversations->findAllForAdmin(), withCustomer: true));
    }

    #[Route('/{id}', name: 'api_admin_conversations_get', methods: ['GET'])]
    public function get(string $id): JsonResponse
    {
        return $this->json($this->serializer->detail($this->requireConversation($id), withCustomer: true));
    }

    #[Route('/{id}/messages', name: 'api_admin_conversations_reply', methods: ['POST'])]
    public function reply(string $id, #[MapRequestPayload] PostMessageRequest $req, DocumentRepository $documents): JsonResponse
    {
        $this->assertValid($req);
        $conversation = $this->requireConversation($id);

        $attachments = $this->resolveAttachments($req->documentIds, $documents);
        $this->service->addMessage($conversation, $this->currentUser(), MessageAuthorRole::ADMIN, $req->body, $attachments);

        return $this->json($this->serializer->detail($conversation, withCustomer: true));
    }

    /** Închide conversația (specificație: adminul „închide și redeschide"). */
    #[Route('/{id}/close', name: 'api_admin_conversations_close', methods: ['POST'])]
    public function close(string $id): JsonResponse
    {
        $updated = $this->service->close($this->requireConversation($id));

        return $this->json($this->serializer->detail($updated, withCustomer: true));
    }

    #[Route('/{id}/reopen', name: 'api_admin_conversations_reopen', methods: ['POST'])]
    public function reopen(string $id): JsonResponse
    {
        $updated = $this->service->reopen($this->requireConversation($id));

        return $this->json($this->serializer->detail($updated, withCustomer: true));
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
            throw ValidationFailedException::fromViolations($violations);
        }
    }
}
