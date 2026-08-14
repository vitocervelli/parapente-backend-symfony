<?php

declare(strict_types=1);

namespace App\Booking;

use App\Entity\AvailabilitySlot;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Bloquea franjas para modificar su contador de plazas sin sobreventas.
 *
 * Dos reglas que hacen que esto funcione y que no son negociables:
 *
 * 1. Se bloquea SIEMPRE en orden ascendente de id. Si una reserva bloquea la
 *    franja 41 y luego la 57, y otra simultánea lo hace al revés, se quedan
 *    esperándose para siempre. El orden determinista elimina el interbloqueo.
 *
 * 2. Solo se llama dentro de una transacción ya abierta. Un bloqueo pesimista
 *    fuera de transacción no significa nada.
 */
final class SlotLocker
{
    /**
     * MySQL 5.7 no tiene NOWAIT ni SKIP LOCKED, así que un cliente atascado
     * dejaría a otro esperando los 50 s del valor por defecto. Cinco segundos
     * es de sobra para este trabajo y evita colgar un worker de PHP.
     */
    private const LOCK_TIMEOUT_SECONDS = 5;

    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    /** Acorta la espera por bloqueo en la conexión actual. */
    public function shortenLockWait(): void
    {
        try {
            $this->em->getConnection()->executeStatement(
                'SET SESSION innodb_lock_wait_timeout = ' . self::LOCK_TIMEOUT_SECONDS,
            );
        } catch (\Throwable) {
            // En un motor que no lo soporte simplemente se usa su valor por
            // defecto: no es motivo para tumbar la reserva.
        }
    }

    /**
     * Bloquea las franjas indicadas y las devuelve indexadas por id.
     *
     * @param int[] $slotIds
     *
     * @return array<int, AvailabilitySlot>
     */
    public function lockAll(array $slotIds): array
    {
        $ids = array_values(array_unique(array_map('intval', $slotIds)));
        sort($ids, SORT_NUMERIC);

        $locked = [];

        foreach ($ids as $id) {
            // find() de uno en uno y no findBy(): Doctrine no garantiza el orden
            // de adquisición de un IN, que es justo lo que aquí importa.
            $slot = $this->em->find(AvailabilitySlot::class, $id, LockMode::PESSIMISTIC_WRITE);

            if (null === $slot) {
                throw BookingException::invalid(sprintf('El horario nº %d ya no existe.', $id));
            }

            $locked[$id] = $slot;
        }

        return $locked;
    }
}
