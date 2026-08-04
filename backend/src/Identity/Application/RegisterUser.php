<?php

declare(strict_types=1);

namespace App\Identity\Application;

use App\Audit\Application\AuditRecorder;
use App\Customer\Domain\Consent;
use App\Customer\Domain\CustomerProfile;
use App\Identity\Domain\User;
use App\Settings\Application\SettingsProvider;
use App\Shared\Presentation\ValidationFailedException;
use App\Vehicle\Domain\VehicleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Înregistrare client (self-service), conform deciziei de produs (P1-02):
 * clientul se înregistrează cu email + parolă și își vede propriile date.
 *
 * Dacă emailul aparține unui cont creat de importul Excel (fără parolă,
 * niciodată activat), înregistrarea REVENDICĂ acel cont: setează parola și
 * consimțământul, iar clientul își vede imediat vehiculele și istoricul
 * importate de service. Revendicarea cere dovada proprietății — numărul de
 * înmatriculare al unui vehicul din evidență (fără verificare pe email, e
 * singurul secret partajat client–service). Un cont deja activat rămâne
 * protejat (eroare de duplicat) — parola nu se suprascrie prin re-înregistrare.
 */
final class RegisterUser
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly AuditRecorder $audit,
        private readonly SettingsProvider $settings,
        private readonly VehicleRepository $vehicles,
        /**
         * Blocul 3: revendicarea prin numărul de înmatriculare este DEZACTIVATĂ
         * implicit (numărul nu e secret, nu dovedește proprietatea). Accesul la
         * un vehicul se obține DOAR cu un cod de activare emis de service
         * (VehicleActivationService). Steagul e păstrat doar pentru migrare/regres
         * și e false în dev/prod/pilot.
         */
        #[\Symfony\Component\DependencyInjection\Attribute\Autowire('%env(bool:LEGACY_PLATE_CLAIM_ENABLED)%')]
        private readonly bool $legacyPlateClaimEnabled = false,
    ) {
    }

    public function __invoke(
        string $email,
        string $plainPassword,
        ?string $firstName,
        ?string $lastName,
        ?string $plateNumber = null,
    ): User {
        $email = strtolower(trim($email));

        $existing = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($existing !== null) {
            // Doar conturile ACTIVE de import se pot revendica — un cont
            // dezactivat sau anonimizat (GDPR) nu redevine folosibil.
            if ($existing->isServiceAdmin() || $existing->getPassword() !== '' || !$existing->isActive()) {
                throw ValidationFailedException::fromArray([
                    'email' => ['Există deja un cont cu acest email.'],
                ]);
            }

            // Blocul 3: revendicarea prin număr de înmatriculare e dezactivată
            // implicit — accesul la vehicul se obține DOAR cu un cod de activare
            // emis de service. Numărul de înmatriculare nu dovedește proprietatea.
            if (!$this->legacyPlateClaimEnabled) {
                throw ValidationFailedException::fromArray([
                    'email' => ['Acest email este deja în evidența service-ului. Cereți service-ului un cod de activare pentru vehicul (nu se poate revendica doar cu numărul de înmatriculare).'],
                ]);
            }

            return $this->claimImportedAccount($existing, $plainPassword, $plateNumber);
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
     * Activarea unui cont creat de importul Excel: dovada proprietății este
     * numărul de înmatriculare al unuia dintre vehiculele din evidență
     * (comparat fără spații, insensibil la litere mari/mici). Parola se
     * setează acum, consimțământul se înregistrează, iar datele importate
     * (nume, vehicule, istoric) rămân neatinse — evidența service-ului e
     * sursa de adevăr.
     */
    private function claimImportedAccount(User $user, string $plainPassword, ?string $plateNumber): User
    {
        $this->assertPlateMatches($user, $plateNumber);

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

    private function assertPlateMatches(User $user, ?string $plateNumber): void
    {
        $profile = $user->customerProfile();
        $vehicles = $profile !== null ? $this->vehicles->findActiveForCustomer($profile) : [];
        if ($vehicles === []) {
            // Fără vehicul activ nu există dovadă de proprietate — contul NU se
            // poate revendica self-service (altfel ar fi de ajuns emailul, iar
            // datele personale importate ar putea fi preluate de oricine).
            throw ValidationFailedException::fromArray([
                'plateNumber' => ['Acest email este deja în evidența service-ului, dar fără un vehicul activ. Contactați service-ul pentru activarea contului.'],
            ]);
        }

        $given = (string) preg_replace('/\s+/', '', strtoupper((string) $plateNumber));
        if ($given === '') {
            throw ValidationFailedException::fromArray([
                'plateNumber' => ['Acest email este deja în evidența service-ului. Introduceți numărul de înmatriculare al mașinii pentru a activa contul.'],
            ]);
        }

        foreach ($vehicles as $vehicle) {
            if (preg_replace('/\s+/', '', $vehicle->plateNumber()) === $given) {
                return;
            }
        }

        throw ValidationFailedException::fromArray([
            'plateNumber' => ['Numărul de înmatriculare nu corespunde evidenței service-ului.'],
        ]);
    }
}
