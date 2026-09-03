<?php
require_once __DIR__ . '/../config/email.php';

// Sends the order confirmation + ticket codes to the buyer via Brevo's API.
// Returns true on success, false on failure (never throws — a failed email
// shouldn't break checkout, since the order is already saved).
function send_ticket_confirmation($order, $line_items, $tickets) {
    $rows = '';
    foreach ($tickets as $ticket) {
        $rows .= '
            <tr>
                <td style="padding:12px 16px;border-bottom:1px solid #DCCBA2;">
                    <div style="font-size:13px;color:#7A4E06;text-transform:uppercase;letter-spacing:1px;">' . htmlspecialchars($ticket['tier_name']) . '</div>
                    <div style="font-family:monospace;font-size:14px;margin-top:4px;">#' . htmlspecialchars($ticket['unique_code']) . '</div>
                </td>
            </tr>';
    }

    $html = '
    <div style="background:#0E0E12;padding:32px 0;font-family:sans-serif;">
        <div style="max-width:480px;margin:0 auto;background:#F3EAD9;color:#0E0E12;">
            <div style="padding:28px;">
                <div style="font-size:11px;text-transform:uppercase;letter-spacing:2px;color:#7A4E06;font-weight:600;margin-bottom:6px;">Order confirmed</div>
                <h1 style="font-size:22px;margin:0 0 16px;">' . htmlspecialchars($order['event_title']) . '</h1>
                <p style="font-family:monospace;font-size:13px;color:#5c5646;margin:0 0 20px;">
                    ' . htmlspecialchars($order['event_date_formatted']) . ' &middot; ' . htmlspecialchars($order['venue']) . '
                </p>
                <table style="width:100%;border-collapse:collapse;">' . $rows . '</table>
                <p style="font-size:13px;color:#5c5646;margin-top:20px;">
                    Show these codes at the door. Order total: ' . htmlspecialchars($order['total_formatted']) . '
                </p>
            </div>
        </div>
    </div>';

    $payload = json_encode([
        'sender'      => ['name' => MAIL_FROM_NAME, 'email' => MAIL_FROM_EMAIL],
        'to'          => [['email' => $order['buyer_email'], 'name' => $order['buyer_name']]],
        'subject'     => 'Your tickets for ' . $order['event_title'],
        'htmlContent' => $html,
    ]);

    $ch = curl_init('https://api.brevo.com/v3/smtp/email');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'api-key: ' . BREVO_API_KEY,
            'Content-Type: application/json',
            'Accept: application/json',
        ],
        CURLOPT_TIMEOUT => 10,
    ]);

    $response   = curl_exec($ch);
    $http_code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($curl_error || $http_code >= 300) {
        error_log('Brevo email failed: ' . ($curl_error ?: $response));
        return false;
    }

    return true;
}
