<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260720020000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'P0-06: coduri de rezervă 2FA (doar hash-uri) pe conturile de utilizator';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD totp_recovery_codes JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP totp_recovery_codes');
    }
}
