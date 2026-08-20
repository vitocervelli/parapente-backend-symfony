<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Galería del vuelo: fotos y vídeos que el equipo sube a cada reserva para que
 * el cliente los vea desde su cuenta. Los ficheros viven en el almacén privado
 * (var/uploads/media) y esta tabla guarda sus metadatos, como payment_proof.
 */
final class Version20260822000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Galería de fotos/vídeos por reserva (tabla booking_media).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE booking_media (id INT AUTO_INCREMENT NOT NULL, kind VARCHAR(10) NOT NULL, storage_path VARCHAR(255) NOT NULL, original_name VARCHAR(200) NOT NULL, mime_type VARCHAR(60) NOT NULL, size_bytes INT UNSIGNED NOT NULL, checksum VARCHAR(64) NOT NULL, uploaded_at DATETIME NOT NULL, booking_id INT NOT NULL, INDEX IDX_FB505CB93301C60 (booking_id), INDEX idx_media_booking (booking_id, uploaded_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE booking_media ADD CONSTRAINT FK_FB505CB93301C60 FOREIGN KEY (booking_id) REFERENCES booking (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // Los ficheros de var/uploads/media quedan huérfanos en disco; borrarlos
        // es decisión manual (mismo trade-off que el CASCADE de payment_proof).
        $this->addSql('ALTER TABLE booking_media DROP FOREIGN KEY FK_FB505CB93301C60');
        $this->addSql('DROP TABLE booking_media');
    }
}
