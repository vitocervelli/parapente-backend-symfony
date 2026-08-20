<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Galería pública (/galeria) gestionable desde el panel. Se siembran las nueve
 * fotos que estaban en duro en la web: las dos polaroids destacadas y la tira.
 * Los ficheros se copian a public/uploads/gallery en el despliegue.
 */
final class Version20260825000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Tabla gallery_photo + siembra de las fotos existentes de /galeria.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE gallery_photo (id INT AUTO_INCREMENT NOT NULL, image_path VARCHAR(255) NOT NULL, alt VARCHAR(200) NOT NULL, is_featured TINYINT(1) DEFAULT 0 NOT NULL, is_wide TINYINT(1) DEFAULT 0 NOT NULL, position SMALLINT DEFAULT 0 NOT NULL, is_active TINYINT(1) DEFAULT 1 NOT NULL, INDEX idx_gallery_active_pos (is_active, position), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');

        $fotos = [
            // [ruta, alt, destacada, ancha]
            ['/uploads/gallery/IMG_0021.JPG.jpeg', 'Vuelo tándem sobre el valle', 1, 0],
            ['/uploads/gallery/IMG_1192.JPG-7c08766d.jpeg', 'Pura felicidad en el aire', 1, 0],
            ['/uploads/gallery/IMG_1332.JPG.jpeg', 'Volando sobre las montañas', 0, 0],
            ['/uploads/gallery/IMG_5605.JPG-4b128596.jpeg', 'Preparando el despegue', 0, 1],
            ['/uploads/gallery/IMG_3266.JPG.jpeg', 'Ala multicolor sobre el valle', 0, 0],
            ['/uploads/gallery/IMG_9789.JPG.jpeg', 'Sonrisas en el aire', 0, 0],
            ['/uploads/gallery/IMG_4752.JPG.jpeg', 'Despegue con ala azul', 0, 0],
            ['/uploads/gallery/IMG_4353.JPG-a66a10fa.jpeg', 'Tándem sobre el bosque', 0, 0],
            ['/uploads/gallery/IMG_0019.JPG-3a06563c.jpeg', 'Sobre el embalse', 0, 0],
        ];

        foreach ($fotos as $posicion => [$ruta, $alt, $destacada, $ancha]) {
            $this->addSql(
                'INSERT INTO gallery_photo (image_path, alt, is_featured, is_wide, position, is_active) VALUES (?, ?, ?, ?, ?, 1)',
                [$ruta, $alt, $destacada, $ancha, $posicion],
            );
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE gallery_photo');
    }
}
