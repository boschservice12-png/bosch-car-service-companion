<?php

declare(strict_types=1);

namespace App\Tax\Presentation;

use App\Identity\Domain\User;
use App\Shared\Presentation\ValidationFailedException;
use App\Tax\Application\TaxService;
use App\Tax\Domain\TaxItem;
use App\Tax\Domain\TaxItemRepository;
use App\Tax\Domain\TaxType;
use App\Tax\Presentation\Dto\CreateTaxItemRequest;
use App\Tax\Presentation\Dto\UpdateTaxItemRequest;
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
 * propriile taxe (TaxItemVoter): le adaugă, le editează, le marchează plătite
 * (integral sau parțial) și le poate șterge. Nu se încarcă niciun fișier —
 * evidența plății este declarativă, fără bon fiscal sau alte documente.
 */
final class ClientTaxController extends AbstractController
{
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

    /**
     * Editare: doar câmpurile trimise se schimbă. O taxă plătită integral nu se
     * mai editează — corecțiile trec prin service (adminul o readuce la neplătită).
     */
    #[Route('/api/taxes/{id}', name: 'api_tax_update', methods: ['PATCH'])]
    public function update(string $id, #[MapRequestPayload] UpdateTaxItemRequest $req): JsonResponse
    {
        $this->assertValid($req);
        $item = $this->requireItem($id);
        $this->denyAccessUnlessGranted(TaxItemVoter::VIEW, $item);
        if ($item->isPaid()) {
            throw ValidationFailedException::fromArray(['status' => ['O taxă plătită nu mai poate fi modificată — cereți service-ului o corecție.']]);
        }

        $vehicle = $item->vehicle();
        if ($req->vehicleId !== null) {
            if ($req->vehicleId === '') {
                $vehicle = null;
            } else {
                $vehicle = $this->requireVehicle($req->vehicleId);
                $this->denyAccessUnlessGranted(VehicleVoter::VIEW, $vehicle);
            }
        }

        $dueDate = $item->dueDate();
        if ($req->dueDate !== null) {
            $dueDate = $req->dueDate === '' ? null : $this->parseDate($req->dueDate);
        }

        $updated = $this->service->update(
            $item,
            $vehicle,
            $req->year ?? $item->year(),
            $req->type !== null ? TaxType::from($req->type) : $item->type(),
            $req->amount !== null ? (int) round($req->amount * 100) : $item->amountBani(),
            $dueDate,
        );

        return $this->json($this->serializer->serialize($updated, withCustomer: false));
    }

    /** Ștergere de către proprietar. O taxă plătită integral nu se șterge. */
    #[Route('/api/taxes/{id}', name: 'api_tax_delete', methods: ['DELETE'])]
    public function delete(string $id): Response
    {
        $item = $this->requireItem($id);
        $this->denyAccessUnlessGranted(TaxItemVoter::VIEW, $item);
        if ($item->isPaid()) {
            throw ValidationFailedException::fromArray(['status' => ['O taxă plătită nu poate fi ștearsă — cereți service-ului o corecție.']]);
        }

        $this->service->delete($item);

        return new Response(null, 204);
    }

    /** Fără sumă → plată integrală; cu `amount` (RON) → plată parțială/cumulativă. */
    #[Route('/api/taxes/{id}/pay', name: 'api_tax_pay', methods: ['POST'])]
    public function pay(string $id, Request $request): JsonResponse
    {
        $item = $this->requireItem($id);
        $this->denyAccessUnlessGranted(TaxItemVoter::VIEW, $item);

        /** @var array{amount?: float|int|string} $payload */
        $payload = json_decode($request->getContent(), true) ?: [];

        $updated = isset($payload['amount']) && is_numeric($payload['amount'])
            ? $this->service->registerPayment($item, (int) round(((float) $payload['amount']) * 100))
            : $this->service->markPaid($item);

        return $this->json($this->serializer->serialize($updated, withCustomer: false));
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
