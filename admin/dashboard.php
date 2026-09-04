<?php
session_start();
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../config/admin.php';
require __DIR__ . '/../includes/functions.php';

if (empty($_SESSION['is_admin'])) {
    header('Location: login.php');
    exit;
}

$notice = '';

// Recording a payout — the only write this page does.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['record_payout'])) {
    $event_id = (int) $_POST['event_id'];
    $creator_id = (int) $_POST['creator_id'];
    $amount = (float) $_POST['amount'];
    $notes = trim($_POST['notes'] ?? '');

    if ($amount > 0) {
        $stmt = $pdo->prepare("INSERT INTO payouts (creator_id, event_id, amount, notes) VALUES (?, ?, ?, ?)");
        $stmt->execute([$creator_id, $event_id, $amount, $notes]);
        $notice = 'Payout recorded.';
    }
}

// One row per event, with what's owed (from paid orders, minus your platform
// fee) and what's already been paid out (sum of payouts rows for that event).
$stmt = $pdo->query("
    SELECT
        e.id AS event_id, e.title, e.event_date,
        u.id AS creator_id, u.name AS creator_name, u.email AS creator_email,
        COALESCE(SUM(CASE WHEN o.status = 'paid' THEN o.total_amount - o.platform_fee ELSE 0 END), 0) AS owed_total,
        (SELECT COALESCE(SUM(amount), 0) FROM payouts p WHERE p.event_id = e.id) AS paid_out
    FROM events e
    JOIN users u ON u.id = e.creator_id
    LEFT JOIN orders o ON o.event_id = e.id
    GROUP BY e.id
    ORDER BY e.event_date DESC
");
$rows = $stmt->fetchAll();

$page_title = 'Admin — payouts';
$base = '../';
require __DIR__ . '/../includes/header.php';
?>

<div class="page">
    <div class="section" style="margin-top:24px;display:flex;justify-content:space-between;align-items:center;">
        <h2>Creator payouts</h2>
        <a href="logout.php" style="color:var(--amber);font-size:13px;">Log out</a>
    </div>

    <?php if ($notice): ?>
        <p style="color:var(--teal);margin-bottom:16px;"><?= e($notice) ?></p>
    <?php endif; ?>

    <?php if (empty($rows)): ?>
        <p style="color:var(--muted);">No events yet.</p>
    <?php endif; ?>

    <?php foreach ($rows as $row): ?>
        <?php $remaining = $row['owed_total'] - $row['paid_out']; ?>
        <div class="event-card" style="margin-bottom:16px;">
            <div class="eyebrow"><?= e($row['creator_name']) ?> · <?= e($row['creator_email']) ?></div>
            <h2><?= e($row['title']) ?></h2>
            <div class="meta"><?= e(format_event_date($row['event_date'])) ?></div>

            <div style="display:flex;gap:24px;margin:16px 0;font-family:'IBM Plex Mono',monospace;font-size:13px;">
                <div><span style="color:var(--muted);">Owed total</span><br><?= format_currency($row['owed_total']) ?></div>
                <div><span style="color:var(--muted);">Paid out</span><br><?= format_currency($row['paid_out']) ?></div>
                <div><span style="color:<?= $remaining > 0 ? 'var(--amber)' : 'var(--teal)' ?>;">Remaining</span><br><?= format_currency($remaining) ?></div>
            </div>

            <?php if ($remaining > 0): ?>
                <form method="post" style="display:flex;gap:8px;align-items:end;flex-wrap:wrap;">
                    <input type="hidden" name="record_payout" value="1">
                    <input type="hidden" name="event_id" value="<?= (int) $row['event_id'] ?>">
                    <input type="hidden" name="creator_id" value="<?= (int) $row['creator_id'] ?>">
                    <div class="field-group" style="margin-bottom:0;">
                        <label>Amount sent</label>
                        <input type="number" name="amount" step="1" min="1" max="<?= (int) $remaining ?>"
                               value="<?= (int) $remaining ?>" style="width:140px;">
                    </div>
                    <div class="field-group" style="margin-bottom:0;flex:1;min-width:160px;">
                        <label>Notes (optional)</label>
                        <input type="text" name="notes" placeholder="e.g. M-Pesa ref, date sent">
                    </div>
                    <button type="submit" class="btn-primary" style="padding:12px 20px;font-size:13px;">Record payout</button>
                </form>
            <?php else: ?>
                <p style="color:var(--teal);font-size:13px;">Fully paid out.</p>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
