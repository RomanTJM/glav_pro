<?php

declare(strict_types=1);

namespace CrmStages\Config;

/**
 * PDO-подключение к базе данных.
 *
 * Docker: переменная DB_HOST задана → MySQL.
 * Локально: SQLite (файл data/crm.sqlite).
 */
final class Database
{
    private static ?\PDO $pdo = null;

    public static function getConnection(): \PDO
    {
        if (self::$pdo === null) {
            $options = [
                \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE  => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES    => false,
            ];

            // PHP-FPM может не передавать переменные через getenv(),
            // поэтому проверяем также $_ENV и $_SERVER
            $dbHost = getenv('DB_HOST') ?: ($_ENV['DB_HOST'] ?? ($_SERVER['DB_HOST'] ?? ''));

            if ($dbHost !== '' && $dbHost !== false) {
                // MySQL (Docker)
                $dbName = getenv('DB_NAME') ?: ($_ENV['DB_NAME'] ?? 'crm_stages');
                $user   = getenv('DB_USER') ?: ($_ENV['DB_USER'] ?? 'crm');
                $pass   = getenv('DB_PASS') ?: ($_ENV['DB_PASS'] ?? 'crm_secret');

                $dsn = "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4";
                self::$pdo = new \PDO($dsn, $user, $pass, $options);

                // Гарантируем UTF-8 на уровне соединения
                self::$pdo->exec('SET NAMES utf8mb4');
                self::$pdo->exec("SET CHARACTER SET utf8mb4");
            } else {
                // SQLite (локально)
                $dsn = 'sqlite:' . self::getDefaultSqlitePath();
                self::$pdo = new \PDO($dsn, null, null, $options);
                self::$pdo->exec('PRAGMA journal_mode=WAL');
                self::$pdo->exec('PRAGMA foreign_keys=ON');
            }
        }

        return self::$pdo;
    }

    public static function getDefaultSqlitePath(): string
    {
        return dirname(__DIR__, 2) . '/data/crm.sqlite';
    }

    /**
     * Для тестов: позволяет подменить PDO-соединение.
     */
    public static function setConnection(\PDO $pdo): void
    {
        self::$pdo = $pdo;
    }

    /**
     * Сброс соединения (для тестов и переподключения).
     */
    public static function reset(): void
    {
        self::$pdo = null;
    }
}
