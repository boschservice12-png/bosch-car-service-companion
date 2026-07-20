<?php

declare(strict_types=1);

namespace App\Customer\Presentation\Console;

use App\Audit\Application\AuditRecorder;
use App\Identity\Domain\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * P1-06 — clientul s-a răzgândit în perioada de grație: operatorul anulează
 * cererea de ștergere, contul redevine activ (auditat). După anonimizare nu
 * mai există cale de întoarcere.
 */
#[AsCommand(name: 'app:gdpr:cancel-deletion', description: 'Anulează o cerere de ștergere aflată în perioada de grație.')]
final class GdprCancelDeletionCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AuditRecorder $audit,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('email', InputArgument::REQUIRED, 'Emailul contului');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = strtolower(trim((string) $input->getArgument('email')));

        $user = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);
        if (!$user instanceof User || $user->deletionRequestedAt() === null) {
            $io->error('Nu există o cerere de ștergere în așteptare pentru acest email.');

            return Command::FAILURE;
        }
        if ($user->isAnonymized()) {
            $io->error('Contul a fost deja anonimizat — ireversibil.');

            return Command::FAILURE;
        }

        $user->cancelDeletion();
        $this->em->flush();
        $this->audit->record('user.deletion_cancelled', 'User', (string) $user->id());
        $io->success(sprintf('Cererea de ștergere pentru %s a fost anulată; contul este din nou activ.', $email));

        return Command::SUCCESS;
    }
}
