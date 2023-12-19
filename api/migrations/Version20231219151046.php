<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20231219151046 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Set version default to 0.0.1';
    }

    public function up(Schema $schema): void
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

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE action ALTER version SET DEFAULT NULL');
        $this->addSql('ALTER TABLE application ALTER version SET DEFAULT NULL');
        $this->addSql('ALTER TABLE collection_entity ALTER version SET DEFAULT NULL');
        $this->addSql('ALTER TABLE cronjob ALTER version SET DEFAULT NULL');
        $this->addSql('ALTER TABLE endpoint ALTER version SET DEFAULT NULL');
        $this->addSql('ALTER TABLE entity ALTER version SET DEFAULT NULL');
        $this->addSql('ALTER TABLE gateway ALTER version SET DEFAULT NULL');
        $this->addSql('ALTER TABLE mapping ALTER version SET DEFAULT NULL');
        $this->addSql('ALTER TABLE organization ALTER version SET DEFAULT NULL');
        $this->addSql('ALTER TABLE security_group ALTER version SET DEFAULT NULL');
        $this->addSql('ALTER TABLE template ALTER version SET DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ALTER version SET DEFAULT NULL');
    }
}
