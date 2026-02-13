<?php

declare(strict_types=1);

namespace CrmStages\Tests\Unit;

use CrmStages\Domain\Company;
use CrmStages\Domain\Event;
use CrmStages\Domain\EventType;
use CrmStages\Domain\Stage;
use CrmStages\Domain\StageTransitionRules;
use PHPUnit\Framework\TestCase;

/**
 * Тесты бизнес-правил переходов стадий.
 *
 * Покрытие:
 * - exit-условия каждой стадии (Given/When/Then)
 * - ограничения (запрещённые действия)
 * - доступные действия
 * - невозможность «перепрыгнуть» стадию
 */
final class StageTransitionRulesTest extends TestCase
{
    // ============================================================
    // Helper: создать компанию на нужной стадии с событиями
    // ============================================================

    private function makeCompany(Stage $stage, array $eventTypes = [], array $eventDataOverrides = []): Company
    {
        $company = new Company(
            id: 1,
            name: 'Test Co',
            stage: $stage,
            createdAt: '2025-01-01 00:00:00',
            updatedAt: '2025-01-01 00:00:00',
        );

        $events = [];
        $id = 1;
        foreach ($eventTypes as $et) {
            $data = $eventDataOverrides[$et->value] ?? [];
            $events[] = new Event(
                id: $id++,
                companyId: 1,
                eventType: $et,
                eventData: $data,
                createdAt: date('Y-m-d H:i:s'), // "свежее" событие
            );
        }
        $company->setEvents($events);

        return $company;
    }

    // ============================================================
    // C0 Ice → C1 Touched
    // ============================================================

    /** Given: компания на стадии Ice, нет событий.
     *  When: проверяем canAdvance.
     *  Then: переход запрещён (нет попытки контакта). */
    public function testCannotExitIceWithoutContactAttempt(): void
    {
        $company = $this->makeCompany(Stage::Ice);
        $result = StageTransitionRules::canAdvance($company);

        $this->assertFalse($result['allowed']);
        $this->assertStringContainsString('попытка контакта', mb_strtolower($result['reason']));
    }

    /** Given: компания на стадии Ice, есть contact_attempt.
     *  When: проверяем canAdvance.
     *  Then: переход разрешён. */
    public function testCanExitIceWithContactAttempt(): void
    {
        $company = $this->makeCompany(Stage::Ice, [EventType::ContactAttempt]);
        $result = StageTransitionRules::canAdvance($company);

        $this->assertTrue($result['allowed']);
    }

    // ============================================================
    // C1 Touched → C2 Aware
    // ============================================================

    /** Given: компания на Touched, нет разговора с ЛПР.
     *  Then: переход запрещён. */
    public function testCannotExitTouchedWithoutLprConversation(): void
    {
        $company = $this->makeCompany(Stage::Touched, [EventType::ContactAttempt]);
        $result = StageTransitionRules::canAdvance($company);

        $this->assertFalse($result['allowed']);
        $this->assertStringContainsString('ЛПР', $result['reason']);
    }

    /** Given: компания на Touched, есть разговор с ЛПР.
     *  Then: переход разрешён. */
    public function testCanExitTouchedWithLprConversation(): void
    {
        $company = $this->makeCompany(Stage::Touched, [EventType::ContactAttempt, EventType::LprConversation]);
        $result = StageTransitionRules::canAdvance($company);

        $this->assertTrue($result['allowed']);
    }

    // ============================================================
    // C2 Aware → W1 Interested
    // ============================================================

    /** Given: компания на Aware, нет дискавери.
     *  Then: переход запрещён. */
    public function testCannotExitAwareWithoutDiscovery(): void
    {
        $company = $this->makeCompany(Stage::Aware, [EventType::LprConversation]);
        $result = StageTransitionRules::canAdvance($company);

        $this->assertFalse($result['allowed']);
        $this->assertStringContainsString('дискавери', mb_strtolower($result['reason']));
    }

    /** Given: компания на Aware, дискавери заполнена.
     *  Then: переход разрешён. */
    public function testCanExitAwareWithDiscovery(): void
    {
        $company = $this->makeCompany(Stage::Aware, [EventType::LprConversation, EventType::DiscoveryFilled]);
        $result = StageTransitionRules::canAdvance($company);

        $this->assertTrue($result['allowed']);
    }

    // ============================================================
    // W1 Interested → W2 DemoPlanned
    // ============================================================

    /** Given: компания на Interested, демо не запланировано.
     *  Then: переход запрещён. */
    public function testCannotExitInterestedWithoutDemoPlanned(): void
    {
        $company = $this->makeCompany(Stage::Interested, [EventType::DiscoveryFilled]);
        $result = StageTransitionRules::canAdvance($company);

        $this->assertFalse($result['allowed']);
        $this->assertStringContainsString('демо', mb_strtolower($result['reason']));
    }

