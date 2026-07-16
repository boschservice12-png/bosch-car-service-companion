<?php

declare(strict_types=1);

namespace App\Vehicle\Presentation;

use App\Identity\Domain\User;
use App\Vehicle\Domain\Vehicle;
use App\Vehicle\Domain\VehicleRepository;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Autorizare la nivel de obiect (nu doar ascundere în UI): un client poate
 * accesa/edita doar vehiculele al căror proprietar activ este. Adminul are acces.
 */
final class VehicleVoter extends Voter
{
    public const VIEW = 'VEHICLE_VIEW';
    public const EDIT = 'VEHICLE_EDIT';

    public function __construct(private readonly VehicleRepository $vehicles)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::EDIT], true) && $subject instanceof Vehicle;
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

        $profile = $user->customerProfile();
        if ($profile === null) {
            return false;
        }

        \assert($subject instanceof Vehicle);

        return $this->vehicles->isActiveOwner($subject, $profile);
    }
}
