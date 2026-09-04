<?php
require __DIR__ . '/config/database.php';

header('Content-Type: application/json');

$order_id = isset($_GET['order_id']) ? (int) $_GET['order_id'] : 0;

$stmt = $pdo->prepare("SELECT status FROM orders WHERE id = ?");
$stmt->execute([$order_id]);
$order = $stmt->fetch();

if (!$order) {
    http_response_code(404);
    echo json_encode(['status' => 'not_found']);
    exit;
}

echo json_encode(['status' => $order['status']]);
