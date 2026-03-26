<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';

const STARTOWA_APPS = [
    'dashboard' => 'Dashboard',
    'dj' => 'DamJanJot DJ',
    'optivio' => 'Optivio',
    'taski' => 'Taski',
    'taskora' => 'Taskora',
    'admin_panel' => 'Panel admina',
    'server_hub' => 'Server Hub',
    'neuronetix' => 'NeuroNetix',
];

function startowa_start_session(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

function startowa_base_url(): string
{
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    foreach (['/public/', '/core/', '/assets/'] as $marker) {
        $pos = strpos($script, $marker);
        if ($pos !== false) {
            return rtrim(substr($script, 0, $pos), '/');
        }
    }

    return '';
}

function startowa_url(string $path): string
{
    $base = startowa_base_url();
    $normalized = '/' . ltrim($path, '/');

    return ($base !== '' ? $base : '') . $normalized;
}

function startowa_redirect(string $path): void
{
    if (preg_match('~^(https?:)?//~i', $path) === 1 || str_starts_with($path, '/')) {
        header('Location: ' . $path);
        exit();
    }

    header('Location: ' . startowa_url($path));
    exit();
}

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

function startowa_normalize_role(?string $role): string
{
    $normalized = strtolower(trim((string) $role));
    return $normalized !== '' ? $normalized : 'user';
}

function startowa_default_role_apps(string $role): array
{
    $all = array_keys(STARTOWA_APPS);

    $map = [
        'owner' => $all,
        'admin' => $all,
        'administrator' => $all,
        'moderator' => ['dashboard', 'dj', 'optivio', 'taski', 'taskora', 'server_hub'],
        'manager' => ['dashboard', 'dj', 'optivio', 'taski', 'taskora'],
        'analyst' => ['dashboard', 'optivio', 'taski'],
        'support' => ['dashboard', 'taski', 'server_hub'],
        'pracownik' => ['dashboard', 'optivio', 'taski'],
        'nauczyciel' => ['dashboard', 'neuronetix'],
        'teacher' => ['dashboard', 'neuronetix'],
        'uczen' => ['dashboard', 'neuronetix'],
        'student' => ['dashboard', 'neuronetix'],
        'user' => ['dashboard', 'optivio', 'taski'],
        'guest' => ['dashboard'],
    ];

    return $map[$role] ?? $map['user'];
}

function startowa_app_catalog(): array
{
    return [
        'dashboard' => [
            'label' => 'Server Hub',
            'url' => '/public/index.php',
            'icon' => '🏠',
            'desc' => 'Panel glowny i skroty do aplikacji',
        ],
        'server_hub' => [
            'label' => 'Panel Admina',
            'url' => '/public/admin/index.php',
            'icon' => '🧭',
            'desc' => 'Narzędzia administracyjne i techniczne',
        ],
        'admin_panel' => [
            'label' => 'Panel Admina',
            'url' => '/public/admin/index.php',
            'icon' => '🛡',
            'desc' => 'Role, dostepy i relacje uzytkownikow',
        ],
        'dj' => [
            'label' => 'DamJanJot DJ',
            'url' => 'https://app-dj.code-dj.pl',
            'icon' => '🎵',
            'desc' => 'Panel DJ - sety, playlisty, rynek',
        ],
        'optivio' => [
            'label' => 'Optivio',
            'url' => 'https://optivio.code-dj.pl',
            'icon' => '📊',
            'desc' => 'Moduly, galeria, notatnik i todo',
        ],
        'taski' => [
            'label' => 'Taski',
            'url' => 'https://taski.j.pl',
            'icon' => '✅',
            'desc' => 'Zarzadzanie zadaniami',
        ],
        'taskora' => [
            'label' => 'Taskora',
            'url' => 'https://taskora.code-dj.pl',
            'icon' => '📋',
            'desc' => 'Workspace i projekty',
        ],
        'neuronetix' => [
            'label' => 'Neuronetix',
            'url' => '/neuronetix/index.php',
            'icon' => '🧠',
            'desc' => 'Panel edukacyjny: uczen i nauczyciel',
        ],
    ];
}

function startowa_login_redirect_target(string $role, array $apps): string
{
    $normalizedRole = startowa_normalize_role($role);

    if (in_array($normalizedRole, ['admin', 'owner', 'administrator'], true) && in_array('admin_panel', $apps, true)) {
        return 'public/admin/index.php';
    }

    if (in_array($normalizedRole, ['uczen', 'student', 'nauczyciel', 'teacher'], true) && in_array('neuronetix', $apps, true)) {
        return '/neuronetix/index.php';
    }

    return 'public/index.php';
}

function startowa_should_redirect_to_app(string $role): ?string
{
    $roleAppsMap = [
        'uczen' => 'neuronetix',
        'student' => 'neuronetix',
        'nauczyciel' => 'neuronetix',
        'teacher' => 'neuronetix',
    ];

    return $roleAppsMap[$role] ?? null;
}

function startowa_fetch_role_apps(PDO $pdo, string $role): ?array
{
    if (!startowa_table_exists($pdo, 'startowa_role_app_assignments')) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT app_key FROM startowa_role_app_assignments WHERE role_key = ? ORDER BY app_key');
    $stmt->execute([$role]);
    $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);
    if (empty($rows)) {
        return null;
    }

    $allowed = array_keys(STARTOWA_APPS);
    $apps = [];
    foreach ($rows as $app) {
        $key = strtolower(trim((string) $app));
        if (in_array($key, $allowed, true) && !in_array($key, $apps, true)) {
            $apps[] = $key;
        }
    }

    return $apps;
}

