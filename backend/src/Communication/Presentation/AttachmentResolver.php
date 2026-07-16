<?php

declare(strict_types=1);

namespace App\Communication\Presentation;

use App\Document\Domain\Document;
use App\Document\Domain\DocumentRepository;
use App\Document\Presentation\DocumentVoter;
use App\Shared\Presentation\ValidationFailedException;
use Symfony\Component\Uid\Uuid;

/**
 * Rezolvă lista de documente atașate unui mesaj, verificând autorizarea (utilizatorul
 * curent trebuie să aibă drept de vizualizare asupra fiecărui document — de regulă
 * pentru că el le-a încărcat). Folosit de controllerele client și admin.
 */
trait AttachmentResolver
{
    /**
     * @param string[] $ids
     *
     * @return Document[]
     */
    private function resolveAttachments(array $ids, DocumentRepository $documents): array
    {
        $result = [];
        foreach ($ids as $id) {
            if (!\is_string($id) || !Uuid::isValid($id)) {
                throw ValidationFailedException::fromArray(['documentIds' => ['Identificator de document invalid.']]);
            }
            $document = $documents->get(Uuid::fromString($id));
            if ($document === null) {
                throw $this->createNotFoundException('Document inexistent.');
            }
            $this->denyAccessUnlessGranted(DocumentVoter::VIEW, $document);
            $result[] = $document;
        }

        return $result;
    }
}
