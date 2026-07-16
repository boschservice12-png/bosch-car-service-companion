<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Console;

use App\Customer\Domain\CustomerProfile;
use App\Identity\Domain\ServiceAdmin;
use App\Identity\Domain\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:user:create',
    description: 'Creează un utilizator (client sau admin) — util pentru seed/demo.',
)]
final class CreateUserCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $hasher,
        #[Autowire('%kernel.environment%')]
        private readonly string $environment,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Email')
            ->addArgument('password', InputArgument::REQUIRED, 'Parolă (min. 8 caractere)')
            ->addOption('admin', null, InputOption::VALUE_NONE, 'Creează SERVICE_ADMIN');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = strtolower(trim((string) $input->getArgument('email')));
        $password = (string) $input->getArgument('password');
        $isAdmin = (bool) $input->getOption('admin');

        // 2FA-ul TOTP pentru admin nu este încă activ (vezi ADR-0002). Până la
        // livrarea gate-ului, crearea de conturi admin în producție este blocată,
        // pentru a nu exista conturi privilegiate protejate doar prin parolă.
        if ($isAdmin && $this->environment === 'prod') {
            $io->error('Crearea de conturi admin în producție este blocată până la activarea 2FA (ADR-0002).');

            return Command::FAILURE;
        }

        if (strlen($password) < 8) {
            $io->error('Parola trebuie să aibă minim 8 caractere.');

            return Command::FAILURE;
        }

        if ($this->em->getRepository(User::class)->findOneBy(['email' => $email]) !== null) {
            $io->error('Există deja un cont cu acest email.');

            return Command::FAILURE;
        }

        $role = $isAdmin ? User::ROLE_SERVICE_ADMIN : User::ROLE_CLIENT;
        $user = new User($email, $role);
        $user->setPasswordHash($this->hasher->hashPassword($user, $password));
        $this->em->persist($user);

        if ($isAdmin) {
            $this->em->persist(new ServiceAdmin($user, $email));
        } else {
            $this->em->persist(new CustomerProfile($user));
        }

        $this->em->flush();

        $io->success(sprintf('Utilizator creat: %s (%s)', $email, $isAdmin ? 'SERVICE_ADMIN' : 'CLIENT'));

        return Command::SUCCESS;
    }
}
