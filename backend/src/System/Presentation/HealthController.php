<?php

declare(strict_types=1);

namespace App\System\Presentation;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class HealthController extends AbstractController
{
    #[Route('/api/health', name: 'api_health', methods: ['GET'])]
    public function live(): JsonResponse
    {
        return $this->json(['status' => 'ok']);
    }

    #[Route('/api/health/ready', name: 'api_health_ready', methods: ['GET'])]
    public function ready(Connection $connection): JsonResponse
    {
        $checks = ['database' => false];
        try {
            $connection->executeQuery('SELECT 1');
            $checks['database'] = true;
        } catch (\Throwable) {
            // rămâne false
        }

        $ready = !in_array(false, $checks, true);

        return $this->json(
            ['status' => $ready ? 'ready' : 'degraded', 'checks' => $checks],
            $ready ? 200 : 503,
        );
    }
}
