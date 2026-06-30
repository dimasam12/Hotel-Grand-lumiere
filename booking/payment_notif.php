<?php
/**
 * payment_notification.php
 * Webhook Midtrans
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/midtrans_config.php';

// Ambil JSON
$json = file_get_contents("php://input");

// Simpan log
file_put_contents(
    __DIR__ . "/midtrans_log.txt",
    date("Y-m-d H:i:s") . PHP_EOL .
    $json . PHP_EOL .
    "----------------------------------------" . PHP_EOL,
    FILE_APPEND
);

$notif = json_decode($json, true);

if (!$notif) {
    http_response_code(400);
    exit("Invalid JSON");
}

$order_id            = $notif['order_id'] ?? '';
$status_code         = $notif['status_code'] ?? '';
$gross_amount        = $notif['gross_amount'] ?? '';
$signature_key       = $notif['signature_key'] ?? '';
$transaction_status  = $notif['transaction_status'] ?? '';
$fraud_status        = $notif['fraud_status'] ?? '';
$payment_type        = $notif['payment_type'] ?? '';

if (empty($order_id)) {
    http_response_code(400);
    exit("Order ID kosong");
}

/*
|--------------------------------------------------------------------------
| Verifikasi Signature Midtrans
|--------------------------------------------------------------------------
*/

$my_signature = hash(
    'sha512',
    $order_id .
    $status_code .
    $gross_amount .
    MIDTRANS_SERVER_KEY
);

if ($signature_key !== $my_signature) {
    http_response_code(403);
    exit("Signature tidak valid");
}

/*
|--------------------------------------------------------------------------
| Tentukan status
|--------------------------------------------------------------------------
*/

$status = "pending";
$payment_status = "unpaid";

switch ($transaction_status) {

    case "capture":

        if ($fraud_status == "accept") {
            $status = "confirmed";
            $payment_status = "paid";
        }

        break;

    case "settlement":

        $status = "confirmed";
        $payment_status = "paid";

        break;

    case "pending":

        $status = "pending";
        $payment_status = "unpaid";

        break;

    case "deny":
    case "cancel":
    case "expire":
    case "failure":

        $status = "cancelled";
        $payment_status = "unpaid";

        break;
}

/*
|--------------------------------------------------------------------------
| Update Database
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    UPDATE pemesanan
    SET
        status = ?,
        payment_status = ?,
        payment_type = ?
    WHERE kode_booking = ?
");

$stmt->bind_param(
    "ssss",
    $status,
    $payment_status,
    $payment_type,
    $order_id
);

$stmt->execute();

$stmt->close();

http_response_code(200);

echo json_encode([
    "success" => true,
    "order_id" => $order_id,
    "status" => $status,
    "payment_status" => $payment_status
]);
