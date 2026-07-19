<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260719172021 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Istoric service: motivul corecției (correction_reason) + starea CORRECTED pe originalele corectate';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE service_records ADD correction_reason TEXT DEFAULT NULL');
        // Originalele care au deja o corecție publicată devin CORRECTED (specificație).
        $this->addSql("UPDATE service_records SET status = 'CORRECTED' WHERE status = 'PUBLISHED' AND id IN (SELECT correction_of_id FROM service_records WHERE correction_of_id IS NOT NULL AND status = 'PUBLISHED')");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE service_records SET status = 'PUBLISHED' WHERE status = 'CORRECTED'");
        $this->addSql('ALTER TABLE service_records DROP correction_reason');
    }
}
