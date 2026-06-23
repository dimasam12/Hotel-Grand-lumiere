<?php
// FIX 1 & 5: Konfigurasi session sebelum session_start() agar lebih stabil
ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_samesite', 'Lax');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../config.php';

// FIX 3 & 5: Fungsi pengecekan login yang konsisten + session_destroy() tepat
function checkAdminSession() {
    if (!isset($_SESSION['user_id'])) {
        return false;
    }

    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
        return false;
    }

    // Cek batas waktu sesi (8 jam)
    if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time'] > 28800)) {
        // FIX 5: session_destroy() yang tepat — unset dulu, baru destroy
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $p['path'], $p['domain'], $p['secure'], $p['httponly']
            );
        }
        session_destroy();
        return false;
    }

    // FIX 1: Perbarui login_time agar session tidak kedaluwarsa saat aktif
    $_SESSION['login_time'] = time();

    return true;
}

// Validasi session
if (!checkAdminSession()) {
    // FIX 5: Bersihkan session dengan benar sebelum redirect
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $p['path'], $p['domain'], $p['secure'], $p['httponly']
        );
    }
    session_destroy();
    header('Location: ../index.html');
    exit;
}

// ── EXPORT CSV (SUPPORT SEMUA FILTER) ──
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $sf     = $_GET['sf'] ?? '';           // status filter
    $ci     = $_GET['ci'] ?? '';           // check-in tanggal
    $co     = $_GET['co'] ?? '';           // check-out tanggal
    $pmin   = $_GET['pmin'] ?? '';
    $pmax   = $_GET['pmax'] ?? '';
    $search = $_GET['search'] ?? '';   // search keyword

    $where = ['1=1'];
    $params = [];
    $types = '';

    // Filter status
    if ($sf && $sf !== 'all') {
        $where[] = 'p.status = ?';
        $params[] = $sf;
        $types .= 's';
    }

    // Filter check-in tepat
    if ($ci) {
        $where[] = 'p.check_in = ?';
        $params[] = $ci;
        $types .= 's';
    }

    // Filter check-out tepat
    if ($co) {
        $where[] = 'p.check_out = ?';
        $params[] = $co;
        $types .= 's';
    }

    // Filter harga
    if ($pmin !== '') {
        $where[] = 'p.total >= ?';
        $params[] = floatval($pmin);
        $types .= 'd';
    }
    if ($pmax !== '') {
        $where[] = 'p.total <= ?';
        $params[] = floatval($pmax);
        $types .= 'd';
    }

    // Filter search (nama tamu, email, kode booking, tipe kamar)
    if ($search !== '') {
        $where[] = '(p.nama_tamu LIKE ? OR p.email_tamu LIKE ? OR p.kode_booking LIKE ? OR p.tipe_kamar LIKE ?)';
        $searchParam = "%{$search}%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
        $types .= 'ssss';
    }

    $sql = '
        SELECT 
            p.id,
            p.kode_booking,
            p.nama_tamu,
            p.email_tamu,
            p.tipe_kamar,
            p.check_in,
            p.check_out,
            p.jumlah_malam,
            p.total,
            p.status,
            u.username,
            u.email
        FROM pemesanan p
        LEFT JOIN users u ON p.user_id = u.id
        WHERE ' . implode(' AND ', $where) . '
        ORDER BY p.created_at DESC
    ';

    $stmt = $conn->prepare($sql);

    if ($types) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();

    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $stmt->close();

    // FIX CSV: Matikan display error supaya warning PHP tidak ikut ter-export
    ini_set('display_errors', '0');
    error_reporting(0);

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="pemesanan_' . date('Ymd_His') . '.csv"');

    $out = fopen('php://output', 'w');

    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

    // FIX CSV: tambah parameter escape '' untuk PHP 8.4+
    fputcsv($out, [
        'ID',
        'Kode Booking',
        'Nama Tamu',
        'Email Tamu',
        'Tipe Kamar',
        'Check-in',
        'Check-out',
        'Jumlah Malam',
        'Total',
        'Status',
        'Username',
        'Email User'
    ], ',', '"', '');

    foreach ($rows as $r) {
        fputcsv($out, [
            $r['id'],
            $r['kode_booking'] ?? '',
            $r['nama_tamu'] ?? '',
            $r['email_tamu'] ?? '',
            $r['tipe_kamar'] ?? '',
            $r['check_in'] ?? '',
            $r['check_out'] ?? '',
            $r['jumlah_malam'] ?? '',
            $r['total'] ?? 0,
            $r['status'],
            $r['username'] ?? '',
            $r['email'] ?? ''
        ], ',', '"', '');
    }

    fclose($out);
    exit;
}