function startowa_fetch_user_apps(PDO $pdo, int $userId): array
{
    if (!startowa_table_exists($pdo, 'startowa_user_app_assignments')) {
        return [];
    }

    $stmt = $pdo->prepare('SELECT app_key FROM startowa_user_app_assignments WHERE user_id = ? ORDER BY app_key');
    $stmt->execute([$userId]);
    $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $allowed = array_keys(STARTOWA_APPS);
    $apps = [];
    foreach ($rows as $app) {
        $key = strtolower(trim((string) $app));
        if (in_array($key, $allowed, true) && !in_array($key, $apps, true)) {
            $apps[] = $key;
        }
    }

    return $apps;
}

function startowa_resolve_user_apps(int $userId, string $role): array
{
    global $pdo;

    $apps = startowa_default_role_apps($role);
    $fromRoleTable = startowa_fetch_role_apps($pdo, $role);
    if ($fromRoleTable !== null) {
        $apps = $fromRoleTable;
    }

    $userApps = startowa_fetch_user_apps($pdo, $userId);
    if (!empty($userApps)) {
        foreach ($userApps as $key) {
            if (!in_array($key, $apps, true)) {
                $apps[] = $key;
            }
        }
    }

    sort($apps);
    return array_values($apps);
}

function startowa_require_login(): void
{
    startowa_start_session();
    if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['id'])) {
        startowa_redirect('public/login.php');
    }
}

function startowa_current_user_access(): array
{
    startowa_require_login();

    if (isset($_SESSION['access']) && is_array($_SESSION['access'])) {
        return $_SESSION['access'];
    }

    $role = startowa_normalize_role((string) ($_SESSION['rola'] ?? 'user'));
    $_SESSION['rola'] = $role;

    $apps = startowa_resolve_user_apps((int) $_SESSION['id'], $role);
    $payload = [
        'role' => $role,
        'apps' => $apps,
    ];

    $_SESSION['access'] = $payload;
    return $payload;
}

function startowa_has_app_access(string $appKey): bool
{
    $access = startowa_current_user_access();
    return in_array($appKey, (array) ($access['apps'] ?? []), true);
}

function startowa_require_admin_panel(): void
{
    startowa_require_login();

    $role = startowa_normalize_role((string) ($_SESSION['rola'] ?? ''));
    if (!in_array($role, ['admin', 'owner'], true) || !startowa_has_app_access('admin_panel')) {
        startowa_redirect('public/index.php');
    }
}

function startowa_refresh_access_cache(): void
{
    startowa_start_session();
    unset($_SESSION['access']);
}
