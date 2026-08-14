<?php

namespace App\Infrastructure;

use PDO;
use RuntimeException;

final class Database
{
    private ?PDO $connection = null;

    public function connection(): PDO
    {
        if ($this->connection instanceof PDO) {
            return $this->connection;
        }

        $url = $_ENV['DATABASE_URL'] ?? $_SERVER['DATABASE_URL'] ?? null;
        if (!$url) {
            throw new RuntimeException('DATABASE_URL is not configured. Add it to .env.local.');
        }

        $parts = parse_url($url);
        if (!is_array($parts) || !isset($parts['scheme'], $parts['host'], $parts['path'])) {
            throw new RuntimeException('DATABASE_URL is invalid.');
        }

        $database = ltrim($parts['path'], '/');
        $port = $parts['port'] ?? 3306;
        $charset = 'utf8mb4';
        parse_str($parts['query'] ?? '', $query);
        if (isset($query['charset'])) {
            $charset = $query['charset'];
        }

        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $parts['host'], $port, $database, $charset);
        $this->connection = new PDO($dsn, urldecode($parts['user'] ?? ''), urldecode($parts['pass'] ?? ''), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        return $this->connection;
    }
}
