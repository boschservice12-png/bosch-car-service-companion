<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260716110214 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Dosare de daună: damage_claims + damage_claim_documents';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE damage_claims (id CHAR(36) NOT NULL, incident_date DATE DEFAULT NULL, incident_location VARCHAR(500) DEFAULT NULL, incident_description TEXT NOT NULL, insurer VARCHAR(200) DEFAULT NULL, policy_number VARCHAR(100) DEFAULT NULL, status VARCHAR(16) NOT NULL, note TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, customer_id CHAR(36) NOT NULL, vehicle_id CHAR(36) DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_AD590BAD545317D1 ON damage_claims (vehicle_id)');
        $this->addSql('CREATE INDEX ix_damage_customer ON damage_claims (customer_id)');
        $this->addSql('CREATE INDEX ix_damage_status ON damage_claims (status)');
        $this->addSql('CREATE TABLE damage_claim_documents (damage_claim_id CHAR(36) NOT NULL, document_id CHAR(36) NOT NULL, PRIMARY KEY (damage_claim_id, document_id))');
        $this->addSql('CREATE INDEX IDX_AC9F466F2156EA1 ON damage_claim_documents (damage_claim_id)');
        $this->addSql('CREATE INDEX IDX_AC9F466FC33F7837 ON damage_claim_documents (document_id)');
        $this->addSql('ALTER TABLE damage_claims ADD CONSTRAINT FK_AD590BAD9395C3F3 FOREIGN KEY (customer_id) REFERENCES users (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE damage_claims ADD CONSTRAINT FK_AD590BAD545317D1 FOREIGN KEY (vehicle_id) REFERENCES vehicles (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE damage_claim_documents ADD CONSTRAINT FK_AC9F466F2156EA1 FOREIGN KEY (damage_claim_id) REFERENCES damage_claims (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE damage_claim_documents ADD CONSTRAINT FK_AC9F466FC33F7837 FOREIGN KEY (document_id) REFERENCES documents (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE damage_claims DROP CONSTRAINT FK_AD590BAD9395C3F3');
        $this->addSql('ALTER TABLE damage_claims DROP CONSTRAINT FK_AD590BAD545317D1');
        $this->addSql('ALTER TABLE damage_claim_documents DROP CONSTRAINT FK_AC9F466F2156EA1');
        $this->addSql('ALTER TABLE damage_claim_documents DROP CONSTRAINT FK_AC9F466FC33F7837');
        $this->addSql('DROP TABLE damage_claims');
        $this->addSql('DROP TABLE damage_claim_documents');
    }
}
