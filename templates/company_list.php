<?php
/** @var \CrmStages\Domain\Company[] $companies */

use CrmStages\Domain\Stage;

ob_start();
?>

<h1>Компании</h1>

<div class="company-grid">
    <?php foreach ($companies as $company): ?>
    <a href="/company?id=<?= $company->id ?>" class="company-card-mini">
        <div class="company-name"><?= htmlspecialchars($company->name) ?></div>
        <div class="stage-badge stage-<?= strtolower($company->stage->value) ?>">
            <?= $company->stage->value ?> — <?= $company->stage->label() ?>
        </div>
        <div class="company-date"><?= date('d.m.Y', strtotime($company->updatedAt)) ?></div>
    </a>
    <?php endforeach; ?>

    <?php if (empty($companies)): ?>
    <p class="empty">Нет компаний</p>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
$title = 'Компании — CRM Stages';
require __DIR__ . '/layout.php';
