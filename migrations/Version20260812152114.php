<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260812152114 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Crea availability_slot: franjas de vuelo con su cupo por día y hora.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE availability_slot (id INT AUTO_INCREMENT NOT NULL, slot_date DATE NOT NULL, start_time TIME NOT NULL, end_time TIME NOT NULL, capacity SMALLINT UNSIGNED NOT NULL, seats_booked SMALLINT UNSIGNED DEFAULT 0 NOT NULL, is_open TINYINT DEFAULT 1 NOT NULL, note VARCHAR(160) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, INDEX idx_slot_open_date (is_open, slot_date), UNIQUE INDEX uniq_slot_date_start (slot_date, start_time), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE availability_slot');
    }
}
