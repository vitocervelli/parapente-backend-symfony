<?php

declare(strict_types=1);

namespace App\Api;

use App\Entity\Extra;
use App\Entity\InclusionItem;
use App\Entity\Service;

/**
 * Convierte entidades en la forma exacta que consumen la web y el panel.
 *
 * Se hace aquí y no con grupos de serialización porque el payload lleva valores
 * derivados (precio formateado, etiqueta resuelta) y porque así la referencia
 * circular servicio ↔ inclusión no puede aparecer.
 *
 * Las rutas de imagen van relativas: el host lo pone el frontend, de modo que la
 * base de datos no queda atada a un dominio.
 */
final class ServicePresenter
{
    /** @return array<string, mixed> */
    public function service(Service $service, bool $withInclusions = true): array
    {
        $payload = [
            'id' => $service->getId(),
            'slug' => $service->getSlug(),
            'name' => $service->getName(),
            'type' => $service->getType()->value,
            'tagline' => $service->getTagline(),
            'description' => $service->getDescription(),
            'price' => [
                // string, no number: DECIMAL no debe pasar por float en ningún punto.
                'amount' => $service->getPriceAmount(),
                'currency' => $service->getCurrency()->value,
                'display' => $service->getCurrency()->format($service->getPriceAmount()),
            ],
            'people' => $service->getPeople(),
            // Resuelto para quien reserva; crudo para el formulario del panel,
            // que si no fijaría el derivado al guardar.
            'seatsPerBooking' => $service->getResolvedSeatsPerBooking(),
            'seatsPerBookingRaw' => $service->getSeatsPerBooking(),
            'priceNote' => $service->getResolvedPriceNote(),
            // El valor tal cual está guardado (puede ser null): lo necesita el
            // formulario del panel para no fijar la nota derivada al guardar.
            'priceNoteRaw' => $service->getPriceNote(),
            'durationMinutes' => $service->getDurationMinutes(),
            'badge' => $service->getBadge(),
            'image' => $service->getCoverImage(),
            'flyer' => $service->getFlyerImage(),
            'position' => $service->getPosition(),
            'isActive' => $service->isActive(),
            'showOnHome' => $service->isShownOnHome(),
            'inclusionsCount' => $service->getInclusions()->count(),
            // Zonas donde se ofrece (para agrupar en la web y filtrar en la reserva).
            'locations' => array_map(
                fn ($location) => [
                    'id' => $location->getId(),
                    'slug' => $location->getSlug(),
                    'name' => $location->getName(),
                ],
                $service->getLocations()->toArray(),
            ),
            // Ids sueltos para el formulario del panel (multi-selección).
            'locationIds' => array_map(
                fn ($location) => $location->getId(),
                $service->getLocations()->toArray(),
            ),
            // Extras de pago que ofrece el servicio (para el paso "Quién vuela").
            'extras' => array_map(
                function ($serviceExtra) {
                    $extra = $serviceExtra->getExtra();

                    return [
                        // El id de asignación y el del extra del catálogo.
                        'id' => $extra?->getId(),
                        'linkId' => $serviceExtra->getId(),
                        'slug' => $extra?->getSlug(),
                        'name' => $extra?->getName() ?? '',
                        'price' => [
                            'amount' => $extra?->getPriceAmount() ?? '0.00',
                            'currency' => ($extra?->getCurrency() ?? $service->getCurrency())->value,
                            'display' => ($extra?->getCurrency() ?? $service->getCurrency())->format($extra?->getPriceAmount() ?? '0.00'),
                        ],
                        'icon' => $extra?->getIcon() ?? 'check',
                        'note' => $extra?->getNote(),
                        'position' => $serviceExtra->getPosition(),
                    ];
                },
                $service->getExtras()->toArray(),
            ),
        ];

        if ($withInclusions) {
            $payload['inclusions'] = array_map(
                fn ($inclusion) => [
                    'id' => $inclusion->getId(),
                    'itemId' => $inclusion->getItem()?->getId(),
                    'itemSlug' => $inclusion->getItem()?->getSlug(),
                    'label' => $inclusion->getLabel(),
                    'labelOverride' => $inclusion->getLabelOverride(),
                    'icon' => $inclusion->getIcon(),
                    'iconPath' => $inclusion->getIconPath(),
                    'note' => $inclusion->getNote(),
                    'position' => $inclusion->getPosition(),
                ],
                $service->getInclusions()->toArray(),
            );
        }

        return $payload;
    }

    /** @return array<string, mixed> */
    public function extra(Extra $extra): array
    {
        return [
            'id' => $extra->getId(),
            'slug' => $extra->getSlug(),
            'name' => $extra->getName(),
            'price' => [
                'amount' => $extra->getPriceAmount(),
                'currency' => $extra->getCurrency()->value,
                'display' => $extra->getCurrency()->format($extra->getPriceAmount()),
            ],
            'currency' => $extra->getCurrency()->value,
            'icon' => $extra->getIcon(),
            'note' => $extra->getNote(),
            'position' => $extra->getPosition(),
            'isActive' => $extra->isActive(),
        ];
    }

    /**
     * @param iterable<Extra> $extras
     *
     * @return list<array<string, mixed>>
     */
    public function extras(iterable $extras): array
    {
        $out = [];
        foreach ($extras as $extra) {
            $out[] = $this->extra($extra);
        }

        return $out;
    }

    /**
     * @param iterable<Service> $services
     *
     * @return list<array<string, mixed>>
     */
    public function services(iterable $services, bool $withInclusions = true): array
    {
        $out = [];
        foreach ($services as $service) {
            $out[] = $this->service($service, $withInclusions);
        }

        return $out;
    }

    /** @return array<string, mixed> */
    public function item(InclusionItem $item): array
    {
        return [
            'id' => $item->getId(),
            'slug' => $item->getSlug(),
            'defaultLabel' => $item->getDefaultLabel(),
            'icon' => $item->getIcon(),
            'iconPath' => $item->getIconPath(),
            'position' => $item->getPosition(),
        ];
    }

    /**
     * @param iterable<InclusionItem> $items
     *
     * @return list<array<string, mixed>>
     */
    public function items(iterable $items): array
    {
        $out = [];
        foreach ($items as $item) {
            $out[] = $this->item($item);
        }

        return $out;
    }
}
