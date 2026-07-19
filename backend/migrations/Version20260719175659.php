<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260719175659 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Istoric service: numărul comenzii de lucru (work_order_number) — folosit și la importul din Excel';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE service_records ADD work_order_number VARCHAR(60) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE service_records DROP work_order_number');
    }
}
