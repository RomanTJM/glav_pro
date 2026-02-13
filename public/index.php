<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use CrmStages\Controller\CompanyController;

// Встроенный PHP-сервер: отдаём статику (CSS, JS, изображения)
if (php_sapi_name() === 'cli-server') {
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $file = __DIR__ . $path;
    if ($path !== '/' && is_file($file)) {
        return false;
    }
}

$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

$controller = new CompanyController();

try {
    match (true) {
        $method === 'GET' && $uri === '/'
            => $controller->index(),

        $method === 'GET' && $uri === '/company'
            => $controller->show((int) ($_GET['id'] ?? 0)),

        $method === 'POST' && $uri === '/action'
            => $controller->action(),

        $method === 'POST' && $uri === '/advance'
            => $controller->advance(),

        default => (function () {
            http_response_code(404);
            echo '404 — Страница не найдена';
        })(),
    };
} catch (\Throwable $e) {
    error_log(sprintf("[CRM Error] %s in %s:%d\n%s", $e->getMessage(), $e->getFile(), $e->getLine(), $e->getTraceAsString()));

    http_response_code(500);

    // POST-запросы ожидают JSON, GET — HTML
    if ($method === 'POST') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'message' => 'Внутренняя ошибка сервера',
        ], JSON_UNESCAPED_UNICODE);
    } else {
        echo 'Произошла ошибка. Попробуйте обновить страницу.';
    }
}
