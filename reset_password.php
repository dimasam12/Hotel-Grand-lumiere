<?php
require_once 'config.php';
$pesan = '';
$tipe = '';
$token = $_GET['token'] ?? '';
$cek = mysqli_query($conn, "SELECT * FROM users WHERE reset_token='$token' AND reset_expire > NOW()");
if (mysqli_num_rows($cek) == 0) {
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Link Tidak Valid - Hotel Grand Lumiere</title>
        <style>
            body { background:#f5f4f1; min-height:100vh; display:flex; align-items:center; justify-content:center; padding:2rem 1rem; margin:0; font-family:sans-serif; }
            .card { background:#fff; border:0.5px solid rgba(0,0,0,0.12); border-radius:14px; padding:2rem; width:100%; max-width:420px; text-align:center; }
            .icon { font-size:36px; margin-bottom:1rem; }
            .title { font-size:17px; font-weight:500; color:#1a1a1a; margin:0 0 6px; }
            .sub { font-size:13px; color:#666; margin:0 0 1.5rem; line-height:1.5; }
            .btn { display:inline-flex; align-items:center; gap:6px; padding:0 20px; height:38px; border-radius:8px; background:#1a1a1a; color:#fff; font-size:13px; font-weight:500; text-decoration:none; }
            .btn:hover { opacity:0.85; }
        </style>
    </head>
    <body>
        <div class="card">
            <div class="icon">🔗</div>
            <p class="title">Link tidak valid atau sudah kedaluwarsa</p>
            <p class="sub">Link reset password hanya berlaku selama 1 jam. Silakan minta link baru untuk melanjutkan.</p>
            <a href="forgot_password.php" class="btn">→ Minta link baru</a>
        </div>
    </body>
    </html>
    <?php
    exit;
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Hotel Grand Lumiere</title>
    <style>
        * { box-sizing: border-box; }
        body {
            background: #f5f4f1;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
            margin: 0;
            font-family: sans-serif;
        }

        .fp-card {
            background: #fff;
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
        }

        .fp-logo-name { margin: 0; font-size: 13px; font-weight: 500; color: #1a1a1a; }
        .fp-logo-sub  { margin: 0; font-size: 11px; color: #888; }

        .fp-title    { font-size: 18px; font-weight: 500; color: #1a1a1a; margin: 0 0 4px; }
        .fp-subtitle { font-size: 13px; color: #666; margin: 0 0 1.5rem; line-height: 1.5; }

        .fp-label { font-size: 13px; color: #555; margin-bottom: 6px; display: block; }

        .fp-input-wrap { position: relative; margin-bottom: 1.25rem; }

        .fp-input-icon {
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            color: #aaa;
            font-size: 15px;
            pointer-events: none;
        }

        .fp-toggle {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: #aaa;
            font-size: 15px;
            padding: 0;
        }

        .fp-input {
            width: 100%;
            padding: 0 36px 0 34px;
            height: 38px;
            border-radius: 8px;
            border: 0.5px solid rgba(0,0,0,0.2);
            background: #fff;
            color: #1a1a1a;
            font-size: 14px;
            outline: none;
            transition: border-color 0.15s, box-shadow 0.15s;
        }

        .fp-input:focus {
            border-color: rgba(0,0,0,0.4);
            box-shadow: 0 0 0 3px rgba(0,100,200,0.08);
        }

        .fp-input::placeholder { color: #bbb; }

        /* strength bar */
        .strength-wrap { margin-top: -12px; margin-bottom: 1.25rem; }
        .strength-bar  { height: 3px; border-radius: 2px; background: #eee; overflow: hidden; margin-bottom: 4px; }
        .strength-fill { height: 100%; width: 0; border-radius: 2px; transition: width 0.3s, background 0.3s; }
        .strength-text { font-size: 11px; color: #aaa; }

        .fp-hint {
            font-size: 12px;
            color: #888;
            margin: -12px 0 1.25rem;
            line-height: 1.5;
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

        .fp-btn:hover   { opacity: 0.85; }
        .fp-btn:disabled { opacity: 0.4; cursor: not-allowed; }

        .fp-divider { height: 0.5px; background: rgba(0,0,0,0.08); margin: 1.5rem 0; }

        .fp-back { text-align: center; font-size: 13px; color: #666; }
        .fp-back a { color: #0066cc; text-decoration: none; }
        .fp-back a:hover { text-decoration: underline; }

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

        .fp-success-center { text-align: center; padding: 0.5rem 0 1rem; }
        .fp-success-icon   { font-size: 40px; margin-bottom: 0.75rem; }
        .fp-success-title  { font-size: 17px; font-weight: 500; color: #1a1a1a; margin: 0 0 6px; }
        .fp-success-sub    { font-size: 13px; color: #666; margin: 0 0 1.5rem; line-height: 1.5; }
        .fp-success-btn    { display:inline-flex; align-items:center; gap:6px; padding:0 20px; height:38px; border-radius:8px; background:#1a1a1a; color:#fff; font-size:13px; font-weight:500; text-decoration:none; transition:opacity .15s; }
        .fp-success-btn:hover { opacity:.85; }
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

    <?php if ($tipe === 'success'): ?>
        <!-- State: Berhasil -->
        <div class="fp-success-center">
            <div class="fp-success-icon">✅</div>
            <p class="fp-success-title">Password berhasil diubah!</p>
            <p class="fp-success-sub">Kamu sekarang bisa masuk menggunakan password baru.</p>
            <a href="index.php" class="fp-success-btn">→ Login sekarang</a>
        </div>

    <?php else: ?>
        <!-- State: Form -->
        <p class="fp-title">Buat password baru</p>
        <p class="fp-subtitle">Pastikan password baru kamu kuat dan mudah diingat.</p>

        <form method="POST" id="reset-form">

            <!-- Password Baru -->
            <label class="fp-label" for="password">Password baru</label>
            <div class="fp-input-wrap">
                <span class="fp-input-icon">🔒</span>
                <input
                    class="fp-input"
                    type="password"
                    id="password"
                    name="password"
                    placeholder="Minimal 8 karakter"
                    required
                    minlength="8"
                    oninput="checkStrength(this.value)"
                >
                <button type="button" class="fp-toggle" onclick="toggleVisibility('password', this)" title="Tampilkan password">👁</button>
            </div>

            <!-- Strength bar -->
            <div class="strength-wrap">
                <div class="strength-bar"><div class="strength-fill" id="strength-fill"></div></div>
                <span class="strength-text" id="strength-text"></span>
            </div>

            <!-- Konfirmasi Password -->
            <label class="fp-label" for="confirm">Konfirmasi password</label>
            <div class="fp-input-wrap">
                <span class="fp-input-icon">🔒</span>
                <input
                    class="fp-input"
                    type="password"
                    id="confirm"
                    name="confirm"
                    placeholder="Ulangi password baru"
                    required
                    oninput="checkMatch()"
                >
                <button type="button" class="fp-toggle" onclick="toggleVisibility('confirm', this)" title="Tampilkan password">👁</button>
            </div>
            <p class="fp-hint" id="match-hint"></p>

            <button type="submit" class="fp-btn" id="submit-btn" disabled>
                <span>🔑</span> Simpan password baru
            </button>
        </form>

        <div class="fp-divider"></div>
        <div class="fp-back">
            <a href="forgot_password.php">← Minta link reset baru</a>
        </div>
    <?php endif; ?>

</div>

<script>
function toggleVisibility(id, btn) {
    const input = document.getElementById(id);
    if (input.type === 'password') {
        input.type = 'text';
        btn.textContent = '🙈';
    } else {
        input.type = 'password';
        btn.textContent = '👁';
    }
}

function checkStrength(val) {
    const fill = document.getElementById('strength-fill');
    const text = document.getElementById('strength-text');
    let score = 0;
    if (val.length >= 8)  score++;
    if (/[A-Z]/.test(val)) score++;
    if (/[0-9]/.test(val)) score++;
    if (/[^A-Za-z0-9]/.test(val)) score++;

    const levels = [
        { w:'0%',   c:'#eee',    t:'' },
        { w:'25%',  c:'#e24b4a', t:'Lemah' },
        { w:'50%',  c:'#ef9f27', t:'Cukup' },
        { w:'75%',  c:'#63a522', t:'Kuat' },
        { w:'100%', c:'#3b6d11', t:'Sangat kuat' },
    ];

    fill.style.width      = levels[score].w;
    fill.style.background = levels[score].c;
    text.textContent      = levels[score].t;
    text.style.color      = levels[score].c;
    checkMatch();
}

function checkMatch() {
    const pw  = document.getElementById('password').value;
    const cf  = document.getElementById('confirm').value;
    const hint = document.getElementById('match-hint');
    const btn  = document.getElementById('submit-btn');
    if (!cf) { hint.textContent = ''; btn.disabled = true; return; }
    if (pw === cf && pw.length >= 8) {
        hint.textContent = '✓ Password cocok';
        hint.style.color = '#3b6d11';
        btn.disabled = false;
    } else {
        hint.textContent = '✗ Password tidak cocok';
        hint.style.color = '#a32d2d';
        btn.disabled = true;
    }
}
</script>
</body>
</html>
