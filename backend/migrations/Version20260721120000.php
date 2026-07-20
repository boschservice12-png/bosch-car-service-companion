<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Blocul 3 (pilot-readiness) — coduri de activare vehicul. Se stochează doar
 * hash-ul codului (SHA-256), niciodată codul în clar. Tabel nou; nu atinge date.
 */
final class Version20260721120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'vehicle_activation_tokens: coduri de activare vehicul (hash-uite, o singură folosință) (Blocul 3)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE vehicle_activation_tokens (
            id CHAR(36) NOT NULL,
            vehicle_id CHAR(36) NOT NULL,
            token_hash VARCHAR(64) NOT NULL,
            created_by_id CHAR(36) DEFAULT NULL,
            created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
            expires_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
            used_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL,
            used_by_id CHAR(36) DEFAULT NULL,
            revoked_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL,
            attempt_count INT DEFAULT 0 NOT NULL,
            last_attempt_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL,
            PRIMARY KEY (id)
        )');
        $this->addSql('CREATE UNIQUE INDEX ux_vat_token_hash ON vehicle_activation_tokens (token_hash)');
        $this->addSql('CREATE INDEX ix_vat_vehicle ON vehicle_activation_tokens (vehicle_id)');
        $this->addSql('CREATE INDEX IDX_vat_created_by ON vehicle_activation_tokens (created_by_id)');
        $this->addSql('CREATE INDEX IDX_vat_used_by ON vehicle_activation_tokens (used_by_id)');
        $this->addSql('ALTER TABLE vehicle_activation_tokens ADD CONSTRAINT FK_vat_vehicle FOREIGN KEY (vehicle_id) REFERENCES vehicles (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE vehicle_activation_tokens ADD CONSTRAINT FK_vat_created_by FOREIGN KEY (created_by_id) REFERENCES users (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE vehicle_activation_tokens ADD CONSTRAINT FK_vat_used_by FOREIGN KEY (used_by_id) REFERENCES users (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE vehicle_activation_tokens');
    }
}
