<?php

declare(strict_types=1);

namespace App\Document\Infrastructure;

use App\Document\Domain\Document;
use App\Document\Domain\DocumentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final class DoctrineDocumentRepository implements DocumentRepository
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function save(Document $document): void
    {
        $this->em->persist($document);
        $this->em->flush();
    }

    public function get(Uuid $id): ?Document
    {
        return $this->em->find(Document::class, $id);
    }
}
