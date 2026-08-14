<?php

declare(strict_types=1);

namespace App\Api;

use App\Entity\AvailabilitySlot;

/**
 * Forma del JSON de disponibilidad. Se agrupa por día porque es como lo pinta
 * el calendario del frontend; devolver una lista plana obligaría a agrupar en
 * el cliente en dos sitios distintos.
 */
final class AvailabilityPresenter
{
    /** @return array<string, mixed> */
    public function slot(AvailabilitySlot $slot, bool $forAdmin = false): array
    {
        $payload = [
            'id' => $slot->getId(),
            'date' => $slot->getDate()->format('Y-m-d'),
            'startTime' => $slot->getStartTime()->format('H:i'),
            'endTime' => $slot->getEndTime()->format('H:i'),
            'label' => sprintf('%s–%s', $slot->getStartTime()->format('H:i'), $slot->getEndTime()->format('H:i')),
            'capacity' => $slot->getCapacity(),
            'seatsFree' => $slot->getSeatsFree(),
            'isOpen' => $slot->isOpen(),
            'note' => $slot->getNote(),
        ];

        if ($forAdmin) {
            // Las plazas ocupadas solo interesan dentro del panel.
            $payload['seatsBooked'] = $slot->getSeatsBooked();
        }

        return $payload;
    }

    /**
     * Agrupa por fecha: [{date, slots: [...]}, ...] en orden cronológico.
     *
     * @param iterable<AvailabilitySlot> $slots
     *
     * @return list<array{date: string, slots: list<array<string, mixed>>}>
     */
    public function byDate(iterable $slots, bool $forAdmin = false): array
    {
        $days = [];

        foreach ($slots as $slot) {
            $key = $slot->getDate()->format('Y-m-d');
            $days[$key] ??= ['date' => $key, 'slots' => []];
            $days[$key]['slots'][] = $this->slot($slot, $forAdmin);
        }

        return array_values($days);
    }
}
