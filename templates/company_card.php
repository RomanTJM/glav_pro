<?php
/**
 * Карточка компании — основной интерфейс менеджера.
 *
 * @var array $card {
 *     company: \CrmStages\Domain\Company,
 *     available_actions: \CrmStages\Domain\EventType[],
 *     restrictions: \CrmStages\Domain\EventType[],
 *     instruction: string,
 *     can_advance: array{allowed: bool, reason: string},
 *     events: \CrmStages\Domain\Event[],
 *     next_stage: ?\CrmStages\Domain\Stage,
 * }
 */

use CrmStages\Domain\EventType;
use CrmStages\Domain\Stage;

$company = $card['company'];

ob_start();
?>

<a href="/" class="back-link">← К списку компаний</a>

<div class="company-card">
    <!-- Заголовок -->
    <div class="card-header">
        <h1><?= htmlspecialchars($company->name) ?></h1>
        <div class="stage-badge stage-<?= strtolower($company->stage->value) ?> large">
            <?= htmlspecialchars($company->stage->value) ?> — <?= htmlspecialchars($company->stage->label()) ?>
        </div>
    </div>

    <!-- Pipeline визуализация -->
    <div class="pipeline">
        <?php
        $pipelineStages = [
            Stage::Ice, Stage::Touched, Stage::Aware, Stage::Interested,
            Stage::DemoPlanned, Stage::DemoDone, Stage::Committed, Stage::Customer, Stage::Activated,
        ];
        foreach ($pipelineStages as $s):
            $isCurrent = $s === $company->stage;
            $isPast = $s->order() < $company->stage->order();
            $cls = $isCurrent ? 'current' : ($isPast ? 'past' : 'future');
        ?>
        <div class="pipeline-stage <?= $cls ?>" title="<?= htmlspecialchars($s->label()) ?>">
            <span class="pipeline-code"><?= htmlspecialchars($s->value) ?></span>
            <span class="pipeline-label"><?= htmlspecialchars($s->label()) ?></span>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Два столбца: действия + инструкция -->
    <div class="card-columns">
        <!-- Доступные действия -->
        <div class="card-section">
            <h2>📋 Доступные действия</h2>
            <div id="actions-container">
                <?php if (empty($card['available_actions'])): ?>
                    <p class="muted">Нет доступных действий на этой стадии</p>
                <?php else: ?>
                    <?php foreach ($card['available_actions'] as $action): ?>
                    <div class="action-block">
                        <?php if ($action === EventType::ContactAttempt): ?>
                            <button class="btn btn-primary"
                                    onclick="performAction(<?= $company->id ?>, '<?= $action->value ?>', {method: 'phone'})">
                                📞 <?= htmlspecialchars($action->label()) ?>
                            </button>

                        <?php elseif ($action === EventType::LprConversation): ?>
                            <form onsubmit="return submitForm(this, <?= $company->id ?>, '<?= $action->value ?>')">
                                <textarea name="comment" placeholder="Комментарий к разговору с ЛПР..." required></textarea>
                                <button type="submit" class="btn btn-primary">💬 <?= htmlspecialchars($action->label()) ?></button>
                            </form>

                        <?php elseif ($action === EventType::DiscoveryFilled): ?>
                            <form onsubmit="return submitForm(this, <?= $company->id ?>, '<?= $action->value ?>')">
                                <input type="text" name="needs" placeholder="Потребности клиента" required>
                                <input type="text" name="budget" placeholder="Бюджет">
                                <input type="text" name="timeline" placeholder="Сроки">
                                <button type="submit" class="btn btn-primary">📝 <?= htmlspecialchars($action->label()) ?></button>
                            </form>

                        <?php elseif ($action === EventType::DemoPlanned): ?>
                            <form onsubmit="return submitForm(this, <?= $company->id ?>, '<?= $action->value ?>')">
                                <input type="date" name="demo_date" required>
                                <input type="time" name="demo_time" required>
                                <button type="submit" class="btn btn-primary">📅 <?= htmlspecialchars($action->label()) ?></button>
                            </form>

                        <?php elseif ($action === EventType::DemoConducted): ?>
                            <button class="btn btn-primary"
                                    onclick="performAction(<?= $company->id ?>, '<?= $action->value ?>', {link_clicked: true})">
                                🔗 Провести демо (переход по ссылке)
                            </button>

                        <?php elseif ($action === EventType::InvoiceIssued): ?>
                            <form onsubmit="return submitForm(this, <?= $company->id ?>, '<?= $action->value ?>')">
                                <input type="text" name="invoice_number" placeholder="Номер счёта" required>
                                <input type="number" name="amount" placeholder="Сумма" step="0.01" min="0.01" required>
                                <button type="submit" class="btn btn-primary">📄 <?= htmlspecialchars($action->label()) ?></button>
                            </form>

                        <?php elseif ($action === EventType::ApplicationCreated): ?>
                            <form onsubmit="return submitForm(this, <?= $company->id ?>, '<?= $action->value ?>')">
                                <input type="text" name="application_type" placeholder="Тип заявки" required>
                                <button type="submit" class="btn btn-primary">📋 <?= htmlspecialchars($action->label()) ?></button>
                            </form>

                        <?php elseif ($action === EventType::CpSent): ?>
                            <button class="btn btn-primary"
                                    onclick="performAction(<?= $company->id ?>, '<?= $action->value ?>', {})">
                                📨 <?= htmlspecialchars($action->label()) ?>
                            </button>

                        <?php elseif ($action === EventType::PaymentReceived): ?>
                            <form onsubmit="return submitForm(this, <?= $company->id ?>, '<?= $action->value ?>')">
                                <input type="number" name="amount" placeholder="Сумма оплаты" step="0.01" min="0.01" required>
                                <button type="submit" class="btn btn-success">💰 <?= htmlspecialchars($action->label()) ?></button>
                            </form>

                        <?php elseif ($action === EventType::CertificateIssued): ?>
                            <form onsubmit="return submitForm(this, <?= $company->id ?>, '<?= $action->value ?>')">
                                <input type="text" name="certificate_number" placeholder="Номер удостоверения" required>
                                <button type="submit" class="btn btn-success">🏆 <?= htmlspecialchars($action->label()) ?></button>
                            </form>

                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <?php if (!empty($card['restrictions'])): ?>
            <div class="restrictions">
                <h3>⚠ Ограничения</h3>
                <ul>
                    <?php foreach ($card['restrictions'] as $r): ?>
                    <li>🚫 <?= htmlspecialchars($r->label()) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
        </div>

        <!-- Инструкция для менеджера -->
        <div class="card-section">
            <h2>📖 Инструкция для менеджера</h2>
            <div class="instruction">
                <?= nl2br(htmlspecialchars($card['instruction'])) ?>
            </div>

            <?php if ($card['can_advance']['allowed'] && $card['next_stage']): ?>
            <div class="advance-block">
                <p class="advance-info">
                    ✅ Все условия выполнены. Можно перейти к стадии
                    <strong><?= htmlspecialchars($card['next_stage']->value) ?> — <?= htmlspecialchars($card['next_stage']->label()) ?></strong>
                </p>
                <button class="btn btn-success" onclick="tryAdvance(<?= $company->id ?>)" style="margin-top: 10px;">
                    🚀 Перейти на <?= htmlspecialchars($card['next_stage']->label()) ?>
                </button>
            </div>
            <?php elseif ($card['next_stage']): ?>
            <div class="advance-block blocked">
                <p>❌ <?= htmlspecialchars($card['can_advance']['reason']) ?></p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- История событий -->
    <div class="card-section full-width">
        <h2>📜 История событий</h2>
        <?php if (empty($card['events'])): ?>
            <p class="muted">Событий пока нет</p>
        <?php else: ?>
        <table class="events-table">
            <thead>
                <tr>
                    <th>Дата</th>
                    <th>Событие</th>
                    <th>Данные</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach (array_reverse($card['events']) as $event): ?>
                <tr>
                    <td class="event-date"><?= date('d.m.Y H:i', strtotime($event->createdAt)) ?></td>
                    <td>
                        <span class="event-type"><?= htmlspecialchars($event->eventType->label()) ?></span>
                    </td>
                    <td class="event-data">
                        <?php if (!empty($event->eventData)): ?>
                            <?php foreach ($event->eventData as $key => $val): ?>
                                <span class="event-field"><?= htmlspecialchars((string) $key) ?>: <?= htmlspecialchars((string) $val) ?></span>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <span class="muted">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<div id="notification" class="notification hidden"></div>

<?php
$content = ob_get_clean();
$title = htmlspecialchars($company->name) . ' — CRM Stages';
require __DIR__ . '/layout.php';
