<?php

declare(strict_types=1);

namespace App\Identity\Application;

use App\Audit\Application\AuditRecorder;
use App\Customer\Domain\Consent;
use App\Customer\Domain\CustomerProfile;
use App\Identity\Domain\User;
use App\Settings\Application\SettingsProvider;
use App\Shared\Presentation\ValidationFailedException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Înregistrare client (self-service). Creează User (ROLE_USER) + CustomerProfile
 * și înregistrează consimțământul. Onboarding-ul poate fi schimbat pe „invitație
 * de la admin" după clarificarea întrebării blocante #8.
 */
final class RegisterUser
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly AuditRecorder $audit,
        private readonly SettingsProvider $settings,
    ) {
    }

    public function __invoke(string $email, string $plainPassword, ?string $firstName, ?string $lastName): User
    {
        $email = strtolower(trim($email));

        $existing = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($existing !== null) {
            throw ValidationFailedException::fromArray([
                'email' => ['Există deja un cont cu acest email.'],
            ]);
        }

        $user = new User($email, User::ROLE_CLIENT);
        $user->setPasswordHash($this->hasher->hashPassword($user, $plainPassword));

        $profile = new CustomerProfile($user, $firstName, $lastName);

        // Evidența consimțământului GDPR (versiunea informării în vigoare).
        $consent = new Consent(
            $user,
            Consent::TYPE_DATA_PROCESSING,
            true,
            $this->settings->privacyTextVersion(),
        );

        $this->em->persist($user);
        $this->em->persist($profile);
        $this->em->persist($consent);
        $this->em->flush();

        $this->audit->record('user.registered', 'User', (string) $user->id());

        return $user;
    }
}
