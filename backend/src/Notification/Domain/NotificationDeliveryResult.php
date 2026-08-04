<?php

declare(strict_types=1);

namespace App\Notification\Domain;

/**
 * Rezultatul unei încercări de livrare, întors de un NotificationDelivery.
 * Modelează adevărul: doar `sent()` marchează efectiv livrarea.
 */
final class NotificationDeliveryResult
{
    private function __construct(
        public readonly NotificationStatus $status,
        public readonly ?string $provider = null,
        public readonly ?string $reason = null,
        public readonly bool $retryable = false,
    ) {
    }

    public static function sent(string $provider): self
    {
        return new self(NotificationStatus::SENT, $provider);
    }

    /** Eșec la un furnizor automat; `retryable` true → Messenger reîncearcă. */
    public static function failed(string $reason, ?string $provider = null, bool $retryable = true): self
    {
        return new self(NotificationStatus::FAILED, $provider, $reason, $retryable);
    }

    /** Fără furnizor automat — trimiterea o face un om. Nu se reîncearcă. */
    public static function manual(?string $reason = null): self
    {
        return new self(NotificationStatus::MANUAL_ACTION_REQUIRED, null, $reason);
    }

    /** Nu are rost trimiterea (fără destinatar / consimțământ / duplicat). */
    public static function skipped(?string $reason = null): self
    {
        return new self(NotificationStatus::SKIPPED, null, $reason);
    }
}
