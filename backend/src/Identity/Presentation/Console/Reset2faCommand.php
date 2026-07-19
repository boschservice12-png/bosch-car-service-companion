<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Console;

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
 * P0-06 — resetarea 2FA de către operator (admin care și-a pierdut telefonul
 * ȘI codurile de rezervă). Operațiune sensibilă: se înregistrează în audit,
 * iar contul redevine „doar parolă" până la o nouă înrolare.
 */
#[AsCommand(
    name: 'app:2fa:reset',
    description: 'Resetează 2FA (TOTP + coduri de rezervă) pentru un cont de service — audit obligatoriu.',
)]
final class Reset2faCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AuditRecorder $audit,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('email', InputArgument::REQUIRED, 'Emailul contului de service');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = strtolower(trim((string) $input->getArgument('email')));

        $user = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);
        if (!$user instanceof User) {
            $io->error('Nu există un cont cu acest email.');

            return Command::FAILURE;
        }
        if (!$user->isServiceAdmin()) {
            $io->error('2FA se resetează doar pentru conturi de service (admin).');

            return Command::FAILURE;
        }
        if (!$user->totpEnabled() && $user->totpSecret() === null) {
            $io->warning('Contul nu are 2FA configurat — nimic de resetat.');

            return Command::SUCCESS;
        }

        $wasEnabled = $user->totpEnabled();
        $user->disableTotp();
        $this->em->flush();
        $this->audit->record('identity.2fa_reset', 'User', (string) $user->id(), [
            'totpEnabled' => $wasEnabled,
        ], ['totpEnabled' => false, 'resetBy' => 'console']);

        $io->success(sprintf('2FA resetat pentru %s. Contul trebuie să se reînroleze la următorul login.', $email));

        return Command::SUCCESS;
    }
}
