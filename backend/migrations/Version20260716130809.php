<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260716130809 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Taxe și impozite: tax_items + tax_item_documents';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE tax_items (id CHAR(36) NOT NULL, year INT NOT NULL, type VARCHAR(20) NOT NULL, amount_bani INT NOT NULL, due_date DATE DEFAULT NULL, status VARCHAR(16) NOT NULL, paid_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, note TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, customer_id CHAR(36) NOT NULL, vehicle_id CHAR(36) DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_52641C6C545317D1 ON tax_items (vehicle_id)');
        $this->addSql('CREATE INDEX ix_tax_customer ON tax_items (customer_id)');
        $this->addSql('CREATE INDEX ix_tax_status ON tax_items (status)');
        $this->addSql('CREATE TABLE tax_item_documents (tax_item_id CHAR(36) NOT NULL, document_id CHAR(36) NOT NULL, PRIMARY KEY (tax_item_id, document_id))');
        $this->addSql('CREATE INDEX IDX_582D06085327F254 ON tax_item_documents (tax_item_id)');
        $this->addSql('CREATE INDEX IDX_582D0608C33F7837 ON tax_item_documents (document_id)');
        $this->addSql('ALTER TABLE tax_items ADD CONSTRAINT FK_52641C6C9395C3F3 FOREIGN KEY (customer_id) REFERENCES users (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE tax_items ADD CONSTRAINT FK_52641C6C545317D1 FOREIGN KEY (vehicle_id) REFERENCES vehicles (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE tax_item_documents ADD CONSTRAINT FK_582D06085327F254 FOREIGN KEY (tax_item_id) REFERENCES tax_items (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE tax_item_documents ADD CONSTRAINT FK_582D0608C33F7837 FOREIGN KEY (document_id) REFERENCES documents (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE tax_items DROP CONSTRAINT FK_52641C6C9395C3F3');
        $this->addSql('ALTER TABLE tax_items DROP CONSTRAINT FK_52641C6C545317D1');
        $this->addSql('ALTER TABLE tax_item_documents DROP CONSTRAINT FK_582D06085327F254');
        $this->addSql('ALTER TABLE tax_item_documents DROP CONSTRAINT FK_582D0608C33F7837');
        $this->addSql('DROP TABLE tax_items');
        $this->addSql('DROP TABLE tax_item_documents');
    }
}
