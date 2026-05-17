<?php
/**
 * payment_notification.php
 * Webhook dari Midtrans — update status pesanan otomatis
 *
 * Di dashboard Midtrans Sandbox:
 * Settings → Configuration → Payment Notification URL
 * Isi: http://localhost/hotel/payment_notification.php
 */

// Perbaiki path
require_once __DIR__ . 'config.php';
require_once __DIR__ . 'midtrans_config.php';

// Ambil payload JSON dari Midtrans
$payload = file_get_contents('php://input');
$notif = json_decode($payload, true);

// Log untuk debugging (opsional)
file_put_contents(__DIR__ . '/midtrans_log.txt', date('Y-m-d H:i:s') . ' - ' . $payload . PHP_EOL, FILE_APPEND);

if (!$notif) {
    http_response_code(400);
    exit('Invalid payload');
}

$order_id = $notif['order_id'] ?? '';
$transaction_status = $notif['transaction_status'] ?? '';
$fraud_status = $notif['fraud_status'] ?? '';
$payment_type = $notif['payment_type'] ?? '';

if (!$order_id) {
    http_response_code(400);
    exit('No order_id');
}

// ── Tentukan status baru berdasarkan respons Midtrans ──
$new_status = 'pending';

if ($transaction_status == 'capture') {
    if ($fraud_status == 'accept') {
        $new_status = 'confirmed';
    }
} elseif ($transaction_status == 'settlement') {
    $new_status = 'confirmed';
} elseif ($transaction_status == 'pending') {
    $new_status = 'pending';
} elseif ($transaction_status == 'deny' || $transaction_status == 'cancel' || $transaction_status == 'expire') {
    $new_status = 'cancelled';
}

// ── Update DB ──
$order_id_esc = $conn->real_escape_string($order_id);
$status_esc = $conn->real_escape_string($new_status);
$payment_type_esc = $conn->real_escape_string($payment_type);

$conn->query("UPDATE pemesanan SET status = '$status_esc', payment_type = '$payment_type_esc' WHERE kode_booking = '$order_id_esc'");

http_response_code(200);
echo json_encode(['status' => 'ok', 'new_status' => $new_status]);
?>