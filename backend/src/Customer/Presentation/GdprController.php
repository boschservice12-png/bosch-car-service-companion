<?php

declare(strict_types=1);

namespace App\Customer\Presentation;

use App\Customer\Application\GdprService;
use App\Identity\Domain\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * P1-06 — drepturile GDPR ale clientului: portabilitatea datelor (export
 * JSON) și dreptul la ștergere (cu re-introducerea parolei; contul se
 * blochează imediat, anonimizarea vine după perioada de grație).
 */
final class GdprController extends AbstractController
{
    public function __construct(
        private readonly GdprService $gdpr,
        private readonly UserPasswordHasherInterface $hasher,
    ) {
    }

    #[Route('/api/me/export', name: 'api_me_export', methods: ['GET'])]
    public function export(): JsonResponse
    {
        $user = $this->requireClient();

        $response = $this->json($this->gdpr->export($user));
        $response->headers->set('Content-Disposition', 'attachment; filename="datele-mele.json"');
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }

    #[Route('/api/me/delete', name: 'api_me_delete', methods: ['POST'])]
    public function requestDeletion(Request $request): JsonResponse
    {
        $user = $this->requireClient();

        /** @var array{password?: mixed} $payload */
        $payload = json_decode((string) $request->getContent(), true) ?: [];
        $password = \is_string($payload['password'] ?? null) ? $payload['password'] : '';
        if ($password === '' || !$this->hasher->isPasswordValid($user, $password)) {
            throw new \InvalidArgumentException('Parola introdusă nu este corectă.');
        }

        $this->gdpr->requestDeletion($user);
        // Sesiunea curentă se închide — contul e blocat până la anonimizare
        // (sau până când operatorul anulează cererea în perioada de grație).
        if ($request->hasSession()) {
            $request->getSession()->invalidate();
        }

        return $this->json(['deletionRequested' => true]);
    }

    private function requireClient(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User || $user->isServiceAdmin()) {
            throw $this->createAccessDeniedException('Doar conturile de client au acces la această rută.');
        }

        return $user;
    }
}
