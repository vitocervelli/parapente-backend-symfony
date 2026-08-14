<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260812142925 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Añade service.show_on_home: qué servicios aparecen en el mosaico de la home.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE service ADD show_on_home TINYINT DEFAULT 1 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE service DROP show_on_home');
    }
}
