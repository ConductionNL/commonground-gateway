<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240216094019 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP SEQUENCE messenger_messages_id_seq CASCADE');
        $this->addSql('ALTER TABLE log DROP CONSTRAINT fk_8f3f68c581257d5d');
        $this->addSql('ALTER TABLE log DROP CONSTRAINT fk_8f3f68c521af7e36');
        $this->addSql('ALTER TABLE log DROP CONSTRAINT fk_8f3f68c5577f8e00');
        $this->addSql('ALTER TABLE log DROP CONSTRAINT fk_8f3f68c5a6e82043');
        $this->addSql('ALTER TABLE handler DROP CONSTRAINT fk_939715cd81257d5d');
        $this->addSql('ALTER TABLE soap DROP CONSTRAINT fk_37a677a9b086aa2c');
        $this->addSql('ALTER TABLE soap DROP CONSTRAINT fk_37a677a991b9e8e7');
        $this->addSql('ALTER TABLE handler_endpoint DROP CONSTRAINT fk_26ccbd14a6e82043');
        $this->addSql('ALTER TABLE handler_endpoint DROP CONSTRAINT fk_26ccbd1421af7e36');
        $this->addSql('DROP TABLE log');
        $this->addSql('DROP TABLE handler');
        $this->addSql('DROP TABLE soap');
        $this->addSql('DROP TABLE handler_endpoint');
        $this->addSql('DROP TABLE audit_trail');
        $this->addSql('DROP TABLE messenger_messages');
        $this->addSql('DROP TABLE change_log');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('CREATE SEQUENCE messenger_messages_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE log (id UUID NOT NULL, entity_id UUID DEFAULT NULL, endpoint_id UUID DEFAULT NULL, gateway_id UUID DEFAULT NULL, handler_id UUID DEFAULT NULL, type VARCHAR(255) NOT NULL, call_id UUID NOT NULL, request_method VARCHAR(255) NOT NULL, request_headers TEXT NOT NULL, request_query TEXT NOT NULL, request_path_info VARCHAR(255) NOT NULL, request_languages TEXT NOT NULL, request_server TEXT NOT NULL, request_content TEXT NOT NULL, response_status VARCHAR(255) DEFAULT NULL, response_status_code INT DEFAULT NULL, response_headers TEXT DEFAULT NULL, response_content TEXT DEFAULT NULL, user_id VARCHAR(255) DEFAULT NULL, session VARCHAR(255) NOT NULL, session_values TEXT NOT NULL, response_time INT NOT NULL, route_name VARCHAR(255) DEFAULT NULL, route_parameters TEXT DEFAULT NULL, object_id VARCHAR(255) DEFAULT NULL, date_created TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, date_modified TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_8f3f68c5a6e82043 ON log (handler_id)');
        $this->addSql('CREATE INDEX idx_8f3f68c5577f8e00 ON log (gateway_id)');
        $this->addSql('CREATE INDEX idx_8f3f68c521af7e36 ON log (endpoint_id)');
        $this->addSql('CREATE INDEX idx_8f3f68c581257d5d ON log (entity_id)');
        $this->addSql('COMMENT ON COLUMN log.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN log.entity_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN log.endpoint_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN log.gateway_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN log.handler_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN log.call_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN log.request_headers IS \'(DC2Type:array)\'');
        $this->addSql('COMMENT ON COLUMN log.request_query IS \'(DC2Type:array)\'');
        $this->addSql('COMMENT ON COLUMN log.request_languages IS \'(DC2Type:array)\'');
        $this->addSql('COMMENT ON COLUMN log.request_server IS \'(DC2Type:array)\'');
        $this->addSql('COMMENT ON COLUMN log.response_headers IS \'(DC2Type:array)\'');
        $this->addSql('COMMENT ON COLUMN log.session_values IS \'(DC2Type:array)\'');
        $this->addSql('COMMENT ON COLUMN log.route_parameters IS \'(DC2Type:array)\'');
        $this->addSql('CREATE TABLE handler (id UUID NOT NULL, entity_id UUID DEFAULT NULL, name VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, methods TEXT NOT NULL, sequence INT NOT NULL, conditions VARCHAR(255) DEFAULT \'{}\' NOT NULL, translations_in TEXT DEFAULT NULL, mapping_in TEXT DEFAULT NULL, skeleton_in TEXT DEFAULT NULL, skeleton_out TEXT DEFAULT NULL, mapping_out TEXT DEFAULT NULL, translations_out TEXT DEFAULT NULL, template_type VARCHAR(255) DEFAULT NULL, template TEXT DEFAULT NULL, proxy_gateway VARCHAR(255) DEFAULT NULL, date_created TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, date_modified TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, method_overrides TEXT DEFAULT NULL, prefix VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_939715cd81257d5d ON handler (entity_id)');
        $this->addSql('COMMENT ON COLUMN handler.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN handler.entity_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN handler.methods IS \'(DC2Type:array)\'');
        $this->addSql('COMMENT ON COLUMN handler.translations_in IS \'(DC2Type:array)\'');
        $this->addSql('COMMENT ON COLUMN handler.mapping_in IS \'(DC2Type:array)\'');
        $this->addSql('COMMENT ON COLUMN handler.skeleton_in IS \'(DC2Type:array)\'');
        $this->addSql('COMMENT ON COLUMN handler.skeleton_out IS \'(DC2Type:array)\'');
        $this->addSql('COMMENT ON COLUMN handler.mapping_out IS \'(DC2Type:array)\'');
        $this->addSql('COMMENT ON COLUMN handler.translations_out IS \'(DC2Type:array)\'');
        $this->addSql('COMMENT ON COLUMN handler.method_overrides IS \'(DC2Type:array)\'');
        $this->addSql('CREATE TABLE soap (id UUID NOT NULL, to_entity_id UUID DEFAULT NULL, from_entity_id UUID DEFAULT NULL, name VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, type VARCHAR(255) NOT NULL, request TEXT DEFAULT NULL, request_skeleton TEXT NOT NULL, request_hydration TEXT DEFAULT NULL, response TEXT DEFAULT NULL, response_skeleton TEXT NOT NULL, response_hydration TEXT DEFAULT NULL, zaaktype VARCHAR(255) DEFAULT NULL, date_created TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, date_modified TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_37a677a991b9e8e7 ON soap (from_entity_id)');
        $this->addSql('CREATE INDEX idx_37a677a9b086aa2c ON soap (to_entity_id)');
        $this->addSql('COMMENT ON COLUMN soap.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN soap.to_entity_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN soap.from_entity_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN soap.request_skeleton IS \'(DC2Type:array)\'');
        $this->addSql('COMMENT ON COLUMN soap.request_hydration IS \'(DC2Type:array)\'');
        $this->addSql('COMMENT ON COLUMN soap.response_skeleton IS \'(DC2Type:array)\'');
        $this->addSql('COMMENT ON COLUMN soap.response_hydration IS \'(DC2Type:array)\'');
        $this->addSql('CREATE TABLE handler_endpoint (handler_id UUID NOT NULL, endpoint_id UUID NOT NULL, PRIMARY KEY(handler_id, endpoint_id))');
        $this->addSql('CREATE INDEX idx_26ccbd1421af7e36 ON handler_endpoint (endpoint_id)');
        $this->addSql('CREATE INDEX idx_26ccbd14a6e82043 ON handler_endpoint (handler_id)');
        $this->addSql('COMMENT ON COLUMN handler_endpoint.handler_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN handler_endpoint.endpoint_id IS \'(DC2Type:uuid)\'');
        $this->addSql('CREATE TABLE audit_trail (id UUID NOT NULL, note TEXT DEFAULT NULL, application VARCHAR(255) DEFAULT NULL, request VARCHAR(255) DEFAULT NULL, username VARCHAR(255) DEFAULT NULL, subject VARCHAR(255) DEFAULT NULL, process VARCHAR(255) DEFAULT NULL, data_elements TEXT DEFAULT NULL, data_subjects TEXT DEFAULT NULL, resource VARCHAR(255) DEFAULT NULL, resource_type VARCHAR(255) DEFAULT NULL, route VARCHAR(255) DEFAULT NULL, endpoint VARCHAR(255) DEFAULT NULL, method VARCHAR(10) DEFAULT NULL, accept VARCHAR(255) DEFAULT NULL, content_type VARCHAR(255) DEFAULT NULL, content TEXT DEFAULT NULL, ip VARCHAR(255) DEFAULT NULL, session VARCHAR(255) NOT NULL, headers TEXT NOT NULL, status_code INT DEFAULT NULL, not_found BOOLEAN DEFAULT NULL, forbidden BOOLEAN DEFAULT NULL, ok BOOLEAN DEFAULT NULL, date_created TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, date_modified TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('COMMENT ON COLUMN audit_trail.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN audit_trail.data_elements IS \'(DC2Type:array)\'');
        $this->addSql('COMMENT ON COLUMN audit_trail.data_subjects IS \'(DC2Type:array)\'');
        $this->addSql('COMMENT ON COLUMN audit_trail.headers IS \'(DC2Type:array)\'');
        $this->addSql('CREATE TABLE messenger_messages (id BIGSERIAL NOT NULL, body TEXT NOT NULL, headers TEXT NOT NULL, queue_name VARCHAR(255) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, available_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, delivered_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_75ea56e016ba31db ON messenger_messages (delivered_at)');
        $this->addSql('CREATE INDEX idx_75ea56e0e3bd61ce ON messenger_messages (available_at)');
        $this->addSql('CREATE INDEX idx_75ea56e0fb7336f0 ON messenger_messages (queue_name)');
        $this->addSql('CREATE TABLE change_log (id UUID NOT NULL, note TEXT DEFAULT NULL, action VARCHAR(8) NOT NULL, logged_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, object_id VARCHAR(64) DEFAULT NULL, object_class VARCHAR(255) NOT NULL, version INT NOT NULL, data TEXT DEFAULT NULL, username VARCHAR(255) DEFAULT NULL, session VARCHAR(255) DEFAULT NULL, date_created TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, date_modified TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('COMMENT ON COLUMN change_log.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN change_log.data IS \'(DC2Type:array)\'');
        $this->addSql('ALTER TABLE log ADD CONSTRAINT fk_8f3f68c581257d5d FOREIGN KEY (entity_id) REFERENCES entity (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE log ADD CONSTRAINT fk_8f3f68c521af7e36 FOREIGN KEY (endpoint_id) REFERENCES endpoint (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE log ADD CONSTRAINT fk_8f3f68c5577f8e00 FOREIGN KEY (gateway_id) REFERENCES gateway (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE log ADD CONSTRAINT fk_8f3f68c5a6e82043 FOREIGN KEY (handler_id) REFERENCES handler (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE handler ADD CONSTRAINT fk_939715cd81257d5d FOREIGN KEY (entity_id) REFERENCES entity (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE soap ADD CONSTRAINT fk_37a677a9b086aa2c FOREIGN KEY (to_entity_id) REFERENCES entity (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE soap ADD CONSTRAINT fk_37a677a991b9e8e7 FOREIGN KEY (from_entity_id) REFERENCES entity (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE handler_endpoint ADD CONSTRAINT fk_26ccbd14a6e82043 FOREIGN KEY (handler_id) REFERENCES handler (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE handler_endpoint ADD CONSTRAINT fk_26ccbd1421af7e36 FOREIGN KEY (endpoint_id) REFERENCES endpoint (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }
}
