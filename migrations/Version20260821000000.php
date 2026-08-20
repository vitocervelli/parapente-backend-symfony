<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Reservas históricas: permite dar de alta reservas anteriores al sistema.
 *
 * - booking_line.slot_id pasa a nullable (las históricas no tienen franja) y se
 *   añade flight_date para guardar la fecha del vuelo cuando no hay franja.
 * - booking.is_historical marca estas altas para filtrarlas y excluirlas del
 *   área de cliente.
 * - Se crea el servicio oculto «Histórico» (inactivo, sin localidades) al que se
 *   anclan las líneas históricas para cumplir la FK obligatoria service_id.
 */
final class Version20260821000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Reservas históricas: slot_id nullable + flight_date, booking.is_historical y servicio oculto «Histórico».';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE booking_line CHANGE slot_id slot_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE booking_line ADD flight_date DATE DEFAULT NULL');
        $this->addSql('ALTER TABLE booking ADD is_historical TINYINT(1) DEFAULT 0 NOT NULL');

        // Servicio oculto para anclar las líneas históricas. INSERT IGNORE lo hace
        // idempotente (el slug tiene índice único), para que el despliegue no
        // dependa del seed. is_active=0 y show_on_home=0, y sin filas en
        // service_location, garantizan que nunca aparece en la web.
        $this->addSql(<<<'SQL'
            INSERT IGNORE INTO service (name, slug, type, price_amount, currency, people, position, is_active, show_on_home, created_at)
            VALUES ('Histórico', 'historico', 'standalone', '0.00', 'EUR', 1, 999, 0, 0, NOW())
            SQL);
    }

    public function down(Schema $schema): void
    {
        // Best-effort: si ya hay líneas históricas, la FK RESTRICT impedirá borrar
        // el servicio y esta migración fallará. Es intencional: no se deben perder
        // reservas registradas.
        $this->addSql("DELETE FROM service WHERE slug = 'historico'");
        $this->addSql('ALTER TABLE booking DROP is_historical');
        $this->addSql('ALTER TABLE booking_line DROP flight_date');
        $this->addSql('ALTER TABLE booking_line CHANGE slot_id slot_id INT NOT NULL');
    }
}
