<?php

declare(strict_types=1);

namespace App\Tax\Presentation;

use App\Identity\Domain\User;
use App\Tax\Domain\TaxItem;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/** Autorizare la nivel de obiect: doar clientul proprietar sau un admin. */
final class TaxItemVoter extends Voter
{
    public const VIEW = 'TAX_VIEW';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $attribute === self::VIEW && $subject instanceof TaxItem;
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

        \assert($subject instanceof TaxItem);

        return $subject->customer()->id()->equals($user->id());
    }
}
