<?php

declare(strict_types=1);

namespace CrmStages\Repository;

use CrmStages\Config\Database;
use CrmStages\Domain\Company;
use CrmStages\Domain\Event;
use CrmStages\Domain\EventType;
use CrmStages\Domain\Stage;

/**
 * Репозиторий для работы с компаниями и событиями.
 *
 * Единственный слой, работающий с PDO напрямую.
 * Не содержит бизнес-логику — только CRUD.
 */
class CompanyRepository
{
    private \PDO $pdo;

    public function __construct(?\PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getConnection();
    }

    /**
     * Найти компанию по ID (с загрузкой всех событий).
     */
    public function findById(int $id): ?Company
    {
        $stmt = $this->pdo->prepare('SELECT * FROM companies WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        if (!$row) {
            return null;
        }

        $company = Company::fromRow($row);
        $company->setEvents($this->findEvents($id));

        return $company;
    }

    /**
     * Получить все компании (без событий, для списка).
     *
     * @return Company[]
     */
    public function findAll(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM companies ORDER BY updated_at DESC');
        $companies = [];

        foreach ($stmt->fetchAll() as $row) {
            $companies[] = Company::fromRow($row);
        }

        return $companies;
    }

    /**
     * Загрузить все события компании (в хронологическом порядке).
     *
     * @return Event[]
     */
    public function findEvents(int $companyId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM company_events WHERE company_id = :id ORDER BY created_at ASC'
        );
        $stmt->execute(['id' => $companyId]);

        $events = [];
        foreach ($stmt->fetchAll() as $row) {
            $events[] = Event::fromRow($row);
        }

        return $events;
    }

    /**
     * Обновить стадию компании.
     */
    public function updateStage(int $companyId, Stage $newStage): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE companies SET stage = :stage WHERE id = :id'
        );
        $stmt->execute(['stage' => $newStage->value, 'id' => $companyId]);
    }

    /**
     * Записать событие компании.
     *
     * @param EventType $eventType Тип события (enum, а не строка — типобезопасность)
     * @param array     $eventData Дополнительные данные события
     * @return int ID созданного события
     */
    public function insertEvent(int $companyId, EventType $eventType, array $eventData): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO company_events (company_id, event_type, event_data)
             VALUES (:company_id, :event_type, :event_data)'
        );
        $stmt->execute([
            'company_id' => $companyId,
            'event_type' => $eventType->value,
            'event_data' => json_encode($eventData, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Записать переход между стадиями.
     */
    public function insertTransition(int $companyId, Stage $from, Stage $to): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO stage_transitions (company_id, from_stage, to_stage)
             VALUES (:company_id, :from_stage, :to_stage)'
        );
        $stmt->execute([
            'company_id' => $companyId,
            'from_stage' => $from->value,
            'to_stage'   => $to->value,
        ]);
    }

    /**
     * Создать новую компанию (стадия Ice по умолчанию).
     *
     * @return int ID созданной компании
     */
    public function create(string $name): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO companies (name, stage) VALUES (:name, :stage)'
        );
        $stmt->execute(['name' => $name, 'stage' => Stage::Ice->value]);

        return (int) $this->pdo->lastInsertId();
    }
}
