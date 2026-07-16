<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Comunicare și cereri de ofertă: conversations (fir client↔service, cu flux de
 * ofertă), messages și message_attachments (documente/foto atașate mesajelor).
 */
final class Version20260716081738 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Comunicare + cereri de ofertă: conversations + messages + message_attachments';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE conversations (id CHAR(36) NOT NULL, type VARCHAR(16) NOT NULL, subject VARCHAR(200) NOT NULL, status VARCHAR(16) NOT NULL, quote_amount_bani INT DEFAULT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, last_message_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, customer_id CHAR(36) NOT NULL, vehicle_id CHAR(36) DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_C2521BF1545317D1 ON conversations (vehicle_id)');
        $this->addSql('CREATE INDEX ix_conversations_customer ON conversations (customer_id)');
        $this->addSql('CREATE INDEX ix_conversations_status ON conversations (status)');
        $this->addSql('CREATE TABLE messages (id CHAR(36) NOT NULL, author_role VARCHAR(16) NOT NULL, body TEXT NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, conversation_id CHAR(36) NOT NULL, sender_id CHAR(36) DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_DB021E96F624B39D ON messages (sender_id)');
        $this->addSql('CREATE INDEX ix_messages_conversation ON messages (conversation_id)');
        $this->addSql('CREATE TABLE message_attachments (message_id CHAR(36) NOT NULL, document_id CHAR(36) NOT NULL, PRIMARY KEY (message_id, document_id))');
        $this->addSql('CREATE INDEX IDX_27BBA42F537A1329 ON message_attachments (message_id)');
        $this->addSql('CREATE INDEX IDX_27BBA42FC33F7837 ON message_attachments (document_id)');
        $this->addSql('ALTER TABLE conversations ADD CONSTRAINT FK_C2521BF19395C3F3 FOREIGN KEY (customer_id) REFERENCES users (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE conversations ADD CONSTRAINT FK_C2521BF1545317D1 FOREIGN KEY (vehicle_id) REFERENCES vehicles (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE messages ADD CONSTRAINT FK_DB021E969AC0396 FOREIGN KEY (conversation_id) REFERENCES conversations (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE messages ADD CONSTRAINT FK_DB021E96F624B39D FOREIGN KEY (sender_id) REFERENCES users (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE message_attachments ADD CONSTRAINT FK_27BBA42F537A1329 FOREIGN KEY (message_id) REFERENCES messages (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE message_attachments ADD CONSTRAINT FK_27BBA42FC33F7837 FOREIGN KEY (document_id) REFERENCES documents (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE conversations DROP CONSTRAINT FK_C2521BF19395C3F3');
        $this->addSql('ALTER TABLE conversations DROP CONSTRAINT FK_C2521BF1545317D1');
        $this->addSql('ALTER TABLE messages DROP CONSTRAINT FK_DB021E969AC0396');
        $this->addSql('ALTER TABLE messages DROP CONSTRAINT FK_DB021E96F624B39D');
        $this->addSql('ALTER TABLE message_attachments DROP CONSTRAINT FK_27BBA42F537A1329');
        $this->addSql('ALTER TABLE message_attachments DROP CONSTRAINT FK_27BBA42FC33F7837');
        $this->addSql('DROP TABLE conversations');
        $this->addSql('DROP TABLE messages');
        $this->addSql('DROP TABLE message_attachments');
    }
}
