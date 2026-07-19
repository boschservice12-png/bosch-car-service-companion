<?php

declare(strict_types=1);

namespace App\Identity\Security;

use App\Identity\Domain\User;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * P0-07: un cont dezactivat (isActive=false) NU se poate autentifica, chiar
 * dacă parola este corectă. Sesiunile deja deschise sunt invalidate prin
 * User::isEqualTo() (EquatableInterface) la următoarea cerere.
 */
final class ActiveUserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if ($user instanceof User && !$user->isActive()) {
            throw new CustomUserMessageAccountStatusException('Contul este dezactivat. Contactați service-ul.');
        }
    }

    public function checkPostAuth(UserInterface $user): void
    {
    }
}
