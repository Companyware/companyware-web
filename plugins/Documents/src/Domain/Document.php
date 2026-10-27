<?php

declare(strict_types=1);

namespace Plugins\Documents\Domain;

use Companyware\Core\Framework\Data\EntityInterface;
use Companyware\Core\Framework\Data\EntityMetadata;

#[EntityMetadata(owner: 'documents')]
final readonly class Document implements EntityInterface
{
    public function __construct(
        private string $id,
        public string $title,
        public string $originalName,
        public string $storedName,
        public string $mimeType,
        public int $fileSize,
        public int $uploadedBy,
        private \DateTimeImmutable $createdAt,
        private \DateTimeImmutable $updatedAt,
        private int $version = 1,
        private ?\DateTimeImmutable $deletedAt = null,
    ) {
        if ($id === '' || $version < 1) {
            throw new \InvalidArgumentException('Document identity or version is invalid.');
        }
    }

    public function id(): string { return $this->id; }
    public function createdAt(): \DateTimeImmutable { return $this->createdAt; }
    public function updatedAt(): \DateTimeImmutable { return $this->updatedAt; }
    public function version(): int { return $this->version; }
    public function deletedAt(): ?\DateTimeImmutable { return $this->deletedAt; }
}
