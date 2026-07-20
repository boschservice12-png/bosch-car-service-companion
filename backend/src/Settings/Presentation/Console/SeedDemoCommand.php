<?php

declare(strict_types=1);

namespace App\Settings\Presentation\Console;

use App\Communication\Domain\Conversation;
use App\QuoteRequest\Domain\QuoteRequest;
use App\QuoteRequest\Domain\QuoteRequestStatus;
use App\QuoteRequest\Domain\QuoteResponse;
use App\Communication\Domain\Message;
use App\Communication\Domain\MessageAuthorRole;
use App\Customer\Domain\Consent;
use App\Customer\Domain\CustomerProfile;
use App\DamageClaim\Domain\DamageClaim;
use App\DamageClaim\Domain\DamageClaimStatus;
use App\Deadline\Domain\DeadlineSource;
use App\Deadline\Domain\DeadlineType;
use App\Deadline\Domain\VehicleDeadline;
use App\Identity\Domain\ServiceAdmin;
use App\Identity\Domain\User;
use App\Mobility\Domain\MobilityRequest;
use App\Mobility\Domain\MobilityStatus;
use App\Mobility\Domain\MobilityType;
use App\Roadside\Domain\MobilityState;
use App\Roadside\Domain\RoadsideRequest;
use App\Roadside\Domain\RoadsideStatus;
use App\Roadside\Domain\SafetyState;
use App\ServiceHistory\Domain\ServiceRecord;
use App\Tax\Domain\PaymentStatus;
use App\Tax\Domain\TaxItem;
use App\Tax\Domain\TaxType;
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
        $profile->updateContact('0722 000 111', null);
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
        // O conversație generală + o cerere de ofertă cu răspuns (REPLIED).
        $this->seedConversation($client, $admin);
        $this->seedQuoteRequest($client, $v1, $admin);
        // Sprint 4: asistență rutieră, mobilitate, dosar de daună, taxe.
        $this->seedRoadside($client, $v1);
        $this->seedMobility($client, $v1);
        $this->seedDamageClaim($client, $v1);
        $this->seedTaxes($client, $v1);

        $this->em->flush();

        $io->success('Date demo create.');
        $io->listing([
            sprintf('Admin: %s / %s', self::ADMIN_EMAIL, self::DEMO_PASSWORD),
            sprintf('Client: %s / %s', self::CLIENT_EMAIL, self::DEMO_PASSWORD),
            '2 vehicule (BMW Seria 3, VW Golf) cu scadențe (valid / expiră curând / expirat)',
            'Istoric service: 1 înregistrare publicată + 1 corecție',
            'Cerere de ofertă cu răspuns (stare REPLIED) + conversație deschisă',
            'Asistență rutieră (preluată), mobilitate (aprobată), dosar de daună (în lucru)',
            'Taxe: impozit auto plătit + taxă de mediu neplătită',
        ]);

        return Command::SUCCESS;
    }

    private function seedDeadlines(Vehicle $v1, Vehicle $v2, User $admin): void
    {
        // v1: ITP valid (validat de service), RCA expiră curând, taxă de drum expirată.
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
        $correction = new ServiceRecord($v1, $admin, $original, 'Kilometraj corectat — cifră inversată la introducere.');
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
        $original->markCorrected();
        $this->em->persist($correction);
    }

    private function seedConversation(User $client, User $admin): void
    {
        $conversation = new Conversation($client, 'Programare revizie');
        $conversation->addMessage(new Message(
            $conversation,
            $client,
            MessageAuthorRole::CLIENT,
            'Bună ziua, aș dori o programare pentru revizia anuală săptămâna viitoare.',
        ));
        $conversation->addMessage(new Message(
            $conversation,
            $admin,
            MessageAuthorRole::ADMIN,
            'Bună ziua! Vă putem primi marți la 09:00. Vă convine?',
        ));
        $conversation->markWaitingClient();

        $this->em->persist($conversation);
    }

    private function seedQuoteRequest(User $client, Vehicle $v1, User $admin): void
    {
        $request = new QuoteRequest(
            $client,
            $v1,
            78400,
            'Se aude un scârțâit la frânare în față, mai ales la frânări puternice.',
            'La frânări de la viteză mare; dimineața e mai zgomotos.',
            true,
            'Niciun martor aprins',
            'PHONE',
            'Luni-vineri după 16:00',
        );
        $request->changeStatus(QuoteRequestStatus::IN_REVIEW);
        $request->changeStatus(QuoteRequestStatus::REPLIED);
        $request->addResponse(new QuoteResponse(
            $request,
            $admin,
            'Estimare: verificare sistem de frânare + înlocuire plăcuțe față. Ofertă: 1.250,00 RON (piese + manoperă).',
        ));

        $this->em->persist($request);
    }

    private function seedRoadside(User $client, Vehicle $v1): void
    {
        $request = new RoadsideRequest(
            $client,
            $v1,
            'DN13, km 12, lângă Sighișoara',
            'Pană de cauciuc, roata dreapta față.',
            MobilityState::NOT_DRIVABLE,
            SafetyState::AT_RISK,
            '+40711223344',
        );
        $request->changeStatus(RoadsideStatus::VALIDATED, null);
        $request->changeStatus(RoadsideStatus::FORWARDED, 'Preluat, echipa de tractare a fost anunțată.');
        $this->em->persist($request);
    }

    private function seedMobility(User $client, Vehicle $v1): void
    {
        $request = new MobilityRequest(
            $client,
            $v1,
            MobilityType::REPLACEMENT_CAR,
            'Am nevoie de o mașină de înlocuire pe durata reparației (2-3 zile).',
            new \DateTimeImmutable('+2 days'),
        );
        $request->changeStatus(MobilityStatus::IN_REVIEW, null);
        $request->changeStatus(MobilityStatus::CONFIRMED, 'Rezervat Dacia Logan alb.');
        $this->em->persist($request);
    }

    private function seedDamageClaim(User $client, Vehicle $v1): void
    {
        $claim = new DamageClaim(
            $client,
            $v1,
            new \DateTimeImmutable('-5 days'),
            'Parcare Kaufland, Târgu Mureș',
            'Coliziune ușoară în parcare, aripă dreapta spate zgâriată.',
            'Allianz-Țiriac',
            'POL-2026-123456',
        );
        $claim->changeStatus(DamageClaimStatus::IN_REVIEW, 'Dosar transmis către asigurător, așteptăm constatarea.');
        $this->em->persist($claim);
    }

    private function seedTaxes(User $client, Vehicle $v1): void
    {
        $year = (int) (new \DateTimeImmutable())->format('Y');

        $paid = new TaxItem($client, $v1, $year, TaxType::VEHICLE_TAX, 48000, new \DateTimeImmutable($year.'-03-31'));
        $paid->markPaid();
        $this->em->persist($paid);

        $unpaid = new TaxItem($client, $v1, $year, TaxType::ENVIRONMENT, 15000, new \DateTimeImmutable('+45 days'));
        $this->em->persist($unpaid);
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
