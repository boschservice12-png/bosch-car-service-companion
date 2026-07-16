<?php

declare(strict_types=1);

namespace App\Shared\Presentation;

use RuntimeException;
use Symfony\Component\Validator\ConstraintViolationListInterface;

/**
 * Aruncată când validarea unui payload eșuează. Transportă erorile per câmp
 * pentru a fi transformate în structura standard `ApiProblem` (422).
 */
final class ValidationFailedException extends RuntimeException
{
    /** @param array<string, string[]> $errors */
    public function __construct(public readonly array $errors)
    {
        parent::__construct('Date invalide');
    }

    public static function fromViolations(ConstraintViolationListInterface $violations): self
    {
        $errors = [];
        foreach ($violations as $violation) {
            $field = self::normalizePath($violation->getPropertyPath());
            $errors[$field][] = (string) $violation->getMessage();
        }

        return new self($errors);
    }

    /** @param array<string, string[]> $errors */
    public static function fromArray(array $errors): self
    {
        return new self($errors);
    }

    private static function normalizePath(string $path): string
    {
        return lcfirst($path) !== '' ? $path : '_';
    }
}
