<?php
session_start();
require_once 'config.php';
require_once 'booking/midtrans_config.php'; // sesuaikan path jika berbeda

$kode = $_GET['booking'] ?? '';
$order = null;

if ($kode) {
    // ── 1. Ambil data booking pakai PREPARED STATEMENT (anti SQL Injection) ──
    $stmt = $conn->prepare("SELECT * FROM pemesanan WHERE kode_booking = ? LIMIT 1");
    $stmt->bind_param('s', $kode);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($order) {
        // ── 2. TANYA LANGSUNG KE MIDTRANS: status transaksi ini SEBENARNYA apa? ──
        // Ini server-to-server (outbound), jadi tetap jalan walau di localhost/XAMPP.
        $auth = base64_encode(MIDTRANS_SERVER_KEY . ':');
        $statusUrl = str_replace(
            '/v2/charge', // sesuaikan jika MIDTRANS_API_URL beda struktur
            '',
            MIDTRANS_API_URL
        );
        // Gunakan endpoint status resmi Midtrans (sandbox/production menyesuaikan server key)
        $isProduction = strpos(MIDTRANS_API_URL, 'app.midtrans.com') !== false;
        $base = $isProduction
            ? 'https://api.midtrans.com'
            : 'https://api.sandbox.midtrans.com';
        $endpoint = $base . '/v2/' . urlencode($order['kode_booking']) . '/status';

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $endpoint,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Accept: application/json',
                'Authorization: Basic ' . $auth,
            ],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT        => 15,
        ]);
        $response = curl_exec($ch);
        curl_close($ch);

        $midtransData = json_decode($response, true);
        $transactionStatus = $midtransData['transaction_status'] ?? null;
        $fraudStatus       = $midtransData['fraud_status'] ?? null;

        // ── 3. Update database SESUAI jawaban asli dari Midtrans ──
        $newPaymentStatus = null;
        $newStatus        = null;

        if ($transactionStatus === 'capture' || $transactionStatus === 'settlement') {
            if ($fraudStatus === 'accept' || $fraudStatus === null) {
                $newPaymentStatus = 'paid';
                $newStatus        = 'confirmed'; // bukan langsung checked_in — itu tugas admin saat tamu datang
            }
        } elseif ($transactionStatus === 'pending') {
            $newPaymentStatus = 'unpaid';
            $newStatus        = 'pending';
        } elseif (in_array($transactionStatus, ['deny', 'cancel', 'expire', 'failure'])) {
            $newPaymentStatus = 'unpaid';
            $newStatus        = 'cancelled';
        }

        if ($newPaymentStatus !== null) {
            $u = $conn->prepare("UPDATE pemesanan SET payment_status = ?, status = ? WHERE kode_booking = ?");
            $u->bind_param('sss', $newPaymentStatus, $newStatus, $order['kode_booking']);
            $u->execute();
            $u->close();

            // refresh data biar tampilan sesuai status terbaru
            $order['payment_status'] = $newPaymentStatus;
            $order['status']         = $newStatus;
        }
        // Jika Midtrans tidak mengembalikan transaction_status yang dikenali,
        // JANGAN ubah apapun — biarkan status lama (lebih aman daripada asal update).
    }
}

