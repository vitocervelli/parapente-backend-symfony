<?php

declare(strict_types=1);

namespace App\Booking;

use App\Entity\Booking;
use App\Entity\PaymentProof;
use App\Entity\User;
use App\Enum\BookingStatus;
use App\Enum\PaymentProofStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Todas las transiciones de estado de una reserva pasan por aquí. Los
 * controladores nunca llaman a setStatus() por su cuenta: así hay un único
 * sitio donde mirar qué puede pasar y qué hace cada cosa con las plazas.
 *
 * Regla central: rechazar un COMPROBANTE no es rechazar la RESERVA. La foto
 * borrosa devuelve la reserva a "pendiente de pago" conservando las plazas;
 * solo el rechazo o la cancelación de la reserva las libera.
 */
final class BookingWorkflow
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly SlotLocker $locker,
        #[Autowire('%app.booking_payment_window_hours%')]
        private readonly int $paymentWindowHours,
    ) {
    }

    /** El cliente sube un comprobante. La reserva deja de tener caducidad. */
    public function submitProof(Booking $booking, PaymentProof $proof): void
    {
        if (!$booking->getStatus()->isLive()) {
            throw new BookingException(
                'invalid_transition',
                sprintf('La reserva está "%s" y ya no admite comprobantes.', $booking->getStatus()->label()),
                409,
            );
        }

        $booking->addProof($proof);

        if (BookingStatus::PendingPayment === $booking->getStatus()) {
            $this->transition($booking, BookingStatus::ProofSubmitted);
        }

        // Con el comprobante subido se congela el reloj: la lentitud del equipo
        // revisando nunca debe caducar a un cliente que ya pagó.
        $booking->setExpiresAt(null)->touch();

        $this->em->flush();
    }

    /**
     * El equipo adjunta un comprobante en nombre del cliente (llegó por
     * WhatsApp, en persona…). Si la reserva estaba pendiente, pasa a revisión
     * igual que si lo hubiera subido el cliente; en una reserva ya cerrada solo
     * se guarda para el registro, sin mover el estado ni las plazas.
     */
    public function addProofByAdmin(Booking $booking, PaymentProof $proof): void
    {
        $booking->addProof($proof);

        if (BookingStatus::PendingPayment === $booking->getStatus()) {
            $this->transition($booking, BookingStatus::ProofSubmitted);
        }

        // Con un comprobante encima, una reserva viva deja de caducar.
        if ($booking->getStatus()->isLive()) {
            $booking->setExpiresAt(null);
        }

        $booking->touch();
        $this->em->flush();
    }

    /**
     * El admin da por bueno un comprobante y registra su importe.
     *
     * NO confirma la reserva: un pago puede llegar partido en varias
     * transferencias, así que aceptar la primera foto no significa que esté
     * pagada. La confirmación es una decisión aparte (confirm()), que el panel
     * ofrece en cuanto lo aceptado cubre el total.
     */
    public function acceptProof(
        PaymentProof $proof,
        User $admin,
        ?string $note,
        ?string $amount = null,
        ?string $reference = null,
    ): void {
        $booking = $this->requireBooking($proof);

        if (!$proof->isPending()) {
            throw new BookingException('proof_already_reviewed', 'Ese comprobante ya está revisado.', 409);
        }

        if (null !== $amount) {
            $proof->setDeclaredAmount($amount);
        }
        if (null !== $reference) {
            $proof->setTransferReference($reference);
        }

        $proof->setStatus(PaymentProofStatus::Accepted)
            ->markReviewed($admin, $note, new \DateTimeImmutable());

        // Sin comprobantes pendientes la reserva deja de estar "en revisión",
        // pero se queda esperando a que el equipo confirme o a que el cliente
        // complete lo que falte.
        if (!$booking->hasPendingProof() && BookingStatus::ProofSubmitted === $booking->getStatus()) {
            $this->transition($booking, BookingStatus::PendingPayment);
            $booking->setExpiresAt(null);
        }

        $booking->touch();
        $this->em->flush();
    }

    /**
     * Corrige un comprobante YA revisado sin cambiar su decisión: el equipo
     * tecleó mal el importe o la referencia y lo arregla. Solo mueve datos, no
     * el estado ni las plazas; el pendiente se recalcula solo al derivarse de
     * la suma de lo aceptado.
     */
    public function adjustReviewedProof(
        PaymentProof $proof,
        ?string $amount,
        ?string $reference,
        ?string $note,
    ): void {
        $booking = $this->requireBooking($proof);

        if ($proof->isPending()) {
            throw new BookingException(
                'proof_not_reviewed',
                'Ese comprobante aún está sin revisar: acéptalo o recházalo.',
                409,
            );
        }

        if (null !== $amount) {
            $proof->setDeclaredAmount($amount);
        }
        $proof->setTransferReference($reference);
        $proof->setReviewNote($note);

        $booking->touch();
        $this->em->flush();
    }

    /**
     * El admin rechaza un comprobante (ilegible, importe que no cuadra). La
     * reserva vuelve a "pendiente de pago" CONSERVANDO las plazas, y se le da
     * al cliente otra ventana para subir uno bueno.
     */
    public function rejectProof(PaymentProof $proof, User $admin, ?string $note): void
    {
        $booking = $this->requireBooking($proof);

        if (!$proof->isPending()) {
            throw new BookingException('proof_already_reviewed', 'Ese comprobante ya está revisado.', 409);
        }

        $proof->setStatus(PaymentProofStatus::Rejected)
            ->markReviewed($admin, $note, new \DateTimeImmutable());

        // Solo se devuelve el reloj si no queda nada aceptado: si ya hay pagos
        // parciales buenos, la reserva sigue viva sin plazo.
        if (BookingStatus::ProofSubmitted === $booking->getStatus() && !$booking->hasPendingProof()) {
            $this->transition($booking, BookingStatus::PendingPayment);

            if ('0.00' === $booking->getAcceptedAmount()) {
                $booking->setExpiresAt(new \DateTimeImmutable(sprintf('+%d hours', $this->paymentWindowHours)));
            }
        }

        $booking->touch();
        $this->em->flush();
    }

    /** Confirmación manual: pagó en efectivo o por otra vía. */
    public function confirm(Booking $booking): void
    {
        $this->assertCanTransition($booking, BookingStatus::Confirmed);

        $this->transition($booking, BookingStatus::Confirmed);
        $booking->setConfirmedAt(new \DateTimeImmutable())->setExpiresAt(null)->touch();

        $this->em->flush();
    }

    /** El vuelo ya ocurrió. */
    public function complete(Booking $booking): void
    {
        $this->assertCanTransition($booking, BookingStatus::Completed);

        $this->transition($booking, BookingStatus::Completed);
        $booking->touch();

        $this->em->flush();
    }

    /** No se presentó. Las plazas no se devuelven: la franja ya pasó. */
    public function markNoShow(Booking $booking): void
    {
        $this->assertCanTransition($booking, BookingStatus::NoShow);

        $this->transition($booking, BookingStatus::NoShow);
        $booking->touch();

        $this->em->flush();
    }

    /** El admin descarta la reserva. Libera las plazas. */
    public function reject(Booking $booking, ?string $note): void
    {
        $this->assertCanTransition($booking, BookingStatus::Rejected);

        // Si estaba vencida y el equipo no escribe un motivo, se etiqueta sola:
        // así en la bandeja se distingue de un rechazo normal. Se mira ANTES de
        // transicionar, que al liberar plazas deja de considerarse vencida.
        if (null === $note && $booking->isOverdue(new \DateTimeImmutable(), new \DateTimeImmutable('today'))) {
            $note = 'Venció el plazo para realizar el pago.';
        }

        $this->finishAndReleaseSeats($booking, BookingStatus::Rejected, $note);
    }

    /** Cancelación por parte del equipo. Libera las plazas. */
    public function cancelByAdmin(Booking $booking, ?string $note): void
    {
        $this->assertCanTransition($booking, BookingStatus::CancelledByAdmin);
        $this->finishAndReleaseSeats($booking, BookingStatus::CancelledByAdmin, $note);
    }

    /** Caducidad automática de reservas abandonadas. Libera las plazas. */
    public function expire(Booking $booking): void
    {
        $this->assertCanTransition($booking, BookingStatus::Expired);
        $this->finishAndReleaseSeats($booking, BookingStatus::Expired, null);
    }

    /**
     * Cierra la reserva y devuelve sus plazas, con el mismo protocolo de
     * bloqueo que al crear: transacción + franjas en orden ascendente de id.
     */
    private function finishAndReleaseSeats(Booking $booking, BookingStatus $target, ?string $note): void
    {
        $this->locker->shortenLockWait();

        $this->em->wrapInTransaction(function () use ($booking, $target, $note): void {
            $this->transition($booking, $target);

            if (null !== $note) {
                $booking->setAdminNote($note);
            }
            $booking->touch();

            // seatsReleasedAt es la guardia de idempotencia: cancelar y luego
            // rechazar, o un doble clic, no puede devolver las plazas dos veces.
            if ($booking->areSeatsReleased()) {
                return;
            }

            $seatsBySlot = [];
            foreach ($booking->getLines() as $line) {
                $slotId = $line->getSlot()?->getId();
                if (null !== $slotId) {
                    $seatsBySlot[$slotId] = ($seatsBySlot[$slotId] ?? 0) + $line->getSeatsTotal();
                }
            }

            $slots = $this->locker->lockAll(array_keys($seatsBySlot));
            foreach ($seatsBySlot as $slotId => $seats) {
                $slots[$slotId]->releaseSeats($seats);
            }

            $booking->markSeatsReleased(new \DateTimeImmutable());
        });
    }

    private function transition(Booking $booking, BookingStatus $target): void
    {
        $booking->setStatus($target);
    }

    private function assertCanTransition(Booking $booking, BookingStatus $target): void
    {
        if (!$booking->getStatus()->canTransitionTo($target)) {
            throw new BookingException(
                'invalid_transition',
                sprintf(
                    'Una reserva "%s" no puede pasar a "%s".',
                    $booking->getStatus()->label(),
                    $target->label(),
                ),
                409,
            );
        }
    }

    private function requireBooking(PaymentProof $proof): Booking
    {
        $booking = $proof->getBooking();
        if (null === $booking) {
            throw new BookingException('invalid_proof', 'El comprobante no pertenece a ninguna reserva.', 422);
        }

        return $booking;
    }
}
