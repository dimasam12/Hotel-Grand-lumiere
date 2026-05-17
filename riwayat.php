<?php
ob_start();
session_start();
require_once 'config.php';
require_once 'booking/midtrans_config.php';

/* =========================
   CEK LOGIN
========================= */
if (!isset($_SESSION['user_id']) && !isset($_SESSION['id'])) {
    header('Location: index.html');
    exit;
}

$user_id = $_SESSION['user_id'] ?? $_SESSION['id'] ?? null;

if (!$user_id) {
    header('Location: index.html');
    exit;
}

/* =========================
   VALIDASI ROLE
========================= */
$roleCheck = $conn->prepare("SELECT role FROM users WHERE id = ? LIMIT 1");
$roleCheck->bind_param('i', $user_id);
$roleCheck->execute();
$roleRow = $roleCheck->get_result()->fetch_assoc();
$roleCheck->close();

if (!$roleRow || $roleRow['role'] === 'admin') {
    session_unset();
    session_destroy();
    header('Location: index.html');
    exit;
}

/* =========================
   CHECK UPDATE (polling JS)
========================= */
if (isset($_GET['check_update'])) {
    ob_clean();
    header('Content-Type: application/json');

    $s = $conn->prepare("SELECT id, kode_booking, status, nomor_kamar, snap_token FROM pemesanan WHERE user_id = ? ORDER BY created_at DESC");
    $s->bind_param('i', $user_id);
    $s->execute();
    $rows = $s->get_result()->fetch_all(MYSQLI_ASSOC);
    $s->close();

    echo json_encode(['bookings' => $rows]);
    exit;
}

