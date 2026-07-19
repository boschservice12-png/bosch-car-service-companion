<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * P0-02: constrângeri la nivel de bază de date — VIN unic între vehiculele
 * active și cel mult UN proprietar activ per vehicul. Migrația se oprește
 * controlat dacă există deja duplicate (acestea trebuie curățate manual).
 */
final class Version20260720010000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Vehicule: index unic parțial pe VIN activ + un singur proprietar activ per vehicul (P0-02)';
    }

    public function up(Schema $schema): void
    {
        $dupVins = $this->connection->fetchFirstColumn(
            'SELECT vin FROM vehicles WHERE deleted_at IS NULL GROUP BY vin HAVING COUNT(*) > 1',
        );
        $this->abortIf(
            $dupVins !== [],
            'VIN-uri duplicate între vehiculele active — curățați întâi datele: '.implode(', ', $dupVins),
        );

        $dupOwners = $this->connection->fetchFirstColumn(
            'SELECT vehicle_id FROM vehicle_ownerships WHERE active = true GROUP BY vehicle_id HAVING COUNT(*) > 1',
        );
        $this->abortIf(
            $dupOwners !== [],
            'Vehicule cu mai mulți proprietari activi — curățați întâi datele: '.implode(', ', $dupOwners),
        );

        $this->addSql('CREATE UNIQUE INDEX ux_vehicles_vin_active ON vehicles (vin) WHERE (deleted_at IS NULL)');
        $this->addSql('CREATE UNIQUE INDEX ux_vehicle_active_owner ON vehicle_ownerships (vehicle_id) WHERE (active = true)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX ux_vehicles_vin_active');
        $this->addSql('DROP INDEX ux_vehicle_active_owner');
    }
}
