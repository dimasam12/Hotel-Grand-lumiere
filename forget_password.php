<?php
include 'koneksi.php';
$pesan = '';
$tipe = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    
    $cek = mysqli_query($conn, "SELECT * FROM users WHERE email='$email'");
    
    if (mysqli_num_rows($cek) > 0) {
        $token = bin2hex(random_bytes(32));
        $expire = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        mysqli_query($conn, "UPDATE users SET reset_token='$token', reset_expire='$expire' WHERE email='$email'");
        
        $link = "http://" . $_SERVER['HTTP_HOST'] . "/reset_password.php?token=$token";
        $pesan = "Link reset password kamu: <a href='$link'>Klik di sini</a>";
        $tipe = 'success';
    } else {
        $pesan = "Email tidak ditemukan.";
        $tipe = 'danger';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Lupa Password - Hotel Grand Lumiere</title>
    <link rel="stylesheet" href="asset/css/bootstrap.min.css">
</head>
<body class="bg-light">
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card shadow">
                <div class="card-body p-4">
                    <h4 class="mb-3">Lupa Password</h4>
                    <?php if ($pesan): ?>
                        <div class="alert alert-<?= $tipe ?>"><?= $pesan ?></div>
                    <?php endif; ?>
                    <form method="POST">
                        <div class="mb-3">
                            <label>Email</label>
                            <input type="email" name="email" class="form-control" placeholder="Masukkan email kamu" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Kirim Link Reset</button>
                    </form>
                    <div class="mt-3 text-center">
                        <a href="fungsidatabase/akun/login.php">Kembali ke Login</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>