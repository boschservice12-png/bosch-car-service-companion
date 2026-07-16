<?php

declare(strict_types=1);

namespace App\Vehicle\Domain;

use InvalidArgumentException;

/**
 * Value object VIN (Vehicle Identification Number).
 *
 * Reguli:
 * - 17 caractere;
 * - normalizat uppercase;
 * - fără literele I, O, Q (standard ISO 3779);
 * - doar caractere alfanumerice permise.
 */
final class Vin
{
    private const LENGTH = 17;
    private const DISALLOWED = ['I', 'O', 'Q'];

    private string $value;

    public function __construct(string $raw)
    {
        $normalized = strtoupper(trim($raw));

        if (strlen($normalized) !== self::LENGTH) {
            throw new InvalidArgumentException('VIN-ul trebuie să aibă exact 17 caractere.');
        }

        if (preg_match('/[^A-Z0-9]/', $normalized) === 1) {
            throw new InvalidArgumentException('VIN-ul conține caractere nepermise.');
        }

        foreach (self::DISALLOWED as $char) {
            if (str_contains($normalized, $char)) {
                throw new InvalidArgumentException(
                    sprintf('VIN-ul nu poate conține caracterul „%s”.', $char)
                );
            }
        }

        $this->value = $normalized;
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
