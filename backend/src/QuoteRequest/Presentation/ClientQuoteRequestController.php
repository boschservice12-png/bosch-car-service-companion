<?php

declare(strict_types=1);

namespace App\QuoteRequest\Presentation;

use App\Identity\Domain\User;
use App\QuoteRequest\Application\QuoteRequestService;
use App\QuoteRequest\Domain\QuoteRequest;
use App\QuoteRequest\Domain\QuoteRequestRepository;
use App\QuoteRequest\Presentation\Dto\CreateQuoteRequest;
use App\Shared\Presentation\ValidationFailedException;
use App\Vehicle\Domain\Vehicle;
use App\Vehicle\Domain\VehicleRepository;
use App\Vehicle\Presentation\VehicleVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Cererile de ofertă din perspectiva CLIENTULUI (funcționalitatea 7).
 * Clientul creează (ciornă sau trimisă), acceptă/refuză oferta primită și
 * completează informațiile cerute. Izolare per client prin QuoteRequestVoter.
 */
final class ClientQuoteRequestController extends AbstractController
{
    public function __construct(
        private readonly QuoteRequestRepository $requests,
        private readonly QuoteRequestService $service,
        private readonly QuoteRequestSerializer $serializer,
        private readonly VehicleRepository $vehicles,
        private readonly ValidatorInterface $validator,
    ) {
    }

    #[Route('/api/quote-requests', name: 'api_quote_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        return $this->json($this->serializer->serializeList($this->requests->findForCustomer($this->currentUser()), withCustomer: false));
    }

    #[Route('/api/quote-requests', name: 'api_quote_create', methods: ['POST'])]
    public function create(#[MapRequestPayload] CreateQuoteRequest $req): JsonResponse
    {
        $this->assertValid($req);

        $vehicle = null;
        if ($req->vehicleId !== null && $req->vehicleId !== '') {
            $vehicle = $this->requireVehicle($req->vehicleId);
            $this->denyAccessUnlessGranted(VehicleVoter::VIEW, $vehicle);
        }

        $request = $this->service->create(
            $this->currentUser(),
            $vehicle,
            $req->mileage,
            $req->symptomDescription,
            $req->occurrenceConditions,
            $req->vehicleDrivable,
            $req->warningLights,
            $req->preferredContactMethod,
            $req->preferredInterval,
            $req->draft,
        );

        return $this->json($this->serializer->serialize($request, withCustomer: false), 201);
    }

    #[Route('/api/quote-requests/{id}', name: 'api_quote_get', methods: ['GET'])]
    public function get(string $id): JsonResponse
    {
        $request = $this->requireRequest($id);
        $this->denyAccessUnlessGranted(QuoteRequestVoter::VIEW, $request);

        return $this->json($this->serializer->serialize($request, withCustomer: false));
    }

    /** Trimite ciorna către service (DRAFT → SUBMITTED). */
    #[Route('/api/quote-requests/{id}/submit', name: 'api_quote_submit', methods: ['POST'])]
    public function submit(string $id): JsonResponse
    {
        return $this->clientTransition($id, fn (QuoteRequest $r) => $this->service->submit($r));
    }

    /** Confirmă completarea informațiilor cerute (NEEDS_INFORMATION → IN_REVIEW). */
    #[Route('/api/quote-requests/{id}/resubmit', name: 'api_quote_resubmit', methods: ['POST'])]
    public function resubmit(string $id): JsonResponse
    {
        return $this->clientTransition($id, fn (QuoteRequest $r) => $this->service->resubmit($r));
    }

    #[Route('/api/quote-requests/{id}/accept', name: 'api_quote_accept', methods: ['POST'])]
    public function accept(string $id): JsonResponse
    {
        return $this->clientTransition($id, fn (QuoteRequest $r) => $this->service->accept($r));
    }

    #[Route('/api/quote-requests/{id}/decline', name: 'api_quote_decline', methods: ['POST'])]
    public function decline(string $id): JsonResponse
    {
        return $this->clientTransition($id, fn (QuoteRequest $r) => $this->service->decline($r));
    }

    /** @param callable(QuoteRequest): QuoteRequest $action */
    private function clientTransition(string $id, callable $action): JsonResponse
    {
        $request = $this->requireRequest($id);
        if (!$request->customer()->id()->equals($this->currentUser()->id())) {
            throw $this->createAccessDeniedException('Doar clientul poate face această operație.');
        }

        return $this->json($this->serializer->serialize($action($request), withCustomer: false));
    }

    private function requireVehicle(string $id): Vehicle
    {
        if (!Uuid::isValid($id) || ($v = $this->vehicles->get(Uuid::fromString($id))) === null) {
            throw $this->createNotFoundException('Vehicul inexistent.');
        }

        return $v;
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
