<?php
session_start();
header('Content-Type: application/json');
require_once '../config.php';
require_once 'midtrans_config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Metode tidak diizinkan']);
    exit;
}

$nama_tamu         = trim($_POST['nama_tamu'] ?? '');
$email_tamu        = trim($_POST['email_tamu'] ?? '');
$tipe_kamar        = trim($_POST['tipe_kamar'] ?? '');
$harga_per_malam   = intval($_POST['harga_per_malam'] ?? 0);
$check_in          = trim($_POST['check_in'] ?? '');
$check_out         = trim($_POST['check_out'] ?? '');
$jumlah_malam      = intval($_POST['jumlah_malam'] ?? 1);
$jumlah_tamu       = intval($_POST['jumlah_tamu'] ?? 1);
$permintaan_khusus = trim($_POST['permintaan_khusus'] ?? '') ?: null;

$errors = [];
if (!$nama_tamu)  $errors[] = 'Nama tamu wajib diisi';
if (!$email_tamu || !filter_var($email_tamu, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email tidak valid';
if (!$check_in)   $errors[] = 'Check-in wajib diisi';
if (!$check_out)  $errors[] = 'Check-out wajib diisi';
if ($check_out <= $check_in) $errors[] = 'Check-out harus setelah check-in';
if ($jumlah_malam < 1)  $errors[] = 'Jumlah malam tidak valid';
if ($harga_per_malam <= 0) $errors[] = 'Harga tidak valid';

$tipe_valid = ['Deluxe Room', 'Junior Suite', 'Deluxe Suite', 'Executive Room', 'Presidential Suite'];
if (!in_array($tipe_kamar, $tipe_valid)) $errors[] = 'Tipe kamar tidak valid';

if ($errors) {
    echo json_encode(['success' => false, 'message' => implode(', ', $errors)]);
    exit;
}

// Hitung server-side
$subtotal = $harga_per_malam * $jumlah_malam;
$pajak    = (int)round($subtotal * 0.15);
$total    = $subtotal + $pajak;

$user_id      = intval($_SESSION['user_id'] ?? 0);
$kode_booking = 'GL-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 6));

$stmt = $conn->prepare("
    INSERT INTO pemesanan
        (user_id, kode_booking, nama_tamu, email_tamu, tipe_kamar, harga_per_malam,
         check_in, check_out, jumlah_malam, jumlah_tamu, subtotal, pajak, total,
         status, permintaan_khusus)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?)
");

if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'DB error: ' . $conn->error]);
    exit;
}

$stmt->bind_param('issssissiiiiis',
    $user_id, $kode_booking, $nama_tamu, $email_tamu,
    $tipe_kamar, $harga_per_malam,
    $check_in, $check_out, $jumlah_malam, $jumlah_tamu,
    $subtotal, $pajak, $total, $permintaan_khusus
);

if (!$stmt->execute()) {
    echo json_encode(['success' => false, 'message' => 'Gagal simpan: ' . $stmt->error]);
    exit;
}

$booking_id = $conn->insert_id;
$stmt->close();

// Midtrans Snap Token
$params = [
    'transaction_details' => [
        'order_id'     => $kode_booking,
        'gross_amount' => $total,
    ],
    'customer_details' => [
        'first_name' => $nama_tamu,
        'email'      => $email_tamu,
    ],
    'item_details' => [
        [
            'id'       => strtolower(str_replace(' ', '-', $tipe_kamar)),
            'price'    => $harga_per_malam,
            'quantity' => $jumlah_malam,
            'name'     => $tipe_kamar . ' (' . $jumlah_malam . ' malam)',
        ],
        [
            'id'       => 'pajak-layanan',
            'price'    => $pajak,
            'quantity' => 1,
            'name'     => 'Pajak & Layanan (15%)',
        ],
    ],
    'callbacks' => [
        'finish'  => APP_URL . '/payment_return.php?booking=' . $kode_booking,
        'error'   => APP_URL . '/payment_return.php?booking=' . $kode_booking . '&status=error',
        'pending' => APP_URL . '/payment_return.php?booking=' . $kode_booking . '&status=pending',
    ],
];

$auth = base64_encode(MIDTRANS_SERVER_KEY . ':');
$ch   = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => MIDTRANS_API_URL,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($params),
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Authorization: Basic ' . $auth,
        'Accept: application/json',
    ],
    CURLOPT_SSL_VERIFYPEER => false,
]);

$response  = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_err  = curl_error($ch);
curl_close($ch);

if ($curl_err) {
    echo json_encode(['success' => false, 'message' => 'cURL error: ' . $curl_err]);
    exit;
}

$midtrans = json_decode($response, true);

if ($http_code !== 201 || empty($midtrans['token'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Midtrans error: ' . ($midtrans['error_messages'][0] ?? $response),
    ]);
    exit;
}

$token = $conn->real_escape_string($midtrans['token']);
$conn->query("UPDATE pemesanan SET snap_token = '$token' WHERE id = $booking_id");

echo json_encode([
    'success'      => true,
    'snap_token'   => $midtrans['token'],
    'kode_booking' => $kode_booking,
]);
