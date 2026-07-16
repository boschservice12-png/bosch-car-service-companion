<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Mobilitate: tabelul mobility_requests (mașină de înlocuire, taxi, transport etc.).
 */
final class Version20260716095225 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Mobilitate: mobility_requests';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE mobility_requests (id CHAR(36) NOT NULL, type VARCHAR(20) NOT NULL, details TEXT NOT NULL, preferred_date DATE DEFAULT NULL, status VARCHAR(16) NOT NULL, note TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, customer_id CHAR(36) NOT NULL, vehicle_id CHAR(36) DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_1755687545317D1 ON mobility_requests (vehicle_id)');
        $this->addSql('CREATE INDEX ix_mobility_customer ON mobility_requests (customer_id)');
        $this->addSql('CREATE INDEX ix_mobility_status ON mobility_requests (status)');
        $this->addSql('ALTER TABLE mobility_requests ADD CONSTRAINT FK_17556879395C3F3 FOREIGN KEY (customer_id) REFERENCES users (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE mobility_requests ADD CONSTRAINT FK_1755687545317D1 FOREIGN KEY (vehicle_id) REFERENCES vehicles (id) ON DELETE SET NULL NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE mobility_requests DROP CONSTRAINT FK_17556879395C3F3');
        $this->addSql('ALTER TABLE mobility_requests DROP CONSTRAINT FK_1755687545317D1');
        $this->addSql('DROP TABLE mobility_requests');
    }
}
