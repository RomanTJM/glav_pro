<?php

declare(strict_types=1);

namespace CrmStages\Tests\Unit;

use CrmStages\Domain\Company;
use CrmStages\Domain\Event;
use CrmStages\Domain\EventType;
use CrmStages\Domain\Stage;
use CrmStages\Repository\CompanyRepository;
use CrmStages\Service\StageService;
use PHPUnit\Framework\TestCase;

/**
 * Тесты сервисного слоя StageService.
 *
 * Покрытие:
 * - performAction: запись события + автопереход
 * - performAction: блокировка запрещённых действий
 * - tryAdvance: ручной переход
 * - tryAdvance: блокировка без выполнения условий
 * - getCompanyCard: полнота данных
 * - Полный pipeline C0 → A1
 */
final class StageServiceTest extends TestCase
{
    /**
     * Создаёт мок репозитория с заданной компанией.
     *
     * @param Company  $company             Компания для findById
     * @param array    $insertedEvents      Ссылка: записанные события
     * @param array    $insertedTransitions Ссылка: записанные переходы
     */
    private function createService(
        Company $company,
        array &$insertedEvents = [],
        array &$insertedTransitions = [],
    ): StageService {
        $repo = $this->createMock(CompanyRepository::class);

        $repo->method('findById')->willReturn($company);

        // Динамически возвращаем events (базовые + вставленные)
        $repo->method('findEvents')->willReturnCallback(
            function () use ($company, &$insertedEvents) {
                $events = $company->getEvents();
                foreach ($insertedEvents as $ie) {
                    $events[] = new Event(
                        id: count($events) + 1,
                        companyId: $company->id,
                        eventType: $ie['event_type'],
                        eventData: $ie['event_data'],
                        createdAt: date('Y-m-d H:i:s'),
                    );
                }
                return $events;
            }
        );

        $repo->method('insertEvent')->willReturnCallback(
            function (int $cid, EventType $type, array $data) use (&$insertedEvents) {
                $insertedEvents[] = ['event_type' => $type, 'event_data' => $data];
                return count($insertedEvents);
            }
        );

        $repo->method('insertTransition')->willReturnCallback(
            function (int $cid, Stage $from, Stage $to) use (&$insertedTransitions) {
                $insertedTransitions[] = ['from' => $from->value, 'to' => $to->value];
            }
        );

        $repo->method('updateStage')->willReturnCallback(
            function (int $cid, Stage $newStage) use ($company) {
                // Мок: ничего не делаем
            }
        );

        return new StageService($repo);
    }

    // ============================================================
    // performAction: успешное действие + автопереход
    // ============================================================

    /**
     * Given: компания на Ice, нет событий.
     * When: performAction(ContactAttempt).
     * Then: событие записано, автопереход на Touched.
     */
    public function testPerformContactAttemptOnIceAdvancesToTouched(): void
    {
        $company = new Company(1, 'Test', Stage::Ice, '2025-01-01', '2025-01-01');
        $company->setEvents([]);

        $insertedEvents = [];
        $insertedTransitions = [];
        $service = $this->createService($company, $insertedEvents, $insertedTransitions);

        $result = $service->performAction(1, EventType::ContactAttempt, ['method' => 'phone']);

        $this->assertTrue($result['success']);
        $this->assertNotEmpty($insertedEvents);
        $this->assertSame(EventType::ContactAttempt, $insertedEvents[0]['event_type']);
        // Автопереход: Ice → Touched
        $this->assertNotEmpty($insertedTransitions);
        $this->assertSame('C0', $insertedTransitions[0]['from']);
        $this->assertSame('C1', $insertedTransitions[0]['to']);
    }

    // ============================================================
    // performAction: запрещённое действие
    // ============================================================

    /**
     * Given: компания на Touched.
     * When: performAction(DemoPlanned).
     * Then: отказ — планирование демо запрещено на этой стадии.
     */
    public function testCannotPlanDemoOnTouched(): void
    {
        $company = new Company(1, 'Test', Stage::Touched, '2025-01-01', '2025-01-01');
        $company->setEvents([]);

        $insertedEvents = [];
        $service = $this->createService($company, $insertedEvents);

        $result = $service->performAction(1, EventType::DemoPlanned, []);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('запрещено', mb_strtolower($result['message']));
        $this->assertEmpty($insertedEvents); // событие НЕ записано
    }

    /**
     * Given: компания на Interested.
     * When: performAction(ApplicationCreated).
     * Then: отказ — нельзя заводить заявку.
     */
    public function testCannotCreateApplicationOnInterested(): void
    {
        $company = new Company(1, 'Test', Stage::Interested, '2025-01-01', '2025-01-01');
        $company->setEvents([]);

        $insertedEvents = [];
        $service = $this->createService($company, $insertedEvents);

        $result = $service->performAction(1, EventType::ApplicationCreated, []);

        $this->assertFalse($result['success']);
        $this->assertEmpty($insertedEvents);
    }

