<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');
session_start();
require_once '../../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Method tidak valid.']);
    exit;
}

$identifier = trim($_POST['identifier'] ?? '');
$password   = $_POST['password'] ?? '';

if (empty($identifier) || empty($password)) {
    echo json_encode(['status' => 'error', 'message' => 'Username/email dan password wajib diisi.']);
    exit;
}

// Cegah brute force sederhana
if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
}
if ($_SESSION['login_attempts'] > 5) {
    echo json_encode(['status' => 'error', 'message' => 'Terlalu banyak percobaan. Coba lagi nanti.']);
    exit;
}

$stmt = $conn->prepare('SELECT id, username, email, password, role FROM users WHERE username = ? OR email = ? LIMIT 1');
$stmt->bind_param('ss', $identifier, $identifier);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['login_attempts']++;
    echo json_encode(['status' => 'error', 'message' => 'Akun tidak ditemukan.']);
    $stmt->close();
    $conn->close();
    exit;
}

$user = $result->fetch_assoc();
$stmt->close();

if (!password_verify($password, $user['password'])) {
    $_SESSION['login_attempts']++;
    echo json_encode(['status' => 'error', 'message' => 'Password salah.']);
    $conn->close();
    exit;
}

// Reset attempts setelah login berhasil
$_SESSION['login_attempts'] = 0;

// Regenerate session ID untuk keamanan
session_regenerate_id(true);

// Set session dengan konsistensi tinggi
$_SESSION['user_id']   = $user['id'];
$_SESSION['username']  = $user['username'];
$_SESSION['email']     = $user['email'];
$_SESSION['role']      = $user['role'];
$_SESSION['login_time'] = time();        // Tambahan untuk keamanan

// Redirect berdasarkan role
if ($user['role'] === 'admin') {
    $redirect = 'admin/dashboard.php';
} else {
    $redirect = 'mainpage.php';
}

echo json_encode([
    'status'   => 'success',
    'message'  => 'Login berhasil! Mengalihkan...',
    'redirect' => $redirect,
    'role'     => $user['role']
]);

$conn->close();
?>