<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Blocul 4 (pilot-readiness) — stări reale de livrare a notificărilor.
 * Coloane noi pe `notifications`: status, dedup_key, attempts, last_attempt_at,
 * provider, failure_reason, sent_by_id. Rândurile existente cu sent_at devin
 * SENT; restul PENDING. NU se șterge nicio dată.
 */
final class Version20260721110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'notifications: stări reale de livrare (status/attempts/provider/... + sent_by) (Blocul 4)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE notifications ADD status VARCHAR(32) DEFAULT 'PENDING' NOT NULL");
        $this->addSql('ALTER TABLE notifications ADD dedup_key VARCHAR(191) DEFAULT NULL');
        $this->addSql('ALTER TABLE notifications ADD attempts INT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE notifications ADD last_attempt_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE notifications ADD provider VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE notifications ADD failure_reason TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE notifications ADD sent_by_id CHAR(36) DEFAULT NULL');

        // Backfill: notificările deja marcate „trimise" (sent_at) devin SENT.
        $this->addSql("UPDATE notifications SET status = 'SENT' WHERE sent_at IS NOT NULL");

        $this->addSql('CREATE INDEX ix_notif_dedup ON notifications (dedup_key)');
        $this->addSql('ALTER TABLE notifications ADD CONSTRAINT FK_notif_sent_by FOREIGN KEY (sent_by_id) REFERENCES users (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        // Numele indexului de FK trebuie să corespundă celui generat de Doctrine din
        // mapping (IDX_<crc32(tabel)><crc32(coloană)>) pentru ca schema:validate să treacă.
        $this->addSql('CREATE INDEX IDX_6000B0D3A45BB98C ON notifications (sent_by_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE notifications DROP CONSTRAINT FK_notif_sent_by');
        $this->addSql('DROP INDEX IDX_6000B0D3A45BB98C');
        $this->addSql('DROP INDEX ix_notif_dedup');
        $this->addSql('ALTER TABLE notifications DROP status');
        $this->addSql('ALTER TABLE notifications DROP dedup_key');
        $this->addSql('ALTER TABLE notifications DROP attempts');
        $this->addSql('ALTER TABLE notifications DROP last_attempt_at');
        $this->addSql('ALTER TABLE notifications DROP provider');
        $this->addSql('ALTER TABLE notifications DROP failure_reason');
        $this->addSql('ALTER TABLE notifications DROP sent_by_id');
    }
}
