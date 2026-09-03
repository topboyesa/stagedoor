<?php
require __DIR__ . '/config/database.php';
require __DIR__ . '/includes/functions.php';

$order_id = isset($_GET['order_id']) ? (int) $_GET['order_id'] : 0;

$stmt = $pdo->prepare("
    SELECT o.*, e.title AS event_title, e.event_date, e.venue
    FROM orders o
    JOIN events e ON e.id = o.event_id
    WHERE o.id = ?
");
$stmt->execute([$order_id]);
$order = $stmt->fetch();

if (!$order) {
    http_response_code(404);
    die('Order not found.');
}

$stmt = $pdo->prepare("
    SELECT t.unique_code, tt.name AS tier_name
    FROM tickets t
    JOIN order_items oi ON oi.id = t.order_item_id
    JOIN ticket_types tt ON tt.id = oi.ticket_type_id
    WHERE oi.order_id = ?
    ORDER BY t.id ASC
");
$stmt->execute([$order_id]);
$tickets = $stmt->fetchAll();

$page_title = 'Confirmed';
require __DIR__ . '/includes/header.php';
?>

<div class="page">
    <div class="section" style="margin-top:24px;">
        <div class="eyebrow">Order confirmed</div>
        <h2><?= e($order['event_title']) ?></h2>
        <p class="mono" style="color:var(--muted);margin-top:8px;">
            <?= e(format_event_date($order['event_date'])) ?> · <?= e($order['venue']) ?>
        </p>
    </div>

    <p style="color:var(--muted);margin-bottom:24px;">
        A confirmation with these ticket codes was sent to <?= e($order['buyer_email']) ?>. You can also screenshot or bookmark this page.
    </p>

    <?php foreach ($tickets as $ticket): ?>
        <div class="stub" style="margin-bottom:16px;">
            <div class="stub-main">
                <div class="stub-label"><?= e($ticket['tier_name']) ?></div>
                <div class="stub-event"><?= e($order['event_title']) ?></div>
                <div class="stub-code">#<?= e($ticket['unique_code']) ?></div>
            </div>
            <div class="stub-stub">
                <div class="mono" style="font-size:11px;color:#5c5646;">Admit one</div>
            </div>
        </div>
    <?php endforeach; ?>

    <a href="index.php" class="btn-primary" style="margin-top:16px;">Browse more events</a>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
