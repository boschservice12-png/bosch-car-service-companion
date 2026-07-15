<?php

declare(strict_types=1);

namespace App\Document\Domain;

use Symfony\Component\Uid\Uuid;

interface DocumentRepository
{
    public function save(Document $document): void;

    public function get(Uuid $id): ?Document;
}
