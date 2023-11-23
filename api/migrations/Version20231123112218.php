<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20231123112218 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE gateway ALTER logging TYPE TEXT');
        $this->addSql('ALTER TABLE gateway ALTER logging SET DEFAULT \'a:10:{s:10:"callMethod";b:1;s:7:"callUrl";b:1;s:9:"callQuery";b:1;s:15:"callContentType";b:1;s:8:"callBody";b:1;s:18:"responseStatusCode";b:1;s:19:"responseContentType";b:1;s:12:"responseBody";b:1;s:16:"maxCharCountBody";i:500;s:21:"maxCharCountErrorBody";i:2000;}\'');
        $this->addSql('UPDATE gateway SET logging = \'a:10:{s:10:"callMethod";b:1;s:7:"callUrl";b:1;s:9:"callQuery";b:1;s:15:"callContentType";b:1;s:8:"callBody";b:1;s:18:"responseStatusCode";b:1;s:19:"responseContentType";b:1;s:12:"responseBody";b:1;s:16:"maxCharCountBody";i:500;s:21:"maxCharCountErrorBody";i:2000;}\'');
        $this->addSql('ALTER TABLE gateway ALTER logging SET NOT NULL');
        $this->addSql('COMMENT ON COLUMN gateway.logging IS \'(DC2Type:array)\'');
        $this->addSql('ALTER TABLE gateway RENAME COLUMN logging TO logging_config');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE gateway RENAME COLUMN logging_config TO logging');
        $this->addSql('ALTER TABLE gateway ALTER logging DROP NOT NULL');
        $this->addSql('ALTER TABLE gateway ALTER logging DROP DEFAULT');
        $this->addSql('UPDATE gateway SET logging = NULL');
        $this->addSql('ALTER TABLE gateway ALTER logging TYPE BOOLEAN USING logging::boolean');
        $this->addSql('COMMENT ON COLUMN gateway.logging IS NULL');
    }
}
