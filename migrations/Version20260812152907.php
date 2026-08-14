<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260812152907 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Añade a app_user los datos personales del cliente y la fecha de alta.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_user ADD full_name VARCHAR(160) DEFAULT NULL, ADD id_number VARCHAR(40) DEFAULT NULL, ADD phone VARCHAR(40) DEFAULT NULL');

        // created_at se añade primero como nullable y se rellena: con NOT NULL
        // directo, las cuentas que ya existen recibirían '0000-00-00', que el
        // sql_mode de esta base (NO_ZERO_DATE) rechaza.
        $this->addSql('ALTER TABLE app_user ADD created_at DATETIME DEFAULT NULL');
        $this->addSql('UPDATE app_user SET created_at = NOW() WHERE created_at IS NULL');
        $this->addSql('ALTER TABLE app_user MODIFY created_at DATETIME NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_user DROP full_name, DROP id_number, DROP phone, DROP created_at');
    }
}
