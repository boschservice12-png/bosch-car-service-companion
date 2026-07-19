<?php

declare(strict_types=1);

namespace App\ServiceHistory\Presentation;

use App\Document\Domain\Document;
use App\Document\Domain\DocumentRepository;
use App\Document\Domain\StorageAdapter;
use App\Identity\Domain\User;
use App\ServiceHistory\Domain\ServiceRecord;
use App\ServiceHistory\Domain\ServiceRecordRepository;
use App\Vehicle\Domain\Vehicle;
use App\Vehicle\Domain\VehicleRepository;
use App\ServiceHistory\Application\ServiceRecordPdfGenerator;
use App\Vehicle\Presentation\VehicleVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

/**
 * Istoricul de service din perspectiva CLIENTULUI (și, cu acces mai larg, admin).
 * Un client vede doar înregistrările PUBLICATE ale propriilor vehicule (autorizare
 * la nivel de obiect prin VehicleVoter). Ciornele nu sunt vizibile clientului.
 */
final class ClientServiceRecordController extends AbstractController
{
    public function __construct(
        private readonly VehicleRepository $vehicles,
        private readonly ServiceRecordRepository $records,
        private readonly ServiceRecordSerializer $serializer,
    ) {
    }

    #[Route('/api/vehicles/{vehicleId}/service-records', name: 'api_service_records_list', methods: ['GET'])]
    public function list(string $vehicleId): JsonResponse
    {
        $vehicle = $this->requireVehicle($vehicleId);
        $this->denyAccessUnlessGranted(VehicleVoter::VIEW, $vehicle);

        // Clientul primește doar publicatele; adminul, dacă folosește această rută, la fel.
        return $this->json($this->serializer->serializeList($this->records->findForVehicle($vehicle, includeDrafts: false)));
    }

    #[Route('/api/service-records/{id}', name: 'api_service_records_get', methods: ['GET'])]
    public function get(string $id): JsonResponse
    {
        $record = $this->requireRecord($id);
        $this->denyAccessUnlessGranted(VehicleVoter::VIEW, $record->vehicle());
        $this->assertVisible($record);

        $correctedIds = $this->correctedIds($record->vehicle());

        return $this->json($this->serializer->serialize($record, $correctedIds));
    }

    /**
     * Descărcare autorizată a unui document atașat unei înregistrări.
     * Autorizarea trece prin *vehicul* (proprietarul clientului sau admin), nu prin
     * proprietarul documentului (care e adminul care l-a încărcat). Servire directă,
     * same-origin, în spatele firewall-ului.
     */
    /** PDF pentru o intrare publicată (specificație: „se poate genera PDF"). */
    #[Route('/api/service-records/{id}/pdf', name: 'api_service_records_pdf', methods: ['GET'])]
    public function pdf(string $id, ServiceRecordPdfGenerator $generator): Response
    {
        $record = $this->requireRecord($id);
        $this->denyAccessUnlessGranted(VehicleVoter::VIEW, $record->vehicle());
        $this->assertVisible($record);

        return new Response($generator->forRecord($record), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf('attachment; filename="istoric-service-%s.pdf"', $record->serviceDate()?->format('Y-m-d') ?? 'intrare'),
        ]);
    }

    /** PDF pentru întregul istoric publicat al vehiculului. */
    #[Route('/api/vehicles/{vehicleId}/service-records/pdf', name: 'api_service_records_vehicle_pdf', methods: ['GET'])]
    public function vehiclePdf(string $vehicleId, ServiceRecordPdfGenerator $generator): Response
    {
        $vehicle = $this->requireVehicle($vehicleId);
        $this->denyAccessUnlessGranted(VehicleVoter::VIEW, $vehicle);

        $records = $this->records->findForVehicle($vehicle, includeDrafts: false);

        return new Response($generator->forVehicle($vehicle, $records), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf('attachment; filename="istoric-%s.pdf"', preg_replace('/[^A-Za-z0-9]+/', '-', $vehicle->plateNumber())),
        ]);
    }

    #[Route('/api/service-records/{recordId}/documents/{docId}', name: 'api_service_records_document', methods: ['GET'])]
    public function document(string $recordId, string $docId, DocumentRepository $documents, StorageAdapter $storage): Response
    {
        $record = $this->requireRecord($recordId);
        $this->denyAccessUnlessGranted(VehicleVoter::VIEW, $record->vehicle());
        $this->assertVisible($record);

        if (!Uuid::isValid($docId)) {
            throw $this->createNotFoundException('Document inexistent.');
        }
        $document = $documents->get(Uuid::fromString($docId));
        if ($document === null || !$record->hasDocument($document)) {
            throw $this->createNotFoundException('Documentul nu aparține acestei înregistrări.');
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

    /** Ciornele nu sunt vizibile clientului; adminul le poate vedea. */
    private function assertVisible(ServiceRecord $record): void
    {
        if (!$record->isPublished() && !$this->isGranted(User::ROLE_SERVICE_ADMIN)) {
            throw $this->createNotFoundException('Înregistrare de service inexistentă.');
        }
    }

    private function requireVehicle(string $id): Vehicle
    {
        if (!Uuid::isValid($id) || ($v = $this->vehicles->get(Uuid::fromString($id))) === null) {
            throw $this->createNotFoundException('Vehicul inexistent.');
        }

        return $v;
    }

    private function requireRecord(string $id): ServiceRecord
    {
        if (!Uuid::isValid($id) || ($r = $this->records->get(Uuid::fromString($id))) === null) {
            throw $this->createNotFoundException('Înregistrare de service inexistentă.');
        }

        return $r;
    }

    /** @return string[] */
    private function correctedIds(Vehicle $vehicle): array
    {
        $ids = [];
        foreach ($this->records->findForVehicle($vehicle, includeDrafts: false) as $r) {
            if ($r->correctionOf() !== null) {
                $ids[] = (string) $r->correctionOf()->id();
            }
        }

        return array_values(array_unique($ids));
    }
}
