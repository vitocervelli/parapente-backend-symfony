<?php

declare(strict_types=1);

namespace App\Mail;

use App\Entity\Booking;
use App\Entity\User;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

/**
 * Único punto de envío de los correos transaccionales del ciclo de reserva.
 *
 * Cada método arma un TemplatedEmail y lo manda de forma síncrona. Regla de oro:
 * un fallo de SMTP se REGISTRA pero nunca se propaga — un correo caído jamás debe
 * romper un alta, una reserva ni una transición de estado.
 *
 * El administrador (%env(ADMIN_EMAIL)%) va en copia OCULTA (Bcc) de cada correo
 * al cliente; además, en una reserva nueva recibe un aviso propio accionable.
 */
final class BookingMailer
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger,
        #[Autowire('%env(ADMIN_EMAIL)%')]
        private readonly string $adminEmail,
        #[Autowire('%env(MAIL_FROM)%')]
        private readonly string $fromEmail,
        #[Autowire('%env(FRONTEND_URL)%')]
        private readonly string $frontendUrl,
        // Logo blanco de la cabecera; se incrusta por CID en cada correo.
        #[Autowire('%kernel.project_dir%/assets/emails/logo-white.png')]
        private readonly string $logoPath,
    ) {
    }

    /** Alta de cliente: correo de bienvenida (con copia oculta al admin). */
    public function welcome(User $user): void
    {
        $email = $this->base('emails/welcome.html.twig', [
            'user' => $user,
            'url' => $this->accountUrl(),
        ])
            ->to(new Address($user->getEmail(), $user->getDisplayName()))
            ->subject('Te damos la bienvenida a Parapente Bella Vista')
            ->bcc($this->adminEmail);

        $this->send($email, 'welcome', $user->getEmail());
    }

    /**
     * Nueva reserva: dos correos.
     *  - Cliente: pendiente de pago + subir el comprobante (Bcc al admin).
     *  - Admin: aviso independiente y accionable con enlace al panel.
     */
    public function bookingCreated(Booking $booking): void
    {
        $customer = $booking->getCustomer();
        if (null === $customer) {
            return;
        }

        $toClient = $this->base('emails/booking_pending.html.twig', [
            'booking' => $booking,
            'url' => $this->bookingUrl($booking),
        ])
            ->to(new Address($customer->getEmail(), $customer->getDisplayName()))
            ->subject(sprintf('Tu reserva %s está pendiente de pago', $booking->getReference()))
            ->bcc($this->adminEmail);

        $this->send($toClient, 'booking_pending', $customer->getEmail());

        $toAdmin = $this->base('emails/booking_new_admin.html.twig', [
            'booking' => $booking,
            'url' => $this->adminBookingUrl($booking),
        ])
            ->to($this->adminEmail)
            ->subject(sprintf('Nueva reserva %s pendiente de revisar', $booking->getReference()));

        $this->send($toAdmin, 'booking_new_admin', $this->adminEmail);
    }

    /** El admin confirma la reserva: aviso al cliente (Bcc al admin). */
    public function bookingConfirmed(Booking $booking): void
    {
        $customer = $booking->getCustomer();
        if (null === $customer) {
            return;
        }

        $email = $this->base('emails/booking_confirmed.html.twig', [
            'booking' => $booking,
            'url' => $this->bookingUrl($booking),
        ])
            ->to(new Address($customer->getEmail(), $customer->getDisplayName()))
            ->subject(sprintf('Reserva %s confirmada', $booking->getReference()))
            ->bcc($this->adminEmail);

        $this->send($email, 'booking_confirmed', $customer->getEmail());
    }

    /** El admin marca la reserva completada: fotos disponibles en el perfil. */
    public function bookingCompleted(Booking $booking): void
    {
        $customer = $booking->getCustomer();
        if (null === $customer) {
            return;
        }

        $email = $this->base('emails/booking_completed.html.twig', [
            'booking' => $booking,
            'url' => $this->bookingUrl($booking),
        ])
            ->to(new Address($customer->getEmail(), $customer->getDisplayName()))
            ->subject(sprintf('Ya tienes las fotos de tu vuelo (%s)', $booking->getReference()))
            ->bcc($this->adminEmail);

        $this->send($email, 'booking_completed', $customer->getEmail());
    }

    /**
     * Recuperación de contraseña. SIN copia al admin: un enlace de reset en otro
     * buzón es un riesgo de seguridad.
     */
    public function passwordReset(User $user, string $rawToken): void
    {
        $url = rtrim($this->frontendUrl, '/') . '/restablecer?token=' . rawurlencode($rawToken);

        $email = $this->base('emails/password_reset.html.twig', [
            'user' => $user,
            'url' => $url,
        ])
            ->to(new Address($user->getEmail(), $user->getDisplayName()))
            ->subject('Recupera tu contraseña · Parapente Bella Vista');

        $this->send($email, 'password_reset', $user->getEmail());
    }

    /**
     * Contraseña temporal para una cuenta recién creada desde el panel. SIN copia
     * al admin: no se filtra la credencial del cliente a otro buzón.
     */
    public function temporaryPassword(User $user, string $plainPassword): void
    {
        $email = $this->base('emails/temporary_password.html.twig', [
            'user' => $user,
            'password' => $plainPassword,
            'url' => $this->accountUrl() . '/perfil',
        ])
            ->to(new Address($user->getEmail(), $user->getDisplayName()))
            ->subject('Tu acceso a Parapente Bella Vista');

        $this->send($email, 'temporary_password', $user->getEmail());
    }

    private function base(string $template, array $context): TemplatedEmail
    {
        $email = (new TemplatedEmail())
            ->from(new Address($this->fromEmail, 'Parapente Bella Vista'))
            ->htmlTemplate($template)
            ->context($context);

        // El logo de la cabecera va incrustado (CID): los clientes de correo
        // bloquean las imágenes remotas, pero muestran las adjuntas en línea.
        // En la plantilla se referencia como `cid:logotipo`.
        $email->embedFromPath($this->logoPath, 'logotipo', 'image/png');

        return $email;
    }

    private function accountUrl(): string
    {
        return rtrim($this->frontendUrl, '/') . '/cuenta';
    }

    private function bookingUrl(Booking $booking): string
    {
        return rtrim($this->frontendUrl, '/') . '/cuenta/reservas/' . rawurlencode($booking->getReference());
    }

    private function adminBookingUrl(Booking $booking): string
    {
        return rtrim($this->frontendUrl, '/') . '/admin/reservas/' . $booking->getId();
    }

    private function send(TemplatedEmail $email, string $kind, string $to): void
    {
        try {
            $this->mailer->send($email);
        } catch (\Throwable $e) {
            // Un correo caído nunca debe romper la acción del usuario.
            $this->logger->error('No se pudo enviar el correo "{kind}" a {to}: {error}', [
                'kind' => $kind,
                'to' => $to,
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);
        }
    }
}
