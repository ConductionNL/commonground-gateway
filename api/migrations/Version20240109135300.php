<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240109135300 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Set version default to 0.0.0 (and not nullable)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('UPDATE action SET version = \'0.0.0\' WHERE version IS NULL');
        $this->addSql('ALTER TABLE action ALTER version SET DEFAULT \'0.0.0\'');
        $this->addSql('UPDATE application SET version = \'0.0.0\' WHERE version IS NULL');
        $this->addSql('ALTER TABLE application ALTER version SET DEFAULT \'0.0.0\'');
        $this->addSql('UPDATE collection_entity SET version = \'0.0.0\' WHERE version IS NULL');
        $this->addSql('ALTER TABLE collection_entity ALTER version SET DEFAULT \'0.0.0\'');
        $this->addSql('UPDATE cronjob SET version = \'0.0.0\' WHERE version IS NULL');
        $this->addSql('ALTER TABLE cronjob ALTER version SET DEFAULT \'0.0.0\'');
        $this->addSql('UPDATE endpoint SET version = \'0.0.0\' WHERE version IS NULL');
        $this->addSql('ALTER TABLE endpoint ALTER version SET DEFAULT \'0.0.0\'');
        $this->addSql('UPDATE entity SET version = \'0.0.0\' WHERE version IS NULL');
        $this->addSql('ALTER TABLE entity ALTER version SET DEFAULT \'0.0.0\'');
        $this->addSql('UPDATE gateway SET version = \'0.0.0\' WHERE version IS NULL');
        $this->addSql('ALTER TABLE gateway ALTER version SET DEFAULT \'0.0.0\'');
        $this->addSql('UPDATE mapping SET version = \'0.0.0\' WHERE version IS NULL');
        $this->addSql('ALTER TABLE mapping ALTER version SET DEFAULT \'0.0.0\'');
        $this->addSql('UPDATE organization SET version = \'0.0.0\' WHERE version IS NULL');
        $this->addSql('ALTER TABLE organization ALTER version SET DEFAULT \'0.0.0\'');
        $this->addSql('UPDATE security_group SET version = \'0.0.0\' WHERE version IS NULL');
        $this->addSql('ALTER TABLE security_group ALTER version SET DEFAULT \'0.0.0\'');
        $this->addSql('UPDATE template SET version = \'0.0.0\' WHERE version IS NULL');
        $this->addSql('ALTER TABLE template ALTER version SET DEFAULT \'0.0.0\'');
        $this->addSql('UPDATE "user" SET version = \'0.0.0\' WHERE version IS NULL');
        $this->addSql('ALTER TABLE "user" ALTER version SET DEFAULT \'0.0.0\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE action ALTER version SET DEFAULT \'0.0.1\'');
        $this->addSql('ALTER TABLE application ALTER version SET DEFAULT \'0.0.1\'');
        $this->addSql('ALTER TABLE collection_entity ALTER version SET DEFAULT \'0.0.1\'');
        $this->addSql('ALTER TABLE cronjob ALTER version SET DEFAULT \'0.0.1\'');
        $this->addSql('ALTER TABLE endpoint ALTER version SET DEFAULT \'0.0.1\'');
        $this->addSql('ALTER TABLE entity ALTER version SET DEFAULT \'0.0.1\'');
        $this->addSql('ALTER TABLE gateway ALTER version SET DEFAULT \'0.0.1\'');
        $this->addSql('ALTER TABLE mapping ALTER version SET DEFAULT \'0.0.1\'');
        $this->addSql('ALTER TABLE organization ALTER version SET DEFAULT \'0.0.1\'');
        $this->addSql('ALTER TABLE security_group ALTER version SET DEFAULT \'0.0.1\'');
        $this->addSql('ALTER TABLE template ALTER version SET DEFAULT \'0.0.1\'');
        $this->addSql('ALTER TABLE "user" ALTER version SET DEFAULT \'0.0.1\'');
    }
}
