<?php

declare(strict_types=1);

namespace Companyware\Core\Framework\Data;

/**
 * @template T of EntityInterface
 * @extends RepositoryInterface<T>
 * @api
 */
interface WritableRepositoryInterface extends RepositoryInterface
{
    /** @param T $entity */
    public function save(EntityInterface $entity): void;

    /** @param T $entity */
    public function remove(EntityInterface $entity): void;
}
