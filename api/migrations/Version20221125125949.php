<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20221125125949 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE cronjob RENAME COLUMN is_active TO is_enabled');
        $this->addSql('ALTER TABLE action RENAME COLUMN is_active TO is_enabled');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE cronjob RENAME COLUMN is_enabled TO is_active');
        $this->addSql('ALTER TABLE action RENAME COLUMN is_enabled TO is_active');
    }
}
