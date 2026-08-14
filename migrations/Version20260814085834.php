<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260814085834 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Extras de pago (catálogo + asignación + congelado por asistente), tarifa de acompañantes por línea y ajustes globales de reserva.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE booking_attendee_extra (id INT AUTO_INCREMENT NOT NULL, extra_name VARCHAR(160) NOT NULL, price_amount NUMERIC(10, 2) NOT NULL, currency VARCHAR(3) NOT NULL, attendee_id INT NOT NULL, extra_id INT DEFAULT NULL, INDEX IDX_DE6513272B959FC6 (extra_id), INDEX idx_attendee_extra_attendee (attendee_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE booking_settings (id INT AUTO_INCREMENT NOT NULL, companion_fee_amount NUMERIC(10, 2) NOT NULL, companion_fee_currency VARCHAR(3) NOT NULL, weekday_free_per_flyer SMALLINT UNSIGNED DEFAULT 1 NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE extra (id INT AUTO_INCREMENT NOT NULL, slug VARCHAR(80) NOT NULL, name VARCHAR(160) NOT NULL, price_amount NUMERIC(10, 2) NOT NULL, currency VARCHAR(3) NOT NULL, icon VARCHAR(60) NOT NULL, note VARCHAR(255) DEFAULT NULL, position SMALLINT DEFAULT 0 NOT NULL, is_active TINYINT DEFAULT 1 NOT NULL, UNIQUE INDEX UNIQ_4D3F0D65989D9B62 (slug), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE service_extra (id INT AUTO_INCREMENT NOT NULL, position SMALLINT DEFAULT 0 NOT NULL, service_id INT NOT NULL, extra_id INT NOT NULL, INDEX IDX_E44DE082ED5CA9E6 (service_id), INDEX IDX_E44DE0822B959FC6 (extra_id), INDEX idx_service_extra_service_pos (service_id, position), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE booking_attendee_extra ADD CONSTRAINT FK_DE651327BCFD782A FOREIGN KEY (attendee_id) REFERENCES booking_attendee (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE booking_attendee_extra ADD CONSTRAINT FK_DE6513272B959FC6 FOREIGN KEY (extra_id) REFERENCES extra (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE service_extra ADD CONSTRAINT FK_E44DE082ED5CA9E6 FOREIGN KEY (service_id) REFERENCES service (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE service_extra ADD CONSTRAINT FK_E44DE0822B959FC6 FOREIGN KEY (extra_id) REFERENCES extra (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE booking_line ADD companion_count SMALLINT UNSIGNED DEFAULT 0 NOT NULL, ADD companion_fee_amount NUMERIC(10, 2) DEFAULT \'0.00\' NOT NULL');

        // Fila única de ajustes con la política de acompañantes por defecto:
        // 5€ por acompañante de pago, 1 gratis por pasajero entre semana.
        $this->addSql("INSERT INTO booking_settings (companion_fee_amount, companion_fee_currency, weekday_free_per_flyer) VALUES ('5.00', 'EUR', 1)");
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE booking_attendee_extra DROP FOREIGN KEY FK_DE651327BCFD782A');
        $this->addSql('ALTER TABLE booking_attendee_extra DROP FOREIGN KEY FK_DE6513272B959FC6');
        $this->addSql('ALTER TABLE service_extra DROP FOREIGN KEY FK_E44DE082ED5CA9E6');
        $this->addSql('ALTER TABLE service_extra DROP FOREIGN KEY FK_E44DE0822B959FC6');
        $this->addSql('DROP TABLE booking_attendee_extra');
        $this->addSql('DROP TABLE booking_settings');
        $this->addSql('DROP TABLE extra');
        $this->addSql('DROP TABLE service_extra');
        $this->addSql('ALTER TABLE booking_line DROP companion_count, DROP companion_fee_amount');
    }
}
