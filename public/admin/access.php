<?php
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
        ]);
    }

    $fallbackRole = startowa_normalize_role((string) ($_SESSION['rola'] ?? 'user'));
    if (!in_array($fallbackRole, ['admin', 'owner'], true)) {
        header('Location: ../index.php');
        exit();
    }
}

$allApps = STARTOWA_APPS;
$notice = '';
$error = '';
$isEmbedded = isset($_GET['embed']) && $_GET['embed'] === '1';

$roleTableExists = startowa_table_exists($pdo, 'startowa_roles');
$roleAppTableExists = startowa_table_exists($pdo, 'startowa_role_app_assignments');
$userAppTableExists = startowa_table_exists($pdo, 'startowa_user_app_assignments');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    try {
        if ($action === 'update_user_role') {
            $userId = (int) ($_POST['user_id'] ?? 0);
            $newRole = startowa_normalize_role((string) ($_POST['role'] ?? 'user'));

            if ($userId <= 0) {
                throw new RuntimeException('Nieprawidlowy uzytkownik.');
            }

            $stmt = $pdo->prepare('UPDATE uzytkownicy SET rola = ? WHERE id = ?');
            $stmt->execute([$newRole, $userId]);
            $notice = 'Zaktualizowano role uzytkownika.';
        }

        if ($action === 'update_role_apps') {
            if (!$roleAppTableExists) {
                throw new RuntimeException('Brak tabeli startowa_role_app_assignments. Uruchom SQL migracji.');
            }

            $roleKey = startowa_normalize_role((string) ($_POST['role_key'] ?? ''));
            $apps = array_values(array_intersect(array_keys($allApps), (array) ($_POST['apps'] ?? [])));

            if ($roleKey === '') {
                throw new RuntimeException('Rola nie moze byc pusta.');
            }

            $pdo->beginTransaction();
            $del = $pdo->prepare('DELETE FROM startowa_role_app_assignments WHERE role_key = ?');
            $del->execute([$roleKey]);

            if (!empty($apps)) {
                $ins = $pdo->prepare('INSERT INTO startowa_role_app_assignments (role_key, app_key) VALUES (?, ?)');
                foreach ($apps as $appKey) {
                    $ins->execute([$roleKey, $appKey]);
                }
            }
            $pdo->commit();
            $notice = 'Zapisano przypisania aplikacji do roli.';
        }

        if ($action === 'update_user_apps') {
            if (!$userAppTableExists) {
                throw new RuntimeException('Brak tabeli startowa_user_app_assignments. Uruchom SQL migracji.');
            }

            $userId = (int) ($_POST['user_id'] ?? 0);
            $apps = array_values(array_intersect(array_keys($allApps), (array) ($_POST['apps'] ?? [])));

            if ($userId <= 0) {
                throw new RuntimeException('Nieprawidlowy uzytkownik.');
            }

            $pdo->beginTransaction();
            $del = $pdo->prepare('DELETE FROM startowa_user_app_assignments WHERE user_id = ?');
            $del->execute([$userId]);

            if (!empty($apps)) {
                $ins = $pdo->prepare('INSERT INTO startowa_user_app_assignments (user_id, app_key) VALUES (?, ?)');
                foreach ($apps as $appKey) {
                    $ins->execute([$userId, $appKey]);
                }
            }
            $pdo->commit();
            $notice = 'Zapisano indywidualne dostepy uzytkownika.';
        }

        startowa_refresh_access_cache();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = $e->getMessage();
    }
}

$roles = [];
if ($roleTableExists) {
    $rolesStmt = $pdo->query('SELECT `key`, `name` FROM startowa_roles ORDER BY is_system DESC, name ASC');
    $roles = $rolesStmt->fetchAll(PDO::FETCH_ASSOC);
}

if (empty($roles)) {
    $rolesStmt = $pdo->query('SELECT DISTINCT LOWER(TRIM(COALESCE(rola, "user"))) AS role_key FROM uzytkownicy ORDER BY role_key ASC');
    $rawRoles = $rolesStmt->fetchAll(PDO::FETCH_COLUMN);
    foreach ($rawRoles as $rawRole) {
        $roleKey = startowa_normalize_role((string) $rawRole);
        if ($roleKey === '') {
            continue;
        }
        $roles[] = ['key' => $roleKey, 'name' => ucfirst($roleKey)];
    }
}

