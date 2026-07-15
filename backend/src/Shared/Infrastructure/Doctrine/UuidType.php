<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Doctrine;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use Symfony\Component\Uid\Uuid;

/**
 * Tip Doctrine pentru UUID stocat ca șir canonical (CHAR(36)).
 *
 * Motivație: stocarea ca text este portabilă între PostgreSQL (producție) și
 * SQLite (teste rapide), spre deosebire de reprezentarea binară care nu se
 * potrivește corect la comparațiile de chei străine pe SQLite. Pe PostgreSQL,
 * coloanele CHAR(36) funcționează identic pentru identificatorii expuși extern.
 */
final class UuidType extends Type
{
    public const NAME = 'uuid';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL(['length' => 36, 'fixed' => true]);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?Uuid
    {
        if ($value === null || $value instanceof Uuid) {
            return $value;
        }

        return Uuid::fromString((string) $value);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }
        if ($value instanceof Uuid) {
            return (string) $value;
        }

        return (string) Uuid::fromString((string) $value);
    }
}
