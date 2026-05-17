<?php
session_start();

// Cek apakah sudah login
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.html');
    exit;
}

// Cek apakah role-nya admin
if ($_SESSION['role'] !== 'admin') {
    header('Location: ../mainpage.html');
    exit;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Grand Lumière</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', sans-serif;
            background: #0a1628;
            color: #fff;
        }
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: 260px;
            height: 100%;
            background: rgba(10, 22, 40, 0.95);
            backdrop-filter: blur(10px);
            border-right: 1px solid rgba(212, 175, 55, 0.2);
            padding: 2rem 1rem;
        }
        .sidebar h2 {
            color: #d4af37;
            margin-bottom: 2rem;
            text-align: center;
        }
        .sidebar nav a {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 16px;
            color: rgba(255,255,255,0.7);
            text-decoration: none;
            border-radius: 8px;
            margin-bottom: 8px;
            transition: all 0.2s;
        }
        .sidebar nav a:hover, .sidebar nav a.active {
            background: rgba(212, 175, 55, 0.15);
            color: #d4af37;
        }
        .main-content {
            margin-left: 260px;
            padding: 2rem;
        }
        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .logout-btn {
            background: rgba(212, 175, 55, 0.2);
            border: 1px solid rgba(212, 175, 55, 0.3);
            padding: 8px 16px;
            border-radius: 8px;
            color: #d4af37;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .stat-card {
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 12px;
            padding: 1rem;
        }
        .stat-card h3 { font-size: 28px; color: #d4af37; margin-bottom: 5px; }
        .stat-card p { color: rgba(255,255,255,0.5); font-size: 12px; }
        table {
            width: 100%;
            background: rgba(255,255,255,0.05);
            border-radius: 12px;
            overflow: hidden;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        th { background: rgba(212, 175, 55, 0.15); color: #d4af37; }
        .badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
        }
        .badge-pending { background: #ff9800; color: #000; }
        .badge-confirmed { background: #4caf50; color: #fff; }
        .btn-small {
            background: rgba(212, 175, 55, 0.2);
            border: none;
            padding: 4px 8px;
            border-radius: 4px;
            color: #d4af37;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <h2><i class="ti ti-crown"></i> Grand Lumière</h2>
        <nav>
            <a href="#" class="active" onclick="showSection('dashboard')">
                <i class="ti ti-dashboard"></i> Dashboard
            </a>
            <a href="#" onclick="showSection('bookings')">
                <i class="ti ti-calendar"></i> Semua Pemesanan
            </a>
            <a href="#" onclick="showSection('users')">
                <i class="ti ti-users"></i> Kelola User
            </a>
        </nav>
    </div>

    <div class="main-content">
        <div class="top-bar">
            <h1>Admin Dashboard</h1>
            <a href="logout.php" class="logout-btn">
                <i class="ti ti-logout"></i> Logout
            </a>
        </div>

        <div id="dashboard">
            <div class="stats" id="stats">
                <!-- Stats akan diisi JS -->
            </div>
            <h3 style="margin-bottom: 1rem;">Pemesanan Terbaru</h3>
            <table id="recentBookings">
                <thead>
                    <tr><th>Kode</th><th>Nama</th><th>Kamar</th><th>Total</th><th>Status</th><th>Aksi</th></tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>

        <div id="bookings" style="display:none;">
            <h3 style="margin-bottom: 1rem;">Semua Pemesanan</h3>
            <table id="allBookings">
                <thead><tr><th>ID</th><th>User ID</th><th>Nama</th><th>Kamar</th><th>Check In</th><th>Check Out</th><th>Total</th><th>Status</th><th>Aksi</th></tr></thead>
                <tbody></tbody>
            </table>
        </div>

        <div id="users" style="display:none;">
            <h3 style="margin-bottom: 1rem;">Kelola User</h3>
            <table id="userTable">
                <thead><tr><th>ID</th><th>Username</th><th>Email</th><th>Role</th><th>Aksi</th></tr></thead>
                <tbody></tbody>
            </table>
        </div>
    </div>

    <script>
        function showSection(section) {
            document.getElementById('dashboard').style.display = section === 'dashboard' ? 'block' : 'none';
            document.getElementById('bookings').style.display = section === 'bookings' ? 'block' : 'none';
            document.getElementById('users').style.display = section === 'users' ? 'block' : 'none';
            
            if (section === 'dashboard') loadDashboard();
            if (section === 'bookings') loadAllBookings();
            if (section === 'users') loadUsers();
        }

        function loadDashboard() {
            fetch('../admin_api.php?action=dashboard')
                .then(res => res.json())
                .then(data => {
                    if (data.stats) {
                        document.getElementById('stats').innerHTML = `
                            <div class="stat-card"><h3>${data.stats.total_users}</h3><p>Total User</p></div>
                            <div class="stat-card"><h3>${data.stats.total_bookings}</h3><p>Total Pemesanan</p></div>
                            <div class="stat-card"><h3>${data.stats.pending_bookings}</h3><p>Pending</p></div>
                            <div class="stat-card"><h3>$${data.stats.total_revenue}</h3><p>Total Revenue</p></div>
                        `;
                    }
                    if (data.recent) {
                        let html = '';
                        data.recent.forEach(b => {
                            html += `<tr>
                                <td>${b.kode_booking}</td>
                                <td>${b.nama_tamu}</td>
                                <td>${b.tipe_kamar}</td>
                                <td>$${b.total}</td>
                                <td><span class="badge badge-${b.status}">${b.status}</span></td>
                                <td><button class="btn-small" onclick="updateStatus(${b.id}, 'confirmed')">Confirm</button></td>
                            </tr>`;
                        });
                        document.querySelector('#recentBookings tbody').innerHTML = html;
                    }
                });
        }

        function loadAllBookings() {
            fetch('../admin_api.php?action=all_bookings')
                .then(res => res.json())
                .then(data => {
                    let html = '';
                    data.forEach(b => {
                        html += `<tr>
                            <td>${b.id}</td>
                            <td>${b.user_id}</td>
                            <td>${b.nama_tamu}</td>
                            <td>${b.tipe_kamar}</td>
                            <td>${b.check_in}</td>
                            <td>${b.check_out}</td>
                            <td>$${b.total}</td>
                            <td><span class="badge badge-${b.status}">${b.status}</span></td>
                            <td>
                                <button class="btn-small" onclick="updateStatus(${b.id}, 'confirmed')">Confirm</button>
                                <button class="btn-small" onclick="updateStatus(${b.id}, 'cancelled')">Cancel</button>
                            </td>
                        </tr>`;
                    });
                    document.querySelector('#allBookings tbody').innerHTML = html;
                });
        }

        function loadUsers() {
            fetch('../admin_api.php?action=users')
                .then(res => res.json())
                .then(data => {
                    let html = '';
                    data.forEach(u => {
                        html += `<tr>
                            <td>${u.id}</td>
                            <td>${u.username}</td>
                            <td>${u.email}</td>
                            <td>${u.role}</td>
                            <td>
                                <button class="btn-small" onclick="changeRole(${u.id}, 'admin')">Jadi Admin</button>
                                <button class="btn-small" onclick="changeRole(${u.id}, 'user')">Jadi User</button>
                            </td>
                        </tr>`;
                    });
                    document.querySelector('#userTable tbody').innerHTML = html;
                });
        }

        function updateStatus(bookingId, status) {
            fetch('../admin_api.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `action=update_status&id=${bookingId}&status=${status}`
            }).then(() => {
                loadDashboard();
                loadAllBookings();
            });
        }

        function changeRole(userId, role) {
            fetch('../admin_api.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `action=change_role&id=${userId}&role=${role}`
            }).then(() => loadUsers());
        }

        loadDashboard();
    </script>
</body>
</html>