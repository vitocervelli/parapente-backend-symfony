<?php

declare(strict_types=1);

namespace App\Booking;

use App\Entity\Booking;
use App\Entity\BookingAttendee;
use App\Entity\BookingAttendeeExtra;
use App\Entity\BookingLine;
use App\Entity\BookingSettings;
use App\Entity\Extra;
use App\Entity\Service;
use App\Entity\User;
use App\Enum\BookingStatus;
use App\Repository\BookingRepository;
use App\Repository\BookingSettingsRepository;
use App\Repository\ExtraRepository;
use App\Repository\ServiceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Crea reservas descontando plazas sin sobreventa.
 *
 * El reparto entre lo que va dentro y fuera de la transacción es deliberado:
 * cargar servicios, validar asistentes y calcular importes puede tardar, y
 * hacerlo con la fila de la franja bloqueada convertiría cada reserva en un
 * cuello de botella para todas las demás.
 */
final class BookingCreator
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ServiceRepository $services,
        private readonly BookingRepository $bookings,
        private readonly ExtraRepository $extras,
        private readonly BookingSettingsRepository $settings,
        private readonly SlotLocker $locker,
        private readonly ValidatorInterface $validator,
        #[Autowire('%app.booking_payment_window_hours%')]
        private readonly int $paymentWindowHours,
    ) {
    }

    /**
     * @param array<int, array{serviceId: int, slotId: int, quantity: int, attendees: array<int, array<string, mixed>>}> $draft
     */
    public function create(User $customer, array $draft, ?string $phone, ?string $note): Booking
    {
        if ([] === $draft) {
            throw BookingException::invalid('No has elegido ningún vuelo.');
        }

        // ── Fuera de la transacción: todo lo caro ────────────────────────────
        $booking = (new Booking())
            ->setCustomer($customer)
            ->setStatus(BookingStatus::PendingPayment)
            ->setContactPhone($phone ?? $customer->getPhone())
            ->setCustomerNote($note);

        $settings = $this->settings->get();

        $total = '0.00';
        $currency = null;
        $locationId = null;
        $seatsBySlot = [];

        foreach (array_values($draft) as $index => $row) {
            $line = $this->buildLine($row, $index, $settings);

            // Dos líneas del mismo horario suman aquí. Si no se agregaran, cada
            // una se compararía por separado contra el mismo saldo y entre las
            // dos podrían pasarse del cupo.
            $slotId = (int) $row['slotId'];
            $seatsBySlot[$slotId] = ($seatsBySlot[$slotId] ?? 0) + $line->getSeatsTotal();

            $currency ??= $line->getCurrency();
            if ($currency !== $line->getCurrency()) {
                throw BookingException::invalid(
                    'No se pueden mezclar servicios en euros y en dólares en la misma reserva.',
                );
            }

            // Una reserva es siempre de una sola zona: sus vuelos ocurren en el
            // mismo sitio y comparten calendario.
            $slotLocationId = $line->getSlot()?->getLocation()?->getId();
            $locationId ??= $slotLocationId;
            if ($locationId !== $slotLocationId) {
                throw BookingException::invalid(
                    'No se pueden mezclar servicios de distintas localidades en la misma reserva.',
                );
            }

            // El total congela servicio + extras + tarifa de acompañantes.
            $total = bcadd($total, $line->getGrandTotal(), 2);
            $booking->addLine($line);
        }

        $booking->setTotalAmount($total)
            ->setCurrency($currency ?? \App\Enum\Currency::Eur)
            ->setReference($this->nextReference())
            ->setExpiresAt(new \DateTimeImmutable(sprintf('+%d hours', $this->paymentWindowHours)));

        $violations = $this->validator->validate($booking);
        if (count($violations) > 0) {
            throw BookingException::invalid($violations->get(0)->getMessage());
        }

        // ── Dentro de la transacción: solo el cupo ───────────────────────────
        $this->locker->shortenLockWait();

        $this->em->wrapInTransaction(function () use ($booking, $seatsBySlot): void {
            $slots = $this->locker->lockAll(array_keys($seatsBySlot));

            foreach ($seatsBySlot as $slotId => $seats) {
                $slot = $slots[$slotId];

                if (!$slot->isOpen()) {
                    throw BookingException::slotFull(
                        sprintf('El horario de las %s ya no está disponible.', $slot->getStartTime()->format('H:i')),
                        ['slotId' => $slotId],
                    );
                }

                // La comprobación va DENTRO del bloqueo: comprobar antes y
                // escribir después es exactamente la carrera que esto evita.
                if (!$slot->hasRoomFor($seats)) {
                    throw BookingException::slotFull(
                        sprintf(
                            'El %s a las %s ya solo quedan %d plazas y pides %d.',
                            $slot->getDate()->format('d/m/Y'),
                            $slot->getStartTime()->format('H:i'),
                            $slot->getSeatsFree(),
                            $seats,
                        ),
                        ['slotId' => $slotId, 'seatsFree' => $slot->getSeatsFree(), 'seatsRequested' => $seats],
                    );
                }

                $slot->reserveSeats($seats);
            }

            $this->em->persist($booking);
        });

        return $booking;
    }

    /**
     * @param array{serviceId: int, slotId: int, quantity: int, companionCount?: int, attendees: array<int, array<string, mixed>>} $row
     */
    private function buildLine(array $row, int $index, BookingSettings $settings): BookingLine
    {
        $posicion = $index + 1;

        $service = $this->services->find((int) ($row['serviceId'] ?? 0));
        if (null === $service || !$service->isActive()) {
            throw BookingException::invalid(sprintf('El servicio de la línea %d no está disponible.', $posicion));
        }

        $slot = $this->em->find(\App\Entity\AvailabilitySlot::class, (int) ($row['slotId'] ?? 0));
        if (null === $slot) {
            throw BookingException::invalid(sprintf('El horario de la línea %d no existe.', $posicion));
        }

        if ($slot->getDate() < new \DateTimeImmutable('today')) {
            throw BookingException::invalid('No se puede reservar en una fecha que ya pasó.');
        }

        // El servicio tiene que ofrecerse en la zona de la franja elegida.
        $slotLocation = $slot->getLocation();
        if (null === $slotLocation || !$service->hasLocation($slotLocation)) {
            throw BookingException::invalid(sprintf(
                '"%s" no se ofrece en la localidad de la franja elegida (línea %d).',
                $service->getName(),
                $posicion,
            ));
        }

        $quantity = max(1, (int) ($row['quantity'] ?? 1));
        if ($quantity > 20) {
            throw BookingException::invalid('Como máximo 20 unidades por línea. Para grupos grandes, escríbenos.');
        }

        $line = (new BookingLine())
            ->setService($service)
            ->setSlot($slot)
            ->setQuantity($quantity)
            // Congelado: el catálogo puede cambiar, lo contratado no.
            ->setServiceName($service->getName())
            ->setUnitPrice($service->getPriceAmount())
            ->setCurrency($service->getCurrency())
            ->setSeatsPerUnit($service->getResolvedSeatsPerBooking())
            ->setPeoplePerUnit($service->getPeople());

        $line->setSeatsTotal($quantity * $line->getSeatsPerUnit());

        $this->attachAttendees($line, $row['attendees'] ?? [], $service, $posicion);
        $this->applyCompanions($line, (int) ($row['companionCount'] ?? 0), $service, $settings, $posicion);

        return $line;
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function attachAttendees(BookingLine $line, array $rows, Service $service, int $posicion): void
    {
        $esperados = $line->getExpectedAttendees();

        if (count($rows) !== $esperados) {
            throw BookingException::invalid(sprintf(
                '"%s" necesita los datos de %d %s (línea %d) y llegaron %d.',
                $service->getName(),
                $esperados,
                1 === $esperados ? 'persona' : 'personas',
                $posicion,
                count($rows),
            ));
        }

        // Extras que este servicio ofrece, indexados por id, para validar la elección.
        $extrasPermitidos = [];
        foreach ($service->getExtras() as $serviceExtra) {
            $extra = $serviceExtra->getExtra();
            if (null !== $extra && null !== $extra->getId()) {
                $extrasPermitidos[$extra->getId()] = $extra;
            }
        }

        foreach (array_values($rows) as $i => $row) {
            $attendee = (new BookingAttendee())
                ->setFullName(trim((string) ($row['fullName'] ?? '')))
                ->setIdNumber(trim((string) ($row['idNumber'] ?? '')))
                ->setEmail(strtolower(trim((string) ($row['email'] ?? ''))))
                ->setPhone($this->nullableString($row['phone'] ?? null));

            if (isset($row['weightKg']) && '' !== $row['weightKg']) {
                $attendee->setWeightKg((int) $row['weightKg']);
            }

            $violations = $this->validator->validate($attendee);
            if (count($violations) > 0) {
                throw BookingException::invalid(sprintf(
                    'Asistente %d de "%s": %s',
                    $i + 1,
                    $service->getName(),
                    $violations->get(0)->getMessage(),
                ));
            }

            $this->attachExtras($attendee, $line, $row['extraIds'] ?? [], $extrasPermitidos, $service, $i + 1);

            $line->addAttendee($attendee);
        }
    }

    /**
     * Congela los extras que un asistente eligió. Cada id debe pertenecer al
     * servicio de la línea, y su moneda coincidir con la de la línea.
     *
     * @param array<int, Extra> $permitidos
     * @param mixed              $extraIds
     */
    private function attachExtras(
        BookingAttendee $attendee,
        BookingLine $line,
        mixed $extraIds,
        array $permitidos,
        Service $service,
        int $numAsistente,
    ): void {
        if (!\is_array($extraIds)) {
            return;
        }

        // Sin duplicados: un extra por asistente cuenta una vez.
        $ids = array_values(array_unique(array_map('intval', $extraIds)));

        foreach ($ids as $extraId) {
            $extra = $permitidos[$extraId] ?? null;
            if (null === $extra) {
                throw BookingException::invalid(sprintf(
                    'El extra elegido por el asistente %d de "%s" no está disponible para ese servicio.',
                    $numAsistente,
                    $service->getName(),
                ));
            }

            if ($extra->getCurrency() !== $line->getCurrency()) {
                throw BookingException::invalid(
                    'No se pueden mezclar servicios en euros y en dólares en la misma reserva.',
                );
            }

            $attendee->addExtra(
                (new BookingAttendeeExtra())
                    ->setExtra($extra)
                    ->setExtraName($extra->getName())
                    ->setPriceAmount($extra->getPriceAmount())
                    ->setCurrency($extra->getCurrency()),
            );
        }
    }

    /**
     * Calcula y congela la tarifa de acompañantes de la línea:
     * entre semana, `weekdayFreePerFlyer` gratis por pasajero; fin de semana,
     * ninguno gratis. El resto paga la tarifa configurada.
     */
    private function applyCompanions(
        BookingLine $line,
        int $companionCount,
        Service $service,
        BookingSettings $settings,
        int $posicion,
    ): void {
        $companionCount = max(0, $companionCount);
        if ($companionCount > 50) {
            throw BookingException::invalid(sprintf(
                'Demasiados acompañantes en la línea %d. Para grupos grandes, escríbenos.',
                $posicion,
            ));
        }

        $line->setCompanionCount($companionCount);

        // 'N' de PHP: 6 = sábado, 7 = domingo.
        $esFinDeSemana = \in_array((int) $line->getSlot()->getDate()->format('N'), [6, 7], true);
        $allowance = $esFinDeSemana ? 0 : $settings->getWeekdayFreePerFlyer() * $line->getExpectedAttendees();
        $cobrables = max(0, $companionCount - $allowance);

        if ($cobrables > 0 && $settings->getCompanionFeeCurrency() !== $line->getCurrency()) {
            throw BookingException::invalid(
                'La tarifa de acompañantes está en otra moneda distinta a la del vuelo. Avísanos para ajustarlo.',
            );
        }

        $line->setCompanionFeeAmount(bcmul($settings->getCompanionFeeAmount(), (string) $cobrables, 2));
    }

    /**
     * Referencia legible y correlativa por año. El índice único de la columna
     * es la red de seguridad si dos altas simultáneas calculan el mismo número.
     */
    private function nextReference(): string
    {
        $year = (int) (new \DateTimeImmutable())->format('Y');

        return sprintf('PBV-%d-%04d', $year, $this->bookings->nextSequenceForYear($year));
    }

    private function nullableString(mixed $value): ?string
    {
        if (null === $value) {
            return null;
        }

        $value = trim((string) $value);

        return '' === $value ? null : $value;
    }
}
