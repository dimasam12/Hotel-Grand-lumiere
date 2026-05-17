<?php
require_once 'config.php';

// Ganti dengan password yang Anda inginkan
$password_baru = 'admin120702';

// Hash password otomatis
$hash = password_hash($password_baru, PASSWORD_BCRYPT);

// Update atau insert admin
$sql = "INSERT INTO users (username, email, password, role) 
        VALUES ('admin', 'admin@gmail.com', '$hash', 'admin')
        ON DUPLICATE KEY UPDATE 
        password = '$hash', 
        email = 'admin@gmail.com',
        role = 'admin'";

if ($conn->query($sql)) {
    echo "✅ BERHASIL!<br><br>";
    echo "Username: <strong>admin</strong><br>";
    echo "Email: <strong>admin@gmail.com</strong><br>";
    echo "Password: <strong>$password_baru</strong><br>";
    echo "Hash: $hash<br><br>";
    echo "<hr>";
    echo "Sekarang login di: <a href='index.html'>http://localhost/hotel/index.html</a>";
} else {
    echo "❌ Gagal: " . $conn->error;
}

$conn->close();
?>