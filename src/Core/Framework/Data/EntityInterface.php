<?php

declare(strict_types=1);

namespace Companyware\Core\Framework\Data;

/**
 * Common persistence model for Core and plugin aggregate roots.
 *
 * @api
 */
interface EntityInterface
{
    public function id(): string;

    public function createdAt(): \DateTimeImmutable;

    public function updatedAt(): \DateTimeImmutable;

    public function version(): int;

    public function deletedAt(): ?\DateTimeImmutable;
}
