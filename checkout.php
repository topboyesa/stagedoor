<?php
session_start();
require __DIR__ . '/config/database.php';
require __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/mailer.php';

if (empty($_SESSION['cart']['items'])) {
    header('Location: index.php');
    exit;
}

$event_id = (int) $_SESSION['cart']['event_id'];
$cart_items = $_SESSION['cart']['items']; // [ticket_type_id => qty]

$stmt = $pdo->prepare("SELECT * FROM events WHERE id = ?");
$stmt->execute([$event_id]);
$event = $stmt->fetch();

$tier_ids = array_keys($cart_items);
$placeholders = implode(',', array_fill(0, count($tier_ids), '?'));
$stmt = $pdo->prepare("SELECT * FROM ticket_types WHERE id IN ($placeholders)");
$stmt->execute($tier_ids);
$tiers_by_id = [];
foreach ($stmt->fetchAll() as $tier) {
    $tiers_by_id[$tier['id']] = $tier;
}

$line_items = [];
$total = 0;
foreach ($cart_items as $tier_id => $qty) {
    if (!isset($tiers_by_id[$tier_id])) continue;
    $tier = $tiers_by_id[$tier_id];
    $subtotal = $tier['price'] * $qty;
    $total += $subtotal;
    $line_items[] = [
        'tier'     => $tier,
        'qty'      => $qty,
        'subtotal' => $subtotal,
    ];
}
$service_fee = round($total * 0.02); // flat 2% placeholder — adjust to your actual fee model
$grand_total = $total + $service_fee;

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name  = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    if ($name === '') $errors[] = 'Enter your full name.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email.';
    if ($phone === '') $errors[] = 'Enter a phone number.';

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // Lock the ticket_type rows so two simultaneous checkouts
            // can't both oversell the last few tickets.
            $order_id = null;
            $stmt = $pdo->prepare(
                "INSERT INTO orders (event_id, buyer_name, buyer_email, buyer_phone, total_amount, status)
                 VALUES (?, ?, ?, ?, ?, 'paid')" // 'paid' is a placeholder until real payment integration lands
            );
            $stmt->execute([$event_id, $name, $email, $phone, $grand_total]);
            $order_id = $pdo->lastInsertId();

            foreach ($line_items as $item) {
                $tier_id = $item['tier']['id'];

                $lock_stmt = $pdo->prepare("SELECT quantity_available, quantity_sold FROM ticket_types WHERE id = ? FOR UPDATE");
                $lock_stmt->execute([$tier_id]);
                $locked = $lock_stmt->fetch();

                $remaining = $locked['quantity_available'] - $locked['quantity_sold'];
                if ($remaining < $item['qty']) {
                    throw new Exception('Not enough tickets left for ' . $item['tier']['name']);
                }

                $update_stmt = $pdo->prepare("UPDATE ticket_types SET quantity_sold = quantity_sold + ? WHERE id = ?");
                $update_stmt->execute([$item['qty'], $tier_id]);

                $item_stmt = $pdo->prepare(
                    "INSERT INTO order_items (order_id, ticket_type_id, quantity, unit_price) VALUES (?, ?, ?, ?)"
                );
                $item_stmt->execute([$order_id, $tier_id, $item['qty'], $item['tier']['price']]);
                $order_item_id = $pdo->lastInsertId();

                // One row per physical ticket so each gets its own scannable code.
                $ticket_stmt = $pdo->prepare(
                    "INSERT INTO tickets (order_item_id, unique_code) VALUES (?, ?)"
                );
                for ($i = 0; $i < $item['qty']; $i++) {
                    $ticket_stmt->execute([$order_item_id, generate_ticket_code()]);
                }
            }

            $pdo->commit();

            // Fetch the generated ticket codes so the email can list them.
            $ticket_stmt = $pdo->prepare("
                SELECT t.unique_code, tt.name AS tier_name
                FROM tickets t
                JOIN order_items oi ON oi.id = t.order_item_id
                JOIN ticket_types tt ON tt.id = oi.ticket_type_id
                WHERE oi.order_id = ?
                ORDER BY t.id ASC
            ");
            $ticket_stmt->execute([$order_id]);
            $issued_tickets = $ticket_stmt->fetchAll();

            send_ticket_confirmation([
                'buyer_email'          => $email,
                'buyer_name'           => $name,
                'event_title'          => $event['title'],
                'event_date_formatted' => format_event_date($event['event_date']) . ' · Doors ' . format_event_time($event['event_date']),
                'venue'                => $event['venue'],
                'total_formatted'      => format_currency($grand_total),
            ], $line_items, $issued_tickets);
            // Note: send_ticket_confirmation() never throws — if it fails,
            // the order is still saved and the buyer still sees their
            // tickets on confirmation.php, they just won't have the email.

            unset($_SESSION['cart']);
            header('Location: confirmation.php?order_id=' . $order_id);
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = 'Checkout failed: ' . $e->getMessage();
        }
    }
}

$page_title = 'Checkout';
require __DIR__ . '/includes/header.php';
?>

<div class="page">
    <div class="section" style="margin-top:24px;">
        <h2>Checkout</h2>
    </div>

    <?php foreach ($errors as $err): ?>
        <p class="error"><?= e($err) ?></p>
    <?php endforeach; ?>

    <form method="post">
        <div class="field-group">
            <label>Full name</label>
            <input type="text" name="name" value="<?= e($_POST['name'] ?? '') ?>" placeholder="Amara Njoroge">
        </div>
        <div class="field-row">
            <div class="field-group">
                <label>Email</label>
                <input type="email" name="email" value="<?= e($_POST['email'] ?? '') ?>" placeholder="amara@email.com">
            </div>
            <div class="field-group">
                <label>Phone</label>
                <input type="text" name="phone" value="<?= e($_POST['phone'] ?? '') ?>" placeholder="07XX XXX XXX">
            </div>
        </div>

        <div class="stub">
            <div class="stub-main">
                <div class="stub-label">Order summary</div>
                <div class="stub-event"><?= e($event['title']) ?></div>
                <div class="stub-details">
                    <div><?= e(format_event_date($event['event_date'])) ?> · Doors <?= e(format_event_time($event['event_date'])) ?></div>
                    <div><?= e($event['venue']) ?></div>
                </div>
                <?php foreach ($line_items as $item): ?>
                    <div class="stub-line">
                        <span><?= $item['qty'] ?> × <?= e($item['tier']['name']) ?></span>
                        <span class="mono"><?= format_currency($item['subtotal']) ?></span>
                    </div>
                <?php endforeach; ?>
                <div class="stub-line">
                    <span>Service fee</span>
                    <span class="mono"><?= format_currency($service_fee) ?></span>
                </div>
                <div class="stub-line total">
                    <span>Total</span>
                    <span class="mono"><?= format_currency($grand_total) ?></span>
                </div>
            </div>
            <div class="stub-stub">
                <div class="mono" style="font-size:11px;color:#5c5646;">
                    <?= array_sum($cart_items) ?> ticket<?= array_sum($cart_items) === 1 ? '' : 's' ?>
                </div>
            </div>
        </div>

        <div class="summary-bar">
            <div class="total">Total<span class="mono"><?= format_currency($grand_total) ?></span></div>
            <button type="submit" class="btn-primary">Confirm & pay</button>
        </div>
    </form>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
