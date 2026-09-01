<?php
$host   = getenv('MYSQL_HOST')     ?: 'mysql';      // ← nama service mysql
$user   = getenv('MYSQL_USER')     ?: 'hoteluser';  // ← user dari compose
$pass   = getenv('MYSQL_PASSWORD') ?: 'hotelpass';  // ← password dari compose
$db     = getenv('MYSQL_DATABASE') ?: 'Hotel_Grand'; // ← database dari compose
$port   = getenv('MYSQL_PORT')     ?: 3306;
$hotel_id = (int)(getenv('HOTEL_ID') ?: 1);

$conn = new mysqli($host, $user, $pass, $db, (int)$port);
if ($conn->connect_error) {
    die(json_encode(['error' => 'DB connect failed: ' . $conn->connect_error]));
}
$conn->set_charset('utf8mb4');