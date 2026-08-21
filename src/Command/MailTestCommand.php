<?php

declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Envía un correo de prueba para validar que el SMTP (MAILER_DSN) funciona,
 * sin tener que disparar el flujo completo de reservas.
 *
 * Uso: php bin/console app:mail:test destino@correo.com
 */
#[AsCommand(name: 'app:mail:test', description: 'Envía un correo de prueba para validar el SMTP.')]
final class MailTestCommand extends Command
{
    public function __construct(
        private readonly MailerInterface $mailer,
        #[Autowire('%env(MAIL_FROM)%')]
        private readonly string $fromEmail,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('to', InputArgument::REQUIRED, 'Dirección de correo de destino');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $to = (string) $input->getArgument('to');

        $email = (new Email())
            ->from(new Address($this->fromEmail, 'Parapente Bella Vista'))
            ->to($to)
            ->subject('Prueba de correo · Parapente Bella Vista')
            ->text('Si recibes este correo, el envío SMTP funciona correctamente.')
            ->html('<p>Si recibes este correo, el envío SMTP funciona correctamente.</p>');

        try {
            $this->mailer->send($email);
        } catch (\Throwable $e) {
            $io->error('Falló el envío: ' . $e->getMessage());

            return Command::FAILURE;
        }

        $io->success(sprintf('Correo de prueba enviado a %s.', $to));

        return Command::SUCCESS;
    }
}