    /** Given: компания на Interested, демо запланировано.
     *  Then: переход разрешён. */
    public function testCanExitInterestedWithDemoPlanned(): void
    {
        $company = $this->makeCompany(Stage::Interested, [EventType::DiscoveryFilled, EventType::DemoPlanned]);
        $result = StageTransitionRules::canAdvance($company);

        $this->assertTrue($result['allowed']);
    }

    // ============================================================
    // W2 DemoPlanned → W3 DemoDone
    // ============================================================

    /** Given: компания на DemoPlanned, демо не проведено.
     *  Then: переход запрещён. */
    public function testCannotExitDemoPlannedWithoutDemoConducted(): void
    {
        $company = $this->makeCompany(Stage::DemoPlanned, [EventType::DemoPlanned]);
        $result = StageTransitionRules::canAdvance($company);

        $this->assertFalse($result['allowed']);
    }

    /** Given: компания на DemoPlanned, демо проведено.
     *  Then: переход разрешён. */
    public function testCanExitDemoPlannedWithDemoConducted(): void
    {
        $company = $this->makeCompany(Stage::DemoPlanned, [EventType::DemoPlanned, EventType::DemoConducted]);
        $result = StageTransitionRules::canAdvance($company);

        $this->assertTrue($result['allowed']);
    }

    // ============================================================
    // W3 DemoDone → H1 Committed
    // ============================================================

    /** Given: компания на DemoDone, нет счёта/заявки.
     *  Then: переход запрещён. */
    public function testCannotExitDemoDoneWithoutInvoiceOrApplication(): void
    {
        $company = $this->makeCompany(Stage::DemoDone, [EventType::DemoConducted]);
        $result = StageTransitionRules::canAdvance($company);

        $this->assertFalse($result['allowed']);
    }

    /** Given: компания на DemoDone, есть счёт, демо < 60 дней.
     *  Then: переход разрешён. */
    public function testCanExitDemoDoneWithInvoice(): void
    {
        $company = $this->makeCompany(Stage::DemoDone, [EventType::DemoConducted, EventType::InvoiceIssued]);
        $result = StageTransitionRules::canAdvance($company);

        $this->assertTrue($result['allowed']);
    }

    /** Given: компания на DemoDone, есть заявка, демо < 60 дней.
     *  Then: переход разрешён. */
    public function testCanExitDemoDoneWithApplication(): void
    {
        $company = $this->makeCompany(Stage::DemoDone, [EventType::DemoConducted, EventType::ApplicationCreated]);
        $result = StageTransitionRules::canAdvance($company);

        $this->assertTrue($result['allowed']);
    }

    /** Given: компания на DemoDone, демо старше 60 дней.
     *  Then: переход запрещён. */
    public function testCannotExitDemoDoneIfDemoOlderThan60Days(): void
    {
        $company = new Company(1, 'Test Co', Stage::DemoDone, '2025-01-01', '2025-01-01');
        $events = [
            new Event(1, 1, EventType::DemoConducted, [], date('Y-m-d H:i:s', strtotime('-90 days'))),
            new Event(2, 1, EventType::InvoiceIssued, [], date('Y-m-d H:i:s')),
        ];
        $company->setEvents($events);

        $result = StageTransitionRules::canAdvance($company);
        $this->assertFalse($result['allowed']);
        $this->assertStringContainsString('60 дней', $result['reason']);
    }

    // ============================================================
    // H1 Committed → H2 Customer
    // ============================================================

    public function testCannotExitCommittedWithoutPayment(): void
    {
        $company = $this->makeCompany(Stage::Committed, [EventType::InvoiceIssued]);
        $result = StageTransitionRules::canAdvance($company);

        $this->assertFalse($result['allowed']);
    }

    public function testCanExitCommittedWithPayment(): void
    {
        $company = $this->makeCompany(Stage::Committed, [EventType::InvoiceIssued, EventType::PaymentReceived]);
        $result = StageTransitionRules::canAdvance($company);

        $this->assertTrue($result['allowed']);
    }

    // ============================================================
    // H2 Customer → A1 Activated
    // ============================================================

    public function testCannotExitCustomerWithoutCertificate(): void
    {
        $company = $this->makeCompany(Stage::Customer, [EventType::PaymentReceived]);
        $result = StageTransitionRules::canAdvance($company);

        $this->assertFalse($result['allowed']);
    }

    public function testCanExitCustomerWithCertificate(): void
    {
        $company = $this->makeCompany(Stage::Customer, [EventType::PaymentReceived, EventType::CertificateIssued]);
        $result = StageTransitionRules::canAdvance($company);

        $this->assertTrue($result['allowed']);
    }

    // ============================================================
    // A1 Activated — финальная стадия
    // ============================================================

    public function testCannotAdvanceBeyondActivated(): void
    {
        $company = $this->makeCompany(Stage::Activated);
        $result = StageTransitionRules::canAdvance($company);

        $this->assertFalse($result['allowed']);
        $this->assertStringContainsString('Финальная', $result['reason']);
    }

    // ============================================================
    // Ограничения (restrictions) — нельзя "перепрыгнуть"
    // ============================================================

