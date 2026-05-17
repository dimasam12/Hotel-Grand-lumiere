<?php
session_start();
header('Content-Type: application/json');
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$action = $_POST['action'] ?? '';

if ($action === 'update_status') {
    $id = intval($_POST['id']);
    $status = $_POST['status'];
    $stmt = $conn->prepare("UPDATE pemesanan SET status = ? WHERE id = ?");
    $stmt->bind_param('si', $status, $id);
    echo json_encode(['success' => $stmt->execute()]);
    exit;
}

if ($action === 'change_role') {
    $id = intval($_POST['id']);
    $role = $_POST['role'];
    $stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
    $stmt->bind_param('si', $role, $id);
    echo json_encode(['success' => $stmt->execute()]);
    exit;
}

echo json_encode(['error' => 'Invalid action']);
?>