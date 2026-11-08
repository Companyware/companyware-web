<?php

namespace App\Infrastructure;

use App\Migration\Core\Version202608150001;
use App\Migration\Core\Version202610260001;
use App\Migration\Core\Version202610260002;
use App\Migration\Core\Version202610270001;
use App\Migration\Core\Version202611020001;
use App\Migration\Core\Version202611020002;
use App\Migration\Core\Version202611080001;
use App\Plugin\Migration\MigrationManager;

final class Schema
{
    private bool $ready = false;

    public function __construct(private readonly MigrationManager $migrations)
    {
    }

    public function ensure(): void
    {
        if ($this->ready) return;
        $this->migrations->migrateCore([Version202608150001::class, Version202610260001::class, Version202610260002::class, Version202610270001::class, Version202611020001::class, Version202611020002::class, Version202611080001::class]);
        $this->ready = true;
    }
}
