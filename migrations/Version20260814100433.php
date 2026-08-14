<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260814100433 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Localidades (zonas de vuelo): tabla location, relación M:N service_location y disponibilidad por zona. Backfill: todo lo existente pasa a Nirgua.';
    }

    public function up(Schema $schema): void
    {
        // ── Tablas nuevas ────────────────────────────────────────────────────
        $this->addSql('CREATE TABLE location (id INT AUTO_INCREMENT NOT NULL, slug VARCHAR(80) NOT NULL, name VARCHAR(120) NOT NULL, region VARCHAR(120) DEFAULT NULL, badge VARCHAR(60) DEFAULT NULL, description LONGTEXT DEFAULT NULL, position SMALLINT DEFAULT 0 NOT NULL, is_active TINYINT DEFAULT 1 NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, UNIQUE INDEX UNIQ_5E9E89CB989D9B62 (slug), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE service_location (service_id INT NOT NULL, location_id INT NOT NULL, INDEX IDX_A7E8D2F6ED5CA9E6 (service_id), INDEX IDX_A7E8D2F664D218E (location_id), PRIMARY KEY (service_id, location_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE service_location ADD CONSTRAINT FK_A7E8D2F6ED5CA9E6 FOREIGN KEY (service_id) REFERENCES service (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE service_location ADD CONSTRAINT FK_A7E8D2F664D218E FOREIGN KEY (location_id) REFERENCES location (id) ON DELETE CASCADE');

        // ── Zonas iniciales (Nirgua como sede principal) ─────────────────────
        $this->addSql("INSERT INTO location (slug, name, region, badge, description, position, is_active, created_at) VALUES ('nirgua', 'Nirgua', 'Estado Yaracuy', 'Sede principal', 'Despegue Bella Vista: parapente, caballos, 4x4 y panadería.', 0, 1, NOW())");
        $this->addSql("INSERT INTO location (slug, name, region, badge, description, position, is_active, created_at) VALUES ('la-guaira', 'La Guaira', 'Litoral central', NULL, 'Vuela frente al mar Caribe.', 1, 1, NOW())");
        $this->addSql("INSERT INTO location (slug, name, region, badge, description, position, is_active, created_at) VALUES ('merida', 'Mérida', 'Los Andes', NULL, 'Térmicas de montaña y paisajes andinos.', 2, 1, NOW())");

        // ── Backfill: todos los servicios existentes quedan en Nirgua ────────
        $this->addSql("INSERT INTO service_location (service_id, location_id) SELECT s.id, (SELECT id FROM location WHERE slug = 'nirgua') FROM service s");

        // ── Disponibilidad por zona: añadir columna nullable, backfill, NOT NULL ─
        $this->addSql('DROP INDEX uniq_slot_date_start ON availability_slot');
        $this->addSql('DROP INDEX idx_slot_open_date ON availability_slot');
        $this->addSql('ALTER TABLE availability_slot ADD location_id INT DEFAULT NULL');
        $this->addSql("UPDATE availability_slot SET location_id = (SELECT id FROM location WHERE slug = 'nirgua')");
        $this->addSql('ALTER TABLE availability_slot MODIFY location_id INT NOT NULL');
        $this->addSql('ALTER TABLE availability_slot ADD CONSTRAINT FK_1C11DC9E64D218E FOREIGN KEY (location_id) REFERENCES location (id) ON DELETE RESTRICT');
        $this->addSql('CREATE INDEX IDX_1C11DC9E64D218E ON availability_slot (location_id)');
        $this->addSql('CREATE INDEX idx_slot_loc_open_date ON availability_slot (location_id, is_open, slot_date)');
        $this->addSql('CREATE UNIQUE INDEX uniq_slot_loc_date_start ON availability_slot (location_id, slot_date, start_time)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE service_location DROP FOREIGN KEY FK_A7E8D2F6ED5CA9E6');
        $this->addSql('ALTER TABLE service_location DROP FOREIGN KEY FK_A7E8D2F664D218E');
        $this->addSql('DROP TABLE location');
        $this->addSql('DROP TABLE service_location');
        $this->addSql('ALTER TABLE availability_slot DROP FOREIGN KEY FK_1C11DC9E64D218E');
        $this->addSql('DROP INDEX IDX_1C11DC9E64D218E ON availability_slot');
        $this->addSql('DROP INDEX idx_slot_loc_open_date ON availability_slot');
        $this->addSql('DROP INDEX uniq_slot_loc_date_start ON availability_slot');
        $this->addSql('ALTER TABLE availability_slot DROP location_id');
        $this->addSql('CREATE UNIQUE INDEX uniq_slot_date_start ON availability_slot (slot_date, start_time)');
        $this->addSql('CREATE INDEX idx_slot_open_date ON availability_slot (is_open, slot_date)');
    }
}
