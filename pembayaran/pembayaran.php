<?php
session_start();

// Cek apakah user login
if (!isset($_SESSION['user_id'])) {
    header('Location: index.html');
    exit;
}

$snap_token = $_GET['snap_token'] ?? '';
$kode_booking = $_GET['kode_booking'] ?? '';

if (!$snap_token) {
    header('Location: mainpage.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pembayaran — Grand Lumière Hotel</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,500;0,600;1,300;1,400&family=Jost:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css">
    <script src="https://app.sandbox.midtrans.com/snap/snap.js" data-client-key="Mid-client-Z5Yf_9PreVUunKsl"></script>
    <style>
        *, *::before, *::after {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --gold: #c9a84c;
            --gold-light: #e8cc80;
            --gold-dim: rgba(201, 168, 76, 0.15);
            --gold-border: rgba(201, 168, 76, 0.25);
            --navy: #080e1c;
            --navy-mid: #0f1c35;
            --navy-card: rgba(15, 28, 53, 0.85);
            --text-primary: #f0ead6;
            --text-muted: rgba(240, 234, 214, 0.45);
            --text-soft: rgba(240, 234, 214, 0.7);
        }

        html, body {
            height: 100%;
        }

        body {
            font-family: 'Jost', sans-serif;
            font-weight: 300;
            background-color: var(--navy);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
            overflow-x: hidden;
        }

        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background:
                radial-gradient(ellipse 80% 60% at 20% 10%, rgba(201, 168, 76, 0.06) 0%, transparent 60%),
                radial-gradient(ellipse 60% 50% at 80% 80%, rgba(15, 50, 100, 0.4) 0%, transparent 60%),
                radial-gradient(ellipse 100% 80% at 50% 50%, rgba(10, 20, 45, 0.8) 0%, transparent 100%);
            pointer-events: none;
            z-index: 0;
        }

        body::after {
            content: '';
            position: fixed;
            inset: 0;
            background-image: repeating-linear-gradient(
                -45deg,
                transparent,
                transparent 80px,
                rgba(201, 168, 76, 0.015) 80px,
                rgba(201, 168, 76, 0.015) 81px
            );
            pointer-events: none;
            z-index: 0;
        }

        .page-wrapper {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 480px;
        }

        .brand-header {
            text-align: center;
            margin-bottom: 32px;
            animation: fadeSlideDown 0.7s ease both;
        }

        .brand-ornament {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            margin-bottom: 8px;
        }

        .ornament-line {
            width: 48px;
            height: 1px;
            background: linear-gradient(to right, transparent, var(--gold));
        }

        .ornament-line.right {
            background: linear-gradient(to left, transparent, var(--gold));
        }

        .ornament-diamond {
            width: 6px;
            height: 6px;
            background: var(--gold);
            transform: rotate(45deg);
        }

        .brand-name {
            font-family: 'Cormorant Garamond', serif;
            font-size: 13px;
            font-weight: 400;
            letter-spacing: 0.4em;
            text-transform: uppercase;
            color: var(--gold);
        }

        .payment-card {
            background: var(--navy-card);
            border: 1px solid var(--gold-border);
            border-radius: 4px;
            overflow: hidden;
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            box-shadow:
                0 0 0 1px rgba(201, 168, 76, 0.05) inset,
                0 40px 80px rgba(0, 0, 0, 0.6),
                0 4px 32px rgba(0, 0, 0, 0.4);
            animation: fadeSlideUp 0.7s ease 0.1s both;
        }

        .card-accent {
            height: 2px;
            background: linear-gradient(to right, transparent 0%, var(--gold) 30%, var(--gold-light) 50%, var(--gold) 70%, transparent 100%);
        }

        .card-body {
            padding: 40px 40px 36px;
        }

        .card-title-section {
            text-align: center;
            margin-bottom: 36px;
        }

        .card-icon {
            width: 52px;
            height: 52px;
            border-radius: 50%;
            background: var(--gold-dim);
            border: 1px solid var(--gold-border);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }

        .card-icon i {
            font-size: 22px;
            color: var(--gold);
        }

        .card-title {
            font-family: 'Cormorant Garamond', serif;
            font-size: 30px;
            font-weight: 400;
            color: var(--text-primary);
            letter-spacing: 0.02em;
            margin-bottom: 6px;
            line-height: 1;
        }

        .card-subtitle {
            font-size: 12px;
            font-weight: 400;
            letter-spacing: 0.25em;
            text-transform: uppercase;
            color: var(--text-muted);
        }

        .divider {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 28px 0;
        }

        .divider-line {
            flex: 1;
            height: 1px;
            background: var(--gold-border);
        }

        .divider-dot {
            width: 4px;
            height: 4px;
            background: var(--gold);
            transform: rotate(45deg);
            opacity: 0.5;
        }

        .booking-box {
            background: rgba(0, 0, 0, 0.35);
            border: 1px solid var(--gold-border);
            border-radius: 3px;
            padding: 18px 20px;
            margin-bottom: 28px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .booking-label {
            font-size: 10px;
            font-weight: 500;
            letter-spacing: 0.3em;
            text-transform: uppercase;
            color: var(--text-muted);
            margin-bottom: 4px;
        }

        .booking-code {
            font-family: 'Cormorant Garamond', serif;
            font-size: 22px;
            font-weight: 500;
            color: var(--gold-light);
            letter-spacing: 0.08em;
        }

        .booking-icon {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: var(--gold-dim);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .booking-icon i {
            font-size: 16px;
            color: var(--gold);
        }

        .btn-primary {
            width: 100%;
            padding: 16px 24px;
            background: linear-gradient(135deg, #c9a84c 0%, #a8852e 50%, #c9a84c 100%);
            background-size: 200% 100%;
            background-position: 100% 0;
            border: none;
            border-radius: 3px;
            color: #1a1000;
            font-family: 'Jost', sans-serif;
            font-size: 12px;
            font-weight: 500;
            letter-spacing: 0.25em;
            text-transform: uppercase;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: background-position 0.4s ease, transform 0.2s ease, box-shadow 0.3s ease;
            position: relative;
            overflow: hidden;
            margin-bottom: 14px;
        }

        .btn-primary::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(to bottom, rgba(255,255,255,0.12), transparent);
            pointer-events: none;
        }

        .btn-primary:hover:not(:disabled) {
            background-position: 0% 0;
            box-shadow: 0 8px 32px rgba(201, 168, 76, 0.3);
            transform: translateY(-1px);
        }

        .btn-primary:active:not(:disabled) {
            transform: translateY(0);
        }

        .btn-primary:disabled {
            opacity: 0.55;
            cursor: not-allowed;
            transform: none;
        }

        .back-link {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            color: var(--text-muted);
            text-decoration: none;
            font-size: 11px;
            font-weight: 400;
            letter-spacing: 0.15em;
            text-transform: uppercase;
            transition: color 0.25s ease;
            padding: 8px;
        }

        .back-link:hover {
            color: var(--gold);
        }

        .back-link i {
            font-size: 14px;
            transition: transform 0.25s ease;
        }

        .back-link:hover i {
            transform: translateX(-3px);
        }

        .info-section {
            border-top: 1px solid var(--gold-border);
            margin-top: 28px;
            padding-top: 24px;
        }

        .info-label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 10px;
            font-weight: 500;
            letter-spacing: 0.25em;
            text-transform: uppercase;
            color: var(--gold);
            margin-bottom: 14px;
            opacity: 0.8;
        }

        .info-label i {
            font-size: 14px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }

        .info-item {
            background: rgba(0, 0, 0, 0.25);
            border: 1px solid rgba(201, 168, 76, 0.12);
            border-radius: 3px;
            padding: 12px 14px;
        }

        .info-item-label {
            font-size: 9px;
            font-weight: 500;
            letter-spacing: 0.2em;
            text-transform: uppercase;
            color: var(--text-muted);
            margin-bottom: 4px;
        }

        .info-item-value {
            font-family: 'Cormorant Garamond', serif;
            font-size: 16px;
            font-weight: 500;
            color: var(--text-soft);
            letter-spacing: 0.05em;
        }

        .info-item.full {
            grid-column: 1 / -1;
        }

        .spinner {
            width: 16px;
            height: 16px;
            border: 2px solid rgba(26, 16, 0, 0.3);
            border-top-color: #1a1000;
            border-radius: 50%;
            animation: spin 0.7s linear infinite;
            flex-shrink: 0;
        }

        .footer-note {
            text-align: center;
            margin-top: 24px;
            font-size: 10px;
            color: var(--text-muted);
            letter-spacing: 0.1em;
            animation: fadeSlideUp 0.7s ease 0.3s both;
        }

        @keyframes fadeSlideDown {
            from { opacity: 0; transform: translateY(-12px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        @keyframes fadeSlideUp {
            from { opacity: 0; transform: translateY(16px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        @media (max-width: 480px) {
            .card-body {
                padding: 28px 24px 24px;
            }

            .card-title {
                font-size: 26px;
            }

            .info-grid {
                grid-template-columns: 1fr;
            }

            .info-item.full {
                grid-column: auto;
            }
        }
    </style>
</head>
<body>
    <div class="page-wrapper">

        <div class="brand-header">
            <div class="brand-ornament">
                <div class="ornament-line"></div>
                <div class="ornament-diamond"></div>
                <div class="ornament-line right"></div>
            </div>
            <div class="brand-name">Grand Lumière Hotel</div>
        </div>

        <div class="payment-card">
            <div class="card-accent"></div>

            <div class="card-body">

                <div class="card-title-section">
                    <div class="card-icon">
                        <i class="ti ti-credit-card"></i>
                    </div>
                    <h1 class="card-title">Pembayaran</h1>
                    <p class="card-subtitle">Selesaikan reservasi Anda</p>
                </div>

                <div class="booking-box">
                    <div>
                        <div class="booking-label">Kode Booking</div>
                        <div class="booking-code"><?= htmlspecialchars($kode_booking) ?></div>
                    </div>
                    <div class="booking-icon">
                        <i class="ti ti-receipt"></i>
                    </div>
                </div>

                <button class="btn-primary" id="pay-button">
                    <i class="ti ti-wallet"></i>
                    Bayar Sekarang
                </button>

                <a href="../mainpage.php" class="back-link">
                    <i class="ti ti-arrow-left"></i>
                    Kembali ke Beranda
                </a>

                <div class="info-section">
                    <div class="info-label">
                        <i class="ti ti-info-circle"></i>
                        Info Pembayaran · Mode Sandbox
                    </div>
                    <div class="info-grid">
                        <div class="info-item full">
                            <div class="info-item-label">Nomor Kartu Uji</div>
                            <div class="info-item-value">4811 1111 1111 1114</div>
                        </div>
                        <div class="info-item">
                            <div class="info-item-label">CVV</div>
                            <div class="info-item-value">123</div>
                        </div>
                        <div class="info-item">
                            <div class="info-item-label">Exp. Date</div>
                            <div class="info-item-value">12 / 25</div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <p class="footer-note">
            Transaksi diamankan dengan enkripsi SSL 256-bit
        </p>
    </div>

    <script>
        const snapToken = '<?= $snap_token ?>';
        const kodeBooking = '<?= $kode_booking ?>';

        const payButton = document.getElementById('pay-button');

        payButton.onclick = function () {
            payButton.disabled = true;
            payButton.innerHTML = '<span class="spinner"></span> Memproses...';

            snap.pay(snapToken, {
                onSuccess: function (result) {
                    // 🔥 REDIRECT KE payment_return.php DENGAN STATUS SUCCESS
                    window.location.href = '../payment_return.php?booking=' + kodeBooking + '&status=success';
                },
                onPending: function (result) {
                    window.location.href = '../payment_return.php?booking=' + kodeBooking + '&status=pending';
                },
                onError: function (result) {
                    alert('Pembayaran gagal: ' + (result.status_message || 'Unknown error'));
                    payButton.disabled = false;
                    payButton.innerHTML = '<i class="ti ti-wallet"></i> Bayar Sekarang';
                }
            });
        };
    </script>
</body>
</html>