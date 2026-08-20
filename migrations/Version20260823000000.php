<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Icono propio para los extras de pago: el administrador puede subir una imagen
 * (a public/uploads/icons) en lugar de limitarse a las claves de icono fijas.
 * Espejo de inclusion_item.icon_path.
 */
final class Version20260823000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'extra.icon_path: icono subido por el administrador para los extras de pago.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE extra ADD icon_path VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE extra DROP icon_path');
    }
}
