<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260719165921 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Modul QuoteRequest (cereri de ofertă + răspunsuri) + conversații pe stările din specificație';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE quote_requests (id CHAR(36) NOT NULL, mileage INT DEFAULT NULL, symptom_description TEXT NOT NULL, occurrence_conditions TEXT DEFAULT NULL, vehicle_drivable BOOLEAN NOT NULL, warning_lights VARCHAR(200) DEFAULT NULL, preferred_contact_method VARCHAR(20) DEFAULT NULL, preferred_interval VARCHAR(200) DEFAULT NULL, status VARCHAR(24) NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, customer_id CHAR(36) NOT NULL, vehicle_id CHAR(36) DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_91BF7EC0545317D1 ON quote_requests (vehicle_id)');
        $this->addSql('CREATE INDEX ix_quote_request_customer ON quote_requests (customer_id)');
        $this->addSql('CREATE INDEX ix_quote_request_status ON quote_requests (status)');
        $this->addSql('CREATE INDEX ix_quote_request_created ON quote_requests (created_at)');
        $this->addSql('CREATE TABLE quote_responses (id CHAR(36) NOT NULL, message TEXT NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, request_id CHAR(36) NOT NULL, author_id CHAR(36) NOT NULL, document_id CHAR(36) DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_B6BD06EEF675F31B ON quote_responses (author_id)');
        $this->addSql('CREATE INDEX IDX_B6BD06EEC33F7837 ON quote_responses (document_id)');
        $this->addSql('CREATE INDEX ix_quote_response_request ON quote_responses (request_id)');
        $this->addSql('ALTER TABLE quote_requests ADD CONSTRAINT FK_91BF7EC09395C3F3 FOREIGN KEY (customer_id) REFERENCES users (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE quote_requests ADD CONSTRAINT FK_91BF7EC0545317D1 FOREIGN KEY (vehicle_id) REFERENCES vehicles (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE quote_responses ADD CONSTRAINT FK_B6BD06EE427EB8A5 FOREIGN KEY (request_id) REFERENCES quote_requests (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE quote_responses ADD CONSTRAINT FK_B6BD06EEF675F31B FOREIGN KEY (author_id) REFERENCES users (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE quote_responses ADD CONSTRAINT FK_B6BD06EEC33F7837 FOREIGN KEY (document_id) REFERENCES documents (id) ON DELETE SET NULL NOT DEFERRABLE');
        // Conversațiile trec pe stările din specificație; fluxul de ofertă mutat în QuoteRequest.
        $this->addSql("UPDATE conversations SET status = 'WAITING_CLIENT' WHERE status = 'QUOTED'");
        $this->addSql("UPDATE conversations SET status = 'CLOSED' WHERE status IN ('ACCEPTED', 'DECLINED')");
        $this->addSql('ALTER TABLE conversations DROP type');
        $this->addSql('ALTER TABLE conversations DROP quote_amount_bani');
        $this->addSql('ALTER TABLE conversations ALTER status TYPE VARCHAR(20)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE quote_requests DROP CONSTRAINT FK_91BF7EC09395C3F3');
        $this->addSql('ALTER TABLE quote_requests DROP CONSTRAINT FK_91BF7EC0545317D1');
        $this->addSql('ALTER TABLE quote_responses DROP CONSTRAINT FK_B6BD06EE427EB8A5');
        $this->addSql('ALTER TABLE quote_responses DROP CONSTRAINT FK_B6BD06EEF675F31B');
        $this->addSql('ALTER TABLE quote_responses DROP CONSTRAINT FK_B6BD06EEC33F7837');
        $this->addSql('DROP TABLE quote_requests');
        $this->addSql('DROP TABLE quote_responses');
        $this->addSql('ALTER TABLE conversations ADD type VARCHAR(16) NOT NULL');
        $this->addSql('ALTER TABLE conversations ADD quote_amount_bani INT DEFAULT NULL');
        $this->addSql('ALTER TABLE conversations ALTER status TYPE VARCHAR(16)');
    }
}
