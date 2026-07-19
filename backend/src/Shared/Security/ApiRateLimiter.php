<?php

declare(strict_types=1);

namespace App\Shared\Security;

use App\Identity\Domain\User;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;

/**
 * P1-04: limitele configurate în framework.rate_limiter chiar se aplică.
 * Cheia = utilizator + IP, deci limita unui client nu îl afectează pe altul.
 * Peste limită → 429 cu antet Retry-After (păstrat de ApiExceptionListener).
 */
final class ApiRateLimiter
{
    public function __construct(
        private readonly RateLimiterFactory $messagesLimiter,
        private readonly RateLimiterFactory $uploadLimiter,
    ) {
    }

    /** Mesaje și conversații (client + admin). */
    public function checkMessages(Request $request, ?User $user): void
    {
        $this->check($this->messagesLimiter, 'msg', $request, $user);
    }

    /** Încărcări de fișiere. */
    public function checkUpload(Request $request, ?User $user): void
    {
        $this->check($this->uploadLimiter, 'up', $request, $user);
    }

    private function check(RateLimiterFactory $factory, string $prefix, Request $request, ?User $user): void
    {
        $key = sprintf('%s|%s|%s', $prefix, $user !== null ? (string) $user->id() : 'anon', $request->getClientIp() ?? '0');
        $limit = $factory->create($key)->consume();
        if (!$limit->isAccepted()) {
            $retryAfter = max(1, $limit->getRetryAfter()->getTimestamp() - time());
            throw new TooManyRequestsHttpException($retryAfter, 'Prea multe cereri — încercați din nou în câteva momente.');
        }
    }
}