if (empty($roles)) {
    $roles[] = ['key' => 'user', 'name' => 'User'];
}

$roleKeys = array_map(static function (array $r): string {
    return (string) $r['key'];
}, $roles);

$usersStmt = $pdo->query('SELECT id, imie, nazwisko, email, COALESCE(rola, "user") AS rola FROM uzytkownicy ORDER BY id DESC');
$users = $usersStmt->fetchAll(PDO::FETCH_ASSOC);

$roleAssignments = [];
if ($roleAppTableExists) {
    $rows = $pdo->query('SELECT role_key, app_key FROM startowa_role_app_assignments ORDER BY role_key, app_key')->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
        $rk = startowa_normalize_role((string) $row['role_key']);
        $ak = strtolower(trim((string) $row['app_key']));
        $roleAssignments[$rk] ??= [];
        if (!in_array($ak, $roleAssignments[$rk], true)) {
            $roleAssignments[$rk][] = $ak;
        }
    }
}

$userAssignments = [];
if ($userAppTableExists) {
    $rows = $pdo->query('SELECT user_id, app_key FROM startowa_user_app_assignments ORDER BY user_id, app_key')->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
        $uid = (int) $row['user_id'];
        $ak = strtolower(trim((string) $row['app_key']));
        $userAssignments[$uid] ??= [];
        if (!in_array($ak, $userAssignments[$uid], true)) {
            $userAssignments[$uid][] = $ak;
        }
    }
}
?>
<!doctype html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dostepy i role - Startowa</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <style>
        :root {
            --bg: #0b1220;
            --card: #0f1b32;
            --line: rgba(148,163,184,.28);
            --txt: #e2e8f0;
            --muted: #94a3b8;
            --acc: #38bdf8;
        }

        body {
            background: radial-gradient(circle at 0% 0%, #122340, var(--bg) 52%, #081123 100%);
            color: var(--txt);
            font-family: Segoe UI, Tahoma, sans-serif;
        }

        body.embed {
            background: transparent;
        }

        .card {
            background: linear-gradient(180deg, rgba(15,23,42,.88), rgba(10,18,35,.88));
            border: 1px solid var(--line);
            color: var(--txt);
            border-radius: 14px;
        }

        .app-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill,minmax(170px,1fr));
            gap: 8px;
        }

        .app-chip {
            border: 1px solid var(--line);
            border-radius: 10px;
            padding: 8px 10px;
            background: rgba(255,255,255,.03);
            transition: border-color .15s ease, background .15s ease;
        }

        .app-chip:hover {
            border-color: var(--acc);
            background: rgba(56,189,248,.08);
        }

        .app-chip input {
            margin-right: 6px;
        }

        .muted { color: var(--muted); }

        .table {
            color: var(--txt);
            margin-bottom: 0;
        }

        .table thead th,
        .table tbody td {
            border-color: var(--line);
            background: rgba(255,255,255,.02);
        }

        .table tbody tr:hover td {
            background: rgba(56,189,248,.06);
        }

        .form-select,
        .form-control {
            background: rgba(15,23,42,.9);
            border: 1px solid var(--line);
            color: var(--txt);
        }

        .form-select:focus,
        .form-control:focus {
            border-color: var(--acc);
            box-shadow: 0 0 0 .2rem rgba(56,189,248,.2);
        }

        .btn-outline-light {
            border-color: var(--line);
            color: var(--txt);
        }

        .btn-outline-light:hover {
            border-color: var(--acc);
            background: rgba(56,189,248,.12);
            color: #dff7ff;
        }

        .table-responsive {
            border: 1px solid var(--line);
            border-radius: 12px;
            overflow: auto;
        }

        body.embed .container {
            padding-top: 8px !important;
            max-width: 100%;
        }

        body.embed .header-actions {
            display: none !important;
        }
    </style>
