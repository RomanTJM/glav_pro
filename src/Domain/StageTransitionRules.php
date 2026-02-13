<?php

declare(strict_types=1);

namespace CrmStages\Domain;

/**
 * Бизнес-правила переходов между стадиями CRM.
 *
 * Каждое правило описывает:
 * - entry conditions (можно ли войти в стадию)
 * - exit conditions (можно ли перейти к следующей)
 * - restrictions (запрещённые действия на текущей стадии)
 * - available actions (доступные действия)
 * - instruction (скрипт/инструкция менеджеру)
 */
final class StageTransitionRules
{
    /**
     * Можно ли перейти из текущей стадии в следующую.
     * Проверяет exit-условие текущей стадии.
     *
     * @return array{allowed: bool, reason: string}
     */
    public static function canAdvance(Company $company): array
    {
        return match ($company->stage) {
            Stage::Ice => self::canExitIce($company),
            Stage::Touched => self::canExitTouched($company),
            Stage::Aware => self::canExitAware($company),
            Stage::Interested => self::canExitInterested($company),
            Stage::DemoPlanned => self::canExitDemoPlanned($company),
            Stage::DemoDone => self::canExitDemoDone($company),
            Stage::Committed => self::canExitCommitted($company),
            Stage::Customer => self::canExitCustomer($company),
            Stage::Activated => ['allowed' => false, 'reason' => 'Финальная стадия'],
            Stage::Null => ['allowed' => false, 'reason' => 'Компания в статусе Null'],
        };
    }

    /**
     * Проверяет, разрешено ли действие (event_type) на текущей стадии.
     *
     * @return array{allowed: bool, reason: string}
     */
    public static function canPerformAction(Company $company, EventType $action): array
    {
        $restrictions = self::getRestrictions($company->stage);

        if (in_array($action, $restrictions, true)) {
            return [
                'allowed' => false,
                'reason' => sprintf(
                    'Действие "%s" запрещено на стадии %s (%s)',
                    $action->label(),
                    $company->stage->value,
                    $company->stage->label()
                ),
            ];
        }

        return ['allowed' => true, 'reason' => ''];
    }

    /**
     * Запрещённые действия на стадии.
     *
     * @return EventType[]
     */
    public static function getRestrictions(Stage $stage): array
    {
        return match ($stage) {
            // C1 Touched: нельзя заводить заявку, отправлять КП, планировать и показывать демо
            Stage::Touched => [
                EventType::ApplicationCreated,
                EventType::CpSent,
                EventType::DemoPlanned,
                EventType::DemoConducted,
            ],
            // C2 Aware: нельзя планировать и показывать демо
            Stage::Aware => [
                EventType::DemoPlanned,
                EventType::DemoConducted,
            ],
            // W1 Interested: нельзя заводить заявку, отправлять КП
            Stage::Interested => [
                EventType::ApplicationCreated,
                EventType::CpSent,
            ],
            // W2 demo_planned: нельзя заводить заявку, отправлять КП
            Stage::DemoPlanned => [
                EventType::ApplicationCreated,
                EventType::CpSent,
            ],
            default => [],
        };
    }

    /**
     * Доступные действия на стадии.
     *
     * @return EventType[]
     */
    public static function getAvailableActions(Stage $stage): array
    {
        return match ($stage) {
            Stage::Ice => [EventType::ContactAttempt],
            Stage::Touched => [EventType::ContactAttempt, EventType::LprConversation],
            Stage::Aware => [EventType::DiscoveryFilled],
            Stage::Interested => [EventType::DemoPlanned],
            Stage::DemoPlanned => [EventType::DemoConducted],
            Stage::DemoDone => [EventType::ApplicationCreated, EventType::CpSent, EventType::InvoiceIssued],
            Stage::Committed => [EventType::PaymentReceived],
            Stage::Customer => [EventType::CertificateIssued],
            Stage::Activated => [],
            Stage::Null => [],
        };
    }

