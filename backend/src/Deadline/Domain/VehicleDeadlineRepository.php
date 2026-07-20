<?php

declare(strict_types=1);

namespace App\Deadline\Domain;

use App\Vehicle\Domain\Vehicle;
use Symfony\Component\Uid\Uuid;

interface VehicleDeadlineRepository
{
    public function save(VehicleDeadline $deadline): void;

    public function get(Uuid $id): ?VehicleDeadline;

    /** @return VehicleDeadline[] */
    public function findForVehicle(Vehicle $vehicle): array;

    /** Scadențe cu dată de expirare setată (pentru scanarea notificărilor).
     * @return VehicleDeadline[]
     */
    public function findWithExpiry(): array;

    public function notificationAlreadySent(VehicleDeadline $deadline, int $thresholdDays, string $channel): bool;

    public function recordNotification(DeadlineNotification $notification): void;

    /**
     * P1-03: scadențele care expiră în cel mult $days zile (inclusiv cele
     * deja expirate), ordonate crescător după data expirării.
     *
     * @return VehicleDeadline[]
     */
    public function findExpiringWithin(int $days): array;

    /**
     * Ultima notificare MANUALĂ (whatsapp/email, trimisă de operator) per
     * scadență, pentru lista dată de id-uri.
     *
     * @param list<string> $deadlineIds
     *
     * @return array<string, \DateTimeImmutable>
     */
    public function lastManualNotifications(array $deadlineIds): array;
}
