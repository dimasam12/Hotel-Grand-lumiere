<?php
session_start();
if (isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Login — Grand Lumière Hotel</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css">
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }

    body {
      font-family: 'Segoe UI', sans-serif;
      min-height: 100vh;
      overflow-x: hidden;
      overflow-y: auto;
    }

    .video-bg {
      position: fixed;
      inset: 0;
      z-index: 0;
    }

    video {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    .video-fallback {
      position: fixed;
      inset: 0;
      background: linear-gradient(135deg, #0a1628 0%, #1a3a5c 40%, #0d2137 70%, #051020 100%);
      z-index: 0;
    }

    .video-fallback::before {
      content: '';
      position: absolute;
      inset: 0;
      background:
        radial-gradient(ellipse at 20% 50%, rgba(24,95,165,0.3) 0%, transparent 60%),
        radial-gradient(ellipse at 80% 20%, rgba(83,74,183,0.25) 0%, transparent 50%),
        radial-gradient(ellipse at 60% 80%, rgba(15,110,86,0.2) 0%, transparent 50%);
      animation: bgPulse 6s ease-in-out infinite alternate;
    }

    .video-fallback::after {
      content: '';
      position: absolute;
      inset: 0;
      background-image: radial-gradient(circle at 1px 1px, rgba(255,255,255,0.06) 1px, transparent 0);
      background-size: 40px 40px;
    }

    @keyframes bgPulse {
      0%   { opacity: 0.7; }
      100% { opacity: 1; }
    }

    .stars {
      position: fixed;
      inset: 0;
      z-index: 1;
      pointer-events: none;
    }

    .star {
      position: absolute;
      width: 2px;
      height: 2px;
      background: white;
      border-radius: 50%;
      animation: twinkle var(--d, 3s) ease-in-out infinite alternate;
    }

    @keyframes twinkle {
      0%   { opacity: 0.1; }
      100% { opacity: 0.8; }
    }

    .overlay {
      position: fixed;
      inset: 0;
      background: rgba(5, 16, 32, 0.5);
      z-index: 2;
    }

    .content-area {
      position: relative;
      z-index: 3;
      display: flex;
      flex-direction: column;
      align-items: center;
      min-height: 100vh;
      padding: 2rem 1rem;
    }

    .hotel-brand {
      text-align: center;
      margin-bottom: 1.5rem;
      animation: fadeDown 0.6s ease;
    }

    @keyframes fadeDown {
      from { opacity: 0; transform: translateY(-20px); }
      to   { opacity: 1; transform: translateY(0); }
    }

    .hotel-brand .crown {
      font-size: 32px;
      color: #d4af37;
      margin-bottom: 4px;
    }

    .hotel-brand h1 {
      font-size: 26px;
      font-weight: 400;
      color: #ffffff;
      letter-spacing: 6px;
      text-transform: uppercase;
    }

    .hotel-brand p {
      font-size: 11px;
      color: rgba(255,255,255,0.5);
      letter-spacing: 3px;
      text-transform: uppercase;
      margin-top: 4px;
    }

    /* Badge Admin */
    .admin-badge {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      background: rgba(212,175,55,0.1);
      border: 0.5px solid rgba(212,175,55,0.35);
      border-radius: 20px;
      padding: 4px 14px;
      font-size: 10px;
      color: rgba(212,175,55,0.9);
      letter-spacing: 2px;
      text-transform: uppercase;
      margin-top: 8px;
    }

    .card-container {
      position: relative;
      width: 380px;
      max-width: 100%;
    }

    .form-card {
      width: 100%;
      background: rgba(255, 255, 255, 0.08);
      backdrop-filter: blur(20px);
      -webkit-backdrop-filter: blur(20px);
      border: 0.5px solid rgba(255,255,255,0.18);
      border-radius: 16px;
      padding: 2rem;
    }

    .form-title {
      font-size: 20px;
      font-weight: 400;
      color: #ffffff;
      margin-bottom: 4px;
      text-align: center;
    }

    .form-subtitle {
      font-size: 11px;
      color: rgba(255,255,255,0.45);
      text-align: center;
      margin-bottom: 1.25rem;
      letter-spacing: 2px;
      text-transform: uppercase;
    }

    .divider-line {
      width: 40px;
      height: 1px;
      background: rgba(212,175,55,0.6);
      margin: 0 auto 1.5rem;
    }

    .input-group {
      margin-bottom: 1rem;
    }

    .input-group label {
      display: block;
      font-size: 11px;
      color: rgba(255,255,255,0.5);
      letter-spacing: 1.5px;
      text-transform: uppercase;
      margin-bottom: 6px;
    }

    .input-wrap {
      position: relative;
      display: flex;
      align-items: center;
    }

    .input-icon {
      position: absolute;
      left: 12px;
      color: rgba(255,255,255,0.35);
      font-size: 16px;
      pointer-events: none;
    }

    .input-wrap input {
      width: 100%;
      background: rgba(255,255,255,0.07);
      border: 0.5px solid rgba(255,255,255,0.18);
      border-radius: 8px;
      padding: 11px 12px 11px 38px;
      font-size: 14px;
      color: #ffffff;
      outline: none;
      transition: border-color 0.2s, background 0.2s;
      font-family: inherit;
    }

    .input-wrap input.has-toggle {
      padding-right: 38px;
    }

    .toggle-password {
      position: absolute;
      right: 12px;
      background: none;
      border: none;
      cursor: pointer;
      color: rgba(255,255,255,0.35);
      font-size: 16px;
      padding: 0;
      display: flex;
      align-items: center;
      transition: color 0.2s;
    }

    .toggle-password:hover { color: rgba(212,175,55,0.8); }

    .input-wrap input::placeholder { color: rgba(255,255,255,0.22); }

    .input-wrap input:focus {
      border-color: rgba(212,175,55,0.6);
      background: rgba(255,255,255,0.1);
    }

    .btn-primary {
      width: 100%;
      padding: 12px;
      background: rgba(212,175,55,0.9);
      border: none;
      border-radius: 8px;
      color: #0a1628;
      font-size: 12px;
      font-weight: 600;
      letter-spacing: 2.5px;
      text-transform: uppercase;
      cursor: pointer;
      margin-top: 0.5rem;
      transition: background 0.2s, transform 0.1s;
      font-family: inherit;
    }

    .btn-primary:hover  { background: #d4af37; }
    .btn-primary:active { transform: scale(0.98); }

    .switch-text {
      text-align: center;
      font-size: 12px;
      color: rgba(255,255,255,0.4);
      margin-top: 1.2rem;
    }

    .switch-text a {
      color: rgba(212,175,55,0.9);
      text-decoration: none;
      font-weight: 500;
      transition: color 0.2s;
    }

    .switch-text a:hover { color: #d4af37; }
  </style>
</head>
<body>

  <div class="video-bg">
    <video autoplay muted loop playsinline>
      <source src="../hotel-bg.mp4" type="video/mp4">
    </video>
  </div>

  <div class="video-fallback" id="fallback"></div>
  <div class="stars" id="stars"></div>
  <div class="overlay"></div>

  <div class="content-area">

    <div class="hotel-brand">
      <div class="crown"><i class="ti ti-shield-lock"></i></div>
      <h1>Grand Lumière</h1>
      <p>Luxury Collection Hotel</p>
      <div class="admin-badge">
        <i class="ti ti-lock" style="font-size:11px;"></i>
        Admin Panel
      </div>
    </div>

    <div class="card-container">
      <div class="form-card">
        <p class="form-title">Admin Login</p>
        <p class="form-subtitle">Restricted Access</p>
        <div class="divider-line"></div>

        <div class="input-group">
          <label>Username atau Email</label>
          <div class="input-wrap">
            <i class="ti ti-shield input-icon"></i>
            <input type="text" id="loginIdentifier" placeholder="admin / admin@email.com">
          </div>
        </div>

        <div class="input-group">
          <label>Password</label>
          <div class="input-wrap">
            <i class="ti ti-lock input-icon"></i>
            <input type="password" id="loginPassword" class="has-toggle" placeholder="••••••••">
            <button class="toggle-password" type="button" onclick="togglePass('loginPassword', this)" tabindex="-1">
              <i class="ti ti-eye"></i>
            </button>
          </div>
        </div>

        <button class="btn-primary" id="btnLogin">Login Admin</button>

        <div class="switch-text">
          Bukan admin? <a href="../index.php">← Kembali ke halaman utama</a>
        </div>
      </div>
    </div>

  </div>

  <script>
    // BINTANG
    const starsEl = document.getElementById('stars');
    for (let i = 0; i < 80; i++) {
      const s = document.createElement('div');
      s.className = 'star';
      s.style.cssText = `
        left: ${Math.random() * 100}%;
        top:  ${Math.random() * 100}%;
        --d:  ${2 + Math.random() * 4}s;
        animation-delay: ${Math.random() * 4}s;
        opacity: ${0.1 + Math.random() * 0.5};
        width:  ${Math.random() > 0.7 ? 3 : 2}px;
        height: ${Math.random() > 0.7 ? 3 : 2}px;
      `;
      starsEl.appendChild(s);
    }

    // VIDEO FALLBACK
    const video = document.querySelector('video');
    if (video) {
      video.addEventListener('canplay', () => {
        document.getElementById('fallback').style.display = 'none';
      });
    }

    // TOGGLE PASSWORD
    function togglePass(inputId, btn) {
      const input = document.getElementById(inputId);
      const icon = btn.querySelector('i');
      if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'ti ti-eye-off';
      } else {
        input.type = 'password';
        icon.className = 'ti ti-eye';
      }
    }

    // TOAST
    const toast = document.createElement('div');
    toast.style.cssText = `
      display:none; position:fixed; top:20px; left:50%; transform:translateX(-50%);
      background:rgba(10,22,40,0.95); border:0.5px solid rgba(255,255,255,0.15);
      backdrop-filter:blur(16px); border-radius:10px; padding:12px 22px;
      font-size:13px; color:#fff; z-index:999; text-align:center;
      min-width:260px; box-shadow:0 8px 32px rgba(0,0,0,0.4);
      transition:opacity 0.3s; font-family:'Segoe UI',sans-serif;
    `;
    document.body.appendChild(toast);

    function showToast(msg, sukses = false) {
      toast.textContent = msg;
      toast.style.display = 'block';
      toast.style.opacity = '1';
      toast.style.borderColor = sukses ? 'rgba(212,175,55,0.5)' : 'rgba(220,80,80,0.4)';
      clearTimeout(toast._t);
      toast._t = setTimeout(() => {
        toast.style.opacity = '0';
        setTimeout(() => toast.style.display = 'none', 300);
      }, 3000);
    }

    // LOGIN ADMIN
    document.getElementById('btnLogin').addEventListener('click', async () => {
      const btn = document.getElementById('btnLogin');
      const identifier = document.getElementById('loginIdentifier').value.trim();
      const password = document.getElementById('loginPassword').value;

      if (!identifier || !password) {
        showToast('Username/email dan password wajib diisi.');
        return;
      }

      btn.disabled = true;
      btn.textContent = 'Mohon tunggu...';
      btn.style.opacity = '0.6';

      const body = new FormData();
      body.append('identifier', identifier);
      body.append('password', password);

      try {
        const res = await fetch('../fungsidatabase/akun/login_admin.php', { method: 'POST', body });
        const data = await res.json();

        if (data.status === 'success') {
          showToast(data.message, true);
          setTimeout(() => window.location.href = data.redirect, 1000);
        } else {
          showToast(data.message);
          btn.disabled = false;
          btn.textContent = 'Login Admin';
          btn.style.opacity = '1';
        }
      } catch (e) {
        showToast('Terjadi kesalahan. Coba lagi.');
        btn.disabled = false;
        btn.textContent = 'Login Admin';
        btn.style.opacity = '1';
      }
    });

    // ENTER KEY
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') document.getElementById('btnLogin').click();
    });
  </script>

</body>
</html>
