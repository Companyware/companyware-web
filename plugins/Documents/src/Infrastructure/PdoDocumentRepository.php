<?php

declare(strict_types=1);

namespace Plugins\Documents\Infrastructure;

use Companyware\Core\Framework\Data\ConnectionProviderInterface;
use Companyware\Core\Framework\Data\EntityQuery;
use Companyware\Core\Framework\Data\OptimisticLockException;
use PDO;
use Plugins\Documents\Domain\Document;
use Plugins\Documents\Domain\DocumentRepositoryInterface;

final class PdoDocumentRepository implements DocumentRepositoryInterface
{
    private const FILTERS = ['id' => 'id', 'uploaded_by' => 'uploaded_by', 'mime_type' => 'mime_type'];
    private const SORTS = ['created_at' => 'created_at', 'title' => 'title', 'updated_at' => 'updated_at'];

    public function __construct(private readonly ConnectionProviderInterface $database) {}

    public function find(string $id): ?Document
    {
        $statement = $this->database->connection()->prepare('SELECT * FROM document_file WHERE id = :id AND deleted_at IS NULL');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function search(EntityQuery $query): array
    {
        [$where, $parameters] = $this->where($query);
        $order = [];
        foreach ($query->orderBy ?: ['created_at' => 'desc'] as $field => $direction) {
            if (!isset(self::SORTS[$field])) throw new \InvalidArgumentException(sprintf('Unsupported document sort "%s".', $field));
            $order[] = self::SORTS[$field].' '.strtoupper($direction);
        }
        $sql = 'SELECT * FROM document_file'.$where.' ORDER BY '.implode(', ', $order).' LIMIT '.$query->limit.' OFFSET '.$query->offset;
        $statement = $this->database->connection()->prepare($sql);
        $statement->execute($parameters);

        return array_map(fn (array $row): Document => $this->hydrate($row), $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    public function count(EntityQuery $query): int
    {
        [$where, $parameters] = $this->where($query);
        $statement = $this->database->connection()->prepare('SELECT COUNT(*) FROM document_file'.$where);
        $statement->execute($parameters);

        return (int) $statement->fetchColumn();
    }

    public function create(string $title, string $originalName, string $storedName, string $mimeType, int $fileSize, int $uploadedBy): Document
    {
        $statement = $this->database->connection()->prepare('INSERT INTO document_file (title, original_name, stored_name, mime_type, file_size, uploaded_by) VALUES (:title, :original, :stored, :mime, :size, :user)');
        $statement->execute(['title' => $title, 'original' => $originalName, 'stored' => $storedName, 'mime' => $mimeType, 'size' => $fileSize, 'user' => $uploadedBy]);
        $document = $this->find((string) $this->database->connection()->lastInsertId());
        if (!$document instanceof Document) throw new \RuntimeException('Created document could not be loaded.');

        return $document;
    }

    public function remove(Document $entity): void
    {
        $statement = $this->database->connection()->prepare('UPDATE document_file SET deleted_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP, version = version + 1 WHERE id = :id AND version = :version AND deleted_at IS NULL');
        $statement->execute(['id' => $entity->id(), 'version' => $entity->version()]);
        if ($statement->rowCount() !== 1) throw OptimisticLockException::forEntity($entity);
    }

    /** @return array{string, array<string, scalar|null>} */
    private function where(EntityQuery $query): array
    {
        $clauses = $query->includeDeleted ? [] : ['deleted_at IS NULL'];
        $parameters = [];
        foreach ($query->filters as $field => $value) {
            if ($field === 'uploaded_by_department') {
                $clauses[] = 'uploaded_by IN (SELECT id FROM admin_user WHERE department_key = :uploaded_by_department)';
                $parameters[$field] = $value;
                continue;
            }
            if (!isset(self::FILTERS[$field])) throw new \InvalidArgumentException(sprintf('Unsupported document filter "%s".', $field));
            $clauses[] = self::FILTERS[$field].' = :'.$field;
            $parameters[$field] = $value;
        }

        return [$clauses === [] ? '' : ' WHERE '.implode(' AND ', $clauses), $parameters];
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): Document
    {
        $utc = new \DateTimeZone('UTC');
        return new Document(
            (string) $row['id'], (string) $row['title'], (string) $row['original_name'], (string) $row['stored_name'],
            (string) $row['mime_type'], (int) $row['file_size'], (int) $row['uploaded_by'],
            new \DateTimeImmutable((string) $row['created_at'], $utc),
            new \DateTimeImmutable((string) ($row['updated_at'] ?? $row['created_at']), $utc),
            (int) ($row['version'] ?? 1),
            isset($row['deleted_at']) ? new \DateTimeImmutable((string) $row['deleted_at'], $utc) : null,
        );
    }
}
