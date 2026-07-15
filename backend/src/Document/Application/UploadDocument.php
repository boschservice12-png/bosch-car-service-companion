<?php

declare(strict_types=1);

namespace App\Document\Application;

use App\Audit\Application\AuditRecorder;
use App\Document\Application\Message\ScanDocument;
use App\Document\Domain\Document;
use App\Document\Domain\DocumentRepository;
use App\Document\Domain\StorageAdapter;
use App\Identity\Domain\User;
use App\Settings\Application\SettingsProvider;
use App\Shared\Presentation\ValidationFailedException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Încărcare sigură de document:
 *  - validare dimensiune (limită configurabilă);
 *  - validare MIME + extensie (allowlist configurabilă);
 *  - stocare privată cu cheie generată aleator (fără nume de fișier de la client);
 *  - status inițial PENDING + dispecerizare scanare antimalware asincronă.
 */
final class UploadDocument
{
    private const EXTENSION_BY_MIME = [
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png' => ['png'],
        'image/webp' => ['webp'],
        'application/pdf' => ['pdf'],
    ];

    public function __construct(
        private readonly DocumentRepository $documents,
        private readonly StorageAdapter $storage,
        private readonly SettingsProvider $settings,
        private readonly Security $security,
        private readonly MessageBusInterface $bus,
        private readonly AuditRecorder $audit,
    ) {
    }

    public function __invoke(UploadedFile $file): Document
    {
        if (!$file->isValid()) {
            throw ValidationFailedException::fromArray(['file' => ['Încărcare eșuată.']]);
        }

        $maxBytes = $this->settings->uploadMaxBytes();
        $size = (int) $file->getSize();
        if ($size <= 0 || $size > $maxBytes) {
            throw ValidationFailedException::fromArray([
                'file' => [sprintf('Fișierul depășește limita de %d MB.', intdiv($maxBytes, 1048576))],
            ]);
        }

        // MIME real (detectat din conținut, nu din header-ul clientului).
        $mime = $file->getMimeType() ?? 'application/octet-stream';
        $allowedMimes = $this->settings->allowedUploadMimeTypes();
        if (!\in_array($mime, $allowedMimes, true)) {
            throw ValidationFailedException::fromArray([
                'file' => ['Tip de fișier nepermis. Acceptăm imagini (JPG, PNG, WEBP) și PDF.'],
            ]);
        }

        // Extensia trebuie să corespundă tipului MIME.
        $ext = strtolower($file->getClientOriginalExtension());
        $validExts = self::EXTENSION_BY_MIME[$mime] ?? [];
        if ($ext !== '' && !\in_array($ext, $validExts, true)) {
            throw ValidationFailedException::fromArray([
                'file' => ['Extensia fișierului nu corespunde conținutului.'],
            ]);
        }

        $safeExt = $validExts[0] ?? 'bin';
        $random = bin2hex(random_bytes(16));
        $key = sprintf('%s/%s.%s', substr($random, 0, 2), $random, $safeExt);

        $this->storage->store($file->getPathname(), $key, $mime);

        $owner = $this->security->getUser();
        $document = new Document(
            $key,
            $mime,
            $size,
            $owner instanceof User ? $owner : null,
            $file->getClientOriginalName(),
        );
        $this->documents->save($document);

        $this->bus->dispatch(new ScanDocument((string) $document->id()));
        $this->audit->record('document.uploaded', 'Document', (string) $document->id(), null, [
            'mimeType' => $mime,
            'sizeBytes' => $size,
        ]);

        return $document;
    }
}
