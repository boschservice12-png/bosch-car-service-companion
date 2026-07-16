<?php

declare(strict_types=1);

namespace App\Roadside\Presentation;

use App\Identity\Domain\User;
use App\Roadside\Domain\RoadsideRequest;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/** Autorizare la nivel de obiect: doar clientul proprietar sau un admin. */
final class RoadsideRequestVoter extends Voter
{
    public const VIEW = 'ROADSIDE_VIEW';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $attribute === self::VIEW && $subject instanceof RoadsideRequest;
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

        \assert($subject instanceof RoadsideRequest);

        return $subject->customer()->id()->equals($user->id());
    }
}
