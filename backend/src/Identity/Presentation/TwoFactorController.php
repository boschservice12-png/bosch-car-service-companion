<?php

declare(strict_types=1);

namespace App\Identity\Presentation;

use App\Audit\Application\AuditRecorder;
use App\Identity\Application\TotpService;
use App\Identity\Domain\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * P0-06 — autentificare în doi factori (TOTP) pentru conturile de service.
 *
 * Flux de înrolare: setup (cu re-introducerea parolei → secret în așteptare +
 * URI pentru QR) → enable (cod valid → activare + 8 coduri de rezervă afișate
 * O SINGURĂ DATĂ, stocate doar ca hash). La fiecare login ulterior sesiunea
 * este „în așteptare" până la verify (cod TOTP sau cod de rezervă) —
 * TwoFactorGuardListener blochează restul API-ului între timp.
 */
final class TwoFactorController extends AbstractController
{
    public const SESSION_VERIFIED = '2fa_verified';
    private const RECOVERY_CODE_COUNT = 8;
    private const RECOVERY_ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789'; // fără 0/O, 1/I/L

    public function __construct(
        private readonly TotpService $totp,
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly AuditRecorder $audit,
    ) {
    }

    /**
     * Pornește înrolarea: cere RE-INTRODUCEREA PAROLEI (sesiunea singură nu e
     * suficientă pentru o operațiune de securitate) și emite secretul.
     */
    #[Route('/api/auth/2fa/setup', name: 'api_2fa_setup', methods: ['POST'])]
    public function setup(Request $request): JsonResponse
    {
        $user = $this->requireAdmin();
        if ($user->totpEnabled()) {
            throw new \DomainException('2FA este deja activ pe acest cont.');
        }

        $password = $this->stringField($request, 'password');
        if ($password === '' || !$this->hasher->isPasswordValid($user, $password)) {
            return $this->problem('invalid_password', 'Parola introdusă nu este corectă.');
        }

        $secret = $this->totp->generateSecret();
        $user->setPendingTotpSecret($secret);
        $this->em->flush();
        $this->audit->record('identity.2fa_setup_started', 'User', (string) $user->id());

        return new JsonResponse([
            'secret' => $secret,
            'otpauthUri' => $this->totp->provisioningUri($secret, $user->getEmail()),
        ]);
    }

    /**
     * Confirmă înrolarea: cod valid → 2FA activ + coduri de rezervă (returnate
     * o singură dată în clar; în bază rămân doar hash-urile).
     */
    #[Route('/api/auth/2fa/enable', name: 'api_2fa_enable', methods: ['POST'])]
    public function enable(Request $request): JsonResponse
    {
        $user = $this->requireAdmin();
        $secret = $user->totpSecret();
        if ($user->totpEnabled() || $secret === null) {
            throw new \DomainException('Nu există o înrolare în așteptare — rulați întâi pasul de configurare.');
        }
        if (!$this->totp->verify($secret, $this->stringField($request, 'code'))) {
            return $this->problem('invalid_code', 'Cod TOTP invalid sau expirat.');
        }

        $plainCodes = [];
        $hashes = [];
        for ($i = 0; $i < self::RECOVERY_CODE_COUNT; ++$i) {
            $plain = $this->generateRecoveryCode();
            $plainCodes[] = $plain;
            $hashes[] = password_hash($plain, PASSWORD_DEFAULT);
        }

        $user->enableTotp($hashes);
        $this->em->flush();
        $request->getSession()->set(self::SESSION_VERIFIED, true);
        $this->audit->record('identity.2fa_enabled', 'User', (string) $user->id());

        return new JsonResponse(['twoFactorEnabled' => true, 'recoveryCodes' => $plainCodes]);
    }

    /**
     * Deblochează sesiunea curentă după login: acceptă un cod TOTP (`code`)
     * sau un cod de rezervă de unică folosință (`recoveryCode`).
     */
    #[Route('/api/auth/2fa/verify', name: 'api_2fa_verify', methods: ['POST'])]
    public function verify(Request $request): JsonResponse
    {
        $user = $this->requireAdmin();
        $secret = $user->totpSecret();
        if (!$user->totpEnabled() || $secret === null) {
            throw new \DomainException('2FA nu este activat pe acest cont.');
        }

        $code = $this->stringField($request, 'code');
        $recovery = $this->stringField($request, 'recoveryCode');

        if ($code !== '' && $this->totp->verify($secret, $code)) {
            $request->getSession()->set(self::SESSION_VERIFIED, true);

            return new JsonResponse(['verified' => true]);
        }

        if ($recovery !== '' && $user->consumeRecoveryCode(strtoupper(trim($recovery)))) {
            $this->em->flush();
            $request->getSession()->set(self::SESSION_VERIFIED, true);
            $this->audit->record('identity.2fa_recovery_code_used', 'User', (string) $user->id(), null, [
                'remaining' => $user->remainingRecoveryCodes(),
            ]);

            return new JsonResponse(['verified' => true, 'recoveryCodesLeft' => $user->remainingRecoveryCodes()]);
        }

        return $this->problem('invalid_code', 'Cod TOTP sau cod de rezervă invalid.');
    }

    /** Dezactivează 2FA (necesită un cod TOTP valid). */
    #[Route('/api/auth/2fa/disable', name: 'api_2fa_disable', methods: ['POST'])]
    public function disable(Request $request): JsonResponse
    {
        $user = $this->requireAdmin();
        $secret = $user->totpSecret();
        if (!$user->totpEnabled() || $secret === null) {
            throw new \DomainException('2FA nu este activat pe acest cont.');
        }
        if (!$this->totp->verify($secret, $this->stringField($request, 'code'))) {
            return $this->problem('invalid_code', 'Cod TOTP invalid sau expirat.');
        }

        $user->disableTotp();
        $this->em->flush();
        $request->getSession()->remove(self::SESSION_VERIFIED);
        $this->audit->record('identity.2fa_disabled', 'User', (string) $user->id());

        return new JsonResponse(['twoFactorEnabled' => false]);
    }

    private function requireAdmin(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User || !$user->isServiceAdmin()) {
            throw $this->createAccessDeniedException('Doar conturile de service folosesc 2FA.');
        }

        return $user;
    }

    private function stringField(Request $request, string $field): string
    {
        /** @var array<string, mixed> $payload */
        $payload = json_decode((string) $request->getContent(), true) ?: [];
        $value = $payload[$field] ?? null;

        return \is_string($value) ? trim($value) : '';
    }

    /** Format „XXXX-XXXX" dintr-un alfabet fără caractere ambigue. */
    private function generateRecoveryCode(): string
    {
        $chars = '';
        for ($i = 0; $i < 8; ++$i) {
            $chars .= self::RECOVERY_ALPHABET[random_int(0, \strlen(self::RECOVERY_ALPHABET) - 1)];
        }

        return substr($chars, 0, 4).'-'.substr($chars, 4);
    }

    private function problem(string $type, string $title): JsonResponse
    {
        return new JsonResponse(
            ['type' => $type, 'title' => $title, 'status' => 422],
            422,
            ['Content-Type' => 'application/problem+json'],
        );
    }
}
