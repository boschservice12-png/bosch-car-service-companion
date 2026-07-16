<?php

declare(strict_types=1);

namespace App\Document\Presentation;

use App\Document\Application\SignedUrlGenerator;
use App\Document\Application\UploadDocument;
use App\Document\Domain\Document;
use App\Document\Domain\DocumentRepository;
use App\Document\Domain\StorageAdapter;
use App\Settings\Application\SettingsProvider;
use App\Shared\Presentation\ValidationFailedException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

final class DocumentController extends AbstractController
{
    public function __construct(
        private readonly DocumentRepository $documents,
        private readonly UploadDocument $uploadDocument,
        private readonly SignedUrlGenerator $signedUrls,
        private readonly SettingsProvider $settings,
    ) {
    }

    /** Încărcare document (multipart, câmp `file`). */
    #[Route('/api/documents', name: 'api_documents_upload', methods: ['POST'])]
    public function upload(Request $request): JsonResponse
    {
        $file = $request->files->get('file');
        if ($file === null) {
            throw ValidationFailedException::fromArray(['file' => ['Niciun fișier încărcat.']]);
        }

        $document = ($this->uploadDocument)($file);

        return $this->json([
            'id' => (string) $document->id(),
            'mimeType' => $document->mimeType(),
            'sizeBytes' => $document->sizeBytes(),
            'scanStatus' => $document->scanStatus()->value,
        ], 201);
    }

    /** URL temporar semnat de descărcare (autorizare la nivel de obiect). */
    #[Route('/api/documents/{id}/download-url', name: 'api_documents_download_url', methods: ['GET'])]
    public function downloadUrl(string $id): JsonResponse
    {
        $document = $this->requireDocument($id);
        $this->denyAccessUnlessGranted(DocumentVoter::VIEW, $document);

        if (!$document->isServable()) {
            throw new \App\Shared\Presentation\ValidationFailedException([
                'document' => ['Documentul nu este disponibil (în curs de scanare sau respins).'],
            ]);
        }

        $signed = $this->signedUrls->generate((string) $document->id(), $this->settings->downloadUrlTtlSeconds());

        return $this->json([
            'url' => $signed['url'],
            'expiresAt' => date(DATE_ATOM, $signed['expiresAt']),
        ]);
    }

    /**
     * Servire efectivă a conținutului. Protejată triplu:
     *  1) firewall (autentificare) — ruta e sub /api;
     *  2) semnătură + expirare valabile;
     *  3) autorizare la nivel de obiect (DocumentVoter) + status CLEAN.
     */
    #[Route('/api/documents/{id}/raw', name: 'api_documents_raw', methods: ['GET'])]
    public function raw(string $id, Request $request, StorageAdapter $storage): Response
    {
        $expires = (int) $request->query->get('expires', '0');
        $sig = (string) $request->query->get('sig', '');

        if (!Uuid::isValid($id) || !$this->signedUrls->isValid($id, $expires, $sig)) {
            throw $this->createAccessDeniedException('URL invalid sau expirat.');
        }

        $document = $this->requireDocument($id);
        $this->denyAccessUnlessGranted(DocumentVoter::VIEW, $document);

        if (!$document->isServable()) {
            throw $this->createNotFoundException('Document indisponibil.');
        }

        $contents = $storage->read($document->storageKey());
        $filename = $document->originalName() ?? ('document.'.pathinfo($document->storageKey(), PATHINFO_EXTENSION));

        return new Response($contents, 200, [
            'Content-Type' => $document->mimeType(),
            'Content-Disposition' => sprintf('attachment; filename="%s"', addslashes($filename)),
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function requireDocument(string $id): Document
    {
        if (!Uuid::isValid($id)) {
            throw $this->createNotFoundException('Document inexistent.');
        }
        $document = $this->documents->get(Uuid::fromString($id));
        if ($document === null) {
            throw $this->createNotFoundException('Document inexistent.');
        }

        return $document;
    }
}
