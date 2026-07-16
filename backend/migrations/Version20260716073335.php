<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Istoric de service: tabelele service_records (cu corecții auto-referite și
 * stare ciornă/publicat) și service_record_documents (documente/foto atașate).
 */
final class Version20260716073335 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Istoric de service: service_records + service_record_documents';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE service_records (id CHAR(36) NOT NULL, service_date DATE DEFAULT NULL, odometer_km INT DEFAULT NULL, work_type VARCHAR(120) DEFAULT NULL, work_description TEXT DEFAULT NULL, parts_summary TEXT DEFAULT NULL, labor_bani INT NOT NULL, total_bani INT NOT NULL, warranty VARCHAR(255) DEFAULT NULL, status VARCHAR(16) NOT NULL, published_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, vehicle_id CHAR(36) NOT NULL, correction_of_id CHAR(36) DEFAULT NULL, created_by CHAR(36) DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_53190ADAFE3DD8A9 ON service_records (correction_of_id)');
        $this->addSql('CREATE INDEX IDX_53190ADADE12AB56 ON service_records (created_by)');
        $this->addSql('CREATE INDEX ix_service_records_vehicle ON service_records (vehicle_id)');
        $this->addSql('CREATE INDEX ix_service_records_status ON service_records (status)');
        $this->addSql('CREATE TABLE service_record_documents (service_record_id CHAR(36) NOT NULL, document_id CHAR(36) NOT NULL, PRIMARY KEY (service_record_id, document_id))');
        $this->addSql('CREATE INDEX IDX_9F64E5FD156C4F46 ON service_record_documents (service_record_id)');
        $this->addSql('CREATE INDEX IDX_9F64E5FDC33F7837 ON service_record_documents (document_id)');
        $this->addSql('ALTER TABLE service_records ADD CONSTRAINT FK_53190ADA545317D1 FOREIGN KEY (vehicle_id) REFERENCES vehicles (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE service_records ADD CONSTRAINT FK_53190ADAFE3DD8A9 FOREIGN KEY (correction_of_id) REFERENCES service_records (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE service_records ADD CONSTRAINT FK_53190ADADE12AB56 FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE service_record_documents ADD CONSTRAINT FK_9F64E5FD156C4F46 FOREIGN KEY (service_record_id) REFERENCES service_records (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE service_record_documents ADD CONSTRAINT FK_9F64E5FDC33F7837 FOREIGN KEY (document_id) REFERENCES documents (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE service_records DROP CONSTRAINT FK_53190ADA545317D1');
        $this->addSql('ALTER TABLE service_records DROP CONSTRAINT FK_53190ADAFE3DD8A9');
        $this->addSql('ALTER TABLE service_records DROP CONSTRAINT FK_53190ADADE12AB56');
        $this->addSql('ALTER TABLE service_record_documents DROP CONSTRAINT FK_9F64E5FD156C4F46');
        $this->addSql('ALTER TABLE service_record_documents DROP CONSTRAINT FK_9F64E5FDC33F7837');
        $this->addSql('DROP TABLE service_records');
        $this->addSql('DROP TABLE service_record_documents');
    }
}