/* =========================
   GET SNAP TOKEN
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'get_snap_token') {
    ob_clean();
    header('Content-Type: application/json');

    $booking_id = intval($_POST['booking_id'] ?? 0);

    if ($booking_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID booking tidak valid']);
        exit;
    }

    $chk = $conn->prepare("SELECT * FROM pemesanan WHERE id = ? AND user_id = ? LIMIT 1");
    $chk->bind_param('ii', $booking_id, $user_id);
    $chk->execute();
    $row = $chk->get_result()->fetch_assoc();
    $chk->close();

    if (!$row) {
        echo json_encode(['success' => false, 'message' => 'Pesanan tidak ditemukan']);
        exit;
    }

    if ($row['status'] !== 'pending') {
        echo json_encode(['success' => false, 'message' => 'Pesanan tidak dalam status pending']);
        exit;
    }

    if (!empty($row['snap_token'])) {
        echo json_encode([
            'success'      => true,
            'snap_token'   => $row['snap_token'],
            'kode_booking' => $row['kode_booking'],
        ]);
        exit;
    }

    $params = [
        'transaction_details' => [
            'order_id'     => $row['kode_booking'],
            'gross_amount' => intval($row['total']),
        ],
        'customer_details' => [
            'first_name' => $row['nama_tamu'],
            'email'      => $row['email_tamu'],
        ],
        'item_details' => [
            [
                'id'       => 'kamar',
                'price'    => intval($row['harga_per_malam']),
                'quantity' => intval($row['jumlah_malam']),
                'name'     => $row['tipe_kamar'],
            ],
            [
                'id'       => 'pajak',
                'price'    => intval($row['pajak']),
                'quantity' => 1,
                'name'     => 'Pajak & Layanan (15%)',
            ],
        ],
    ];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => MIDTRANS_API_URL,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($params),
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Basic ' . base64_encode(MIDTRANS_SERVER_KEY . ':'),
        ],
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $response  = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_err  = curl_error($ch);
    curl_close($ch);

    if ($curl_err) {
        echo json_encode(['success' => false, 'message' => 'cURL error: ' . $curl_err]);
        exit;
    }

    $midtrans = json_decode($response, true);

    if ($http_code !== 201 || empty($midtrans['token'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Gagal generate token: ' . ($midtrans['error_messages'][0] ?? $response),
        ]);
        exit;
    }

    $token = $conn->real_escape_string($midtrans['token']);
    $conn->query("UPDATE pemesanan SET snap_token = '$token' WHERE id = $booking_id");

    echo json_encode([
        'success'      => true,
        'snap_token'   => $midtrans['token'],
        'kode_booking' => $row['kode_booking'],
    ]);
    exit;
}

/* =========================
   CANCEL REQUEST
   FIX: kamar_id diambil,
   lalu status kamar
   dikembalikan ke 'available'
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel_request') {
    ob_clean();
    header('Content-Type: application/json');

    $booking_id = intval($_POST['booking_id'] ?? 0);

    if ($booking_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID booking tidak valid']);
        exit;
    }

    // Ambil kamar_id dan nomor_kamar supaya bisa dibebaskan
    $chk = $conn->prepare("SELECT id, status, kamar_id, nomor_kamar FROM pemesanan WHERE id = ? AND user_id = ? LIMIT 1");
    $chk->bind_param('ii', $booking_id, $user_id);
    $chk->execute();
    $row = $chk->get_result()->fetch_assoc();
    $chk->close();

    if (!$row) {
        echo json_encode(['success' => false, 'message' => 'Pesanan tidak ditemukan']);
        exit;
    }

    if (!in_array($row['status'], ['pending', 'confirmed', 'checked_in'])) {
        echo json_encode(['success' => false, 'message' => 'Status tidak bisa dicancel']);
        exit;
    }

    $conn->begin_transaction();
    try {
        // Cancel pesanan + reset kamar_id & nomor_kamar
        $upd = $conn->prepare("UPDATE pemesanan SET status = 'cancelled', kamar_id = NULL, nomor_kamar = NULL WHERE id = ?");
        $upd->bind_param('i', $booking_id);
        $upd->execute();
        $upd->close();

        // Bebaskan kamar: utamakan kamar_id, fallback ke nomor_kamar
        if (!empty($row['kamar_id'])) {
            $free = $conn->prepare("UPDATE kamar SET status = 'available' WHERE id = ?");
            $free->bind_param('i', $row['kamar_id']);
            $free->execute();
            $free->close();
        } elseif (!empty($row['nomor_kamar'])) {
            $free = $conn->prepare("UPDATE kamar SET status = 'available' WHERE nomor_kamar = ?");
            $free->bind_param('s', $row['nomor_kamar']);
            $free->execute();
            $free->close();
        }

        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Pesanan berhasil dibatalkan']);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Gagal: ' . $e->getMessage()]);
    }
    exit;
}

/* =========================
   CHECKOUT
   FIX: kamar_id diambil,
   lalu status kamar
   dikembalikan ke 'available'
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'checkout') {
    ob_clean();
    header('Content-Type: application/json');

    $booking_id = intval($_POST['booking_id'] ?? 0);

    if ($booking_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID booking tidak valid']);
        exit;
    }

    // Ambil kamar_id dan nomor_kamar supaya bisa dibebaskan
    $chk = $conn->prepare("SELECT id, status, kamar_id, nomor_kamar FROM pemesanan WHERE id = ? AND user_id = ? LIMIT 1");
    $chk->bind_param('ii', $booking_id, $user_id);
    $chk->execute();
    $row = $chk->get_result()->fetch_assoc();
    $chk->close();

    if (!$row) {
        echo json_encode(['success' => false, 'message' => 'Pesanan tidak ditemukan']);
        exit;
    }

    if ($row['status'] !== 'checked_in') {
        echo json_encode(['success' => false, 'message' => 'Checkout hanya bisa dari status checked_in']);
        exit;
    }

    $conn->begin_transaction();
    try {
        // Checkout pesanan + reset kamar_id & nomor_kamar
        $upd = $conn->prepare("UPDATE pemesanan SET status = 'checked_out', kamar_id = NULL, nomor_kamar = NULL WHERE id = ?");
        $upd->bind_param('i', $booking_id);
        $upd->execute();
        $upd->close();

        // Bebaskan kamar: utamakan kamar_id, fallback ke nomor_kamar
        if (!empty($row['kamar_id'])) {
            $free = $conn->prepare("UPDATE kamar SET status = 'available' WHERE id = ?");
            $free->bind_param('i', $row['kamar_id']);
            $free->execute();
            $free->close();
        } elseif (!empty($row['nomor_kamar'])) {
            $free = $conn->prepare("UPDATE kamar SET status = 'available' WHERE nomor_kamar = ?");
            $free->bind_param('s', $row['nomor_kamar']);
            $free->execute();
            $free->close();
        }

        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Checkout berhasil']);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Gagal: ' . $e->getMessage()]);
    }
    exit;
}

/* =========================
   AMBIL SEMUA PESANAN USER
========================= */
$stmt = $conn->prepare("
    SELECT *
    FROM pemesanan
    WHERE user_id = ?
    ORDER BY created_at DESC
");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$bookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$username = $_SESSION['username'] ?? $_SESSION['nama'] ?? $_SESSION['name'] ?? 'User';

$midtrans_client_key = defined('MIDTRANS_CLIENT_KEY') ? MIDTRANS_CLIENT_KEY : '';
$midtrans_snap_url   = (defined('MIDTRANS_IS_PRODUCTION') && MIDTRANS_IS_PRODUCTION)
    ? 'https://app.midtrans.com/snap/snap.js'
    : 'https://app.sandbox.midtrans.com/snap/snap.js';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Riwayat Pemesanan — Grand Lumière</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@300;400;500&family=Jost:wght@300;400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css">
<script src="<?= $midtrans_snap_url ?>" data-client-key="<?= htmlspecialchars($midtrans_client_key) ?>"></script>
<style>
*, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }

