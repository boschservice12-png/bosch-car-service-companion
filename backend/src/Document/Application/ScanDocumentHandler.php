<?php

declare(strict_types=1);

namespace App\Document\Application;

use App\Document\Application\Message\ScanDocument;
use App\Document\Domain\DocumentRepository;
use App\Document\Domain\MalwareScanner;
use App\Document\Domain\StorageAdapter;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

/**
 * Scanează antimalware un document. Dacă e curat -> CLEAN; dacă e infectat ->
 * INFECTED și fișierul se șterge din storage. Documentele non-CLEAN nu se servesc.
 */
#[AsMessageHandler]
final class ScanDocumentHandler
{
    public function __construct(
        private readonly DocumentRepository $documents,
        private readonly StorageAdapter $storage,
        private readonly MalwareScanner $scanner,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(ScanDocument $message): void
    {
        $document = $this->documents->get(Uuid::fromString($message->documentId));
        if ($document === null) {
            return;
        }

        $contents = $this->storage->read($document->storageKey());
        $tmp = tempnam(sys_get_temp_dir(), 'bcsc_scan_');
        if ($tmp === false) {
            throw new \RuntimeException('Nu s-a putut crea fișierul temporar pentru scanare.');
        }

        try {
            file_put_contents($tmp, $contents);
            $clean = $this->scanner->isClean($tmp);
        } finally {
            @unlink($tmp);
        }

        if ($clean) {
            $document->markClean();
        } else {
            $document->markInfected();
            $this->storage->delete($document->storageKey());
            $this->logger->warning('document.infected', ['documentId' => $message->documentId]);
        }

        $this->em->flush();
    }
}
