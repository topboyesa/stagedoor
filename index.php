<?php
require __DIR__ . '/config/database.php';
require __DIR__ . '/includes/functions.php';

$page_title = 'Upcoming events';

// Pull each published event along with its cheapest ticket price,
// so the card can show "From KSh X" without a separate query per event.
$stmt = $pdo->query("
    SELECT e.id, e.title, e.event_date, e.venue,
           MIN(t.price) AS from_price
    FROM events e
    LEFT JOIN ticket_types t ON t.event_id = e.id
    WHERE e.status = 'published'
    GROUP BY e.id
    ORDER BY e.event_date ASC
");
$events = $stmt->fetchAll();

require __DIR__ . '/includes/header.php';
?>

<div class="page">
    <div class="section" style="margin-top:24px;">
        <h2>Upcoming events</h2>
    </div>

    <?php if (empty($events)): ?>
        <p style="color:var(--muted);">No events published yet.</p>
    <?php else: ?>
        <?php foreach ($events as $event): ?>
            <a class="event-card" href="event.php?id=<?= (int) $event['id'] ?>">
                <div class="eyebrow"><?= e(format_event_date($event['event_date'])) ?></div>
                <h2><?= e($event['title']) ?></h2>
                <div class="meta"><?= e($event['venue']) ?></div>
                <?php if ($event['from_price'] !== null): ?>
                    <div class="price-from">From <?= format_currency($event['from_price']) ?></div>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
