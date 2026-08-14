<?php

declare(strict_types=1);

namespace App\DataSeed;

/**
 * Catálogo de partida, transcrito de los flyers de la marca.
 *
 * Es solo el punto de partida: a partir de aquí todo se edita desde el panel.
 */
final class CatalogData
{
    /**
     * Elementos reutilizables. `icon` es una clave que el frontend mapea a un SVG propio.
     *
     * @return list<array{slug: string, label: string, icon: string}>
     */
    public static function items(): array
    {
        return [
            ['slug' => 'vuelo-instructivo', 'label' => 'Vuelo instructivo', 'icon' => 'paraglider'],
            ['slug' => 'pancarta', 'label' => 'Pancarta personalizada', 'icon' => 'banner'],
            ['slug' => 'paseo-montana', 'label' => 'Paseo por la montaña', 'icon' => 'mountain'],
            ['slug' => 'fotos-video-4k', 'label' => 'Fotografías + video 4K', 'icon' => 'camera'],
            ['slug' => 'paseo-caballo', 'label' => 'Paseo a caballo', 'icon' => 'horse'],
            ['slug' => 'almuerzo', 'label' => 'Almuerzo', 'icon' => 'meal'],
            ['slug' => 'ramo-rosas', 'label' => 'Ramo de rosas', 'icon' => 'roses'],
            ['slug' => 'dedicatoria', 'label' => 'Dedicatoria', 'icon' => 'letter'],
            ['slug' => 'oso-teddy', 'label' => 'Servicio de oso Teddy', 'icon' => 'teddy-service'],
            ['slug' => 'peluche', 'label' => 'Peluche', 'icon' => 'teddy'],
            ['slug' => 'torta', 'label' => 'Torta', 'icon' => 'cake'],
            ['slug' => 'refresco', 'label' => 'Refresco 1L', 'icon' => 'soda'],
            ['slug' => 'caja-galletas', 'label' => 'Caja de galletas', 'icon' => 'cookies'],
            ['slug' => 'arreglo-floral', 'label' => 'Arreglo floral', 'icon' => 'flowers'],
        ];
    }

