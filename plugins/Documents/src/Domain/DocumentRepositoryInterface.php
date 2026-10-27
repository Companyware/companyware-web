<?php

declare(strict_types=1);

namespace Plugins\Documents\Domain;

use Companyware\Core\Framework\Data\RepositoryInterface;

/** @extends RepositoryInterface<Document> */
interface DocumentRepositoryInterface extends RepositoryInterface
{
    public function create(
        string $title,
        string $originalName,
        string $storedName,
        string $mimeType,
        int $fileSize,
        int $uploadedBy,
    ): Document;

    public function remove(Document $document): void;
}
