<?php

declare(strict_types=1);

namespace App\Api;

use App\Entity\Booking;
use App\Entity\BookingLine;
use App\Entity\PaymentProof;

final class BookingPresenter
{
    /** @return array<string, mixed> */
    public function booking(Booking $booking, bool $forAdmin = false): array
    {
        $payload = [
            'reference' => $booking->getReference(),
            'status' => $booking->getStatus()->value,
            'statusLabel' => $booking->getStatus()->label(),
            'isLive' => $booking->getStatus()->isLive(),
            'total' => [
                'amount' => $booking->getTotalAmount(),
                'currency' => $booking->getCurrency()->value,
                'display' => $booking->getCurrency()->format($booking->getTotalAmount()),
            ],
            // El pago puede llegar partido: hace falta saber cuánto se lleva
            // cobrado y cuánto queda.
            'paid' => [
                'amount' => $booking->getAcceptedAmount(),
                'display' => $booking->getCurrency()->format($booking->getAcceptedAmount()),
            ],
            'outstanding' => [
                'amount' => $booking->getOutstandingAmount(),
                'display' => $booking->getCurrency()->format($booking->getOutstandingAmount()),
            ],
            'isFullyPaid' => $booking->isFullyPaid(),
            'seats' => $booking->getTotalSeats(),
            'people' => $booking->getTotalPeople(),
            'contactPhone' => $booking->getContactPhone(),
            'customerNote' => $booking->getCustomerNote(),
            'expiresAt' => $booking->getExpiresAt()?->format(\DATE_ATOM),
            'confirmedAt' => $booking->getConfirmedAt()?->format(\DATE_ATOM),
            'createdAt' => $booking->getCreatedAt()->format(\DATE_ATOM),
            'lines' => array_map(fn (BookingLine $l) => $this->line($l), $booking->getLines()->toArray()),
            'proofs' => array_map(fn (PaymentProof $p) => $this->proof($p), $booking->getProofs()->toArray()),
        ];

        if ($forAdmin) {
            $customer = $booking->getCustomer();
            $payload['id'] = $booking->getId();
            $payload['adminNote'] = $booking->getAdminNote();
            // Vencida: aún retiene plazas pese a haber pasado su plazo.
            $payload['isOverdue'] = $booking->isOverdue(new \DateTimeImmutable(), new \DateTimeImmutable('today'));
            $payload['customer'] = null === $customer ? null : [
                'id' => $customer->getId(),
                'email' => $customer->getEmail(),
                'fullName' => $customer->getFullName(),
                'phone' => $customer->getPhone(),
            ];
        }

        return $payload;
    }

    /** @return array<string, mixed> */
    public function line(BookingLine $line): array
    {
        $slot = $line->getSlot();

        return [
            'id' => $line->getId(),
            // El nombre congelado, no el actual del catálogo.
            'serviceName' => $line->getServiceName(),
            'serviceSlug' => $line->getService()?->getSlug(),
            'quantity' => $line->getQuantity(),
            'seatsTotal' => $line->getSeatsTotal(),
            'unitPrice' => [
                'amount' => $line->getUnitPrice(),
                'currency' => $line->getCurrency()->value,
                'display' => $line->getCurrency()->format($line->getUnitPrice()),
            ],
            'lineTotal' => [
                'amount' => $line->getLineTotal(),
                'display' => $line->getCurrency()->format($line->getLineTotal()),
            ],
            'extrasTotal' => [
                'amount' => $line->getExtrasTotal(),
                'display' => $line->getCurrency()->format($line->getExtrasTotal()),
            ],
            'companionCount' => $line->getCompanionCount(),
            'companionFee' => [
                'amount' => $line->getCompanionFeeAmount(),
                'display' => $line->getCurrency()->format($line->getCompanionFeeAmount()),
            ],
            // Total de la línea con servicio + extras + acompañantes.
            'subtotal' => [
                'amount' => $line->getGrandTotal(),
                'display' => $line->getCurrency()->format($line->getGrandTotal()),
            ],
            'slot' => null === $slot ? null : [
                'id' => $slot->getId(),
                'date' => $slot->getDate()->format('Y-m-d'),
                'startTime' => $slot->getStartTime()->format('H:i'),
                'endTime' => $slot->getEndTime()->format('H:i'),
                'label' => sprintf('%s–%s', $slot->getStartTime()->format('H:i'), $slot->getEndTime()->format('H:i')),
            ],
            'attendees' => array_map(fn ($a) => [
                'id' => $a->getId(),
                'fullName' => $a->getFullName(),
                'idNumber' => $a->getIdNumber(),
                'email' => $a->getEmail(),
                'phone' => $a->getPhone(),
                'weightKg' => $a->getWeightKg(),
                'extras' => array_map(fn ($e) => [
                    'name' => $e->getExtraName(),
                    'price' => [
                        'amount' => $e->getPriceAmount(),
                        'display' => $e->getCurrency()->format($e->getPriceAmount()),
                    ],
                ], $a->getExtras()->toArray()),
            ], $line->getAttendees()->toArray()),
        ];
    }

    /** @return array<string, mixed> */
    public function proof(PaymentProof $proof): array
    {
        return [
            'id' => $proof->getId(),
            'status' => $proof->getStatus()->value,
            'statusLabel' => $proof->getStatus()->label(),
            'originalName' => $proof->getOriginalName(),
            'mimeType' => $proof->getMimeType(),
            'sizeBytes' => $proof->getSizeBytes(),
            'declaredAmount' => $proof->getDeclaredAmount(),
            'transferReference' => $proof->getTransferReference(),
            'uploadedAt' => $proof->getUploadedAt()->format(\DATE_ATOM),
            'reviewedAt' => $proof->getReviewedAt()?->format(\DATE_ATOM),
            'reviewNote' => $proof->getReviewNote(),
            // La ruta de almacenamiento NO se expone: el fichero se sirve solo
            // por los endpoints autenticados de descarga.
        ];
    }

    /**
     * @param iterable<Booking> $bookings
     *
     * @return list<array<string, mixed>>
     */
    public function bookings(iterable $bookings, bool $forAdmin = false): array
    {
        $out = [];
        foreach ($bookings as $booking) {
            $out[] = $this->booking($booking, $forAdmin);
        }

        return $out;
    }
}
