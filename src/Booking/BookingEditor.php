<?php

declare(strict_types=1);

namespace App\Booking;

use App\Entity\Booking;
use App\Entity\BookingAttendee;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Edición de los datos de una reserva por parte del panel.
 *
 * A diferencia de crearla, aquí NO se tocan ni las plazas ni el estado: solo
 * los campos que el equipo corrige a mano (teléfono, notas y los asistentes).
 * Por eso funciona en cualquier estado, incluso con la reserva ya confirmada o
 * el pago ajustado — que es justo lo que se pide.
 */
final class BookingEditor
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ValidatorInterface $validator,
    ) {
    }

    /**
     * Aplica solo las claves presentes en el payload: lo que no llega, no se
     * toca. Así el panel puede mandar un cambio suelto sin arrastrar el resto.
     *
     * @param array<string, mixed> $payload
     */
    public function update(Booking $booking, array $payload): void
    {
        if (\array_key_exists('contactPhone', $payload)) {
            $booking->setContactPhone($this->nullableString($payload['contactPhone'], 40));
        }
        if (\array_key_exists('customerNote', $payload)) {
            $booking->setCustomerNote($this->nullableString($payload['customerNote']));
        }
        if (\array_key_exists('adminNote', $payload)) {
            $booking->setAdminNote($this->nullableString($payload['adminNote']));
        }

        if (\array_key_exists('attendees', $payload)) {
            $rows = \is_array($payload['attendees']) ? $payload['attendees'] : [];
            $this->updateAttendees($booking, $rows);
        }

        $violations = $this->validator->validate($booking);
        if (count($violations) > 0) {
            throw BookingException::invalid($violations->get(0)->getMessage());
        }

        $booking->touch();
        $this->em->flush();
    }

    /**
     * Cada fila se casa con un asistente por id: no se crean ni se borran, solo
     * se corrigen los que ya existen. Un id ajeno a la reserva es un error, no
     * un alta silenciosa.
     *
     * @param array<int, mixed> $rows
     */
    private function updateAttendees(Booking $booking, array $rows): void
    {
        /** @var array<int, BookingAttendee> $porId */
        $porId = [];
        foreach ($booking->getAllAttendees() as $attendee) {
            $porId[(int) $attendee->getId()] = $attendee;
        }

        foreach ($rows as $row) {
            if (!\is_array($row) || !isset($row['id'])) {
                throw BookingException::invalid('Falta el identificador de un asistente.');
            }

            $id = (int) $row['id'];
            $attendee = $porId[$id] ?? null;
            if (null === $attendee) {
                throw BookingException::invalid('Un asistente no pertenece a esta reserva.');
            }

            if (\array_key_exists('fullName', $row)) {
                $attendee->setFullName(trim((string) $row['fullName']));
            }
            if (\array_key_exists('idNumber', $row)) {
                $attendee->setIdNumber(trim((string) $row['idNumber']));
            }
            if (\array_key_exists('email', $row)) {
                $attendee->setEmail(strtolower(trim((string) $row['email'])));
            }
            if (\array_key_exists('phone', $row)) {
                $attendee->setPhone($this->nullableString($row['phone'], 40));
            }
            if (\array_key_exists('weightKg', $row)) {
                $peso = trim((string) $row['weightKg']);
                $attendee->setWeightKg('' === $peso ? null : (int) $peso);
            }

            $violations = $this->validator->validate($attendee);
            if (count($violations) > 0) {
                throw BookingException::invalid(sprintf(
                    '%s: %s',
                    '' !== $attendee->getFullName() ? $attendee->getFullName() : 'Asistente',
                    $violations->get(0)->getMessage(),
                ));
            }
        }
    }

    private function nullableString(mixed $value, ?int $maxLength = null): ?string
    {
        $text = trim((string) ($value ?? ''));
        if ('' === $text) {
            return null;
        }

        return null === $maxLength ? $text : mb_substr($text, 0, $maxLength);
    }
}
