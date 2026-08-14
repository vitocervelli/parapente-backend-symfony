<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Crea o actualiza la cuenta del panel. Si el correo ya existe, solo cambia la
 * contraseña — así sirve también para recuperarla.
 */
#[AsCommand(
    name: 'app:create-admin',
    description: 'Crea (o actualiza la contraseña de) un administrador del panel.',
)]
final class CreateAdminCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserRepository $users,
        private readonly UserPasswordHasherInterface $hasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Correo de la cuenta')
            ->addArgument('password', InputArgument::REQUIRED, 'Contraseña en claro');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = (string) $input->getArgument('email');
        $password = (string) $input->getArgument('password');

        if (strlen($password) < 8) {
            $io->error('La contraseña debe tener al menos 8 caracteres.');

            return self::FAILURE;
        }

        $user = $this->users->findOneBy(['email' => $email]);
        $isNew = null === $user;

        if ($isNew) {
            $user = (new User())->setEmail($email);
            $this->em->persist($user);
        }

        $user->setRoles(['ROLE_ADMIN'])
            ->setPassword($this->hasher->hashPassword($user, $password));

        $this->em->flush();

        $io->success($isNew
            ? sprintf('Administrador "%s" creado.', $email)
            : sprintf('Contraseña de "%s" actualizada.', $email));

        return self::SUCCESS;
    }
}
