<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Convierte los iconos por defecto (claves como "horse", "cake") en archivos
 * editables: cada elemento incluido y cada extra sin imagen propia recibe el
 * SVG correspondiente de public/uploads/icons. Así el administrador puede
 * cambiarlos como cualquier otra imagen.
 */
final class Version20260826000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Asigna los SVG de icono por defecto a inclusion_item y extra sin icono propio.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE inclusion_item SET icon_path = CONCAT('/uploads/icons/icono-', icon, '.svg') WHERE icon_path IS NULL OR icon_path = ''");
        $this->addSql("UPDATE extra SET icon_path = CONCAT('/uploads/icons/icono-', icon, '.svg') WHERE icon_path IS NULL OR icon_path = ''");
    }

    public function down(Schema $schema): void
    {
        // Best-effort: solo revierte los que apuntan a los SVG por defecto,
        // dejando intactos los iconos que el administrador haya subido a mano.
        $this->addSql("UPDATE inclusion_item SET icon_path = NULL WHERE icon_path LIKE '/uploads/icons/icono-%.svg'");
        $this->addSql("UPDATE extra SET icon_path = NULL WHERE icon_path LIKE '/uploads/icons/icono-%.svg'");
    }
}
