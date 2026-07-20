<?php

declare(strict_types=1);

namespace App\System\Presentation;

use App\System\Application\ReadinessChecker;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Blocul 6 — liveness și readiness sunt SEPARATE:
 *  - `/api/health` (liveness): procesul trăiește și poate răspunde. NU atinge
 *    dependențe externe → nu declanșează restart-uri în lanț dacă baza pică.
 *  - `/api/health/ready` (readiness): aplicația poate SERVI în siguranță o cerere
 *    reală. O dependență critică picată → 503, ca orchestratorul să scoată
 *    instanța din rotație. Nu arătăm niciodată „ready" cu o dependență critică jos.
 */
final class HealthController extends AbstractController
{
    #[Route('/api/health', name: 'api_health', methods: ['GET'])]
    public function live(): JsonResponse
    {
        return $this->json(['status' => 'ok']);
    }

    #[Route('/api/health/ready', name: 'api_health_ready', methods: ['GET'])]
    public function ready(ReadinessChecker $readiness): JsonResponse
    {
        $result = $readiness->check();

        return $this->json($result, $result['ready'] ? 200 : 503);
    }
}
