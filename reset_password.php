<?php
include 'koneksi.php';
$pesan = '';
$tipe = '';
$token = $_GET['token'] ?? '';

$cek = mysqli_query($conn, "SELECT * FROM users WHERE reset_token='$token' AND reset_expire > NOW()");

if (mysqli_num_rows($cek) == 0) {
    die("<div class='container mt-5'><div class='alert alert-danger'>Link tidak valid atau sudah expired. <a href='forgot_password.php'>Minta link baru</a></div></div>");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $password_baru = password_hash($_POST['password'], PASSWORD_DEFAULT);
    mysqli_query($conn, "UPDATE users SET password='$password_baru', reset_token=NULL, reset_expire=NULL WHERE reset_token='$token'");
    $pesan = "Password berhasil diubah!";
    $tipe = 'success';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Reset Password - Hotel Grand Lumiere</title>
    <link rel="stylesheet" href="asset/css/bootstrap.min.css">
</head>
<body class="bg-light">
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card shadow">
                <div class="card-body p-4">
                    <h4 class="mb-3">Reset Password</h4>
                    <?php if ($pesan): ?>
                        <div class="alert alert-<?= $tipe ?>">
                            <?= $pesan ?> <a href="fungsidatabase/akun/login.php">Login sekarang</a>
                        </div>
                    <?php else: ?>
                    <form method="POST">
                        <div class="mb-3">
                            <label>Password Baru</label>
                            <input type="password" name="password" class="form-control" placeholder="Minimal 8 karakter" required minlength="8">
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Simpan Password</button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>