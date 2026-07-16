<?php

declare(strict_types=1);

namespace App\Settings\Presentation\Console;

use App\Communication\Domain\Conversation;
use App\Communication\Domain\ConversationType;
use App\Communication\Domain\Message;
use App\Communication\Domain\MessageAuthorRole;
use App\Customer\Domain\Consent;
use App\Customer\Domain\CustomerProfile;
use App\Deadline\Domain\DeadlineSource;
use App\Deadline\Domain\DeadlineType;
use App\Deadline\Domain\VehicleDeadline;
use App\Identity\Domain\ServiceAdmin;
use App\Identity\Domain\User;
use App\ServiceHistory\Domain\ServiceRecord;
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

        // Scadențe cu stări variate (VALID / DUE_SOON / EXPIRED) pentru demo.
        $this->seedDeadlines($v1, $v2, $admin);
        // Istoric de service: o înregistrare publicată + o corecție.
        $this->seedServiceHistory($v1, $admin);
        // O cerere de ofertă cu răspuns (stare QUOTED).
        $this->seedQuoteConversation($client, $v1, $admin);

        $this->em->flush();

        $io->success('Date demo create.');
        $io->listing([
            sprintf('Admin: %s / %s', self::ADMIN_EMAIL, self::DEMO_PASSWORD),
            sprintf('Client: %s / %s', self::CLIENT_EMAIL, self::DEMO_PASSWORD),
            '2 vehicule (BMW Seria 3, VW Golf) cu scadențe (valid / expiră curând / expirat)',
            'Istoric service: 1 înregistrare publicată + 1 corecție',
            'Cerere de ofertă cu ofertă trimisă (stare QUOTED)',
        ]);

        return Command::SUCCESS;
    }

    private function seedDeadlines(Vehicle $v1, Vehicle $v2, User $admin): void
    {
        // v1: ITP valid (validat de service), RCA expiră curând, rovinietă expirată.
        $itp = new VehicleDeadline($v1, DeadlineType::ITP, new \DateTimeImmutable('+200 days'), DeadlineSource::SERVICE, new \DateTimeImmutable('-165 days'));
        $itp->markVerified($admin);
        $rca = new VehicleDeadline($v1, DeadlineType::RCA, new \DateTimeImmutable('+18 days'), DeadlineSource::CLIENT);
        $tax = new VehicleDeadline($v1, DeadlineType::ROAD_TAX, new \DateTimeImmutable('-9 days'), DeadlineSource::CLIENT);
        // v2: ITP care expiră curând.
        $itp2 = new VehicleDeadline($v2, DeadlineType::ITP, new \DateTimeImmutable('+25 days'), DeadlineSource::CLIENT);

        foreach ([$itp, $rca, $tax, $itp2] as $deadline) {
            $this->em->persist($deadline);
        }
    }

    private function seedServiceHistory(Vehicle $v1, User $admin): void
    {
        $original = new ServiceRecord($v1, $admin);
        $original->applyDetails(
            new \DateTimeImmutable('-60 days'),
            82000,
            'Revizie periodică',
            'Schimb ulei și filtre, verificare sistem de frânare, diagnoză computerizată.',
            'Ulei 5W30 (5L), filtru ulei, filtru aer, filtru polen.',
            35000,
            120000,
            '12 luni / 20.000 km',
        );
        $original->publish();
        $this->em->persist($original);

        // Corecție: totalul corect (piesă adăugată), ca intrare separată.
        $correction = new ServiceRecord($v1, $admin, $original);
        $correction->applyDetails(
            new \DateTimeImmutable('-60 days'),
            82000,
            'Revizie periodică (corecție)',
            'Corecție: s-a adăugat înlocuirea plăcuțelor de frână față.',
            'Ulei 5W30 (5L), filtre, set plăcuțe frână față.',
            42000,
            139000,
            '12 luni / 20.000 km',
        );
        $correction->publish();
        $this->em->persist($correction);
    }

    private function seedQuoteConversation(User $client, Vehicle $v1, User $admin): void
    {
        $conversation = new Conversation($client, ConversationType::QUOTE, 'Zgomot la frânare', $v1);
        $conversation->addMessage(new Message(
            $conversation,
            $client,
            MessageAuthorRole::CLIENT,
            'Bună ziua, se aude un scârțâit la frânare în față. Puteți estima costul verificării și reparației?',
        ));
        $conversation->setQuote(125000);
        $conversation->addMessage(new Message(
            $conversation,
            $admin,
            MessageAuthorRole::ADMIN,
            'Estimare: verificare + înlocuire plăcuțe față. Ofertă: 1.250,00 RON.',
        ));

        $this->em->persist($conversation);
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
