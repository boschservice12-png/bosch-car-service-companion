<?php

declare(strict_types=1);

namespace App\Vehicle\Application;

use App\Audit\Application\AuditRecorder;
use App\Identity\Domain\User;
use App\Shared\Presentation\ValidationFailedException;
use App\Vehicle\Domain\Vehicle;
use App\Vehicle\Domain\VehicleActivationToken;
use App\Vehicle\Domain\VehicleActivationTokenRepository;
use App\Vehicle\Domain\VehicleOwnership;
use App\Vehicle\Domain\VehicleRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Blocul 3 — activarea sigură a unui vehicul.
 *
 * Fluxul: adminul EMITE un cod (entropie mare, stocat doar ca hash SHA-256,
 * cu lejare); clientul îl FOLOSEȘTE o singură dată și devine proprietar activ.
 * Numărul de înmatriculare / VIN NU sunt dovadă (nu sunt secrete).
 *
 * La activare, dacă vehiculul are deja alt proprietar activ, are loc o schimbare
 * de proprietar: legătura veche se închide, cea nouă se creează — totul într-o
 * tranzacție. Istoricul de service NU se șterge.
 */
final class VehicleActivationService
{
    private const TTL_DAYS = 7;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly VehicleActivationTokenRepository $tokens,
        private readonly VehicleRepository $vehicles,
        private readonly AuditRecorder $audit,
    ) {
    }

    /**
     * Emite un cod nou pentru vehicul (revocând codurile încă valabile).
     * Întoarce codul în CLAR — se afișează O SINGURĂ DATĂ; în baza de date
     * rămâne doar hash-ul.
     */
    public function issue(Vehicle $vehicle, ?User $admin, int $ttlDays = self::TTL_DAYS): string
    {
        $now = new \DateTimeImmutable();
        foreach ($this->tokens->findLiveForVehicle($vehicle, $now) as $live) {
            $live->revoke();
        }

        $plain = strtoupper(bin2hex(random_bytes(16))); // 128 biți
        $token = new VehicleActivationToken(
            $vehicle,
            $this->hash($plain),
            $admin,
            $now->modify(sprintf('+%d days', $ttlDays)),
        );
        $this->tokens->save($token);

        $this->audit->record('vehicle.activation_issued', 'Vehicle', (string) $vehicle->id(), null, [
            'tokenId' => (string) $token->id(),
            'expiresAt' => $token->expiresAt()->format(\DateTimeInterface::ATOM),
        ]);

        return $this->format($plain);
    }

    /** Revocă toate codurile încă valabile ale vehiculului. */
    public function revoke(Vehicle $vehicle, ?User $admin): int
    {
        $now = new \DateTimeImmutable();
        $live = $this->tokens->findLiveForVehicle($vehicle, $now);
        foreach ($live as $token) {
            $token->revoke();
        }
        $this->em->flush();
        if ($live !== []) {
            $this->audit->record('vehicle.activation_revoked', 'Vehicle', (string) $vehicle->id(), null, [
                'count' => \count($live),
            ]);
        }

        return \count($live);
    }

    /**
     * Activează un vehicul cu un cod. La succes clientul devine proprietar
     * activ (înlocuind eventualul proprietar anterior) și codul se consumă.
     * Orice cod invalid / expirat / folosit / revocat → același mesaj generic.
     */
    public function activate(string $plainToken, User $user): Vehicle
    {
        $now = new \DateTimeImmutable();
        $token = $this->tokens->findByHash($this->hash($this->normalize($plainToken)));

        if ($token !== null) {
            $token->registerAttempt();
            $this->em->flush();
        }
        if ($token === null || !$token->isLive($now)) {
            throw ValidationFailedException::fromArray([
                'token' => ['Cod de activare invalid sau expirat.'],
            ]);
        }

        $profile = $user->customerProfile();
        if ($profile === null) {
            throw new \DomainException('Contul nu are un profil de client.');
        }

        $vehicle = $token->vehicle();
        $connection = $this->em->getConnection();
        $connection->beginTransaction();
        try {
            /** @var VehicleOwnership|null $current */
            $current = $this->em->getRepository(VehicleOwnership::class)
                ->findOneBy(['vehicle' => $vehicle, 'active' => true]);

            if ($current === null) {
                // Vehicul fără proprietar activ → creăm legătura.
                $this->vehicles->assignOwner($vehicle, $profile);
            } elseif (!$current->customerProfile()->id()->equals($profile->id())) {
                // Schimbare de proprietar: fostul pierde accesul, noul îl primește.
                // Reatribuim legătura activă (un singur rând per vehicul); schimbarea
                // e consemnată în audit. Istoricul de service NU se atinge.
                $current->transferTo($profile);
            }
            // (dacă e deja proprietarul → idempotent, nu schimbăm proprietatea)
            $token->markUsed($user);
            $this->em->flush();
            $connection->commit();
        } catch (UniqueConstraintViolationException $e) {
            $connection->rollBack();
            throw new \DomainException('Vehiculul are deja un proprietar activ — reîncercați.');
        } catch (\Throwable $e) {
            $connection->rollBack();
            throw $e;
        }

        $this->audit->record('vehicle.activation_used', 'Vehicle', (string) $vehicle->id(), null, [
            'tokenId' => (string) $token->id(),
            'userId' => (string) $user->id(),
        ]);

        return $vehicle;
    }

    private function hash(string $normalized): string
    {
        return hash('sha256', $normalized);
    }

    private function normalize(string $input): string
    {
        return strtoupper((string) preg_replace('/[^0-9A-Fa-f]/', '', $input));
    }

    /** Grupare în blocuri de 4 pentru citire ușoară. */
    private function format(string $hex): string
    {
        return implode('-', str_split($hex, 4));
    }
}
