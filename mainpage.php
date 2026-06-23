<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.html');
    exit;
}
require_once 'config.php';

$user_id  = $_SESSION['user_id'];
$username = htmlspecialchars($_SESSION['username'] ?? 'Guest');
$email    = htmlspecialchars($_SESSION['email']    ?? '');
$initial  = strtoupper(mb_substr($username, 0, 1));

// Ambil pesanan yang belum dibayar (status = pending)
$stmt = $conn->prepare("
    SELECT id, kode_booking, tipe_kamar, check_in, check_out, total, status, created_at
    FROM pemesanan
    WHERE user_id = ? AND status = 'pending'
    ORDER BY created_at DESC
    LIMIT 10
");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$pending_orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grand Lumière Hotel — Main</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css">
    <link
        href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@300;400;500;600&family=Jost:wght@300;400;500&display=swap"
        rel="stylesheet">
    <script src="https://app.sandbox.midtrans.com/snap/snap.js"
        data-client-key="Mid-client-Z5Yf_9PreVUunKsl"></script>
    <style>
        *,
        *::before,
        *::after {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --gold: #d4af37;
            --gold-dim: rgba(212, 175, 55, 0.7);
            --gold-pale: rgba(212, 175, 55, 0.15);
            --dark: #060e1a;
            --dark2: #0a1628;
            --dark3: #0f1f38;
            --glass: rgba(255, 255, 255, 0.06);
            --glass-border: rgba(255, 255, 255, 0.12);
            --text: #ffffff;
            --text-muted: rgba(255, 255, 255, 0.5);
            --text-dim: rgba(255, 255, 255, 0.3);
            --navy: #0d2137;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            font-family: 'Jost', sans-serif;
            background: var(--dark);
            color: var(--text);
            overflow-x: hidden;
        }

        .bg-fixed {
            position: fixed;
            inset: 0;
            z-index: 0;
            background: linear-gradient(135deg, #060e1a 0%, #0f1f38 45%, #060e1a 100%);
        }

        .bg-fixed::before {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(ellipse at 15% 40%, rgba(24, 95, 165, 0.18) 0%, transparent 55%),
                radial-gradient(ellipse at 85% 15%, rgba(83, 74, 183, 0.15) 0%, transparent 50%),
                radial-gradient(ellipse at 55% 85%, rgba(15, 110, 86, 0.12) 0%, transparent 50%);
            animation: bgPulse 8s ease-in-out infinite alternate;
        }

        .bg-fixed::after {
            content: '';
            position: absolute;
            inset: 0;
            background-image: radial-gradient(circle at 1px 1px, rgba(255, 255, 255, 0.04) 1px, transparent 0);
            background-size: 48px 48px;
        }

        @keyframes bgPulse {
            0% {
                opacity: 0.6;
            }

            100% {
                opacity: 1;
            }
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
            0% {
                opacity: 0.05;
            }

            100% {
                opacity: 0.6;
            }
        }

        .overlay {
            position: fixed;
            inset: 0;
            background: rgba(6, 14, 26, 0.45);
            z-index: 2;
        }

        .page-wrap {
            position: relative;
            z-index: 3;
        }

        nav {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 100;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 3rem;
            height: 64px;
            background: rgba(6, 14, 26, 0.75);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            border-bottom: 0.5px solid var(--glass-border);
            transition: background 0.3s;
        }

        .nav-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
        }

        .nav-brand i {
            font-size: 20px;
            color: var(--gold);
        }

        .nav-brand span {
            font-family: 'Cormorant Garamond', serif;
            font-size: 18px;
            font-weight: 500;
            color: var(--text);
            letter-spacing: 3px;
            text-transform: uppercase;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 2px;
            list-style: none;
        }

        .nav-links li a {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 8px 18px;
            font-size: 12px;
            font-weight: 400;
            color: var(--text-muted);
            letter-spacing: 2px;
            text-transform: uppercase;
            text-decoration: none;
            border-radius: 6px;
            transition: color 0.2s, background 0.2s;
            position: relative;
        }

        .nav-links li a i {
            font-size: 15px;
        }

        .nav-links li a:hover {
            color: var(--text);
            background: var(--glass);
        }

        .nav-links li a.active {
            color: var(--gold);
        }

        .nav-links li a.active::after {
            content: '';
            position: absolute;
            bottom: 2px;
            left: 50%;
            transform: translateX(-50%);
            width: 20px;
            height: 1.5px;
            background: var(--gold);
            border-radius: 2px;
        }

        .nav-user {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .nav-avatar {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: var(--gold-pale);
            border: 1px solid var(--gold-dim);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            color: var(--gold);
            font-weight: 500;
            cursor: pointer;
            transition: background 0.2s, transform 0.2s;
            user-select: none;
        }
        .nav-avatar:hover { background: rgba(212,175,55,0.25); transform: scale(1.05); }

        /* ── RIWAYAT BAR (sebelah profil) ── */
        .riwayat-wrap { position: relative; }
        .riwayat-btn {
            display: flex; align-items: center; gap: 7px;
            padding: 6px 13px; border-radius: 20px;
            background: rgba(212,175,55,0.08);
            border: 0.5px solid rgba(212,175,55,0.25);
            color: var(--gold); font-size: 12px; font-weight: 500;
            letter-spacing: 0.5px; cursor: pointer;
            transition: background 0.2s, border-color 0.2s;
            font-family: 'Jost', sans-serif;
            white-space: nowrap;
        }
        .riwayat-btn:hover { background: rgba(212,175,55,0.16); border-color: rgba(212,175,55,0.5); }
        .riwayat-btn .rb-count {
            background: #f0a843; color: #060e1a;
            font-size: 10px; font-weight: 700;
            width: 17px; height: 17px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            line-height: 1;
        }

        .riwayat-dropdown {
            display: none;
            position: absolute;
            top: calc(100% + 12px);
            right: 0;
            width: 310px;
            background: rgba(10,22,40,0.97);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            border: 0.5px solid rgba(212,175,55,0.2);
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0,0,0,0.5);
            animation: dropIn 0.2s ease;
            z-index: 200;
        }
        .riwayat-dropdown.open { display: block; }

        .rd-head {
            padding: 14px 18px 10px;
            border-bottom: 0.5px solid rgba(255,255,255,0.07);
            display: flex; align-items: center; justify-content: space-between;
        }
        .rd-head span {
            font-size: 11px; letter-spacing: 2px; text-transform: uppercase; color: var(--gold);
        }
        .rd-head .rd-badge {
            background: rgba(240,168,67,0.15); color: #f0a843;
            border: 1px solid rgba(240,168,67,0.3);
            font-size: 10px; font-weight: 700;
            padding: 2px 8px; border-radius: 10px;
        }
        .rd-list { max-height: 260px; overflow-y: auto; }
        .rd-list::-webkit-scrollbar { width: 4px; }
        .rd-list::-webkit-scrollbar-thumb { background: rgba(212,175,55,0.2); border-radius: 2px; }

        .rd-item {
            padding: 11px 18px;
            border-bottom: 0.5px solid rgba(255,255,255,0.05);
            display: flex; align-items: center; justify-content: space-between; gap: 10px;
            transition: background 0.15s;
        }
        .rd-item:last-child { border-bottom: none; }
        .rd-item:hover { background: rgba(255,255,255,0.03); }
        .rd-left { flex: 1; min-width: 0; }
        .rd-code { font-size: 10px; font-family: monospace; color: var(--text-muted); margin-bottom: 2px; }
        .rd-room { font-size: 13px; font-weight: 500; color: var(--text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .rd-date { font-size: 11px; color: var(--text-muted); margin-top: 2px; }
        .rd-right { text-align: right; flex-shrink: 0; }
        .rd-total { font-size: 12px; color: var(--gold); font-weight: 500; }
        .rd-status {
            display: inline-block; margin-top: 3px;
            padding: 1px 7px; border-radius: 10px; font-size: 10px; font-weight: 700;
            background: rgba(240,168,67,0.15); color: #f0a843;
            border: 1px solid rgba(240,168,67,0.25);
        }
        .rd-empty {
            padding: 24px 18px; text-align: center;
            font-size: 12px; color: var(--text-muted);
        }
        .rd-empty i { font-size: 26px; display: block; opacity: 0.25; margin-bottom: 8px; }

        /* ── PROFILE DROPDOWN (hanya info + logout) ── */
        .profile-wrap {
            position: relative;
        }
        .profile-dropdown {
            display: none;
            position: absolute;
            top: calc(100% + 12px);
            right: 0;
            width: 300px;
            background: rgba(10, 22, 40, 0.97);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            border: 0.5px solid rgba(212,175,55,0.2);
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0,0,0,0.5);
            animation: dropIn 0.2s ease;
            z-index: 200;
        }
        .profile-dropdown.open { display: block; }
        @keyframes dropIn {
            from { opacity: 0; transform: translateY(-8px) scale(0.97); }
            to   { opacity: 1; transform: translateY(0) scale(1); }
        }

        /* Header profil */
        .pd-head {
            padding: 18px 18px 14px;
            border-bottom: 0.5px solid rgba(255,255,255,0.08);
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .pd-avatar-big {
            width: 42px; height: 42px;
            border-radius: 50%;
            background: var(--gold-pale);
            border: 1px solid var(--gold-dim);
            display: flex; align-items: center; justify-content: center;
            font-size: 17px; color: var(--gold); font-weight: 600;
            flex-shrink: 0;
        }
        .pd-name { font-size: 14px; font-weight: 500; color: var(--text); }
        .pd-email { font-size: 11px; color: var(--text-muted); margin-top: 2px; }

        /* Footer dropdown */
        .pd-footer {
            padding: 10px 12px;
            border-top: 0.5px solid rgba(255,255,255,0.08);
        }
        .pd-logout {
            display: flex; align-items: center; justify-content: center; gap: 7px;
            width: 100%; padding: 9px;
            background: rgba(224,82,82,0.08);
            border: 1px solid rgba(224,82,82,0.2);
            border-radius: 8px;
            color: #e05252; font-size: 13px; font-weight: 500;
            font-family: 'Jost', sans-serif;
            text-decoration: none; cursor: pointer;
            transition: background 0.2s, border-color 0.2s;
        }
        .pd-logout:hover { background: rgba(224,82,82,0.18); border-color: rgba(224,82,82,0.5); }

        section {
            min-height: 100vh;
            padding: 100px 0 60px;
            display: none;
        }

        section.active-section {
            display: block;
        }

        .container {
            max-width: 1100px;
            margin: 0 auto;
            padding: 0 2rem;
        }

        .section-label {
            font-size: 10px;
            letter-spacing: 4px;
            text-transform: uppercase;
            color: var(--gold);
            margin-bottom: 8px;
        }

        .section-heading {
            font-family: 'Cormorant Garamond', serif;
            font-size: 42px;
            font-weight: 300;
            color: var(--text);
            line-height: 1.15;
            margin-bottom: 12px;
        }

        .section-sub {
            font-size: 14px;
            color: var(--text-muted);
            line-height: 1.7;
            max-width: 520px;
            margin-bottom: 2.5rem;
        }

        .gold-line {
            width: 48px;
            height: 1px;
            background: var(--gold-dim);
            margin-bottom: 2rem;
        }

        #home .hero-top {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            margin-bottom: 2.5rem;
        }

        .slider-section {
            margin-bottom: 3rem;
        }

        .slider-label {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1rem;
        }

        .slider-label span {
            font-size: 11px;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: var(--text-muted);
        }

        .slider-nav {
            display: flex;
            gap: 8px;
        }

        .slider-btn {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: var(--glass);
            border: 0.5px solid var(--glass-border);
            color: var(--text-muted);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 14px;
            transition: background 0.2s, color 0.2s, border-color 0.2s;
        }

        .slider-btn:hover {
            background: var(--gold-pale);
            border-color: var(--gold-dim);
            color: var(--gold);
        }

        .slider-track-wrap {
            overflow: hidden;
            border-radius: 14px;
        }

        .slider-track {
            display: flex;
            gap: 16px;
            transition: transform 0.5s cubic-bezier(0.4, 0, 0.2, 1);
            will-change: transform;
        }

        .slide-card {
            flex: 0 0 300px;
            height: 200px;
            border-radius: 12px;
            overflow: hidden;
            position: relative;
            border: 0.5px solid var(--glass-border);
        }

        .slide-card-overlay {
            position: absolute;
            inset: 0;
            background: linear-gradient(to top, rgba(6, 14, 26, 0.85) 0%, transparent 55%);
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            padding: 14px 16px;
        }

        .slide-card-title {
            font-family: 'Cormorant Garamond', serif;
            font-size: 16px;
            font-weight: 500;
            color: var(--text);
            margin-bottom: 2px;
        }

        .slide-card-sub {
            font-size: 11px;
            color: var(--text-muted);
        }

        .slide-card-bg {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .grad-1 {
            background: linear-gradient(135deg, #1a3a5c, #0a2040);
        }

        .grad-2 {
            background: linear-gradient(135deg, #1a2a1a, #0a3020);
        }

        .grad-3 {
            background: linear-gradient(135deg, #2a1a3a, #1a0a2a);
        }

        .grad-4 {
            background: linear-gradient(135deg, #3a2a1a, #2a1a0a);
        }

        .grad-5 {
            background: linear-gradient(135deg, #1a2a3a, #0a1a2a);
        }

        .video-gallery-section {
            margin-bottom: 3rem;
        }

        .video-gallery-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1rem;
        }

        .video-gallery-header span {
            font-size: 11px;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: var(--text-muted);
        }

        .video-gallery-badge {
            display: flex;
            align-items: center;
            gap: 6px;
            background: var(--gold-pale);
            border: 0.5px solid rgba(212, 175, 55, 0.3);
            border-radius: 20px;
            padding: 4px 12px;
            font-size: 10px;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            color: var(--gold);
        }

        .video-gallery-badge i {
            font-size: 12px;
        }

        .video-grid {
            display: grid;
            grid-template-columns: 1.65fr 1fr;
            grid-template-rows: 220px 220px;
            gap: 14px;
        }

        .video-card:first-child {
            grid-row: 1 / 3;
        }

        .video-card {
            position: relative;
            border-radius: 14px;
            overflow: hidden;
            border: 0.5px solid var(--glass-border);
            cursor: pointer;
            transition: border-color 0.3s, transform 0.3s;
            background: var(--dark2);
        }

        .video-card:hover {
            border-color: rgba(212, 175, 55, 0.45);
            transform: translateY(-3px);
        }

        .vc-bg-1 {
            background: linear-gradient(145deg, #0e2a4a 0%, #061828 60%, #0a1e3a 100%);
        }

        .vc-bg-2 {
            background: linear-gradient(145deg, #1a0f2e 0%, #0d0820 60%, #180a28 100%);
        }

        .vc-bg-3 {
            background: linear-gradient(145deg, #0f2a1a 0%, #061810 60%, #0a2015 100%);
        }

        .vc-bg {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .vc-bg::before {
            content: '';
            position: absolute;
            width: 160px;
            height: 160px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(212, 175, 55, 0.08) 0%, transparent 70%);
            animation: vcPulse 3s ease-in-out infinite;
        }

        @keyframes vcPulse {

            0%,
            100% {
                transform: scale(1);
                opacity: 0.6;
            }

            50% {
                transform: scale(1.3);
                opacity: 1;
            }
        }

        .vc-icon-main {
            font-size: 60px;
            color: rgba(255, 255, 255, 0.06);
            position: absolute;
            right: 20px;
            bottom: 56px;
            line-height: 1;
        }

        .video-card:first-child .vc-icon-main {
            font-size: 90px;
            right: 28px;
            bottom: 72px;
        }

        .vc-play {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .vc-play-btn {
            width: 52px;
            height: 52px;
            border-radius: 50%;
            background: rgba(212, 175, 55, 0.15);
            border: 1.5px solid var(--gold-dim);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: var(--gold);
            transition: background 0.25s, transform 0.25s, box-shadow 0.25s;
            backdrop-filter: blur(8px);
        }

        .video-card:first-child .vc-play-btn {
            width: 68px;
            height: 68px;
            font-size: 26px;
        }

        .video-card:hover .vc-play-btn {
            background: rgba(212, 175, 55, 0.28);
            transform: scale(1.1);
            box-shadow: 0 0 32px rgba(212, 175, 55, 0.25);
        }

        .vc-duration {
            position: absolute;
            top: 12px;
            right: 12px;
            background: rgba(6, 14, 26, 0.75);
            border: 0.5px solid var(--glass-border);
            border-radius: 6px;
            padding: 3px 8px;
            font-size: 10px;
            color: var(--text-muted);
            letter-spacing: 0.5px;
            backdrop-filter: blur(8px);
        }

        .vc-info {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(to top, rgba(6, 14, 26, 0.92) 0%, transparent 100%);
            padding: 18px 16px 16px;
        }

        .video-card:first-child .vc-info {
            padding: 28px 20px 20px;
        }

        .vc-tag {
            display: inline-block;
            font-size: 9px;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            color: var(--gold);
            margin-bottom: 4px;
        }

        .vc-title {
            font-family: 'Cormorant Garamond', serif;
            font-size: 16px;
            font-weight: 400;
            color: var(--text);
            margin-bottom: 2px;
            line-height: 1.3;
        }

        .video-card:first-child .vc-title {
            font-size: 22px;
        }

        .vc-sub {
            font-size: 11px;
            color: var(--text-dim);
        }

        .video-card::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(90deg, transparent, var(--gold-dim), transparent);
            opacity: 0;
            transition: opacity 0.3s;
        }

        .video-card:hover::after {
            opacity: 1;
        }

        .video-modal {
            position: fixed;
            inset: 0;
            z-index: 200;
            background: rgba(6, 14, 26, 0.95);
            backdrop-filter: blur(20px);
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.35s;
        }

        .video-modal.open {
            opacity: 1;
            pointer-events: all;
        }

        .modal-inner {
            position: relative;
            width: min(900px, 90vw);
        }

        .modal-close {
            position: absolute;
            top: -44px;
            right: 0;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: var(--glass);
            border: 0.5px solid var(--glass-border);
            color: var(--text-muted);
            font-size: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: background 0.2s, color 0.2s;
            z-index: 10;
        }

        .modal-close:hover {
            background: rgba(212, 175, 55, 0.15);
            color: var(--gold);
        }

        .modal-video-wrap {
            width: 100%;
            aspect-ratio: 16/9;
            border-radius: 14px;
            overflow: hidden;
            border: 0.5px solid var(--glass-border);
            background: var(--dark2);
            position: relative;
        }

        .modal-video-wrap video {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .modal-title-bar {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 14px;
        }

        .modal-title-bar i {
            font-size: 16px;
            color: var(--gold);
        }

        .modal-title {
            font-family: 'Cormorant Garamond', serif;
            font-size: 18px;
            color: var(--text);
        }

        .modal-meta {
            font-size: 11px;
            color: var(--text-dim);
            margin-left: auto;
        }

        .quick-facilities {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 12px;
            margin-top: 1rem;
        }

        .qf-card {
            background: var(--glass);
            border: 0.5px solid var(--glass-border);
            border-radius: 12px;
            padding: 16px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            transition: background 0.2s, border-color 0.2s, transform 0.2s;
            cursor: default;
        }

        .qf-card:hover {
            background: rgba(255, 255, 255, 0.09);
            border-color: rgba(212, 175, 55, 0.3);
            transform: translateY(-2px);
        }

        .qf-icon {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            background: var(--gold-pale);
            border: 0.5px solid rgba(212, 175, 55, 0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: 17px;
            color: var(--gold);
        }

        .qf-name {
            font-size: 13px;
            color: var(--text);
            font-weight: 400;
            margin-bottom: 2px;
        }

        .qf-desc {
            font-size: 11px;
            color: var(--text-dim);
            line-height: 1.4;
        }

        .stats-row {
            display: flex;
            gap: 2rem;
            flex-wrap: wrap;
            margin-top: 2.5rem;
            padding-top: 2rem;
            border-top: 0.5px solid var(--glass-border);
        }

        .stat-item {
            flex: 1;
            min-width: 100px;
        }

        .stat-num {
            font-family: 'Cormorant Garamond', serif;
            font-size: 36px;
            font-weight: 300;
            color: var(--gold);
        }

        .stat-label {
            font-size: 11px;
            color: var(--text-muted);
            letter-spacing: 1.5px;
            text-transform: uppercase;
            margin-top: 2px;
        }

        #fasilitas .fac-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-top: 1rem;
        }

        .fac-card {
            background: var(--glass);
            border: 0.5px solid var(--glass-border);
            border-radius: 16px;
            overflow: hidden;
            transition: transform 0.25s, border-color 0.25s;
        }

        .fac-card:hover {
            transform: translateY(-4px);
            border-color: rgba(212, 175, 55, 0.35);
        }

        .fac-img {
            height: 160px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            color: rgba(255, 255, 255, 0.12);
            position: relative;
            overflow: hidden;
        }

        .fac-img-inner {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .fac-img-inner i {
            font-size: 52px;
            color: rgba(255, 255, 255, 0.1);
        }

        .fac-img-label {
            position: absolute;
            top: 12px;
            left: 12px;
            background: var(--gold-pale);
            border: 0.5px solid var(--gold-dim);
            border-radius: 20px;
            padding: 4px 12px;
            font-size: 10px;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            color: var(--gold);
        }

        .fac-body {
            padding: 16px 18px 20px;
        }

        .fac-title {
            font-family: 'Cormorant Garamond', serif;
            font-size: 20px;
            font-weight: 400;
            color: var(--text);
            margin-bottom: 6px;
        }

        .fac-desc {
            font-size: 13px;
            color: var(--text-muted);
            line-height: 1.6;
            margin-bottom: 12px;
        }

        .fac-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }

        .fac-tag {
            font-size: 11px;
            color: var(--text-dim);
            background: rgba(255, 255, 255, 0.05);
            border: 0.5px solid rgba(255, 255, 255, 0.1);
            border-radius: 4px;
            padding: 3px 8px;
        }

        #pemesanan .booking-wrap {
            display: grid;
            grid-template-columns: 1fr 380px;
            gap: 2rem;
            align-items: start;
        }

        .room-picker {
            margin-bottom: 2rem;
        }

        .room-picker-title {
            font-size: 11px;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: var(--text-muted);
            margin-bottom: 12px;
        }

        .room-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .room-option {
            display: flex;
            align-items: center;
            gap: 14px;
            background: var(--glass);
            border: 0.5px solid var(--glass-border);
            border-radius: 12px;
            padding: 14px 16px;
            cursor: pointer;
            transition: background 0.2s, border-color 0.2s;
            position: relative;
        }

        .room-option:hover {
            background: rgba(255, 255, 255, 0.09);
        }

        .room-option.selected {
            border-color: var(--gold-dim);
            background: var(--gold-pale);
        }

        .room-option-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            flex-shrink: 0;
        }

        .room-option.selected .room-option-icon {
            background: rgba(212, 175, 55, 0.2);
            color: var(--gold);
        }

        .room-option:not(.selected) .room-option-icon {
            background: rgba(255, 255, 255, 0.05);
            color: var(--text-muted);
        }

        .room-option-info {
            flex: 1;
        }

        .room-option-name {
            font-size: 14px;
            color: var(--text);
            margin-bottom: 2px;
        }

        .room-option-detail {
            font-size: 11px;
            color: var(--text-dim);
        }

        .room-option-price {
            font-size: 15px;
            color: var(--gold);
            font-weight: 500;
        }

        .room-option-check {
            position: absolute;
            top: 12px;
            right: 12px;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            border: 1.5px solid var(--glass-border);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            color: var(--gold);
            transition: border-color 0.2s, background 0.2s;
        }

        .room-option.selected .room-option-check {
            background: var(--gold);
            border-color: var(--gold);
            color: var(--dark);
        }

        .booking-card {
            background: var(--glass);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 0.5px solid var(--glass-border);
            border-radius: 16px;
            padding: 1.75rem;
            position: sticky;
            top: 80px;
        }

        .booking-card-title {
            font-family: 'Cormorant Garamond', serif;
            font-size: 22px;
            font-weight: 400;
            color: var(--text);
            margin-bottom: 4px;
        }

        .booking-card-sub {
            font-size: 11px;
            color: var(--text-dim);
            letter-spacing: 1.5px;
            text-transform: uppercase;
            margin-bottom: 1.5rem;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }

        .form-field {
            margin-bottom: 14px;
        }

        .form-field label {
            display: block;
            font-size: 10px;
            color: var(--text-muted);
            letter-spacing: 1.5px;
            text-transform: uppercase;
            margin-bottom: 6px;
        }

        .form-field input,
        .form-field select {
            width: 100%;
            background: rgba(255, 255, 255, 0.06);
            border: 0.5px solid var(--glass-border);
            border-radius: 8px;
            padding: 10px 12px;
            font-size: 13px;
            color: var(--text);
            outline: none;
            transition: border-color 0.2s, background 0.2s;
            font-family: 'Jost', sans-serif;
            appearance: none;
        }

        .form-field input:focus,
        .form-field select:focus {
            border-color: var(--gold-dim);
            background: rgba(255, 255, 255, 0.09);
        }

        .form-field input::placeholder {
            color: var(--text-dim);
        }

        .form-field select option {
            background: var(--dark2);
            color: var(--text);
        }

        .input-icon-wrap {
            position: relative;
        }

        .input-icon-wrap i {
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 15px;
            color: var(--text-dim);
            pointer-events: none;
        }

        .input-icon-wrap input {
            padding-left: 34px;
        }

        .booking-summary {
            background: rgba(212, 175, 55, 0.07);
            border: 0.5px solid rgba(212, 175, 55, 0.2);
            border-radius: 8px;
            padding: 12px 14px;
            margin: 14px 0;
        }

        .booking-summary-row {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            color: var(--text-muted);
            margin-bottom: 6px;
        }

        .booking-summary-row:last-child {
            margin-bottom: 0;
            padding-top: 8px;
            border-top: 0.5px solid rgba(212, 175, 55, 0.2);
            font-size: 14px;
            font-weight: 500;
            color: var(--text);
        }

        .booking-summary-row span:last-child {
            color: var(--gold);
        }

        .btn-book {
            width: 100%;
            padding: 13px;
            background: var(--gold);
            border: none;
            border-radius: 8px;
            color: var(--dark);
            font-family: 'Jost', sans-serif;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 3px;
            text-transform: uppercase;
            cursor: pointer;
            transition: background 0.2s, transform 0.1s;
        }

        .btn-book:hover {
            background: #e0be45;
        }

        .btn-book:active {
            transform: scale(0.98);
        }

        .booking-note {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-top: 10px;
            font-size: 11px;
            color: var(--text-dim);
            justify-content: center;
        }

        .booking-note i {
            font-size: 13px;
            color: var(--gold-dim);
        }

        .toast {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            z-index: 999;
            background: rgba(6, 14, 26, 0.95);
            border: 0.5px solid rgba(212, 175, 55, 0.4);
            border-radius: 12px;
            padding: 14px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            transform: translateY(80px);
            opacity: 0;
            transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1), opacity 0.4s;
            backdrop-filter: blur(16px);
        }

        .toast.show {
            transform: translateY(0);
            opacity: 1;
        }

        .toast i {
            font-size: 20px;
            color: var(--gold);
        }

        .toast-msg {
            font-size: 13px;
            color: var(--text);
        }

        .toast-sub {
            font-size: 11px;
            color: var(--text-muted);
            margin-top: 2px;
        }

        @keyframes shake {

            0%,
            100% {
                transform: translateX(0);
            }

            25% {
                transform: translateX(-6px);
            }

            75% {
                transform: translateX(6px);
            }
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        footer {
            border-top: 0.5px solid var(--glass-border);
            padding: 1.5rem 2rem;
            text-align: center;
            font-size: 11px;
            color: var(--text-dim);
            letter-spacing: 1.5px;
            text-transform: uppercase;
        }

        /* ── TABLET (768px – 1024px) ── */
        @media (max-width: 1024px) {
            nav { padding: 0 1.5rem; }

            .container { padding: 0 1.5rem; }

            #pemesanan .booking-wrap {
                grid-template-columns: 1fr;
            }

            .booking-card { position: static; }

            .video-grid {
                grid-template-columns: 1fr 1fr;
                grid-template-rows: 200px 200px;
            }

            .video-card:first-child { grid-row: auto; grid-column: 1 / 3; }

            .section-heading { font-size: 36px; }

            .riwayat-btn span { display: none; }
        }

        /* ── MOBILE (≤ 768px) ── */
        @media (max-width: 768px) {
            nav {
                padding: 0 1rem;
                height: 56px;
            }

            .nav-brand span {
                font-size: 13px;
                letter-spacing: 2px;
            }

            .nav-links { gap: 0; }

            .nav-links li a span { display: none; }

            .nav-links li a {
                padding: 8px 10px;
                font-size: 11px;
            }

            .riwayat-btn {
                padding: 5px 9px;
                font-size: 11px;
            }

            .riwayat-btn i ~ * { display: none; }

            .riwayat-dropdown,
            .profile-dropdown {
                width: calc(100vw - 2rem);
                right: -0.5rem;
            }

            section { padding: 76px 0 48px; }

            .container { padding: 0 1rem; }

            .section-heading { font-size: 26px; }

            .section-sub { font-size: 13px; }

            .slide-card { flex: 0 0 240px; height: 165px; }

            .video-grid {
                grid-template-columns: 1fr;
                grid-template-rows: auto;
            }

            .video-card:first-child { grid-row: auto; grid-column: auto; }

            .video-card { min-height: 180px; }

            .quick-facilities {
                grid-template-columns: repeat(2, 1fr);
                gap: 10px;
            }

            .stats-row { gap: 1rem; }

            .stat-num { font-size: 28px; }

            #fasilitas .fac-grid {
                grid-template-columns: 1fr;
            }

            #pemesanan .booking-wrap { gap: 1.25rem; }

            .form-row { grid-template-columns: 1fr; }

            .booking-card { padding: 1.25rem; }

            .toast {
                bottom: 1rem;
                right: 1rem;
                left: 1rem;
                right: 1rem;
            }

            .modal-inner { width: 95vw; }
        }

        /* ── SMALL MOBILE (≤ 480px) ── */
        @media (max-width: 480px) {
            .nav-brand span { display: none; }

            .nav-links li a { padding: 8px 8px; }

            .section-heading { font-size: 22px; }

            .quick-facilities { grid-template-columns: 1fr; }

            .slide-card { flex: 0 0 200px; height: 145px; }

            .stat-num { font-size: 24px; }

            .booking-card-title { font-size: 18px; }
        }
    </style>
</head>

<body>

    <!-- BG -->
    <div class="bg-fixed"></div>
    <div class="stars" id="stars"></div>
    <div class="overlay"></div>

    <!-- ══ NAVBAR ══ -->
    <nav id="navbar">
        <a class="nav-brand" href="#">
            <i class="ti ti-crown"></i>
            <span>Grand Lumière</span>
        </a>

        <ul class="nav-links">
            <li>
                <a href="#" class="active" data-section="home" onclick="showSection('home',this)">
                    <i class="ti ti-home"></i> <span>Home</span>
                </a>
            </li>
            <li>
                <a href="#" data-section="fasilitas" onclick="showSection('fasilitas',this)">
                    <i class="ti ti-star"></i> <span>Fasilitas</span>
                </a>
            </li>
            <li>
                <a href="#" data-section="pemesanan" onclick="showSection('pemesanan',this)">
                    <i class="ti ti-calendar-check"></i> <span>Pemesanan</span>
                </a>
            </li>
        </ul>

        <div class="nav-user">

            <!-- RIWAYAT PESANAN BAR -->
            <div class="riwayat-wrap">
                <button class="riwayat-btn" id="riwayatBtn" onclick="toggleRiwayat(event)">
                    <i class="ti ti-clock" style="font-size:14px"></i>
                    Riwayat Pesanan
                    <?php if (!empty($pending_orders)): ?>
                        <span class="rb-count"><?= count($pending_orders) ?></span>
                    <?php endif; ?>
                </button>

                <div class="riwayat-dropdown" id="riwayatDropdown">
                    <div class="rd-head">
                        <span><i class="ti ti-clock" style="vertical-align:-2px;margin-right:5px"></i>Belum Dibayar</span>
                        <?php if (!empty($pending_orders)): ?>
                            <span class="rd-badge"><?= count($pending_orders) ?> pesanan</span>
                        <?php endif; ?>
                    </div>
                    <div class="rd-list">
                        <?php if (empty($pending_orders)): ?>
                            <div class="rd-empty">
                                <i class="ti ti-inbox"></i>
                                Tidak ada pesanan pending
                            </div>
                        <?php else: ?>
                            <?php foreach ($pending_orders as $o): ?>
                                <a href="riwayat.php" style="text-decoration:none;color:inherit;display:block;">
                                <div class="rd-item" style="cursor:pointer;">
                                    <div class="rd-left">
                                        <div class="rd-code"><?= htmlspecialchars($o['kode_booking'] ?? '#'.$o['id']) ?></div>
                                        <div class="rd-room"><?= htmlspecialchars($o['tipe_kamar'] ?? '—') ?></div>
                                        <div class="rd-date">
                                            <?= date('d M', strtotime($o['check_in'])) ?> → <?= date('d M Y', strtotime($o['check_out'])) ?>
                                        </div>
                                    </div>
                                    <div class="rd-right">
                                        <div class="rd-total">Rp<?= number_format($o['total'], 0, ',', '.') ?></div>
                                        <div class="rd-status">Pending</div>
                                    </div>
                                </div>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <div style="padding: 10px 14px; border-top: 1px solid rgba(255,255,255,0.08);">
                        <a href="riwayat.php" style="
                            display: block;
                            text-align: center;
                            font-size: 11px;
                            letter-spacing: 1.5px;
                            text-transform: uppercase;
                            color: var(--gold);
                            text-decoration: none;
                            padding: 8px;
                            border-radius: 6px;
                            transition: background 0.2s;
                        " onmouseover="this.style.background='rgba(212,175,55,0.1)'" onmouseout="this.style.background='transparent'">
                            <i class="ti ti-list" style="vertical-align:-2px; margin-right:5px;"></i>
                            Lihat Semua Riwayat
                        </a>
                    </div>
                </div>
            </div>

            <!-- PROFIL AVATAR -->
            <div class="profile-wrap">
                <div class="nav-avatar" id="profileBtn" onclick="toggleProfile(event)" title="<?= $username ?>">
                    <?= $initial ?>
                </div>
                <div class="profile-dropdown" id="profileDropdown">
                    <div class="pd-head">
                        <div class="pd-avatar-big"><?= $initial ?></div>
                        <div>
                            <div class="pd-name"><?= $username ?></div>
                            <div class="pd-email"><?= $email ?></div>
                        </div>
                    </div>
                    <div class="pd-footer">
                        <a href="logout.php" class="pd-logout">
                            <i class="ti ti-logout"></i> Keluar dari Akun
                        </a>
                    </div>
                </div>
            </div>

        </div>
    </nav>

    <!-- ══ PAGE WRAP ══ -->
    <div class="page-wrap">

        <!-- HOME SECTION -->
        <section id="home" class="active-section">
            <div class="container">

                <div class="hero-top">
                    <p class="section-label">Selamat Datang</p>
                    <h1 class="section-heading">Rasakan Kemewahan<br>Yang Sesungguhnya</h1>
                    <div class="gold-line"></div>
                    <p class="section-sub">Grand Lumière menghadirkan pengalaman menginap terbaik dengan sentuhan
                        keanggunan dan pelayanan kelas dunia di jantung kota.</p>
                </div>

                <!-- FOTO SLIDER -->
                <div class="slider-section">
                    <div class="slider-label">
                        <span>Galeri Hotel</span>
                        <div class="slider-nav">
                            <button class="slider-btn" id="prevBtn" onclick="slideMove(-1)"><i
                                    class="ti ti-chevron-left"></i></button>
                            <button class="slider-btn" id="nextBtn" onclick="slideMove(1)"><i
                                    class="ti ti-chevron-right"></i></button>
                        </div>
                    </div>

                    <div class="slider-track-wrap">
                        <div class="slider-track" id="sliderTrack">
                            <div class="slide-card">
                                <div class="slide-card-bg grad-1">
                                    <div class="slide-card-overlay">
                                        <div class="slide-card-title">Lobby Utama</div>
                                        <div class="slide-card-sub">Elegan & Megah</div>
                                    </div>
                                    <div class="slide-card-bg grad-1"
                                        style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;">
                                        <i class="ti ti-building"
                                            style="font-size:56px;color:rgba(255,255,255,0.08)"></i>
                                    </div>
                                </div>
                            </div>
                            <div class="slide-card">
                                <div class="slide-card-bg grad-2" style="position:relative;">
                                    <div
                                        style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;">
                                        <i class="ti ti-bed" style="font-size:56px;color:rgba(255,255,255,0.08)"></i>
                                    </div>
                                    <div class="slide-card-overlay">
                                        <div class="slide-card-title">Presidential Suite</div>
                                        <div class="slide-card-sub">140 m² · Panoramic View</div>
                                    </div>
                                </div>
                            </div>
                            <div class="slide-card">
                                <div class="slide-card-bg grad-3" style="position:relative;">
                                    <div
                                        style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;">
                                        <i class="ti ti-pool" style="font-size:56px;color:rgba(255,255,255,0.08)"></i>
                                    </div>
                                    <div class="slide-card-overlay">
                                        <div class="slide-card-title">Rooftop Pool</div>
                                        <div class="slide-card-sub">Infinity · Pemandangan Kota</div>
                                    </div>
                                </div>
                            </div>
                            <div class="slide-card">
                                <div class="slide-card-bg grad-4" style="position:relative;">
                                    <div
                                        style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;">
                                        <i class="ti ti-tool-kitchen-2"
                                            style="font-size:56px;color:rgba(255,255,255,0.08)"></i>
                                    </div>
                                    <div class="slide-card-overlay">
                                        <div class="slide-card-title">Le Jardin Restaurant</div>
                                        <div class="slide-card-sub">Fine Dining · Masakan Dunia</div>
                                    </div>
                                </div>
                            </div>
                            <div class="slide-card">
                                <div class="slide-card-bg grad-5" style="position:relative;">
                                    <div
                                        style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;">
                                        <i class="ti ti-spa" style="font-size:56px;color:rgba(255,255,255,0.08)"></i>
                                    </div>
                                    <div class="slide-card-overlay">
                                        <div class="slide-card-title">Lumière Spa</div>
                                        <div class="slide-card-sub">Relaksasi & Peremajaan</div>
                                    </div>
                                </div>
                            </div>
                            <div class="slide-card">
                                <div class="slide-card-bg grad-1"
                                    style="position:relative;background:linear-gradient(135deg,#1a3050,#0a1a30)">
                                    <div
                                        style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;">
                                        <i class="ti ti-confetti"
                                            style="font-size:56px;color:rgba(255,255,255,0.08)"></i>
                                    </div>
                                    <div class="slide-card-overlay">
                                        <div class="slide-card-title">Grand Ballroom</div>
                                        <div class="slide-card-sub">Kapasitas 500 Tamu</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- VIDEO GALLERY -->
                <div class="video-gallery-section">
                    <div class="video-gallery-header">
                        <span>Galeri Video</span>
                        <div class="video-gallery-badge">
                            <i class="ti ti-player-play"></i>
                            3 Video
                        </div>
                    </div>

                    <div class="video-grid">

                        <div class="video-card vc-bg-1"
                            onclick="openVideoModal('Tour Hotel Grand Lumière', '4:28', 'Tur Lengkap Hotel', 'asset/video hotel.mp4')">
                            <div class="vc-bg"></div>
                            <i class="ti ti-building vc-icon-main"></i>
                            <div class="vc-play">
                                <div class="vc-play-btn"><i class="ti ti-player-play-filled"></i></div>
                            </div>
                            <div class="vc-info">
                                <div class="vc-tag">Featured · Tur Hotel</div>
                                <div class="vc-title">Tour Grand Lumière — Seluruh Fasilitas</div>
                                <div class="vc-sub">Jelajahi setiap sudut kemewahan hotel kami</div>
                            </div>
                        </div>

                        <div class="video-card vc-bg-2"
                            onclick="openVideoModal('Lumière Spa Experience', '2:15', 'Spa & Wellness', 'videos/spa-experience.mp4')">
                            <div class="vc-bg"></div>
                            <i class="ti ti-spa vc-icon-main"></i>
                            <div class="vc-play">
                                <div class="vc-play-btn"><i class="ti ti-player-play-filled"></i></div>
                            </div>
                            <span class="vc-duration">2:15</span>
                            <div class="vc-info">
                                <div class="vc-tag">Spa & Wellness</div>
                                <div class="vc-title">Lumière Spa Experience</div>
                                <div class="vc-sub">Terapi eksklusif kelas dunia</div>
                            </div>
                        </div>

                        <div class="video-card vc-bg-3"
                            onclick="openVideoModal('Rooftop Pool & Sunset View', '1:50', 'Rooftop Pool', 'videos/rooftop-pool.mp4')">
                            <div class="vc-bg"></div>
                            <i class="ti ti-pool vc-icon-main"></i>
                            <div class="vc-play">
                                <div class="vc-play-btn"><i class="ti ti-player-play-filled"></i></div>
                            </div>
                            <span class="vc-duration">1:50</span>
                            <div class="vc-info">
                                <div class="vc-tag">Rooftop Pool</div>
                                <div class="vc-title">Infinity Pool & Sunset View</div>
                                <div class="vc-sub">Pemandangan senja dari lantai 32</div>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- VIDEO MODAL -->
                <div class="video-modal" id="videoModal" onclick="closeVideoModal(event)">
                    <div class="modal-inner">
                        <button class="modal-close" onclick="closeVideoModal(null, true)"><i
                                class="ti ti-x"></i></button>
                        <div class="modal-video-wrap" id="modalVideoWrap">
                            <video id="modalVideo" controls>
                                <source src="" type="video/mp4">
                                Your browser does not support the video tag.
                            </video>
                        </div>
                        <div class="modal-title-bar">
                            <i class="ti ti-video"></i>
                            <span class="modal-title" id="modalCategory">—</span>
                            <span class="modal-meta" id="modalDuration">—</span>
                        </div>
                    </div>
                </div>

                <!-- FASILITAS CEPAT -->
                <div class="gold-line"></div>
                <p class="section-label">Fasilitas Unggulan</p>
                <div class="quick-facilities">
                    <div class="qf-card">
                        <div class="qf-icon"><i class="ti ti-pool"></i></div>
                        <div class="qf-info">
                            <div class="qf-name">Rooftop Pool</div>
                            <div class="qf-desc">Kolam renang infinity di lantai 32 dengan pemandangan kota 360°</div>
                        </div>
                    </div>
                    <div class="qf-card">
                        <div class="qf-icon"><i class="ti ti-spa"></i></div>
                        <div class="qf-info">
                            <div class="qf-name">Lumière Spa</div>
                            <div class="qf-desc">Terapi premium dengan produk organik pilihan dari seluruh dunia</div>
                        </div>
                    </div>
                    <div class="qf-card">
                        <div class="qf-icon"><i class="ti ti-tool-kitchen-2"></i></div>
                        <div class="qf-info">
                            <div class="qf-name">Fine Dining</div>
                            <div class="qf-desc">Restoran Le Jardin dengan chef bintang Michelin dari Paris</div>
                        </div>
                    </div>
                    <div class="qf-card">
                        <div class="qf-icon"><i class="ti ti-dumbbell"></i></div>
                        <div class="qf-info">
                            <div class="qf-name">Fitness Center</div>
                            <div class="qf-desc">Peralatan modern 24 jam dengan personal trainer bersertifikat</div>
                        </div>
                    </div>
                    <div class="qf-card">
                        <div class="qf-icon"><i class="ti ti-wifi"></i></div>
                        <div class="qf-info">
                            <div class="qf-name">Business Lounge</div>
                            <div class="qf-desc">Ruang kerja eksklusif dengan koneksi fiber 1 Gbps</div>
                        </div>
                    </div>
                    <div class="qf-card">
                        <div class="qf-icon"><i class="ti ti-car"></i></div>
                        <div class="qf-info">
                            <div class="qf-name">Valet & Limousine</div>
                            <div class="qf-desc">Layanan antar-jemput bandara dan valet 24 jam</div>
                        </div>
                    </div>
                </div>

                <!-- STATS -->
                <div class="stats-row">
                    <div class="stat-item">
                        <div class="stat-num">32</div>
                        <div class="stat-label">Lantai</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-num">186</div>
                        <div class="stat-label">Kamar & Suite</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-num">12</div>
                        <div class="stat-label">Restoran & Bar</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-num">98%</div>
                        <div class="stat-label">Kepuasan Tamu</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-num">24/7</div>
                        <div class="stat-label">Pelayanan</div>
                    </div>
                </div>

            </div>
        </section>

        <!-- FASILITAS SECTION -->
        <section id="fasilitas">
            <div class="container">
                <p class="section-label">Yang Kami Tawarkan</p>
                <h2 class="section-heading">Fasilitas Premium</h2>
                <div class="gold-line"></div>
                <p class="section-sub">Setiap sudut Grand Lumière dirancang untuk memberikan pengalaman terbaik, mulai
                    dari kenyamanan kamar hingga hiburan kelas dunia.</p>

                <div class="fac-grid">
                    <div class="fac-card">
                        <div class="fac-img" style="background:linear-gradient(135deg,#0f2a40,#061828);">
                            <div class="fac-img-inner"><i class="ti ti-pool"></i></div>
                            <span class="fac-img-label">Unggulan</span>
                        </div>
                        <div class="fac-body">
                            <div class="fac-title">Rooftop Infinity Pool</div>
                            <div class="fac-desc">Kolam renang sepanjang 40 meter di lantai 32 dengan pemandangan
                                cakrawala kota yang memukau. Tersedia pool bar dan sun deck eksklusif.</div>
                            <div class="fac-tags">
                                <span class="fac-tag">Buka 06.00–22.00</span>
                                <span class="fac-tag">Gratis untuk tamu</span>
                                <span class="fac-tag">Lantai 32</span>
                            </div>
                        </div>
                    </div>

                    <div class="fac-card">
                        <div class="fac-img" style="background:linear-gradient(135deg,#1a0f30,#0f0820);">
                            <div class="fac-img-inner"><i class="ti ti-spa"></i></div>
                        </div>
                        <div class="fac-body">
                            <div class="fac-title">Lumière Spa & Wellness</div>
                            <div class="fac-desc">Pusat perawatan tubuh dan pikiran dengan 12 ruang perawatan privat,
                                sauna, hammam, dan terapi air menggunakan bahan alami pilihan.</div>
                            <div class="fac-tags">
                                <span class="fac-tag">Reservasi diperlukan</span>
                                <span class="fac-tag">09.00–21.00</span>
                                <span class="fac-tag">Diskon 20% tamu hotel</span>
                            </div>
                        </div>
                    </div>

                    <div class="fac-card">
                        <div class="fac-img" style="background:linear-gradient(135deg,#1a2a10,#0a1808);">
                            <div class="fac-img-inner"><i class="ti ti-tool-kitchen-2"></i></div>
                            <span class="fac-img-label">Bintang Michelin</span>
                        </div>
                        <div class="fac-body">
                            <div class="fac-title">Le Jardin Restaurant</div>
                            <div class="fac-desc">Restoran fine dining dengan menu fusion Prancis-Asia oleh Chef
                                Guillaume Moreau. Tersedia ruang private dining untuk acara khusus.</div>
                            <div class="fac-tags">
                                <span class="fac-tag">Sarapan 06.30–10.30</span>
                                <span class="fac-tag">Makan malam 18.00–23.00</span>
                            </div>
                        </div>
                    </div>

                    <div class="fac-card">
                        <div class="fac-img" style="background:linear-gradient(135deg,#2a1a0a,#1a0e05);">
                            <div class="fac-img-inner"><i class="ti ti-dumbbell"></i></div>
                        </div>
                        <div class="fac-body">
                            <div class="fac-title">Fitness & Wellness Center</div>
                            <div class="fac-desc">Pusat kebugaran 24 jam seluas 800 m² dengan peralatan Technogym
                                terbaru, studio yoga, studio pilates, dan personal trainer bersertifikat internasional.
                            </div>
                            <div class="fac-tags">
                                <span class="fac-tag">24 Jam</span>
                                <span class="fac-tag">Personal trainer</span>
                                <span class="fac-tag">Gratis tamu</span>
                            </div>
                        </div>
                    </div>

                    <div class="fac-card">
                        <div class="fac-img" style="background:linear-gradient(135deg,#0a1e38,#051020);">
                            <div class="fac-img-inner"><i class="ti ti-confetti"></i></div>
                            <span class="fac-img-label">Event</span>
                        </div>
                        <div class="fac-body">
                            <div class="fac-title">Grand Ballroom</div>
                            <div class="fac-desc">Ballroom megah seluas 2.000 m² dengan kapasitas 500 tamu, sistem
                                audio-visual mutakhir, dan tim event profesional siap melayani segala kebutuhan acara
                                Anda.</div>
                            <div class="fac-tags">
                                <span class="fac-tag">500 kapasitas</span>
                                <span class="fac-tag">Pernikahan</span>
                                <span class="fac-tag">Konferensi</span>
                            </div>
                        </div>
                    </div>

                    <div class="fac-card">
                        <div class="fac-img" style="background:linear-gradient(135deg,#182030,#0a1020);">
                            <div class="fac-img-inner"><i class="ti ti-wifi"></i></div>
                        </div>
                        <div class="fac-body">
                            <div class="fac-title">Executive Business Lounge</div>
                            <div class="fac-desc">Ruang kerja eksklusif dengan akses fiber optic 1 Gbps, ruang meeting
                                privat, printing & scanning, dan concierge bisnis personal tersedia 18 jam sehari.</div>
                            <div class="fac-tags">
                                <span class="fac-tag">06.00–24.00</span>
                                <span class="fac-tag">Fiber 1 Gbps</span>
                                <span class="fac-tag">Khusus anggota</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- PEMESANAN SECTION -->
        <section id="pemesanan">
            <div class="container">
                <p class="section-label">Reservasi Kamar</p>
                <h2 class="section-heading">Buat Pemesanan</h2>
                <div class="gold-line"></div>
                <p class="section-sub">Pilih kamar impian Anda dan nikmati pengalaman menginap yang tak terlupakan
                    bersama Grand Lumière.</p>

                <div class="booking-wrap">

                    <div>
                        <div class="room-picker">
                            <p class="room-picker-title">Pilih Tipe Kamar</p>
                            <div class="room-list" id="roomList">

                                <div class="room-option selected" onclick="selectRoom(this, 'Deluxe Room', 1000000)">
                                    <div class="room-option-check"><i class="ti ti-check"></i></div>
                                    <div class="room-option-icon"><i class="ti ti-bed"></i></div>
                                    <div class="room-option-info">
                                        <div class="room-option-name">Deluxe Room</div>
                                        <div class="room-option-detail">City view · King bed · 42 m²</div>
                                    </div>
                                    <div class="room-option-price">Rp1.000.000<span
                                            style="font-size:11px;color:var(--text-dim)">/malam</span></div>
                                </div>

                                <div class="room-option" onclick="selectRoom(this, 'Junior Suite', 1500000)">
                                    <div class="room-option-check"></div>
                                    <div class="room-option-icon"><i class="ti ti-building"></i></div>
                                    <div class="room-option-info">
                                        <div class="room-option-name">Junior Suite</div>
                                        <div class="room-option-detail">Garden view · King bed · 68 m²</div>
                                    </div>
                                    <div class="room-option-price">Rp1.500.000<span
                                            style="font-size:11px;color:var(--text-dim)">/malam</span></div>
                                </div>

                                <div class="room-option" onclick="selectRoom(this, 'Deluxe Suite', 2500000)">
                                    <div class="room-option-check"></div>
                                    <div class="room-option-icon"><i class="ti ti-star"></i></div>
                                    <div class="room-option-info">
                                        <div class="room-option-name">Deluxe Suite</div>
                                        <div class="room-option-detail">Sea view · King bed + sofa · 95 m²</div>
                                    </div>
                                    <div class="room-option-price">Rp2.500.000<span
                                            style="font-size:11px;color:var(--text-dim)">/malam</span></div>
                                </div>
                                <div class="room-option" onclick="selectRoom(this, 'Executive Room', 1200000)">
    <div class="room-option-check"></div>
    <div class="room-option-icon"><i class="ti ti-briefcase"></i></div>
    <div class="room-option-info">
        <div class="room-option-name">Executive Room</div>
        <div class="room-option-detail">City view · Queen bed · 55 m² · Lantai 5</div>
    </div>
    <div class="room-option-price">Rp1.200.000<span
            style="font-size:11px;color:var(--text-dim)">/malam</span></div>
</div>

                                <div class="room-option" onclick="selectRoom(this, 'Presidential Suite', 5000000)">
                                    <div class="room-option-check"></div>
                                    <div class="room-option-icon"><i class="ti ti-crown"></i></div>
                                    <div class="room-option-info">
                                        <div class="room-option-name">Presidential Suite</div>
                                        <div class="room-option-detail">Panoramic view · 2 bedrooms · 140 m²</div>
                                    </div>
                                    <div class="room-option-price">Rp5.000.000<span
                                            style="font-size:11px;color:var(--text-dim)">/malam</span></div>
                                </div>

                            </div>
                        </div>

                        <div
                            style="background:var(--glass);border:0.5px solid var(--glass-border);border-radius:12px;padding:14px 16px;margin-top:1rem;">
                            <p
                                style="font-size:11px;color:var(--text-muted);letter-spacing:1.5px;text-transform:uppercase;margin-bottom:10px;">
                                Kebijakan Hotel</p>
                            <div style="display:flex;flex-direction:column;gap:8px;">
                                <div style="display:flex;align-items:center;gap:8px;font-size:12px;color:var(--text-dim);">
                                    <i class="ti ti-clock" style="color:var(--gold-dim);font-size:14px;"></i>
                                    Check-in 14.00 · Check-out 12.00
                                </div>
                                <div style="display:flex;align-items:center;gap:8px;font-size:12px;color:var(--text-dim);">
                                    <i class="ti ti-calendar-x" style="color:var(--gold-dim);font-size:14px;"></i>
                                    Pembatalan gratis 48 jam sebelum check-in
                                </div>
                                <div style="display:flex;align-items:center;gap:8px;font-size:12px;color:var(--text-dim);">
                                    <i class="ti ti-coffee" style="color:var(--gold-dim);font-size:14px;"></i>
                                    Sarapan termasuk untuk semua kamar
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- RIGHT: BOOKING FORM -->
                    <div class="booking-card">
                        <div class="booking-card-title">Detail Pemesanan</div>
                        <div class="booking-card-sub">Isi informasi di bawah</div>

                        <div class="form-field">
                            <label>Nama Lengkap</label>
                            <div class="input-icon-wrap">
                                <i class="ti ti-user"></i>
                                <input type="text" placeholder="John Doe" id="guestName">
                            </div>
                        </div>

                        <div class="form-field">
                            <label>Email</label>
                            <div class="input-icon-wrap">
                                <i class="ti ti-mail"></i>
                                <input type="email" placeholder="your@email.com" id="guestEmail">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-field">
                                <label>Check-in</label>
                                <input type="date" id="checkIn" onchange="updatePriceByDate()">
                            </div>
                            <div class="form-field">
                                <label>Check-out</label>
                                <input type="date" id="checkOut" onchange="calcTotal()">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-field">
                                <label>Tamu</label>
                                <select id="guests">
                                    <option>1 Tamu</option>
                                    <option selected>2 Tamu</option>
                                    <option>3 Tamu</option>
                                    <option>4 Tamu</option>
                                </select>
                            </div>
                            <div class="form-field">
                                <label>Permintaan Khusus</label>
                                <select id="specialRequest">
                                    <option value="">Tidak ada</option>
                                    <option value="Late check-out">Late check-out</option>
                                    <option value="Early check-in">Early check-in</option>
                                    <option value="Honeymoon setup">Honeymoon setup</option>
                                </select>
                            </div>
                        </div>

                        <div class="booking-summary" id="bookingSummary">
                            <div class="booking-summary-row"><span id="sumRoom">Deluxe Room</span><span
                                    id="sumPrice">Rp1.000.000/malam</span></div>
                            <div class="booking-summary-row"><span id="sumNights">1 malam</span><span
                                    id="sumSub">Rp1.000.000</span></div>
                            <div class="booking-summary-row"><span>Pajak & layanan (15%)</span><span
                                    id="sumTax">Rp150.000</span></div>
                            <div class="booking-summary-row"><span>Total</span><span id="sumTotal">Rp1.150.000</span>
                            </div>
                        </div>

                        <button class="btn-book" onclick="submitBooking()">
                            <i class="ti ti-calendar-check"
                                style="font-size:14px;vertical-align:-1px;margin-right:6px;"></i>
                            Pesan Sekarang
                        </button>

                        <div class="booking-note">
                            <i class="ti ti-shield-check"></i>
                            Pembayaran aman & terenkripsi
                        </div>
                    </div>

                </div>
            </div>
        </section>

        <footer>&copy; 2025 Grand Lumière Hotel &nbsp;·&nbsp; Luxury Collection &nbsp;·&nbsp; All Rights Reserved
        </footer>

    </div>

    <!-- TOAST -->
    <div class="toast" id="toast">
        <i class="ti ti-circle-check"></i>
        <div>
            <div class="toast-msg">Pemesanan berhasil!</div>
            <div class="toast-sub" id="toastSub">Tim kami akan segera menghubungi Anda.</div>
        </div>
    </div>

    <script>
        // STARS
        const starsEl = document.getElementById('stars');
        for (let i = 0; i < 90; i++) {
            const s = document.createElement('div');
            s.className = 'star';
            s.style.cssText = `left:${Math.random() * 100}%;top:${Math.random() * 100}%;--d:${2 + Math.random() * 5}s;animation-delay:${Math.random() * 5}s;opacity:${0.05 + Math.random() * 0.4};width:${Math.random() > .75 ? 3 : 2}px;height:${Math.random() > .75 ? 3 : 2}px;`;
            starsEl.appendChild(s);
        }

        // SECTION SWITCH
        function showSection(id, el) {
            document.querySelectorAll('section').forEach(s => s.classList.remove('active-section'));
            document.getElementById(id).classList.add('active-section');
            document.querySelectorAll('.nav-links a').forEach(a => a.classList.remove('active'));
            if (el) el.classList.add('active');
            window.scrollTo({ top: 0, behavior: 'smooth' });
            return false;
        }

        // SLIDER
        let slideIndex = 0;
        const cardWidth = 316;

        function slideMove(dir) {
            const track = document.getElementById('sliderTrack');
            const total = track.children.length;
            const visible = Math.floor(track.parentElement.offsetWidth / cardWidth);
            const max = total - visible;
            slideIndex = Math.max(0, Math.min(slideIndex + dir, max));
            track.style.transform = `translateX(-${slideIndex * cardWidth}px)`;
        }

        // VIDEO MODAL
        function openVideoModal(title, duration, category, videoSrc) {
            const modal = document.getElementById('videoModal');
            const video = document.getElementById('modalVideo');
            const source = video.querySelector('source');
            const modalCategory = document.getElementById('modalCategory');
            const modalDuration = document.getElementById('modalDuration');

            source.src = videoSrc;
            video.load();
            modalCategory.textContent = category;
            modalDuration.textContent = duration;
            modal.classList.add('open');
            document.body.style.overflow = 'hidden';
            video.play().catch(e => console.log('Autoplay prevented:', e));
        }

        function closeVideoModal(e, force) {
            const modal = document.getElementById('videoModal');
            const video = document.getElementById('modalVideo');

            if (force || (e && e.target === modal) || (e && e.target.classList && e.target.classList.contains('modal-close'))) {
                video.pause();
                video.currentTime = 0;
                modal.classList.remove('open');
                document.body.style.overflow = '';
            }
        }

        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') closeVideoModal(null, true);
        });

        // HARGA KAMAR PER MALAM (BASE PRICE)
        let basePrices = {
            'Deluxe Room': 1000000,
            'Junior Suite': 1500000,
            'Deluxe Suite': 2500000,
            'Executive Room': 1200000,
            'Presidential Suite': 5000000
        };

        // HARGA WEEKEND (Jumat & Sabtu) - 20% lebih mahal
        let weekendMultiplier = 1.2;
        
        // HARGA HIGH SEASON (Juni, Juli, Desember) - 30% lebih mahal
        let highSeasonMultiplier = 1.3;

        let selectedPrice = 1000000;
        let selectedRoomName = 'Deluxe Room';
        let currentPricePerNight = 1000000;

        // Fungsi untuk cek apakah tanggal weekend (Jumat=5, Sabtu=6)
        function isWeekend(date) {
            const day = new Date(date).getDay();
            return (day === 5 || day === 6); // Jumat atau Sabtu
        }

        // Fungsi untuk cek apakah bulan high season (Juni=5, Juli=6, Desember=11)
        function isHighSeason(date) {
            const month = new Date(date).getMonth();
            return (month === 5 || month === 6 || month === 11); // Juni, Juli, Desember
        }

        // Fungsi untuk menghitung harga per malam berdasarkan tanggal
        function getPriceForDate(basePrice, date) {
            let price = basePrice;
            
            if (isWeekend(date)) {
                price = Math.round(price * weekendMultiplier);
            }
            
            if (isHighSeason(date)) {
                price = Math.round(price * highSeasonMultiplier);
            }
            
            return price;
        }

        // Fungsi untuk update harga berdasarkan tanggal check-in
        function updatePriceByDate() {
            const checkInDate = document.getElementById('checkIn').value;
            const checkOutDate = document.getElementById('checkOut').value;
            
            if (checkInDate) {
                currentPricePerNight = getPriceForDate(basePrices[selectedRoomName], checkInDate);
                selectedPrice = currentPricePerNight;
                
                // Update tampilan harga di room picker
                document.querySelectorAll('.room-option').forEach(room => {
                    const roomName = room.querySelector('.room-option-name').textContent;
                    if (roomName === selectedRoomName) {
                        const priceDisplay = room.querySelector('.room-option-price');
                        priceDisplay.innerHTML = formatRupiah(currentPricePerNight) + '<span style="font-size:11px;color:var(--text-dim)">/malam</span>';
                    }
                });
                
                // Update summary price display
                document.getElementById('sumPrice').textContent = formatRupiah(currentPricePerNight) + '/malam';
            }
            
            calcTotal();
            
            if (checkInDate && checkOutDate) {
                let message = '';
                if (isWeekend(checkInDate)) message += 'Weekend (20% lebih mahal) • ';
                if (isHighSeason(checkInDate)) message += 'High Season (30% lebih mahal)';
                
                if (message) {
                    const toast = document.getElementById('toast');
                    document.getElementById('toastSub').textContent = message;
                    toast.classList.add('show');
                    setTimeout(() => toast.classList.remove('show'), 3000);
                }
            }
        }

        // Format Rupiah
        function formatRupiah(angka) {
            return 'Rp' + angka.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        }

        // ROOM SELECTION
        function selectRoom(el, name, basePrice) {
            document.querySelectorAll('.room-option').forEach(r => {
                r.classList.remove('selected');
                r.querySelector('.room-option-check').innerHTML = '';
            });
            el.classList.add('selected');
            el.querySelector('.room-option-check').innerHTML = '<i class="ti ti-check"></i>';
            
            selectedRoomName = name;
            basePrices[name] = basePrice;
            
            // Update harga berdasarkan tanggal check-in
            const checkInDate = document.getElementById('checkIn').value;
            if (checkInDate) {
                currentPricePerNight = getPriceForDate(basePrice, checkInDate);
            } else {
                currentPricePerNight = basePrice;
            }
            selectedPrice = currentPricePerNight;
            
            calcTotal();
            updatePriceByDate();
        }

        // CALC TOTAL
        function calcTotal() {
            const ci = document.getElementById('checkIn').value;
            const co = document.getElementById('checkOut').value;
            let nights = 1;
            if (ci && co) {
                const diff = (new Date(co) - new Date(ci)) / (1000 * 60 * 60 * 24);
                if (diff > 0) nights = diff;
            }
            
            const sub = currentPricePerNight * nights;
            const tax = Math.round(sub * 0.15);
            const total = sub + tax;

            document.getElementById('sumRoom').textContent = selectedRoomName;
            document.getElementById('sumPrice').textContent = formatRupiah(currentPricePerNight) + '/malam';
            document.getElementById('sumNights').textContent = nights + ' malam';
            document.getElementById('sumSub').textContent = formatRupiah(sub);
            document.getElementById('sumTax').textContent = formatRupiah(tax);
            document.getElementById('sumTotal').textContent = formatRupiah(total);
        }

        const today = new Date();
        const tomorrow = new Date(today);
        tomorrow.setDate(tomorrow.getDate() + 1);
        document.getElementById('checkIn').value = today.toISOString().split('T')[0];
        document.getElementById('checkOut').value = tomorrow.toISOString().split('T')[0];
        
        // Set initial price berdasarkan tanggal hari ini
        currentPricePerNight = getPriceForDate(1000000, today.toISOString().split('T')[0]);
        selectedPrice = currentPricePerNight;
        calcTotal();

        // SHAKE FUNCTION
        function shake(id) {
            const el = document.getElementById(id);
            if (!el) return;
            el.style.borderColor = 'rgba(212,80,80,0.7)';
            el.style.animation = 'shake .35s ease';
            setTimeout(() => {
                el.style.borderColor = '';
                el.style.animation = '';
                el.focus();
            }, 400);
        }

        // SUBMIT BOOKING WITH MIDTRANS PAYMENT
        async function submitBooking() {
            const name = document.getElementById('guestName').value.trim();
            const email = document.getElementById('guestEmail').value.trim();
            const ci = document.getElementById('checkIn').value;
            const co = document.getElementById('checkOut').value;

            if (!name) {
                shake('guestName');
                return;
            }
            if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                shake('guestEmail');
                return;
            }
            if (!ci || !co || co <= ci) {
                shake('checkIn');
                shake('checkOut');
                return;
            }

            const diff = (new Date(co) - new Date(ci)) / (1000 * 60 * 60 * 24);
            const nights = diff > 0 ? Math.round(diff) : 1;
            const subtotal = currentPricePerNight * nights;
            const pajak = Math.round(subtotal * 0.15);
            const total = subtotal + pajak;
            const guestStr = document.getElementById('guests').value;
            const jumlah_tamu = parseInt(guestStr) || 2;
            const permintaan = document.getElementById('specialRequest').value;

            const btn = document.querySelector('.btn-book');
            btn.disabled = true;
            btn.innerHTML = '<i class="ti ti-loader" style="font-size:14px;vertical-align:-1px;margin-right:6px;animation:spin 1s linear infinite"></i> Memproses...';

            const fd = new FormData();
            fd.append('nama_tamu', name);
            fd.append('email_tamu', email);
            fd.append('tipe_kamar', selectedRoomName);
            fd.append('harga_per_malam', currentPricePerNight);
            fd.append('check_in', ci);
            fd.append('check_out', co);
            fd.append('jumlah_malam', nights);
            fd.append('jumlah_tamu', jumlah_tamu);
            fd.append('subtotal', subtotal);
            fd.append('pajak', pajak);
            fd.append('total', total);
            fd.append('permintaan_khusus', permintaan);

            try {
                const res = await fetch('booking/proses_booking.php', { method: 'POST', body: fd });
                const data = await res.json();

                btn.disabled = false;
                btn.innerHTML = '<i class="ti ti-calendar-check" style="font-size:14px;vertical-align:-1px;margin-right:6px;"></i> Pesan Sekarang';

                if (data.success && data.snap_token) {
                    window.location.href = 'pembayaran/pembayaran.php?snap_token=' + data.snap_token + '&kode_booking=' + data.kode_booking;
                } else if (data.success) {
                    const toast = document.getElementById('toast');
                    document.getElementById('toastSub').textContent = `${selectedRoomName} · ${nights} malam · Kode: ${data.kode_booking}`;
                    toast.classList.add('show');
                    setTimeout(() => toast.classList.remove('show'), 6000);
                    document.getElementById('guestName').value = '';
                    document.getElementById('guestEmail').value = '';
                    document.getElementById('specialRequest').value = '';
                } else {
                    alert('Gagal: ' + (data.message || 'Terjadi kesalahan'));
                }
            } catch (err) {
                btn.disabled = false;
                btn.innerHTML = '<i class="ti ti-calendar-check" style="font-size:14px;vertical-align:-1px;margin-right:6px;"></i> Pesan Sekarang';
                alert('Koneksi gagal. Periksa server PHP.');
                console.error(err);
            }
        }
        // ── PROFILE DROPDOWN ──
        function toggleProfile(e) {
            e.stopPropagation();
            document.getElementById('riwayatDropdown').classList.remove('open');
            document.getElementById('profileDropdown').classList.toggle('open');
        }
        function toggleRiwayat(e) {
            e.stopPropagation();
            document.getElementById('profileDropdown').classList.remove('open');
            document.getElementById('riwayatDropdown').classList.toggle('open');
        }
        document.addEventListener('click', function() {
            document.getElementById('profileDropdown').classList.remove('open');
            document.getElementById('riwayatDropdown').classList.remove('open');
        });
    </script>

</body>

</html>
