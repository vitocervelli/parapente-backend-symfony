<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Aliados de la portada («Vuelan con nosotros») gestionables desde el panel.
 * Se siembran los dos que estaban en duro en la web; el hueco «Tu marca aquí»
 * desaparece.
 */
final class Version20260824000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Tabla ally (aliados de la portada) + siembra de los aliados existentes.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE ally (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(160) NOT NULL, kind VARCHAR(120) DEFAULT NULL, logo_path VARCHAR(255) DEFAULT NULL, position SMALLINT DEFAULT 0 NOT NULL, is_active TINYINT(1) DEFAULT 1 NOT NULL, INDEX idx_ally_active_pos (is_active, position), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');

        // Los dos aliados que estaban en duro en app/page.tsx. El logo de
        // MasonJard se copia a public/uploads/allies en el despliegue.
        $this->addSql("INSERT INTO ally (name, kind, logo_path, position, is_active) VALUES ('La Panamericana', 'Panadería', NULL, 0, 1)");
        $this->addSql("INSERT INTO ally (name, kind, logo_path, position, is_active) VALUES ('Tú MasonJard', 'Tienda de regalos', '/uploads/allies/aliado-masonjard.jpg', 1, 1)");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE ally');
    }
}
