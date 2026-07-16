<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260716010812 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE deadline_notifications (id CHAR(36) NOT NULL, threshold_days INT NOT NULL, channel VARCHAR(16) NOT NULL, sent_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, deadline_id CHAR(36) NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_F244B9A73EA0AF8 ON deadline_notifications (deadline_id)');
        $this->addSql('CREATE UNIQUE INDEX uq_deadline_threshold_channel ON deadline_notifications (deadline_id, threshold_days, channel)');
        $this->addSql('CREATE TABLE vehicle_deadlines (id CHAR(36) NOT NULL, type VARCHAR(32) NOT NULL, valid_from DATE DEFAULT NULL, expires_at DATE DEFAULT NULL, source VARCHAR(16) NOT NULL, verified_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, note TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, vehicle_id CHAR(36) NOT NULL, document_id CHAR(36) DEFAULT NULL, verified_by CHAR(36) DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_BFB55E1DC33F7837 ON vehicle_deadlines (document_id)');
        $this->addSql('CREATE INDEX IDX_BFB55E1D60D6A0BF ON vehicle_deadlines (verified_by)');
        $this->addSql('CREATE INDEX ix_deadlines_vehicle ON vehicle_deadlines (vehicle_id)');
        $this->addSql('CREATE INDEX ix_deadlines_expires ON vehicle_deadlines (expires_at)');
        $this->addSql('ALTER TABLE deadline_notifications ADD CONSTRAINT FK_F244B9A73EA0AF8 FOREIGN KEY (deadline_id) REFERENCES vehicle_deadlines (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE vehicle_deadlines ADD CONSTRAINT FK_BFB55E1D545317D1 FOREIGN KEY (vehicle_id) REFERENCES vehicles (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE vehicle_deadlines ADD CONSTRAINT FK_BFB55E1DC33F7837 FOREIGN KEY (document_id) REFERENCES documents (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE vehicle_deadlines ADD CONSTRAINT FK_BFB55E1D60D6A0BF FOREIGN KEY (verified_by) REFERENCES users (id) ON DELETE SET NULL NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE deadline_notifications DROP CONSTRAINT FK_F244B9A73EA0AF8');
        $this->addSql('ALTER TABLE vehicle_deadlines DROP CONSTRAINT FK_BFB55E1D545317D1');
        $this->addSql('ALTER TABLE vehicle_deadlines DROP CONSTRAINT FK_BFB55E1DC33F7837');
        $this->addSql('ALTER TABLE vehicle_deadlines DROP CONSTRAINT FK_BFB55E1D60D6A0BF');
        $this->addSql('DROP TABLE deadline_notifications');
        $this->addSql('DROP TABLE vehicle_deadlines');
    }
}
