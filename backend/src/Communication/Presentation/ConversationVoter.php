<?php

declare(strict_types=1);

namespace App\Communication\Presentation;

use App\Communication\Domain\Conversation;
use App\Identity\Domain\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Autorizare la nivel de obiect: o conversație poate fi văzută/continuată doar de
 * clientul proprietar sau de un administrator al service-ului.
 */
final class ConversationVoter extends Voter
{
    public const VIEW = 'CONVERSATION_VIEW';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $attribute === self::VIEW && $subject instanceof Conversation;
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

        \assert($subject instanceof Conversation);

        return $subject->customer()->id()->equals($user->id());
    }
}
