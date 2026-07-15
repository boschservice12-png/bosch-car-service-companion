<?php

declare(strict_types=1);

namespace App\Settings\Application;

use App\Settings\Domain\ApplicationSetting;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Acces tipizat la setările aplicației, cu valori implicite. Cheile publice
 * (whatsapp, versiune informare confidențialitate) pot fi expuse clientului;
 * restul sunt interne. Pragurile și limitele sunt configurabile din admin.
 */
final class SettingsProvider
{
    public const DEFAULTS = [
        'deadline.notification_thresholds' => [60, 30, 7, 0],
        'whatsapp.number' => '',
        'privacy.text_version' => 'v1',
        'upload.max_bytes' => 10485760, // 10 MB
        'upload.allowed_mime_types' => ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'],
        'download.url_ttl_seconds' => 300,
    ];

    /** Chei expuse public (fără autentificare admin). */
    public const PUBLIC_KEYS = ['whatsapp.number', 'privacy.text_version'];

    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $setting = $this->em->find(ApplicationSetting::class, $key);
        if ($setting !== null) {
            return $setting->value();
        }

        return $default ?? (self::DEFAULTS[$key] ?? null);
    }

    public function set(string $key, mixed $value): void
    {
        $setting = $this->em->find(ApplicationSetting::class, $key);
        if ($setting === null) {
            $setting = new ApplicationSetting($key, $value);
            $this->em->persist($setting);
        } else {
            $setting->setValue($value);
        }
        $this->em->flush();
    }

    /** @return int[] */
    public function notificationThresholds(): array
    {
        /** @var int[] $value */
        $value = $this->get('deadline.notification_thresholds', self::DEFAULTS['deadline.notification_thresholds']);

        return $value;
    }

    public function whatsappNumber(): string
    {
        return (string) $this->get('whatsapp.number', '');
    }

    public function privacyTextVersion(): string
    {
        return (string) $this->get('privacy.text_version', 'v1');
    }

    public function uploadMaxBytes(): int
    {
        return (int) $this->get('upload.max_bytes', self::DEFAULTS['upload.max_bytes']);
    }

    /** @return string[] */
    public function allowedUploadMimeTypes(): array
    {
        /** @var string[] $value */
        $value = $this->get('upload.allowed_mime_types', self::DEFAULTS['upload.allowed_mime_types']);

        return $value;
    }

    public function downloadUrlTtlSeconds(): int
    {
        return (int) $this->get('download.url_ttl_seconds', self::DEFAULTS['download.url_ttl_seconds']);
    }

    /** @return array<string, mixed> Setările publice pentru client. */
    public function publicSettings(): array
    {
        return [
            'whatsappNumber' => $this->whatsappNumber(),
            'privacyTextVersion' => $this->privacyTextVersion(),
        ];
    }
}