</head>
<body class="<?php echo $isEmbedded ? 'embed' : ''; ?>">
<div class="container py-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">Role i dostepy aplikacji</h1>
            <div class="muted">Zarzadzanie dostepem do DJ, Optivio, Taski, Taskora i panelu admina.</div>
        </div>
        <div class="d-flex gap-2 header-actions">
            <a class="btn btn-outline-light" href="index.php">Wroc do Server Hub</a>
            <a class="btn btn-outline-light" href="../index.php">Panel usera</a>
            <a class="btn btn-danger" href="../logout.php">Wyloguj</a>
        </div>
    </div>

    <?php if ($notice !== ''): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($notice, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <?php if (!$roleTableExists || !$roleAppTableExists || !$userAppTableExists): ?>
        <div class="alert alert-warning">Czesc tabel RBAC nie istnieje jeszcze w bazie. Uruchom SQL z pliku database/add_access_control_tables.sql.</div>
    <?php endif; ?>

    <div class="row g-3">
        <div class="col-12 col-xl-5">
            <div class="card p-3 h-100">
                <h2 class="h5">Przypisania aplikacji do roli</h2>
                <p class="muted mb-3">Uzytkownik dostaje te aplikacje na podstawie roli.</p>

                <?php foreach ($roles as $role): ?>
                    <?php $roleKey = (string) $role['key']; ?>
                    <form method="post" class="mb-4">
                        <input type="hidden" name="action" value="update_role_apps">
                        <input type="hidden" name="role_key" value="<?php echo htmlspecialchars($roleKey, ENT_QUOTES, 'UTF-8'); ?>">
                        <h3 class="h6 mb-2"><?php echo htmlspecialchars((string) $role['name'], ENT_QUOTES, 'UTF-8'); ?> <span class="muted">(<?php echo htmlspecialchars($roleKey, ENT_QUOTES, 'UTF-8'); ?>)</span></h3>
                        <div class="app-grid mb-2">
                            <?php foreach ($allApps as $appKey => $appLabel): ?>
                                <label class="app-chip">
                                    <input type="checkbox" name="apps[]" value="<?php echo htmlspecialchars($appKey, ENT_QUOTES, 'UTF-8'); ?>"
                                        <?php echo in_array($appKey, (array) ($roleAssignments[$roleKey] ?? []), true) ? 'checked' : ''; ?>>
                                    <?php echo htmlspecialchars($appLabel, ENT_QUOTES, 'UTF-8'); ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <button class="btn btn-sm btn-primary" type="submit">Zapisz dla roli</button>
                    </form>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="col-12 col-xl-7">
            <div class="card p-3">
                <h2 class="h5">Uzytkownicy</h2>
                <p class="muted">Edycja roli i indywidualnych dostepow per uzytkownik.</p>
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Uzytkownik</th>
                                <th>Rola</th>
                                <th>Dostepy indywidualne</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($users as $user): ?>
                            <?php $uid = (int) $user['id']; ?>
                            <tr>
                                <td><?php echo $uid; ?></td>
                                <td>
                                    <div><?php echo htmlspecialchars(trim(((string) $user['imie']) . ' ' . ((string) $user['nazwisko'])), ENT_QUOTES, 'UTF-8'); ?></div>
                                    <div class="muted"><?php echo htmlspecialchars((string) $user['email'], ENT_QUOTES, 'UTF-8'); ?></div>
                                </td>
                                <td>
                                    <form method="post" class="d-flex gap-2 align-items-center">
                                        <input type="hidden" name="action" value="update_user_role">
                                        <input type="hidden" name="user_id" value="<?php echo $uid; ?>">
                                        <select class="form-select form-select-sm" name="role">
                                            <?php foreach ($roleKeys as $rk): ?>
                                                <option value="<?php echo htmlspecialchars($rk, ENT_QUOTES, 'UTF-8'); ?>"
                                                    <?php echo startowa_normalize_role((string) $user['rola']) === $rk ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($rk, ENT_QUOTES, 'UTF-8'); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button class="btn btn-sm btn-outline-light" type="submit">Zmien</button>
                                    </form>
                                </td>
                                <td>
                                    <form method="post">
                                        <input type="hidden" name="action" value="update_user_apps">
                                        <input type="hidden" name="user_id" value="<?php echo $uid; ?>">
                                        <div class="app-grid mb-2">
                                            <?php foreach ($allApps as $appKey => $appLabel): ?>
                                                <label class="app-chip">
                                                    <input type="checkbox" name="apps[]" value="<?php echo htmlspecialchars($appKey, ENT_QUOTES, 'UTF-8'); ?>"
                                                        <?php echo in_array($appKey, (array) ($userAssignments[$uid] ?? []), true) ? 'checked' : ''; ?>>
                                                    <?php echo htmlspecialchars($appLabel, ENT_QUOTES, 'UTF-8'); ?>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                        <button class="btn btn-sm btn-outline-info" type="submit">Zapisz user access</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
