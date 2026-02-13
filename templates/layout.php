<?php
/** @var string $title */
/** @var string $content — captured via ob_start/ob_get_clean */
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'CRM Stages') ?></title>
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
    <header class="header">
        <div class="container">
            <a href="/" class="logo">CRM Stages</a>
            <span class="subtitle">Управление воронкой продаж</span>
        </div>
    </header>
    <main class="container">
        <?= $content ?>
    </main>
    <script src="/js/app.js"></script>
</body>
</html>
