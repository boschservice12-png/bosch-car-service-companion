<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Aliniere la specificația funcțională a mașinilor de stări + câmpuri noi:
 *  - roadside/mobility/damage: redenumirea stărilor la valorile din specificație;
 *  - mobility: tipul RIDE_HOME devine PERSON_TRANSPORT (+ ACCOMMODATION nou);
 *  - damage_claims: coloana missing_documents (lista documentelor cerute) și
 *    lărgirea coloanei status (DOCUMENTS_MISSING > 16 caractere);
 *  - tax_items: paid_amount_bani pentru plăți parțiale (PARTIALLY_PAID).
 */
final class Version20260719163614 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Stări conform specificației (roadside/mobility/damage/tax) + missing_documents + paid_amount_bani';
    }

    public function up(Schema $schema): void
    {
        // Schimbări de schemă.
        $this->addSql('ALTER TABLE damage_claims ADD missing_documents JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE damage_claims ALTER status TYPE VARCHAR(24)');
        $this->addSql('ALTER TABLE tax_items ADD paid_amount_bani INT DEFAULT NULL');

        // Remaparea datelor existente la stările din specificație.
        $this->addSql("UPDATE roadside_requests SET status = 'SUBMITTED' WHERE status = 'NEW'");
        $this->addSql("UPDATE roadside_requests SET status = 'COMPLETED' WHERE status = 'RESOLVED'");

        $this->addSql("UPDATE mobility_requests SET status = 'SUBMITTED' WHERE status = 'NEW'");
        $this->addSql("UPDATE mobility_requests SET status = 'CONFIRMED' WHERE status = 'APPROVED'");
        $this->addSql("UPDATE mobility_requests SET status = 'COMPLETED' WHERE status = 'PROVIDED'");
        $this->addSql("UPDATE mobility_requests SET status = 'UNAVAILABLE' WHERE status = 'DECLINED'");
        $this->addSql("UPDATE mobility_requests SET type = 'PERSON_TRANSPORT' WHERE type = 'RIDE_HOME'");

        $this->addSql("UPDATE damage_claims SET status = 'SUBMITTED' WHERE status = 'NEW'");
        $this->addSql("UPDATE damage_claims SET status = 'IN_REVIEW' WHERE status = 'IN_PROGRESS'");
        // Specificația nu are CANCELLED pentru dosare — anulările devin CLOSED.
        $this->addSql("UPDATE damage_claims SET status = 'CLOSED' WHERE status = 'CANCELLED'");

        // Taxele deja plătite primesc paid_amount = suma integrală.
        $this->addSql("UPDATE tax_items SET paid_amount_bani = amount_bani WHERE status = 'PAID'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE roadside_requests SET status = 'NEW' WHERE status IN ('SUBMITTED', 'VALIDATED')");
        $this->addSql("UPDATE roadside_requests SET status = 'RESOLVED' WHERE status IN ('IN_PROGRESS', 'COMPLETED')");

        $this->addSql("UPDATE mobility_requests SET status = 'NEW' WHERE status IN ('SUBMITTED', 'IN_REVIEW', 'CONTACTED')");
        $this->addSql("UPDATE mobility_requests SET status = 'APPROVED' WHERE status = 'CONFIRMED'");
        $this->addSql("UPDATE mobility_requests SET status = 'PROVIDED' WHERE status = 'COMPLETED'");
        $this->addSql("UPDATE mobility_requests SET status = 'DECLINED' WHERE status = 'UNAVAILABLE'");
        $this->addSql("UPDATE mobility_requests SET type = 'RIDE_HOME' WHERE type IN ('PERSON_TRANSPORT', 'ACCOMMODATION')");

        $this->addSql("UPDATE damage_claims SET status = 'NEW' WHERE status = 'SUBMITTED'");
        $this->addSql("UPDATE damage_claims SET status = 'IN_PROGRESS' WHERE status IN ('DOCUMENTS_MISSING', 'IN_REVIEW', 'CONTACTED', 'FILE_OPENED')");

        $this->addSql('ALTER TABLE damage_claims DROP missing_documents');
        $this->addSql('ALTER TABLE damage_claims ALTER status TYPE VARCHAR(16)');
        $this->addSql('ALTER TABLE tax_items DROP paid_amount_bani');
    }
}
