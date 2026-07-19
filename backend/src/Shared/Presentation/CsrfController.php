<?php

declare(strict_types=1);

namespace App\Shared\Presentation;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Emite tokenul CSRF: răspunsul acestei rute primește cookie-ul `bcsc_csrf`
 * (setat de CsrfProtectionSubscriber) pe care frontendul îl retrimite în
 * antetul X-CSRF-Token la fiecare cerere modificatoare.
 */
final class CsrfController extends AbstractController
{
    #[Route('/api/csrf', name: 'api_csrf', methods: ['GET'])]
    public function issue(): JsonResponse
    {
        return $this->json(['ok' => true]);
    }
}
