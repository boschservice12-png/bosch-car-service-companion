<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Blocul 5 (pilot-readiness) — anti-replay TOTP: reținem per utilizator ultimul
 * pas de timp TOTP acceptat (contorul RFC 6238), ca un cod deja folosit să nu
 * mai fie acceptat. Coloană nouă, nullable — nu atinge date existente.
 */
final class Version20260721100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'users.last_totp_step pentru protecția anti-replay TOTP (Blocul 5)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD last_totp_step BIGINT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP last_totp_step');
    }
}
