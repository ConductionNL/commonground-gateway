<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240223102937 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE action ALTER version SET NOT NULL');
        $this->addSql('ALTER TABLE action ALTER is_enabled DROP DEFAULT');
        $this->addSql('ALTER TABLE application ALTER resource TYPE TEXT');
        $this->addSql('ALTER TABLE application ALTER version SET NOT NULL');
        $this->addSql('DROP INDEX entity_attribute_unique');
        $this->addSql('ALTER TABLE attribute DROP extend');
        $this->addSql('ALTER TABLE attribute ALTER nullable SET DEFAULT false');
        $this->addSql('ALTER TABLE attribute ALTER allow_cascade SET DEFAULT false');
        $this->addSql('ALTER TABLE attribute ALTER cache_sub_objects DROP NOT NULL');
        $this->addSql('ALTER TABLE collection_entity ALTER description TYPE TEXT');
        $this->addSql('ALTER TABLE collection_entity ALTER plugin TYPE VARCHAR(2555)');
        $this->addSql('ALTER TABLE collection_entity ALTER version SET NOT NULL');
        $this->addSql('ALTER TABLE cronjob ALTER description TYPE TEXT');
        $this->addSql('ALTER TABLE cronjob ALTER version SET NOT NULL');
        $this->addSql('ALTER TABLE document ALTER route DROP NOT NULL');
        $this->addSql('ALTER TABLE document RENAME COLUMN content_type TO type');
        $this->addSql('ALTER TABLE endpoint DROP CONSTRAINT fk_c4420f7b81257d5d');
        $this->addSql('DROP INDEX idx_c4420f7b81257d5d');
        $this->addSql('ALTER TABLE endpoint DROP entity_id');
        $this->addSql('ALTER TABLE endpoint ALTER tags SET NOT NULL');
        $this->addSql('ALTER TABLE endpoint ALTER version SET NOT NULL');
        $this->addSql('ALTER TABLE entity DROP CONSTRAINT fk_e284468577f8e00');
        $this->addSql('DROP INDEX idx_e284468577f8e00');
        $this->addSql('ALTER TABLE entity DROP gateway_id');
        $this->addSql('ALTER TABLE entity DROP endpoint');
        $this->addSql('ALTER TABLE entity DROP function_column');
        $this->addSql('ALTER TABLE entity DROP transformations');
        $this->addSql('ALTER TABLE entity DROP route');
        $this->addSql('ALTER TABLE entity DROP available_properties');
        $this->addSql('ALTER TABLE entity DROP used_properties');
        $this->addSql('ALTER TABLE entity DROP translation_config');
        $this->addSql('ALTER TABLE entity DROP collection_config');
        $this->addSql('ALTER TABLE entity DROP item_config');
        $this->addSql('ALTER TABLE entity DROP extern_mapping_in');
        $this->addSql('ALTER TABLE entity DROP extern_mapping_out');
        $this->addSql('ALTER TABLE entity DROP schema_column');
        $this->addSql('ALTER TABLE entity ALTER version SET NOT NULL');
        $this->addSql('ALTER TABLE entity ALTER persist SET DEFAULT true');
        $this->addSql('ALTER TABLE gateway ALTER location SET DEFAULT \'\'');
        $this->addSql('ALTER TABLE gateway ALTER documentation TYPE VARCHAR(511)');
        $this->addSql('ALTER TABLE gateway ALTER logging_config DROP DEFAULT');
        $this->addSql('ALTER TABLE gateway ALTER version SET NOT NULL');
        $this->addSql('ALTER TABLE gateway_audit_trail DROP uuid');
        $this->addSql('ALTER TABLE gateway_audit_trail ALTER amendments TYPE TEXT');
        $this->addSql('COMMENT ON COLUMN gateway_audit_trail.amendments IS \'(DC2Type:array)\'');
        $this->addSql('ALTER TABLE mapping ALTER version SET NOT NULL');
        $this->addSql('ALTER TABLE mapping ALTER pass_trough SET DEFAULT true');
        $this->addSql('ALTER TABLE mapping RENAME COLUMN cast_column TO "cast"');
        $this->addSql('ALTER TABLE object_entity ALTER name SET NOT NULL');
        $this->addSql('ALTER TABLE organization ALTER version SET NOT NULL');
        $this->addSql('ALTER TABLE property ALTER description TYPE TEXT');
        $this->addSql('ALTER TABLE purpose ALTER description TYPE TEXT');
        $this->addSql('ALTER TABLE security_group ALTER version SET NOT NULL');
        $this->addSql('ALTER TABLE synchronization ALTER dont_sync_before DROP DEFAULT');
        $this->addSql('ALTER TABLE synchronization ALTER sha TYPE TEXT');
        $this->addSql('ALTER TABLE synchronization ALTER sha SET NOT NULL');
        $this->addSql('ALTER TABLE template ALTER version SET NOT NULL');
        $this->addSql('ALTER TABLE "user" ALTER version SET NOT NULL');
        $this->addSql('ALTER INDEX idx_8d93d6499e6b1585 RENAME TO IDX_8D93D64932C8A3DE');
        $this->addSql('ALTER TABLE value ALTER uri TYPE VARCHAR(10)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE security_group ALTER version DROP NOT NULL');
        $this->addSql('ALTER TABLE organization ALTER version DROP NOT NULL');
        $this->addSql('ALTER TABLE cronjob ALTER description TYPE VARCHAR(255)');
        $this->addSql('ALTER TABLE cronjob ALTER version DROP NOT NULL');
        $this->addSql('ALTER TABLE gateway ALTER version DROP NOT NULL');
        $this->addSql('ALTER TABLE gateway ALTER location DROP DEFAULT');
        $this->addSql('ALTER TABLE gateway ALTER documentation TYPE VARCHAR(255)');
        $this->addSql('ALTER TABLE gateway ALTER logging_config SET DEFAULT \'a:10:{s:10:"callMethod";b:1;s:7:"callUrl";b:1;s:9:"callQuery";b:1;s:15:"callContentType";b:1;s:8:"callBody";b:1;s:18:"responseStatusCode";b:1;s:19:"responseContentType";b:1;s:12:"responseBody";b:1;s:16:"maxCharCountBody";i:500;s:21:"maxCharCountErrorBody";i:2000;}\'');
        $this->addSql('ALTER TABLE collection_entity ALTER description TYPE VARCHAR(255)');
        $this->addSql('ALTER TABLE collection_entity ALTER version DROP NOT NULL');
        $this->addSql('ALTER TABLE collection_entity ALTER plugin TYPE VARCHAR(255)');
        $this->addSql('ALTER TABLE action ALTER version DROP NOT NULL');
        $this->addSql('ALTER TABLE action ALTER is_enabled SET DEFAULT true');
        $this->addSql('ALTER TABLE "user" ALTER version DROP NOT NULL');
        $this->addSql('ALTER INDEX idx_8d93d64932c8a3de RENAME TO idx_8d93d6499e6b1585');
        $this->addSql('ALTER TABLE mapping ALTER version DROP NOT NULL');
        $this->addSql('ALTER TABLE mapping ALTER pass_trough DROP DEFAULT');
        $this->addSql('ALTER TABLE mapping RENAME COLUMN "cast" TO cast_column');
        $this->addSql('ALTER TABLE attribute ADD extend BOOLEAN DEFAULT false NOT NULL');
        $this->addSql('ALTER TABLE attribute ALTER nullable SET DEFAULT true');
        $this->addSql('ALTER TABLE attribute ALTER allow_cascade SET DEFAULT true');
        $this->addSql('ALTER TABLE attribute ALTER cache_sub_objects SET NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX entity_attribute_unique ON attribute (name, entity_id)');
        $this->addSql('ALTER TABLE synchronization ALTER sha TYPE VARCHAR(255)');
        $this->addSql('ALTER TABLE synchronization ALTER sha DROP NOT NULL');
        $this->addSql('ALTER TABLE synchronization ALTER dont_sync_before SET DEFAULT CURRENT_TIMESTAMP');
        $this->addSql('ALTER TABLE endpoint ADD entity_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE endpoint ALTER version DROP NOT NULL');
        $this->addSql('ALTER TABLE endpoint ALTER tags DROP NOT NULL');
        $this->addSql('COMMENT ON COLUMN endpoint.entity_id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE endpoint ADD CONSTRAINT fk_c4420f7b81257d5d FOREIGN KEY (entity_id) REFERENCES entity (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_c4420f7b81257d5d ON endpoint (entity_id)');
        $this->addSql('ALTER TABLE template ALTER version DROP NOT NULL');
        $this->addSql('ALTER TABLE object_entity ALTER name DROP NOT NULL');
        $this->addSql('ALTER TABLE "gateway_audit_trail" ADD uuid UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE "gateway_audit_trail" ALTER amendments TYPE TEXT');
        $this->addSql('COMMENT ON COLUMN "gateway_audit_trail".uuid IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN "gateway_audit_trail".amendments IS \'(DC2Type:object)\'');
        $this->addSql('ALTER TABLE entity ADD gateway_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE entity ADD endpoint VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE entity ADD function_column VARCHAR(255) DEFAULT \'noFunction\' NOT NULL');
        $this->addSql('ALTER TABLE entity ADD transformations TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE entity ADD route VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE entity ADD available_properties TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE entity ADD used_properties TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE entity ADD translation_config TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE entity ADD collection_config TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE entity ADD item_config TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE entity ADD extern_mapping_in TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE entity ADD extern_mapping_out TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE entity ADD schema_column VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE entity ALTER version DROP NOT NULL');
        $this->addSql('ALTER TABLE entity ALTER persist SET DEFAULT false');
        $this->addSql('COMMENT ON COLUMN entity.gateway_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN entity.transformations IS \'(DC2Type:array)\'');
        $this->addSql('COMMENT ON COLUMN entity.available_properties IS \'(DC2Type:array)\'');
        $this->addSql('COMMENT ON COLUMN entity.used_properties IS \'(DC2Type:array)\'');
        $this->addSql('COMMENT ON COLUMN entity.translation_config IS \'(DC2Type:array)\'');
        $this->addSql('COMMENT ON COLUMN entity.collection_config IS \'(DC2Type:array)\'');
        $this->addSql('COMMENT ON COLUMN entity.item_config IS \'(DC2Type:array)\'');
        $this->addSql('COMMENT ON COLUMN entity.extern_mapping_in IS \'(DC2Type:array)\'');
        $this->addSql('COMMENT ON COLUMN entity.extern_mapping_out IS \'(DC2Type:array)\'');
        $this->addSql('ALTER TABLE entity ADD CONSTRAINT fk_e284468577f8e00 FOREIGN KEY (gateway_id) REFERENCES gateway (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_e284468577f8e00 ON entity (gateway_id)');
        $this->addSql('ALTER TABLE property ALTER description TYPE VARCHAR(255)');
        $this->addSql('ALTER TABLE value ALTER uri TYPE VARCHAR(255)');
        $this->addSql('ALTER TABLE purpose ALTER description TYPE VARCHAR(255)');
        $this->addSql('ALTER TABLE application ALTER version DROP NOT NULL');
        $this->addSql('ALTER TABLE application ALTER resource TYPE VARCHAR(255)');
        $this->addSql('ALTER TABLE document ALTER route SET NOT NULL');
        $this->addSql('ALTER TABLE document RENAME COLUMN type TO content_type');
    }
}
