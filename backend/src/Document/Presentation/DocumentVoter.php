<?php

declare(strict_types=1);

namespace App\Document\Presentation;

use App\Document\Domain\Document;
use App\Identity\Domain\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Autorizare la nivel de obiect pentru documente: proprietarul (cel care a
 * încărcat) sau un admin al service-ului. Ceilalți clienți nu au acces.
 */
final class DocumentVoter extends Voter
{
    public const VIEW = 'DOCUMENT_VIEW';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $attribute === self::VIEW && $subject instanceof Document;
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

        \assert($subject instanceof Document);
        $owner = $subject->owner();

        return $owner !== null && $owner->id()->equals($user->id());
    }
}
