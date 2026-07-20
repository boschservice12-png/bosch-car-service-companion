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
 * Înregistrare client (self-service), conform deciziei de produs (P1-02):
 * clientul se înregistrează cu email + parolă și își vede propriile date.
 *
 * Dacă emailul aparține unui cont creat de importul Excel (fără parolă,
 * niciodată activat), înregistrarea REVENDICĂ acel cont: setează parola și
 * consimțământul, iar clientul își vede imediat vehiculele și istoricul
 * importate de service. Un cont deja activat rămâne protejat (eroare de
 * duplicat) — parola existentă nu poate fi suprascrisă prin re-înregistrare.
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
            if ($existing->isServiceAdmin() || $existing->getPassword() !== '') {
                throw ValidationFailedException::fromArray([
                    'email' => ['Există deja un cont cu acest email.'],
                ]);
            }

            return $this->claimImportedAccount($existing, $plainPassword);
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

    /**
     * Activarea unui cont creat de importul Excel: parola se setează acum,
     * consimțământul se înregistrează, iar datele importate (nume, vehicule,
     * istoric) rămân neatinse — evidența service-ului e sursa de adevăr.
     */
    private function claimImportedAccount(User $user, string $plainPassword): User
    {
        $user->setPasswordHash($this->hasher->hashPassword($user, $plainPassword));

        if ($user->customerProfile() === null) {
            $this->em->persist(new CustomerProfile($user));
        }

        $this->em->persist(new Consent(
            $user,
            Consent::TYPE_DATA_PROCESSING,
            true,
            $this->settings->privacyTextVersion(),
        ));
        $this->em->flush();

        $this->audit->record('user.import_account_claimed', 'User', (string) $user->id());

        return $user;
    }
}