:root {
    --gold:       #c9a84c;
    --gold-light: #e8cc80;
    --gold-border:rgba(201,168,76,.25);
    --gold-dim:   rgba(201,168,76,.12);
    --navy:       #080e1c;
    --navy-card:  rgba(15,28,53,.92);
    --text:       #f0ead6;
    --muted:      rgba(240,234,214,.55);
    --success:    #4caf7d;
    --danger:     #e05252;
    --warn:       #f0a843;
    --info:       #5b9cf6;
}

body {
    background: var(--navy);
    color: var(--text);
    font-family: 'Jost', sans-serif;
    overflow-x: hidden;
    min-height: 100vh;
}

body::before {
    content: '';
    position: fixed; inset: 0;
    background: radial-gradient(circle at top left, rgba(201,168,76,.08), transparent 40%);
    pointer-events: none;
}

/* NAV */
nav {
    position: fixed; top: 0; left: 0; right: 0;
    height: 70px;
    display: flex; align-items: center; justify-content: space-between;
    padding: 0 28px;
    z-index: 1000;
    backdrop-filter: blur(18px);
    background: rgba(8,14,28,.88);
    border-bottom: 1px solid var(--gold-border);
}
.nav-brand {
    display: flex; align-items: center; gap: 10px;
    text-decoration: none; color: var(--text);
}
.nav-brand i { color: var(--gold); font-size: 24px; }
.nav-brand span { font-family: 'Cormorant Garamond', serif; letter-spacing: 2px; font-size: 20px; }
.nav-links { display: flex; list-style: none; gap: 16px; }
.nav-links a {
    text-decoration: none; color: var(--muted);
    display: flex; align-items: center; gap: 8px;
    padding: 8px 16px; border-radius: 6px; transition: .2s;
}
.nav-links a:hover, .nav-links a.active { color: var(--gold); background: var(--gold-dim); }
.nav-user { display: flex; align-items: center; gap: 14px; }
.nav-avatar {
    width: 40px; height: 40px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    background: var(--gold-dim); border: 1px solid var(--gold-border); color: var(--gold);
    font-weight: 600; font-size: 14px;
}
.logout-link { text-decoration: none; color: var(--muted); transition: .2s; }
.logout-link:hover { color: var(--danger); }

/* WRAPPER */
.page-wrapper { max-width: 1200px; margin: auto; padding: 100px 20px 60px; }

/* HEADER */
.page-header { text-align: center; margin-bottom: 36px; }
.page-header h1 { font-family: 'Cormorant Garamond', serif; font-size: 40px; margin-bottom: 6px; }
.page-header p { color: var(--muted); font-size: 14px; }

/* TOOLBAR */
.toolbar {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 16px; gap: 12px; flex-wrap: wrap;
}
.toolbar-left { color: var(--muted); font-size: 13px; }
.search-box {
    padding: 9px 16px; border-radius: 24px;
    background: var(--navy-card); border: 1px solid var(--gold-border);
    color: var(--text); outline: none; font-family: 'Jost', sans-serif;
    font-size: 13px; width: 240px; transition: border-color .2s;
}
.search-box:focus { border-color: var(--gold); }
.search-box::placeholder { color: var(--muted); }

/* TABLE */
.table-wrap {
    overflow-x: auto;
    border: 1px solid var(--gold-border);
    border-radius: 10px;
    background: var(--navy-card);
}
table { width: 100%; min-width: 860px; border-collapse: collapse; }
thead { background: rgba(0,0,0,.3); }
th { padding: 14px 16px; text-align: left; color: var(--muted); font-size: 11px; letter-spacing: 1px; text-transform: uppercase; }
td { padding: 14px 16px; border-top: 1px solid var(--gold-border); vertical-align: middle; }
tr:hover td { background: rgba(255,255,255,.02); }
tr.is-done td { opacity: .55; }

