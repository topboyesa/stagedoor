<?php
require_once __DIR__ . '/../config/mpesa.php';

// Step 1: exchange your consumer key/secret for a short-lived access token.
// Every Daraja API call needs this token in its Authorization header.
function mpesa_get_access_token() {
    $ch = curl_init(MPESA_BASE_URL . '/oauth/v1/generate?grant_type=client_credentials');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Basic ' . base64_encode(MPESA_CONSUMER_KEY . ':' . MPESA_CONSUMER_SECRET),
        ],
        CURLOPT_TIMEOUT => 15,
    ]);
    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        error_log('M-Pesa auth failed: ' . $error);
        return null;
    }

    $data = json_decode($response, true);
    return $data['access_token'] ?? null;
}

// Step 2: trigger the STK Push — this is what makes the payment prompt
// pop up on the buyer's phone. Returns the raw Daraja response array,
// which includes a CheckoutRequestID we need to match up the callback later.
function mpesa_stk_push($phone, $amount, $account_reference, $description) {
    $access_token = mpesa_get_access_token();
    if (!$access_token) {
        return ['error' => 'Could not authenticate with M-Pesa.'];
    }

    $timestamp = date('YmdHis');
    $password = base64_encode(MPESA_SHORTCODE . MPESA_PASSKEY . $timestamp);

    // Daraja expects a Kenyan number in 2547XXXXXXXX format, no leading 0 or +.
    $phone = preg_replace('/^0/', '254', $phone);
    $phone = preg_replace('/^\+/', '', $phone);

    $payload = json_encode([
        'BusinessShortCode' => MPESA_SHORTCODE,
        'Password'          => $password,
        'Timestamp'         => $timestamp,
        'TransactionType'   => 'CustomerPayBillOnline',
        'Amount'            => (int) round($amount), // Daraja sandbox rejects decimals
        'PartyA'            => $phone,
        'PartyB'            => MPESA_SHORTCODE,
        'PhoneNumber'       => $phone,
        'CallBackURL'       => MPESA_CALLBACK_URL,
        'AccountReference'  => $account_reference,
        'TransactionDesc'   => $description,
    ]);

    $ch = curl_init(MPESA_BASE_URL . '/mpesa/stkpush/v1/processrequest');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $access_token,
            'Content-Type: application/json',
        ],
        CURLOPT_TIMEOUT => 20,
    ]);
    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        error_log('M-Pesa STK push failed: ' . $error);
        return ['error' => 'Could not reach M-Pesa.'];
    }

    return json_decode($response, true);
}

// Generates the actual ticket rows (codes) for every item on an order.
// Called only once payment is confirmed — not at order creation time —
// so nobody gets a valid ticket code before they've actually paid.
function issue_tickets_for_order($pdo, $order_id) {
    $stmt = $pdo->prepare("SELECT id FROM order_items WHERE order_id = ?");
    $stmt->execute([$order_id]);
    $order_items = $stmt->fetchAll();

    $ticket_stmt = $pdo->prepare("SELECT quantity FROM order_items WHERE id = ?");
    $insert_stmt = $pdo->prepare("INSERT INTO tickets (order_item_id, unique_code) VALUES (?, ?)");

    foreach ($order_items as $item) {
        $ticket_stmt->execute([$item['id']]);
        $qty = $ticket_stmt->fetchColumn();
        for ($i = 0; $i < $qty; $i++) {
            $insert_stmt->execute([$item['id'], generate_ticket_code()]);
        }
    }
}
