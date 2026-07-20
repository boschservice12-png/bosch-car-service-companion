<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260720040000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'P1-06: momentul cererii de ștergere GDPR pe conturile de utilizator';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD deletion_requested_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP deletion_requested_at');
    }
}
