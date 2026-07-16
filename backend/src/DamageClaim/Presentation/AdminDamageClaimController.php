<?php

declare(strict_types=1);

namespace App\DamageClaim\Presentation;

use App\DamageClaim\Application\DamageClaimService;
use App\DamageClaim\Domain\DamageClaim;
use App\DamageClaim\Domain\DamageClaimRepository;
use App\DamageClaim\Domain\DamageClaimStatus;
use App\DamageClaim\Presentation\Dto\UpdateDamageClaimStatusRequest;
use App\Shared\Presentation\ValidationFailedException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
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
        $updated = $this->service->updateStatus($claim, DamageClaimStatus::from($req->status), $req->note);

        return $this->json($this->serializer->serialize($updated, withCustomer: true));
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