    // ============================================================
    // tryAdvance: успешный ручной переход
    // ============================================================

    public function testTryAdvanceFromTouchedWithLpr(): void
    {
        $company = new Company(1, 'Test', Stage::Touched, '2025-01-01', '2025-01-01');
        $company->setEvents([
            new Event(1, 1, EventType::LprConversation, ['comment' => 'Обсудили'], date('Y-m-d H:i:s')),
        ]);

        $insertedTransitions = [];
        $insertedEvents = [];
        $service = $this->createService($company, $insertedEvents, $insertedTransitions);

        $result = $service->tryAdvance(1);

        $this->assertTrue($result['success']);
        $this->assertNotEmpty($insertedTransitions);
    }

    // ============================================================
    // tryAdvance: блокировка — условие не выполнено
    // ============================================================

    public function testTryAdvanceFromTouchedWithoutLprFails(): void
    {
        $company = new Company(1, 'Test', Stage::Touched, '2025-01-01', '2025-01-01');
        $company->setEvents([]); // нет разговора с ЛПР

        $insertedTransitions = [];
        $insertedEvents = [];
        $service = $this->createService($company, $insertedEvents, $insertedTransitions);

        $result = $service->tryAdvance(1);

        $this->assertFalse($result['success']);
        $this->assertEmpty($insertedTransitions);
    }

    // ============================================================
    // tryAdvance: финальная стадия
    // ============================================================

    public function testTryAdvanceFromActivatedFails(): void
    {
        $company = new Company(1, 'Test', Stage::Activated, '2025-01-01', '2025-01-01');
        $company->setEvents([]);

        $insertedTransitions = [];
        $insertedEvents = [];
        $service = $this->createService($company, $insertedEvents, $insertedTransitions);

        $result = $service->tryAdvance(1);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('финальной', mb_strtolower($result['message']));
        $this->assertEmpty($insertedTransitions);
    }

    // ============================================================
    // getCompanyCard: полнота данных
    // ============================================================

    public function testGetCompanyCardReturnsFullData(): void
    {
        $company = new Company(1, 'Test', Stage::Aware, '2025-01-01', '2025-01-01');
        $company->setEvents([
            new Event(1, 1, EventType::LprConversation, [], date('Y-m-d H:i:s')),
        ]);

        $insertedEvents = [];
        $service = $this->createService($company, $insertedEvents);

        $card = $service->getCompanyCard(1);

        $this->assertNotNull($card);
        $this->assertSame($company, $card['company']);
        $this->assertArrayHasKey('available_actions', $card);
        $this->assertArrayHasKey('restrictions', $card);
        $this->assertArrayHasKey('instruction', $card);
        $this->assertArrayHasKey('can_advance', $card);
        $this->assertArrayHasKey('events', $card);
        $this->assertArrayHasKey('next_stage', $card);
    }

    /**
     * getCompanyCard для несуществующей компании → null.
     */
    public function testGetCompanyCardReturnsNullForMissing(): void
    {
        $repo = $this->createMock(CompanyRepository::class);
        $repo->method('findById')->willReturn(null);

        $service = new StageService($repo);
        $this->assertNull($service->getCompanyCard(999));
    }

    // ============================================================
    // Полный pipeline: С0 → А1
    // ============================================================

    /**
     * Проверяем, что каждый шаг воронки С0 → A1 работает корректно
     * при правильной последовательности действий.
     */
    public function testFullPipelineIceToActivated(): void
    {
        $stages = [
            ['stage' => Stage::Ice,         'action' => EventType::ContactAttempt,  'next' => Stage::Touched],
            ['stage' => Stage::Touched,     'action' => EventType::LprConversation, 'next' => Stage::Aware],
            ['stage' => Stage::Aware,       'action' => EventType::DiscoveryFilled, 'next' => Stage::Interested],
            ['stage' => Stage::Interested,  'action' => EventType::DemoPlanned,     'next' => Stage::DemoPlanned],
            ['stage' => Stage::DemoPlanned, 'action' => EventType::DemoConducted,   'next' => Stage::DemoDone],
        ];

        foreach ($stages as $step) {
            $company = new Company(1, 'Test', $step['stage'], '2025-01-01', '2025-01-01');
            $company->setEvents([]);

            $insertedEvents = [];
            $insertedTransitions = [];
            $service = $this->createService($company, $insertedEvents, $insertedTransitions);

            $result = $service->performAction(1, $step['action'], []);

            $this->assertTrue(
                $result['success'],
                "Failed action {$step['action']->value} on stage {$step['stage']->value}"
            );
            $this->assertNotEmpty(
                $insertedTransitions,
                "No transition for {$step['stage']->value}"
            );
            $this->assertSame(
                $step['next']->value,
                $insertedTransitions[0]['to'],
                "Wrong target stage from {$step['stage']->value}"
            );
        }
    }
}
