<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230504111926 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE "gateway_audit_trail" (id UUID NOT NULL, uuid UUID DEFAULT NULL, source VARCHAR(255) DEFAULT NULL, application_id VARCHAR(255) DEFAULT NULL, application_view VARCHAR(255) DEFAULT NULL, user_id VARCHAR(255) DEFAULT NULL, user_view VARCHAR(255) DEFAULT NULL, action VARCHAR(255) DEFAULT NULL, action_view VARCHAR(255) DEFAULT NULL, result INT DEFAULT NULL, main_object VARCHAR(255) DEFAULT NULL, resource VARCHAR(255) DEFAULT NULL, resource_url VARCHAR(255) DEFAULT NULL, explanation VARCHAR(255) DEFAULT NULL, resource_view VARCHAR(255) DEFAULT NULL, creation_date TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, amendments TEXT DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('COMMENT ON COLUMN "gateway_audit_trail".amendments IS \'(DC2Type:object)\'');
        $this->addSql('ALTER TABLE audit_trail ALTER id TYPE UUID');
        $this->addSql('ALTER TABLE audit_trail ALTER id DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE "gateway_audit_trail"');
        $this->addSql('ALTER TABLE audit_trail ALTER id TYPE UUID');
        $this->addSql('ALTER TABLE audit_trail ALTER id DROP DEFAULT');
    }
}
