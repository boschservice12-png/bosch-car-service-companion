<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Asistență rutieră: tabelele roadside_requests și roadside_request_documents.
 */
final class Version20260716094054 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Asistență rutieră: roadside_requests + roadside_request_documents';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE roadside_requests (id CHAR(36) NOT NULL, location VARCHAR(500) NOT NULL, problem TEXT NOT NULL, mobility VARCHAR(16) NOT NULL, safety VARCHAR(16) NOT NULL, phone VARCHAR(40) NOT NULL, status VARCHAR(16) NOT NULL, note TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, customer_id CHAR(36) NOT NULL, vehicle_id CHAR(36) DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_7632304F545317D1 ON roadside_requests (vehicle_id)');
        $this->addSql('CREATE INDEX ix_roadside_customer ON roadside_requests (customer_id)');
        $this->addSql('CREATE INDEX ix_roadside_status ON roadside_requests (status)');
        $this->addSql('CREATE TABLE roadside_request_documents (roadside_request_id CHAR(36) NOT NULL, document_id CHAR(36) NOT NULL, PRIMARY KEY (roadside_request_id, document_id))');
        $this->addSql('CREATE INDEX IDX_91B054DADF56CD80 ON roadside_request_documents (roadside_request_id)');
        $this->addSql('CREATE INDEX IDX_91B054DAC33F7837 ON roadside_request_documents (document_id)');
        $this->addSql('ALTER TABLE roadside_requests ADD CONSTRAINT FK_7632304F9395C3F3 FOREIGN KEY (customer_id) REFERENCES users (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE roadside_requests ADD CONSTRAINT FK_7632304F545317D1 FOREIGN KEY (vehicle_id) REFERENCES vehicles (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE roadside_request_documents ADD CONSTRAINT FK_91B054DADF56CD80 FOREIGN KEY (roadside_request_id) REFERENCES roadside_requests (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE roadside_request_documents ADD CONSTRAINT FK_91B054DAC33F7837 FOREIGN KEY (document_id) REFERENCES documents (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE roadside_requests DROP CONSTRAINT FK_7632304F9395C3F3');
        $this->addSql('ALTER TABLE roadside_requests DROP CONSTRAINT FK_7632304F545317D1');
        $this->addSql('ALTER TABLE roadside_request_documents DROP CONSTRAINT FK_91B054DADF56CD80');
        $this->addSql('ALTER TABLE roadside_request_documents DROP CONSTRAINT FK_91B054DAC33F7837');
        $this->addSql('DROP TABLE roadside_requests');
        $this->addSql('DROP TABLE roadside_request_documents');
    }
}