$status  = $order['status'] ?? 'pending';
$payStat = $order['payment_status'] ?? 'unpaid';
$isOk    = $payStat === 'paid';
$isPend  = $payStat === 'unpaid' && $status !== 'cancelled';
$isError = $status === 'cancelled';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Status Pembayaran — Grand Lumière</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,500;1,300;1,400&family=Jost:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css">
    <style>
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }
        :root {
            --gold: #c9a84c;
            --gold-light: #e8cc80;
            --gold-dim: rgba(201, 168, 76, 0.15);
            --gold-border: rgba(201, 168, 76, 0.25);
            --navy: #080e1c;
            --navy-card: rgba(15, 28, 53, 0.9);
            --text-primary: #f0ead6;
            --text-muted: rgba(240, 234, 214, 0.4);
            --text-soft: rgba(240, 234, 214, 0.7);
            --success: #4caf7d;
            --warn: #f0a843;
            --danger: #e05252;
        }
        html, body { height: 100%; }
        body {
            font-family: 'Jost', sans-serif;
            font-weight: 300;
            background-color: var(--navy);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px 16px;
            position: relative;
            overflow-x: hidden;
        }
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background: radial-gradient(ellipse 80% 60% at 20% 10%, rgba(201, 168, 76, 0.06) 0%, transparent 60%),
                        radial-gradient(ellipse 60% 50% at 80% 80%, rgba(15, 50, 100, 0.4) 0%, transparent 60%);
            pointer-events: none;
            z-index: 0;
        }
        .page-wrapper { position: relative; z-index: 1; width: 100%; max-width: 480px; }
        .brand-header { text-align: center; margin-bottom: 26px; animation: fadeDown .6s ease both; }
        .brand-ornament { display: flex; align-items: center; justify-content: center; gap: 12px; margin-bottom: 7px; }
        .ornament-line { width: 48px; height: 1px; background: linear-gradient(to right, transparent, var(--gold)); }
        .ornament-line.right { background: linear-gradient(to left, transparent, var(--gold)); }
        .ornament-diamond { width: 5px; height: 5px; background: var(--gold); transform: rotate(45deg); }
        .brand-name { font-family: 'Cormorant Garamond', serif; font-size: 13px; font-weight: 400; letter-spacing: .45em; text-transform: uppercase; color: var(--gold); }
        .card {
            background: var(--navy-card);
            border: 1px solid var(--gold-border);
            border-radius: 4px;
            overflow: hidden;
            backdrop-filter: blur(24px);
            box-shadow: 0 0 0 1px rgba(201,168,76,.05) inset, 0 40px 80px rgba(0,0,0,.6);
            animation: fadeUp .65s ease .1s both;
        }
        .card-accent { height: 2px; background: linear-gradient(to right, transparent 0%, var(--gold) 30%, var(--gold-light) 50%, var(--gold) 70%, transparent 100%); }
        .card-body { padding: 36px 36px 32px; }
        .status-icon {
            width: 68px; height: 68px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 20px; font-size: 30px;
        }
        .icon-success { background: rgba(76, 175, 125, .12); border: 1px solid rgba(76, 175, 125, .3); color: var(--success); }
        .icon-pending  { background: rgba(240, 168, 67, .12); border: 1px solid rgba(240, 168, 67, .3); color: var(--warn); }
        .icon-error    { background: rgba(224, 82, 82, .12);  border: 1px solid rgba(224, 82, 82, .3);  color: var(--danger); }
        .card-title {
            font-family: 'Cormorant Garamond', serif;
            font-size: 28px; font-weight: 400;
            color: var(--text-primary);
            text-align: center;
            margin-bottom: 7px;
        }
        .card-desc {
            font-size: 12px; font-weight: 300;
            letter-spacing: .04em; line-height: 1.75;
            color: var(--text-muted);
            text-align: center;
            margin-bottom: 28px;
        }
        .kode-box {
            background: rgba(0,0,0,.35);
            border: 1px solid var(--gold-border);
            border-radius: 3px;
            padding: 15px 18px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .kode-label { font-size: 9px; font-weight: 500; letter-spacing: .3em; text-transform: uppercase; color: var(--text-muted); margin-bottom: 4px; }
        .kode-val { font-family: 'Cormorant Garamond', serif; font-size: 20px; font-weight: 500; color: var(--gold-light); }
        .kode-icon { width: 34px; height: 34px; border-radius: 50%; background: var(--gold-dim); display: flex; align-items: center; justify-content: center; }
        .kode-icon i { font-size: 15px; color: var(--gold); }
        .detail-table {
            background: rgba(0,0,0,.25);
            border: 1px solid rgba(201,168,76,.12);
            border-radius: 3px;
            overflow: hidden;
            margin-bottom: 24px;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 11px 16px;
            border-bottom: 1px solid rgba(201,168,76,.07);
        }
        .detail-row:last-child { border-bottom: none; }
        .detail-row.total { background: rgba(201,168,76,.04); border-top: 1px solid rgba(201,168,76,.15); }
        .row-label { font-size: 10px; font-weight: 400; letter-spacing: .1em; text-transform: uppercase; color: var(--text-muted); }
        .row-val { font-family: 'Cormorant Garamond', serif; font-size: 16px; font-weight: 400; color: var(--text-soft); }
        .row-val.highlight { font-size: 20px; font-weight: 500; color: var(--gold-light); }
        .btn-row { display: flex; gap: 10px; }
        .btn {
            flex: 1; padding: 14px 16px; border-radius: 3px;
            font-family: 'Jost', sans-serif; font-size: 10px; font-weight: 500;
            letter-spacing: .25em; text-transform: uppercase;
            text-decoration: none; display: flex; align-items: center; justify-content: center; gap: 7px;
            transition: all .25s ease;
        }
        .btn-ghost { background: transparent; border: 1px solid rgba(201,168,76,.22); color: var(--text-muted); }
        .btn-ghost:hover { border-color: rgba(201,168,76,.5); color: var(--gold); }
        .btn-gold { background: linear-gradient(135deg, #c9a84c 0%, #a8852e 100%); border: none; color: #1a1000; }
        .btn-gold:hover { opacity: .88; box-shadow: 0 6px 20px rgba(201,168,76,.25); }
        .divider { height: 1px; background: rgba(201,168,76,.15); margin: 22px 0; }
        .page-footer { text-align: center; margin-top: 20px; font-size: 9px; color: rgba(240,234,214,.2); letter-spacing: .12em; }
        @keyframes fadeDown { from { opacity:0; transform:translateY(-12px); } to { opacity:1; transform:translateY(0); } }
        @keyframes fadeUp   { from { opacity:0; transform:translateY(16px);  } to { opacity:1; transform:translateY(0); } }
        @media (max-width: 480px) {
            .card-body { padding: 28px 20px 24px; }
            .card-title { font-size: 24px; }
            .btn-row { flex-direction: column; }
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

    <div class="card">
        <div class="card-accent"></div>
        <div class="card-body">
            <?php if ($isOk): ?>
                <div class="status-icon icon-success"><i class="ti ti-circle-check"></i></div>
                <h1 class="card-title">Pembayaran Berhasil!</h1>
                <p class="card-desc">Terima kasih! Pembayaran Anda sudah <strong>terkonfirmasi</strong>. Sampai jumpa saat check-in.</p>
            <?php elseif ($isPend): ?>
                <div class="status-icon icon-pending"><i class="ti ti-clock"></i></div>
                <h1 class="card-title">Menunggu Pembayaran</h1>
                <p class="card-desc">Pembayaran Anda sedang diproses atau belum diselesaikan.</p>
            <?php else: ?>
                <div class="status-icon icon-error"><i class="ti ti-alert-circle"></i></div>
                <h1 class="card-title">Pembayaran Gagal / Dibatalkan</h1>
                <p class="card-desc">Terjadi masalah dengan pembayaran Anda.</p>
            <?php endif; ?>

            <?php if ($order): ?>
                <div class="kode-box">
                    <div>
                        <div class="kode-label">Kode Booking</div>
                        <div class="kode-val"><?= htmlspecialchars($order['kode_booking']) ?></div>
                    </div>
                    <div class="kode-icon"><i class="ti ti-receipt"></i></div>
                </div>

                <div class="detail-table">
                    <div class="detail-row">
                        <span class="row-label">Nama Tamu</span>
                        <span class="row-val"><?= htmlspecialchars($order['nama_tamu']) ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="row-label">Tipe Kamar</span>
                        <span class="row-val"><?= htmlspecialchars($order['tipe_kamar']) ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="row-label">Check-in</span>
                        <span class="row-val"><?= date('d M Y', strtotime($order['check_in'])) ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="row-label">Check-out</span>
                        <span class="row-val"><?= date('d M Y', strtotime($order['check_out'])) ?></span>
                    </div>
                    <div class="detail-row total">
                        <span class="row-label">Total</span>
                        <span class="row-val highlight">Rp <?= number_format($order['total'], 0, ',', '.') ?></span>
                    </div>
                </div>
            <?php endif; ?>

            <div class="divider"></div>
            <div class="btn-row">
                <a href="mainpage.php" class="btn btn-ghost"><i class="ti ti-home"></i> Beranda</a>
                <a href="riwayat.php" class="btn btn-gold"><i class="ti ti-list"></i> Riwayat</a>
            </div>
        </div>
    </div>
    <p class="page-footer">Grand Lumière Hotel · Transaksi Aman</p>
</div>
</body>
</html>
