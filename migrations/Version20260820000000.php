<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Elimina la columna flyer_image de service: el "flyer de la promoción" no se
 * usaba en ninguna parte de la web ni del panel, así que se retira del stack.
 */
final class Version20260820000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Elimina service.flyer_image (campo sin uso en la web ni en el panel).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE service DROP flyer_image');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE service ADD flyer_image VARCHAR(255) DEFAULT NULL');
    }
}