    /**
     * Servicios. En `inclusions`, `label` es opcional: si falta se usa el texto
     * por defecto del catálogo.
     *
     * @return list<array{
     *     slug: string, name: string, type: string, tagline: ?string, description: ?string,
     *     price: string, currency: string, people: int, priceNote: ?string,
     *     durationMinutes: ?int, badge: ?string, coverImage: ?string, position: int,
     *     inclusions: list<array{item: string, label?: string}>
     * }>
     */
    public static function services(): array
    {
        return [
            [
                'slug' => 'vuela-en-parapente',
                'name' => 'Vuela en parapente',
                'type' => 'standalone',
                'tagline' => 'Tu vuelo tándem sobre el valle con piloto certificado',
                'description' => 'El vuelo de siempre, el que engancha. Subes al despegue, corres unos pasos y ya estás en el aire con un piloto certificado. Fotos y video del vuelo incluidos.',
                'price' => '50.00',
                'currency' => 'USD',
                'people' => 1,
                'priceNote' => 'por persona',
                'durationMinutes' => 15,
                'badge' => 'El clásico',
                'coverImage' => '/uploads/services/vuela-en-parapente.jpeg',
                'position' => 0,
                'inclusions' => [
                    ['item' => 'vuelo-instructivo'],
                    ['item' => 'fotos-video-4k'],
                ],
            ],
            [
                'slug' => 'el-vuelo-de-tu-vida',
                'name' => 'El vuelo de tu vida',
                'type' => 'promotion',
                'tagline' => 'La pedida de mano que no se olvida',
                'description' => 'Dos vuelos, una pancarta con la pregunta más importante y un día entero preparado para que digan que sí.',
                'price' => '180.00',
                'currency' => 'EUR',
                'people' => 2,
                'priceNote' => null,
                'durationMinutes' => null,
                'badge' => null,
                'coverImage' => '/uploads/services/el-vuelo-de-tu-vida.jpeg',
                'position' => 1,
                'inclusions' => [
                    ['item' => 'vuelo-instructivo', 'label' => '2 vuelos instructivos'],
                    ['item' => 'pancarta', 'label' => 'Pancarta de ¿Te quieres casar conmigo?'],
                    ['item' => 'paseo-montana'],
                    ['item' => 'fotos-video-4k'],
                    ['item' => 'paseo-caballo'],
                    ['item' => 'almuerzo'],
                    ['item' => 'ramo-rosas', 'label' => 'Ramo de rosas mediano'],
                    ['item' => 'dedicatoria'],
                    ['item' => 'oso-teddy'],
                    ['item' => 'peluche', 'label' => 'Peluche mediano'],
                ],
            ],
            [
                'slug' => 'amor-en-las-nubes',
                'name' => 'Amor en las nubes',
                'type' => 'promotion',
                'tagline' => 'Pídele salir a 800 metros del suelo',
                'description' => 'Dos vuelos, la pancarta con la pregunta y el resto del día para celebrarlo.',
                'price' => '150.00',
                'currency' => 'EUR',
                'people' => 2,
                'priceNote' => null,
                'durationMinutes' => null,
                'badge' => null,
                'coverImage' => '/uploads/services/amor-en-las-nubes.jpeg',
                'position' => 2,
                'inclusions' => [
                    ['item' => 'vuelo-instructivo', 'label' => '2 vuelos instructivos'],
                    ['item' => 'pancarta', 'label' => 'Pancarta de ¿Quieres ser mi novia?'],
                    ['item' => 'paseo-montana'],
                    ['item' => 'fotos-video-4k'],
                    ['item' => 'paseo-caballo'],
                    ['item' => 'almuerzo'],
                    ['item' => 'ramo-rosas', 'label' => 'Ramo de rosas pequeño'],
                    ['item' => 'peluche', 'label' => 'Peluche pequeño'],
                ],
            ],
            [
                'slug' => 'tu-cumple-compartido',
                'name' => 'Tu cumple compartido',
                'type' => 'promotion',
                'tagline' => 'Cumpleaños en el aire, para dos',
                'description' => 'Dos vuelos, pancarta de cumpleaños y merienda en la base para soplar las velas.',
                'price' => '135.00',
                'currency' => 'EUR',
                'people' => 2,
                'priceNote' => null,
                'durationMinutes' => null,
                'badge' => 'Nuevo',
                'coverImage' => '/uploads/services/tu-cumple-compartido.jpeg',
                'position' => 3,
                'inclusions' => [
                    ['item' => 'vuelo-instructivo', 'label' => '2 vuelos instructivos'],
                    ['item' => 'pancarta', 'label' => 'Pancarta de cumpleaños'],
                    ['item' => 'paseo-montana'],
                    ['item' => 'fotos-video-4k'],
                    ['item' => 'paseo-caballo'],
                    ['item' => 'torta', 'label' => 'Torta de 1/2'],
                    ['item' => 'refresco'],
                    ['item' => 'caja-galletas'],
                    ['item' => 'arreglo-floral', 'label' => 'Arreglo pequeño'],
                ],
            ],
            [
                'slug' => 'tu-vuelta-al-sol',
                'name' => 'Tu vuelta al sol',
                'type' => 'promotion',
                'tagline' => 'Tu cumpleaños, tu vuelo',
                'description' => 'El paquete de cumpleaños para una persona: vuelo, pancarta y torta en la base.',
                'price' => '90.00',
                'currency' => 'EUR',
                'people' => 1,
                'priceNote' => null,
                'durationMinutes' => null,
                'badge' => null,
                'coverImage' => '/uploads/services/tu-vuelta-al-sol.jpeg',
                'position' => 4,
                'inclusions' => [
                    ['item' => 'vuelo-instructivo'],
                    ['item' => 'pancarta', 'label' => 'Pancarta de cumpleaños'],
                    ['item' => 'paseo-montana'],
                    ['item' => 'fotos-video-4k'],
                    ['item' => 'paseo-caballo'],
                    ['item' => 'torta', 'label' => 'Torta de 1/4'],
                    ['item' => 'refresco'],
                ],
            ],

            // ── Servicios de prueba en otras zonas (para ver el flujo) ────────
            [
                'slug' => 'vuelo-costa-la-guaira',
                'name' => 'Vuelo sobre la costa',
                'type' => 'standalone',
                'tagline' => 'Parapente frente al mar Caribe',
                'description' => 'Despegue en el litoral y vuelo con vistas al mar. Fotos y video incluidos.',
                'price' => '60.00',
                'currency' => 'USD',
                'people' => 1,
                'priceNote' => null,
                'durationMinutes' => null,
                'badge' => null,
                'coverImage' => null,
                'position' => 0,
                'locations' => ['la-guaira'],
                'inclusions' => [
                    ['item' => 'vuelo-instructivo'],
                    ['item' => 'fotos-video-4k'],
                ],
            ],
            [
                'slug' => 'vuelo-andino-merida',
                'name' => 'Vuelo andino',
                'type' => 'standalone',
                'tagline' => 'Térmicas de montaña en los Andes',
                'description' => 'Vuelo de altura sobre paisajes andinos con piloto certificado.',
                'price' => '70.00',
                'currency' => 'USD',
                'people' => 1,
                'priceNote' => null,
                'durationMinutes' => null,
                'badge' => null,
                'coverImage' => null,
                'position' => 0,
                'locations' => ['merida'],
                'inclusions' => [
                    ['item' => 'vuelo-instructivo'],
                    ['item' => 'paseo-montana'],
                ],
            ],
        ];
    }

    /**
     * Zonas de vuelo de partida.
     *
     * @return list<array{slug: string, name: string, region: ?string, badge: ?string, description: ?string, position: int}>
     */
    public static function locations(): array
    {
        return [
            ['slug' => 'nirgua', 'name' => 'Nirgua', 'region' => 'Estado Yaracuy', 'badge' => 'Sede principal', 'description' => 'Despegue Bella Vista: parapente, caballos, 4x4 y panadería.', 'position' => 0],
            ['slug' => 'la-guaira', 'name' => 'La Guaira', 'region' => 'Litoral central', 'badge' => null, 'description' => 'Vuela frente al mar Caribe.', 'position' => 1],
            ['slug' => 'merida', 'name' => 'Mérida', 'region' => 'Los Andes', 'badge' => null, 'description' => 'Térmicas de montaña y paisajes andinos.', 'position' => 2],
        ];
    }
}
