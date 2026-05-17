<?php
// test_midtrans.php
require_once 'midtrans_config.php';

$auth = base64_encode(MIDTRANS_SERVER_KEY . ':');

// Data transaksi test
$data = [
    'transaction_details' => [
        'order_id' => 'TEST-' . time(),
        'gross_amount' => 10000
    ],
    'customer_details' => [
        'first_name' => 'Test',
        'email' => 'test@example.com'
    ]
];

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => MIDTRANS_API_URL,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($data),
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: Basic ' . $auth,
        'Accept: application/json',
    ],
    CURLOPT_SSL_VERIFYPEER => false,
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: " . $http_code . "<br><br>";

if ($http_code == 201) {
    echo "✅ KEY VALID!<br>";
    $result = json_decode($response, true);
    echo "Snap Token: " . ($result['token'] ?? 'Tidak ada');
} elseif ($http_code == 401) {
    echo "❌ KEY TIDAK VALID!<br>";
    echo "Silakan login ke dashboard dan ambil key baru.<br>";
} else {
    echo "Response: " . $response;
}
?>