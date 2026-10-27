<?php

declare(strict_types=1);

namespace Companyware\Core\Framework\Data;

/**
 * Decoration-ready aggregate repository boundary.
 *
 * @template T of EntityInterface
 * @api
 */
interface RepositoryInterface
{
    /** @return T|null */
    public function find(string $id): ?EntityInterface;

    /** @return list<T> */
    public function search(EntityQuery $query): array;

    public function count(EntityQuery $query): int;

}
