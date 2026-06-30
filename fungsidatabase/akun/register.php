<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');
require_once '../../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  echo json_encode(['status' => 'error', 'message' => 'Method tidak valid.']);
  exit;
}

$username         = trim($_POST['username'] ?? '');
$email            = trim($_POST['email'] ?? '');
$password         = $_POST['password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';

if (!$username || !$email || !$password || !$confirm_password) {
  echo json_encode(['status' => 'error', 'message' => 'Semua field wajib diisi.']);
  exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
  echo json_encode(['status' => 'error', 'message' => 'Format email tidak valid.']);
  exit;
}
if (strlen($username) < 3) {
  echo json_encode(['status' => 'error', 'message' => 'Username minimal 3 karakter.']);
  exit;
}
if (strlen($password) < 6) {
  echo json_encode(['status' => 'error', 'message' => 'Password minimal 6 karakter.']);
  exit;
}
if ($password !== $confirm_password) {
  echo json_encode(['status' => 'error', 'message' => 'Password dan konfirmasi tidak cocok.']);
  exit;
}

// Cek username atau email sudah ada di hotel yang sama
$stmt = $conn->prepare('SELECT id, username, email, password, hotel_id FROM users WHERE (username = ? OR email = ?) AND role = "user" AND hotel_id = ? LIMIT 1');
$stmt->bind_param('ssi', $username, $email, $hotel_id);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
  echo json_encode(['status' => 'error', 'message' => 'Username atau email sudah terdaftar.']);
  $stmt->close();
  $conn->close();
  exit;
}
$stmt->close();

// Simpan user baru dengan hotel_id dari env
$hashed = password_hash($password, PASSWORD_BCRYPT);
$stmt = $conn->prepare('INSERT INTO users (username, email, password, hotel_id) VALUES (?, ?, ?, ?)');
$stmt->bind_param('sssi', $username, $email, $hashed, $hotel_id);
if ($stmt->execute()) {
  echo json_encode(['status' => 'success', 'message' => 'Akun berhasil dibuat! Silakan login.']);
} else {
  echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan akun: ' . $stmt->error]);
}
$stmt->close();
$conn->close();
?>
