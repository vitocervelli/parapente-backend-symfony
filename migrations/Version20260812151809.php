<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260812151809 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Añade service.seats_per_booking: plazas del cupo que consume cada unidad.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE service ADD seats_per_booking SMALLINT UNSIGNED DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE service DROP seats_per_booking');
    }
}
