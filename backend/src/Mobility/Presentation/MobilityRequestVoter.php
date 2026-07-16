<?php

declare(strict_types=1);

namespace App\Mobility\Presentation;

use App\Identity\Domain\User;
use App\Mobility\Domain\MobilityRequest;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/** Autorizare la nivel de obiect: doar clientul proprietar sau un admin. */
final class MobilityRequestVoter extends Voter
{
    public const VIEW = 'MOBILITY_VIEW';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $attribute === self::VIEW && $subject instanceof MobilityRequest;
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

        \assert($subject instanceof MobilityRequest);

        return $subject->customer()->id()->equals($user->id());
    }
}
