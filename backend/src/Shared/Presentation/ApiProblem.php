<?php

declare(strict_types=1);

namespace App\Shared\Presentation;

/**
 * Structură unică de eroare (application/problem+json).
 *
 * Exemplu:
 * {
 *   "type": "validation_error",
 *   "title": "Date invalide",
 *   "status": 422,
 *   "errors": { "expiresAt": ["Data expirării trebuie să fie ulterioară datei de început."] },
 *   "traceId": "..."
 * }
 */
final class ApiProblem
{
    /** @param array<string, string[]> $errors */
    public function __construct(
        public readonly string $type,
        public readonly string $title,
        public readonly int $status,
        public readonly string $traceId,
        public readonly array $errors = [],
    ) {
    }

    public static function validation(string $traceId, array $errors): self
    {
        return new self('validation_error', 'Date invalide', 422, $traceId, $errors);
    }

    public static function forbidden(string $traceId): self
    {
        return new self('forbidden', 'Acces interzis', 403, $traceId);
    }

    public static function notFound(string $traceId): self
    {
        return new self('not_found', 'Resursa nu a fost găsită', 404, $traceId);
    }

    public static function conflict(string $traceId, string $title = 'Conflict de stare'): self
    {
        return new self('state_conflict', $title, 409, $traceId);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $out = [
            'type' => $this->type,
            'title' => $this->title,
            'status' => $this->status,
            'traceId' => $this->traceId,
        ];

        if ($this->errors !== []) {
            $out['errors'] = $this->errors;
        }

        return $out;
    }
}
