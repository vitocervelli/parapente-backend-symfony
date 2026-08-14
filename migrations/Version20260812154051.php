<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260812154051 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Crea booking, booking_line y booking_attendee: reservas con sus líneas y asistentes.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE booking (id INT AUTO_INCREMENT NOT NULL, reference VARCHAR(24) NOT NULL, status VARCHAR(30) NOT NULL, contact_phone VARCHAR(40) DEFAULT NULL, customer_note LONGTEXT DEFAULT NULL, admin_note LONGTEXT DEFAULT NULL, total_amount NUMERIC(10, 2) NOT NULL, currency VARCHAR(3) NOT NULL, expires_at DATETIME DEFAULT NULL, confirmed_at DATETIME DEFAULT NULL, seats_released_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, customer_id INT NOT NULL, INDEX IDX_E00CEDDE9395C3F3 (customer_id), INDEX idx_booking_status_created (status, created_at), INDEX idx_booking_customer (customer_id, created_at), UNIQUE INDEX uniq_booking_reference (reference), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE booking_attendee (id INT AUTO_INCREMENT NOT NULL, full_name VARCHAR(160) NOT NULL, id_number VARCHAR(40) NOT NULL, email VARCHAR(180) NOT NULL, phone VARCHAR(40) DEFAULT NULL, birth_date DATE DEFAULT NULL, weight_kg SMALLINT UNSIGNED DEFAULT NULL, notes LONGTEXT DEFAULT NULL, line_id INT NOT NULL, INDEX idx_attendee_line (line_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE booking_line (id INT AUTO_INCREMENT NOT NULL, quantity SMALLINT UNSIGNED DEFAULT 1 NOT NULL, seats_total SMALLINT UNSIGNED NOT NULL, service_name VARCHAR(160) NOT NULL, unit_price NUMERIC(10, 2) NOT NULL, currency VARCHAR(3) NOT NULL, seats_per_unit SMALLINT UNSIGNED DEFAULT 1 NOT NULL, people_per_unit SMALLINT UNSIGNED DEFAULT 1 NOT NULL, booking_id INT NOT NULL, service_id INT NOT NULL, slot_id INT NOT NULL, INDEX IDX_C98596B83301C60 (booking_id), INDEX IDX_C98596B8ED5CA9E6 (service_id), INDEX idx_line_slot (slot_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE booking ADD CONSTRAINT FK_E00CEDDE9395C3F3 FOREIGN KEY (customer_id) REFERENCES app_user (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE booking_attendee ADD CONSTRAINT FK_2A644BEA4D7B7542 FOREIGN KEY (line_id) REFERENCES booking_line (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE booking_line ADD CONSTRAINT FK_C98596B83301C60 FOREIGN KEY (booking_id) REFERENCES booking (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE booking_line ADD CONSTRAINT FK_C98596B8ED5CA9E6 FOREIGN KEY (service_id) REFERENCES service (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE booking_line ADD CONSTRAINT FK_C98596B859E5119C FOREIGN KEY (slot_id) REFERENCES availability_slot (id) ON DELETE RESTRICT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE booking DROP FOREIGN KEY FK_E00CEDDE9395C3F3');
        $this->addSql('ALTER TABLE booking_attendee DROP FOREIGN KEY FK_2A644BEA4D7B7542');
        $this->addSql('ALTER TABLE booking_line DROP FOREIGN KEY FK_C98596B83301C60');
        $this->addSql('ALTER TABLE booking_line DROP FOREIGN KEY FK_C98596B8ED5CA9E6');
        $this->addSql('ALTER TABLE booking_line DROP FOREIGN KEY FK_C98596B859E5119C');
        $this->addSql('DROP TABLE booking');
        $this->addSql('DROP TABLE booking_attendee');
        $this->addSql('DROP TABLE booking_line');
    }
}
