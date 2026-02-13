<?php

declare(strict_types=1);

namespace CrmStages\Tests\Unit;

use CrmStages\Domain\Company;
use CrmStages\Domain\Event;
use CrmStages\Domain\EventType;
use CrmStages\Domain\Stage;
use PHPUnit\Framework\TestCase;

/**
 * Тесты сущности Company.
 *
 * Покрытие:
 * - fromRow (парсинг из БД)
 * - hasEvent
 * - hasRecentEvent (проверка давности)
 * - getLastEventData
 */
final class CompanyTest extends TestCase
{
    public function testFromRow(): void
    {
        $row = [
            'id'         => '42',
            'name'       => 'ООО Тест',
            'stage'      => 'W1',
            'created_at' => '2025-06-01 10:00:00',
            'updated_at' => '2025-06-15 12:00:00',
        ];

        $company = Company::fromRow($row);

        $this->assertSame(42, $company->id);
        $this->assertSame('ООО Тест', $company->name);
        $this->assertSame(Stage::Interested, $company->stage);
    }

    public function testHasEvent(): void
    {
        $company = new Company(1, 'Test', Stage::Ice, '2025-01-01', '2025-01-01');
        $company->setEvents([
            new Event(1, 1, EventType::ContactAttempt, [], '2025-01-01 10:00:00'),
        ]);

        $this->assertTrue($company->hasEvent(EventType::ContactAttempt));
        $this->assertFalse($company->hasEvent(EventType::LprConversation));
    }

    public function testHasRecentEvent(): void
    {
        $company = new Company(1, 'Test', Stage::DemoDone, '2025-01-01', '2025-01-01');

        // Свежее событие (сегодня)
        $company->setEvents([
            new Event(1, 1, EventType::DemoConducted, [], date('Y-m-d H:i:s')),
        ]);
        $this->assertTrue($company->hasRecentEvent(EventType::DemoConducted, 60));

        // Старое событие (100 дней назад)
        $company->setEvents([
            new Event(1, 1, EventType::DemoConducted, [], date('Y-m-d H:i:s', strtotime('-100 days'))),
        ]);
        $this->assertFalse($company->hasRecentEvent(EventType::DemoConducted, 60));
    }

    public function testGetLastEventData(): void
    {
        $company = new Company(1, 'Test', Stage::Touched, '2025-01-01', '2025-01-01');
        $company->setEvents([
            new Event(1, 1, EventType::ContactAttempt, ['method' => 'email'], '2025-01-01 10:00:00'),
            new Event(2, 1, EventType::ContactAttempt, ['method' => 'phone'], '2025-01-02 10:00:00'),
        ]);

        $data = $company->getLastEventData(EventType::ContactAttempt);
        $this->assertSame('phone', $data['method'] ?? null);

        $this->assertNull($company->getLastEventData(EventType::LprConversation));
    }
}
