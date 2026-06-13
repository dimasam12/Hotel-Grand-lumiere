<?php
require_once 'config.php';
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
        $pesan = "Link reset password sudah dikirim. Klik: <a href='$link' style='color:inherit;font-weight:500;'>di sini</a>";
        $tipe = 'success';
    } else {
        $pesan = "Email tidak ditemukan di sistem kami.";
        $tipe = 'danger';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lupa Password - Hotel Grand Lumiere</title>
    <link rel="stylesheet" href="asset/css/bootstrap.min.css">
    <style>
        body {
            background-color: #f5f4f1;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
        }

        .fp-card {
            background: #ffffff;
            border: 0.5px solid rgba(0,0,0,0.12);
            border-radius: 14px;
            padding: 2rem;
            width: 100%;
            max-width: 420px;
        }

        .fp-logo {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 1.75rem;
        }

        .fp-logo-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: #f5f4f1;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 0.5px solid rgba(0,0,0,0.1);
            font-size: 20px;
            color: #666;
        }

        .fp-logo-name {
            margin: 0;
            font-size: 13px;
            font-weight: 500;
            color: #1a1a1a;
        }

        .fp-logo-sub {
            margin: 0;
            font-size: 11px;
            color: #888;
        }

        .fp-title {
            font-size: 18px;
            font-weight: 500;
            color: #1a1a1a;
            margin: 0 0 4px;
        }

        .fp-subtitle {
            font-size: 13px;
            color: #666;
            margin: 0 0 1.5rem;
            line-height: 1.5;
        }

        .fp-label {
            font-size: 13px;
            color: #555;
            margin-bottom: 6px;
            display: block;
        }

        .fp-input-wrap {
            position: relative;
            margin-bottom: 1.25rem;
        }

        .fp-input-icon {
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            color: #aaa;
            font-size: 15px;
            pointer-events: none;
        }

        .fp-input {
            width: 100%;
            padding: 0 12px 0 34px;
            height: 38px;
            border-radius: 8px;
            border: 0.5px solid rgba(0,0,0,0.2);
            background: #fff;
            color: #1a1a1a;
            font-size: 14px;
            outline: none;
            transition: border-color 0.15s, box-shadow 0.15s;
            box-sizing: border-box;
        }

        .fp-input:focus {
            border-color: rgba(0,0,0,0.4);
            box-shadow: 0 0 0 3px rgba(0,100,200,0.08);
        }

        .fp-input::placeholder {
            color: #bbb;
        }

        .fp-btn {
            width: 100%;
            height: 38px;
            border-radius: 8px;
            border: 0.5px solid rgba(0,0,0,0.2);
            background: #1a1a1a;
            color: #fff;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: opacity 0.15s;
        }

        .fp-btn:hover {
            opacity: 0.85;
        }

        .fp-divider {
            height: 0.5px;
            background: rgba(0,0,0,0.08);
            margin: 1.5rem 0;
        }

        .fp-steps {
            background: #f5f4f1;
            border-radius: 8px;
            padding: 12px 14px;
            margin-bottom: 1.25rem;
        }

        .fp-steps-title {
            font-size: 12px;
            font-weight: 500;
            color: #666;
            margin: 0 0 8px;
        }

        .fp-step {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin-bottom: 8px;
        }

        .fp-step:last-child {
            margin-bottom: 0;
        }

        .fp-step-num {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: #fff;
            border: 0.5px solid rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            color: #666;
            flex-shrink: 0;
            margin-top: 1px;
        }

        .fp-step-text {
            font-size: 12px;
            color: #666;
            line-height: 1.5;
        }

        .fp-back {
            text-align: center;
            font-size: 13px;
            color: #666;
        }

        .fp-back a {
            color: #0066cc;
            text-decoration: none;
        }

        .fp-back a:hover {
            text-decoration: underline;
        }

        /* Alert */
        .fp-alert {
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: flex-start;
            gap: 8px;
            line-height: 1.5;
        }

        .fp-alert.success {
            background: #eaf3de;
            color: #3b6d11;
            border: 0.5px solid #97c459;
        }

        .fp-alert.danger {
            background: #fcebeb;
            color: #a32d2d;
            border: 0.5px solid #f09595;
        }

        .fp-alert-icon {
            flex-shrink: 0;
            margin-top: 1px;
        }
    </style>
</head>
<body>
    <div class="fp-card">

        <!-- Header -->
        <div class="fp-logo">
            <div class="fp-logo-icon">🏨</div>
            <div>
                <p class="fp-logo-name">Hotel Grand Lumiere</p>
                <p class="fp-logo-sub">Sistem Manajemen Hotel</p>
            </div>
        </div>

        <!-- Judul -->
        <p class="fp-title">Lupa password?</p>
        <p class="fp-subtitle">Masukkan email akunmu dan kami akan mengirimkan link untuk mereset password.</p>

        <!-- Alert -->
        <?php if ($pesan): ?>
            <div class="fp-alert <?= $tipe ?>">
                <?php if ($tipe === 'success'): ?>
                    <span class="fp-alert-icon">✅</span>
                <?php else: ?>
                    <span class="fp-alert-icon">❌</span>
                <?php endif; ?>
                <span><?= $pesan ?></span>
            </div>
        <?php endif; ?>

        <!-- Form -->
        <form method="POST">
            <label class="fp-label" for="email">Alamat email</label>
            <div class="fp-input-wrap">
                <span class="fp-input-icon">✉</span>
                <input
                    class="fp-input"
                    type="email"
                    id="email"
                    name="email"
                    placeholder="nama@example.com"
                    autocomplete="email"
                    required
                >
            </div>
            <button type="submit" class="fp-btn">
                <span>→</span> Kirim link reset
            </button>
        </form>

        <div class="fp-divider"></div>

        <!-- Panduan -->
        <div class="fp-steps">
            <p class="fp-steps-title">Cara kerja reset password:</p>
            <div class="fp-step">
                <div class="fp-step-num">1</div>
                <div class="fp-step-text">Masukkan email yang terdaftar di sistem</div>
            </div>
            <div class="fp-step">
                <div class="fp-step-num">2</div>
                <div class="fp-step-text">Buka link yang dikirim ke email kamu</div>
            </div>
            <div class="fp-step">
                <div class="fp-step-num">3</div>
                <div class="fp-step-text">Buat password baru dalam 1 jam</div>
            </div>
        </div>

        <!-- Kembali -->
        <div class="fp-back">
            <a href="fungsidatabase/akun/login.php">← Kembali ke halaman login</a>
        </div>

    </div>
</body>
</html>
