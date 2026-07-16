<?php

declare(strict_types=1);

namespace App\DamageClaim\Presentation;

use App\DamageClaim\Domain\DamageClaim;
use App\Identity\Domain\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/** Autorizare la nivel de obiect: doar clientul proprietar sau un admin. */
final class DamageClaimVoter extends Voter
{
    public const VIEW = 'DAMAGE_CLAIM_VIEW';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $attribute === self::VIEW && $subject instanceof DamageClaim;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }
        if ($user->isServiceAdmin()) {
            return true;
        }

        \assert($subject instanceof DamageClaim);

        return $subject->customer()->id()->equals($user->id());
    }
}
