<?php

declare(strict_types=1);

namespace App\Deadline\Presentation\Console;

use App\Deadline\Domain\DeadlineNotification;
use App\Deadline\Domain\DeadlineStatusCalculator;
use App\Deadline\Domain\NotificationThresholds;
use App\Deadline\Domain\VehicleDeadlineRepository;
use App\Notification\Application\Message\SendNotification;
use App\Settings\Application\SettingsProvider;
use App\Vehicle\Domain\VehicleRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Scanează scadențele și emite notificări la pragurile configurate
 * (implicit 60/30/7/0 zile) + o notificare unică după expirare. Idempotent:
 * `deadline_notifications` împiedică trimiterea repetată a aceleiași notificări.
 * De rulat periodic (ex. zilnic, prin cron/scheduler).
 */
#[AsCommand(name: 'app:deadlines:scan', description: 'Emite notificări pentru scadențele apropiate/expirate.')]
final class ScanDeadlinesCommand extends Command
{
    private const CHANNEL = 'push';
    private const EXPIRED_THRESHOLD = -1;

    public function __construct(
        private readonly VehicleDeadlineRepository $deadlines,
        private readonly VehicleRepository $vehicles,
        private readonly DeadlineStatusCalculator $calculator,
        private readonly SettingsProvider $settings,
        private readonly MessageBusInterface $bus,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $thresholds = new NotificationThresholds($this->settings->notificationThresholds());
        $now = new \DateTimeImmutable();
        $sent = 0;

        foreach ($this->deadlines->findWithExpiry() as $deadline) {
            $daysLeft = $this->calculator->daysLeft($deadline->expiresAt(), $now);
            if ($daysLeft === null) {
                continue;
            }

            // Prag corespunzător: fie o valoare configurată (ex. 30), fie „expirat".
            $threshold = $daysLeft < 0
                ? self::EXPIRED_THRESHOLD
                : ($thresholds->shouldNotify($daysLeft) ? $daysLeft : null);
            if ($threshold === null) {
                continue;
            }

            if ($this->deadlines->notificationAlreadySent($deadline, $threshold, self::CHANNEL)) {
                continue;
            }

            $owner = $this->vehicles->findActiveOwner($deadline->vehicle());
            if ($owner === null) {
                continue;
            }

            $this->bus->dispatch(new SendNotification(
                (string) $owner->user()->id(),
                'deadline.reminder',
                [
                    'deadlineId' => (string) $deadline->id(),
                    'type' => $deadline->type()->value,
                    'daysLeft' => $daysLeft,
                ],
                self::CHANNEL,
                // Idempotență la nivel de mesaj: un prag pe o scadență = o notificare.
                sprintf('deadline:%s:%d:%s', $deadline->id(), $threshold, self::CHANNEL),
            ));
            $this->deadlines->recordNotification(new DeadlineNotification($deadline, $threshold, self::CHANNEL));
            ++$sent;
        }

        $io->success(sprintf('Notificări emise: %d.', $sent));

        return Command::SUCCESS;
    }
}
