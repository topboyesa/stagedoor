<?php
session_start();
require __DIR__ . '/config/database.php';
require __DIR__ . '/includes/functions.php';

$event_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

$stmt = $pdo->prepare("SELECT * FROM events WHERE id = ? AND status = 'published'");
$stmt->execute([$event_id]);
$event = $stmt->fetch();

if (!$event) {
    http_response_code(404);
    die('Event not found.');
}

$stmt = $pdo->prepare("SELECT * FROM ticket_types WHERE event_id = ? ORDER BY price ASC");
$stmt->execute([$event_id]);
$tiers = $stmt->fetchAll();

// Handle "add tickets to cart" submission.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cart = [];
    foreach ($tiers as $tier) {
        $qty = (int) ($_POST['qty_' . $tier['id']] ?? 0);
        if ($qty > 0) {
            $remaining = $tier['quantity_available'] - $tier['quantity_sold'];
            $qty = min($qty, $remaining);
            if ($qty > 0) {
                $cart[$tier['id']] = $qty;
            }
        }
    }

    if (!empty($cart)) {
        $_SESSION['cart'] = [
            'event_id' => $event_id,
            'items'    => $cart,
        ];
        header('Location: checkout.php');
        exit;
    } else {
        $error = 'Select at least one ticket to continue.';
    }
}

$page_title = $event['title'];
require __DIR__ . '/includes/header.php';
?>

<div class="page">
    <div class="hero">
        <div class="eyebrow"><?= e($event['venue']) ?></div>
        <h1><?= e($event['title']) ?></h1>
        <div class="hero-meta">
            <div><span class="label">Date</span><?= e(format_event_date($event['event_date'])) ?></div>
            <div><span class="label">Doors</span><?= e(format_event_time($event['event_date'])) ?></div>
            <div><span class="label">Venue</span><?= e($event['venue']) ?></div>
        </div>
    </div>

    <?php if ($event['description']): ?>
        <p style="color:var(--muted);margin-bottom:32px;"><?= nl2br(e($event['description'])) ?></p>
    <?php endif; ?>

    <?php if (isset($error)): ?>
        <p class="error"><?= e($error) ?></p>
    <?php endif; ?>

    <form method="post">
        <div class="section">
            <h2>Choose your ticket</h2>

            <?php foreach ($tiers as $tier): ?>
                <?php $remaining = $tier['quantity_available'] - $tier['quantity_sold']; ?>
                <div class="tier-row">
                    <div class="tier-info">
                        <h3><?= e($tier['name']) ?></h3>
                        <?php if ($remaining <= 0): ?>
                            <div class="tier-avail sold-out">Sold out</div>
                        <?php elseif ($remaining <= 20): ?>
                            <div class="tier-avail low"><?= $remaining ?> left</div>
                        <?php else: ?>
                            <div class="tier-avail"><?= $remaining ?> left</div>
                        <?php endif; ?>
                    </div>
                    <div class="tier-right">
                        <div class="tier-price mono"><?= format_currency($tier['price']) ?></div>
                        <div class="qty-select">
                            <?php if ($remaining > 0): ?>
                                <select name="qty_<?= (int) $tier['id'] ?>">
                                    <?php for ($i = 0; $i <= min($remaining, 10); $i++): ?>
                                        <option value="<?= $i ?>"><?= $i ?></option>
                                    <?php endfor; ?>
                                </select>
                            <?php else: ?>
                                <span class="mono" style="color:var(--muted);">—</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="summary-bar">
            <div class="total">StageDoor<span>Select quantities above</span></div>
            <button type="submit" class="btn-primary">Continue</button>
        </div>
    </form>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
