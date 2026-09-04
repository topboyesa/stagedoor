<?php
// Finds 'pending' orders older than $minutes (buyer got an STK prompt but
// never entered their PIN, or the prompt timed out and Safaricom's callback
// never fired), releases the ticket stock they reserved, and marks them
// 'expired' so they're distinguishable from an active payment failure.
//
// Returns how many orders were expired, mostly useful for the CLI script's
// output — callers using this opportunistically (see event.php/checkout.php)
// can ignore the return value.
function expire_abandoned_orders($pdo, $minutes = 5) {
    $stmt = $pdo->prepare("
        SELECT id FROM orders
        WHERE status = 'pending' AND created_at < (NOW() - INTERVAL ? MINUTE)
    ");
    $stmt->execute([$minutes]);
    $expired_orders = $stmt->fetchAll();

    foreach ($expired_orders as $order) {
        $pdo->beginTransaction();
        try {
            $items_stmt = $pdo->prepare("SELECT ticket_type_id, quantity FROM order_items WHERE order_id = ?");
            $items_stmt->execute([$order['id']]);

            foreach ($items_stmt->fetchAll() as $item) {
                $pdo->prepare("UPDATE ticket_types SET quantity_sold = quantity_sold - ? WHERE id = ?")
                    ->execute([$item['quantity'], $item['ticket_type_id']]);
            }

            $pdo->prepare("UPDATE orders SET status = 'expired' WHERE id = ?")
                ->execute([$order['id']]);

            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log('Failed to expire order ' . $order['id'] . ': ' . $e->getMessage());
        }
    }

    return count($expired_orders);
}
