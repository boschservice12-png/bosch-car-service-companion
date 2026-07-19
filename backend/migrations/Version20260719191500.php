<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260719191500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Taxe: fără încărcare de fișiere (bon fiscal etc.) — se elimină legătura taxă–documente';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP TABLE tax_item_documents');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE TABLE tax_item_documents (tax_item_id CHAR(36) NOT NULL, document_id CHAR(36) NOT NULL, PRIMARY KEY (tax_item_id, document_id))');
        $this->addSql('CREATE INDEX IDX_582D06085327F254 ON tax_item_documents (tax_item_id)');
        $this->addSql('CREATE INDEX IDX_582D0608C33F7837 ON tax_item_documents (document_id)');
        $this->addSql('ALTER TABLE tax_item_documents ADD CONSTRAINT FK_582D06085327F254 FOREIGN KEY (tax_item_id) REFERENCES tax_items (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE tax_item_documents ADD CONSTRAINT FK_582D0608C33F7837 FOREIGN KEY (document_id) REFERENCES documents (id) ON DELETE CASCADE');
    }
}
