<?php

declare(strict_types=1);

namespace App\QuoteRequest\Presentation;

use App\Identity\Domain\User;
use App\QuoteRequest\Domain\QuoteRequest;
use App\QuoteRequest\Domain\QuoteRequestStatus;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Autorizare la nivel de obiect: clientul își vede doar propriile cereri;
 * adminul le vede pe toate, cu excepția ciornelor (acelea sunt private).
 */
final class QuoteRequestVoter extends Voter
{
    public const VIEW = 'QUOTE_REQUEST_VIEW';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $attribute === self::VIEW && $subject instanceof QuoteRequest;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }
        \assert($subject instanceof QuoteRequest);
        if ($user->isServiceAdmin()) {
            return $subject->status() !== QuoteRequestStatus::DRAFT;
        }

        return $subject->customer()->id()->equals($user->id());
    }
}
