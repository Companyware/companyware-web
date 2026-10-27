<?php

declare(strict_types=1);

namespace Companyware\Core\Test\Data;

use PDO;

/** @api */
final class SchemaTestHelper
{
    /** @param list<string> $columns */
    public static function assertColumns(PDO $database, string $table, array $columns): void
    {
        if (!preg_match('/^[a-z][a-z0-9_]*$/', $table)) {
            throw new \InvalidArgumentException('Unsafe table name.');
        }
        $actual = [];
        foreach ($database->query('SHOW COLUMNS FROM `'.$table.'`')->fetchAll(PDO::FETCH_ASSOC) as $column) {
            $actual[] = (string) $column['Field'];
        }
        $missing = array_values(array_diff($columns, $actual));
        if ($missing !== []) {
            throw new \RuntimeException(sprintf('Table "%s" misses columns: %s.', $table, implode(', ', $missing)));
        }
    }
}
