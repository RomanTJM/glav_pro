<?php

declare(strict_types=1);

namespace CrmStages\Service;

use CrmStages\Domain\Company;
use CrmStages\Domain\EventType;
use CrmStages\Domain\Stage;
use CrmStages\Domain\StageTransitionRules;
use CrmStages\Repository\CompanyRepository;

/**
 * Сервис управления стадиями CRM.
 *
 * Оркестрация: проверка правил → запись события → переход стадии.
 * Не содержит бизнес-правила напрямую — делегирует StageTransitionRules.
 */
final class StageService
{
    public function __construct(
        private readonly CompanyRepository $repo,
    ) {}

    /**
     * Выполнить действие над компанией.
     *
     * Алгоритм:
     * 1. Проверить ограничения текущей стадии
     * 2. Записать событие
     * 3. Перезагрузить события компании
     * 4. Проверить, можно ли автоматически перейти на следующую стадию
     *
     * @return array{success: bool, message: string, new_stage?: string}
     */
    public function performAction(int $companyId, EventType $action, array $data = []): array
    {
        $company = $this->repo->findById($companyId);
        if ($company === null) {
            return ['success' => false, 'message' => 'Компания не найдена'];
        }

        // 1. Проверяем ограничения стадии
        $check = StageTransitionRules::canPerformAction($company, $action);
        if (!$check['allowed']) {
            return ['success' => false, 'message' => $check['reason']];
        }

        // 2. Записываем событие (EventType enum — типобезопасно)
        $this->repo->insertEvent($companyId, $action, $data);

        // 3. Перезагружаем события для актуальной проверки
        $company->setEvents($this->repo->findEvents($companyId));

        // 4. Автопереход, если exit-условие выполнено
        $advanceResult = StageTransitionRules::canAdvance($company);
        if ($advanceResult['allowed']) {
            $nextStage = $company->stage->next();
            if ($nextStage !== null) {
                $this->repo->insertTransition($companyId, $company->stage, $nextStage);
                $this->repo->updateStage($companyId, $nextStage);

                return [
                    'success'   => true,
                    'message'   => sprintf(
                        'Действие выполнено. Компания перешла на стадию %s (%s)',
                        $nextStage->value,
                        $nextStage->label()
                    ),
                    'new_stage' => $nextStage->value,
                ];
            }
        }

        return [
            'success'   => true,
            'message'   => 'Действие выполнено',
            'new_stage' => $company->stage->value,
        ];
    }

    /**
     * Ручной переход на следующую стадию.
     *
     * Не даёт «перепрыгнуть» — только на следующую,
     * и только если exit-условие текущей стадии выполнено.
     *
     * @return array{success: bool, message: string}
     */
    public function tryAdvance(int $companyId): array
    {
        $company = $this->repo->findById($companyId);
        if ($company === null) {
            return ['success' => false, 'message' => 'Компания не найдена'];
        }

        $nextStage = $company->stage->next();
        if ($nextStage === null) {
            return ['success' => false, 'message' => 'Компания уже на финальной стадии'];
        }

        $check = StageTransitionRules::canAdvance($company);
        if (!$check['allowed']) {
            return ['success' => false, 'message' => $check['reason']];
        }

        $this->repo->insertTransition($companyId, $company->stage, $nextStage);
        $this->repo->updateStage($companyId, $nextStage);

        return [
            'success' => true,
            'message' => sprintf(
                'Переход выполнен: %s → %s',
                $company->stage->label(),
                $nextStage->label()
            ),
        ];
    }

    /**
     * Собрать данные для карточки компании.
     *
     * @return array{
     *     company: Company,
     *     available_actions: EventType[],
     *     restrictions: EventType[],
     *     instruction: string,
     *     can_advance: array{allowed: bool, reason: string},
     *     events: Event[],
     *     next_stage: ?Stage
     * }|null
     */
    public function getCompanyCard(int $companyId): ?array
    {
        $company = $this->repo->findById($companyId);
        if ($company === null) {
            return null;
        }

        return [
            'company'           => $company,
            'available_actions' => StageTransitionRules::getAvailableActions($company->stage),
            'restrictions'      => StageTransitionRules::getRestrictions($company->stage),
            'instruction'       => StageTransitionRules::getInstruction($company->stage),
            'can_advance'       => StageTransitionRules::canAdvance($company),
            'events'            => $company->getEvents(),
            'next_stage'        => $company->stage->next(),
        ];
    }
}
