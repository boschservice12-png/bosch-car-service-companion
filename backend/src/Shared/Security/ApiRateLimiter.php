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
        private readonly RateLimiterFactory $registerLimiter,
        private readonly RateLimiterFactory $twofaLimiter,
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

    /**
     * Înregistrare / revendicare de cont. Cheia este DOAR emailul țintă: dovada
     * revendicării (numărul de înmatriculare) nu trebuie să fie ghicibilă prin
     * forță brută nici de pe multe IP-uri diferite.
     */
    public function checkRegister(string $email): void
    {
        $limit = $this->registerLimiter->create('reg|'.mb_strtolower(trim($email)))->consume();
        if (!$limit->isAccepted()) {
            $retryAfter = max(1, $limit->getRetryAfter()->getTimestamp() - time());
            throw new TooManyRequestsHttpException($retryAfter, 'Prea multe încercări pentru acest email — încercați din nou mai târziu.');
        }
    }

    /** Rutele 2FA (codul TOTP are 6 cifre — nu trebuie să fie ghicibil). */
    public function checkTwoFactor(Request $request, User $user, string $action): void
    {
        $this->check($this->twofaLimiter, '2fa-'.$action, $request, $user);
    }

    /** La o verificare 2FA reușită contorul se golește — utilizatorul legitim nu acumulează eșecuri. */
    public function resetTwoFactor(Request $request, User $user, string $action): void
    {
        $this->twofaLimiter->create($this->key('2fa-'.$action, $request, $user))->reset();
    }

    private function check(RateLimiterFactory $factory, string $prefix, Request $request, ?User $user): void
    {
        $limit = $factory->create($this->key($prefix, $request, $user))->consume();
        if (!$limit->isAccepted()) {
            $retryAfter = max(1, $limit->getRetryAfter()->getTimestamp() - time());
            throw new TooManyRequestsHttpException($retryAfter, 'Prea multe cereri — încercați din nou în câteva momente.');
        }
    }

    private function key(string $prefix, Request $request, ?User $user): string
    {
        return sprintf('%s|%s|%s', $prefix, $user !== null ? (string) $user->id() : 'anon', $request->getClientIp() ?? '0');
    }
}
