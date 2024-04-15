<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240311132843 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE endpoint_gateway (endpoint_id UUID NOT NULL, gateway_id UUID NOT NULL, PRIMARY KEY(endpoint_id, gateway_id))');
        $this->addSql('CREATE INDEX IDX_39C1245121AF7E36 ON endpoint_gateway (endpoint_id)');
        $this->addSql('CREATE INDEX IDX_39C12451577F8E00 ON endpoint_gateway (gateway_id)');
        $this->addSql('ALTER TABLE endpoint_gateway ADD CONSTRAINT FK_39C1245121AF7E36 FOREIGN KEY (endpoint_id) REFERENCES endpoint (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE endpoint_gateway ADD CONSTRAINT FK_39C12451577F8E00 FOREIGN KEY (gateway_id) REFERENCES gateway (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE endpoint_gateway');
    }
}
