<?php
header('Content-Type: text/html; charset=UTF-8');

if (is_file(__DIR__ . '/../../core/access_control.php')) {
    require_once __DIR__ . '/../../core/access_control.php';
    startowa_require_admin_panel();
} else {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
        header('Location: ../login.php');
        exit();
    }
    $fallbackRole = strtolower(trim((string) ($_SESSION['rola'] ?? 'user')));
    if (!in_array($fallbackRole, ['admin', 'owner'], true)) {
        header('Location: ../index.php');
        exit();
    }
}

$userName = $_SESSION['imie'] ?? 'Uzytkownik';
$userRole = $_SESSION['rola'] ?? 'user';
$userEmail = $_SESSION['email'] ?? (strtolower(preg_replace('/\s+/', '.', $userName)) . '@local');

$access = function_exists('startowa_current_user_access') ? startowa_current_user_access() : ['apps' => ['dashboard', 'admin_panel']];
$allowedApps = (array) ($access['apps'] ?? []);
$appLinks = function_exists('startowa_app_catalog') ? startowa_app_catalog() : [
    'dashboard' => ['label' => 'Server Hub', 'url' => '/public/index.php', 'icon' => '🏠'],
    'admin_panel' => ['label' => 'Panel Admina', 'url' => '/public/admin/index.php', 'icon' => '🛡'],
    'neuronetix' => ['label' => 'Neuronetix', 'url' => '/neuronetix/index.php', 'icon' => '🧠'],
];
$visibleApps = [];
foreach ($appLinks as $appKey => $meta) {
    if (in_array($appKey, $allowedApps, true)) {
        $visibleApps[$appKey] = $meta;
    }
}
?>
<!doctype html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Server Hub</title>
    <link rel="icon" type="image/png" href="../assets/img/kossmo.png">
    <style>
        * { box-sizing: border-box; }

        :root {
            --bg: #0b1220;
            --bg2: #111b30;
            --panel: rgba(255, 255, 255, 0.06);
            --panel-2: rgba(255, 255, 255, 0.09);
            --line: rgba(255, 255, 255, 0.16);
            --txt: #e5e7eb;
            --muted: #94a3b8;
            --acc: #38bdf8;
            --acc2: #34d399;
            --warn: #fda4af;
        }

        html, body {
            margin: 0;
            height: 100%;
            font-family: Segoe UI, Tahoma, sans-serif;
            color: var(--txt);
            background: radial-gradient(circle at 0% 0%, #1e293b, #0b1220 45%, #070d18 100%);
            overflow: hidden;
        }

        .bg {
            position: fixed;
            inset: 0;
            pointer-events: none;
        }

        .blob {
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            opacity: 0.18;
            animation: float 14s ease-in-out infinite;
        }

        .b1 { width: 320px; height: 320px; background: #38bdf8; top: -60px; left: -40px; }
        .b2 { width: 280px; height: 280px; background: #34d399; right: -60px; top: 90px; animation-delay: -5s; }
        .b3 { width: 380px; height: 380px; background: #60a5fa; left: 30%; bottom: -200px; animation-delay: -9s; }

        @keyframes float {
            0%, 100% { transform: translate(0, 0) scale(1); }
            50% { transform: translate(-15px, 28px) scale(1.08); }
        }

        .app {
            position: relative;
            z-index: 1;
            display: grid;
            grid-template-columns: 340px 1fr;
            height: 100vh;
            transition: grid-template-columns 0.2s ease;
        }

        .app.collapsed {
            grid-template-columns: 64px 1fr;
        }

        .sidebar {
            border-right: 1px solid var(--line);
            background: var(--panel);
            backdrop-filter: blur(10px);
            padding: 12px;
            display: grid;
            grid-template-rows: auto auto 1fr auto;
            gap: 10px;
            min-height: 0;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .logo {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            display: grid;
            place-items: center;
            background: linear-gradient(130deg, var(--acc), var(--acc2));
            color: #062333;
            font-weight: 800;
        }

        .brand-name {
            font-size: 20px;
            font-weight: 800;
            letter-spacing: 0.3px;
        }

        .rail-nav {
            display: none;
            gap: 8px;
            align-content: start;
            justify-items: center;
            padding-top: 8px;
        }

        .rail-btn {
            width: 40px;
            height: 40px;
            border-radius: 11px;
            border: 1px solid var(--line);
            background: rgba(255, 255, 255, 0.04);
            color: var(--txt);
            display: grid;
            place-items: center;
            cursor: pointer;
            font-size: 16px;
        }

        .rail-btn:hover {
            border-color: var(--acc);
            background: rgba(56, 189, 248, 0.14);
        }

        .collapse-btn {
            border: 1px solid var(--line);
            background: var(--panel);
            color: var(--txt);
            border-radius: 9px;
            padding: 8px;
            cursor: pointer;
            width: 28px;
            height: 44px;
            position: absolute;
            left: 340px;
            top: 14px;
            transform: none;
            z-index: 5;
            border-left: none;
            border-top-left-radius: 0;
            border-bottom-left-radius: 0;
        }

        .profile-trigger {
            border: 1px solid var(--line);
            border-radius: 10px;
            background: var(--panel);
            padding: 6px 8px;
            display: flex;
            gap: 10px;
            align-items: center;
            cursor: pointer;
            width: 100%;
            justify-content: space-between;
        }

        .profile-main {
            display: flex;
            align-items: center;
            gap: 8px;
            min-width: 0;
        }

        .profile-meta {
            min-width: 0;
        }

        .profile-name,
        .profile-email {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .profile-name {
            font-size: 13px;
            font-weight: 600;
        }

        .profile-email {
            font-size: 11px;
            color: var(--muted);
        }

        .settings-btn {
            border: 1px solid var(--line);
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.04);
            color: var(--txt);
            width: 30px;
            height: 30px;
            cursor: pointer;
            display: grid;
            place-items: center;
            flex: 0 0 auto;
        }

        .profile-trigger.compact {
            max-width: 60px;
            justify-content: center;
            padding: 6px;
        }

        .avatar {
            width: 34px;
            height: 34px;
            border-radius: 10px;
            display: grid;
            place-items: center;
            background: linear-gradient(130deg, var(--acc), var(--acc2));
            color: #062333;
            font-weight: 700;
        }

        .mini-preview {
            border: 1px solid var(--line);
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.04);
            padding: 10px;
            font-size: 12px;
            color: var(--muted);
            max-height: 100%;
            overflow: auto;
        }

        .side-mid {
            min-height: 0;
            overflow: auto;
            display: grid;
            align-content: start;
            gap: 8px;
        }

        .pins {
            border: 1px solid var(--line);
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.03);
            padding: 8px;
            display: grid;
            gap: 6px;
        }

        .pins h4 {
            margin: 0;
            font-size: 12px;
            color: var(--muted);
            font-weight: 600;
        }

        .recent {
            border: 1px solid var(--line);
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.03);
            padding: 8px;
            display: grid;
            gap: 6px;
        }

        .recent h4 {
            margin: 0;
            font-size: 12px;
            color: var(--muted);
            font-weight: 600;
        }

        .recent-item {
            border: 1px solid var(--line);
            border-radius: 8px;
            background: var(--panel);
            color: var(--txt);
            padding: 6px 8px;
            font-size: 12px;
            text-align: left;
            cursor: pointer;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .recent-item:hover {
            border-color: var(--acc);
        }

        .tasks {
            border: 1px solid var(--line);
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.03);
            padding: 8px;
            display: grid;
            gap: 6px;
        }

        .tasks h4 {
            margin: 0;
            font-size: 12px;
            color: var(--muted);
            font-weight: 600;
        }

        .task-item {
            border: 1px solid var(--line);
            border-radius: 8px;
            background: var(--panel);
            color: var(--txt);
            padding: 6px 8px;
            font-size: 12px;
            text-align: left;
            cursor: pointer;
        }

        .task-item:hover {
            border-color: var(--acc);
        }

        .task-actions {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }

        .task-actions .btn {
            padding: 6px 8px;
            font-size: 12px;
        }

        .pin-item {
            border: 1px solid var(--line);
            border-radius: 8px;
            background: var(--panel);
            color: var(--txt);
            padding: 6px 8px;
            font-size: 12px;
            text-align: left;
            cursor: pointer;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .pin-item:hover {
            border-color: var(--acc);
        }

        .side-foot {
            display: grid;
            gap: 8px;
        }

        .user-menu {
            border: 1px solid var(--line);
            border-radius: 10px;
            padding: 8px;
            background: rgba(255, 255, 255, 0.03);
            display: none;
            gap: 8px;
        }

        .user-menu.open {
            display: grid;
        }

        .btn {
            border: 1px solid var(--line);
            border-radius: 10px;
            background: var(--panel);
            color: var(--txt);
            padding: 9px 11px;
            cursor: pointer;
        }

        .btn:hover { border-color: var(--acc); }
        .btn.primary { border: none; background: linear-gradient(120deg, var(--acc), var(--acc2)); color: #052838; font-weight: 700; }
        .btn.warn { border-color: rgba(253, 164, 175, 0.55); color: #fecdd3; }
        .btn.icon {
            width: 44px;
            min-width: 44px;
            min-height: 40px;
            padding: 0;
            display: grid;
            place-items: center;
            font-size: 18px;
        }

        .btn.icon.root {
            width: 48px;
            min-width: 48px;
            min-height: 42px;
            font-size: 17px;
        }

        .main {
            display: grid;
            grid-template-rows: auto auto 1fr auto;
            min-height: 0;
        }

        .main.dashboard-mode .explorer-only {
            display: none !important;
        }

        .main.explorer-mode .dashboard-view {
            display: none !important;
        }

        .dashboard-view {
            padding: 12px 14px;
            display: grid;
            gap: 10px;
            align-content: start;
            overflow: auto;
        }

        .dashboard-head {
            border: 1px solid var(--line);
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.03);
            padding: 12px;
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 10px;
        }

        .dashboard-card {
            border: 1px solid var(--line);
            border-radius: 12px;
            background: rgba(8, 17, 35, 0.85);
            padding: 12px;
            display: grid;
            gap: 8px;
        }

        .dashboard-card h3 {
            margin: 0;
            font-size: 18px;
        }

        .dashboard-card p {
            margin: 0;
            color: var(--muted);
            font-size: 13px;
        }

        .top {
            border-bottom: 1px solid var(--line);
            padding: 12px 14px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 8px;
            background: rgba(255, 255, 255, 0.03);
        }

        .title { font-size: 20px; font-weight: 800; }
        .sub { font-size: 12px; color: var(--muted); }

        .top-right {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        .app-switch {
            position: relative;
        }

        .app-switch-btn {
            border: 1px solid var(--line);
            border-radius: 10px;
            background: var(--panel);
            color: var(--txt);
            padding: 8px 12px;
            min-height: 38px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }

        .app-switch-btn:hover {
            border-color: var(--acc);
        }

        .app-switch-menu {
            position: absolute;
            right: 0;
            top: calc(100% + 8px);
            min-width: 240px;
            border: 1px solid var(--line);
            border-radius: 12px;
            background: rgba(8,17,35,0.98);
            box-shadow: 0 12px 28px rgba(0,0,0,.35);
            padding: 8px;
            display: none;
            z-index: 35;
        }

        .app-switch.open .app-switch-menu {
            display: grid;
            gap: 6px;
        }

        .app-switch-item {
            border: 1px solid var(--line);
            border-radius: 10px;
            background: rgba(255,255,255,0.02);
            color: var(--txt);
            text-decoration: none;
            padding: 8px 10px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
        }

        .app-switch-item:hover {
            border-color: var(--acc);
            background: rgba(56,189,248,0.12);
        }

        .panel-nav {
            border: 1px solid var(--line);
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.03);
            padding: 6px;
            display: grid;
            gap: 4px;
        }

        .panel-nav h4 {
            margin: 0;
            font-size: 12px;
            color: var(--muted);
            font-weight: 600;
        }

        .panel-nav-btn {
            border: 1px solid var(--line);
            border-radius: 7px;
            background: var(--panel);
            color: var(--txt);
            padding: 6px 8px;
            font-size: 11px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
            text-align: left;
            min-height: 34px;
        }

        .panel-nav-btn:hover {
            border-color: var(--acc);
            background: rgba(56, 189, 248, 0.12);
        }

        .panel-nav-btn.active {
            border-color: rgba(56, 189, 248, 0.75);
            background: rgba(56, 189, 248, 0.2);
        }

        .panel-nav-icon {
            width: 16px;
            min-width: 16px;
            text-align: center;
            font-size: 12px;
        }

        .admin-panel-btn {
            font-size: 12px;
            padding: 7px 10px;
        }

        .tasks-overlay {
            position: fixed;
            inset: 0;
            background: rgba(2, 7, 18, 0.62);
            backdrop-filter: blur(2px);
            z-index: 1180;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 18px;
        }

        .tasks-overlay.open {
            display: flex;
        }

        .tasks-panel {
            width: min(640px, 96vw);
            border: 1px solid var(--line);
            border-radius: 14px;
            background: rgba(8, 17, 35, 0.98);
            box-shadow: 0 24px 64px rgba(2, 6, 23, 0.55);
            overflow: hidden;
        }

        .tasks-panel-head {
            height: 52px;
            border-bottom: 1px solid var(--line);
            padding: 0 12px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: rgba(255,255,255,0.04);
        }

        .tasks-panel-body {
            padding: 12px;
            display: grid;
            gap: 10px;
        }

        .mobile-tools-toggle {
            display: none;
        }

        .top-tools {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            padding: 10px 14px;
            border-bottom: 1px solid var(--line);
            background: rgba(255, 255, 255, 0.02);
            flex-wrap: wrap;
        }

        .top-tools-group {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            align-items: center;
        }

        .tool-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            min-height: 36px;
            padding: 8px 12px;
            border: 1px solid var(--line);
            border-radius: 999px;
            background: rgba(255,255,255,0.04);
            color: var(--txt);
            cursor: pointer;
            font-size: 13px;
        }

        .tool-pill:hover { border-color: var(--acc); }

        .tool-pill.icon-only {
            width: 36px;
            min-width: 36px;
            justify-content: center;
            padding: 0;
            font-size: 15px;
        }

        /* ===== Admin FAB ===== */
        .admin-fab {
            position: fixed;
            bottom: 52px;
            right: 22px;
            z-index: 28;
        }

        .fab-main {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            border: none;
            background: linear-gradient(135deg, var(--acc), var(--acc2));
            color: #042233;
            font-size: 22px;
            cursor: pointer;
            box-shadow: 0 4px 24px rgba(56,189,248,0.45);
            display: grid;
            place-items: center;
            transition: transform 0.35s cubic-bezier(0.4,0,0.2,1), box-shadow 0.3s;
            position: relative;
            z-index: 2;
            outline: none;
        }

        .admin-fab.open .fab-main,
        .fab-main:hover {
            transform: rotate(45deg) scale(1.08);
            box-shadow: 0 6px 32px rgba(56,189,248,0.65);
        }

        .fab-item {
            position: absolute;
            bottom: 0;
            right: 0;
            width: 44px;
            height: 44px;
            border-radius: 50%;
            border: 1px solid var(--line);
            background: rgba(11, 18, 32, 0.92);
            color: var(--txt);
            font-size: 18px;
            cursor: pointer;
            display: grid;
            place-items: center;
            transform: translate(0, 0) scale(0);
            opacity: 0;
            transition: transform 0.35s cubic-bezier(0.4,0,0.2,1), opacity 0.25s ease, border-color 0.2s, background 0.2s;
            backdrop-filter: blur(12px);
            box-shadow: 0 2px 12px rgba(0,0,0,0.4);
            z-index: 1;
            outline: none;
        }

        .fab-item:hover { border-color: var(--acc); background: rgba(56,189,248,0.14); }
        .fab-item.fab-danger:hover { border-color: var(--warn); background: rgba(253,164,175,0.12); }

        .admin-fab.open .fi-1  { transform: translate(0px, -62px) scale(1); opacity: 1; transition-delay: 0.00s; }
        .admin-fab.open .fi-2  { transform: translate(0px, -116px) scale(1); opacity: 1; transition-delay: 0.03s; }
        .admin-fab.open .fi-3  { transform: translate(0px, -170px) scale(1); opacity: 1; transition-delay: 0.06s; }
        .admin-fab.open .fi-4  { transform: translate(-54px, -62px) scale(1); opacity: 1; transition-delay: 0.09s; }
        .admin-fab.open .fi-5  { transform: translate(-54px, -116px) scale(1); opacity: 1; transition-delay: 0.12s; }
        .admin-fab.open .fi-6  { transform: translate(-54px, -170px) scale(1); opacity: 1; transition-delay: 0.15s; }

        .fab-item::after {
            content: attr(title);
            position: absolute;
            bottom: calc(100% + 8px);
            left: 50%;
            transform: translateX(-50%);
            white-space: nowrap;
            background: rgba(7, 13, 24, 0.92);
            border: 1px solid var(--line);
            border-radius: 8px;
            padding: 4px 10px;
            font-size: 11px;
            color: var(--txt);
            pointer-events: none;
            opacity: 0;
            transition: opacity 0.15s;
            backdrop-filter: blur(8px);
        }
        .fab-item:hover::after { opacity: 1; }

        /* Search inline — floating overlay */
        .search-inline {
            display: none;
            align-items: center;
            gap: 6px;
            position: fixed;
            top: 70px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(7, 13, 24, 0.95);
            border: 1px solid var(--line);
            border-radius: 10px;
            padding: 8px 12px;
            z-index: 16;
            backdrop-filter: blur(12px);
            box-shadow: 0 4px 20px rgba(0,0,0,0.5);
        }
        .search-inline.open { display: flex; }
        .search-inline input {
            border: 1px solid var(--line);
            border-radius: 8px;
            background: rgba(0, 0, 0, 0.22);
            color: var(--txt);
            padding: 8px 10px;
            width: min(380px, 60vw);
            outline: none;
        }

        .crumbs {
            border-bottom: 1px solid var(--line);
            padding: 9px 14px;
            font-size: 12px;
            color: #bfdbfe;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .list {
            min-height: 0;
            overflow: auto;
            padding: 8px 14px 14px;
        }

        .row {
            display: grid;
            grid-template-columns: 36px minmax(260px, 1fr) 120px 180px;
            gap: 10px;
            align-items: center;
            border: 1px solid transparent;
            border-radius: 10px;
            padding: 8px 10px;
            cursor: pointer;
        }

        .row:hover { border-color: var(--line); background: rgba(255, 255, 255, 0.05); }
        .row.selected { border-color: var(--acc); background: rgba(56, 189, 248, 0.14); }
        .meta { font-size: 12px; color: var(--muted); text-align: right; }

        .preview-pane {
            display: none;
            margin: 0 14px 12px;
            border: 1px solid var(--line);
            border-radius: 10px;
            overflow: hidden;
            background: rgba(0, 0, 0, 0.35);
        }

        .preview-pane.open { display: block; }

        .preview-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 10px;
            border-bottom: 1px solid var(--line);
            background: rgba(255, 255, 255, 0.04);
            font-size: 13px;
        }

        .preview-body {
            max-height: 320px;
            overflow: auto;
            padding: 10px;
            background: #060b14;
        }

        .preview-body img,
        .preview-body iframe {
            width: 100%;
            border: none;
            border-radius: 8px;
            min-height: 260px;
            background: #fff;
        }

        .preview-body .md {
            line-height: 1.6;
            color: #dbeafe;
        }

        .preview-body .md h1,
        .preview-body .md h2,
        .preview-body .md h3 { color: #a5f3fc; }

        .terminal-window {
            position: fixed;
            right: 18px;
            bottom: 56px;
            width: min(720px, calc(100vw - 24px));
            height: 330px;
            border: 1px solid var(--line);
            border-radius: 12px;
            background: rgba(3, 9, 20, 0.96);
            box-shadow: 0 20px 45px rgba(0, 0, 0, 0.45);
            z-index: 30;
            display: none;
            overflow: hidden;
            resize: both;
            min-width: 420px;
            min-height: 220px;
            max-width: calc(100vw - 10px);
            max-height: calc(100vh - 60px);
        }

        .terminal-window.open {
            display: flex;
            flex-direction: column;
        }

        .terminal-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            padding: 8px 10px;
            border-bottom: 1px solid var(--line);
            background: rgba(255, 255, 255, 0.05);
            cursor: move;
            user-select: none;
        }

        .terminal-actions {
            display: flex;
            gap: 6px;
        }

        .terminal-mini-btn {
            border: 1px solid var(--line);
            background: var(--panel);
            color: var(--txt);
            border-radius: 7px;
            padding: 4px 8px;
            cursor: pointer;
            font-size: 12px;
        }

        .term-out {
            flex: 1;
            min-height: 120px;
            overflow: auto;
            padding: 9px 12px;
            font: 12px/1.5 Consolas, monospace;
            color: #86efac;
            white-space: pre-wrap;
            word-break: break-word;
        }

        .term-in {
            border-top: 1px solid var(--line);
            display: flex;
            gap: 8px;
            padding: 8px 10px;
        }

        .term-input {
            flex: 1;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: rgba(0, 0, 0, 0.25);
            color: var(--txt);
            padding: 8px 10px;
            outline: none;
            font-family: Consolas, monospace;
        }

        .term-line.cmd { color: #7dd3fc; }
        .term-line.ok { color: #86efac; }
        .term-line.err { color: #fca5a5; }

        .terminal-dock {
            position: fixed;
            left: 340px;
            right: 0;
            bottom: 0;
            height: 38px;
            z-index: 29;
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 4px 10px;
            background: rgba(7, 13, 24, 0.95);
            border-top: 1px solid var(--line);
        }

        .app.collapsed + .terminal-dock {
            left: 0;
        }

        .dock-item {
            border: 1px solid var(--line);
            border-radius: 8px;
            background: var(--panel);
            color: var(--txt);
            padding: 5px 10px;
            cursor: pointer;
            font-size: 12px;
        }

        .status {
            border-top: 1px solid var(--line);
            padding: 6px 14px;
            font-size: 12px;
            color: var(--muted);
            background: rgba(255, 255, 255, 0.02);
        }

        .modal {
            position: fixed;
            inset: 0;
            display: none;
            place-items: center;
            background: rgba(0, 0, 0, 0.75);
            z-index: 20;
        }

        .modal.open { display: grid; }

        .editor-box {
            width: min(1080px, 95vw);
            height: min(86vh, 900px);
            border: 1px solid var(--line);
            border-radius: 14px;
            overflow: hidden;
            display: grid;
            grid-template-rows: auto 1fr auto;
            background: #070d17;
        }

        .mhead, .mfoot {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 9px 10px;
            border-bottom: 1px solid var(--line);
        }

        .mfoot {
            border-top: 1px solid var(--line);
            border-bottom: none;
            justify-content: flex-end;
            gap: 8px;
        }

        .editor {
            width: 100%;
            height: 100%;
            border: none;
            outline: none;
            resize: none;
            padding: 10px;
            font: 13px/1.5 Consolas, monospace;
            background: #030712;
            color: #dbeafe;
        }

        .hidden { display: none !important; }

        .app.collapsed .hide-when-collapsed {
            display: none;
        }

        .app.collapsed .sidebar {
            position: relative;
            top: auto;
            left: auto;
            width: auto;
            height: 100%;
            overflow: hidden;
            border-radius: 0;
            padding: 10px 8px;
            border-right: 1px solid var(--line);
            border-top: none;
            z-index: 20;
            display: grid;
            grid-template-rows: auto 1fr auto;
            align-items: stretch;
            cursor: default;
            box-shadow: none;
        }

        .app.collapsed .sidebar > * { display: none; }
        .app.collapsed .sidebar .brand,
        .app.collapsed .sidebar .side-foot,
        .app.collapsed .sidebar #profileTrigger,
        .app.collapsed .sidebar .panel-nav {
            display: flex;
        }

        .app.collapsed .sidebar .brand {
            justify-content: center;
        }

        .app.collapsed .sidebar .side-foot {
            display: grid;
            width: 100%;
            margin-top: auto;
        }

        .app.collapsed .sidebar .panel-nav { display: grid; }

        .app.collapsed .sidebar .profile-trigger {
            width: 100%;
            justify-content: center;
            padding: 5px;
            border-radius: 9px;
        }

        .app.collapsed .sidebar .profile-main {
            justify-content: center;
            width: 100%;
        }

        .app.collapsed .sidebar .profile-meta,
        .app.collapsed .sidebar .settings-btn,
        .app.collapsed .sidebar .user-menu,
        .app.collapsed .sidebar .side-mid {
            display: none;
        }

        .app.collapsed .panel-nav {
            border: none;
            padding: 0;
            background: transparent;
            gap: 4px;
            margin-top: 4px;
            align-content: start;
        }

        .app.collapsed .panel-nav h4,
        .app.collapsed .panel-nav .panel-nav-label {
            display: none;
        }

        .app.collapsed .panel-nav .panel-nav-btn {
            width: 38px;
            height: 38px;
            min-width: 38px;
            border-radius: 10px;
            justify-content: center;
            padding: 0;
        }

        .app.collapsed .panel-nav .panel-nav-icon {
            width: 14px;
            min-width: 14px;
            font-size: 11px;
        }

        .app.collapsed .collapse-btn {
            display: block;
            left: 64px;
            top: 14px;
        }
        .app.collapsed .top { padding-left: 14px; }

        .app.collapsed .sidebar:hover {
            border-color: var(--acc);
            box-shadow: none;
        }

        @media (max-width: 1020px) {
            .app {
                grid-template-columns: 1fr;
            }

            .sidebar {
                grid-template-rows: auto auto;
                overflow: auto;
                max-height: 230px;
            }

            .row {
                grid-template-columns: 34px 1fr 100px;
            }

            .row .meta:last-child {
                display: none;
            }

            .collapse-btn {
                left: 12px;
                top: 12px;
                border-left: 1px solid var(--line);
                border-radius: 8px;
                z-index: 40;
            }

            .top {
                padding-left: 56px;
            }

            .top-tools {
                padding-left: 56px;
            }

            .terminal-window {
                width: calc(100vw - 16px);
                right: 8px;
                left: 8px;
                bottom: 44px;
            }

            .terminal-dock,
            .app.collapsed + .terminal-dock {
                left: 0;
            }

            .profile-trigger {
                width: 100%;
            }

            .tool-pill {
                font-size: 12px;
            }
        }

        @media (max-width: 640px) {
            .sidebar {
                max-height: 180px;
            }

            .side-foot {
                grid-template-columns: auto auto;
                align-items: center;
                justify-content: start;
            }

            .profile-trigger {
                width: 100%;
                padding: 4px 6px;
                gap: 6px;
            }

            .avatar {
                width: 30px;
                height: 30px;
            }

            .top-tools {
                gap: 8px;
                max-height: 0;
                opacity: 0;
                overflow: hidden;
                border-bottom: none;
                padding-top: 0;
                padding-bottom: 0;
                transition: max-height 0.25s ease, opacity 0.2s ease, padding 0.2s ease;
            }

            .top-tools.open {
                max-height: 260px;
                opacity: 1;
                padding-top: 10px;
                padding-bottom: 10px;
                border-bottom: 1px solid var(--line);
            }

            .top-tools-group {
                width: 100%;
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 6px;
            }

            .tool-pill {
                width: 100%;
                justify-content: center;
                padding: 7px 8px;
                font-size: 12px;
            }

            .tool-pill.icon-only {
                width: 100%;
                min-width: 0;
            }

            .mobile-tools-toggle {
                display: inline-flex;
                width: auto;
                min-width: 0;
                padding: 0 10px;
                font-size: 12px;
            }

            .admin-fab {
                right: 14px;
                bottom: 48px;
            }

            .fab-main {
                width: 52px;
                height: 52px;
            }

            .fab-item {
                width: 42px;
                height: 42px;
            }
        }

        .admin-overlay {
            position: fixed;
            inset: 0;
            background: rgba(2, 7, 18, 0.68);
            backdrop-filter: blur(3px);
            z-index: 1200;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 18px;
        }

        .admin-overlay.open {
            display: flex;
        }

        .admin-panel-overlay {
            width: min(1280px, 96vw);
            height: min(88vh, 900px);
            border: 1px solid var(--line);
            border-radius: 14px;
            overflow: hidden;
            background: rgba(8, 17, 35, 0.98);
            box-shadow: 0 24px 64px rgba(2, 6, 23, 0.55);
            display: grid;
            grid-template-rows: auto 1fr;
        }

        .admin-panel-head {
            height: 52px;
            border-bottom: 1px solid var(--line);
            padding: 0 12px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            background: rgba(255,255,255,0.04);
        }

        .admin-frame {
            width: 100%;
            height: 100%;
            border: none;
            background: transparent;
        }
    </style>
</head>
<body>
    <div class="bg">
        <div class="blob b1"></div>
        <div class="blob b2"></div>
        <div class="blob b3"></div>
    </div>

    <div id="app" class="app">
        <button id="collapseBtn" class="collapse-btn"><</button>

        <aside class="sidebar">
            <div class="brand">
                <div class="logo">H</div>
                <div class="brand-name hide-when-collapsed">Server Hub</div>
            </div>

            <div class="panel-nav" aria-label="Panele administracyjne">
                <h4>Panele admina</h4>
                <button class="panel-nav-btn active" data-main-view="dashboard" title="Dashboard">
                    <span class="panel-nav-icon">▦</span>
                    <span class="panel-nav-label">Dashboard</span>
                </button>
                <button class="panel-nav-btn" data-main-view="explorer" title="Pliki serwera">
                    <span class="panel-nav-icon">📁</span>
                    <span class="panel-nav-label">Pliki serwera</span>
                </button>
                <button class="panel-nav-btn" data-panel-url="users.php" data-panel-title="Uzytkownicy" title="Uzytkownicy">
                    <span class="panel-nav-icon">👤</span>
                    <span class="panel-nav-label">Uzytkownicy</span>
                </button>
                <button class="panel-nav-btn" data-panel-url="roles.php" data-panel-title="Role" title="Role">
                    <span class="panel-nav-icon">🛡</span>
                    <span class="panel-nav-label">Role</span>
                </button>
                <button class="panel-nav-btn" data-panel-url="relations.php" data-panel-title="Relacje" title="Relacje">
                    <span class="panel-nav-icon">🔗</span>
                    <span class="panel-nav-label">Relacje</span>
                </button>
                <button class="panel-nav-btn" data-panel-url="access.php" data-panel-title="Dostepy" title="Dostepy">
                    <span class="panel-nav-icon">✅</span>
                    <span class="panel-nav-label">Dostepy</span>
                </button>
                <button class="panel-nav-btn" data-panel-url="database.php" data-panel-title="Baza danych" title="Baza danych">
                    <span class="panel-nav-icon">🗄</span>
                    <span class="panel-nav-label">Baza danych</span>
                </button>
                <button class="panel-nav-btn" data-panel-url="demos.php" data-panel-title="Demo Links" title="Demo Links">
                    <span class="panel-nav-icon">🔐</span>
                    <span class="panel-nav-label">Demo Links</span>
                </button>
            </div>

            <div class="side-mid">
                <div id="miniPreview" class="hidden"></div>
                <div id="pinsList" class="hidden"></div>
                <div id="recentList" class="hidden"></div>
            </div>

            <div class="side-foot">
                <div id="profileTrigger" class="profile-trigger">
                    <div class="profile-main">
                        <div class="avatar"><?php echo strtoupper(substr($userName, 0, 1)); ?></div>
                        <div class="profile-meta hide-when-collapsed">
                            <div class="profile-name"><?php echo htmlspecialchars($userName); ?></div>
                            <div class="profile-email"><?php echo htmlspecialchars($userEmail); ?></div>
                            <div class="sub"><?php echo $userRole === 'admin' ? 'Admin' : 'User'; ?></div>
                        </div>
                    </div>
                    <button id="settingsBtn" class="settings-btn hide-when-collapsed" title="Ustawienia">⚙</button>
                </div>

                <div id="userMenu" class="user-menu hide-when-collapsed">
                    <button class="btn warn" onclick="window.location.href='../logout.php'">Wyloguj</button>
                </div>
            </div>
        </aside>

        <main class="main dashboard-mode">
            <div class="top" style="height: 64px;">
                <div>
                    <!-- <div class="title">Eksplorator plikow</div> -->
                    <!-- <div class="sub">Root + operacje + podglad + terminal</div> -->
                </div>
                <div class="top-right">
                    <div class="app-switch" id="appSwitch">
                        <button type="button" class="app-switch-btn" id="appSwitchBtn" aria-expanded="false">
                            <span>🧭</span>
                            <span>Przelacz aplikacje</span>
                        </button>
                        <div class="app-switch-menu" id="appSwitchMenu">
                            <?php if (empty($visibleApps)): ?>
                                <div class="app-switch-item" style="cursor:default; opacity:.75;">Brak przypisanych aplikacji</div>
                            <?php else: ?>
                                <?php foreach ($visibleApps as $app): ?>
                                    <a class="app-switch-item" href="<?php echo htmlspecialchars((string) $app['url'], ENT_QUOTES, 'UTF-8'); ?>">
                                        <span><?php echo htmlspecialchars((string) $app['icon'], ENT_QUOTES, 'UTF-8'); ?></span>
                                        <span><?php echo htmlspecialchars((string) $app['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                                    </a>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <button id="openTasksOverlayBtn" class="btn" title="Task runner">Task runner</button>
                    <button id="mobileToolsToggle" class="btn mobile-tools-toggle" title="Pokaż lub ukryj narzędzia">Narzędzia</button>
                    <button id="toggleTerminalBtn" class="btn icon" title="Terminal">⌨</button>
                    <button id="terminalTabBtn" class="btn icon" title="Terminal w nowej karcie">↗</button>
                </div>
            </div>

            <section id="dashboardView" class="dashboard-view">
                <div class="dashboard-head">
                    <div class="title">Admin Dashboard</div>
                    <div class="sub">Widok startowy. Explorer plikow otworzysz z przycisku Pliki serwera w sidebarze.</div>
                </div>
                <div class="dashboard-grid">
                    <article class="dashboard-card">
                        <h3>Uzytkownicy</h3>
                        <p>Role kont i szybkie aktualizacje uprawnien.</p>
                        <button class="btn" data-panel-url="users.php" data-panel-title="Uzytkownicy">Otworz panel</button>
                    </article>
                    <article class="dashboard-card">
                        <h3>Role</h3>
                        <p>Definicje rol i przypisania systemowe.</p>
                        <button class="btn" data-panel-url="roles.php" data-panel-title="Role">Otworz panel</button>
                    </article>
                    <article class="dashboard-card">
                        <h3>Dostepy</h3>
                        <p>Mapa app role i access per user.</p>
                        <button class="btn" data-panel-url="access.php" data-panel-title="Dostepy">Otworz panel</button>
                    </article>
                    <article class="dashboard-card">
                        <h3>Baza danych</h3>
                        <p>SQL runner, tabele i kolumny.</p>
                        <button class="btn" data-panel-url="database.php" data-panel-title="Baza danych">Otworz panel</button>
                    </article>
                    <article class="dashboard-card">
                        <h3>Demo Links</h3>
                        <p>Publiczne linki demo z autologowaniem na konta pokazowe.</p>
                        <button class="btn" data-panel-url="demos.php" data-panel-title="Demo Links">Otworz panel</button>
                    </article>
                </div>
            </section>

            <div class="top-tools explorer-only">
                <div class="top-tools-group">
                    <button class="tool-pill icon-only" id="fabBackBtn" title="Powrót">←</button>
                    <button class="tool-pill icon-only" id="fabRefreshBtn" title="Odśwież">↻</button>
                    <button class="tool-pill" id="fabMkdirBtn" title="Nowy folder">📁 Folder</button>
                    <button class="tool-pill" id="fabNewFileBtn" title="Nowy plik">📄 Plik</button>
                    <button class="tool-pill" id="fabUploadFilesBtn" title="Upload plików">⬆ Pliki</button>
                    <button class="tool-pill" id="fabUploadFolderBtn" title="Upload folderu">📦 Folder</button>
                </div>
                <div class="top-tools-group">
                    <button class="tool-pill" id="fabTemplateBtn" title="Szablon pliku">📋 Szablon</button>
                    <button class="tool-pill" id="fabCreateComponentBtn" title="Utwórz komponent">⚛ Komponent</button>
                    <button class="tool-pill" id="fabCreatePageBtn" title="Utwórz stronę">📃 Strona</button>
                    <button class="tool-pill" id="fabCreateControllerBtn" title="Utwórz kontroler">🎮 Kontroler</button>
                </div>
            </div>

            <!-- Pasek wyszukiwania — pływający overlay -->
            <div id="searchInline" class="search-inline explorer-only">
                <input id="searchTextInput" type="text" placeholder="Szukaj w plikach..." autocomplete="off">
                <button id="searchTextRunBtn" class="btn">Szukaj</button>
                <button id="searchTextCloseBtn" class="btn icon" style="width:32px;min-width:32px;">✕</button>
            </div>

            <div id="crumbs" class="crumbs explorer-only">/</div>

            <div id="list" class="list explorer-only"></div>

            <div id="previewPane" class="preview-pane">
                <div class="preview-head">
                    <strong id="previewTitle">Podglad</strong>
                    <button id="closePreviewBtn" class="btn">Zamknij podglad</button>
                </div>
                <div id="previewBody" class="preview-body"></div>
            </div>

            <div id="terminalWindow" class="terminal-window">
                <div id="terminalHead" class="terminal-head">
                    <strong>Terminal</strong>
                    <div class="terminal-actions">
                        <button id="terminalSizeSmBtn" class="terminal-mini-btn" title="Maly">S</button>
                        <button id="terminalSizeMdBtn" class="terminal-mini-btn" title="Sredni">M</button>
                        <button id="terminalSizeLgBtn" class="terminal-mini-btn" title="Duzy">L</button>
                        <button id="terminalMinBtn" class="terminal-mini-btn">_</button>
                        <button id="terminalCloseBtn" class="terminal-mini-btn">x</button>
                    </div>
                </div>
                <div id="termOut" class="term-out"><div class="term-line ok">Tryb terminala: host moze blokowac shell. Zawsze dzialaja: pwd, ls, dir, date, whoami, echo, php -v</div></div>
                <div class="term-in">
                    <input id="termInput" class="term-input" type="text" placeholder="Polecenie...">
                    <button id="termRunBtn" class="btn">Wykonaj</button>
                </div>
            </div>

            <div id="status" class="status explorer-only">Gotowe</div>
        </main>
    </div>

    <!-- Admin FAB — rozwijane menu w prawym dolnym rogu -->
    <div id="adminFab" class="admin-fab">
        <button class="fab-main" id="fabMainBtn" title="Akcje dla zaznaczenia">✦</button>
        <button class="fab-item fi-1" id="fabSearchBtn" title="Szukaj w plikach">🔍</button>
        <button class="fab-item fi-2" id="fabFindReplaceBtn" title="Znajdź i zamień">🔄</button>
        <button class="fab-item fi-3" id="fabRenameBtn" title="Zmień nazwę">✏</button>
        <button class="fab-item fi-4" id="fabMoveBtn" title="Przenieś">↗</button>
        <button class="fab-item fi-5" id="fabPinBtn" title="Przypnij/Odepnij">📌</button>
        <button class="fab-item fi-6 fab-danger" id="fabDeleteBtn" title="Usuń">🗑</button>
    </div>

    <div id="terminalDock" class="terminal-dock">
        <button id="terminalDockItem" class="dock-item hidden">Terminal</button>
    </div>

    <div id="tasksOverlay" class="tasks-overlay" aria-hidden="true">
        <div class="tasks-panel">
            <div class="tasks-panel-head">
                <strong>Task runner</strong>
                <button id="closeTasksOverlayBtn" class="btn">Zamknij</button>
            </div>
            <div class="tasks-panel-body">
                <div id="taskList"></div>
                <div class="task-actions">
                    <button id="addTaskBtn" class="btn">+ Task</button>
                    <button id="resetTasksBtn" class="btn">Reset</button>
                    <button id="exportTasksBtn" class="btn">Export</button>
                    <button id="importTasksBtn" class="btn">Import</button>
                </div>
                <input id="importTasksInput" type="file" accept="application/json,.json" style="display:none;">
            </div>
        </div>
    </div>

    <div id="adminOverlay" class="admin-overlay" aria-hidden="true">
        <div class="admin-panel-overlay">
            <div class="admin-panel-head">
                <strong id="adminPanelTitle">Panel administracyjny</strong>
                <div style="display:flex; gap:8px; align-items:center;">
                    <a id="openAdminPanelTab" class="btn" href="access.php" target="_blank" rel="noopener">Otworz osobno</a>
                    <button id="closeAdminOverlayBtn" class="btn">Zamknij</button>
                </div>
            </div>
            <iframe id="adminFrame" class="admin-frame" src="about:blank" loading="lazy"></iframe>
        </div>
    </div>

    <input id="uploadFilesInput" class="hidden" type="file" multiple>
    <input id="uploadFolderInput" class="hidden" type="file" webkitdirectory directory multiple>

    <div id="editorModal" class="modal">
        <div class="editor-box">
            <div class="mhead">
                <strong id="editorPath">Edytor</strong>
                <button id="closeEditorBtn" class="btn">Zamknij</button>
            </div>
            <textarea id="editor" class="editor"></textarea>
            <div class="mfoot">
                <button id="editorCodePreviewBtn" class="btn">Kolorowany kod</button>
                <button id="editorPreviewBtn" class="btn">Podglad</button>
                <button id="editorPreviewTabBtn" class="btn">Podglad nowa karta</button>
                <button id="saveEditorBtn" class="btn primary">Zapisz</button>
            </div>
        </div>
    </div>

    <script>
        const state = {
            root: '',
            current: '',
            parent: null,
            items: [],
            selectedPath: '',
            selectedType: '',
            currentFile: '',
            currentMainView: 'dashboard',
            terminalMinimized: false,
        };

        const ICON = {
            folder: '📁',
            file: '📄',
            code: '🧩',
            php: '🐘',
            paint: '🎨',
            html: '🌐',
            json: '🧱',
            db: '🗄',
            markdown: '📝',
            text: '📄',
            settings: '⚙',
            xml: '📐',
            image: '🖼',
            archive: '🧰',
            pdf: '📕',
        };

        const appEl = document.getElementById('app');
        const mainEl = document.querySelector('.main');
        const listEl = document.getElementById('list');
        const statusEl = document.getElementById('status');
        const crumbsEl = document.getElementById('crumbs');
        const miniPreviewEl = document.getElementById('miniPreview');
        const previewPaneEl = document.getElementById('previewPane');
        const previewBodyEl = document.getElementById('previewBody');
        const previewTitleEl = document.getElementById('previewTitle');
        const collapseBtnEl = document.getElementById('collapseBtn');
        const terminalWindowEl = document.getElementById('terminalWindow');
        const terminalDockItemEl = document.getElementById('terminalDockItem');
        const topToolsEl = document.querySelector('.top-tools');
        const mobileToolsToggleEl = document.getElementById('mobileToolsToggle');
        const mainViewButtonsEl = Array.from(document.querySelectorAll('[data-main-view]'));
        const railRootBtnEl = document.getElementById('railRootBtn');
        const railRefreshBtnEl = document.getElementById('railRefreshBtn');
        const railTerminalBtnEl = document.getElementById('railTerminalBtn');
        const railToolsBtnEl = document.getElementById('railToolsBtn');
        const openTasksOverlayBtnEl = document.getElementById('openTasksOverlayBtn');
        const tasksOverlayEl = document.getElementById('tasksOverlay');
        const closeTasksOverlayBtnEl = document.getElementById('closeTasksOverlayBtn');
        const adminPanelButtonsEl = Array.from(document.querySelectorAll('[data-panel-url]'));
        const adminOverlayEl = document.getElementById('adminOverlay');
        const closeAdminOverlayBtnEl = document.getElementById('closeAdminOverlayBtn');
        const adminFrameEl = document.getElementById('adminFrame');
        const adminPanelTitleEl = document.getElementById('adminPanelTitle');
        const openAdminPanelTabEl = document.getElementById('openAdminPanelTab');
        const appSwitchEl = document.getElementById('appSwitch');
        const appSwitchBtnEl = document.getElementById('appSwitchBtn');

        const PIN_STORAGE_KEY = 'serverHubPins';
        const TERM_POS_STORAGE_KEY = 'serverHubTermPos';
        const TERM_SIZE_STORAGE_KEY = 'serverHubTermSize';
        const RECENT_FILES_STORAGE_KEY = 'serverHubRecentFiles';
        const TASKS_STORAGE_KEY = 'serverHubTasks';

        function getPins() {
            try {
                const raw = localStorage.getItem(PIN_STORAGE_KEY);
                const arr = raw ? JSON.parse(raw) : [];
                return Array.isArray(arr) ? arr : [];
            } catch (e) {
                return [];
            }
        }

        function savePins(pins) {
            localStorage.setItem(PIN_STORAGE_KEY, JSON.stringify(pins));
        }

        function getRecentFiles() {
            try {
                const raw = localStorage.getItem(RECENT_FILES_STORAGE_KEY);
                const arr = raw ? JSON.parse(raw) : [];
                return Array.isArray(arr) ? arr : [];
            } catch (e) {
                return [];
            }
        }

        function saveRecentFiles(items) {
            localStorage.setItem(RECENT_FILES_STORAGE_KEY, JSON.stringify(items.slice(0, 12)));
        }

        function addRecentFile(path) {
            const items = getRecentFiles().filter((x) => x !== path);
            items.unshift(path);
            saveRecentFiles(items);
            renderRecentFiles();
        }

        function renderPins() {
            const pins = getPins();
            const holder = document.getElementById('pinsList');
            if (!holder) return;
            holder.innerHTML = '';
            if (pins.length === 0) {
                holder.innerHTML = '<div class="sub">Brak przypietych</div>';
                return;
            }

            pins.forEach((pinPath) => {
                const btn = document.createElement('button');
                btn.className = 'pin-item';
                btn.title = pinPath;
                btn.textContent = pinPath.split('\\').pop() || pinPath;
                btn.addEventListener('click', () => loadDir(pinPath));
                holder.appendChild(btn);
            });
        }

        function renderRecentFiles() {
            const holder = document.getElementById('recentList');
            if (!holder) return;
            const items = getRecentFiles();
            holder.innerHTML = '';

            if (items.length === 0) {
                holder.innerHTML = '<div class="sub">Brak ostatnio otwieranych</div>';
                return;
            }

            items.forEach((path) => {
                const btn = document.createElement('button');
                btn.className = 'recent-item';
                btn.title = path;
                btn.textContent = path.split('\\').pop() || path;
                btn.addEventListener('click', async () => {
                    const fileName = path.split('\\').pop() || path;
                    await openFileOrPreview(path, fileName);
                });
                holder.appendChild(btn);
            });
        }

        function defaultTasks() {
            return [
                { name: 'Build', cmd: 'npm run build' },
                { name: 'Test', cmd: 'npm test' },
                { name: 'Deploy', cmd: 'git status' },
            ];
        }

        function getTasks() {
            try {
                const raw = localStorage.getItem(TASKS_STORAGE_KEY);
                if (!raw) return defaultTasks();
                const arr = JSON.parse(raw);
                return Array.isArray(arr) && arr.length > 0 ? arr : defaultTasks();
            } catch (e) {
                return defaultTasks();
            }
        }

        function saveTasks(tasks) {
            localStorage.setItem(TASKS_STORAGE_KEY, JSON.stringify(tasks.slice(0, 20)));
        }

        function renderTasks() {
            const holder = document.getElementById('taskList');
            if (!holder) return;
            const tasks = getTasks();
            holder.innerHTML = '';

            tasks.forEach((task, idx) => {
                const btn = document.createElement('button');
                btn.className = 'task-item';
                btn.title = task.cmd;
                btn.textContent = task.name;
                btn.addEventListener('click', async () => {
                    await runTask(task.cmd);
                });
                btn.addEventListener('contextmenu', (e) => {
                    e.preventDefault();
                    const ok = confirm(`Usunac task: ${task.name}?`);
                    if (!ok) return;
                    const next = getTasks();
                    next.splice(idx, 1);
                    saveTasks(next);
                    renderTasks();
                });
                holder.appendChild(btn);
            });
        }

        async function runTask(cmd) {
            openTerminal();
            document.getElementById('termInput').value = cmd;
            await runTerminalCommand();
        }

        function addTaskShortcut() {
            const name = prompt('Nazwa taska (np. Build API):');
            if (!name) return;
            const cmd = prompt('Komenda taska (np. npm run build):');
            if (!cmd) return;
            const tasks = getTasks();
            tasks.push({ name, cmd });
            saveTasks(tasks);
            renderTasks();
            setStatus('Dodano task');
        }

        function resetTasks() {
            saveTasks(defaultTasks());
            renderTasks();
            setStatus('Taski zresetowane');
        }

        function exportTasks() {
            const tasks = getTasks();
            const blob = new Blob([JSON.stringify(tasks, null, 2)], { type: 'application/json' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'server-hub-tasks.json';
            document.body.appendChild(a);
            a.click();
            a.remove();
            URL.revokeObjectURL(url);
            setStatus('Taski wyeksportowane');
        }

        async function importTasksFromFile(file) {
            if (!file) return;
            try {
                const text = await file.text();
                const parsed = JSON.parse(text);
                if (!Array.isArray(parsed)) {
                    setStatus('Bledny format JSON taskow');
                    return;
                }

                const sanitized = parsed
                    .filter((t) => t && typeof t.name === 'string' && typeof t.cmd === 'string')
                    .map((t) => ({ name: t.name.trim(), cmd: t.cmd.trim() }))
                    .filter((t) => t.name.length > 0 && t.cmd.length > 0)
                    .slice(0, 20);

                if (sanitized.length === 0) {
                    setStatus('Brak poprawnych taskow do importu');
                    return;
                }

                saveTasks(sanitized);
                renderTasks();
                setStatus(`Zaimportowano taski: ${sanitized.length}`);
            } catch (e) {
                setStatus('Nie udalo sie odczytac JSON taskow');
            }
        }

        function setStatus(text) {
            statusEl.textContent = text;
        }

        async function setMainView(view) {
            const nextView = view === 'explorer' ? 'explorer' : 'dashboard';
            state.currentMainView = nextView;

            if (mainEl) {
                mainEl.classList.toggle('dashboard-mode', nextView === 'dashboard');
                mainEl.classList.toggle('explorer-mode', nextView === 'explorer');
            }

            mainViewButtonsEl.forEach((button) => {
                const btnView = button.getAttribute('data-main-view') || '';
                button.classList.toggle('active', btnView === nextView);
            });

            if (nextView === 'explorer' && state.root && !state.current) {
                await loadDir(state.root);
            }
        }

        function updatePinButtonVisual(path, type) {
            const pinBtn = document.getElementById('fabPinBtn');
            const pins = getPins();
            const pinned = type === 'directory' && pins.includes(path);
            pinBtn.textContent = pinned ? '📍' : '📌';
            pinBtn.title = pinned ? 'Odepnij folder' : 'Przypnij folder';
        }

        function escapeHtml(text) {
            return String(text)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function formatBytes(size) {
            if (!size || size <= 0) return '-';
            const units = ['B', 'KB', 'MB', 'GB'];
            let n = size;
            let i = 0;
            while (n >= 1024 && i < units.length - 1) {
                n /= 1024;
                i += 1;
            }
            return `${n.toFixed(i > 0 && n < 10 ? 1 : 0)} ${units[i]}`;
        }

        function formatDate(ts) {
            if (!ts) return '-';
            return new Date(ts * 1000).toLocaleString('pl-PL');
        }

        async function apiGet(action, params = {}) {
            const query = new URLSearchParams({ action, ...params });
            const res = await fetch(`../api.php?${query.toString()}`);
            return res.json();
        }

        async function apiPost(action, data = {}) {
            const fd = new FormData();
            fd.append('action', action);
            Object.entries(data).forEach(([k, v]) => {
                if (Array.isArray(v)) {
                    v.forEach((item) => fd.append(`${k}[]`, item));
                } else {
                    fd.append(k, v);
                }
            });
            const res = await fetch('../api.php', { method: 'POST', body: fd });
            return res.json();
        }

        async function loadRoot() {
            setStatus('Pobieranie roota...');
            const data = await apiGet('root');
            if (data.error) {
                setStatus(`Blad: ${data.error}`);
                return;
            }
            state.root = data.root;
            renderPins();
            renderRecentFiles();
            renderTasks();
            await setMainView('dashboard');

            const params = new URLSearchParams(window.location.search);
            if (params.get('terminal') === '1') {
                openTerminal();
            }
            if (params.get('view') === 'explorer') {
                await setMainView('explorer');
                await loadDir(state.root);
            }
        }

        async function loadDir(path) {
            setStatus('Ladowanie folderu...');
            const data = await apiGet('list', { path });
            if (data.error) {
                setStatus(`Blad: ${data.error}`);
                return;
            }

            state.current = data.current;
            state.parent = data.parent;
            state.items = data.items || [];
            state.selectedPath = '';
            state.selectedType = '';

            crumbsEl.textContent = data.current;
            renderRows();
            setStatus('Folder zaladowany');
            loadMiniIndexPreview(state.current);
        }

        function renderRows() {
            listEl.innerHTML = '';
            if (!Array.isArray(state.items) || state.items.length === 0) {
                listEl.innerHTML = '<div class="sub">Brak elementow w folderze.</div>';
                return;
            }

            state.items.forEach((item) => {
                const row = document.createElement('div');
                row.className = 'row';
                row.dataset.path = item.path;
                row.dataset.name = item.name;
                row.innerHTML = `
                    <div>${ICON[item.icon] || ICON.file}</div>
                    <div>${escapeHtml(item.name)}</div>
                    <div class="meta">${item.type === 'directory' ? 'folder' : formatBytes(item.size)}</div>
                    <div class="meta">${formatDate(item.modified)}</div>
                `;

                row.addEventListener('click', () => selectRow(item.path, item.type));
                row.addEventListener('dblclick', () => {
                    if (item.type === 'directory') {
                        loadDir(item.path);
                    } else {
                        openFileOrPreview(item.path, item.name);
                    }
                });

                listEl.appendChild(row);
            });
        }

        async function selectRow(path, type) {
            state.selectedPath = path;
            state.selectedType = type;

            listEl.querySelectorAll('.row').forEach((row) => {
                row.classList.toggle('selected', row.dataset.path === path);
            });

            if (type === 'directory') {
                await loadMiniIndexPreview(path);
            } else {
                if (miniPreviewEl) {
                    miniPreviewEl.textContent = 'Zaznaczono plik. Kliknij 2x, aby otworzyc edytor lub podglad.';
                }
            }

            updatePinButtonVisual(path, type);
        }

        async function loadMiniIndexPreview(dirPath) {
            if (!miniPreviewEl) {
                return;
            }
            const data = await apiGet('folder_index_preview', { path: dirPath });
            if (data.error) {
                miniPreviewEl.textContent = `Mini podglad: ${data.error}`;
                return;
            }
            if (!data.exists) {
                miniPreviewEl.textContent = 'W tym folderze nie ma index.php/index.html.';
                return;
            }

            miniPreviewEl.innerHTML = `
                <strong>Mini podglad index:</strong><br>
                Plik: ${escapeHtml(data.file || '-')}<br>
                Tytul: ${escapeHtml(data.title || '(brak tytulu)')}<br><br>
                ${escapeHtml(data.snippet || '')}
            `;
        }

        async function openFileOrPreview(path, fileName) {
            const ext = fileName.split('.').pop().toLowerCase();
            if (['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg', 'pdf', 'md'].includes(ext)) {
                await openPreview(path, fileName);
                return;
            }
            await openEditor(path, fileName);
        }

        async function openPreview(path, fileName) {
            setStatus('Ladowanie podgladu...');
            const data = await apiGet('preview', { path });
            if (data.error) {
                setStatus(`Blad podgladu: ${data.error}`);
                return;
            }

            previewTitleEl.textContent = `Podglad: ${fileName}`;
            previewBodyEl.innerHTML = '';

            if (data.kind === 'image') {
                previewBodyEl.innerHTML = `<img alt="podglad obrazu" src="${data.dataUrl}">`;
            } else if (data.kind === 'pdf') {
                previewBodyEl.innerHTML = `<iframe title="PDF" src="${data.dataUrl}"></iframe>`;
            } else if (data.kind === 'markdown') {
                previewBodyEl.innerHTML = `<div class="md">${data.html}</div>`;
            }

            previewPaneEl.classList.add('open');
            addRecentFile(path);
            setStatus('Podglad gotowy');
        }

        async function openEditor(path, fileName) {
            setStatus('Wczytywanie pliku...');
            const data = await apiGet('read', { path });
            if (data.error) {
                setStatus(`Blad: ${data.error}`);
                return;
            }

            state.currentFile = path;
            document.getElementById('editorPath').textContent = fileName;
            document.getElementById('editor').value = data.content || '';
            document.getElementById('editorModal').classList.add('open');
            addRecentFile(path);
            setStatus('Plik otwarty');
        }

        async function saveEditor() {
            if (!state.currentFile) return;
            setStatus('Zapisywanie...');
            const data = await apiPost('write', {
                path: state.currentFile,
                content: document.getElementById('editor').value,
            });
            if (data.error) {
                setStatus(`Blad: ${data.error}`);
                return;
            }
            setStatus('Zapisano plik');
        }

        function simpleHighlight(code, ext) {
            let html = escapeHtml(code);
            const kwSets = {
                php: ['function', 'if', 'else', 'return', 'class', 'public', 'private', 'foreach', 'switch', 'case'],
                js: ['function', 'if', 'else', 'return', 'const', 'let', 'var', 'class', 'async', 'await'],
                html: ['html', 'head', 'body', 'div', 'span', 'script', 'style', 'meta', 'link'],
                css: ['display', 'position', 'color', 'background', 'border', 'padding', 'margin', 'grid', 'flex'],
            };

            const extKey = ['php', 'js', 'ts', 'tsx', 'jsx', 'html', 'css'].includes(ext) ? (ext === 'ts' || ext === 'tsx' || ext === 'jsx' ? 'js' : ext) : 'js';
            const kws = kwSets[extKey] || kwSets.js;

            html = html.replace(/(&quot;.*?&quot;|'.*?')/g, '<span style="color:#f9a8d4;">$1</span>');
            html = html.replace(/(\/\/.*$)/gm, '<span style="color:#64748b;">$1</span>');
            html = html.replace(/(\b\d+\b)/g, '<span style="color:#fbbf24;">$1</span>');

            kws.forEach((kw) => {
                const re = new RegExp(`\\b${kw}\\b`, 'g');
                html = html.replace(re, `<span style="color:#67e8f9;">${kw}</span>`);
            });

            return html;
        }

        function openCodePreviewModal() {
            const path = state.currentFile || '';
            const ext = (path.split('.').pop() || '').toLowerCase();
            const code = document.getElementById('editor').value || '';
            previewTitleEl.textContent = `Kolorowany podglad: ${path.split('\\').pop() || ''}`;
            previewBodyEl.innerHTML = `<pre style="margin:0; white-space:pre-wrap; font:13px/1.5 Consolas,monospace; color:#dbeafe;">${simpleHighlight(code, ext)}</pre>`;
            previewPaneEl.classList.add('open');
        }

        async function resolvePreviewUrl(path) {
            const data = await apiGet('web_preview_url', { path });
            if (data.error || !data.url) {
                return '';
            }
            return data.url;
        }

        async function editorPreview(openInNewTab) {
            if (!state.currentFile) {
                setStatus('Brak pliku do podgladu');
                return;
            }

            const url = await resolvePreviewUrl(state.currentFile);
            if (!url) {
                setStatus('Brak webowego URL dla tego pliku. Uzyj podgladu pliku w liscie.');
                return;
            }

            if (openInNewTab) {
                window.open(url, '_blank', 'noopener');
            } else {
                window.location.href = url;
            }
        }

        function closeEditor() {
            document.getElementById('editorModal').classList.remove('open');
        }

        async function createFolder() {
            const name = prompt('Nazwa nowego folderu:');
            if (!name) return;
            const data = await apiPost('mkdir', { parentPath: state.current, folderName: name });
            if (data.error) {
                setStatus(`Blad: ${data.error}`);
                return;
            }
            await loadDir(state.current);
            setStatus('Folder utworzony');
        }

        async function createNewFile() {
            const name = prompt('Nazwa nowego pliku (np. notes.md, index.php, main.js):');
            if (!name) return;
            const data = await apiPost('create_file', { parentPath: state.current, fileName: name, content: '' });
            if (data.error) {
                setStatus(`Blad: ${data.error}`);
                return;
            }
            await loadDir(state.current);
            setStatus('Plik utworzony');
        }

        function getTemplateContent(type, fileName) {
            const safeTitle = fileName.replace(/\.[^.]+$/, '');
            const map = {
                php: `<` + `?php\n\n// ${safeTitle}\n\n`,
                html: `<!doctype html>\n<html lang="pl">\n<head>\n  <meta charset="UTF-8">\n  <meta name="viewport" content="width=device-width, initial-scale=1.0">\n  <title>${safeTitle}</title>\n</head>\n<body>\n\n</body>\n</html>\n`,
                js: `// ${safeTitle}\nexport function init() {\n  console.log('init ${safeTitle}');\n}\n`,
                css: `/* ${safeTitle} */\n:root {\n  --color-primary: #38bdf8;\n}\n\nbody {\n  margin: 0;\n}\n`,
                md: `# ${safeTitle}\n\nOpis modułu i TODO.\n`,
            };
            return map[type] || '';
        }

        async function createFromTemplate() {
            const type = prompt('Typ szablonu: php | html | js | css | md', 'php');
            if (!type) return;

            const normalized = type.trim().toLowerCase();
            if (!['php', 'html', 'js', 'css', 'md'].includes(normalized)) {
                setStatus('Nieznany typ szablonu');
                return;
            }

            const fileName = prompt(`Nazwa pliku (np. nowy.${normalized})`, `nowy.${normalized}`);
            if (!fileName) return;

            const content = getTemplateContent(normalized, fileName);
            const data = await apiPost('create_file', { parentPath: state.current, fileName, content });
            if (data.error) {
                setStatus(`Blad: ${data.error}`);
                return;
            }

            await loadDir(state.current);
            setStatus('Utworzono plik z szablonu');
        }

        function scaffoldTemplate(kind, baseName, stack) {
            const s = stack || 'react';

            if (kind === 'component') {
                if (s === 'php') {
                    return {
                        fileName: `${baseName}.php`,
                        content: `<` + `?php

function render${baseName}(): void
{
    echo '<section><h2>${baseName}</h2></section>';
}
`,
                    };
                }

                return {
                    fileName: `${baseName}.tsx`,
                    content: `type ${baseName}Props = {};

export default function ${baseName}(props: ${baseName}Props) {
  return (
    <section>
      <h2>${baseName}</h2>
    </section>
  );
}
`,
                };
            }

            if (kind === 'page') {
                if (s === 'php') {
                    return {
                        fileName: `${baseName}.php`,
                        content: `<` + `?php

$title = '${baseName}';
?><!doctype html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($title); ?></title>
</head>
<body>
    <main>
        <h1><?php echo htmlspecialchars($title); ?></h1>
    </main>
</body>
</html>
`,
                    };
                }

                return {
                    fileName: `${baseName}Page.tsx`,
                    content: `export default function ${baseName}Page() {
  return (
    <main>
      <h1>${baseName} page</h1>
    </main>
  );
}
`,
                };
            }

            if (s === 'laravel') {
                return {
                    fileName: `${baseName}Controller.php`,
                    content: `<` + `?php

namespace App\\Http\\Controllers;

use Illuminate\\Http\\Request;

class ${baseName}Controller extends Controller
{
    public function index()
    {
        return view('${baseName.toLowerCase()}.index');
    }
}
`,
                };
            }

            return {
                fileName: `${baseName}Controller.php`,
                content: `<` + `?php

class ${baseName}Controller
{
    public function index(): void
    {
        echo '${baseName}Controller@index';
    }
}
`,
            };
        }

        async function createQuickScaffold(kind) {
            const map = {
                component: 'Nazwa komponentu (np. UserCard):',
                page: 'Nazwa strony (np. Dashboard):',
                controller: 'Nazwa kontrolera (np. User):',
            };
            const stack = (prompt('Stack: react | php | laravel', kind === 'controller' ? 'laravel' : 'react') || '').trim().toLowerCase();
            if (!['react', 'php', 'laravel'].includes(stack)) {
                setStatus('Nieznany stack');
                return;
            }

            if (stack === 'laravel' && kind !== 'controller') {
                setStatus('Laravel quick action aktualnie wspiera tylko controller');
                return;
            }

            if (stack === 'react' && kind === 'controller') {
                setStatus('Dla React wybierz component lub page');
                return;
            }

            const rawName = prompt(map[kind] || 'Nazwa:');
            if (!rawName) return;

            const baseName = rawName.replace(/[^A-Za-z0-9_]/g, '').trim();
            if (!baseName) {
                setStatus('Niepoprawna nazwa');
                return;
            }

            const tpl = scaffoldTemplate(kind, baseName, stack);
            const data = await apiPost('create_file', {
                parentPath: state.current,
                fileName: tpl.fileName,
                content: tpl.content,
            });

            if (data.error) {
                setStatus(`Blad: ${data.error}`);
                return;
            }

            await loadDir(state.current);
            setStatus(`Utworzono: ${tpl.fileName} (${stack})`);
        }

        async function findAndReplaceInCurrentFolder() {
            const find = prompt('Znajdz tekst (dokladne dopasowanie):');
            if (!find) return;

            const replace = prompt('Zamien na (moze byc puste):', '') ?? '';
            const extensions = prompt('Rozszerzenia (CSV, np. php,js,ts,tsx,html,css,md):', 'php,js,ts,tsx,jsx,css,html,md') || '';
            const maxFiles = prompt('Limit plikow do skanowania (1-400):', '150') || '150';
            const dryRunChoice = (prompt('Tryb: podglad (dry-run) czy zapis? wpisz: preview albo apply', 'preview') || 'preview').trim().toLowerCase();
            const dryRun = dryRunChoice !== 'apply';

            if (!dryRun && !confirm('Ta operacja zapisze zmiany w wielu plikach. Kontynuowac?')) return;

            setStatus(dryRun ? 'Analizuje zmiany (dry-run)...' : 'Wykonuje znajdz i zamien...');
            const data = await apiPost('find_replace_text', {
                basePath: state.current || state.root,
                find,
                replace,
                extensions,
                maxFiles,
                dryRun: dryRun ? '1' : '0',
            });

            if (data.error) {
                setStatus(`Blad: ${data.error}`);
                return;
            }

            const changed = Array.isArray(data.changed) ? data.changed : [];
            const rows = changed.map((item) => {
                const path = escapeHtml(item.path || '');
                const reps = Number(item.replacements || 0);
                return `<div style="padding:8px; border-bottom:1px solid rgba(255,255,255,0.1); cursor:pointer;" data-path="${path}"><strong>${path.split('\\').pop() || path}</strong><br><span style="color:#94a3b8;">Zamian: ${reps}</span></div>`;
            }).join('');

            const modeName = data.dryRun ? 'Podglad zmian' : 'Zastosowano zmiany';
            previewTitleEl.textContent = `${modeName}: pliki ${data.filesChanged || 0}, zamiany ${data.totalReplacements || 0}`;
            previewBodyEl.innerHTML = rows || '<div class="sub">Brak dopasowan</div>';
            previewPaneEl.classList.add('open');

            previewBodyEl.querySelectorAll('[data-path]').forEach((el) => {
                el.addEventListener('click', async () => {
                    const path = el.getAttribute('data-path') || '';
                    const fileName = path.split('\\').pop() || path;
                    await openEditor(path, fileName);
                });
            });

            if (!data.dryRun) {
                await loadDir(state.current || state.root);
            }
            setStatus(data.dryRun ? 'Podglad zmian gotowy' : 'Znajdz i zamien zakonczone');
        }

        async function searchTextInCurrentFolder(queryInput) {
            const query = (queryInput || '').trim() || prompt('Szukaj tekstu w plikach (w aktualnym folderze i podfolderach):');
            if (!query) return;

            setStatus('Szukam w plikach...');
            const data = await apiPost('search_text', {
                basePath: state.current || state.root,
                query,
                maxResults: '120',
            });

            if (data.error) {
                setStatus(`Blad: ${data.error}`);
                return;
            }

            const rows = (data.results || []).map((r) => {
                return `<div style="padding:8px; border-bottom:1px solid rgba(255,255,255,0.1); cursor:pointer;" data-path="${escapeHtml(r.path)}"><strong>${escapeHtml(r.name)}:${r.line}</strong><br><span style="color:#94a3b8;">${escapeHtml(r.preview || '')}</span></div>`;
            }).join('');

            previewTitleEl.textContent = `Wyniki wyszukiwania: ${query} (${data.count || 0})`;
            previewBodyEl.innerHTML = rows || '<div class="sub">Brak wynikow</div>';
            previewPaneEl.classList.add('open');

            previewBodyEl.querySelectorAll('[data-path]').forEach((el) => {
                el.addEventListener('click', async () => {
                    const path = el.getAttribute('data-path');
                    const fileName = path.split('\\').pop() || path;
                    await openEditor(path, fileName);
                });
            });

            setStatus('Wyszukiwanie zakonczone');
        }

        function openSearchInline() {
            const box = document.getElementById('searchInline');
            box.classList.add('open');
            const input = document.getElementById('searchTextInput');
            input.focus();
            input.select();
        }

        function closeSearchInline() {
            const box = document.getElementById('searchInline');
            box.classList.remove('open');
        }

        async function renameSelected() {
            if (!state.selectedPath) {
                setStatus('Najpierw zaznacz plik/folder');
                return;
            }
            const currentName = state.selectedPath.split('\\').pop();
            const newName = prompt('Nowa nazwa:', currentName);
            if (!newName || newName === currentName) return;

            const data = await apiPost('rename', { path: state.selectedPath, newName });
            if (data.error) {
                setStatus(`Blad: ${data.error}`);
                return;
            }
            await loadDir(state.current);
            setStatus('Nazwa zmieniona');
        }

        async function moveSelected() {
            if (!state.selectedPath) {
                setStatus('Najpierw zaznacz plik/folder');
                return;
            }
            const target = prompt('Podaj docelowy folder (pelna sciezka w root):', state.current);
            if (!target) return;

            const data = await apiPost('move', { path: state.selectedPath, targetDir: target });
            if (data.error) {
                setStatus(`Blad: ${data.error}`);
                return;
            }
            await loadDir(state.current);
            setStatus('Element przeniesiony');
        }

        async function deleteSelected() {
            if (!state.selectedPath) {
                setStatus('Najpierw zaznacz plik/folder');
                return;
            }
            if (!confirm('Na pewno usunac zaznaczony element?')) return;

            const data = await apiPost('delete', { path: state.selectedPath });
            if (data.error) {
                setStatus(`Blad: ${data.error}`);
                return;
            }
            await loadDir(state.current);
            setStatus('Usunieto');
        }

        async function uploadFiles(files, relativePaths, extractZip) {
            if (!files || files.length === 0) return;

            setStatus('Wysylanie plikow...');
            const fd = new FormData();
            fd.append('action', 'upload');
            fd.append('parentPath', state.current);
            fd.append('extractZip', extractZip ? '1' : '0');

            files.forEach((f) => fd.append('files[]', f));
            relativePaths.forEach((p) => fd.append('relativePaths[]', p));

            const res = await fetch('../api.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.error) {
                setStatus(`Blad uploadu: ${data.error}`);
                return;
            }

            await loadDir(state.current);
            setStatus('Upload zakonczony');
        }

        function applyTerminalSavedPosition() {
            try {
                const raw = localStorage.getItem(TERM_POS_STORAGE_KEY);
                if (!raw) return;
                const pos = JSON.parse(raw);
                if (typeof pos.left === 'number' && typeof pos.top === 'number') {
                    terminalWindowEl.style.left = `${pos.left}px`;
                    terminalWindowEl.style.top = `${pos.top}px`;
                    terminalWindowEl.style.right = 'auto';
                    terminalWindowEl.style.bottom = 'auto';
                }
            } catch (e) {
                // ignore bad storage
            }
        }

        function saveTerminalSize() {
            const rect = terminalWindowEl.getBoundingClientRect();
            localStorage.setItem(TERM_SIZE_STORAGE_KEY, JSON.stringify({
                width: Math.round(rect.width),
                height: Math.round(rect.height),
            }));
        }

        function applyTerminalSavedSize() {
            try {
                const raw = localStorage.getItem(TERM_SIZE_STORAGE_KEY);
                if (!raw) return;
                const size = JSON.parse(raw);
                if (typeof size.width === 'number' && size.width >= 360) {
                    terminalWindowEl.style.width = `${size.width}px`;
                }
                if (typeof size.height === 'number' && size.height >= 220) {
                    terminalWindowEl.style.height = `${size.height}px`;
                }
            } catch (e) {
                // ignore bad storage
            }
        }

        function setTerminalSizePreset(sizeName) {
            const map = {
                sm: { width: 520, height: 260 },
                md: { width: 760, height: 340 },
                lg: { width: 980, height: 460 },
            };
            const cfg = map[sizeName] || map.md;
            terminalWindowEl.style.width = `${Math.min(cfg.width, window.innerWidth - 20)}px`;
            terminalWindowEl.style.height = `${Math.min(cfg.height, window.innerHeight - 60)}px`;
            saveTerminalSize();
        }

        function openTerminal() {
            state.terminalMinimized = false;
            terminalDockItemEl.classList.add('hidden');
            terminalWindowEl.classList.add('open');
            document.getElementById('termInput').focus();
        }

        function closeTerminal() {
            state.terminalMinimized = false;
            terminalDockItemEl.classList.add('hidden');
            terminalWindowEl.classList.remove('open');
        }

        function minimizeTerminal() {
            state.terminalMinimized = true;
            terminalWindowEl.classList.remove('open');
            terminalDockItemEl.classList.remove('hidden');
        }

        function toggleTerminal() {
            if (terminalWindowEl.classList.contains('open')) {
                minimizeTerminal();
            } else {
                openTerminal();
            }
        }

        function setupTerminalDrag() {
            const head = document.getElementById('terminalHead');
            let dragging = false;
            let offsetX = 0;
            let offsetY = 0;

            head.addEventListener('mousedown', (e) => {
                dragging = true;
                const rect = terminalWindowEl.getBoundingClientRect();
                offsetX = e.clientX - rect.left;
                offsetY = e.clientY - rect.top;
                terminalWindowEl.style.right = 'auto';
                terminalWindowEl.style.bottom = 'auto';
                terminalWindowEl.style.left = `${rect.left}px`;
                terminalWindowEl.style.top = `${rect.top}px`;
            });

            window.addEventListener('mousemove', (e) => {
                if (!dragging) return;
                const left = Math.max(0, Math.min(window.innerWidth - 320, e.clientX - offsetX));
                const top = Math.max(0, Math.min(window.innerHeight - 180, e.clientY - offsetY));
                terminalWindowEl.style.left = `${left}px`;
                terminalWindowEl.style.top = `${top}px`;
            });

            window.addEventListener('mouseup', () => {
                if (!dragging) return;
                dragging = false;
                const rect = terminalWindowEl.getBoundingClientRect();
                localStorage.setItem(TERM_POS_STORAGE_KEY, JSON.stringify({ left: rect.left, top: rect.top }));
                saveTerminalSize();
            });

            if (typeof ResizeObserver !== 'undefined') {
                const ro = new ResizeObserver(() => {
                    if (terminalWindowEl.classList.contains('open')) {
                        saveTerminalSize();
                    }
                });
                ro.observe(terminalWindowEl);
            }
        }

        async function runTerminalCommand() {
            const input = document.getElementById('termInput');
            const cmd = input.value.trim();
            if (!cmd) return;

            const out = document.getElementById('termOut');
            out.innerHTML += `<div class="term-line cmd">$ ${escapeHtml(cmd)}</div>`;
            input.value = '';

            const data = await apiPost('execute', { cmd, cwd: state.current });
            if (data.error) {
                out.innerHTML += `<div class="term-line err">[blad] ${escapeHtml(data.error)}</div>`;
            } else {
                out.innerHTML += `<div class="term-line ok">${escapeHtml(data.output).replace(/\n/g, '<br>')}</div>`;
            }

            out.scrollTop = out.scrollHeight;
        }

        function togglePinSelected() {
            if (!state.selectedPath || state.selectedType !== 'directory') {
                setStatus('Zaznacz folder, aby przypiac');
                return;
            }

            const pins = getPins();
            const idx = pins.indexOf(state.selectedPath);
            if (idx >= 0) {
                pins.splice(idx, 1);
                setStatus('Folder odpięty');
            } else {
                pins.push(state.selectedPath);
                setStatus('Folder przypięty');
            }
            savePins(pins);
            renderPins();
            selectRow(state.selectedPath, state.selectedType);
            updatePinButtonVisual(state.selectedPath, state.selectedType);
        }

        collapseBtnEl.addEventListener('click', (e) => {
            e.stopPropagation();
            appEl.classList.toggle('collapsed');
            collapseBtnEl.textContent = appEl.classList.contains('collapsed') ? '>' : '<';
            document.getElementById('profileTrigger').classList.toggle('compact', appEl.classList.contains('collapsed'));
        });

        document.getElementById('profileTrigger').addEventListener('click', () => {
            if (appEl.classList.contains('collapsed')) {
                return;
            }
            document.getElementById('userMenu').classList.toggle('open');
        });

        if (appSwitchEl && appSwitchBtnEl) {
            appSwitchBtnEl.addEventListener('click', (e) => {
                e.stopPropagation();
                const isOpen = appSwitchEl.classList.toggle('open');
                appSwitchBtnEl.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            });
        }

        document.getElementById('settingsBtn').addEventListener('click', (e) => {
            e.stopPropagation();
            setStatus('Ustawienia beda dostepne w kolejnym kroku.');
        });

        mainViewButtonsEl.forEach((button) => {
            button.addEventListener('click', async () => {
                const view = button.getAttribute('data-main-view') || 'dashboard';
                await setMainView(view);
                if (view === 'explorer' && state.root) {
                    await loadDir(state.root);
                }
            });
        });

        function syncMobileToolsState() {
            if (!topToolsEl || !mobileToolsToggleEl) return;
            if (window.innerWidth > 640) {
                topToolsEl.classList.remove('open');
                mobileToolsToggleEl.textContent = 'Narzędzia';
                return;
            }

            const isOpen = topToolsEl.classList.contains('open');
            mobileToolsToggleEl.textContent = isOpen ? 'Ukryj narzędzia' : 'Narzędzia';
        }

        if (mobileToolsToggleEl && topToolsEl) {
            mobileToolsToggleEl.addEventListener('click', () => {
                topToolsEl.classList.toggle('open');
                syncMobileToolsState();
            });

            window.addEventListener('resize', syncMobileToolsState);
            syncMobileToolsState();
        }

        function openAdminOverlay(panelUrl, panelTitle) {
            if (!adminOverlayEl || !adminFrameEl) {
                window.location.href = panelUrl;
                return;
            }

            const targetUrl = `${panelUrl}?embed=1`;
            adminFrameEl.setAttribute('src', targetUrl);

            if (adminPanelTitleEl) {
                adminPanelTitleEl.textContent = panelTitle || 'Panel administracyjny';
            }
            if (openAdminPanelTabEl) {
                openAdminPanelTabEl.setAttribute('href', panelUrl);
            }

            adminOverlayEl.classList.add('open');
            adminOverlayEl.setAttribute('aria-hidden', 'false');
        }

        function closeAdminOverlay() {
            if (!adminOverlayEl) {
                return;
            }

            adminOverlayEl.classList.remove('open');
            adminOverlayEl.setAttribute('aria-hidden', 'true');
        }

        function openTasksOverlay() {
            if (!tasksOverlayEl) return;
            tasksOverlayEl.classList.add('open');
            tasksOverlayEl.setAttribute('aria-hidden', 'false');
            renderTasks();
        }

        function closeTasksOverlay() {
            if (!tasksOverlayEl) return;
            tasksOverlayEl.classList.remove('open');
            tasksOverlayEl.setAttribute('aria-hidden', 'true');
        }

        adminPanelButtonsEl.forEach((button) => {
            button.addEventListener('click', () => {
                const panelUrl = button.getAttribute('data-panel-url') || 'access.php';
                const panelTitle = button.getAttribute('data-panel-title') || 'Panel administracyjny';
                openAdminOverlay(panelUrl, panelTitle);
            });
        });

        if (closeAdminOverlayBtnEl) {
            closeAdminOverlayBtnEl.addEventListener('click', closeAdminOverlay);
        }

        if (openTasksOverlayBtnEl) {
            openTasksOverlayBtnEl.addEventListener('click', openTasksOverlay);
        }

        if (closeTasksOverlayBtnEl) {
            closeTasksOverlayBtnEl.addEventListener('click', closeTasksOverlay);
        }

        if (adminOverlayEl) {
            adminOverlayEl.addEventListener('click', (e) => {
                if (e.target === adminOverlayEl) {
                    closeAdminOverlay();
                }
            });
        }

        if (tasksOverlayEl) {
            tasksOverlayEl.addEventListener('click', (e) => {
                if (e.target === tasksOverlayEl) {
                    closeTasksOverlay();
                }
            });
        }

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && adminOverlayEl && adminOverlayEl.classList.contains('open')) {
                closeAdminOverlay();
            }
            if (e.key === 'Escape' && tasksOverlayEl && tasksOverlayEl.classList.contains('open')) {
                closeTasksOverlay();
            }
        });

        try {
            const qs = new URLSearchParams(window.location.search);
            if (qs.get('panel') === 'access') {
                openAdminOverlay('access.php', 'Dostepy');
            }
            if (qs.get('panel') === 'users') {
                openAdminOverlay('users.php', 'Uzytkownicy');
            }
            if (qs.get('panel') === 'roles') {
                openAdminOverlay('roles.php', 'Role');
            }
            if (qs.get('panel') === 'relations') {
                openAdminOverlay('relations.php', 'Relacje');
            }
            if (qs.get('panel') === 'database') {
                openAdminOverlay('database.php', 'Baza danych');
            }
            if (qs.get('panel') === 'demos') {
                openAdminOverlay('demos.php', 'Demo Links');
            }
        } catch (e) {
            // Ignore malformed query string.
        }

        if (railRootBtnEl) {
            railRootBtnEl.addEventListener('click', (e) => {
                e.stopPropagation();
                loadDir(state.root);
            });
        }

        if (railRefreshBtnEl) {
            railRefreshBtnEl.addEventListener('click', (e) => {
                e.stopPropagation();
                loadDir(state.current || state.root);
            });
        }

        if (railTerminalBtnEl) {
            railTerminalBtnEl.addEventListener('click', (e) => {
                e.stopPropagation();
                toggleTerminalWindow();
            });
        }

        if (railToolsBtnEl) {
            railToolsBtnEl.addEventListener('click', (e) => {
                e.stopPropagation();
                appEl.classList.remove('collapsed');
                collapseBtnEl.textContent = '<';
                document.getElementById('profileTrigger').classList.remove('compact');
            });
        }

        // ===== Admin FAB =====
        const fabEl = document.getElementById('adminFab');
        document.getElementById('fabMainBtn').addEventListener('click', (e) => {
            e.stopPropagation();
            fabEl.classList.toggle('open');
        });
        document.addEventListener('click', (e) => {
            if (fabEl && !fabEl.contains(e.target)) fabEl.classList.remove('open');
            if (appSwitchEl && !appSwitchEl.contains(e.target)) {
                appSwitchEl.classList.remove('open');
                if (appSwitchBtnEl) {
                    appSwitchBtnEl.setAttribute('aria-expanded', 'false');
                }
            }
        });

        document.getElementById('fabBackBtn').addEventListener('click', () => {
            fabEl.classList.remove('open');
            if (state.parent) loadDir(state.parent); else setStatus('Jestes w root');
        });
        document.getElementById('fabRefreshBtn').addEventListener('click', () => {
            fabEl.classList.remove('open');
            loadDir(state.current);
        });
        document.getElementById('fabMkdirBtn').addEventListener('click', () => {
            fabEl.classList.remove('open');
            createFolder();
        });
        document.getElementById('fabNewFileBtn').addEventListener('click', () => {
            fabEl.classList.remove('open');
            createNewFile();
        });
        document.getElementById('fabUploadFilesBtn').addEventListener('click', () => {
            fabEl.classList.remove('open');
            document.getElementById('uploadFilesInput').click();
        });
        document.getElementById('fabUploadFolderBtn').addEventListener('click', () => {
            fabEl.classList.remove('open');
            document.getElementById('uploadFolderInput').click();
        });
        document.getElementById('fabSearchBtn').addEventListener('click', () => {
            fabEl.classList.remove('open');
            const box = document.getElementById('searchInline');
            if (box.classList.contains('open')) closeSearchInline(); else openSearchInline();
        });
        document.getElementById('fabFindReplaceBtn').addEventListener('click', () => {
            fabEl.classList.remove('open');
            findAndReplaceInCurrentFolder();
        });
        document.getElementById('fabRenameBtn').addEventListener('click', () => {
            fabEl.classList.remove('open');
            renameSelected();
        });
        document.getElementById('fabMoveBtn').addEventListener('click', () => {
            fabEl.classList.remove('open');
            moveSelected();
        });
        document.getElementById('fabPinBtn').addEventListener('click', () => {
            fabEl.classList.remove('open');
            togglePinSelected();
        });
        document.getElementById('fabDeleteBtn').addEventListener('click', () => {
            fabEl.classList.remove('open');
            deleteSelected();
        });
        document.getElementById('fabTemplateBtn').addEventListener('click', () => {
            fabEl.classList.remove('open');
            createFromTemplate();
        });
        document.getElementById('fabCreateComponentBtn').addEventListener('click', () => {
            fabEl.classList.remove('open');
            createQuickScaffold('component');
        });
        document.getElementById('fabCreatePageBtn').addEventListener('click', () => {
            fabEl.classList.remove('open');
            createQuickScaffold('page');
        });
        document.getElementById('fabCreateControllerBtn').addEventListener('click', () => {
            fabEl.classList.remove('open');
            createQuickScaffold('controller');
        });

        document.getElementById('searchTextRunBtn').addEventListener('click', async () => {
            await searchTextInCurrentFolder(document.getElementById('searchTextInput').value);
        });
        document.getElementById('searchTextCloseBtn').addEventListener('click', closeSearchInline);
        document.getElementById('searchTextInput').addEventListener('keydown', async (e) => {
            if (e.key === 'Enter') await searchTextInCurrentFolder(document.getElementById('searchTextInput').value);
            if (e.key === 'Escape') closeSearchInline();
        });
        document.getElementById('addTaskBtn').addEventListener('click', addTaskShortcut);
        document.getElementById('resetTasksBtn').addEventListener('click', resetTasks);
        document.getElementById('exportTasksBtn').addEventListener('click', exportTasks);
        document.getElementById('importTasksBtn').addEventListener('click', () => document.getElementById('importTasksInput').click());
        document.getElementById('importTasksInput').addEventListener('change', async (e) => {
            const file = (e.target.files || [])[0];
            await importTasksFromFile(file);
            e.target.value = '';
        });

        document.getElementById('uploadFilesInput').addEventListener('change', async (e) => {
            const files = Array.from(e.target.files || []);
            const rel = files.map((f) => f.name);
            await uploadFiles(files, rel, true);
            e.target.value = '';
        });

        document.getElementById('uploadFolderInput').addEventListener('change', async (e) => {
            const files = Array.from(e.target.files || []);
            const rel = files.map((f) => f.webkitRelativePath || f.name);
            await uploadFiles(files, rel, false);
            e.target.value = '';
        });

        document.getElementById('toggleTerminalBtn').addEventListener('click', toggleTerminal);
        document.getElementById('terminalTabBtn').addEventListener('click', () => {
            const url = new URL('../terminal.php', window.location.href);
            if (state.current) {
                url.searchParams.set('cwd', state.current);
            }
            window.open(url.toString(), '_blank', 'noopener');
        });
        document.getElementById('terminalSizeSmBtn').addEventListener('click', () => setTerminalSizePreset('sm'));
        document.getElementById('terminalSizeMdBtn').addEventListener('click', () => setTerminalSizePreset('md'));
        document.getElementById('terminalSizeLgBtn').addEventListener('click', () => setTerminalSizePreset('lg'));
        document.getElementById('termRunBtn').addEventListener('click', runTerminalCommand);
        document.getElementById('termInput').addEventListener('keydown', (e) => {
            if (e.key === 'Enter') runTerminalCommand();
        });

        document.getElementById('closePreviewBtn').addEventListener('click', () => previewPaneEl.classList.remove('open'));

        document.getElementById('closeEditorBtn').addEventListener('click', closeEditor);
        document.getElementById('saveEditorBtn').addEventListener('click', saveEditor);
        document.getElementById('editorCodePreviewBtn').addEventListener('click', openCodePreviewModal);
        document.getElementById('editorPreviewBtn').addEventListener('click', () => editorPreview(false));
        document.getElementById('editorPreviewTabBtn').addEventListener('click', () => editorPreview(true));
        document.getElementById('editorPath').addEventListener('dblclick', openCodePreviewModal);
        document.getElementById('editorModal').addEventListener('click', (e) => {
            if (e.target.id === 'editorModal') closeEditor();
        });

        document.getElementById('terminalMinBtn').addEventListener('click', minimizeTerminal);
        document.getElementById('terminalCloseBtn').addEventListener('click', closeTerminal);
        terminalDockItemEl.addEventListener('click', openTerminal);

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closeEditor();
                previewPaneEl.classList.remove('open');
            }
            if (e.altKey && e.key === 'ArrowLeft') {
                if (state.parent) {
                    loadDir(state.parent);
                }
            }
            if (e.key === '`') {
                toggleTerminal();
            }
        });

        applyTerminalSavedPosition();
        applyTerminalSavedSize();
        setupTerminalDrag();
        updatePinButtonVisual('', '');
        loadRoot();
    </script>
</body>
</html>
