<?php

declare(strict_types=1);

namespace App\Settings\Presentation\Console;

use App\Customer\Domain\Consent;
use App\Customer\Domain\CustomerProfile;
use App\Identity\Domain\ServiceAdmin;
use App\Identity\Domain\User;
use App\Settings\Application\SettingsProvider;
use App\Vehicle\Domain\Vehicle;
use App\Vehicle\Domain\VehicleOwnership;
use App\Vehicle\Domain\Vin;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Populează date demo realiste (idempotent). Creează un admin, un client cu
 * profil + consimțământ, două vehicule și setările implicite ale aplicației.
 */
#[AsCommand(name: 'app:demo:seed', description: 'Populează date demo (idempotent).')]
final class SeedDemoCommand extends Command
{
    private const CLIENT_EMAIL = 'client@bcsc.ro';
    private const ADMIN_EMAIL = 'admin@bcsc.ro';
    private const DEMO_PASSWORD = 'Demo1234!';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly SettingsProvider $settings,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Setări implicite.
        foreach (SettingsProvider::DEFAULTS as $key => $value) {
            if ($this->settings->get($key, null) === null || !$this->settingExists($key)) {
                $this->settings->set($key, $value);
            }
        }

        if ($this->userExists(self::ADMIN_EMAIL)) {
            $io->warning('Datele demo există deja — nimic de făcut.');

            return Command::SUCCESS;
        }

        // Admin.
        $admin = new User(self::ADMIN_EMAIL, User::ROLE_SERVICE_ADMIN);
        $admin->setPasswordHash($this->hasher->hashPassword($admin, self::DEMO_PASSWORD));
        $this->em->persist($admin);
        $this->em->persist(new ServiceAdmin($admin, 'Service Szkaliczki'));

        // Client + profil + consimțământ.
        $client = new User(self::CLIENT_EMAIL, User::ROLE_CLIENT);
        $client->setPasswordHash($this->hasher->hashPassword($client, self::DEMO_PASSWORD));
        $profile = new CustomerProfile($client, 'Ion', 'Popescu');
        $this->em->persist($client);
        $this->em->persist($profile);
        $this->em->persist(new Consent($client, Consent::TYPE_DATA_PROCESSING, true, $this->settings->privacyTextVersion()));

        // Vehicule.
        $v1 = new Vehicle(new Vin('WBA3A5C50EF123456'), 'MS01POP');
        $v1->updateDetails('BMW', 'Seria 3', 2018);
        $v2 = new Vehicle(new Vin('WVWZZZ1KZAW123459'), 'MS02POP');
        $v2->updateDetails('Volkswagen', 'Golf', 2020);
        foreach ([$v1, $v2] as $vehicle) {
            $this->em->persist($vehicle);
            $this->em->persist(new VehicleOwnership($vehicle, $profile));
        }

        $this->em->flush();

        $io->success('Date demo create.');
        $io->listing([
            sprintf('Admin: %s / %s', self::ADMIN_EMAIL, self::DEMO_PASSWORD),
            sprintf('Client: %s / %s', self::CLIENT_EMAIL, self::DEMO_PASSWORD),
            '2 vehicule pentru client (BMW Seria 3, VW Golf)',
        ]);

        return Command::SUCCESS;
    }

    private function userExists(string $email): bool
    {
        return $this->em->getRepository(User::class)->findOneBy(['email' => $email]) !== null;
    }

    private function settingExists(string $key): bool
    {
        return $this->em->find(\App\Settings\Domain\ApplicationSetting::class, $key) !== null;
    }
}
