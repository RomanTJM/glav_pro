<?php

declare(strict_types=1);

namespace CrmStages\Controller;

use CrmStages\Domain\EventType;
use CrmStages\Repository\CompanyRepository;
use CrmStages\Service\StageService;

/**
 * Контроллер карточки компании.
 *
 * Обрабатывает HTTP-запросы, валидирует input,
 * вызывает StageService, рендерит шаблоны.
 */
final class CompanyController
{
    private StageService $stageService;
    private CompanyRepository $repo;

    public function __construct(?CompanyRepository $repo = null)
    {
        $this->repo = $repo ?? new CompanyRepository();
        $this->stageService = new StageService($this->repo);
    }

    /**
     * GET / — список компаний.
     */
    public function index(): void
    {
        $companies = $this->repo->findAll();
        require __DIR__ . '/../../templates/company_list.php';
    }

    /**
     * GET /company?id=N — карточка компании.
     */
    public function show(int $id): void
    {
        if ($id <= 0) {
            http_response_code(400);
            echo 'Некорректный ID компании';
            return;
        }

        $card = $this->stageService->getCompanyCard($id);
        if ($card === null) {
            http_response_code(404);
            echo 'Компания не найдена';
            return;
        }

        require __DIR__ . '/../../templates/company_card.php';
    }

    /**
     * POST /action — выполнить действие (событие).
     *
     * Body: company_id, action, + дополнительные поля данных события.
     */
    public function action(): void
    {
        $this->sendJsonHeaders();

        $companyId = filter_input(INPUT_POST, 'company_id', FILTER_VALIDATE_INT);
        $actionStr = trim((string) ($_POST['action'] ?? ''));

        if (!$companyId || $companyId <= 0) {
            $this->sendJson(['success' => false, 'message' => 'Некорректный ID компании']);
            return;
        }

        if ($actionStr === '') {
            $this->sendJson(['success' => false, 'message' => 'Не указано действие']);
            return;
        }

        $action = EventType::tryFrom($actionStr);
        if ($action === null) {
            $this->sendJson(['success' => false, 'message' => 'Неизвестное действие: ' . $actionStr]);
            return;
        }

        // Собираем дополнительные данные из POST (исключая служебные поля)
        $data = [];
        $serviceFields = ['company_id', 'action'];
        foreach ($_POST as $key => $value) {
            if (!in_array($key, $serviceFields, true)) {
                $data[$key] = is_string($value) ? trim($value) : $value;
            }
        }

        $result = $this->stageService->performAction($companyId, $action, $data);
        $this->sendJson($result);
    }

    /**
     * POST /advance — ручной переход на следующую стадию.
     */
    public function advance(): void
    {
        $this->sendJsonHeaders();

        $companyId = filter_input(INPUT_POST, 'company_id', FILTER_VALIDATE_INT);
        if (!$companyId || $companyId <= 0) {
            $this->sendJson(['success' => false, 'message' => 'Некорректный ID компании']);
            return;
        }

        $result = $this->stageService->tryAdvance($companyId);
        $this->sendJson($result);
    }

    /**
     * Отправить JSON-ответ.
     */
    private function sendJson(array $data): void
    {
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    /**
     * Установить заголовки для JSON-ответа.
     */
    private function sendJsonHeaders(): void
    {
        header('Content-Type: application/json; charset=utf-8');
    }
}
