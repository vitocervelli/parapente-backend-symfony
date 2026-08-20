<?php

declare(strict_types=1);

namespace App\Booking;

use App\Entity\Booking;
use App\Entity\BookingAttendee;
use App\Entity\BookingLine;
use App\Entity\User;
use App\Enum\BookingStatus;
use App\Enum\Currency;
use App\Repository\BookingRepository;
use App\Repository\ServiceRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Alta de una reserva HISTÓRICA: una reserva anterior al sistema que el
 * administrador transcribe para que las cifras (personas voladas, ingresos)
 * tengan continuidad.
 *
 * A diferencia de {@see BookingCreator}, no toca el cupo: no reserva plazas, no
 * bloquea franjas ni fija plazo de pago. La reserva nace directamente en un
 * estado final (completada o no-show) y su única línea:
 *  - se ancla al servicio oculto «Histórico» (para cumplir la FK obligatoria),
 *  - congela el nombre y precio reales que teclea el administrador,
 *  - no tiene franja: la fecha del vuelo se guarda en flightDate,
 *  - representa «N personas por importe T» como quantity=1, peoplePerUnit=N,
 *    unitPrice=T, para que countPeopleFlown sume N y el total cuadre en T.
 */
final class HistoricalBookingCreator
{
    /** Slug del servicio oculto al que se anclan todas las líneas históricas. */
    public const HISTORICAL_SERVICE_SLUG = 'historico';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ServiceRepository $services,
        private readonly BookingRepository $bookings,
    ) {
    }

    /**
     * @param string   $amount         importe ya normalizado a "1234.56"
     * @param string[] $passengerNames nombres opcionales de los pasajeros
     */
    public function create(
        User $customer,
        string $serviceName,
        \DateTimeImmutable $flightDate,
        int $peopleCount,
        string $amount,
        Currency $currency,
        BookingStatus $status,
        ?string $note,
        array $passengerNames = [],
    ): Booking {
        if (!\in_array($status, [BookingStatus::Completed, BookingStatus::NoShow], true)) {
            throw BookingException::invalid('Una reserva histórica solo puede ser «completada» o «no-show».');
        }

        $historical = $this->services->findOneBy(['slug' => self::HISTORICAL_SERVICE_SLUG]);
        if (null === $historical) {
            // Lo crea la migración; si falta, es un despliegue a medias.
            throw BookingException::invalid('Falta el servicio interno «Histórico». Ejecuta las migraciones.');
        }

        $line = (new BookingLine())
            ->setService($historical)
            ->setSlot(null)
            ->setFlightDate($flightDate)
            ->setServiceName($serviceName)
            ->setUnitPrice($amount)
            ->setCurrency($currency)
            ->setQuantity(1)
            ->setPeoplePerUnit($peopleCount)
            // No consume plazas reales: no hay franja a la que descontárselas.
            ->setSeatsPerUnit(0)
            ->setSeatsTotal(0);

        foreach ($passengerNames as $name) {
            $name = trim($name);
            if ('' === $name) {
                continue;
            }

            // Datos mínimos: de reservas viejas rara vez hay cédula o correo.
            // Las columnas son NOT NULL, pero cadena vacía es válida en BD.
            $line->addAttendee((new BookingAttendee())
                ->setFullName(substr($name, 0, 160))
                ->setIdNumber('')
                ->setEmail(''));
        }

        $year = (int) $flightDate->format('Y');

        $booking = (new Booking())
            ->setCustomer($customer)
            ->setStatus($status)
            ->setIsHistorical(true)
            ->setCurrency($currency)
            ->setTotalAmount($amount)
            ->setAdminNote($note)
            ->setReference(sprintf('PBV-%d-%04d', $year, $this->bookings->nextSequenceForYear($year)));
        $booking->addLine($line);

        $this->em->persist($booking);
        $this->em->flush();

        return $booking;
    }
}
