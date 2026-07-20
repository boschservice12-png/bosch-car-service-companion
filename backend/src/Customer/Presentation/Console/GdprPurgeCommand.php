<?php

declare(strict_types=1);

namespace App\Customer\Presentation\Console;

use App\Customer\Application\GdprService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * P1-06 — purjarea periodică (cron zilnic, vezi monitoring.md):
 * anonimizează conturile a căror perioadă de grație a expirat și aplică
 * retenția pe jurnalul de audit și pe notificări.
 */
#[AsCommand(name: 'app:gdpr:purge', description: 'Aplică politica de retenție GDPR (anonimizare + curățare jurnale).')]
final class GdprPurgeCommand extends Command
{
    public function __construct(private readonly GdprService $gdpr)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('grace-days', null, InputOption::VALUE_REQUIRED, 'Grația după cererea de ștergere', '30')
            ->addOption('audit-days', null, InputOption::VALUE_REQUIRED, 'Retenția jurnalului de audit', '365')
            ->addOption('notification-days', null, InputOption::VALUE_REQUIRED, 'Retenția notificărilor', '90');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $result = $this->gdpr->purge(
            max(0, (int) $input->getOption('grace-days')),
            max(1, (int) $input->getOption('audit-days')),
            max(1, (int) $input->getOption('notification-days')),
        );

        $io->success(sprintf(
            'Conturi anonimizate: %d · intrări de audit șterse: %d · notificări șterse: %d.',
            $result['purgedUsers'],
            $result['deletedAuditLogs'],
            $result['deletedNotifications'],
        ));

        return Command::SUCCESS;
    }
}
