<?php

declare(strict_types=1);

namespace Companyware\Core\Framework\Data;

/** @api */
final class OptimisticLockException extends \RuntimeException
{
    public static function forEntity(EntityInterface $entity): self
    {
        return new self(sprintf('Entity "%s" changed concurrently (version %d).', $entity->id(), $entity->version()));
    }
}
