<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Reels de la portada gestionables desde el panel («Vívelo en movimiento»).
 * Sustituye el array en duro de lib/site.ts. Se siembra el vídeo de muestra que
 * había, ya copiado a public/uploads/reels, para que el administrador lo
 * reemplace por los suyos.
 */
final class Version20260827000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Tabla reel (vídeos de la portada) + siembra del vídeo de muestra.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE reel (id INT AUTO_INCREMENT NOT NULL, video_path VARCHAR(255) NOT NULL, poster_path VARCHAR(255) DEFAULT NULL, caption VARCHAR(120) DEFAULT NULL, position SMALLINT DEFAULT 0 NOT NULL, is_active TINYINT(1) DEFAULT 1 NOT NULL, INDEX idx_reel_active_pos (is_active, position), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');

        // El vídeo de muestra que estaba en lib/site.ts, ya copiado al backend.
        for ($i = 0; $i < 3; ++$i) {
            $this->addSql(
                "INSERT INTO reel (video_path, poster_path, caption, position, is_active) VALUES ('/uploads/reels/muestra-reel.mp4', NULL, 'Vídeo de muestra', ?, 1)",
                [$i],
            );
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE reel');
    }
}
