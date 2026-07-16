<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260715230109 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE application_settings (key VARCHAR(120) NOT NULL, value JSON NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, PRIMARY KEY (key))');
        $this->addSql('CREATE TABLE audit_logs (id CHAR(36) NOT NULL, actor_id CHAR(36) DEFAULT NULL, action VARCHAR(120) NOT NULL, entity_type VARCHAR(120) DEFAULT NULL, entity_id CHAR(36) DEFAULT NULL, before JSON DEFAULT NULL, after JSON DEFAULT NULL, ip VARCHAR(64) DEFAULT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX ix_audit_entity ON audit_logs (entity_type, entity_id)');
        $this->addSql('CREATE INDEX ix_audit_created ON audit_logs (created_at)');
        $this->addSql('CREATE TABLE consents (id CHAR(36) NOT NULL, type VARCHAR(64) NOT NULL, granted BOOLEAN NOT NULL, text_version VARCHAR(32) NOT NULL, granted_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, revoked_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, user_id CHAR(36) NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_6DACD67A76ED395 ON consents (user_id)');
        $this->addSql('CREATE TABLE customer_profiles (id CHAR(36) NOT NULL, first_name VARCHAR(120) DEFAULT NULL, last_name VARCHAR(120) DEFAULT NULL, phone VARCHAR(32) DEFAULT NULL, address TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, user_id CHAR(36) NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_A7F5C61FA76ED395 ON customer_profiles (user_id)');
        $this->addSql('CREATE TABLE documents (id CHAR(36) NOT NULL, storage_key VARCHAR(512) NOT NULL, original_name VARCHAR(255) DEFAULT NULL, mime_type VARCHAR(120) NOT NULL, size_bytes BIGINT NOT NULL, scan_status VARCHAR(16) NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, deleted_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, owner_user_id CHAR(36) DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_A2B072882B18554A ON documents (owner_user_id)');
        $this->addSql('CREATE TABLE notifications (id CHAR(36) NOT NULL, type VARCHAR(64) NOT NULL, payload JSON NOT NULL, channel VARCHAR(16) NOT NULL, read_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, sent_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, user_id CHAR(36) NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX ix_notif_user ON notifications (user_id)');
        $this->addSql('CREATE TABLE service_admins (id CHAR(36) NOT NULL, display_name VARCHAR(120) DEFAULT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, user_id CHAR(36) NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_9C271039A76ED395 ON service_admins (user_id)');
        $this->addSql('CREATE TABLE users (id CHAR(36) NOT NULL, email VARCHAR(255) NOT NULL, phone VARCHAR(32) DEFAULT NULL, password_hash VARCHAR(255) NOT NULL, role VARCHAR(32) NOT NULL, totp_secret VARCHAR(255) DEFAULT NULL, totp_enabled BOOLEAN NOT NULL, is_active BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1483A5E9E7927C74 ON users (email)');
        $this->addSql('CREATE TABLE vehicle_ownerships (id CHAR(36) NOT NULL, active BOOLEAN NOT NULL, valid_from DATE NOT NULL, valid_to DATE DEFAULT NULL, vehicle_id CHAR(36) NOT NULL, customer_profile_id CHAR(36) NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_69E68727545317D1 ON vehicle_ownerships (vehicle_id)');
        $this->addSql('CREATE INDEX ix_ownership_customer ON vehicle_ownerships (customer_profile_id)');
        $this->addSql('CREATE TABLE vehicles (id CHAR(36) NOT NULL, vin VARCHAR(17) NOT NULL, plate_number VARCHAR(16) NOT NULL, make VARCHAR(80) DEFAULT NULL, model VARCHAR(80) DEFAULT NULL, year INT DEFAULT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, deleted_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX ix_vehicles_vin ON vehicles (vin)');
        $this->addSql('ALTER TABLE consents ADD CONSTRAINT FK_6DACD67A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE customer_profiles ADD CONSTRAINT FK_A7F5C61FA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE documents ADD CONSTRAINT FK_A2B072882B18554A FOREIGN KEY (owner_user_id) REFERENCES users (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE notifications ADD CONSTRAINT FK_6000B0D3A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE service_admins ADD CONSTRAINT FK_9C271039A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE vehicle_ownerships ADD CONSTRAINT FK_69E68727545317D1 FOREIGN KEY (vehicle_id) REFERENCES vehicles (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE vehicle_ownerships ADD CONSTRAINT FK_69E687273133163A FOREIGN KEY (customer_profile_id) REFERENCES customer_profiles (id) NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE consents DROP CONSTRAINT FK_6DACD67A76ED395');
        $this->addSql('ALTER TABLE customer_profiles DROP CONSTRAINT FK_A7F5C61FA76ED395');
        $this->addSql('ALTER TABLE documents DROP CONSTRAINT FK_A2B072882B18554A');
        $this->addSql('ALTER TABLE notifications DROP CONSTRAINT FK_6000B0D3A76ED395');
        $this->addSql('ALTER TABLE service_admins DROP CONSTRAINT FK_9C271039A76ED395');
        $this->addSql('ALTER TABLE vehicle_ownerships DROP CONSTRAINT FK_69E68727545317D1');
        $this->addSql('ALTER TABLE vehicle_ownerships DROP CONSTRAINT FK_69E687273133163A');
        $this->addSql('DROP TABLE application_settings');
        $this->addSql('DROP TABLE audit_logs');
        $this->addSql('DROP TABLE consents');
        $this->addSql('DROP TABLE customer_profiles');
        $this->addSql('DROP TABLE documents');
        $this->addSql('DROP TABLE notifications');
        $this->addSql('DROP TABLE service_admins');
        $this->addSql('DROP TABLE users');
        $this->addSql('DROP TABLE vehicle_ownerships');
        $this->addSql('DROP TABLE vehicles');
    }
}
