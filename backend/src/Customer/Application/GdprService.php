<?php

declare(strict_types=1);

namespace App\Customer\Application;

use App\Audit\Application\AuditRecorder;
use App\Audit\Domain\AuditLog;
use App\Communication\Domain\ConversationRepository;
use App\Deadline\Domain\VehicleDeadlineRepository;
use App\Identity\Domain\User;
use App\Mobility\Domain\MobilityRequestRepository;
use App\Notification\Domain\Notification;
use App\QuoteRequest\Domain\QuoteRequestRepository;
use App\Roadside\Domain\RoadsideRequestRepository;
use App\ServiceHistory\Domain\ServiceRecordRepository;
use App\Tax\Domain\TaxItemRepository;
use App\Vehicle\Domain\VehicleOwnership;
use App\Vehicle\Domain\VehicleRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * P1-06 — drepturile GDPR ale clientului și politica de retenție:
 *  - export: toate datele personale și operaționale ale clientului, ca JSON;
 *  - cererea de ștergere: blochează contul imediat; după perioada de grație
 *    (implicit 30 de zile) purjarea anonimizează ireversibil identitatea;
 *  - purjarea periodică șterge și jurnalul de audit mai vechi de retenție
 *    (implicit 365 de zile) și notificările vechi (implicit 90 de zile).
 *
 * Ce RĂMÂNE după anonimizare: vehiculul, scadențele și istoricul service —
 * evidența operațională a atelierului, nelegată de o persoană identificabilă
 * (legătura de proprietate se închide). Ce se ȘTERGE: conversațiile și
 * mesajele clientului (conțin text liber personal).
 */
