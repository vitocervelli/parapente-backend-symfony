<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Esquema inicial: servicios, catálogo de elementos incluidos y usuarios del panel.
 */
final class Version20260812102642 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Esquema inicial del CMS: service, inclusion_item, service_inclusion y app_user.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE app_user (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, UNIQUE INDEX UNIQ_88BDF3E9E7927C74 (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE inclusion_item (id INT AUTO_INCREMENT NOT NULL, slug VARCHAR(80) NOT NULL, default_label VARCHAR(160) NOT NULL, icon VARCHAR(60) NOT NULL, icon_path VARCHAR(255) DEFAULT NULL, position SMALLINT DEFAULT 0 NOT NULL, UNIQUE INDEX UNIQ_DFD19F25989D9B62 (slug), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE service (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(160) NOT NULL, slug VARCHAR(128) NOT NULL, type VARCHAR(20) NOT NULL, tagline VARCHAR(255) DEFAULT NULL, description LONGTEXT DEFAULT NULL, price_amount NUMERIC(10, 2) NOT NULL, currency VARCHAR(3) NOT NULL, people SMALLINT UNSIGNED DEFAULT 1 NOT NULL, price_note VARCHAR(60) DEFAULT NULL, duration_minutes SMALLINT UNSIGNED DEFAULT NULL, badge VARCHAR(40) DEFAULT NULL, cover_image VARCHAR(255) DEFAULT NULL, flyer_image VARCHAR(255) DEFAULT NULL, position SMALLINT DEFAULT 0 NOT NULL, is_active TINYINT DEFAULT 1 NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, UNIQUE INDEX UNIQ_E19D9AD2989D9B62 (slug), INDEX idx_service_type_active_pos (type, is_active, position), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE service_inclusion (id INT AUTO_INCREMENT NOT NULL, label_override VARCHAR(160) DEFAULT NULL, note VARCHAR(255) DEFAULT NULL, position SMALLINT DEFAULT 0 NOT NULL, service_id INT NOT NULL, item_id INT NOT NULL, INDEX IDX_980999EED5CA9E6 (service_id), INDEX IDX_980999E126F525E (item_id), INDEX idx_inclusion_service_pos (service_id, position), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE service_inclusion ADD CONSTRAINT FK_980999EED5CA9E6 FOREIGN KEY (service_id) REFERENCES service (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE service_inclusion ADD CONSTRAINT FK_980999E126F525E FOREIGN KEY (item_id) REFERENCES inclusion_item (id) ON DELETE RESTRICT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE service_inclusion DROP FOREIGN KEY FK_980999EED5CA9E6');
        $this->addSql('ALTER TABLE service_inclusion DROP FOREIGN KEY FK_980999E126F525E');
        $this->addSql('DROP TABLE app_user');
        $this->addSql('DROP TABLE inclusion_item');
        $this->addSql('DROP TABLE service');
        $this->addSql('DROP TABLE service_inclusion');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
