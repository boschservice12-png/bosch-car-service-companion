<?php

declare(strict_types=1);

namespace App\Tests\Integration\Vehicle;

use App\Customer\Domain\CustomerProfile;
use App\Identity\Domain\User;
use App\Vehicle\Domain\Vehicle;
use App\Vehicle\Domain\Vin;
use App\Vehicle\Infrastructure\DoctrineVehicleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Test de autorizare la nivel de obiect: un client NU trebuie să fie recunoscut
 * ca proprietar al vehiculului altui client. Necesită bază de date (rulează în CI).
 *
 * @group integration
 */
final class VehicleAuthorizationTest extends KernelTestCase
{
    public function testClientIsNotOwnerOfAnotherClientsVehicle(): void
    {
        self::bootKernel();
        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $ownerUser = new User('owner-'.uniqid().'@example.test');
        $owner = new CustomerProfile($ownerUser, 'Ana', 'Owner');
        $otherUser = new User('other-'.uniqid().'@example.test');
        $other = new CustomerProfile($otherUser, 'Bogdan', 'Other');

        $vehicle = new Vehicle(new Vin('WBA3A5C50EF123456'), 'MS01ABC');

        $em->persist($ownerUser);
        $em->persist($owner);
        $em->persist($otherUser);
        $em->persist($other);
        $em->persist($vehicle);
        $em->flush();

        $repo = new DoctrineVehicleRepository($em);
        $repo->assignOwner($vehicle, $owner);

        self::assertTrue($repo->isActiveOwner($vehicle, $owner), 'Proprietarul trebuie recunoscut.');
        self::assertFalse($repo->isActiveOwner($vehicle, $other), 'Alt client NU trebuie să aibă acces.');
    }
}
