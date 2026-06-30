<?php
session_start();
require_once 'config.php';

$kode = $_GET['booking'] ?? '';

if ($kode) {
    $k = $conn->real_escape_string($kode);
    
    // 🔥 UPDATE STATUS LANGSUNG JADI CONFIRMED 🔥
    // Ini menggantikan webhook karena Midtrans tidak bisa akses localhost
    $conn->query("UPDATE pemesanan SET status = 'checked_in' WHERE kode_booking = '$k'");
    
    // Ambil data terbaru
    $res = $conn->query("SELECT * FROM pemesanan WHERE kode_booking = '$k' LIMIT 1");
    $order = $res ? $res->fetch_assoc() : null;
}

$status = $order['status'] ?? 'pending';
$isOk = in_array($status, ['confirmed', 'checked_in']);
$isPend = $status === 'pending';
$isError = !$isOk && !$isPend;
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
        .page-wrapper {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 480px;
        }
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
        .badge {
            display: inline-block; padding: 3px 12px; border-radius: 20px;
            font-size: 10px; font-weight: 500; letter-spacing: .15em; text-transform: uppercase;
        }
        .badge-checked_in { background: rgba(76,175,125,.12); color: var(--success); }
        .btn-row { display: flex; gap: 10px; }
        .btn {
            flex: 1; padding: 14px 16px; border-radius: 3px;
            font-family: 'Jost', sans-serif; font-size: 10px; font-weight: 500;
            letter-spacing: .25em; text-transform: uppercase;
            text-decoration: none; display: flex; align-items: center; justify-content: center; gap: 7px;
            transition: all .25s ease;
        }
        .btn-ghost {
            background: transparent;
            border: 1px solid rgba(201,168,76,.22);
            color: var(--text-muted);
        }
        .btn-ghost:hover { border-color: rgba(201,168,76,.5); color: var(--gold); }
        .btn-gold {
            background: linear-gradient(135deg, #c9a84c 0%, #a8852e 100%);
            border: none;
            color: #1a1000;
        }
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
                <p class="card-desc">Terima kasih! Pembayaran dikonfirmasi dan Anda sudah tercatat <strong>Check In</strong>. Selamat menikmati stay Anda.</p>
            <?php elseif ($isPend): ?>
                <div class="status-icon icon-pending"><i class="ti ti-clock"></i></div>
                <h1 class="card-title">Menunggu Pembayaran</h1>
                <p class="card-desc">Silakan selesaikan pembayaran Anda.</p>
            <?php else: ?>
                <div class="status-icon icon-error"><i class="ti ti-alert-circle"></i></div>
                <h1 class="card-title">Pembayaran Gagal</h1>
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