    /** C1 Touched: нельзя заводить заявку */
    public function testTouchedCannotCreateApplication(): void
    {
        $company = $this->makeCompany(Stage::Touched);
        $result = StageTransitionRules::canPerformAction($company, EventType::ApplicationCreated);

        $this->assertFalse($result['allowed']);
    }

    /** C1 Touched: нельзя планировать демо */
    public function testTouchedCannotPlanDemo(): void
    {
        $company = $this->makeCompany(Stage::Touched);
        $result = StageTransitionRules::canPerformAction($company, EventType::DemoPlanned);

        $this->assertFalse($result['allowed']);
    }

    /** C1 Touched: нельзя отправлять КП */
    public function testTouchedCannotSendCp(): void
    {
        $company = $this->makeCompany(Stage::Touched);
        $result = StageTransitionRules::canPerformAction($company, EventType::CpSent);

        $this->assertFalse($result['allowed']);
    }

    /** C1 Touched: нельзя проводить демо */
    public function testTouchedCannotConductDemo(): void
    {
        $company = $this->makeCompany(Stage::Touched);
        $result = StageTransitionRules::canPerformAction($company, EventType::DemoConducted);

        $this->assertFalse($result['allowed']);
    }

    /** C2 Aware: нельзя планировать демо */
    public function testAwareCannotPlanDemo(): void
    {
        $company = $this->makeCompany(Stage::Aware);
        $result = StageTransitionRules::canPerformAction($company, EventType::DemoPlanned);

        $this->assertFalse($result['allowed']);
    }

    /** C2 Aware: нельзя проводить демо */
    public function testAwareCannotConductDemo(): void
    {
        $company = $this->makeCompany(Stage::Aware);
        $result = StageTransitionRules::canPerformAction($company, EventType::DemoConducted);

        $this->assertFalse($result['allowed']);
    }

    /** W1 Interested: нельзя заводить заявку */
    public function testInterestedCannotCreateApplication(): void
    {
        $company = $this->makeCompany(Stage::Interested);
        $result = StageTransitionRules::canPerformAction($company, EventType::ApplicationCreated);

        $this->assertFalse($result['allowed']);
    }

    /** W1 Interested: нельзя отправлять КП */
    public function testInterestedCannotSendCp(): void
    {
        $company = $this->makeCompany(Stage::Interested);
        $result = StageTransitionRules::canPerformAction($company, EventType::CpSent);

        $this->assertFalse($result['allowed']);
    }

    /** W2 DemoPlanned: нельзя заводить заявку */
    public function testDemoPlannedCannotCreateApplication(): void
    {
        $company = $this->makeCompany(Stage::DemoPlanned);
        $result = StageTransitionRules::canPerformAction($company, EventType::ApplicationCreated);

        $this->assertFalse($result['allowed']);
    }

    /** W3 DemoDone: МОЖНО заводить заявку */
    public function testDemoDoneCanCreateApplication(): void
    {
        $company = $this->makeCompany(Stage::DemoDone);
        $result = StageTransitionRules::canPerformAction($company, EventType::ApplicationCreated);

        $this->assertTrue($result['allowed']);
    }

    /** W3 DemoDone: МОЖНО отправлять КП */
    public function testDemoDoneCanSendCp(): void
    {
        $company = $this->makeCompany(Stage::DemoDone);
        $result = StageTransitionRules::canPerformAction($company, EventType::CpSent);

        $this->assertTrue($result['allowed']);
    }

    // ============================================================
    // Доступные действия
    // ============================================================

    public function testIceHasOnlyContactAttempt(): void
    {
        $actions = StageTransitionRules::getAvailableActions(Stage::Ice);
        $this->assertCount(1, $actions);
        $this->assertSame(EventType::ContactAttempt, $actions[0]);
    }

    public function testTouchedHasCallAndLprActions(): void
    {
        $actions = StageTransitionRules::getAvailableActions(Stage::Touched);
        $this->assertContains(EventType::ContactAttempt, $actions);
        $this->assertContains(EventType::LprConversation, $actions);
    }

    public function testAwareHasDiscoveryAction(): void
    {
        $actions = StageTransitionRules::getAvailableActions(Stage::Aware);
        $this->assertContains(EventType::DiscoveryFilled, $actions);
    }

    public function testDemoDoneHasApplicationAndCpAndInvoice(): void
    {
        $actions = StageTransitionRules::getAvailableActions(Stage::DemoDone);
        $this->assertContains(EventType::ApplicationCreated, $actions);
        $this->assertContains(EventType::CpSent, $actions);
        $this->assertContains(EventType::InvoiceIssued, $actions);
    }

    // ============================================================
    // Инструкции
    // ============================================================

    public function testInstructionsAreNotEmpty(): void
    {
        foreach (Stage::cases() as $stage) {
            $instruction = StageTransitionRules::getInstruction($stage);
            $this->assertNotEmpty($instruction, "Empty instruction for {$stage->value}");
        }
    }
}
