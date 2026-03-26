<?php

declare(strict_types=1);

namespace MerxyLab\Core;

use PDO;
use PDOException;
use RuntimeException;

final class Database
{
    private static ?PDO $pdo = null;

    public static function connection(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $host = Config::require('MERXYLAB_DB_HOST');
        $port = Config::int('MERXYLAB_DB_PORT', 3306);
        $name = Config::require('MERXYLAB_DB_NAME');
        $user = Config::require('MERXYLAB_DB_USER');
        $pass = Config::require('MERXYLAB_DB_PASS');

        if ($port < 1 || $port > 65535) {
            throw new RuntimeException('Invalid database configuration.');
        }

        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $name);

        try {
            self::$pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            throw new RuntimeException('Database connection failed.');
        }

        return self::$pdo;
    }
}