final class GdprService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly VehicleRepository $vehicles,
        private readonly VehicleDeadlineRepository $deadlines,
        private readonly ServiceRecordRepository $serviceRecords,
        private readonly ConversationRepository $conversations,
        private readonly QuoteRequestRepository $quoteRequests,
        private readonly RoadsideRequestRepository $roadsideRequests,
        private readonly MobilityRequestRepository $mobilityRequests,
        private readonly TaxItemRepository $taxes,
        private readonly AuditRecorder $audit,
    ) {
    }

    /** @return array<string, mixed> */
    public function export(User $user): array
    {
        $profile = $user->customerProfile();
        $bani = static fn (?int $b): ?float => $b === null ? null : $b / 100;
        $date = static fn (?\DateTimeImmutable $d): ?string => $d?->format('Y-m-d');

        $vehicles = [];
        if ($profile !== null) {
            foreach ($this->vehicles->findActiveForCustomer($profile) as $vehicle) {
                $vehicles[] = [
                    'vin' => (string) $vehicle->vin(),
                    'plateNumber' => $vehicle->plateNumber(),
                    'make' => $vehicle->make(),
                    'model' => $vehicle->model(),
                    'deadlines' => array_map(fn ($d) => [
                        'type' => $d->type()->value,
                        'validFrom' => $date($d->validFrom()),
                        'expiresAt' => $date($d->expiresAt()),
                        'note' => $d->note(),
                    ], $this->deadlines->findForVehicle($vehicle)),
                    'serviceHistory' => array_map(fn ($r) => [
                        'date' => $date($r->serviceDate()),
                        'workType' => $r->workType(),
                        'description' => $r->workDescription(),
                        'odometerKm' => $r->odometerKm(),
                        'total' => $bani($r->totalBani()),
                    ], $this->serviceRecords->findForVehicle($vehicle, false)),
                ];
            }
        }

        return [
            'exportedAt' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            'account' => [
                'email' => $user->getEmail(),
                'name' => $profile?->fullName() ?: null,
                'phone' => $profile?->phone(),
                'address' => $profile?->address(),
            ],
            'vehicles' => $vehicles,
            'taxes' => array_map(fn ($t) => [
                'year' => $t->year(),
                'type' => $t->type()->value,
                'amount' => $bani($t->amountBani()),
                'paidAmount' => $bani($t->paidAmountBani()),
                'dueDate' => $date($t->dueDate()),
            ], $this->taxes->findForCustomer($user)),
            'conversations' => array_map(fn ($c) => [
                'subject' => $c->subject(),
                'createdAt' => $c->createdAt()->format(\DateTimeInterface::ATOM),
                'messages' => array_map(fn ($m) => [
                    'author' => $m->authorRole()->value,
                    'body' => $m->body(),
                    'at' => $m->createdAt()->format(\DateTimeInterface::ATOM),
                ], [...$c->messages()]),
            ], $this->conversations->findForCustomer($user)),
            'quoteRequests' => array_map(fn ($q) => [
                'symptomDescription' => $q->symptomDescription(),
                'status' => $q->status()->value,
                'createdAt' => $q->createdAt()->format(\DateTimeInterface::ATOM),
            ], $this->quoteRequests->findForCustomer($user)),
            'roadsideRequests' => array_map(fn ($r) => [
                'location' => $r->location(),
                'problem' => $r->problem(),
                'status' => $r->status()->value,
                'createdAt' => $r->createdAt()->format(\DateTimeInterface::ATOM),
            ], $this->roadsideRequests->findForCustomer($user)),
            'mobilityRequests' => array_map(fn ($m) => [
                'type' => $m->type()->value,
                'details' => $m->details(),
                'status' => $m->status()->value,
                'createdAt' => $m->createdAt()->format(\DateTimeInterface::ATOM),
            ], $this->mobilityRequests->findForCustomer($user)),
        ];
    }

    public function requestDeletion(User $user): void
    {
        if ($user->isServiceAdmin()) {
            throw new \DomainException('Conturile de service nu se șterg din aplicație.');
        }
        $user->requestDeletion();
        $this->em->flush();
        $this->audit->record('user.deletion_requested', 'User', (string) $user->id());
    }

    /**
     * Purjarea periodică (cron zilnic). Întoarce contorii pe categorii.
     *
     * @return array{purgedUsers: int, deletedAuditLogs: int, deletedNotifications: int}
     */
    public function purge(int $graceDays = 30, int $auditDays = 365, int $notificationDays = 90): array
    {
        $now = new \DateTimeImmutable();
        $deletionCutoff = $now->modify(sprintf('-%d days', $graceDays));

        /** @var list<User> $due */
        $due = $this->em->createQueryBuilder()
            ->select('u')
            ->from(User::class, 'u')
            ->where('u.deletionRequestedAt IS NOT NULL')
            ->andWhere('u.deletionRequestedAt <= :cutoff')
            ->andWhere('u.isActive = false')
            ->andWhere("u.email NOT LIKE '%@anonim.local'")
            ->setParameter('cutoff', $deletionCutoff)
            ->getQuery()
            ->getResult();

        foreach ($due as $user) {
            // Conversațiile și mesajele clientului conțin text liber personal.
            foreach ($this->conversations->findForCustomer($user) as $conversation) {
                foreach ($conversation->messages() as $message) {
                    $this->em->remove($message);
                }
                $this->em->remove($conversation);
            }

            // Legătura de proprietate se închide — vehiculul și istoricul rămân
            // în evidența service-ului, fără persoană identificabilă.
            $profile = $user->customerProfile();
            if ($profile !== null) {
                $ownerships = $this->em->getRepository(VehicleOwnership::class)
                    ->findBy(['customerProfile' => $profile, 'active' => true]);
                foreach ($ownerships as $ownership) {
                    $ownership->release();
                }
                $profile->anonymize();
            }

            $userId = (string) $user->id();
            $user->anonymize();
            $this->em->flush();
            $this->audit->record('user.purged', 'User', $userId);
        }

        $deletedAudit = $this->em->createQuery(
            'DELETE FROM '.AuditLog::class.' a WHERE a.createdAt < :cutoff',
        )->setParameter('cutoff', $now->modify(sprintf('-%d days', $auditDays)))->execute();

        $deletedNotifications = $this->em->createQuery(
            'DELETE FROM '.Notification::class.' n WHERE n.createdAt < :cutoff',
        )->setParameter('cutoff', $now->modify(sprintf('-%d days', $notificationDays)))->execute();

        return [
            'purgedUsers' => \count($due),
            'deletedAuditLogs' => $deletedAudit,
            'deletedNotifications' => $deletedNotifications,
        ];
    }
}