    /**
     * Инструкция/скрипт для менеджера на текущей стадии.
     */
    public static function getInstruction(Stage $stage): string
    {
        return match ($stage) {
            Stage::Ice =>
                "🧊 Компания новая. Нужно связаться.\n" .
                "1. Нажмите кнопку «Позвонить» для попытки контакта.\n" .
                "2. Цель: дозвониться до лица, принимающего решение (ЛПР).",

            Stage::Touched =>
                "📞 Контакт установлен, но разговора с ЛПР ещё не было.\n" .
                "1. Продолжайте звонить — нужен разговор с ЛПР.\n" .
                "2. После успешного разговора заполните комментарий.\n" .
                "⚠ Нельзя: заводить заявку, отправлять КП, планировать демо.",

            Stage::Aware =>
                "💬 Был разговор с ЛПР. Нужно провести дискавери.\n" .
                "1. Заполните форму дискавери (потребности клиента, бюджет, сроки).\n" .
                "⚠ Нельзя: планировать демо до заполнения дискавери.",

            Stage::Interested =>
                "🎯 Дискавери заполнено. Назначьте демо.\n" .
                "1. Согласуйте с клиентом дату и время демонстрации.\n" .
                "2. Нажмите «Запланировать демо» и укажите дату/время.\n" .
                "⚠ Нельзя: заводить заявку, отправлять КП.",

            Stage::DemoPlanned =>
                "📅 Демо запланировано. Проведите его.\n" .
                "1. В назначенное время откройте ссылку на демо.\n" .
                "2. Переход по ссылке зарегистрирует проведение демо.\n" .
                "⚠ Нельзя: заводить заявку, отправлять КП.",

            Stage::DemoDone =>
                "✅ Демо проведено. Оформите заявку или КП.\n" .
                "1. Заведите заявку и/или выставьте счёт.\n" .
                "2. Отправьте коммерческое предложение.",

            Stage::Committed =>
                "📄 Счёт выставлен. Ожидайте оплату.\n" .
                "1. Контролируйте поступление оплаты.\n" .
                "2. Зарегистрируйте оплату по факту.",

            Stage::Customer =>
                "💰 Оплата получена. Выдайте удостоверение.\n" .
                "1. Оформите и выдайте первое удостоверение клиенту.",

            Stage::Activated =>
                "🏆 Клиент активирован! Все этапы пройдены.\n" .
                "Компания полностью введена в работу.",

            Stage::Null => "Компания в статусе Null.",
        };
    }

    // --- Private exit condition checks ---

    private static function canExitIce(Company $company): array
    {
        // Из Ice можно выйти, если есть хотя бы попытка контакта
        if ($company->hasEvent(EventType::ContactAttempt)) {
            return ['allowed' => true, 'reason' => ''];
        }
        return ['allowed' => false, 'reason' => 'Нужна хотя бы одна попытка контакта'];
    }

    private static function canExitTouched(Company $company): array
    {
        // Из Touched: есть разговор с ЛПР
        if ($company->hasEvent(EventType::LprConversation)) {
            return ['allowed' => true, 'reason' => ''];
        }
        return ['allowed' => false, 'reason' => 'Нужен разговор с лицом, принимающим решение (ЛПР)'];
    }

    private static function canExitAware(Company $company): array
    {
        // Из Aware: заполнена форма дискавери
        if ($company->hasEvent(EventType::DiscoveryFilled)) {
            return ['allowed' => true, 'reason' => ''];
        }
        return ['allowed' => false, 'reason' => 'Нужно заполнить форму дискавери'];
    }

    private static function canExitInterested(Company $company): array
    {
        // Из Interested: есть дата и время демонстрации
        if ($company->hasEvent(EventType::DemoPlanned)) {
            return ['allowed' => true, 'reason' => ''];
        }
        return ['allowed' => false, 'reason' => 'Нужно запланировать демо (дата и время)'];
    }

    private static function canExitDemoPlanned(Company $company): array
    {
        // Из DemoPlanned: проведено демо (зарегистрирован переход по ссылке)
        if ($company->hasEvent(EventType::DemoConducted)) {
            return ['allowed' => true, 'reason' => ''];
        }
        return ['allowed' => false, 'reason' => 'Нужно провести демо (переход по ссылке)'];
    }

    private static function canExitDemoDone(Company $company): array
    {
        // Из DemoDone: есть демо < 60 дней + есть заявка и/или счёт
        if (!$company->hasRecentEvent(EventType::DemoConducted, 60)) {
            return ['allowed' => false, 'reason' => 'Демо проведено более 60 дней назад или отсутствует'];
        }
        if (!$company->hasEvent(EventType::InvoiceIssued) && !$company->hasEvent(EventType::ApplicationCreated)) {
            return ['allowed' => false, 'reason' => 'Нужна заявка и/или выставленный счёт'];
        }
        return ['allowed' => true, 'reason' => ''];
    }

    private static function canExitCommitted(Company $company): array
    {
        // Из Committed: есть оплата
        if ($company->hasEvent(EventType::PaymentReceived)) {
            return ['allowed' => true, 'reason' => ''];
        }
        return ['allowed' => false, 'reason' => 'Нужна оплата'];
    }

    private static function canExitCustomer(Company $company): array
    {
        // Из Customer: выдано удостоверение
        if ($company->hasEvent(EventType::CertificateIssued)) {
            return ['allowed' => true, 'reason' => ''];
        }
        return ['allowed' => false, 'reason' => 'Нужно выдать удостоверение'];
    }
}
