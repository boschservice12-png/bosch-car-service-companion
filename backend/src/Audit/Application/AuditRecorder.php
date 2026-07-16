<?php

declare(strict_types=1);

namespace App\Audit\Application;

use App\Audit\Domain\AuditLog;
use App\Identity\Domain\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Uid\Uuid;

/**
 * Punct unic de scriere în audit. Nu loghează niciodată parole, token-uri sau
 * conținut sensibil — doar metadate before/after ale operațiunii.
 */
final class AuditRecorder
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Security $security,
        private readonly RequestStack $requestStack,
    ) {
    }

    /**
     * @param array<string, mixed>|null $before
     * @param array<string, mixed>|null $after
     */
    public function record(
        string $action,
        ?string $entityType = null,
        ?string $entityId = null,
        ?array $before = null,
        ?array $after = null,
    ): void {
        $user = $this->security->getUser();
        $actorId = $user instanceof User ? $user->id() : null;

        $log = new AuditLog(
            $actorId,
            $action,
            $entityType,
            $entityId !== null ? Uuid::fromString($entityId) : null,
            $before,
            $after,
            $this->requestStack->getCurrentRequest()?->getClientIp(),
        );

        $this->em->persist($log);
        $this->em->flush();
    }
}
