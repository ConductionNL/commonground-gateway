<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230328151236 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE security_group ADD reference VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE security_group ADD version VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD reference VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD version VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE organisation ADD reference VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE organisation ADD version VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE application ADD reference VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE application ADD version VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE security_group DROP reference');
        $this->addSql('ALTER TABLE security_group DROP version');
        $this->addSql('ALTER TABLE "user" DROP reference');
        $this->addSql('ALTER TABLE "user" DROP version');
        $this->addSql('ALTER TABLE organisation DROP reference');
        $this->addSql('ALTER TABLE organisation DROP version');
        $this->addSql('ALTER TABLE application DROP reference');
        $this->addSql('ALTER TABLE application DROP version');
    }
}
