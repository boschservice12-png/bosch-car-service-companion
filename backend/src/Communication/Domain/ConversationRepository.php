<?php

declare(strict_types=1);

namespace App\Communication\Domain;

use App\Identity\Domain\User;
use Symfony\Component\Uid\Uuid;

interface ConversationRepository
{
    public function save(Conversation $conversation): void;

    public function get(Uuid $id): ?Conversation;

    /** Conversațiile unui client, cele mai recent active primele. @return Conversation[] */
    public function findForCustomer(User $customer): array;

    /** Toate conversațiile (portal admin), cele mai recent active primele. @return Conversation[] */
    public function findAllForAdmin(): array;
}
