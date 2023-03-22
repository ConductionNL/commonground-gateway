<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230322102328 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE action ALTER id TYPE UUID');
        $this->addSql('ALTER TABLE action ALTER id DROP DEFAULT');
        $this->addSql('ALTER TABLE action_handler ALTER id TYPE UUID');
        $this->addSql('ALTER TABLE action_handler ALTER id DROP DEFAULT');
        $this->addSql('ALTER TABLE application ALTER id TYPE UUID');
        $this->addSql('ALTER TABLE application ALTER id DROP DEFAULT');
        $this->addSql('ALTER TABLE application ALTER organization_id TYPE UUID');
        $this->addSql('ALTER TABLE application ALTER organization_id DROP DEFAULT');
        $this->addSql('ALTER TABLE application_endpoint ALTER application_id TYPE UUID');
        $this->addSql('ALTER TABLE application_endpoint ALTER application_id DROP DEFAULT');
        $this->addSql('ALTER TABLE application_endpoint ALTER endpoint_id TYPE UUID');
        $this->addSql('ALTER TABLE application_endpoint ALTER endpoint_id DROP DEFAULT');
        $this->addSql('ALTER TABLE attribute ALTER id TYPE UUID');
        $this->addSql('ALTER TABLE attribute ALTER id DROP DEFAULT');
        $this->addSql('ALTER TABLE attribute ALTER entity_id TYPE UUID');
        $this->addSql('ALTER TABLE attribute ALTER entity_id DROP DEFAULT');
        $this->addSql('ALTER TABLE attribute ALTER search_partial_id TYPE UUID');
        $this->addSql('ALTER TABLE attribute ALTER search_partial_id DROP DEFAULT');
        $this->addSql('ALTER TABLE attribute ALTER object_id TYPE UUID');
        $this->addSql('ALTER TABLE attribute ALTER object_id DROP DEFAULT');
        $this->addSql('ALTER TABLE attribute ALTER inversed_by_id TYPE UUID');
        $this->addSql('ALTER TABLE attribute ALTER inversed_by_id DROP DEFAULT');
        $this->addSql('ALTER TABLE audit_trail ALTER id TYPE UUID');
        $this->addSql('ALTER TABLE audit_trail ALTER id DROP DEFAULT');
        $this->addSql('ALTER TABLE authentication ALTER id TYPE UUID');
        $this->addSql('ALTER TABLE authentication ALTER id DROP DEFAULT');
        $this->addSql('ALTER TABLE change_log ALTER id TYPE UUID');
        $this->addSql('ALTER TABLE change_log ALTER id DROP DEFAULT');
        $this->addSql('ALTER TABLE collection_entity ALTER id TYPE UUID');
        $this->addSql('ALTER TABLE collection_entity ALTER id DROP DEFAULT');
        $this->addSql('ALTER TABLE collection_entity ALTER source_id TYPE UUID');
        $this->addSql('ALTER TABLE collection_entity ALTER source_id DROP DEFAULT');
        $this->addSql('ALTER TABLE collection_entity_application ALTER collection_entity_id TYPE UUID');
        $this->addSql('ALTER TABLE collection_entity_application ALTER collection_entity_id DROP DEFAULT');
        $this->addSql('ALTER TABLE collection_entity_application ALTER application_id TYPE UUID');
        $this->addSql('ALTER TABLE collection_entity_application ALTER application_id DROP DEFAULT');
        $this->addSql('ALTER TABLE collection_entity_endpoint ALTER collection_entity_id TYPE UUID');
        $this->addSql('ALTER TABLE collection_entity_endpoint ALTER collection_entity_id DROP DEFAULT');
        $this->addSql('ALTER TABLE collection_entity_endpoint ALTER endpoint_id TYPE UUID');
        $this->addSql('ALTER TABLE collection_entity_endpoint ALTER endpoint_id DROP DEFAULT');
        $this->addSql('ALTER TABLE collection_entity_entity ALTER collection_entity_id TYPE UUID');
        $this->addSql('ALTER TABLE collection_entity_entity ALTER collection_entity_id DROP DEFAULT');
        $this->addSql('ALTER TABLE collection_entity_entity ALTER entity_id TYPE UUID');
        $this->addSql('ALTER TABLE collection_entity_entity ALTER entity_id DROP DEFAULT');
        $this->addSql('ALTER TABLE contract ALTER id TYPE UUID');
        $this->addSql('ALTER TABLE contract ALTER id DROP DEFAULT');
        $this->addSql('ALTER TABLE contract ALTER application_id TYPE UUID');
        $this->addSql('ALTER TABLE contract ALTER application_id DROP DEFAULT');
        $this->addSql('ALTER TABLE cronjob ALTER id TYPE UUID');
        $this->addSql('ALTER TABLE cronjob ALTER id DROP DEFAULT');
        $this->addSql('ALTER TABLE dashboard_card ALTER id TYPE UUID');
        $this->addSql('ALTER TABLE dashboard_card ALTER id DROP DEFAULT');
        $this->addSql('ALTER TABLE dashboard_card ALTER entity_id TYPE UUID');
        $this->addSql('ALTER TABLE dashboard_card ALTER entity_id DROP DEFAULT');
        $this->addSql('ALTER TABLE document ALTER id TYPE UUID');
        $this->addSql('ALTER TABLE document ALTER id DROP DEFAULT');
        $this->addSql('ALTER TABLE document ALTER object_id TYPE UUID');
        $this->addSql('ALTER TABLE document ALTER object_id DROP DEFAULT');
        $this->addSql('ALTER TABLE endpoint ALTER id TYPE UUID');
        $this->addSql('ALTER TABLE endpoint ALTER id DROP DEFAULT');
        $this->addSql('ALTER TABLE endpoint ALTER entity_id TYPE UUID');
        $this->addSql('ALTER TABLE endpoint ALTER entity_id DROP DEFAULT');
        $this->addSql('ALTER TABLE endpoint ALTER proxy_id TYPE UUID');
        $this->addSql('ALTER TABLE endpoint ALTER proxy_id DROP DEFAULT');
        $this->addSql('ALTER TABLE endpoint_entity ALTER endpoint_id TYPE UUID');
        $this->addSql('ALTER TABLE endpoint_entity ALTER endpoint_id DROP DEFAULT');
        $this->addSql('ALTER TABLE endpoint_entity ALTER entity_id TYPE UUID');
        $this->addSql('ALTER TABLE endpoint_entity ALTER entity_id DROP DEFAULT');
        $this->addSql('ALTER TABLE entity ADD persist BOOLEAN DEFAULT \'false\'');
        $this->addSql('ALTER TABLE entity ALTER id TYPE UUID');
        $this->addSql('ALTER TABLE entity ALTER id DROP DEFAULT');
        $this->addSql('ALTER TABLE entity ALTER gateway_id TYPE UUID');
        $this->addSql('ALTER TABLE entity ALTER gateway_id DROP DEFAULT');
        $this->addSql('ALTER TABLE file ALTER id TYPE UUID');
        $this->addSql('ALTER TABLE file ALTER id DROP DEFAULT');
        $this->addSql('ALTER TABLE file ALTER value_id TYPE UUID');
        $this->addSql('ALTER TABLE file ALTER value_id DROP DEFAULT');
        $this->addSql('ALTER TABLE gateway ALTER id TYPE UUID');
        $this->addSql('ALTER TABLE gateway ALTER id DROP DEFAULT');
        $this->addSql('ALTER TABLE gateway ALTER location SET DEFAULT \'\'');
        $this->addSql('ALTER TABLE handler ALTER id TYPE UUID');
        $this->addSql('ALTER TABLE handler ALTER id DROP DEFAULT');
        $this->addSql('ALTER TABLE handler ALTER entity_id TYPE UUID');
        $this->addSql('ALTER TABLE handler ALTER entity_id DROP DEFAULT');
        $this->addSql('ALTER TABLE handler_endpoint ALTER handler_id TYPE UUID');
        $this->addSql('ALTER TABLE handler_endpoint ALTER handler_id DROP DEFAULT');
        $this->addSql('ALTER TABLE handler_endpoint ALTER endpoint_id TYPE UUID');
        $this->addSql('ALTER TABLE handler_endpoint ALTER endpoint_id DROP DEFAULT');
        $this->addSql('ALTER TABLE log ALTER id TYPE UUID');
        $this->addSql('ALTER TABLE log ALTER id DROP DEFAULT');
        $this->addSql('ALTER TABLE log ALTER entity_id TYPE UUID');
        $this->addSql('ALTER TABLE log ALTER entity_id DROP DEFAULT');
        $this->addSql('ALTER TABLE log ALTER endpoint_id TYPE UUID');
        $this->addSql('ALTER TABLE log ALTER endpoint_id DROP DEFAULT');
        $this->addSql('ALTER TABLE log ALTER gateway_id TYPE UUID');
        $this->addSql('ALTER TABLE log ALTER gateway_id DROP DEFAULT');
        $this->addSql('ALTER TABLE log ALTER handler_id TYPE UUID');
        $this->addSql('ALTER TABLE log ALTER handler_id DROP DEFAULT');
        $this->addSql('ALTER TABLE log ALTER call_id TYPE UUID');
        $this->addSql('ALTER TABLE log ALTER call_id DROP DEFAULT');
        $this->addSql('ALTER TABLE mapping ALTER id TYPE UUID');
        $this->addSql('ALTER TABLE mapping ALTER id DROP DEFAULT');
        $this->addSql('ALTER TABLE object_entity ALTER id TYPE UUID');
        $this->addSql('ALTER TABLE object_entity ALTER id DROP DEFAULT');
        $this->addSql('ALTER TABLE object_entity ALTER application_id TYPE UUID');
        $this->addSql('ALTER TABLE object_entity ALTER application_id DROP DEFAULT');
        $this->addSql('ALTER TABLE object_entity ALTER organization_id TYPE UUID');
        $this->addSql('ALTER TABLE object_entity ALTER organization_id DROP DEFAULT');
        $this->addSql('ALTER TABLE object_entity ALTER entity_id TYPE UUID');
        $this->addSql('ALTER TABLE object_entity ALTER entity_id DROP DEFAULT');
        $this->addSql('ALTER TABLE object_entity_value ALTER object_entity_id TYPE UUID');
        $this->addSql('ALTER TABLE object_entity_value ALTER object_entity_id DROP DEFAULT');
        $this->addSql('ALTER TABLE object_entity_value ALTER value_id TYPE UUID');
        $this->addSql('ALTER TABLE object_entity_value ALTER value_id DROP DEFAULT');
        $this->addSql('ALTER TABLE organization ALTER id TYPE UUID');
        $this->addSql('ALTER TABLE organization ALTER id DROP DEFAULT');
        $this->addSql('ALTER TABLE property ALTER id TYPE UUID');
        $this->addSql('ALTER TABLE property ALTER id DROP DEFAULT');
        $this->addSql('ALTER TABLE property ALTER endpoint_id TYPE UUID');
        $this->addSql('ALTER TABLE property ALTER endpoint_id DROP DEFAULT');
        $this->addSql('ALTER TABLE purpose ALTER id TYPE UUID');
        $this->addSql('ALTER TABLE purpose ALTER id DROP DEFAULT');
        $this->addSql('ALTER TABLE purpose ALTER contract_id TYPE UUID');
        $this->addSql('ALTER TABLE purpose ALTER contract_id DROP DEFAULT');
        $this->addSql('ALTER TABLE security_group ALTER id TYPE UUID');
        $this->addSql('ALTER TABLE security_group ALTER id DROP DEFAULT');
        $this->addSql('ALTER TABLE security_group ALTER parent_id TYPE UUID');
        $this->addSql('ALTER TABLE security_group ALTER parent_id DROP DEFAULT');
        $this->addSql('ALTER TABLE security_group_user ALTER security_group_id TYPE UUID');
        $this->addSql('ALTER TABLE security_group_user ALTER security_group_id DROP DEFAULT');
        $this->addSql('ALTER TABLE security_group_user ALTER user_id TYPE UUID');
        $this->addSql('ALTER TABLE security_group_user ALTER user_id DROP DEFAULT');
        $this->addSql('ALTER TABLE soap ALTER id TYPE UUID');
        $this->addSql('ALTER TABLE soap ALTER id DROP DEFAULT');
        $this->addSql('ALTER TABLE soap ALTER to_entity_id TYPE UUID');
        $this->addSql('ALTER TABLE soap ALTER to_entity_id DROP DEFAULT');
        $this->addSql('ALTER TABLE soap ALTER from_entity_id TYPE UUID');
        $this->addSql('ALTER TABLE soap ALTER from_entity_id DROP DEFAULT');
        $this->addSql('ALTER TABLE synchronization ALTER id TYPE UUID');
        $this->addSql('ALTER TABLE synchronization ALTER id DROP DEFAULT');
        $this->addSql('ALTER TABLE synchronization ALTER entity_id TYPE UUID');
        $this->addSql('ALTER TABLE synchronization ALTER entity_id DROP DEFAULT');
        $this->addSql('ALTER TABLE synchronization ALTER object_id TYPE UUID');
        $this->addSql('ALTER TABLE synchronization ALTER object_id DROP DEFAULT');
        $this->addSql('ALTER TABLE synchronization ALTER action_id TYPE UUID');
        $this->addSql('ALTER TABLE synchronization ALTER action_id DROP DEFAULT');
        $this->addSql('ALTER TABLE synchronization ALTER gateway_id TYPE UUID');
        $this->addSql('ALTER TABLE synchronization ALTER gateway_id DROP DEFAULT');
        $this->addSql('ALTER TABLE synchronization ALTER source_object_id TYPE UUID');
        $this->addSql('ALTER TABLE synchronization ALTER source_object_id DROP DEFAULT');
        $this->addSql('ALTER TABLE synchronization ALTER mapping_id TYPE UUID');
        $this->addSql('ALTER TABLE synchronization ALTER mapping_id DROP DEFAULT');
        $this->addSql('ALTER TABLE translation ALTER id TYPE UUID');
        $this->addSql('ALTER TABLE translation ALTER id DROP DEFAULT');
        $this->addSql('ALTER TABLE unread ALTER id TYPE UUID');
        $this->addSql('ALTER TABLE unread ALTER id DROP DEFAULT');
        $this->addSql('ALTER TABLE unread ALTER object_id TYPE UUID');
        $this->addSql('ALTER TABLE unread ALTER object_id DROP DEFAULT');
        $this->addSql('ALTER TABLE "user" ALTER id TYPE UUID');
        $this->addSql('ALTER TABLE "user" ALTER id DROP DEFAULT');
        $this->addSql('ALTER TABLE "user" ALTER organisation_id TYPE UUID');
        $this->addSql('ALTER TABLE "user" ALTER organisation_id DROP DEFAULT');
        $this->addSql('ALTER TABLE user_application ALTER user_id TYPE UUID');
        $this->addSql('ALTER TABLE user_application ALTER user_id DROP DEFAULT');
        $this->addSql('ALTER TABLE user_application ALTER application_id TYPE UUID');
        $this->addSql('ALTER TABLE user_application ALTER application_id DROP DEFAULT');
        $this->addSql('ALTER TABLE value ALTER id TYPE UUID');
        $this->addSql('ALTER TABLE value ALTER id DROP DEFAULT');
        $this->addSql('ALTER TABLE value ALTER attribute_id TYPE UUID');
        $this->addSql('ALTER TABLE value ALTER attribute_id DROP DEFAULT');
        $this->addSql('ALTER TABLE value ALTER object_entity_id TYPE UUID');
        $this->addSql('ALTER TABLE value ALTER object_entity_id DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE authentication ALTER id TYPE UUID');
        $this->addSql('ALTER TABLE authentication ALTER id DROP DEFAULT');
        $this->addSql('ALTER TABLE action_handler ALTER id TYPE UUID');
        $this->addSql('ALTER TABLE action_handler ALTER id DROP DEFAULT');
        $this->addSql('ALTER TABLE audit_trail ALTER id TYPE UUID');
        $this->addSql('ALTER TABLE audit_trail ALTER id DROP DEFAULT');
        $this->addSql('ALTER TABLE change_log ALTER id TYPE UUID');
        $this->addSql('ALTER TABLE change_log ALTER id DROP DEFAULT');
        $this->addSql('ALTER TABLE dashboard_card ALTER id TYPE UUID');
        $this->addSql('ALTER TABLE dashboard_card ALTER id DROP DEFAULT');
        $this->addSql('ALTER TABLE dashboard_card ALTER entity_id TYPE UUID');
        $this->addSql('ALTER TABLE dashboard_card ALTER entity_id DROP DEFAULT');
        $this->addSql('ALTER TABLE cronjob ALTER id TYPE UUID');
        $this->addSql('ALTER TABLE cronjob ALTER id DROP DEFAULT');
        $this->addSql('ALTER TABLE translation ALTER id TYPE UUID');
        $this->addSql('ALTER TABLE translation ALTER id DROP DEFAULT');
        $this->addSql('ALTER TABLE application_endpoint ALTER application_id TYPE UUID');
        $this->addSql('ALTER TABLE application_endpoint ALTER application_id DROP DEFAULT');
        $this->addSql('ALTER TABLE application_endpoint ALTER endpoint_id TYPE UUID');
        $this->addSql('ALTER TABLE application_endpoint ALTER endpoint_id DROP DEFAULT');
        $this->addSql('ALTER TABLE collection_entity ALTER id TYPE UUID');
        $this->addSql('ALTER TABLE collection_entity ALTER id DROP DEFAULT');
        $this->addSql('ALTER TABLE collection_entity ALTER source_id TYPE UUID');
        $this->addSql('ALTER TABLE collection_entity ALTER source_id DROP DEFAULT');
        $this->addSql('ALTER TABLE collection_entity_application ALTER collection_entity_id TYPE UUID');
        $this->addSql('ALTER TABLE collection_entity_application ALTER collection_entity_id DROP DEFAULT');
        $this->addSql('ALTER TABLE collection_entity_application ALTER application_id TYPE UUID');
        $this->addSql('ALTER TABLE collection_entity_application ALTER application_id DROP DEFAULT');
        $this->addSql('ALTER TABLE collection_entity_endpoint ALTER collection_entity_id TYPE UUID');
        $this->addSql('ALTER TABLE collection_entity_endpoint ALTER collection_entity_id DROP DEFAULT');
        $this->addSql('ALTER TABLE collection_entity_endpoint ALTER endpoint_id TYPE UUID');
        $this->addSql('ALTER TABLE collection_entity_endpoint ALTER endpoint_id DROP DEFAULT');
        $this->addSql('ALTER TABLE collection_entity_entity ALTER collection_entity_id TYPE UUID');
        $this->addSql('ALTER TABLE collection_entity_entity ALTER collection_entity_id DROP DEFAULT');
        $this->addSql('ALTER TABLE collection_entity_entity ALTER entity_id TYPE UUID');
        $this->addSql('ALTER TABLE collection_entity_entity ALTER entity_id DROP DEFAULT');
        $this->addSql('ALTER TABLE document ALTER id TYPE UUID');
        $this->addSql('ALTER TABLE document ALTER id DROP DEFAULT');
        $this->addSql('ALTER TABLE document ALTER object_id TYPE UUID');
        $this->addSql('ALTER TABLE document ALTER object_id DROP DEFAULT');
        $this->addSql('ALTER TABLE endpoint_entity ALTER endpoint_id TYPE UUID');
        $this->addSql('ALTER TABLE endpoint_entity ALTER endpoint_id DROP DEFAULT');
        $this->addSql('ALTER TABLE endpoint_entity ALTER entity_id TYPE UUID');
        $this->addSql('ALTER TABLE endpoint_entity ALTER entity_id DROP DEFAULT');
        $this->addSql('ALTER TABLE file ALTER id TYPE UUID');
        $this->addSql('ALTER TABLE file ALTER id DROP DEFAULT');
        $this->addSql('ALTER TABLE file ALTER value_id TYPE UUID');
        $this->addSql('ALTER TABLE file ALTER value_id DROP DEFAULT');
        $this->addSql('ALTER TABLE handler_endpoint ALTER handler_id TYPE UUID');
        $this->addSql('ALTER TABLE handler_endpoint ALTER handler_id DROP DEFAULT');
        $this->addSql('ALTER TABLE handler_endpoint ALTER endpoint_id TYPE UUID');
        $this->addSql('ALTER TABLE handler_endpoint ALTER endpoint_id DROP DEFAULT');
        $this->addSql('ALTER TABLE handler ALTER id TYPE UUID');
        $this->addSql('ALTER TABLE handler ALTER id DROP DEFAULT');
        $this->addSql('ALTER TABLE handler ALTER entity_id TYPE UUID');
        $this->addSql('ALTER TABLE handler ALTER entity_id DROP DEFAULT');
        $this->addSql('ALTER TABLE log ALTER id TYPE UUID');
        $this->addSql('ALTER TABLE log ALTER id DROP DEFAULT');
        $this->addSql('ALTER TABLE log ALTER entity_id TYPE UUID');
        $this->addSql('ALTER TABLE log ALTER entity_id DROP DEFAULT');
        $this->addSql('ALTER TABLE log ALTER endpoint_id TYPE UUID');
        $this->addSql('ALTER TABLE log ALTER endpoint_id DROP DEFAULT');
        $this->addSql('ALTER TABLE log ALTER gateway_id TYPE UUID');
        $this->addSql('ALTER TABLE log ALTER gateway_id DROP DEFAULT');
        $this->addSql('ALTER TABLE log ALTER handler_id TYPE UUID');
        $this->addSql('ALTER TABLE log ALTER handler_id DROP DEFAULT');
        $this->addSql('ALTER TABLE log ALTER call_id TYPE UUID');
        $this->addSql('ALTER TABLE log ALTER call_id DROP DEFAULT');
        $this->addSql('ALTER TABLE endpoint ALTER id TYPE UUID');
        $this->addSql('ALTER TABLE endpoint ALTER id DROP DEFAULT');
        $this->addSql('ALTER TABLE endpoint ALTER entity_id TYPE UUID');
        $this->addSql('ALTER TABLE endpoint ALTER entity_id DROP DEFAULT');
        $this->addSql('ALTER TABLE endpoint ALTER proxy_id TYPE UUID');
        $this->addSql('ALTER TABLE endpoint ALTER proxy_id DROP DEFAULT');
        $this->addSql('ALTER TABLE object_entity_value ALTER object_entity_id TYPE UUID');
        $this->addSql('ALTER TABLE object_entity_value ALTER object_entity_id DROP DEFAULT');
        $this->addSql('ALTER TABLE object_entity_value ALTER value_id TYPE UUID');
        $this->addSql('ALTER TABLE object_entity_value ALTER value_id DROP DEFAULT');
        $this->addSql('ALTER TABLE property ALTER id TYPE UUID');
        $this->addSql('ALTER TABLE property ALTER id DROP DEFAULT');
        $this->addSql('ALTER TABLE property ALTER endpoint_id TYPE UUID');
        $this->addSql('ALTER TABLE property ALTER endpoint_id DROP DEFAULT');
        $this->addSql('ALTER TABLE contract ALTER id TYPE UUID');
        $this->addSql('ALTER TABLE contract ALTER id DROP DEFAULT');
        $this->addSql('ALTER TABLE contract ALTER application_id TYPE UUID');
        $this->addSql('ALTER TABLE contract ALTER application_id DROP DEFAULT');
        $this->addSql('ALTER TABLE purpose ALTER id TYPE UUID');
        $this->addSql('ALTER TABLE purpose ALTER id DROP DEFAULT');
        $this->addSql('ALTER TABLE purpose ALTER contract_id TYPE UUID');
        $this->addSql('ALTER TABLE purpose ALTER contract_id DROP DEFAULT');
        $this->addSql('ALTER TABLE security_group ALTER id TYPE UUID');
        $this->addSql('ALTER TABLE security_group ALTER id DROP DEFAULT');
        $this->addSql('ALTER TABLE security_group ALTER parent_id TYPE UUID');
        $this->addSql('ALTER TABLE security_group ALTER parent_id DROP DEFAULT');
        $this->addSql('ALTER TABLE security_group_user ALTER security_group_id TYPE UUID');
        $this->addSql('ALTER TABLE security_group_user ALTER security_group_id DROP DEFAULT');
        $this->addSql('ALTER TABLE security_group_user ALTER user_id TYPE UUID');
        $this->addSql('ALTER TABLE security_group_user ALTER user_id DROP DEFAULT');
        $this->addSql('ALTER TABLE soap ALTER id TYPE UUID');
        $this->addSql('ALTER TABLE soap ALTER id DROP DEFAULT');
        $this->addSql('ALTER TABLE soap ALTER to_entity_id TYPE UUID');
        $this->addSql('ALTER TABLE soap ALTER to_entity_id DROP DEFAULT');
        $this->addSql('ALTER TABLE soap ALTER from_entity_id TYPE UUID');
        $this->addSql('ALTER TABLE soap ALTER from_entity_id DROP DEFAULT');
        $this->addSql('ALTER TABLE entity DROP persist');
        $this->addSql('ALTER TABLE entity ALTER id TYPE UUID');
        $this->addSql('ALTER TABLE entity ALTER id DROP DEFAULT');
        $this->addSql('ALTER TABLE entity ALTER gateway_id TYPE UUID');
        $this->addSql('ALTER TABLE entity ALTER gateway_id DROP DEFAULT');
        $this->addSql('ALTER TABLE action ALTER id TYPE UUID');
        $this->addSql('ALTER TABLE action ALTER id DROP DEFAULT');
        $this->addSql('ALTER TABLE application ALTER id TYPE UUID');
        $this->addSql('ALTER TABLE application ALTER id DROP DEFAULT');
        $this->addSql('ALTER TABLE application ALTER organization_id TYPE UUID');
        $this->addSql('ALTER TABLE application ALTER organization_id DROP DEFAULT');
        $this->addSql('ALTER TABLE mapping ALTER id TYPE UUID');
        $this->addSql('ALTER TABLE mapping ALTER id DROP DEFAULT');
        $this->addSql('ALTER TABLE synchronization ALTER id TYPE UUID');
        $this->addSql('ALTER TABLE synchronization ALTER id DROP DEFAULT');
        $this->addSql('ALTER TABLE synchronization ALTER entity_id TYPE UUID');
        $this->addSql('ALTER TABLE synchronization ALTER entity_id DROP DEFAULT');
        $this->addSql('ALTER TABLE synchronization ALTER object_id TYPE UUID');
        $this->addSql('ALTER TABLE synchronization ALTER object_id DROP DEFAULT');
        $this->addSql('ALTER TABLE synchronization ALTER action_id TYPE UUID');
        $this->addSql('ALTER TABLE synchronization ALTER action_id DROP DEFAULT');
        $this->addSql('ALTER TABLE synchronization ALTER gateway_id TYPE UUID');
        $this->addSql('ALTER TABLE synchronization ALTER gateway_id DROP DEFAULT');
        $this->addSql('ALTER TABLE synchronization ALTER source_object_id TYPE UUID');
        $this->addSql('ALTER TABLE synchronization ALTER source_object_id DROP DEFAULT');
        $this->addSql('ALTER TABLE synchronization ALTER mapping_id TYPE UUID');
        $this->addSql('ALTER TABLE synchronization ALTER mapping_id DROP DEFAULT');
        $this->addSql('ALTER TABLE unread ALTER id TYPE UUID');
        $this->addSql('ALTER TABLE unread ALTER id DROP DEFAULT');
        $this->addSql('ALTER TABLE unread ALTER object_id TYPE UUID');
        $this->addSql('ALTER TABLE unread ALTER object_id DROP DEFAULT');
        $this->addSql('ALTER TABLE organization ALTER id TYPE UUID');
        $this->addSql('ALTER TABLE organization ALTER id DROP DEFAULT');
        $this->addSql('ALTER TABLE "user" ALTER id TYPE UUID');
        $this->addSql('ALTER TABLE "user" ALTER id DROP DEFAULT');
        $this->addSql('ALTER TABLE "user" ALTER organisation_id TYPE UUID');
        $this->addSql('ALTER TABLE "user" ALTER organisation_id DROP DEFAULT');
        $this->addSql('ALTER TABLE user_application ALTER user_id TYPE UUID');
        $this->addSql('ALTER TABLE user_application ALTER user_id DROP DEFAULT');
        $this->addSql('ALTER TABLE user_application ALTER application_id TYPE UUID');
        $this->addSql('ALTER TABLE user_application ALTER application_id DROP DEFAULT');
        $this->addSql('ALTER TABLE attribute ALTER id TYPE UUID');
        $this->addSql('ALTER TABLE attribute ALTER id DROP DEFAULT');
        $this->addSql('ALTER TABLE attribute ALTER entity_id TYPE UUID');
        $this->addSql('ALTER TABLE attribute ALTER entity_id DROP DEFAULT');
        $this->addSql('ALTER TABLE attribute ALTER search_partial_id TYPE UUID');
        $this->addSql('ALTER TABLE attribute ALTER search_partial_id DROP DEFAULT');
        $this->addSql('ALTER TABLE attribute ALTER object_id TYPE UUID');
        $this->addSql('ALTER TABLE attribute ALTER object_id DROP DEFAULT');
        $this->addSql('ALTER TABLE attribute ALTER inversed_by_id TYPE UUID');
        $this->addSql('ALTER TABLE attribute ALTER inversed_by_id DROP DEFAULT');
        $this->addSql('ALTER TABLE object_entity ALTER id TYPE UUID');
        $this->addSql('ALTER TABLE object_entity ALTER id DROP DEFAULT');
        $this->addSql('ALTER TABLE object_entity ALTER application_id TYPE UUID');
        $this->addSql('ALTER TABLE object_entity ALTER application_id DROP DEFAULT');
        $this->addSql('ALTER TABLE object_entity ALTER organization_id TYPE UUID');
        $this->addSql('ALTER TABLE object_entity ALTER organization_id DROP DEFAULT');
        $this->addSql('ALTER TABLE object_entity ALTER entity_id TYPE UUID');
        $this->addSql('ALTER TABLE object_entity ALTER entity_id DROP DEFAULT');
        $this->addSql('ALTER TABLE value ALTER id TYPE UUID');
        $this->addSql('ALTER TABLE value ALTER id DROP DEFAULT');
        $this->addSql('ALTER TABLE value ALTER attribute_id TYPE UUID');
        $this->addSql('ALTER TABLE value ALTER attribute_id DROP DEFAULT');
        $this->addSql('ALTER TABLE value ALTER object_entity_id TYPE UUID');
        $this->addSql('ALTER TABLE value ALTER object_entity_id DROP DEFAULT');
        $this->addSql('ALTER TABLE gateway ALTER id TYPE UUID');
        $this->addSql('ALTER TABLE gateway ALTER id DROP DEFAULT');
        $this->addSql('ALTER TABLE gateway ALTER location DROP DEFAULT');
    }
}
