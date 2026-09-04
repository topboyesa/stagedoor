<?php
// Run this from the command line (not a browser) — e.g.:
//   php cleanup-expired-orders.php
//
// event.php and checkout.php already call expire_abandoned_orders()
// opportunistically on every page load, which is enough for most traffic
// levels. This script exists for when you want cleanup to happen on a
// reliable schedule regardless of whether anyone is actually browsing —
// see README.md for how to schedule it with Windows Task Scheduler.

require __DIR__ . '/config/database.php';
require __DIR__ . '/includes/cleanup.php';

$count = expire_abandoned_orders($pdo, 5);

echo date('Y-m-d H:i:s') . " — expired {$count} abandoned order(s)." . PHP_EOL;
