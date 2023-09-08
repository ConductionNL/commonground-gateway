<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230907115512 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE template ADD reference VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE template ADD version VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE template ALTER id TYPE UUID');
        $this->addSql('ALTER TABLE template ALTER id DROP DEFAULT');
        $this->addSql('ALTER TABLE template ALTER organization_id TYPE UUID');
        $this->addSql('ALTER TABLE template ALTER organization_id DROP DEFAULT');
        $this->addSql('ALTER TABLE template ALTER organization_id DROP NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE template DROP reference');
        $this->addSql('ALTER TABLE template DROP version');
        $this->addSql('ALTER TABLE template ALTER id TYPE UUID');
        $this->addSql('ALTER TABLE template ALTER id DROP DEFAULT');
        $this->addSql('ALTER TABLE template ALTER organization_id TYPE UUID');
        $this->addSql('ALTER TABLE template ALTER organization_id DROP DEFAULT');
        $this->addSql('ALTER TABLE template ALTER organization_id SET NOT NULL');
    }
}
