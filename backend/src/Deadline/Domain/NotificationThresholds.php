<?php

declare(strict_types=1);

namespace App\Deadline\Domain;

/**
 * Pragurile (în zile înainte de expirare) la care se emit notificări.
 * Valori implicite; se pot suprascrie din `application_settings`.
 * Convenție: 0 = ziua expirării; valorile negative = după expirare.
 */
final class NotificationThresholds
{
    /** @var int[] */
    private array $days;

    /** @param int[] $days */
    public function __construct(array $days = [60, 30, 7, 0])
    {
        $unique = array_values(array_unique($days));
        rsort($unique);
        $this->days = $unique;
    }

    /** @return int[] */
    public function all(): array
    {
        return $this->days;
    }

    /**
     * Determină dacă pentru un anumit „zile rămase” trebuie declanșată o
     * notificare (adică $daysLeft coincide cu un prag configurat).
     */
    public function shouldNotify(int $daysLeft): bool
    {
        return in_array($daysLeft, $this->days, true);
    }
}
