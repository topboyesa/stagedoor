<?php
session_start();
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/../includes/auth.php';
require_creator_login();

// Revenue is calculated from PAID orders only — quantity_sold on ticket_types
// includes reserved-but-unpaid stock too, so it's not the right number to show
// as "money in the door." This joins through order_items to only count
// tickets that were actually paid for.
$stmt = $pdo->prepare("
    SELECT
        e.id, e.title, e.event_date, e.venue, e.status,
        COALESCE(SUM(CASE WHEN o.status = 'paid' THEN oi.quantity ELSE 0 END), 0) AS tickets_sold,
        COALESCE(SUM(CASE WHEN o.status = 'paid' THEN oi.quantity * oi.unit_price ELSE 0 END), 0) AS revenue,
        COALESCE(SUM(CASE WHEN o.status = 'paid' THEN o.total_amount - o.platform_fee ELSE 0 END), 0) AS owed_total,
        (SELECT COALESCE(SUM(amount), 0) FROM payouts p WHERE p.event_id = e.id) AS paid_out
    FROM events e
    LEFT JOIN order_items oi ON oi.ticket_type_id IN (SELECT id FROM ticket_types WHERE event_id = e.id)
    LEFT JOIN orders o ON o.id = oi.order_id
    WHERE e.creator_id = ?
    GROUP BY e.id
    ORDER BY e.event_date DESC
");
$stmt->execute([current_creator_id()]);
$events = $stmt->fetchAll();

$page_title = 'Dashboard';
$base = '../';
require __DIR__ . '/../includes/header.php';
?>

<div class="page">
    <div class="section" style="margin-top:24px;display:flex;justify-content:space-between;align-items:center;">
        <h2>Your events</h2>
        <div style="display:flex;gap:12px;">
            <a href="create-event.php" class="btn-primary" style="padding:10px 20px;font-size:13px;">+ New event</a>
        </div>
    </div>

    <p style="color:var(--muted);margin-bottom:20px;">
        Logged in as <?= e($_SESSION['creator_name']) ?> · <a href="logout.php" style="color:var(--amber);">Log out</a>
    </p>

    <?php if (empty($events)): ?>
        <p style="color:var(--muted);">You haven't created any events yet.</p>
    <?php else: ?>
        <?php foreach ($events as $event): ?>
            <div class="event-card" style="margin-bottom:12px;">
                <div class="eyebrow">
                    <?= e(format_event_date($event['event_date'])) ?>
                    · <?= $event['status'] === 'published' ? 'Published' : ucfirst($event['status']) ?>
                </div>
                <h2><?= e($event['title']) ?></h2>
                <div class="meta"><?= e($event['venue']) ?></div>
                <div style="display:flex;gap:24px;margin-top:12px;font-family:'IBM Plex Mono',monospace;font-size:13px;">
                    <div><span style="color:var(--muted);">Sold</span><br><?= (int) $event['tickets_sold'] ?> tickets</div>
                    <div><span style="color:var(--muted);">Revenue</span><br><?= format_currency($event['revenue']) ?></div>
                    <div><span style="color:var(--muted);">Paid out</span><br><?= format_currency($event['paid_out']) ?></div>
                    <?php $remaining = $event['owed_total'] - $event['paid_out']; ?>
                    <div><span style="color:<?= $remaining > 0 ? 'var(--amber)' : 'var(--teal)' ?>;">Owed to you</span><br><?= format_currency($remaining) ?></div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
