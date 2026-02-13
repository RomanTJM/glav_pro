<?php

/**
 * Инициализация БД (SQLite) для локального запуска.
 *
 * Использование: php setup.php
 *
 * Для Docker — MySQL инициализируется автоматически
 * через docker-entrypoint-initdb.d (см. docker/mysql.Dockerfile).
 */

declare(strict_types=1);

$dataDir = __DIR__ . '/data';
$dbPath  = $dataDir . '/crm.sqlite';

if (!is_dir($dataDir)) {
    mkdir($dataDir, 0755, true);
    echo "Создана директория data/\n";
}

if (file_exists($dbPath)) {
    echo "База данных уже существует: $dbPath\n";
    echo "Удалите файл вручную для повторной инициализации.\n";
    exit(0);
}

$pdo = new PDO("sqlite:$dbPath");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('PRAGMA journal_mode=WAL');
$pdo->exec('PRAGMA foreign_keys=ON');

// --- Схема (SQLite-синтаксис) ---
$pdo->exec("
    CREATE TABLE companies (
        id          INTEGER PRIMARY KEY AUTOINCREMENT,
        name        TEXT NOT NULL,
        stage       TEXT NOT NULL DEFAULT 'C0',
        created_at  TEXT NOT NULL DEFAULT (datetime('now')),
        updated_at  TEXT NOT NULL DEFAULT (datetime('now'))
    );

    CREATE INDEX idx_companies_stage   ON companies(stage);
    CREATE INDEX idx_companies_updated ON companies(updated_at);

    CREATE TABLE company_events (
        id          INTEGER PRIMARY KEY AUTOINCREMENT,
        company_id  INTEGER NOT NULL,
        event_type  TEXT NOT NULL,
        event_data  TEXT DEFAULT '{}',
        created_at  TEXT NOT NULL DEFAULT (datetime('now')),
        FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
    );

    CREATE INDEX idx_events_company      ON company_events(company_id);
    CREATE INDEX idx_events_company_type ON company_events(company_id, event_type);
    CREATE INDEX idx_events_created      ON company_events(created_at);

    CREATE TABLE stage_transitions (
        id          INTEGER PRIMARY KEY AUTOINCREMENT,
        company_id  INTEGER NOT NULL,
        from_stage  TEXT NOT NULL,
        to_stage    TEXT NOT NULL,
        created_at  TEXT NOT NULL DEFAULT (datetime('now')),
        FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
    );

    CREATE INDEX idx_transitions_company ON stage_transitions(company_id);
    CREATE INDEX idx_transitions_created ON stage_transitions(created_at);
");
echo "Схема создана.\n";

// --- Тестовые данные ---
$pdo->exec("
    INSERT INTO companies (name, stage) VALUES
        ('ООО \"Альфа Технологии\"', 'C0'),
        ('ЗАО \"Бета Консалтинг\"', 'C1'),
        ('ИП Иванов', 'C2');
");

$pdo->exec("
    INSERT INTO company_events (company_id, event_type, event_data) VALUES
        (2, 'contact_attempt', '{\"method\": \"phone\", \"comment\": \"Не дозвонились\"}');
");

$pdo->exec("
    INSERT INTO company_events (company_id, event_type, event_data) VALUES
        (3, 'contact_attempt', '{\"method\": \"phone\", \"comment\": \"Набрали номер\"}'),
        (3, 'lpr_conversation', '{\"comment\": \"Поговорили с директором, интерес есть\"}');
");

$pdo->exec("
    INSERT INTO stage_transitions (company_id, from_stage, to_stage) VALUES
        (2, 'C0', 'C1'),
        (3, 'C0', 'C1'),
        (3, 'C1', 'C2');
");

echo "Тестовые данные добавлены.\n";
echo "База готова: $dbPath\n\n";
echo "Запуск сервера:\n";
echo "  php -S localhost:8080 -t public\n";
