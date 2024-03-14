<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240314120005 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Added a Database Entity for Multi-tenancy';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SEQUENCE database_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE database (id UUID NOT NULL, name VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, reference VARCHAR(255) NOT NULL, version VARCHAR(255) NOT NULL DEFAULT \'0.0.0\', uri VARCHAR(255) NOT NULL, date_created TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, date_modified TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('ALTER TABLE organization ADD database_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE organization ADD CONSTRAINT FK_C1EE637CF0AA09DB FOREIGN KEY (database_id) REFERENCES database (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_C1EE637CF0AA09DB ON organization (database_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE organization DROP CONSTRAINT FK_C1EE637CF0AA09DB');
        $this->addSql('DROP INDEX IDX_C1EE637CF0AA09DB');
        $this->addSql('ALTER TABLE organization DROP database_id');
        $this->addSql('DROP SEQUENCE database_id_seq CASCADE');
        $this->addSql('DROP TABLE database');
    }
}
