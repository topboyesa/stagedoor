<?php
// Safaricom's servers POST here directly — not the buyer's browser.
// This must be a real, publicly reachable HTTPS URL (see MPESA_CALLBACK_URL
// in config/mpesa.php). It never redirects or shows HTML; it just logs the
// result and responds with a small JSON acknowledgment, which Daraja requires.

require __DIR__ . '/config/database.php';
require __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/mpesa.php';
require __DIR__ . '/includes/mailer.php';

$raw = file_get_contents('php://input');
error_log('M-Pesa callback received: ' . $raw); // keep this while testing — remove/reduce in production

$data = json_decode($raw, true);
$callback = $data['Body']['stkCallback'] ?? null;

if (!$callback) {
    http_response_code(400);
    echo json_encode(['ResultCode' => 1, 'ResultDesc' => 'Invalid payload']);
    exit;
}

$checkout_request_id = $callback['CheckoutRequestID'] ?? null;
$result_code = $callback['ResultCode'] ?? null;

$stmt = $pdo->prepare("
    SELECT o.*, e.title AS event_title, e.event_date, e.venue
    FROM orders o
    JOIN events e ON e.id = o.event_id
    WHERE o.mpesa_checkout_request_id = ?
");
$stmt->execute([$checkout_request_id]);
$order = $stmt->fetch();

if (!$order) {
    error_log('M-Pesa callback: no matching order for CheckoutRequestID ' . $checkout_request_id);
    http_response_code(200); // still acknowledge — Safaricom will retry otherwise
    echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
    exit;
}

if ($result_code === 0) {
    // Payment succeeded. Mark paid, issue the actual ticket codes now, email them.
    $pdo->prepare("UPDATE orders SET status = 'paid' WHERE id = ?")->execute([$order['id']]);
    issue_tickets_for_order($pdo, $order['id']);

    $ticket_stmt = $pdo->prepare("
        SELECT t.unique_code, tt.name AS tier_name
        FROM tickets t
        JOIN order_items oi ON oi.id = t.order_item_id
        JOIN ticket_types tt ON tt.id = oi.ticket_type_id
        WHERE oi.order_id = ?
        ORDER BY t.id ASC
    ");
    $ticket_stmt->execute([$order['id']]);
    $issued_tickets = $ticket_stmt->fetchAll();

    send_ticket_confirmation([
        'buyer_email'          => $order['buyer_email'],
        'buyer_name'           => $order['buyer_name'],
        'event_title'          => $order['event_title'],
        'event_date_formatted' => format_event_date($order['event_date']) . ' · Doors ' . format_event_time($order['event_date']),
        'venue'                => $order['venue'],
        'total_formatted'      => format_currency($order['total_amount']),
    ], [], $issued_tickets);

} else {
    // Payment failed or was cancelled by the buyer. Release the reserved stock.
    $pdo->prepare("UPDATE orders SET status = 'failed' WHERE id = ?")->execute([$order['id']]);

    $items_stmt = $pdo->prepare("SELECT ticket_type_id, quantity FROM order_items WHERE order_id = ?");
    $items_stmt->execute([$order['id']]);
    foreach ($items_stmt->fetchAll() as $item) {
        $pdo->prepare("UPDATE ticket_types SET quantity_sold = quantity_sold - ? WHERE id = ?")
            ->execute([$item['quantity'], $item['ticket_type_id']]);
    }
}

// Daraja expects exactly this shape back, or it will keep retrying the callback.
http_response_code(200);
echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
