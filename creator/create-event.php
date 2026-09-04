<?php
session_start();
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/../includes/auth.php';
require_creator_login();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $event_date = trim($_POST['event_date'] ?? '');
    $venue = trim($_POST['venue'] ?? '');

    $tier_names = $_POST['tier_name'] ?? [];
    $tier_prices = $_POST['tier_price'] ?? [];
    $tier_qtys = $_POST['tier_qty'] ?? [];

    if ($title === '') $errors[] = 'Enter an event title.';
    if ($venue === '') $errors[] = 'Enter a venue.';
    if (!strtotime($event_date)) $errors[] = 'Enter a valid date and time.';

    // Collect only tiers where a name was actually filled in.
    $tiers = [];
    foreach ($tier_names as $i => $name) {
        $name = trim($name);
        if ($name === '') continue;
        $price = (float) ($tier_prices[$i] ?? 0);
        $qty = (int) ($tier_qtys[$i] ?? 0);
        if ($price <= 0 || $qty <= 0) {
            $errors[] = "\"$name\" needs a price and quantity greater than zero.";
            continue;
        }
        $tiers[] = ['name' => $name, 'price' => $price, 'qty' => $qty];
    }
    if (empty($tiers)) $errors[] = 'Add at least one ticket tier.';

    if (empty($errors)) {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO events (creator_id, title, description, event_date, venue, status)
                 VALUES (?, ?, ?, ?, ?, 'published')"
            );
            $stmt->execute([
                current_creator_id(),
                $title,
                $description,
                date('Y-m-d H:i:s', strtotime($event_date)),
                $venue,
            ]);
            $event_id = $pdo->lastInsertId();

            $tier_stmt = $pdo->prepare(
                "INSERT INTO ticket_types (event_id, name, price, quantity_available) VALUES (?, ?, ?, ?)"
            );
            foreach ($tiers as $tier) {
                $tier_stmt->execute([$event_id, $tier['name'], $tier['price'], $tier['qty']]);
            }

            $pdo->commit();
            header('Location: dashboard.php');
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = 'Could not create event: ' . $e->getMessage();
        }
    }
}

$page_title = 'New event';
$base = '../';
require __DIR__ . '/../includes/header.php';
?>

<div class="page">
    <div class="section" style="margin-top:24px;">
        <h2>New event</h2>
    </div>

    <?php foreach ($errors as $err): ?>
        <p class="error"><?= e($err) ?></p>
    <?php endforeach; ?>

    <form method="post">
        <div class="field-group">
            <label>Event title</label>
            <input type="text" name="title" value="<?= e($_POST['title'] ?? '') ?>" placeholder="Neon Hours — The Wachira Sessions">
        </div>
        <div class="field-group">
            <label>Description</label>
            <input type="text" name="description" value="<?= e($_POST['description'] ?? '') ?>" placeholder="A night of live sets...">
        </div>
        <div class="field-row">
            <div class="field-group">
                <label>Date & time</label>
                <input type="datetime-local" name="event_date" value="<?= e($_POST['event_date'] ?? '') ?>">
            </div>
            <div class="field-group">
                <label>Venue</label>
                <input type="text" name="venue" value="<?= e($_POST['venue'] ?? '') ?>" placeholder="The Alchemist, Westlands">
            </div>
        </div>

        <div class="section">
            <h2 style="font-size:16px;margin-bottom:12px;">Ticket tiers</h2>
            <p style="color:var(--muted);font-size:13px;margin-bottom:16px;">
                Leave a row's name blank to skip it. Add more rows if you need more than 4 tiers.
            </p>

            <?php for ($i = 0; $i < 4; $i++): ?>
                <div class="tier-row" style="margin-bottom:8px;">
                    <div class="tier-info" style="flex:1;">
                        <input type="text" name="tier_name[]" placeholder="e.g. General admission"
                               style="background:transparent;border:none;color:var(--white);font-size:14px;width:100%;padding:4px 0;">
                    </div>
                    <div class="tier-right" style="gap:12px;">
                        <input type="number" name="tier_price[]" placeholder="Price (KSh)" min="0" step="1"
                               style="width:110px;background:var(--ink);border:1px solid var(--line);color:var(--white);padding:8px;font-family:'IBM Plex Mono',monospace;font-size:13px;">
                        <input type="number" name="tier_qty[]" placeholder="Qty" min="0" step="1"
                               style="width:80px;background:var(--ink);border:1px solid var(--line);color:var(--white);padding:8px;font-family:'IBM Plex Mono',monospace;font-size:13px;">
                    </div>
                </div>
            <?php endfor; ?>
        </div>

        <button type="submit" class="btn-primary">Publish event</button>
    </form>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
