<?php
ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_samesite', 'Lax');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../config.php';
opcache_reset();

/* ── CEK SESSION ADMIN ── */
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../index.html');
    exit;
}

/* ══════════════════════════════════════════
   AJAX HANDLER
══════════════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';

    /* ── ASSIGN NOMOR KAMAR ── */
    if ($action === 'assign_room') {
        $booking_id  = intval($_POST['booking_id']  ?? 0);
        $kamar_id    = intval($_POST['kamar_id']    ?? 0); // 0 = input manual
        $nomor_kamar = trim($_POST['nomor_kamar']   ?? '');

        // Validasi dasar: booking_id dan nomor_kamar wajib; kamar_id boleh 0 (manual)
        if ($booking_id <= 0 || !$nomor_kamar) {
            echo json_encode(['success' => false, 'message' => 'Booking ID atau nomor kamar tidak valid']);
            exit;
        }

        // Cek pesanan ada & berstatus checked_in
        $cek_pesan = $conn->prepare("SELECT id, tipe_kamar, status FROM pemesanan WHERE id = ? LIMIT 1");
        if (!$cek_pesan) {
            echo json_encode(['success' => false, 'message' => 'DB error: ' . $conn->error]);
            exit;
        }
        $cek_pesan->bind_param('i', $booking_id);
        $cek_pesan->execute();
        $pesan = $cek_pesan->get_result()->fetch_assoc();
        $cek_pesan->close();

        if (!$pesan) {
            echo json_encode(['success' => false, 'message' => 'Pesanan tidak ditemukan']);
            exit;
        }
        if ($pesan['status'] !== 'checked_in') {
            echo json_encode(['success' => false, 'message' => 'Pesanan harus berstatus checked_in (saat ini: ' . $pesan['status'] . ')']);
            exit;
        }

        // Jika kamar dipilih dari daftar (bukan manual), validasi ketersediaan & tipe
        if ($kamar_id > 0) {
            $chk = $conn->prepare("SELECT id, status, tipe_kamar FROM kamar WHERE id = ? AND is_active = 1 LIMIT 1");
            if (!$chk) {
                echo json_encode(['success' => false, 'message' => 'DB error: ' . $conn->error]);
                exit;
            }
            $chk->bind_param('i', $kamar_id);
            $chk->execute();
            $kamar = $chk->get_result()->fetch_assoc();
            $chk->close();

            if (!$kamar) {
                echo json_encode(['success' => false, 'message' => 'Kamar tidak ditemukan di database']);
                exit;
            }
            if ($kamar['status'] !== 'available') {
                echo json_encode(['success' => false, 'message' => 'Kamar sudah tidak tersedia (status: ' . $kamar['status'] . ')']);
                exit;
            }
            if ($kamar['tipe_kamar'] !== $pesan['tipe_kamar']) {
                echo json_encode(['success' => false, 'message' => 'Tipe kamar tidak cocok. Pesanan: ' . $pesan['tipe_kamar'] . ', Kamar: ' . $kamar['tipe_kamar']]);
                exit;
            }
        }

        // Transaksi: update pemesanan + tandai kamar occupied (jika kamar_id ada)
        $conn->begin_transaction();
        try {
            if ($kamar_id > 0) {
                $u1 = $conn->prepare("UPDATE pemesanan SET kamar_id = ?, nomor_kamar = ? WHERE id = ?");
                if (!$u1) throw new Exception('Prepare gagal: ' . $conn->error);
                $u1->bind_param('isi', $kamar_id, $nomor_kamar, $booking_id);
            } else {
                $u1 = $conn->prepare("UPDATE pemesanan SET kamar_id = NULL, nomor_kamar = ? WHERE id = ?");
                if (!$u1) throw new Exception('Prepare gagal: ' . $conn->error);
                $u1->bind_param('si', $nomor_kamar, $booking_id);
            }
            if (!$u1->execute()) throw new Exception('Execute gagal: ' . $u1->error);
            $u1->close();

            if ($kamar_id > 0) {
                $u2 = $conn->prepare("UPDATE kamar SET status = 'occupied' WHERE id = ?");
                if (!$u2) throw new Exception('Prepare kamar gagal: ' . $conn->error);
                $u2->bind_param('i', $kamar_id);
                if (!$u2->execute()) throw new Exception('Execute kamar gagal: ' . $u2->error);
                $u2->close();
            }

            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Kamar ' . $nomor_kamar . ' berhasil ditetapkan']);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'Transaksi gagal: ' . $e->getMessage()]);
        }
        exit;
    }

    /* ── LEPAS NOMOR KAMAR (reset saat checkout) ── */
    if ($action === 'release_room') {
        $booking_id = intval($_POST['booking_id'] ?? 0);

        if ($booking_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID booking tidak valid']);
            exit;
        }

        // Ambil kamar_id DAN nomor_kamar untuk fallback jika kamar_id NULL (input manual)
        $cek = $conn->prepare("SELECT kamar_id, nomor_kamar, status FROM pemesanan WHERE id = ? LIMIT 1");
        $cek->bind_param('i', $booking_id);
        $cek->execute();
        $row = $cek->get_result()->fetch_assoc();
        $cek->close();

        if (!$row) {
            echo json_encode(['success' => false, 'message' => 'Pesanan tidak ditemukan']);
            exit;
        }

        // Tidak ada kamar sama sekali (kamar_id NULL dan nomor_kamar kosong)
        if (!$row['kamar_id'] && !$row['nomor_kamar']) {
            echo json_encode(['success' => false, 'message' => 'Tidak ada kamar yang di-assign pada pesanan ini']);
            exit;
        }

        $conn->begin_transaction();
        try {
            $u1 = $conn->prepare("UPDATE pemesanan SET kamar_id = NULL, nomor_kamar = NULL, status = 'checked_out' WHERE id = ?");
            $u1->bind_param('i', $booking_id);
            $u1->execute();
            $u1->close();

            // Bebaskan kamar: utamakan kamar_id, fallback ke nomor_kamar (untuk assign manual)
            if (!empty($row['kamar_id'])) {
                $u2 = $conn->prepare("UPDATE kamar SET status = 'available' WHERE id = ?");
                $u2->bind_param('i', $row['kamar_id']);
                $u2->execute();
                $u2->close();
            } elseif (!empty($row['nomor_kamar'])) {
                $u2 = $conn->prepare("UPDATE kamar SET status = 'available' WHERE nomor_kamar = ?");
                $u2->bind_param('s', $row['nomor_kamar']);
                $u2->execute();
                $u2->close();
            }

            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Kamar dibebaskan & pesanan di-checkout']);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'Gagal: ' . $e->getMessage()]);
        }
        exit;
    }

    /* ── AMBIL KAMAR TERSEDIA BERDASAR TIPE ── */
    if ($action === 'get_available_rooms') {
        $tipe = trim($_POST['tipe_kamar'] ?? '');
        if (!$tipe) {
            echo json_encode(['success' => false, 'message' => 'Tipe kamar wajib diisi']);
            exit;
        }

        $stmt = $conn->prepare("
            SELECT id, nomor_kamar, lantai, kapasitas, harga_per_malam
            FROM kamar
            WHERE tipe_kamar = ? AND status = 'available' AND is_active = 1
            ORDER BY lantai, nomor_kamar
        ");
        $stmt->bind_param('s', $tipe);
        $stmt->execute();
        $kamar_list = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        echo json_encode(['success' => true, 'rooms' => $kamar_list]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Action tidak dikenal']);
    exit;
}

/* ══════════════════════════════════════════
   AMBIL DATA PESANAN STATUS checked_in
══════════════════════════════════════════ */
$checked_in_orders = $conn->query("
    SELECT 
        p.*,
        u.username AS nama_user,
        u.email AS email_user
    FROM pemesanan p
    LEFT JOIN users u ON p.user_id = u.id
    WHERE p.status = 'checked_in'
    ORDER BY p.check_in ASC, p.created_at DESC
")->fetch_all(MYSQLI_ASSOC);

/* ── SEMUA KAMAR AKTIF (untuk ringkasan status) ── */
$semua_kamar = $conn->query("
    SELECT id, nomor_kamar, tipe_kamar, lantai, status, kapasitas
    FROM kamar
    WHERE is_active = 1
    ORDER BY lantai, nomor_kamar
")->fetch_all(MYSQLI_ASSOC);

$kamar_available = count(array_filter($semua_kamar, fn($k) => $k['status'] === 'available'));
$kamar_occupied  = count(array_filter($semua_kamar, fn($k) => $k['status'] === 'occupied'));
$kamar_maint     = count(array_filter($semua_kamar, fn($k) => $k['status'] === 'maintenance'));
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manajemen Kamar — Grand Lumière</title>
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
  --gold-bg:  rgba(212,175,55,.09);
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
body { font-family: var(--font-b); background: var(--bg); color: var(--text); min-height: 100vh; display: flex; }

/* ── SIDEBAR ── */
.sidebar {
  width: 250px; position: fixed; top: 0; left: 0; bottom: 0;
  background: var(--bg2); border-right: 1px solid var(--border);
  display: flex; flex-direction: column; z-index: 100;
}
.logo { padding: 30px 24px 22px; border-bottom: 1px solid var(--border); }
.logo .crown { font-size: 22px; margin-bottom: 6px; display: block; }
.logo h2 { font-family: var(--font-h); font-size: 20px; color: var(--gold); letter-spacing: 1px; }
.logo small { font-size: 10px; letter-spacing: 3px; text-transform: uppercase; color: var(--muted); }
.nav { flex: 1; padding: 18px 0; }
.nav a {
  display: flex; align-items: center; gap: 11px;
  padding: 11px 22px; color: var(--muted); text-decoration: none;
  font-size: 14px; font-weight: 500;
  border-left: 3px solid transparent; transition: all .2s;
}
.nav a i { font-size: 17px; }
.nav a:hover { color: var(--text); background: rgba(212,175,55,.06); }
.nav a.active { color: var(--gold); border-left-color: var(--gold); background: var(--gold-bg); }
.sidebar-foot { padding: 18px 22px; border-top: 1px solid var(--border); font-size: 13px; }
.sidebar-foot strong { display: block; color: var(--text); margin-bottom: 2px; }
.sidebar-foot span { color: var(--muted); font-size: 11px; }
.logout-btn {
  display: inline-flex; align-items: center; gap: 6px;
  margin-top: 12px; padding: 7px 14px; border-radius: 8px;
  background: rgba(224,82,82,.12); border: 1px solid rgba(224,82,82,.25);
  color: var(--danger); font-size: 13px; font-family: var(--font-b);
  text-decoration: none; cursor: pointer; transition: all .2s;
}
.logout-btn:hover { background: var(--danger); color: #fff; }

/* ── MAIN ── */
.main { margin-left: 250px; flex: 1; display: flex; flex-direction: column; }
.topbar {
  background: var(--bg2); border-bottom: 1px solid var(--border);
  padding: 18px 32px; display: flex; align-items: center;
  justify-content: space-between; position: sticky; top: 0; z-index: 50;
}
.topbar h1 { font-family: var(--font-h); font-size: 20px; }
.badge-admin {
  background: rgba(212,175,55,.12); color: var(--gold);
  border: 1px solid var(--gold-dim); font-size: 11px;
  font-weight: 600; letter-spacing: 1.5px; text-transform: uppercase;
  padding: 5px 13px; border-radius: 20px;
}
.content { padding: 32px; flex: 1; }

/* ── STATS ── */
.stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 32px; }
.stat-card {
  background: var(--surface); border: 1px solid var(--border);
  border-radius: var(--r); padding: 20px; position: relative; overflow: hidden;
}
.stat-card::before {
  content: ''; position: absolute; top: 0; left: 0; right: 0; height: 2px;
  background: var(--c, var(--gold));
}
.stat-card .st-label { font-size: 10px; text-transform: uppercase; letter-spacing: 2px; color: var(--muted); margin-bottom: 8px; }
.stat-card .st-val { font-family: var(--font-h); font-size: 28px; color: var(--text); }
.stat-card .st-icon { position: absolute; right: 16px; top: 16px; font-size: 24px; opacity: .1; }

/* ── SECTION HEAD ── */
.sec-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 18px; }
.sec-title { font-family: var(--font-h); font-size: 18px; }

/* ── TABLE ── */
.table-wrap { background: var(--surface); border: 1px solid var(--border); border-radius: var(--r); overflow-x: auto; }
table { width: 100%; border-collapse: collapse; font-size: 13px; }
thead { background: var(--surface2); border-bottom: 1px solid var(--border); }
th { padding: 12px 14px; text-align: left; font-size: 10px; text-transform: uppercase; letter-spacing: 1.5px; color: var(--muted); font-weight: 600; white-space: nowrap; }
td { padding: 13px 14px; border-bottom: 1px solid var(--border); vertical-align: middle; }
tr:last-child td { border-bottom: none; }
tr:hover td { background: rgba(255,255,255,.015); }

/* ── BADGE ── */
.badge { display: inline-block; padding: 3px 9px; border-radius: 20px; font-size: 11px; font-weight: 600; }
.badge-available    { background: rgba(76,175,125,.15); color: var(--success); }
.badge-occupied     { background: rgba(91,156,246,.15); color: var(--info); }
.badge-maintenance  { background: rgba(240,168,67,.15); color: var(--warn); }
.badge-checked_in   { background: rgba(91,156,246,.15); color: var(--info); }
.badge-no-room      { background: rgba(240,168,67,.15); color: var(--warn); }

/* ── BTN ── */
.btn {
  display: inline-flex; align-items: center; gap: 5px;
  padding: 7px 13px; border-radius: 8px; font-size: 13px; font-weight: 500;
  font-family: var(--font-b); cursor: pointer; border: none; transition: all .2s;
}
.btn-gold   { background: var(--gold); color: #080e1a; }
.btn-gold:hover { background: #e2c450; }
.btn-ghost  { background: transparent; border: 1px solid var(--border); color: var(--muted); }
.btn-ghost:hover { border-color: var(--gold); color: var(--gold); }
.btn-success { background: rgba(76,175,125,.12); color: var(--success); border: 1px solid rgba(76,175,125,.3); }
.btn-success:hover { background: var(--success); color: #fff; }
.btn-danger { background: rgba(224,82,82,.12); color: var(--danger); border: 1px solid rgba(224,82,82,.3); }
.btn-danger:hover { background: var(--danger); color: #fff; }
.btn-info   { background: rgba(91,156,246,.12); color: var(--info); border: 1px solid rgba(91,156,246,.3); }
.btn-info:hover { background: var(--info); color: #fff; }
.btn-sm { padding: 5px 9px; font-size: 12px; }

/* ── MODAL ── */
.modal-overlay {
  display: none; position: fixed; inset: 0;
  background: rgba(0,0,0,.7); backdrop-filter: blur(4px);
  z-index: 500; align-items: center; justify-content: center;
}
.modal-overlay.open { display: flex; }
.modal {
  background: var(--surface); border: 1px solid var(--border);
  border-radius: var(--r); width: 520px; max-width: 95vw;
  max-height: 90vh; overflow-y: auto;
}
.modal-head {
  display: flex; align-items: center; justify-content: space-between;
  padding: 20px 24px; border-bottom: 1px solid var(--border);
}
.modal-head h3 { font-family: var(--font-h); font-size: 18px; }
.close-x { background: none; border: none; color: var(--muted); font-size: 22px; cursor: pointer; }
.close-x:hover { color: var(--text); }
.modal-body { padding: 24px; display: flex; flex-direction: column; gap: 16px; }
.modal-foot { padding: 16px 24px; border-top: 1px solid var(--border); display: flex; gap: 10px; justify-content: flex-end; }

/* ── FORM ELEMENTS ── */
.fg { display: flex; flex-direction: column; gap: 6px; }
.fg label { font-size: 11px; letter-spacing: 1px; text-transform: uppercase; color: var(--muted); }
.fg input, .fg select {
  background: var(--bg2); border: 1px solid var(--border);
  color: var(--text); padding: 9px 12px; border-radius: 8px;
  font-family: var(--font-b); font-size: 13px; outline: none; transition: border-color .2s;
}
.fg input:focus, .fg select:focus { border-color: var(--gold); }
.fg select option { background: var(--bg2); }
.fg .readonly-field { opacity: .6; cursor: not-allowed; }

/* ── KAMAR GRID (pilih kamar) ── */
.room-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap: 10px; margin-top: 4px; }
.room-card {
  background: var(--bg2); border: 2px solid var(--border);
  border-radius: 8px; padding: 12px 10px; cursor: pointer;
  transition: all .2s; text-align: center;
}
.room-card:hover { border-color: var(--gold); background: var(--gold-bg); }
.room-card.selected { border-color: var(--gold); background: var(--gold-bg); }
.room-card .rn { font-size: 18px; font-weight: 700; color: var(--gold); font-family: var(--font-h); }
.room-card .rl { font-size: 11px; color: var(--muted); margin-top: 2px; }
.room-empty { text-align: center; padding: 20px; color: var(--muted); font-size: 13px; }

/* ── INFO BOX ── */
.info-box {
  background: var(--bg2); border: 1px solid var(--border);
  border-radius: 8px; padding: 14px 16px;
  display: grid; grid-template-columns: 1fr 1fr; gap: 8px;
}
.info-row { display: flex; flex-direction: column; gap: 2px; }
.info-row .ik { font-size: 10px; text-transform: uppercase; letter-spacing: 1px; color: var(--muted); }
.info-row .iv { font-size: 13px; color: var(--text); font-weight: 500; }

/* ── EMPTY STATE ── */
.empty { text-align: center; padding: 48px 24px; color: var(--muted); }
.empty i { font-size: 48px; display: block; margin-bottom: 12px; opacity: .3; }
.empty p { font-size: 14px; }

/* ── TOAST ── */
#toast {
  position: fixed; bottom: 24px; right: 24px;
  background: var(--surface); border: 1px solid var(--border);
  border-radius: 10px; padding: 12px 20px;
  display: flex; align-items: center; gap: 10px;
  font-size: 13px; color: var(--text);
  transform: translateY(80px); opacity: 0;
  transition: all .3s; z-index: 9999; min-width: 240px;
}
#toast.show { transform: translateY(0); opacity: 1; }
#toast.success { border-color: var(--success); }
#toast.error   { border-color: var(--danger); }

/* ── LOADING SPINNER ── */
.spinner { display: inline-block; width: 16px; height: 16px; border: 2px solid var(--border); border-top-color: var(--gold); border-radius: 50%; animation: spin .6s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

/* ── TAB SECTION ── */
.tab-bar { display: flex; gap: 4px; margin-bottom: 24px; background: var(--surface); border: 1px solid var(--border); border-radius: 10px; padding: 4px; width: fit-content; }
.tab-btn { padding: 8px 20px; border-radius: 8px; border: none; background: none; color: var(--muted); font-family: var(--font-b); font-size: 13px; font-weight: 500; cursor: pointer; transition: all .2s; display: flex; align-items: center; gap: 6px; }
.tab-btn.active { background: var(--gold); color: #080e1a; }

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
    <a href="dashboard.php">
      <i class="ti ti-layout-dashboard"></i> Dashboard
    </a>
    <a href="dashboard.php?section=bookings">
      <i class="ti ti-calendar-event"></i> Semua Pemesanan
    </a>
    <a href="management.php" class="active">
      <i class="ti ti-building"></i> Manajemen Kamar
    </a>
    <a href="dashboard.php?section=users">
      <i class="ti ti-users"></i> Kelola User
    </a>
  </nav>
  <div class="sidebar-foot">
    <strong><?= htmlspecialchars($_SESSION['username'] ?? 'Admin') ?></strong>
    <span>Administrator</span><br>
    <a href="../logout.php" class="logout-btn"><i class="ti ti-logout"></i> Logout</a>
  </div>
</aside>

<div class="main">
  <header class="topbar">
    <h1><i class="ti ti-building" style="color:var(--gold);margin-right:8px"></i> Manajemen Kamar</h1>
    <div style="display:flex;gap:10px;align-items:center">
      <span class="badge-admin"><i class="ti ti-shield-check"></i> Admin</span>
      <button class="btn btn-ghost btn-sm" onclick="location.reload()">
        <i class="ti ti-refresh"></i> Refresh
      </button>
    </div>
  </header>

  <div class="content">

    <!-- STATISTIK KAMAR -->
    <div class="stats-grid">
      <div class="stat-card" style="--c:var(--gold)">
        <div class="st-label">Total Kamar Aktif</div>
        <div class="st-val"><?= count($semua_kamar) ?></div>
        <i class="ti ti-building st-icon"></i>
      </div>
      <div class="stat-card" style="--c:var(--success)">
        <div class="st-label">Tersedia</div>
        <div class="st-val"><?= $kamar_available ?></div>
        <i class="ti ti-circle-check st-icon"></i>
      </div>
      <div class="stat-card" style="--c:var(--info)">
        <div class="st-label">Ditempati</div>
        <div class="st-val"><?= $kamar_occupied ?></div>
        <i class="ti ti-door-enter st-icon"></i>
      </div>
      <div class="stat-card" style="--c:var(--warn)">
        <div class="st-label">Perlu Assign Kamar</div>
        <div class="st-val"><?= count(array_filter($checked_in_orders, fn($o) => !$o['nomor_kamar'])) ?></div>
        <i class="ti ti-alert-triangle st-icon"></i>
      </div>
    </div>

    <!-- TAB -->
    <div class="tab-bar">
      <button class="tab-btn active" onclick="switchTab('assign',this)">
        <i class="ti ti-key"></i> Assign Kamar
      </button>
      <button class="tab-btn" onclick="switchTab('overview',this)">
        <i class="ti ti-layout-grid"></i> Status Kamar
      </button>
    </div>

    <!-- TAB: ASSIGN KAMAR -->
    <div id="tab-assign">
      <div class="sec-head">
        <h2 class="sec-title">Pesanan Aktif (Check In)</h2>
        <span style="color:var(--muted);font-size:13px"><?= count($checked_in_orders) ?> pesanan</span>
      </div>

      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Kode Booking</th>
              <th>Nama Tamu</th>
              <th>Tipe Kamar</th>
              <th>Check-in</th>
              <th>Check-out</th>
              <th>Nomor Kamar</th>
              <th>Aksi</th>
            </tr>
          </thead>
          <tbody>
          <?php if (empty($checked_in_orders)): ?>
            <tr>
              <td colspan="7">
                <div class="empty">
                  <i class="ti ti-calendar-off"></i>
                  <p>Tidak ada pesanan dengan status Check In saat ini.</p>
                </div>
              </td>
            </tr>
          <?php else: foreach ($checked_in_orders as $o): ?>
            <tr>
              <td style="font-family:monospace;font-size:12px;color:var(--muted)"><?= htmlspecialchars($o['kode_booking']) ?></td>
              <td>
                <div style="font-weight:500"><?= htmlspecialchars($o['nama_tamu']) ?></div>
                <div style="font-size:11px;color:var(--muted)"><?= htmlspecialchars($o['email_tamu'] ?? $o['email_user'] ?? '') ?></div>
              </td>
              <td><?= htmlspecialchars($o['tipe_kamar']) ?></td>
              <td style="font-size:12px"><?= $o['check_in'] ? date('d M Y', strtotime($o['check_in'])) : '—' ?></td>
              <td style="font-size:12px"><?= $o['check_out'] ? date('d M Y', strtotime($o['check_out'])) : '—' ?></td>
              <td>
                <?php if ($o['nomor_kamar']): ?>
                  <span class="badge badge-occupied">
                    <i class="ti ti-door-enter"></i> <?= htmlspecialchars($o['nomor_kamar']) ?>
                  </span>
                <?php else: ?>
                  <span class="badge badge-no-room">
                    <i class="ti ti-alert-triangle"></i> Belum diassign
                  </span>
                <?php endif; ?>
              </td>
              <td>
                <div style="display:flex;gap:6px;flex-wrap:wrap">
                  <?php if (!$o['nomor_kamar']): ?>
                    <button class="btn btn-gold btn-sm"
                      onclick='openAssignModal(<?= json_encode([
                        "id"        => $o["id"],
                        "kode"      => $o["kode_booking"],
                        "nama"      => $o["nama_tamu"],
                        "tipe"      => $o["tipe_kamar"],
                        "check_in"  => $o["check_in"],
                        "check_out" => $o["check_out"],
                      ]) ?>)'>
                      <i class="ti ti-key"></i> Assign Kamar
                    </button>
                  <?php else: ?>
                    <button class="btn btn-success btn-sm"
                      onclick='confirmRelease(<?= $o["id"] ?>, "<?= htmlspecialchars($o["kode_booking"], ENT_QUOTES) ?>", "<?= htmlspecialchars($o["nomor_kamar"], ENT_QUOTES) ?>")'>
                      <i class="ti ti-door-exit"></i> Checkout & Bebaskan
                    </button>
                    <button class="btn btn-ghost btn-sm"
                      onclick='openAssignModal(<?= json_encode([
                        "id"        => $o["id"],
                        "kode"      => $o["kode_booking"],
                        "nama"      => $o["nama_tamu"],
                        "tipe"      => $o["tipe_kamar"],
                        "check_in"  => $o["check_in"],
                        "check_out" => $o["check_out"],
                      ]) ?>)'>
                      <i class="ti ti-edit"></i> Ganti
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

    <!-- TAB: STATUS KAMAR -->
    <div id="tab-overview" style="display:none">
      <div class="sec-head">
        <h2 class="sec-title">Semua Kamar</h2>
        <span style="color:var(--muted);font-size:13px"><?= count($semua_kamar) ?> kamar aktif</span>
      </div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr><th>Nomor</th><th>Tipe</th><th>Lantai</th><th>Kapasitas</th><th>Status</th></tr>
          </thead>
          <tbody>
          <?php foreach ($semua_kamar as $k): ?>
            <tr>
              <td style="font-weight:600;color:var(--gold)"><?= htmlspecialchars($k['nomor_kamar']) ?></td>
              <td><?= htmlspecialchars($k['tipe_kamar']) ?></td>
              <td style="color:var(--muted)">Lantai <?= $k['lantai'] ?></td>
              <td style="text-align:center;color:var(--muted)"><?= $k['kapasitas'] ?> org</td>
              <td>
                <span class="badge badge-<?= $k['status'] ?>">
                  <?= ucfirst($k['status']) ?>
                </span>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div>
</div>

<!-- ══ MODAL: ASSIGN KAMAR ══ -->
<div class="modal-overlay" id="assign-modal">
  <div class="modal">
    <div class="modal-head">
      <h3><i class="ti ti-key"></i> Assign Nomor Kamar</h3>
      <button class="close-x" onclick="closeModal()">×</button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="m-booking-id">
      <input type="hidden" id="m-tipe">
      <input type="hidden" id="m-selected-kamar-id">
      <input type="hidden" id="m-selected-nomor">

      <!-- Info pesanan -->
      <div class="info-box" id="m-info"></div>

      <!-- Pilih dari daftar kamar tersedia -->
      <div class="fg">
        <label><i class="ti ti-building"></i> Pilih Kamar Tersedia</label>
        <div id="m-room-grid" class="room-grid">
          <div class="room-empty"><span class="spinner"></span> Memuat kamar…</div>
        </div>
      </div>

      <!-- Atau isi manual -->
      <div class="fg">
        <label><i class="ti ti-edit"></i> Atau Isi Manual (jika tidak ada di list)</label>
        <input type="text" id="m-manual" placeholder="cth: 205" maxlength="10"
          oninput="onManualInput(this.value)">
      </div>

      <div id="m-selected-info" style="display:none;background:var(--bg2);border:1px solid var(--gold-dim);border-radius:8px;padding:12px 14px;font-size:13px;color:var(--gold)">
        ✓ Kamar dipilih: <strong id="m-selected-label">—</strong>
      </div>
    </div>
    <div class="modal-foot">
      <button class="btn btn-ghost" onclick="closeModal()">Batal</button>
      <button class="btn btn-gold" id="btn-assign" onclick="submitAssign()" disabled>
        <i class="ti ti-check"></i> Tetapkan Kamar
      </button>
    </div>
  </div>
</div>

<!-- TOAST -->
<div id="toast"><span id="toast-icon">✅</span><span id="toast-msg">Berhasil</span></div>

<script>
/* ── TAB ── */
function switchTab(name, el) {
  document.getElementById('tab-assign').style.display   = name === 'assign'   ? '' : 'none';
  document.getElementById('tab-overview').style.display = name === 'overview' ? '' : 'none';
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
  el.classList.add('active');
}

/* ── TOAST ── */
function showToast(msg, type = 'success') {
  const t = document.getElementById('toast');
  document.getElementById('toast-msg').textContent = msg;
  document.getElementById('toast-icon').textContent = type === 'success' ? '✅' : '❌';
  t.className = 'show ' + type;
  clearTimeout(t._t);
  t._t = setTimeout(() => t.className = '', 3500);
}

/* ── MODAL OPEN / CLOSE ── */
let currentBooking = null;

async function openAssignModal(o) {
  currentBooking = o;
  document.getElementById('m-booking-id').value = o.id;
  document.getElementById('m-tipe').value        = o.tipe;
  document.getElementById('m-selected-kamar-id').value = '';
  document.getElementById('m-selected-nomor').value    = '';
  document.getElementById('m-manual').value = '';
  document.getElementById('m-selected-info').style.display = 'none';
  document.getElementById('btn-assign').disabled = true;

  // Info box
  const fmt = s => s ? new Date(s).toLocaleDateString('id-ID',{day:'2-digit',month:'short',year:'numeric'}) : '—';
  document.getElementById('m-info').innerHTML = `
    <div class="info-row"><span class="ik">Kode Booking</span><span class="iv" style="font-family:monospace">${o.kode}</span></div>
    <div class="info-row"><span class="ik">Nama Tamu</span><span class="iv">${o.nama}</span></div>
    <div class="info-row"><span class="ik">Tipe Kamar</span><span class="iv">${o.tipe}</span></div>
    <div class="info-row"><span class="ik">Check-in / Check-out</span><span class="iv">${fmt(o.check_in)} — ${fmt(o.check_out)}</span></div>
  `;

  document.getElementById('assign-modal').classList.add('open');

  // Load kamar tersedia
  await loadRooms(o.tipe);
}

function closeModal() {
  document.getElementById('assign-modal').classList.remove('open');
  currentBooking = null;
}

/* ── LOAD KAMAR DARI SERVER ── */
async function loadRooms(tipe) {
  const grid = document.getElementById('m-room-grid');
  grid.innerHTML = '<div class="room-empty"><span class="spinner"></span> Memuat daftar kamar…</div>';

  const fd = new FormData();
  fd.append('ajax', '1');
  fd.append('action', 'get_available_rooms');
  fd.append('tipe_kamar', tipe);

  try {
    const res  = await fetch('management.php', { method: 'POST', body: fd });
    const data = await res.json();

    if (!data.success || !data.rooms.length) {
      grid.innerHTML = '<div class="room-empty"><i class="ti ti-building-off"></i> Tidak ada kamar tersedia untuk tipe ini.<br><small>Gunakan input manual di bawah.</small></div>';
      return;
    }

    grid.innerHTML = '';
    data.rooms.forEach(k => {
      const card = document.createElement('div');
      card.className = 'room-card';
      card.dataset.id    = k.id;
      card.dataset.nomor = k.nomor_kamar;
      card.innerHTML = `<div class="rn">${k.nomor_kamar}</div><div class="rl">Lantai ${k.lantai}</div><div class="rl" style="font-size:10px;margin-top:3px">${Number(k.harga_per_malam).toLocaleString('id-ID',{style:'currency',currency:'IDR',minimumFractionDigits:0})}</div>`;
      card.onclick = () => selectRoom(card, k.id, k.nomor_kamar);
      grid.appendChild(card);
    });
  } catch (e) {
    grid.innerHTML = '<div class="room-empty" style="color:var(--danger)">Gagal memuat kamar.</div>';
  }
}

/* ── SELECT ROOM ── */
function selectRoom(card, id, nomor) {
  // Deselect semua
  document.querySelectorAll('.room-card').forEach(c => c.classList.remove('selected'));
  card.classList.add('selected');
  document.getElementById('m-selected-kamar-id').value = id;
  document.getElementById('m-selected-nomor').value    = nomor;
  document.getElementById('m-manual').value = '';
  showSelectedInfo(nomor + ' (dari daftar)');
}

/* ── MANUAL INPUT ── */
function onManualInput(val) {
  document.querySelectorAll('.room-card').forEach(c => c.classList.remove('selected'));
  document.getElementById('m-selected-kamar-id').value = '';
  if (val.trim()) {
    document.getElementById('m-selected-nomor').value = val.trim();
    showSelectedInfo(val.trim() + ' (manual)');
  } else {
    document.getElementById('m-selected-nomor').value = '';
    document.getElementById('m-selected-info').style.display = 'none';
    document.getElementById('btn-assign').disabled = true;
  }
}

function showSelectedInfo(label) {
  document.getElementById('m-selected-label').textContent = label;
  document.getElementById('m-selected-info').style.display = '';
  document.getElementById('btn-assign').disabled = false;
}

/* ── SUBMIT ASSIGN ── */
async function submitAssign() {
  const bookingId  = document.getElementById('m-booking-id').value;
  const kamarId    = document.getElementById('m-selected-kamar-id').value;
  const nomorKamar = document.getElementById('m-selected-nomor').value.trim();

  if (!nomorKamar) {
    showToast('Pilih atau isi nomor kamar terlebih dahulu', 'error');
    return;
  }

  const btn = document.getElementById('btn-assign');
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner"></span> Menyimpan…';

  const fd = new FormData();
  fd.append('ajax', '1');
  fd.append('action', 'assign_room');
  fd.append('booking_id', bookingId);
  fd.append('kamar_id', kamarId || 0);
  fd.append('nomor_kamar', nomorKamar);

  try {
    const res  = await fetch('management.php', { method: 'POST', body: fd });
    const text = await res.text();
    let data;
    try {
      data = JSON.parse(text);
    } catch (parseErr) {
      console.error('Response bukan JSON:', text);
      showToast('Response server tidak valid. Cek console untuk detail.', 'error');
      btn.disabled = false;
      btn.innerHTML = '<i class="ti ti-check"></i> Tetapkan Kamar';
      return;
    }
    if (data.success) {
      showToast(data.message, 'success');
      closeModal();
      setTimeout(() => location.reload(), 1200);
    } else {
      showToast(data.message || 'Terjadi kesalahan', 'error');
      btn.disabled = false;
      btn.innerHTML = '<i class="ti ti-check"></i> Tetapkan Kamar';
    }
  } catch (e) {
    console.error('Fetch error:', e);
    showToast('Gagal menghubungi server: ' + e.message, 'error');
    btn.disabled = false;
    btn.innerHTML = '<i class="ti ti-check"></i> Tetapkan Kamar';
  }
}

/* ── RELEASE / CHECKOUT ── */
async function confirmRelease(id, kode, nomor) {
  if (!confirm(`Checkout & bebaskan kamar ${nomor} dari booking ${kode}?\nStatus pesanan akan berubah ke checked_out.`)) return;

  const fd = new FormData();
  fd.append('ajax', '1');
  fd.append('action', 'release_room');
  fd.append('booking_id', id);

  try {
    const res  = await fetch('management.php', { method: 'POST', body: fd });
    const data = await res.json();

    if (data.success) {
      showToast(data.message, 'success');
      setTimeout(() => location.reload(), 1200);
    } else {
      showToast(data.message, 'error');
    }
  } catch (e) {
    showToast('Server error', 'error');
  }
}

/* ── CLOSE MODAL ON BACKDROP / ESC ── */
document.querySelector('.modal-overlay').addEventListener('click', e => {
  if (e.target.classList.contains('modal-overlay')) closeModal();
});
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });
</script>
</body>
</html>