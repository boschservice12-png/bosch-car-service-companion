<?php

declare(strict_types=1);

namespace App\Tax\Presentation;

use App\Shared\Presentation\ValidationFailedException;
use App\Tax\Application\TaxService;
use App\Tax\Domain\PaymentStatus;
use App\Tax\Domain\TaxItem;
use App\Tax\Domain\TaxItemRepository;
use App\Tax\Presentation\Dto\UpdateTaxStatusRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Portal service (ADMIN) — sub /api/admin (ROLE_SERVICE_ADMIN). Adminul vede toate
 * taxele clienților și le poate marca plătite/neplătite (cu notă).
 */
#[Route('/api/admin/taxes')]
final class AdminTaxController extends AbstractController
{
    public function __construct(
        private readonly TaxItemRepository $items,
        private readonly TaxService $service,
        private readonly TaxSerializer $serializer,
        private readonly ValidatorInterface $validator,
    ) {
    }

    #[Route('', name: 'api_admin_tax_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        return $this->json($this->serializer->serializeList($this->items->findAllForAdmin(), withCustomer: true));
    }

    #[Route('/{id}', name: 'api_admin_tax_get', methods: ['GET'])]
    public function get(string $id): JsonResponse
    {
        return $this->json($this->serializer->serialize($this->requireItem($id), withCustomer: true));
    }

    #[Route('/{id}', name: 'api_admin_tax_update', methods: ['PATCH'])]
    public function update(string $id, #[MapRequestPayload] UpdateTaxStatusRequest $req): JsonResponse
    {
        $this->assertValid($req);
        $item = $this->requireItem($id);
        $updated = $this->service->setStatus($item, PaymentStatus::from($req->status), $req->note);

        return $this->json($this->serializer->serialize($updated, withCustomer: true));
    }

    private function requireItem(string $id): TaxItem
    {
        if (!Uuid::isValid($id) || ($t = $this->items->get(Uuid::fromString($id))) === null) {
            throw $this->createNotFoundException('Taxă inexistentă.');
        }

        return $t;
    }

    private function assertValid(object $dto): void
    {
        $violations = $this->validator->validate($dto);
        if (count($violations) > 0) {
            throw ValidationFailedException::fromViolations($violations);
        }
    }
}
