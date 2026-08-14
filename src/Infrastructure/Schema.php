<?php

namespace App\Infrastructure;

final class Schema
{
    public function __construct(private readonly Database $database)
    {
    }

    public function ensure(): void
    {
        $pdo = $this->database->connection();
        $pdo->exec('CREATE TABLE IF NOT EXISTS admin_user (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(100) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            display_name VARCHAR(150) NOT NULL,
            email VARCHAR(190) DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $pdo->exec('CREATE TABLE IF NOT EXISTS plugin (
            name VARCHAR(190) PRIMARY KEY,
            active TINYINT(1) NOT NULL DEFAULT 0,
            installed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $pdo->exec('CREATE TABLE IF NOT EXISTS system_config (
            config_key VARCHAR(190) PRIMARY KEY,
            config_value TEXT DEFAULT NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    }
}
