<?php

declare(strict_types=1);

namespace Companyware\Core\Test\Data;

use Companyware\Core\Framework\Data\EntityInterface;
use Companyware\Core\Framework\Data\WritableRepositoryInterface;

/** @api */
final class RepositoryTestHelper
{
    public static function assertRoundTrip(WritableRepositoryInterface $repository, EntityInterface $entity): void
    {
        $repository->save($entity);
        $stored = $repository->find($entity->id());
        if (!$stored instanceof EntityInterface || $stored->id() !== $entity->id()) {
            throw new \RuntimeException(sprintf('Repository did not return saved entity "%s".', $entity->id()));
        }
    }
}
