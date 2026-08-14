<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260813092903 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Crea payment_proof: comprobantes de pago subidos por el cliente.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE payment_proof (id INT AUTO_INCREMENT NOT NULL, storage_path VARCHAR(255) NOT NULL, original_name VARCHAR(200) NOT NULL, mime_type VARCHAR(60) NOT NULL, size_bytes INT UNSIGNED NOT NULL, checksum VARCHAR(64) NOT NULL, declared_amount NUMERIC(10, 2) DEFAULT NULL, transfer_reference VARCHAR(80) DEFAULT NULL, status VARCHAR(20) NOT NULL, uploaded_at DATETIME NOT NULL, reviewed_at DATETIME DEFAULT NULL, review_note LONGTEXT DEFAULT NULL, booking_id INT NOT NULL, reviewed_by_id INT DEFAULT NULL, INDEX IDX_ACB0766F3301C60 (booking_id), INDEX IDX_ACB0766FFC6B21F1 (reviewed_by_id), INDEX idx_proof_booking (booking_id, uploaded_at), INDEX idx_proof_status (status), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE payment_proof ADD CONSTRAINT FK_ACB0766F3301C60 FOREIGN KEY (booking_id) REFERENCES booking (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE payment_proof ADD CONSTRAINT FK_ACB0766FFC6B21F1 FOREIGN KEY (reviewed_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE payment_proof DROP FOREIGN KEY FK_ACB0766F3301C60');
        $this->addSql('ALTER TABLE payment_proof DROP FOREIGN KEY FK_ACB0766FFC6B21F1');
        $this->addSql('DROP TABLE payment_proof');
    }
}
