<?php

declare(strict_types=1);

namespace App\Settings\Presentation;

use App\Audit\Application\AuditRecorder;
use App\Settings\Application\SettingsProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class SettingsController extends AbstractController
{
    public function __construct(private readonly SettingsProvider $settings)
    {
    }

    /** Setări publice (fără autentificare) — număr WhatsApp, versiune informare. */
    #[Route('/api/settings', name: 'api_settings_public', methods: ['GET'])]
    public function publicSettings(): JsonResponse
    {
        return $this->json($this->settings->publicSettings());
    }

    /** (admin) Actualizează o setare configurabilă. */
    #[Route('/api/admin/settings/{key}', name: 'api_admin_settings_update', methods: ['PATCH'])]
    public function update(string $key, Request $request, AuditRecorder $audit): JsonResponse
    {
        if (!\array_key_exists($key, SettingsProvider::DEFAULTS)) {
            throw $this->createNotFoundException('Setare necunoscută.');
        }

        /** @var array{value?: mixed} $payload */
        $payload = json_decode($request->getContent(), true) ?? [];
        if (!\array_key_exists('value', $payload)) {
            throw new \App\Shared\Presentation\ValidationFailedException(['value' => ['Câmpul „value" este obligatoriu.']]);
        }

        $before = $this->settings->get($key);
        $this->settings->set($key, $payload['value']);
        $audit->record('settings.updated', 'ApplicationSetting', null, ['key' => $key, 'value' => $before], ['key' => $key, 'value' => $payload['value']]);

        return $this->json(['key' => $key, 'value' => $this->settings->get($key)]);
    }
}
