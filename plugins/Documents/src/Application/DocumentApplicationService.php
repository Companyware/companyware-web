<?php

declare(strict_types=1);

namespace Plugins\Documents\Application;

use Companyware\Core\Framework\Application\ActorContextInterface;
use Companyware\Core\Framework\Application\PageRequest;
use Companyware\Core\Framework\Application\PageResult;
use Companyware\Core\Framework\Data\EntityQuery;
use Companyware\Core\Framework\Security\AuthorizationInterface;
use Plugins\Documents\DocumentStorage;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/** Shared Documents use cases for administration, CLI and versioned APIs. */
final class DocumentApplicationService
{
    public function __construct(
        private readonly DocumentStorage $storage,
        private readonly AuthorizationInterface $authorization,
        private readonly ActorContextInterface $actors,
    ) {}

    /** @return list<array<string, mixed>> */
    public function list(): array
    {
        $this->authorization->denyUnlessGranted('documents.read');
        return $this->storage->all();
    }

    public function search(PageRequest $page, array $filters = [], array $orderBy = ['created_at' => 'desc']): PageResult
    {
        $this->authorization->denyUnlessGranted('documents.read');
        $query = new EntityQuery($filters, $orderBy, $page->limit, $page->offset);
        return new PageResult($this->storage->search($query), $this->storage->count($query), $page->limit, $page->offset);
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        $this->authorization->denyUnlessGranted('documents.read');
        return $this->storage->find($id);
    }

    public function add(UploadedFile $file, string $title): array
    {
        $this->authorization->denyUnlessGranted('documents.create');
        $actorId = $this->actors->actorId();
        if ($actorId === null) throw new AccessDeniedHttpException('An authenticated actor is required.');
        return $this->storage->add($file, $title, $actorId);
    }

    /** @param array<string, mixed> $document */
    public function path(array $document): string
    {
        $this->authorization->denyUnlessGranted('documents.read');
        return $this->storage->path($document);
    }

    public function delete(int $id): void
    {
        $this->authorization->denyUnlessGranted('documents.delete');
        $this->storage->delete($id);
    }

    public function maxUploadMb(): int
    {
        return $this->storage->maxUploadMb();
    }
}
