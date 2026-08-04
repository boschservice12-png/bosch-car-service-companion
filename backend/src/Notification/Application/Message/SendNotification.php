<?php

declare(strict_types=1);

namespace App\Notification\Application\Message;

/**
 * Mesaj asincron (Symfony Messenger) pentru trimiterea unei notificări.
 * Rutat spre transportul `async` (vezi config/packages/messenger.yaml).
 */
final class SendNotification
{
    /** @param array<string, mixed> $payload */
    public function __construct(
        public readonly string $userId,
        public readonly string $type,
        public readonly array $payload = [],
        public readonly string $channel = 'push',
        /** Cheie de idempotență: la reîncercări/duplicate se refolosește notificarea. */
        public readonly ?string $dedupKey = null,
    ) {
    }
}