/* =========================
   AJAX ACTION
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {

    header('Content-Type: application/json');

    $action = $_POST['action'] ?? '';

    // DELETE
    if ($action === 'delete_order') {
        $id = intval($_POST['id']);

        $stmt = $conn->prepare("DELETE FROM pemesanan WHERE id = ?");
        $stmt->bind_param('i', $id);
        $success = $stmt->execute();
        $stmt->close();

        echo json_encode(['success' => $success]);
        exit;
    }

    // UPDATE STATUS
    if ($action === 'update_status') {
        $id = intval($_POST['id']);
        $status = $_POST['status'];

        $allowed = [
            'pending',
            'checked_in',
            'checked_out',
            'cancelled',
        ];

        if (!in_array($status, $allowed)) {
            echo json_encode([
                'success' => false,
                'message' => 'Status tidak valid'
            ]);
            exit;
        }

        $stmt = $conn->prepare("UPDATE pemesanan SET status = ? WHERE id = ?");
        $stmt->bind_param('si', $status, $id);
        $success = $stmt->execute();
        $stmt->close();

        echo json_encode(['success' => $success]);
        exit;
    }

    // UPDATE RESERVATION
    if ($action === 'update_reservation') {
        $id           = intval($_POST['id']);
        $check_in     = $_POST['check_in'] ?? '';
        $check_out    = $_POST['check_out'] ?? '';
        $jumlah_malam = intval($_POST['jumlah_malam'] ?? 1);
        $tipe_kamar   = trim($_POST['tipe_kamar'] ?? '');
        $total        = floatval($_POST['total'] ?? 0);

        $stmt = $conn->prepare("
            UPDATE pemesanan
            SET 
                check_in     = ?,
                check_out    = ?,
                jumlah_malam = ?,
                tipe_kamar   = ?,
                total        = ?
            WHERE id = ?
        ");

        $stmt->bind_param('ssisdi', $check_in, $check_out, $jumlah_malam, $tipe_kamar, $total, $id);
        $success = $stmt->execute();
        $stmt->close();

        echo json_encode(['success' => $success]);
        exit;
    }

    // UPDATE USER ROLE
    if ($action === 'update_role') {
        $id = intval($_POST['id']);
        $role = $_POST['role'];

        if (!in_array($role, ['admin', 'user'])) {
            echo json_encode(['success' => false, 'message' => 'Role tidak valid']);
            exit;
        }

        $stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
        $stmt->bind_param('si', $role, $id);
        $success = $stmt->execute();
        $stmt->close();

        echo json_encode(['success' => $success]);
        exit;
    }

    echo json_encode(['error' => 'Invalid action']);
    exit;
}

/* =========================
   AMBIL DATA (SEMUA STATUS)
   TIDAK ADA FILTER OTOMATIS
========================= */
$orders_result = $conn->query("
    SELECT 
        p.*,
        u.username AS nama_user,
        u.email
    FROM pemesanan p
    LEFT JOIN users u ON p.user_id = u.id
    ORDER BY p.created_at DESC
");

$orders = $orders_result ? $orders_result->fetch_all(MYSQLI_ASSOC) : [];

/* =========================
   USERS
========================= */
$users_result = $conn->query("
    SELECT id, username, email, role, created_at
    FROM users
    ORDER BY created_at DESC
");

$users = $users_result ? $users_result->fetch_all(MYSQLI_ASSOC) : [];

/* =========================
   STATISTIK (SEMUA STATUS)
========================= */
$total_orders = count($orders);

$pending_count = count(array_filter($orders, fn($o) => $o['status'] === 'pending'));

$checkin_count = count(array_filter($orders, fn($o) => $o['status'] === 'checked_in'));

$total_revenue = array_sum(array_map(fn($o) => $o['total'] ?? 0, $orders));

$cancelled_count = count(array_filter($orders, fn($o) => $o['status'] === 'cancelled'));

$checked_out_count = count(array_filter($orders, fn($o) => $o['status'] === 'checked_out'));


?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard — Grand Lumière</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
:root {
  --bg:       #080e1a;
  --bg2:      #0d1525;
  --surface:  #111d2e;
  --surface2: #172035;
  --border:   #1e2d42;
  --gold:     #d4af37;
  --gold-dim: #8a7020;
  --text:     #e8e2d4;
  --muted:    #6b7a90;
  --danger:   #e05252;
  --success:  #4caf7d;
  --info:     #5b9cf6;
  --warn:     #f0a843;
  --r:        12px;
  --font-h:   'Playfair Display', serif;
  --font-b:   'DM Sans', sans-serif;
}
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { scroll-behavior: smooth; }
body { font-family: var(--font-b); background: var(--bg); color: var(--text); min-height: 100vh; display: flex; }

.sidebar {
  width: 250px; position: fixed; top: 0; left: 0; bottom: 0;
  background: var(--bg2);
  border-right: 1px solid var(--border);
  display: flex; flex-direction: column;
  z-index: 100;
}
.logo {
  padding: 30px 24px 22px;
  border-bottom: 1px solid var(--border);
}
.logo .crown { font-size: 22px; margin-bottom: 6px; display: block; }
.logo h2 { font-family: var(--font-h); font-size: 20px; color: var(--gold); letter-spacing: 1px; }
.logo small { font-size: 10px; letter-spacing: 3px; text-transform: uppercase; color: var(--muted); }

.nav { flex: 1; padding: 18px 0; }
.nav a {
  display: flex; align-items: center; gap: 11px;
  padding: 11px 22px;
  color: var(--muted); text-decoration: none;
  font-size: 14px; font-weight: 500;
  border-left: 3px solid transparent;
  transition: all .2s;
}
.nav a i { font-size: 17px; }
.nav a:hover { color: var(--text); background: rgba(212,175,55,.06); }
.nav a.active { color: var(--gold); border-left-color: var(--gold); background: rgba(212,175,55,.09); }

.sidebar-foot {
  padding: 18px 22px; border-top: 1px solid var(--border); font-size: 13px;
}
.sidebar-foot strong { display: block; color: var(--text); margin-bottom: 2px; font-size: 14px; }
.sidebar-foot span { color: var(--muted); font-size: 11px; }
.logout-btn {
  display: inline-flex; align-items: center; gap: 6px;
  margin-top: 12px; padding: 7px 14px; border-radius: 8px;
  background: rgba(224,82,82,.12); border: 1px solid rgba(224,82,82,.25);
  color: var(--danger); font-size: 13px; font-family: var(--font-b);
  text-decoration: none; cursor: pointer; transition: all .2s;
}
.logout-btn:hover { background: var(--danger); color: #fff; }

.main { margin-left: 250px; flex: 1; display: flex; flex-direction: column; }
.topbar {
  background: var(--bg2); border-bottom: 1px solid var(--border);
  padding: 18px 32px; display: flex; align-items: center;
  justify-content: space-between; position: sticky; top: 0; z-index: 50;
}
.topbar h1 { font-family: var(--font-h); font-size: 20px; color: var(--text); }
.badge-admin {
  background: rgba(212,175,55,.12); color: var(--gold);
  border: 1px solid var(--gold-dim); font-size: 11px;
  font-weight: 600; letter-spacing: 1.5px; text-transform: uppercase;
  padding: 5px 13px; border-radius: 20px;
}

.content { padding: 32px; flex: 1; }

.section { display: none; }
.section.active { display: block; }

.stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 18px; margin-bottom: 32px; }
.stat-card {
  background: var(--surface); border: 1px solid var(--border);
  border-radius: var(--r); padding: 22px; position: relative; overflow: hidden;
}
.stat-card::before {
  content: ''; position: absolute; top: 0; left: 0; right: 0; height: 2px;
  background: var(--c, var(--gold));
}
.stat-card .st-label { font-size: 10px; text-transform: uppercase; letter-spacing: 2px; color: var(--muted); margin-bottom: 10px; }
.stat-card .st-val { font-family: var(--font-h); font-size: 30px; color: var(--text); }
.stat-card .st-icon { position: absolute; right: 18px; top: 18px; font-size: 26px; opacity: .1; }

.sec-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 18px; }
.sec-title { font-family: var(--font-h); font-size: 18px; }

.table-wrap { background: var(--surface); border: 1px solid var(--border); border-radius: var(--r); overflow-x: auto; overflow-y: visible; }
table { width: 100%; border-collapse: collapse; font-size: 13px; }
thead { background: var(--surface2); border-bottom: 1px solid var(--border); }
th { padding: 13px 16px; text-align: left; font-size: 10px; text-transform: uppercase; letter-spacing: 1.5px; color: var(--muted); font-weight: 600; white-space: nowrap; }
td { padding: 13px 16px; border-bottom: 1px solid var(--border); color: var(--text); vertical-align: middle; }
tr:last-child td { border-bottom: none; }
tr:hover td { background: rgba(255,255,255,.015); }

.badge { display: inline-block; padding: 3px 9px; border-radius: 20px; font-size: 11px; font-weight: 600; text-transform: capitalize; }
.badge-pending    { background: rgba(240,168,67,.15); color: var(--warn); }
.badge-checked_in { background: rgba(91,156,246,.15); color: var(--info); }
.badge-checked_out{ background: rgba(107,122,144,.15); color: var(--muted); }
.badge-cancelled  { background: rgba(224,82,82,.15); color: var(--danger); }

.btn {
  display: inline-flex; align-items: center; gap: 5px;
  padding: 7px 13px; border-radius: 8px; font-size: 13px; font-weight: 500;
  font-family: var(--font-b); cursor: pointer; border: none; transition: all .2s;
}
.btn-gold   { background: var(--gold); color: #080e1a; }
.btn-gold:hover { background: #e2c450; }
.btn-ghost  { background: transparent; border: 1px solid var(--border); color: var(--muted); }
.btn-ghost:hover { border-color: var(--gold); color: var(--gold); }
.btn-danger { background: rgba(224,82,82,.12); color: var(--danger); border: 1px solid rgba(224,82,82,.3); }
.btn-danger:hover { background: var(--danger); color: #fff; }
.btn-info   { background: rgba(91,156,246,.12); color: var(--info); border: 1px solid rgba(91,156,246,.3); }
.btn-info:hover { background: var(--info); color: #fff; }
.btn-warn   { background: rgba(240,168,67,.12); color: var(--warn); border: 1px solid rgba(240,168,67,.3); }
.btn-warn:hover { background: var(--warn); color: #080e1a; }
.btn-success { background: rgba(76,175,125,.12); color: var(--success); border: 1px solid rgba(76,175,125,.3); }
.btn-success:hover { background: var(--success); color: #fff; }
.btn-sm { padding: 5px 9px; font-size: 12px; }

.act-cell { display: flex; gap: 5px; flex-wrap: wrap; }

.filter-bar { display: flex; gap: 8px; margin-bottom: 18px; flex-wrap: wrap; align-items: center; }
.filter-btn {
  padding: 6px 14px; border-radius: 20px; font-size: 12px; font-weight: 600;
  cursor: pointer; border: 1px solid var(--border); background: transparent; color: var(--muted);
  font-family: var(--font-b); transition: all .2s;
}
.filter-btn.active, .filter-btn:hover { background: var(--gold); color: #080e1a; border-color: var(--gold); }

.search-box {
  background: var(--surface); border: 1px solid var(--border); border-radius: 8px;
  padding: 7px 13px; color: var(--text); font-family: var(--font-b); font-size: 13px;
  outline: none; transition: border-color .2s; width: 200px;
}
.search-box:focus { border-color: var(--gold); }
.search-box::placeholder { color: var(--muted); }

.filter-panel {
  background: var(--surface); border: 1px solid var(--border);
  border-radius: var(--r); padding: 18px 20px; margin-bottom: 16px;
}
.filter-panel-top {
  display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end;
}
.fg { display: flex; flex-direction: column; gap: 5px; }
.fg label { font-size: 10px; text-transform: uppercase; letter-spacing: 1.5px; color: var(--muted); font-weight: 600; }
.fg input[type=date], .fg input[type=number], .fg input[type=text], .fg select {
  background: var(--surface2); border: 1px solid var(--border); border-radius: 8px;
  color: var(--text); font-family: var(--font-b); font-size: 13px;
  padding: 7px 11px; outline: none; transition: border-color .2s; min-width: 140px;
}
.fg input:focus, .fg select:focus { border-color: var(--gold); }
.fg input::placeholder { color: var(--muted); }
.filter-divider { width: 100%; height: 1px; background: var(--border); margin: 14px 0 10px; }
.pill-row { display: flex; gap: 6px; flex-wrap: wrap; align-items: center; }
.pill-label { font-size: 10px; text-transform: uppercase; letter-spacing: 1.5px; color: var(--muted); font-weight: 600; white-space: nowrap; }
.pill {
  padding: 5px 14px; border-radius: 20px; font-size: 12px; font-weight: 600;
  cursor: pointer; border: 1px solid var(--border); background: transparent;
  color: var(--muted); font-family: var(--font-b); transition: all .2s;
}
.pill.active, .pill:hover { background: var(--gold); color: #080e1a; border-color: var(--gold); }
.filter-result {
  margin-top: 10px; font-size: 12px; color: var(--muted);
  display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
}
.chip {
  display: inline-flex; align-items: center; gap: 4px;
  background: rgba(212,175,55,.1); border: 1px solid rgba(212,175,55,.25);
  color: var(--gold); padding: 2px 9px; border-radius: 20px; font-size: 11px; font-weight: 600;
}

.modal-overlay {
  display: none; position: fixed; inset: 0;
  background: rgba(0,0,0,.72); z-index: 999;
  align-items: center; justify-content: center;
  backdrop-filter: blur(5px);
}
.modal-overlay.open { display: flex; }
.modal {
  background: var(--surface); border: 1px solid var(--border);
  border-radius: 16px; width: 100%; max-width: 500px;
  max-height: 90vh; overflow-y: auto;
  animation: mIn .25s ease;
}
@keyframes mIn { from { opacity:0; transform: translateY(16px) scale(.97); } to { opacity:1; transform: none; } }
.modal-head {
  padding: 22px 26px 16px; border-bottom: 1px solid var(--border);
  display: flex; align-items: center; justify-content: space-between;
}
.modal-head h3 { font-family: var(--font-h); font-size: 17px; }
.modal-body { padding: 22px 26px; display: grid; gap: 14px; }
.modal-foot {
  padding: 16px 26px; border-top: 1px solid var(--border);
  display: flex; gap: 8px; justify-content: flex-end;
}
.close-x { background: none; border: none; color: var(--muted); cursor: pointer; font-size: 20px; }
.close-x:hover { color: var(--text); }

label { font-size: 11px; color: var(--muted); display: block; margin-bottom: 4px; letter-spacing: .5px; text-transform: uppercase; }
input[type=text], input[type=date], input[type=number], select, textarea {
  background: var(--surface2); border: 1px solid var(--border);
  border-radius: 8px; color: var(--text); font-family: var(--font-b);
  font-size: 13px; padding: 8px 11px; outline: none; width: 100%;
  transition: border-color .2s;
}
input:focus, select:focus, textarea:focus { border-color: var(--gold); }
option { background: var(--bg2); }
textarea { resize: vertical; min-height: 65px; }
.row2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }

#confirm-overlay {
  display: none; position: fixed; inset: 0;
  background: rgba(0,0,0,.75); z-index: 1100;
  align-items: center; justify-content: center;
  backdrop-filter: blur(5px);
}
#confirm-overlay.open { display: flex; }
#confirm-box {
  background: var(--surface); border: 1px solid var(--danger);
  border-radius: 14px; padding: 30px 28px; max-width: 360px; text-align: center;
  animation: mIn .2s ease;
}
#confirm-box h4 { font-family: var(--font-h); font-size: 19px; margin-bottom: 8px; }
#confirm-box p { color: var(--muted); font-size: 13.5px; margin-bottom: 22px; line-height: 1.6; }
#confirm-box .cbtn { display: flex; gap: 10px; justify-content: center; }

#toast {
  position: fixed; bottom: 28px; right: 28px;
  background: var(--surface2); border: 1px solid var(--border);
  border-radius: 10px; padding: 13px 18px;
  font-size: 13.5px; color: var(--text);
  display: flex; align-items: center; gap: 9px;
  box-shadow: 0 8px 30px rgba(0,0,0,.4);
  transform: translateY(16px); opacity: 0;
  transition: all .3s; z-index: 9999; pointer-events: none;
}
#toast.show { transform: translateY(0); opacity: 1; }
#toast.success { border-color: var(--success); }
#toast.error   { border-color: var(--danger); }

.empty { text-align: center; padding: 50px 20px; color: var(--muted); }
.empty i { font-size: 40px; margin-bottom: 10px; display: block; opacity: .35; }

@media (max-width: 1100px) { .stats-grid { grid-template-columns: repeat(2, 1fr); } }
</style>
</head>
<body>

<aside class="sidebar">
  <div class="logo">
    <span class="crown">👑</span>
    <h2>Grand Lumière</h2>
    <small>Admin Panel</small>
  </div>
  <nav class="nav">
    <a href="#" class="active" onclick="showSection('dashboard', this, event)">
      <i class="ti ti-layout-dashboard"></i> Dashboard
    </a>
    <a href="#" onclick="showSection('bookings', this, event)">
      <i class="ti ti-calendar-event"></i> Semua Pemesanan
    </a>
    <a href="management.php">
      <i class="ti ti-building"></i> Manajemen Kamar
    </a>
    <a href="#" onclick="showSection('users', this, event)">
      <i class="ti ti-users"></i> Kelola User
    </a>
  </nav>
  <div class="sidebar-foot">
    <strong><?= htmlspecialchars($_SESSION['username'] ?? 'Admin') ?></strong>
    <span>Administrator</span>
    <br>
    <a href="../logout.php" class="logout-btn"><i class="ti ti-logout"></i> Logout</a>
  </div>
</aside>

<div class="main">
  <header class="topbar">
    <h1 id="page-title">Dashboard</h1>
    <div style="display: flex; gap: 10px; align-items: center;">
      <span class="badge-admin"><i class="ti ti-shield-check"></i> Admin</span>
      <button class="btn btn-ghost btn-sm" onclick="location.reload()" title="Refresh Data">
        <i class="ti ti-refresh"></i> Refresh
      </button>
    </div>
  </header>

  <div class="content">

    <!-- DASHBOARD SECTION -->
    <div id="dashboardSection" class="section active">
      <div class="stats-grid">
        <div class="stat-card" style="--c:var(--gold)">
          <div class="st-label">Total Pemesanan</div>
          <div class="st-val"><?= $total_orders ?></div>
          <i class="ti ti-clipboard-list st-icon"></i>
        </div>
        <div class="stat-card" style="--c:var(--warn)">
          <div class="st-label">Menunggu</div>
          <div class="st-val"><?= $pending_count ?></div>
          <i class="ti ti-clock st-icon"></i>
        </div>
        <div class="stat-card" style="--c:var(--info)">
          <div class="st-label">Sedang Check In</div>
          <div class="st-val"><?= $checkin_count ?></div>
          <i class="ti ti-door-enter st-icon"></i>
        </div>
        <div class="stat-card" style="--c:var(--danger)">
          <div class="st-label">Dibatalkan</div>
          <div class="st-val"><?= $cancelled_count ?></div>
          <i class="ti ti-circle-x st-icon"></i>
        </div>
        <div class="stat-card" style="--c:var(--info)">
          <div class="st-label">Total Revenue</div>
          <div class="st-val">Rp<?= number_format($total_revenue, 0, ',', '.') ?></div>
          <i class="ti ti-coin st-icon"></i>
        </div>
      </div>

      <div class="sec-head">
        <h2 class="sec-title">Pemesanan Terbaru</h2>
        <button class="btn btn-ghost btn-sm" onclick="showSection('bookings', document.querySelector('.nav a:nth-child(2)'))">
          Lihat Semua <i class="ti ti-arrow-right"></i>
        </button>
      </div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr><th>Kode</th><th>Nama Tamu</th><th>Kamar</th><th>Check-in</th><th>Total</th><th>Status</th><th>Aksi</th></tr>
          </thead>
          <tbody>
          <?php if (empty($orders)): ?>
            <tr><td colspan="7"><div class="empty"><i class="ti ti-inbox"></i>Belum ada pemesanan</div></td></tr>
          <?php else: foreach (array_slice($orders, 0, 6) as $o): ?>
            <tr>
              <td style="font-family:monospace;font-size:12px;color:var(--muted)"><?= htmlspecialchars($o['kode_booking'] ?? '#'.$o['id']) ?></td>
              <td><div style="font-weight:500"><?= htmlspecialchars($o['nama_tamu'] ?? $o['nama_user'] ?? '—') ?></div>
              <div style="font-size:11px;color:var(--muted)"><?= htmlspecialchars($o['email_tamu'] ?? $o['email'] ?? '') ?></div></td>
              <td><?= htmlspecialchars($o['tipe_kamar'] ?? '—') ?></td>
              <td style="font-size:12px"><?= $o['check_in'] ?? '—' ?></td>
              <td style="color:var(--gold)">Rp<?= number_format($o['total'] ?? 0, 0, ',', '.') ?></td>
              <td>
                <?php
                  $statusLabel = [
                    'pending'     => '⏳ Pending',
                    'checked_in'  => '🏨 Check In',
                    'checked_out' => '👋 Check Out',
                    'cancelled'   => '❌ Dibatalkan',
                  ];
                ?>
                <span class="badge badge-<?= htmlspecialchars($o['status']) ?>" id="badge-<?= $o['id'] ?>">
                  <?= $statusLabel[$o['status']] ?? ucfirst(str_replace('_', ' ', $o['status'])) ?>
                </span>
              </td>
              <td><div class="act-cell">
                  <button class="btn btn-info btn-sm" onclick='openEditModal(<?= json_encode($o) ?>)'><i class="ti ti-edit"></i></button>
                  <?php if ($o['status'] === 'pending'): ?>
                    <button class="btn btn-warn btn-sm" onclick='quickStatus(<?= $o["id"] ?>, "checked_in")' title="Check In"><i class="ti ti-door-enter"></i> Check In</button>
                  <?php elseif ($o['status'] === 'checked_in'): ?>
                    <?php if (empty($o['nomor_kamar'])): ?>
                      <a href="management.php" class="btn btn-gold btn-sm" title="Assign nomor kamar"><i class="ti ti-key"></i> Assign Kamar</a>
                    <?php else: ?>
                      <span class="badge badge-checked_in" style="font-size:11px"><i class="ti ti-door-enter"></i> <?= htmlspecialchars($o['nomor_kamar']) ?></span>
                    <?php endif; ?>
                    <button class="btn btn-success btn-sm" onclick='quickStatus(<?= $o["id"] ?>, "checked_out")' title="Check Out"><i class="ti ti-door-exit"></i> Check Out</button>
                  <?php elseif ($o['status'] === 'checked_out'): ?>
                    <span class="badge badge-checked_out"><i class="ti ti-circle-check"></i> Selesai</span>
                  <?php elseif ($o['status'] === 'cancelled'): ?>
                    <span class="badge badge-cancelled"><i class="ti ti-circle-x"></i> Dibatalkan</span>
                  <?php endif; ?>
                  <button class="btn btn-danger btn-sm" onclick='confirmDelete(<?= $o["id"] ?>, "<?= addslashes($o["nama_tamu"] ?? $o["nama_user"] ?? "") ?>")'><i class="ti ti-trash"></i></button>
                </div></td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- BOOKINGS SECTION -->
    <div id="bookingsSection" class="section">
      <div class="filter-panel">
        <div class="filter-panel-top">
          <div class="fg">
            <label><i class="ti ti-search"></i> Cari</label>
            <input type="text" id="fSearch" placeholder="Nama, email, kode…" oninput="applyFilters()" style="min-width:180px">
          </div>
          <div class="fg">
            <label><i class="ti ti-calendar"></i> Check-in</label>
            <input type="date" id="fCheckIn" onchange="applyFilters()">
          </div>
          <div class="fg">
            <label><i class="ti ti-calendar"></i> Check-out</label>
            <input type="date" id="fCheckOut" onchange="applyFilters()">
          </div>
          <div class="fg">
            <label><i class="ti ti-coin"></i> Harga Min (Rp)</label>
            <input type="number" id="fPriceMin" placeholder="0" min="0" step="50000" oninput="applyFilters()">
          </div>
          <div class="fg">
            <label><i class="ti ti-coin"></i> Harga Max (Rp)</label>
            <input type="number" id="fPriceMax" placeholder="∞" min="0" step="50000" oninput="applyFilters()">
          </div>
          <div class="fg" style="flex-direction:row;gap:8px;padding-top:21px">
            <button class="btn btn-ghost btn-sm" onclick="resetFilters()">
              <i class="ti ti-x"></i> Reset
            </button>
            <button class="btn btn-success btn-sm" onclick="exportCSV()" title="Export data yang terfilter ke CSV">
              <i class="ti ti-download"></i> Export CSV
            </button>
          </div>
        </div>

        <div class="filter-divider"></div>
        <div class="pill-row">
          <span class="pill-label">Status:</span>
          <button class="pill active" onclick="setPill('',this)">Semua</button>
          <button class="pill" onclick="setPill('pending',this)"> Pending</button>
          <button class="pill" onclick="setPill('checked_in',this)"> Check-in</button>
          <button class="pill" onclick="setPill('checked_out',this)"> Check-out</button>
          <button class="pill" onclick="setPill('cancelled',this)"> Dibatalkan</button>
        </div>

        <div class="filter-result">
          <span id="fCount" style="color:var(--muted)"></span>
          <span id="fChips"></span>
        </div>
      </div>

      <div class="table-wrap">
        <table id="ordersTable">
          <thead>
            <tr><th>#</th><th>Kode</th><th>Nama Tamu</th><th>Email</th><th>Kamar</th><th>Check-in</th><th>Check-out</th><th>Malam</th><th>Total</th><th>Status</th><th>Aksi</th></tr>
          </thead>
          <tbody>
          <?php foreach ($orders as $o): ?>
            <tr
              data-status="<?= $o['status'] ?>"
              data-search="<?= strtolower(($o['nama_tamu']??'').' '.($o['email_tamu']??'').' '.($o['tipe_kamar']??'').' '.($o['kode_booking']??'')) ?>"
              data-checkin="<?= $o['check_in'] ?? '' ?>"
              data-checkout="<?= $o['check_out'] ?? '' ?>"
              data-total="<?= floatval($o['total'] ?? 0) ?>"
            >
              <td style="color:var(--muted);font-size:11px"><?= $o['id'] ?></td>
              <td style="font-family:monospace;font-size:12px;color:var(--muted)"><?= htmlspecialchars($o['kode_booking'] ?? '—') ?></td>
              <td style="font-weight:500"><?= htmlspecialchars($o['nama_tamu'] ?? $o['nama_user'] ?? '—') ?></td>
              <td style="color:var(--muted);font-size:12px"><?= htmlspecialchars($o['email_tamu'] ?? $o['email'] ?? '—') ?></td>
              <td><?= htmlspecialchars($o['tipe_kamar'] ?? '—') ?></td>
              <td style="font-size:12px"><?= $o['check_in'] ?? '—' ?></td>
              <td style="font-size:12px"><?= $o['check_out'] ?? '—' ?></td>
              <td style="text-align:center"><?= $o['jumlah_malam'] ?? '—' ?></td>
              <td style="color:var(--gold)">Rp<?= number_format($o['total'] ?? 0, 0, ',', '.') ?></td>
              <td>
                <?php
                  $statusLabel = [
                    'pending'     => ' Pending',
                    'checked_in'  => ' Check In',
                    'checked_out' => ' Check Out',
                    'cancelled'   => ' Dibatalkan',
                  ];
                ?>
                <span class="badge badge-<?= htmlspecialchars($o['status']) ?>" id="badge-<?= $o['id'] ?>">
                  <?= $statusLabel[$o['status']] ?? ucfirst(str_replace('_', ' ', $o['status'])) ?>
                </span>
              </td>
              <td><div class="act-cell">
                  <button class="btn btn-info btn-sm" onclick='openEditModal(<?= json_encode($o) ?>)'><i class="ti ti-edit"></i></button>
                  <?php if ($o['status'] === 'pending'): ?>
                    <button class="btn btn-warn btn-sm" onclick='quickStatus(<?= $o["id"] ?>, "checked_in")' title="Check In"><i class="ti ti-door-enter"></i> Check In</button>
                  <?php elseif ($o['status'] === 'checked_in'): ?>
                    <?php if (empty($o['nomor_kamar'])): ?>
                      <a href="management.php" class="btn btn-gold btn-sm" title="Assign nomor kamar"><i class="ti ti-key"></i> Assign Kamar</a>
                    <?php else: ?>
                      <span class="badge badge-checked_in" style="font-size:11px"><i class="ti ti-door-enter"></i> <?= htmlspecialchars($o['nomor_kamar']) ?></span>
                    <?php endif; ?>
                    <button class="btn btn-success btn-sm" onclick='quickStatus(<?= $o["id"] ?>, "checked_out")' title="Check Out"><i class="ti ti-door-exit"></i> Check Out</button>
                  <?php elseif ($o['status'] === 'checked_out'): ?>
                    <span class="badge badge-checked_out"><i class="ti ti-circle-check"></i> Selesai</span>
                  <?php elseif ($o['status'] === 'cancelled'): ?>
                    <span class="badge badge-cancelled"><i class="ti ti-circle-x"></i> Dibatalkan</span>
                  <?php endif; ?>
                  <button class="btn btn-danger btn-sm" onclick='confirmDelete(<?= $o["id"] ?>, "<?= addslashes($o["nama_tamu"] ?? $o["nama_user"] ?? "") ?>")'><i class="ti ti-trash"></i></button>
                </div></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- USERS SECTION -->
    <div id="usersSection" class="section">
      <div class="filter-bar">
        <input class="search-box" type="text" placeholder="🔍 Cari user…" oninput="searchUsers(this.value)" style="margin-left:0">
      </div>
      <div class="table-wrap">
        <table id="usersTable">
          <thead>
            <tr><th>#</th><th>Username</th><th>Email</th><th>Role</th><th>Bergabung</th><th>Aksi</th></tr>
          </thead>
          <tbody>
          <?php foreach ($users as $u): ?>
            <tr data-search="<?= strtolower($u['username'].' '.$u['email']) ?>">
              <td style="color:var(--muted);font-size:11px"><?= $u['id'] ?></td>
              <td style="font-weight:500"><?= htmlspecialchars($u['username']) ?></td>
              <td style="color:var(--muted);font-size:12px"><?= htmlspecialchars($u['email']) ?></td>
              <td><span class="badge <?= $u['role']==='admin' ? 'badge-checked_in' : 'badge-checked_out' ?>"><?= $u['role'] ?></span></td>
              <td style="font-size:12px;color:var(--muted)"><?= date('d M Y', strtotime($u['created_at'])) ?></td>
              <td><?php if ($u['id'] != ($_SESSION['user_id'] ?? 0)): ?>
                  <button class="btn btn-info btn-sm" onclick="changeRole(<?= $u['id'] ?>, 'admin')">Jadi Admin</button>
                  <button class="btn btn-ghost btn-sm" onclick="changeRole(<?= $u['id'] ?>, 'user')">Jadi User</button>
                <?php else: ?>
                  <span style="font-size:12px;color:var(--muted)">(Kamu)</span>
                <?php endif; ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div>
</div>

<!-- MODAL EDIT RESERVASI -->
<div class="modal-overlay" id="edit-modal">
  <div class="modal">
    <div class="modal-head">
      <h3><i class="ti ti-edit"></i> Edit Reservasi</h3>
      <button class="close-x" onclick="closeModal('edit-modal')">×</button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="edit-id">
      <div><label>Nama Tamu</label><input type="text" id="edit-nama" readonly style="opacity:.6"></div>
      <div><label>Tipe Kamar</label><input type="text" id="edit-tipe" placeholder="mis: Deluxe, Suite…"></div>
      <div class="row2">
        <div><label>Check-in</label><input type="date" id="edit-checkin"></div>
        <div><label>Check-out</label><input type="date" id="edit-checkout"></div>
      </div>
      <div class="row2">
        <div><label>Jumlah Malam</label><input type="number" id="edit-malam" min="1" placeholder="Malam"></div>
        <div><label>Total (Rp)</label><input type="number" id="edit-total" min="0" step="1" placeholder="0"></div>
      </div>
    </div>
    <div class="modal-foot">
      <button class="btn btn-ghost" onclick="closeModal('edit-modal')">Batal</button>
      <button class="btn btn-gold" onclick="saveReservation()"><i class="ti ti-device-floppy"></i> Simpan</button>
    </div>
  </div>
</div>

<!-- TOAST -->
<div id="toast"><span id="toast-icon">✅</span><span id="toast-msg">Berhasil</span></div>

<script>

function showSection(name, el, e) {
  if (e) e.preventDefault();
  ['dashboard','bookings','users'].forEach(s => document.getElementById(s+'Section').classList.remove('active'));
  document.getElementById(name+'Section').classList.add('active');
  document.querySelectorAll('.nav a').forEach(a => a.classList.remove('active'));
  if (el) el.classList.add('active');
  const titles = { dashboard:'Dashboard', bookings:'Semua Pemesanan', users:'Kelola User' };
  document.getElementById('page-title').textContent = titles[name] || '';
}

function showToast(msg, type='success') {
  const t = document.getElementById('toast');
  document.getElementById('toast-msg').textContent = msg;
  document.getElementById('toast-icon').textContent = type === 'success' ? '✅' : '❌';
  t.className = 'show ' + type;
  setTimeout(() => t.className = '', 3000);
}

function openModal(id) { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }

document.querySelectorAll('.modal-overlay').forEach(el => {
  el.addEventListener('click', e => { if (e.target === el) el.classList.remove('open'); });
});

// Buka modal edit dan isi datanya
function openEditModal(o) {
  document.getElementById('edit-id').value       = o.id;
  document.getElementById('edit-nama').value     = o.nama_tamu || o.nama_user || '';
  document.getElementById('edit-tipe').value     = o.tipe_kamar || '';
  document.getElementById('edit-checkin').value  = o.check_in || '';
  document.getElementById('edit-checkout').value = o.check_out || '';
  document.getElementById('edit-malam').value    = o.jumlah_malam || '';
  document.getElementById('edit-total').value    = o.total || 0;
  openModal('edit-modal');
}

async function quickStatus(id, status) {
    let message = '';
    if (status === 'checked_in')  message = 'Proses Check In pesanan ini?';
    else if (status === 'checked_out') message = 'Proses Check Out pesanan ini?';
    else message = 'Ubah status pesanan ini?';

    if (!confirm(message)) return;

    const res = await post({ ajax: 1, action: 'update_status', id, status });
    
    if (res.success) {
        showToast('Status berhasil diubah', 'success');
        setTimeout(() => location.reload(), 1000);
    } else {
        showToast('Gagal mengubah status', 'error');
    }
}

async function saveReservation() {
  const id       = document.getElementById('edit-id').value;
  const check_in = document.getElementById('edit-checkin').value;
  const check_out= document.getElementById('edit-checkout').value;
  const malam    = document.getElementById('edit-malam').value;
  const tipe     = document.getElementById('edit-tipe').value;
  const total    = document.getElementById('edit-total').value;

  if (!check_in || !check_out) { showToast('Tanggal tidak boleh kosong!','error'); return; }
  if (check_out <= check_in)   { showToast('Check-out harus setelah check-in!','error'); return; }

  const res = await post({
    ajax: 1,
    action: 'update_reservation',
    id,
    check_in,
    check_out,
    jumlah_malam: malam,
    tipe_kamar: tipe,
    total
  });

  if (res.success) {
    closeModal('edit-modal');
    showToast('Reservasi berhasil diperbarui');
    setTimeout(() => location.reload(), 800);
  } else {
    showToast('Gagal memperbarui reservasi','error');
  }
}

function confirmDelete(id, name) {
  if(confirm(`Hapus pesanan dari "${name}" (#${id})?`)) {
    post({ ajax:1, action:'delete_order', id }).then(res => {
      if(res.success) location.reload();
      else showToast('Gagal menghapus','error');
    });
  }
}

async function changeRole(id, role) {
  if (!confirm(`Ubah role user #${id} menjadi "${role}"?`)) return;
  const res = await post({ ajax: 1, action: 'update_role', id, role });
  if (res.success) {
    showToast('Role berhasil diubah', 'success');
    setTimeout(() => location.reload(), 1000);
  } else {
    showToast('Gagal mengubah role', 'error');
  }
}

async function post(data) {
  const fd = new FormData();
  Object.entries(data).forEach(([k,v]) => fd.append(k, v));
  try {
    const r = await fetch(location.href, { method:'POST', body:fd });
    return await r.json();
  } catch(e) { 
    console.error('POST error:', e);
    return { success: false }; 
  }
}

// Filter
let fStatus = '';
function setPill(status, btn) {
  fStatus = status;
  document.querySelectorAll('.pill').forEach(p => p.classList.remove('active'));
  btn.classList.add('active');
  applyFilters();
}

function applyFilters() {
  const search   = (document.getElementById('fSearch').value || '').toLowerCase();
  const checkIn  = document.getElementById('fCheckIn').value;
  const checkOut = document.getElementById('fCheckOut').value;
  const pMin     = document.getElementById('fPriceMin').value;
  const pMax     = document.getElementById('fPriceMax').value;

  let visible = 0;

  document.querySelectorAll('#ordersTable tbody tr[data-status]').forEach(tr => {
    const s     = tr.dataset.status;
    const q     = tr.dataset.search || '';
    const ci    = tr.dataset.checkin  || '';
    const co    = tr.dataset.checkout || '';
    const total = parseFloat(tr.dataset.total || 0);

    const statusList = fStatus ? fStatus.split('|') : [];
    const okStatus  = statusList.length === 0 || statusList.includes(s);
    const okSearch  = !search   || q.includes(search);
    const okCI      = !checkIn  || ci === checkIn;
    const okCO      = !checkOut || co === checkOut;
    const okPMin    = pMin === '' || total >= parseFloat(pMin);
    const okPMax    = pMax === '' || total <= parseFloat(pMax);

    const show = okStatus && okSearch && okCI && okCO && okPMin && okPMax;
    tr.style.display = show ? '' : 'none';
    if (show) visible++;
  });

  const total = document.querySelectorAll('#ordersTable tbody tr[data-status]').length;
  document.getElementById('fCount').textContent =
    visible === total ? `Menampilkan semua ${total} pesanan`
                      : `Menampilkan ${visible} dari ${total} pesanan`;
}

function resetFilters() {
  document.getElementById('fSearch').value   = '';
  document.getElementById('fCheckIn').value  = '';
  document.getElementById('fCheckOut').value = '';
  document.getElementById('fPriceMin').value = '';
  document.getElementById('fPriceMax').value = '';
  fStatus = '';
  document.querySelectorAll('.pill').forEach(p => p.classList.remove('active'));
  document.querySelector('.pill').classList.add('active');
  applyFilters();
}

function exportCSV() {
  const sf      = fStatus;
  const ci      = document.getElementById('fCheckIn').value;
  const co      = document.getElementById('fCheckOut').value;
  const pmin    = document.getElementById('fPriceMin').value;
  const pmax    = document.getElementById('fPriceMax').value;
  const search  = document.getElementById('fSearch').value;

  const params = new URLSearchParams({ 
    export: 'csv', 
    sf, ci, co, pmin, pmax, search 
  });
  window.location.href = '?' + params.toString();
}

function searchUsers(val) {
  const q = val.toLowerCase();
  document.querySelectorAll('#usersTable tbody tr[data-search]').forEach(tr => {
    tr.style.display = (tr.dataset.search||'').includes(q) ? '' : 'none';
  });
}

// Session monitoring untuk mencegah logout tak terduga
let sessionCheckInterval = setInterval(async () => {
  try {
    const response = await fetch('../check_session.php');
    const data = await response.json();
    if (!data.valid) {
      clearInterval(sessionCheckInterval);
      alert('Sesi Anda telah berakhir. Silakan login kembali.');
      window.location.href = '../index.html';
    }
  } catch(e) {
    console.error('Session check error:', e);
  }
}, 300000); // Cek setiap 5 menit

// Jalankan filter awal
applyFilters();
</script>
</body>
</html>
