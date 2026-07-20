<?php

declare(strict_types=1);

namespace App\Deadline\Presentation;

use App\Audit\Application\AuditRecorder;
use App\Deadline\Domain\DeadlineNotification;
use App\Deadline\Domain\DeadlineStatusCalculator;
use App\Deadline\Domain\VehicleDeadlineRepository;
use App\Vehicle\Domain\VehicleRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

/**
 * P1-03 (decizie de produs): notificările de scadență pleacă de pe WhatsApp-ul
 * PROPRIU al service-ului (fără API plătit) și pe email — operatorul vede aici
 * scadențele apropiate cu datele de contact, aplicația compune mesajul
 * (wa.me / mailto), iar trimiterea se consemnează per canal.
 */
final class AdminDeadlineNotificationController extends AbstractController
{
    private const CHANNELS = ['whatsapp', 'email'];

    public function __construct(
        private readonly VehicleDeadlineRepository $deadlines,
        private readonly VehicleRepository $vehicles,
        private readonly DeadlineStatusCalculator $calculator,
        private readonly AuditRecorder $audit,
    ) {
    }

    /** Scadențele care expiră în cel mult ?days zile (implicit 30), cu contactul proprietarului. */
    #[Route('/api/admin/deadlines/upcoming', name: 'admin_deadlines_upcoming', methods: ['GET'])]
    public function upcoming(Request $request): JsonResponse
    {
        $days = max(1, min(365, (int) ($request->query->get('days') ?? 30)));
        $now = new \DateTimeImmutable();

        $deadlines = $this->deadlines->findExpiringWithin($days);
        $lastNotified = $this->deadlines->lastManualNotifications(
            array_map(static fn ($d) => (string) $d->id(), $deadlines),
        );

        $items = [];
        foreach ($deadlines as $deadline) {
            $vehicle = $deadline->vehicle();
            $owner = $this->vehicles->findActiveOwner($vehicle);
            $email = $owner?->user()->getEmail();
            // Adresele interne de import nu sunt adrese reale de contact.
            if ($email !== null && str_ends_with($email, '@clienti.local')) {
                $email = null;
            }

            $state = $this->calculator->calculate($deadline->expiresAt(), $now);
            $items[] = [
                'id' => (string) $deadline->id(),
                'type' => $deadline->type()->value,
                'typeLabel' => $deadline->type()->label(),
                'expiresAt' => $deadline->expiresAt()?->format('Y-m-d'),
                'daysLeft' => $this->calculator->daysLeft($deadline->expiresAt(), $now),
                'stateLabel' => $state->label(),
                'vehicle' => [
                    'id' => (string) $vehicle->id(),
                    'plateNumber' => $vehicle->plateNumber(),
                    'make' => $vehicle->make(),
                    'model' => $vehicle->model(),
                ],
                'owner' => $owner === null ? null : [
                    'name' => $owner->fullName() ?: null,
                    'phone' => $owner->phone(),
                    'email' => $email,
                ],
                'lastNotifiedAt' => isset($lastNotified[(string) $deadline->id()])
                    ? $lastNotified[(string) $deadline->id()]->format(\DateTimeInterface::ATOM)
                    : null,
            ];
        }

        return $this->json(['days' => $days, 'items' => $items]);
    }

    /** Consemnează o notificare trimisă manual (whatsapp sau email). */
    #[Route('/api/admin/deadlines/{id}/notifications', name: 'admin_deadline_notify', methods: ['POST'])]
    public function markNotified(string $id, Request $request): JsonResponse
    {
        /** @var array{channel?: mixed} $payload */
        $payload = json_decode((string) $request->getContent(), true) ?: [];
        $channel = $payload['channel'] ?? null;
        if (!\is_string($channel) || !\in_array($channel, self::CHANNELS, true)) {
            throw new \InvalidArgumentException('Canal necunoscut — folosiți "whatsapp" sau "email".');
        }

        $deadline = $this->deadlines->get(Uuid::fromString($id));
        if ($deadline === null) {
            throw new NotFoundHttpException('Scadența nu există.');
        }

        $daysLeft = $this->calculator->daysLeft($deadline->expiresAt(), new \DateTimeImmutable()) ?? 0;
        if (!$this->deadlines->notificationAlreadySent($deadline, $daysLeft, $channel)) {
            $this->deadlines->recordNotification(new DeadlineNotification($deadline, $daysLeft, $channel));
        }
        $this->audit->record('deadline.manual_notification', 'VehicleDeadline', $id, null, [
            'channel' => $channel,
            'daysLeft' => $daysLeft,
        ]);

        return $this->json(['ok' => true, 'notifiedAt' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM)]);
    }
}
