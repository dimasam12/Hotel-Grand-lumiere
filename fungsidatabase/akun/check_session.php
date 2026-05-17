<?php
header('Content-Type: application/json');
session_start();

if (isset($_SESSION['user_id'])) {
  echo json_encode([
    'loggedIn' => true,
    'user_id'  => $_SESSION['user_id'],
    'username' => $_SESSION['username'],
    'email'    => $_SESSION['email'],
    'role'     => $_SESSION['role'],
  ]);
} else {
  echo json_encode(['loggedIn' => false]);
}