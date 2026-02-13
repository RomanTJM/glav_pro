<?php

declare(strict_types=1);

namespace CrmStages\Domain;

/**
 * Сущность «Компания».
 *
 * Содержит текущее состояние (стадию) и историю событий.
 * Бизнес-логика проверки условий перехода — в StageTransitionRules.
 */
final class Company
{
    /** @var Event[] */
    private array $events = [];

    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public Stage $stage,
        public readonly string $createdAt,
        public readonly string $updatedAt,
    ) {}

    /**
     * Создать из строки БД.
     */
    public static function fromRow(array $row): self
    {
        return new self(
            id: (int) $row['id'],
            name: $row['name'],
            stage: Stage::from($row['stage']),
            createdAt: $row['created_at'],
            updatedAt: $row['updated_at'],
        );
    }

    /**
     * @param Event[] $events
     */
    public function setEvents(array $events): void
    {
        $this->events = $events;
    }

    /**
     * @return Event[]
     */
    public function getEvents(): array
    {
        return $this->events;
    }

    /**
     * Есть ли хотя бы одно событие данного типа?
     */
    public function hasEvent(EventType $type): bool
    {
        foreach ($this->events as $event) {
            if ($event->eventType === $type) {
                return true;
            }
        }
        return false;
    }

    /**
     * Есть ли событие данного типа не старше $days дней?
     *
     * Используется для правила «демо проведено < 60 дней».
     */
    public function hasRecentEvent(EventType $type, int $days): bool
    {
        $threshold = (new \DateTimeImmutable())->modify("-{$days} days");

        foreach ($this->events as $event) {
            if ($event->eventType === $type) {
                $eventDate = new \DateTimeImmutable($event->createdAt);
                if ($eventDate >= $threshold) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Данные последнего события данного типа (или null).
     */
    public function getLastEventData(EventType $type): ?array
    {
        $last = null;

        foreach ($this->events as $event) {
            if ($event->eventType === $type) {
                $last = $event;
            }
        }

        return $last?->eventData;
    }
}
