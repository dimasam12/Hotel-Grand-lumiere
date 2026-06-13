<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Grand Lumière Hotel</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css">
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }

    body {
      font-family: 'Segoe UI', sans-serif;
      min-height: 100vh;
      overflow-x: hidden;
      overflow-y: auto;
    }

    /* VIDEO BACKGROUND */
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

    /* Ganti video dengan animasi gradient jika tidak ada file video */
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

    /* STARS */
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

    /* OVERLAY */
    .overlay {
      position: fixed;
      inset: 0;
      background: rgba(5, 16, 32, 0.5);
      z-index: 2;
    }

    /* CONTENT */
    .content-area {
      position: relative;
      z-index: 3;
      display: flex;
      flex-direction: column;
      align-items: center;
      min-height: 100vh;
      padding: 2rem 1rem;
    }

    /* BRAND */
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

    /* CARD */
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
      transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .form-card.login-card {
      opacity: 1;
      transform: translateX(0) scale(1);
      pointer-events: all;
      position: relative;
    }

    .form-card.register-card {
      opacity: 0;
      transform: translateX(40px) scale(0.97);
      pointer-events: none;
      position: absolute;
      top: 0; left: 0;
    }

    .form-card.login-card.hide {
      opacity: 0;
      transform: translateX(-40px) scale(0.97);
      pointer-events: none;
      position: absolute;
      top: 0; left: 0;
    }

    .form-card.register-card.show {
      opacity: 1;
      transform: translateX(0) scale(1);
      pointer-events: all;
      position: relative;
    }

    /* FORM ELEMENTS */
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

    .input-wrap input::placeholder {
      color: rgba(255,255,255,0.22);
    }

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

    .forgot-link {
      text-align: right;
      margin-top: -4px;
      margin-bottom: 1rem;
    }

    .forgot-link a {
      font-size: 11px;
      color: rgba(255,255,255,0.38);
      cursor: pointer;
      text-decoration: none;
      transition: color 0.2s;
    }

    .forgot-link a:hover { color: rgba(212,175,55,0.8); }

    .separator {
      display: flex;
      align-items: center;
      gap: 10px;
      margin: 1.2rem 0 0.8rem;
    }

    .separator span {
      font-size: 11px;
      color: rgba(255,255,255,0.28);
    }

    .separator::before,
    .separator::after {
      content: '';
      flex: 1;
      height: 0.5px;
      background: rgba(255,255,255,0.12);
    }

    .switch-text {
      text-align: center;
      font-size: 12px;
      color: rgba(255,255,255,0.4);
    }

    .switch-text a {
      color: rgba(212,175,55,0.9);
      cursor: pointer;
      text-decoration: none;
      font-weight: 500;
      transition: color 0.2s;
    }

    .switch-text a:hover { color: #d4af37; }

    /* DOTS */
    .progress-dots {
      display: flex;
      justify-content: center;
      gap: 6px;
      margin-top: 1.5rem;
    }

    .dot {
      height: 6px;
      width: 6px;
      border-radius: 3px;
      background: rgba(255,255,255,0.2);
      transition: background 0.3s, width 0.3s;
    }

    .dot.active {
      background: rgba(212,175,55,0.9);
      width: 20px;
    }
  </style>
</head>
<body>

  <!-- BACKGROUND VIDEO -->
  <div class="video-bg">
    <video autoplay muted loop playsinline>
      <source src="hotel-bg.mp4" type="video/mp4">
    </video>
  </div>

  <div class="video-fallback" id="fallback"></div>
  <div class="stars" id="stars"></div>
  <div class="overlay"></div>

  <div class="content-area">

    <div class="hotel-brand">
      <div class="crown"><i class="ti ti-crown"></i></div>
      <h1>Grand Lumière</h1>
      <p>Luxury Collection Hotel</p>
    </div>

    <div class="card-container" id="cardContainer">

      <!-- LOGIN CARD -->
      <div class="form-card login-card" id="loginCard">
        <p class="form-title">Welcome Back</p>
        <p class="form-subtitle">Sign in to your account</p>
        <div class="divider-line"></div>

        <div class="input-group">
          <label>Username atau Email</label>
          <div class="input-wrap">
            <i class="ti ti-user input-icon"></i>
            <input type="text" id="loginIdentifier" placeholder="johndoe / your@email.com">
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

        <div class="forgot-link">
          <a href="forget_password.php">Forgot password?</a>
        </div>

        <button class="btn-primary" id="btnLogin">Sign In</button>

        <div class="separator"><span>or</span></div>

        <div class="switch-text">
          Don't have an account?
          <a id="goRegister">Create one &rarr;</a>
        </div>
      </div>

      <!-- REGISTER CARD -->
      <div class="form-card register-card" id="registerCard">
        <p class="form-title">Create Account</p>
        <p class="form-subtitle">Join us today</p>
        <div class="divider-line"></div>

        <div class="input-group">
          <label>Username</label>
          <div class="input-wrap">
            <i class="ti ti-user input-icon"></i>
            <input type="text" id="regUsername" placeholder="johndoe">
          </div>
        </div>

        <div class="input-group">
          <label>Email address</label>
          <div class="input-wrap">
            <i class="ti ti-mail input-icon"></i>
            <input type="email" id="regEmail" placeholder="your@email.com">
          </div>
        </div>

        <div class="input-group">
          <label>Password</label>
          <div class="input-wrap">
            <i class="ti ti-lock input-icon"></i>
            <input type="password" id="regPassword" class="has-toggle" placeholder="••••••••">
            <button class="toggle-password" type="button" onclick="togglePass('regPassword', this)" tabindex="-1">
              <i class="ti ti-eye"></i>
            </button>
          </div>
        </div>

        <div class="input-group">
          <label>Confirm password</label>
          <div class="input-wrap">
            <i class="ti ti-shield-check input-icon"></i>
            <input type="password" id="regConfirm" class="has-toggle" placeholder="••••••••">
            <button class="toggle-password" type="button" onclick="togglePass('regConfirm', this)" tabindex="-1">
              <i class="ti ti-eye"></i>
            </button>
          </div>
        </div>

        <button class="btn-primary" id="btnRegister">Create Account</button>

        <div class="switch-text" style="margin-top: 1rem;">
          Already have an account?
          <a id="goLogin">&larr; Sign in</a>
        </div>
      </div>

    </div>

    <div class="progress-dots">
      <div class="dot active" id="dot1"></div>
      <div class="dot" id="dot2"></div>
    </div>

  </div>

  <script>
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

    // ANIMASI PINDAH CARD
    const loginCard = document.getElementById('loginCard');
    const registerCard = document.getElementById('registerCard');
    const dot1 = document.getElementById('dot1');
    const dot2 = document.getElementById('dot2');

    document.getElementById('goRegister').addEventListener('click', () => {
      loginCard.classList.add('hide');
      registerCard.classList.add('show');
      dot1.classList.remove('active');
      dot2.classList.add('active');
    });

    document.getElementById('goLogin').addEventListener('click', () => {
      registerCard.classList.remove('show');
      loginCard.classList.remove('hide');
      dot2.classList.remove('active');
      dot1.classList.add('active');
    });

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

    function setLoading(btn, loading) {
      btn.disabled = loading;
      btn.style.opacity = loading ? '0.6' : '1';
      if (btn.id === 'btnLogin') {
        btn.textContent = loading ? 'Mohon tunggu...' : 'Sign In';
      } else {
        btn.textContent = loading ? 'Mohon tunggu...' : 'Create Account';
      }
    }

    // LOGIN - SUDAH BENAR
    document.getElementById('btnLogin').addEventListener('click', async () => {
      const btn = document.getElementById('btnLogin');
      const identifier = document.getElementById('loginIdentifier').value.trim();
      const password = document.getElementById('loginPassword').value;

      if (!identifier || !password) {
        showToast('Username/email dan password wajib diisi.');
        return;
      }

      setLoading(btn, true);

      const body = new FormData();
      body.append('identifier', identifier);
      body.append('password', password);

      try {
        const res = await fetch('fungsidatabase/akun/login.php', { method: 'POST', body });
        const data = await res.json();

        if (data.status === 'success') {
          showToast(data.message, true);
          setTimeout(() => window.location.href = data.redirect, 1000);
        } else {
          showToast(data.message);
          setLoading(btn, false);
        }
      } catch (error) {
        console.error('Login error:', error);
        showToast('Terjadi kesalahan. Coba lagi.');
        setLoading(btn, false);
      }
    });

    // REGISTER - SUDAH BENAR
    document.getElementById('btnRegister').addEventListener('click', async () => {
      const btn = document.getElementById('btnRegister');
      const username = document.getElementById('regUsername').value.trim();
      const email = document.getElementById('regEmail').value.trim();
      const password = document.getElementById('regPassword').value;
      const confirm = document.getElementById('regConfirm').value;

      if (!username || !email || !password || !confirm) {
        showToast('Semua field wajib diisi.');
        return;
      }
      if (password !== confirm) {
        showToast('Password dan konfirmasi tidak cocok.');
        return;
      }

      setLoading(btn, true);

      const body = new FormData();
      body.append('username', username);
      body.append('email', email);
      body.append('password', password);
      body.append('confirm_password', confirm);

      try {
        const res = await fetch('fungsidatabase/akun/register.php', { method: 'POST', body });
        const data = await res.json();

        if (data.status === 'success') {
          showToast(data.message, true);
          setTimeout(() => {
            registerCard.classList.remove('show');
            loginCard.classList.remove('hide');
            dot2.classList.remove('active');
            dot1.classList.add('active');
            document.getElementById('regUsername').value = '';
            document.getElementById('regEmail').value = '';
            document.getElementById('regPassword').value = '';
            document.getElementById('regConfirm').value = '';
          }, 1500);
        } else {
          showToast(data.message);
        }
      } catch (error) {
        console.error('Register error:', error);
        showToast('Terjadi kesalahan. Coba lagi.');
      }

      setLoading(btn, false);
    });

    // ENTER KEY
    document.addEventListener('keydown', (e) => {
      if (e.key !== 'Enter') return;
      if (!loginCard.classList.contains('hide')) {
        document.getElementById('btnLogin').click();
      } else {
        document.getElementById('btnRegister').click();
      }
    });
  </script>

</body>
</html>
