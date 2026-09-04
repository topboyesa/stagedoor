<?php
require __DIR__ . '/config/database.php';
require __DIR__ . '/includes/functions.php';

$order_id = isset($_GET['order_id']) ? (int) $_GET['order_id'] : 0;

$stmt = $pdo->prepare("SELECT o.*, e.title AS event_title FROM orders o JOIN events e ON e.id = o.event_id WHERE o.id = ?");
$stmt->execute([$order_id]);
$order = $stmt->fetch();

if (!$order) {
    http_response_code(404);
    die('Order not found.');
}

// If this got refreshed after payment already resolved, skip straight there.
if ($order['status'] === 'paid') {
    header('Location: confirmation.php?order_id=' . $order_id);
    exit;
}

$page_title = 'Check your phone';
require __DIR__ . '/includes/header.php';
?>

<div class="page">
    <div class="section" style="margin-top:24px;text-align:center;">
        <div class="eyebrow">Payment requested</div>
        <h2>Check your phone</h2>
        <p style="color:var(--muted);margin-top:12px;">
            A prompt was sent to <span class="mono"><?= e($order['buyer_phone']) ?></span> for
            <?= format_currency($order['total_amount']) ?>. Enter your M-Pesa PIN to complete the booking.
        </p>
        <p id="status-line" style="color:var(--teal);margin-top:20px;font-family:'IBM Plex Mono',monospace;font-size:13px;">
            Waiting for confirmation…
        </p>
    </div>
</div>

<script>
const orderId = <?= (int) $order_id ?>;
let attempts = 0;
const maxAttempts = 40; // ~2 minutes at 3s intervals — STK prompts expire around then anyway

function poll() {
    attempts++;
    fetch('check-payment-status.php?order_id=' + orderId)
        .then(r => r.json())
        .then(data => {
            if (data.status === 'paid') {
                window.location.href = 'confirmation.php?order_id=' + orderId;
            } else if (data.status === 'failed' || data.status === 'cancelled') {
                document.getElementById('status-line').textContent = 'Payment was not completed. You can try again.';
                document.getElementById('status-line').style.color = '#E8834A';
            } else if (attempts < maxAttempts) {
                setTimeout(poll, 3000);
            } else {
                document.getElementById('status-line').textContent = 'This is taking a while — check your phone, or try again.';
                document.getElementById('status-line').style.color = '#E8834A';
            }
        })
        .catch(() => {
            if (attempts < maxAttempts) setTimeout(poll, 3000);
        });
}

setTimeout(poll, 3000);
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
