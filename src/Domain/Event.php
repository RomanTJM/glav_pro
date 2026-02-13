<?php

declare(strict_types=1);

namespace CrmStages\Domain;

/**
 * Событие компании (immutable value object).
 *
 * Хранит факт: что произошло, когда и с какими данными.
 * Все события — append-only (event sourcing pattern).
 */
final class Event
{
    public function __construct(
        public readonly int $id,
        public readonly int $companyId,
        public readonly EventType $eventType,
        public readonly array $eventData,
        public readonly string $createdAt,
    ) {}

    /**
     * Создать из строки БД.
     */
    public static function fromRow(array $row): self
    {
        $rawData = $row['event_data'] ?? '{}';
        $decoded = json_decode($rawData, true);

        return new self(
            id: (int) $row['id'],
            companyId: (int) $row['company_id'],
            eventType: EventType::from($row['event_type']),
            eventData: is_array($decoded) ? $decoded : [],
            createdAt: $row['created_at'],
        );
    }
}
