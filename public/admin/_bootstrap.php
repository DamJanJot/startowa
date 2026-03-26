<?php

declare(strict_types=1);

if (is_file(__DIR__ . '/../../core/access_control.php')) {
    require_once __DIR__ . '/../../core/access_control.php';
    startowa_require_admin_panel();
} else {
    require_once __DIR__ . '/../../core/db.php';

    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
        header('Location: ../login.php');
        exit();
    }

    if (!function_exists('startowa_normalize_role')) {
        function startowa_normalize_role(?string $role): string
        {
            $normalized = strtolower(trim((string) $role));
            return $normalized !== '' ? $normalized : 'user';
        }
    }

    if (!function_exists('startowa_table_exists')) {
        function startowa_table_exists(PDO $pdo, string $tableName): bool
        {
            try {
                $stmt = $pdo->prepare('SHOW TABLES LIKE ?');
                $stmt->execute([$tableName]);
                return (bool) $stmt->fetchColumn();
            } catch (Throwable $e) {
                return false;
            }
        }
    }

    if (!function_exists('startowa_refresh_access_cache')) {
        function startowa_refresh_access_cache(): void
        {
            unset($_SESSION['access']);
        }
    }

    if (!defined('STARTOWA_APPS')) {
        define('STARTOWA_APPS', [
            'dashboard' => 'Dashboard',
            'dj' => 'DamJanJot DJ',
            'optivio' => 'Optivio',
            'taski' => 'Taski',
            'taskora' => 'Taskora',
            'admin_panel' => 'Panel admina',
            'server_hub' => 'Server Hub',
            'neuronetix' => 'Neuronetix',
        ]);
    }

    $fallbackRole = startowa_normalize_role((string) ($_SESSION['rola'] ?? 'user'));
    if (!in_array($fallbackRole, ['admin', 'owner'], true)) {
        header('Location: ../index.php');
        exit();
    }
}

$userName = $_SESSION['imie'] ?? 'Uzytkownik';
$userEmail = $_SESSION['email'] ?? '';
$userRole = startowa_normalize_role((string) ($_SESSION['rola'] ?? 'user'));

function panel_is_embedded(): bool
{
    return isset($_GET['embed']) && $_GET['embed'] === '1';
}

function panel_layout_start(string $title, string $subtitle): void
{
    $embedded = panel_is_embedded();
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!doctype html><html lang="pl"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">';
    echo '<title>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</title>';
    echo '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">';
    echo '<style>';
    echo ':root{--bg:#0b1220;--line:rgba(148,163,184,.28);--txt:#e2e8f0;--muted:#94a3b8;--acc:#38bdf8}';
    echo 'body{background:radial-gradient(circle at 0% 0%,#122340,var(--bg) 52%,#081123 100%);color:var(--txt);font-family:Segoe UI,Tahoma,sans-serif}';
    echo 'body.embed{background:transparent}.card{background:linear-gradient(180deg,rgba(15,23,42,.88),rgba(10,18,35,.88));border:1px solid var(--line);color:var(--txt);border-radius:14px}';
    echo '.muted{color:var(--muted)}.table{color:var(--txt);margin-bottom:0}.table td,.table th{border-color:var(--line);background:rgba(255,255,255,.02)!important}';
    echo '.table-responsive{border:1px solid var(--line);border-radius:12px;overflow:auto}.form-control,.form-select{background:rgba(15,23,42,.9);border:1px solid var(--line);color:var(--txt)}';
    echo '.form-control::placeholder{color:#9db0c7}.form-select option{background:#0f172a;color:var(--txt)}';
    echo '.form-control:focus,.form-select:focus{border-color:var(--acc);box-shadow:0 0 0 .2rem rgba(56,189,248,.2)}';
    echo '.chip{border:1px solid var(--line);border-radius:10px;padding:8px 10px;background:rgba(255,255,255,.03)}';
    echo '.chip:hover{border-color:var(--acc);background:rgba(56,189,248,.08)}';
    echo '.btn-outline-light{border-color:var(--line)!important;color:var(--txt)!important}.btn-outline-light:hover{border-color:var(--acc)!important;background:rgba(56,189,248,.12)!important;color:#dff7ff!important}';
    echo '.btn-outline-info{border-color:rgba(56,189,248,.6)!important;color:#bae6fd!important}.btn-outline-info:hover{border-color:var(--acc)!important;background:rgba(56,189,248,.12)!important;color:#dff7ff!important}';
    echo '.form-label,label,.alert{color:var(--txt)}';
    echo '</style></head><body class="' . ($embedded ? 'embed' : '') . '"><div class="container py-4">';
    echo '<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">';
    echo '<div><h1 class="h3 mb-1">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h1><div class="muted">' . htmlspecialchars($subtitle, ENT_QUOTES, 'UTF-8') . '</div></div>';
    if (!$embedded) {
        echo '<div class="d-flex gap-2"><a class="btn btn-outline-light" href="index.php">Wroc do Server Hub</a><a class="btn btn-outline-light" href="../index.php">Panel usera</a><a class="btn btn-danger" href="../logout.php">Wyloguj</a></div>';
    }
    echo '</div>';
}

function panel_layout_end(): void
{
    echo '</div></body></html>';
}