/* BADGE */
.badge { padding: 4px 11px; border-radius: 20px; font-size: 11px; font-weight: 600; white-space: nowrap; transition: background .4s, color .4s; }
.badge-pending       { background: rgba(240,168,67,.15); color: var(--warn); }
.badge-confirmed     { background: rgba(76,175,125,.15); color: var(--success); }
.badge-cancelled     { background: rgba(224,82,82,.15);  color: var(--danger); }
.badge-checked_in    { background: rgba(91,156,246,.15); color: var(--info); }
.badge-checked_out   { background: rgba(255,255,255,.08);color: #aaa; }

/* TOMBOL */
.btn {
    padding: 6px 12px; border-radius: 6px;
    cursor: pointer; border: none; font-size: 12px;
    font-family: 'Jost', sans-serif; font-weight: 500;
    transition: .2s; display: inline-flex; align-items: center; gap: 5px;
}
.btn-detail   { background: var(--gold-dim); color: var(--gold); border: 1px solid var(--gold-border); }
.btn-detail:hover { opacity: .8; }
.btn-cancel   { background: rgba(224,82,82,.15); color: var(--danger); }
.btn-cancel:hover { background: var(--danger); color: #fff; }
.btn-checkout { background: rgba(91,156,246,.15); color: var(--info); }
.btn-checkout:hover { background: var(--info); color: #fff; }
.btn-pay      { background: rgba(76,175,125,.15); color: var(--success); border: 1px solid rgba(76,175,125,.3); font-weight: 600; }
.btn-pay:hover { background: var(--success); color: #fff; }
.btn-pay i { font-size: 14px; }
.act-wrap { display: flex; gap: 6px; flex-wrap: wrap; }

/* KOSONG */
.empty { text-align: center; padding: 70px 20px; color: var(--muted); }
.empty i { font-size: 54px; display: block; margin-bottom: 12px; opacity: .4; }

/* TOAST */
#toast {
    position: fixed; bottom: 30px; left: 50%;
    transform: translateX(-50%) translateY(80px);
    background: var(--navy-card); border: 1px solid var(--gold-border);
    padding: 11px 24px; border-radius: 30px;
    font-size: 13px; transition: .3s; opacity: 0; z-index: 9999;
    white-space: nowrap;
}
#toast.show { opacity: 1; transform: translateX(-50%) translateY(0); }
#toast.success { color: var(--success); border-color: var(--success); }
#toast.error   { color: var(--danger);  border-color: var(--danger); }

/* CONFIRM OVERLAY */
.confirm-overlay {
    position: fixed; inset: 0; display: none;
    align-items: center; justify-content: center;
    background: rgba(0,0,0,.75); backdrop-filter: blur(6px); z-index: 2000;
}
.confirm-overlay.open { display: flex; }
.confirm-box {
    background: var(--navy-card); border: 1px solid var(--gold-border);
    border-radius: 10px; padding: 30px 28px;
    max-width: 380px; width: 95%; text-align: center;
    animation: fadeUp .25s ease;
}
.confirm-box p { color: var(--text); font-size: 15px; line-height: 1.6; margin-bottom: 22px; }
.confirm-actions { display: flex; justify-content: center; gap: 10px; }
.btn-yes { background: var(--danger); color: #fff; padding: 9px 22px; border: none; border-radius: 6px; cursor: pointer; font-size: 13px; font-family: 'Jost', sans-serif; }
.btn-no  { background: rgba(255,255,255,.08); color: var(--text); padding: 9px 22px; border: 1px solid var(--gold-border); border-radius: 6px; cursor: pointer; font-size: 13px; font-family: 'Jost', sans-serif; }
.btn-yes:hover { opacity: .85; }
.btn-no:hover  { border-color: var(--gold); color: var(--gold); }

/* DETAIL MODAL */
.modal-overlay {
    position: fixed; inset: 0; display: none;
    align-items: center; justify-content: center;
    background: rgba(0,0,0,.75); backdrop-filter: blur(6px); z-index: 2000;
}
.modal-overlay.open { display: flex; }
.modal {
    background: var(--navy-card); border: 1px solid var(--gold-border);
    border-radius: 10px; width: 95%; max-width: 500px;
    max-height: 90vh; overflow-y: auto;
    animation: fadeUp .25s ease;
}
.modal-head {
    display: flex; justify-content: space-between; align-items: center;
    padding: 18px 22px; border-bottom: 1px solid var(--gold-border);
}
.modal-head h3 { font-family: 'Cormorant Garamond', serif; font-size: 20px; }
.modal-close { background: none; border: none; color: var(--muted); font-size: 22px; cursor: pointer; }
.modal-close:hover { color: var(--text); }
.modal-body { padding: 20px 22px; }
.detail-item {
    display: flex; justify-content: space-between; align-items: center;
    padding: 10px 0; border-bottom: 1px solid rgba(255,255,255,.06);
    font-size: 14px;
}
.detail-item:last-child { border-bottom: none; }
.detail-item span:first-child { color: var(--muted); }
.detail-item span:last-child  { font-weight: 500; text-align: right; }

/* STATUS CHANGE HIGHLIGHT */
@keyframes statusFlash {
    0%   { box-shadow: 0 0 0 0 rgba(201,168,76,.6); }
    50%  { box-shadow: 0 0 0 6px rgba(201,168,76,.15); }
    100% { box-shadow: 0 0 0 0 rgba(201,168,76,0); }
}
.badge.updated { animation: statusFlash .8s ease; }

@keyframes fadeUp { from { opacity:0; transform: translateY(14px); } to { opacity:1; transform: none; } }

@media (max-width: 600px) {
    .nav-links span { display: none; }
    .search-box { width: 100%; }
    .toolbar { flex-direction: column; align-items: flex-start; }
}
</style>
</head>
<body>

<nav>
    <a href="mainpage.php" class="nav-brand">
        <i class="ti ti-crown"></i>
        <span>Grand Lumière</span>
    </a>
    <ul class="nav-links">
        <li><a href="mainpage.php"><i class="ti ti-home"></i><span>Home</span></a></li>
        <li><a href="riwayat.php" class="active"><i class="ti ti-list"></i><span>Riwayat</span></a></li>
    </ul>
    <div class="nav-user">
        <div class="nav-avatar"><?= strtoupper(substr($username, 0, 2)) ?></div>
        <a href="logout.php" class="logout-link"><i class="ti ti-logout"></i> Logout</a>
    </div>
</nav>

<div class="page-wrapper">

    <div class="page-header">
        <h1>Riwayat Pemesanan</h1>
        <p>Halo, <?= htmlspecialchars($username) ?>! Berikut semua pesanan kamu.</p>
    </div>

    <div class="toolbar">
        <span class="toolbar-left">
            Total <strong><?= count($bookings) ?></strong> pesanan
        </span>
        <input
            type="text"
            class="search-box"
            id="searchInput"
            placeholder=" Cari kode, kamar…"
        >
    </div>

    <div class="table-wrap">
        <table id="bookingsTable">
            <thead>
                <tr>
                    <th>Kode Booking</th>
                    <th>Kamar</th>
                    <th>No. Kamar</th>
                    <th>Check In</th>
                    <th>Check Out</th>
                    <th>Malam</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>

            <?php if (empty($bookings)): ?>
                <tr>
                    <td colspan="9">
                        <div class="empty">
                            <i class="ti ti-inbox"></i>
                            <p>Belum ada pemesanan</p>
                        </div>
                    </td>
                </tr>

            <?php else: foreach ($bookings as $b):
                $done = in_array($b['status'], ['cancelled', 'checked_out']);
            ?>
                <tr
                    class="<?= $done ? 'is-done' : '' ?>"
                    data-search="<?= strtolower(htmlspecialchars($b['kode_booking'].' '.$b['tipe_kamar'])) ?>"
                    data-id="<?= intval($b['id']) ?>"
                >
                    <td style="font-family:monospace;font-size:13px">
                        <?= htmlspecialchars($b['kode_booking'] ?? '—') ?>
                    </td>
                    <td><?= htmlspecialchars($b['tipe_kamar'] ?? '—') ?></td>
                    <td class="nomor-kamar-cell">
                        <?php if (!empty($b['nomor_kamar'])): ?>
                            <span style="background:rgba(91,156,246,.15);color:#5b9cf6;padding:3px 9px;border-radius:20px;font-size:11px;font-weight:600;">
                                <i class="ti ti-door-enter"></i> <?= htmlspecialchars($b['nomor_kamar']) ?>
                            </span>
                        <?php elseif ($b['status'] === 'checked_in'): ?>
                            <span style="color:var(--warn);font-size:12px;">— Menunggu</span>
                        <?php else: ?>
                            <span style="color:var(--muted);font-size:12px;">—</span>
                        <?php endif; ?>
                    </td>
                    <td><?= $b['check_in']  ? date('d M Y', strtotime($b['check_in']))  : '—' ?></td>
                    <td><?= $b['check_out'] ? date('d M Y', strtotime($b['check_out'])) : '—' ?></td>
                    <td style="text-align:center"><?= intval($b['jumlah_malam']) ?> mlm</td>
                    <td style="color:var(--gold)">Rp<?= number_format($b['total'], 0, ',', '.') ?></td>
                    <td>
                        <span
                            class="badge badge-<?= htmlspecialchars($b['status']) ?>"
                            data-status="<?= htmlspecialchars($b['status']) ?>"
                        >
                            <?php
                                $statusLabel = [
                                    'pending'     => 'Pending',
                                    'confirmed'   => 'Confirmed',
                                    'checked_in'  => 'Check In',
                                    'checked_out' => 'Check Out',
                                    'cancelled'   => 'Dibatalkan',
                                ];
                                echo $statusLabel[$b['status']] ?? ucfirst(str_replace('_', ' ', $b['status']));
                            ?>
                        </span>
                    </td>
                    <td>
                        <div class="act-wrap" data-actions>

                            <button class="btn btn-detail"
                                onclick='showDetail(<?= json_encode($b, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                                <i class="ti ti-eye"></i> Detail
                            </button>

                            <?php if ($b['status'] === 'pending'): ?>
                                <?php if (!empty($b['snap_token'])): ?>
                                    <button class="btn btn-pay"
                                        onclick='doBayar("<?= htmlspecialchars($b["snap_token"], ENT_QUOTES) ?>","<?= htmlspecialchars($b["kode_booking"], ENT_QUOTES) ?>")'>
                                        <i class="ti ti-credit-card"></i> Bayar Sekarang
                                    </button>
                                <?php else: ?>
                                    <button class="btn btn-pay" onclick='getBayarToken(<?= intval($b["id"]) ?>)'>
                                        <i class="ti ti-credit-card"></i> Bayar Sekarang
                                    </button>
                                <?php endif; ?>
                            <?php endif; ?>

                            <?php if (in_array($b['status'], ['pending', 'confirmed', 'checked_in'])): ?>
                                <button class="btn btn-cancel"
                                    onclick='confirmCancel(<?= intval($b["id"]) ?>,"<?= htmlspecialchars($b["kode_booking"], ENT_QUOTES) ?>")'>
                                    <i class="ti ti-x"></i> Cancel
                                </button>
                            <?php endif; ?>

                            <?php if ($b['status'] === 'checked_in'): ?>
                                <button class="btn btn-checkout"
                                    onclick='doCheckout(<?= intval($b["id"]) ?>,"<?= htmlspecialchars($b["kode_booking"], ENT_QUOTES) ?>")'>
                                    <i class="ti ti-door-exit"></i> Checkout
                                </button>
                            <?php endif; ?>

                        </div>
                    </td>
                </tr>
            <?php endforeach; endif; ?>

            </tbody>
        </table>
    </div>

</div>

<!-- TOAST -->
<div id="toast"></div>

<!-- CONFIRM CANCEL -->
<div class="confirm-overlay" id="confirmOverlay">
    <div class="confirm-box">
        <p id="confirmMsg"></p>
        <div class="confirm-actions">
            <button class="btn-yes" id="confirmYes">Ya, Cancel</button>
            <button class="btn-no" onclick="closeConfirm()">Tidak</button>
        </div>
    </div>
</div>

<!-- DETAIL MODAL -->
<div class="modal-overlay" id="detailModal">
    <div class="modal">
        <div class="modal-head">
            <h3>Detail Pemesanan</h3>
            <button class="modal-close" onclick="closeModal()">×</button>
        </div>
        <div class="modal-body" id="modalBody"></div>
    </div>
</div>

<script>
/* ── TOAST ── */
function showToast(msg, type = 'success') {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.className = 'show ' + type;
    clearTimeout(t._timer);
    t._timer = setTimeout(() => t.className = '', 3000);
}

/* ── SEARCH ── */
document.getElementById('searchInput').addEventListener('input', function () {
    const q = this.value.toLowerCase();
    document.querySelectorAll('#bookingsTable tbody tr[data-search]').forEach(tr => {
        tr.style.display = tr.dataset.search.includes(q) ? '' : 'none';
    });
});

/* ── CANCEL ── */
let cancelId = null;

function confirmCancel(id, kode) {
    cancelId = id;
    document.getElementById('confirmMsg').innerHTML =
        'Yakin ingin membatalkan booking <b>' + kode + '</b>?<br>'
        + '<small style="color:var(--muted);font-size:12px">Pesanan dibatalkan & kamar dibebaskan otomatis.</small>';
    document.getElementById('confirmOverlay').classList.add('open');
}

function closeConfirm() {
    cancelId = null;
    document.getElementById('confirmOverlay').classList.remove('open');
}

document.getElementById('confirmYes').addEventListener('click', async () => {
    if (!cancelId) return;

    const fd = new FormData();
    fd.append('action', 'cancel_request');
    fd.append('booking_id', cancelId);

    try {
        const res  = await fetch('riwayat.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            showToast(data.message, 'success');
            updateRowDOM(cancelId, 'cancelled', null);
        } else {
            showToast(data.message, 'error');
        }
    } catch (e) {
        console.error(e);
        showToast('Server error', 'error');
    }

    closeConfirm();
});

/* ── BAYAR (MIDTRANS SNAP) ── */
function doBayar(snapToken, kode) {
    if (typeof window.snap === 'undefined') {
        showToast('Midtrans Snap belum siap, coba refresh halaman', 'error');
        return;
    }
    window.snap.pay(snapToken, {
        onSuccess: function(result) {
            showToast('Pembayaran berhasil! Booking: ' + kode, 'success');
            setTimeout(() => location.reload(), 1500);
        },
        onPending: function(result) {
            showToast('Pembayaran pending, silakan selesaikan', 'success');
        },
        onError: function(result) {
            showToast('Pembayaran gagal: ' + (result.status_message || 'Terjadi kesalahan'), 'error');
        },
        onClose: function() {
            showToast('Jendela pembayaran ditutup', 'error');
        }
    });
}

async function getBayarToken(bookingId) {
    showToast('Mengambil token pembayaran…', 'success');

    const fd = new FormData();
    fd.append('action', 'get_snap_token');
    fd.append('booking_id', bookingId);

    try {
        const res  = await fetch('riwayat.php', { method: 'POST', body: fd });
        const text = await res.text();

        let data;
        try {
            data = JSON.parse(text);
        } catch (e) {
            console.error('Response bukan JSON:', text);
            showToast('Server error: response tidak valid', 'error');
            return;
        }

        if (data.success && data.snap_token) {
            doBayar(data.snap_token, data.kode_booking);
        } else {
            showToast(data.message || 'Gagal mendapatkan token pembayaran', 'error');
        }
    } catch (e) {
        console.error(e);
        showToast('Server error saat ambil token', 'error');
    }
}

/* ── CHECKOUT ── */
async function doCheckout(id, kode) {
    if (!confirm('Checkout booking ' + kode + '?')) return;

    const fd = new FormData();
    fd.append('action', 'checkout');
    fd.append('booking_id', id);

    try {
        const res  = await fetch('riwayat.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            showToast(data.message, 'success');
            updateRowDOM(id, 'checked_out', null);
        } else {
            showToast(data.message, 'error');
        }
    } catch (e) {
        console.error(e);
        showToast('Server error', 'error');
    }
}

/* ── DETAIL MODAL ── */
function showDetail(b) {
    const statusLabel = {
        pending:     'Pending',
        confirmed:   'Confirmed',
        checked_in:  'Check In',
        checked_out: 'Check Out',
        cancelled:   'Dibatalkan',
    };

    const fmt = n => 'Rp' + new Intl.NumberFormat('id-ID').format(n);

    document.getElementById('modalBody').innerHTML = `
        <div class="detail-item"><span>Kode Booking</span><span>${b.kode_booking ?? '—'}</span></div>
        <div class="detail-item"><span>Tipe Kamar</span><span>${b.tipe_kamar ?? '—'}</span></div>
        <div class="detail-item"><span>Nomor Kamar</span><span style="color:${b.nomor_kamar ? '#5b9cf6' : 'var(--warn)'}">
            ${b.nomor_kamar ? '🏨 ' + b.nomor_kamar : (b.status === 'checked_in' ? '⏳ Menunggu penugasan admin' : '—')}
        </span></div>
        <div class="detail-item"><span>Check In</span><span>${b.check_in ?? '—'}</span></div>
        <div class="detail-item"><span>Check Out</span><span>${b.check_out ?? '—'}</span></div>
        <div class="detail-item"><span>Jumlah Malam</span><span>${b.jumlah_malam} malam</span></div>
        <div class="detail-item"><span>Total</span><span style="color:var(--gold)">${fmt(b.total)}</span></div>
        <div class="detail-item"><span>Status</span><span>${statusLabel[b.status] ?? b.status}</span></div>
    `;

    document.getElementById('detailModal').classList.add('open');
}

function closeModal() {
    document.getElementById('detailModal').classList.remove('open');
}

/* ── CLOSE ON BACKDROP / ESC ── */
document.querySelectorAll('.modal-overlay, .confirm-overlay').forEach(el => {
    el.addEventListener('click', e => { if (e.target === el) el.classList.remove('open'); });
});

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') { closeModal(); closeConfirm(); }
});

/* ══════════════════════════════════════════
   AUTO POLLING — update DOM langsung,
   tanpa reload halaman sama sekali
══════════════════════════════════════════ */
const STATUS_LABEL = {
    pending:     'Pending',
    confirmed:   'Confirmed',
    checked_in:  'Check In',
    checked_out: 'Check Out',
    cancelled:   'Dibatalkan',
};

const STATUS_BADGE = {
    pending:     'badge-pending',
    confirmed:   'badge-confirmed',
    checked_in:  'badge-checked_in',
    checked_out: 'badge-checked_out',
    cancelled:   'badge-cancelled',
};

function updateRowDOM(id, newStatus, nomorKamar) {
    const row = document.querySelector(`tr[data-id="${id}"]`);
    if (!row) return;

    const badge = row.querySelector('.badge');
    if (!badge) return;

    const oldStatus = badge.dataset.status;
    if (oldStatus === newStatus && nomorKamar === null) return;

    // Update badge
    badge.dataset.status = newStatus;
    badge.className = 'badge ' + (STATUS_BADGE[newStatus] ?? '');
    badge.textContent = STATUS_LABEL[newStatus] ?? newStatus;

    // Animasi flash
    badge.classList.remove('updated');
    void badge.offsetWidth;
    badge.classList.add('updated');

    // Update opacity baris
    const done = ['cancelled', 'checked_out'].includes(newStatus);
    row.classList.toggle('is-done', done);

    // Update nomor kamar jika ada
    if (nomorKamar !== null) {
        const cell = row.querySelector('.nomor-kamar-cell');
        if (cell && nomorKamar) {
            cell.innerHTML = `<span style="background:rgba(91,156,246,.15);color:#5b9cf6;padding:3px 9px;border-radius:20px;font-size:11px;font-weight:600;"><i class="ti ti-door-enter"></i> ${nomorKamar}</span>`;
        }
    }

    // Update tombol aksi
    const actWrap = row.querySelector('[data-actions]');
    if (actWrap) {
        actWrap.querySelectorAll('.btn-pay, .btn-cancel, .btn-checkout').forEach(b => b.remove());

        const kode = row.querySelector('td:first-child')?.textContent?.trim() ?? '';

        if (newStatus === 'pending') {
            const btnPay = document.createElement('button');
            btnPay.className = 'btn btn-pay';
            btnPay.innerHTML = '<i class="ti ti-credit-card"></i> Bayar Sekarang';
            btnPay.onclick = () => getBayarToken(id);
            actWrap.appendChild(btnPay);
        }

        if (['pending', 'confirmed', 'checked_in'].includes(newStatus)) {
            const btnCancel = document.createElement('button');
            btnCancel.className = 'btn btn-cancel';
            btnCancel.innerHTML = '<i class="ti ti-x"></i> Cancel';
            btnCancel.onclick = () => confirmCancel(id, kode);
            actWrap.appendChild(btnCancel);
        }

        if (newStatus === 'checked_in') {
            const btnCO = document.createElement('button');
            btnCO.className = 'btn btn-checkout';
            btnCO.innerHTML = '<i class="ti ti-door-exit"></i> Checkout';
            btnCO.onclick = () => doCheckout(id, kode);
            actWrap.appendChild(btnCO);
        }
    }
}

/* Polling tiap 15 detik */
async function checkStatusUpdate() {
    if (document.getElementById('detailModal').classList.contains('open')) return;
    if (document.getElementById('confirmOverlay').classList.contains('open')) return;

    try {
        const res = await fetch('riwayat.php?check_update=1', { cache: 'no-store' });
        if (!res.ok) return;

        const data = await res.json();
        if (!Array.isArray(data.bookings)) return;

        data.bookings.forEach(b => {
            const row = document.querySelector(`tr[data-id="${b.id}"]`);
            if (!row) return;

            const badge = row.querySelector('.badge');
            if (!badge) return;

            const currentStatus = badge.dataset.status;
            const nomorCell = row.querySelector('.nomor-kamar-cell');
            const currentNomor = nomorCell?.querySelector('span[style*="5b9cf6"]')?.textContent?.trim() ?? '';
            const newNomor = b.nomor_kamar ?? '';

            const statusChanged = currentStatus !== b.status;
            const nomorChanged  = newNomor && currentNomor !== newNomor;

            if (!statusChanged && !nomorChanged) return;

            updateRowDOM(b.id, b.status, nomorChanged ? newNomor : null);

            if (statusChanged) {
                showToast(`Booking diperbarui → ${STATUS_LABEL[b.status] ?? b.status}`, 'success');
            } else if (nomorChanged) {
                showToast(`Nomor kamar ditetapkan: ${newNomor}`, 'success');
            }
        });

    } catch (e) { /* diam saja */ }
}

setInterval(checkStatusUpdate, 15000);
</script>
</body>
</html>