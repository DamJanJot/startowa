<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function demo_public_origin(): string
{
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;
    $scheme = $isHttps ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptDir = str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/public/admin/demos.php')));
    $basePath = preg_replace('#/public/admin$#', '', rtrim($scriptDir, '/'));

    return $scheme . '://' . $host . ($basePath !== '' ? $basePath : '');
}

function demo_generate_public_key(): string
{
    return bin2hex(random_bytes(8));
}

function demo_generate_target_token(): string
{
    return 'demo_' . bin2hex(random_bytes(20));
}

function demo_startowa_root(): string
{
    return realpath(__DIR__ . '/../../') ?: dirname(__DIR__, 2);
}

function demo_guess_app_root(string $appKey): string
{
    $startowaRoot = demo_startowa_root();
    $parentRoot = dirname($startowaRoot);

    if ($appKey === 'startowa') {
        return $startowaRoot;
    }

    $candidates = [
        $parentRoot . DIRECTORY_SEPARATOR . $appKey,
        $startowaRoot . DIRECTORY_SEPARATOR . $appKey,
    ];

    foreach ($candidates as $candidate) {
        if (is_dir($candidate)) {
            return $candidate;
        }
    }

    return $candidates[0];
}

function demo_resolve_app_root(string $appKey, string $manualPath): string
{
    $candidate = trim($manualPath);
    if ($candidate !== '') {
        if (preg_match('#^[A-Za-z]:\\\\#', $candidate) || str_starts_with($candidate, '/') || str_starts_with($candidate, '\\\\')) {
            return rtrim($candidate, "\\/");
        }

        $startowaRoot = demo_startowa_root();
        return rtrim($startowaRoot . DIRECTORY_SEPARATOR . $candidate, "\\/");
    }

    return demo_guess_app_root($appKey);
}

function demo_parse_env_file(string $envPath): array
{
    if (!is_file($envPath)) {
        throw new RuntimeException('Brak pliku .env dla aplikacji docelowej: ' . $envPath);
    }

    $config = [];
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || str_starts_with($trimmed, '#') || !str_contains($trimmed, '=')) {
            continue;
        }

        [$name, $value] = explode('=', $trimmed, 2);
        $config[trim($name)] = trim($value, " \t\n\r\0\x0B\"'");
    }

    return $config;
}

function demo_connect_target_pdo(string $appRoot): PDO
{
    $env = demo_parse_env_file(rtrim($appRoot, "\\/") . DIRECTORY_SEPARATOR . '.env');
    $host = $env['DB_HOST'] ?? 'localhost';
    $dbName = $env['DB_NAME'] ?? '';
    $dbUser = $env['DB_USER'] ?? '';
    $dbPass = $env['DB_PASS'] ?? '';

    if ($dbName === '' || $dbUser === '') {
        throw new RuntimeException('W .env aplikacji brakuje DB_NAME albo DB_USER.');
    }

    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8', $host, $dbName);
    return new PDO($dsn, $dbUser, $dbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
}

function demo_table_exists(PDO $pdo, string $tableName): bool
{
    $stmt = $pdo->prepare('SHOW TABLES LIKE ?');
    $stmt->execute([$tableName]);
    return (bool) $stmt->fetchColumn();
}

function demo_table_columns(PDO $pdo, string $tableName): array
{
    $stmt = $pdo->query('SHOW COLUMNS FROM `' . str_replace('`', '``', $tableName) . '`');
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $columns = [];
    foreach ($rows as $row) {
        $columns[] = (string) ($row['Field'] ?? '');
    }

    return $columns;
}

function demo_ensure_autologiny_table(PDO $pdo): void
{
    if (demo_table_exists($pdo, 'autologiny')) {
        return;
    }

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS autologiny (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            token VARCHAR(255) NOT NULL UNIQUE,
            redirect_to VARCHAR(255) DEFAULT NULL,
            uses INT NOT NULL DEFAULT 0,
            used TINYINT(1) NOT NULL DEFAULT 0,
            expires_at DATETIME NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_token (token),
            INDEX idx_user_id (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
}

function demo_fetch_target_user(PDO $pdo, int $userId, string $userEmail): array
{
    if (!demo_table_exists($pdo, 'uzytkownicy')) {
        throw new RuntimeException('Aplikacja docelowa nie ma tabeli uzytkownicy.');
    }

    $columns = demo_table_columns($pdo, 'uzytkownicy');
    if (!in_array('id', $columns, true)) {
        throw new RuntimeException('Tabela uzytkownicy nie zawiera kolumny id.');
    }

    $selectColumns = ['id'];
    foreach (['email', 'imie', 'nazwisko', 'nazwa'] as $column) {
        if (in_array($column, $columns, true)) {
            $selectColumns[] = $column;
        }
    }

    if ($userId > 0) {
        $stmt = $pdo->prepare('SELECT ' . implode(', ', $selectColumns) . ' FROM uzytkownicy WHERE id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            return $row;
        }
    }

    if ($userEmail !== '') {
        if (!in_array('email', $columns, true)) {
            throw new RuntimeException('Tabela uzytkownicy nie ma kolumny email, wiec nie mozna szukac po emailu.');
        }

        $stmt = $pdo->prepare('SELECT ' . implode(', ', $selectColumns) . ' FROM uzytkownicy WHERE email = ? LIMIT 1');
        $stmt->execute([$userEmail]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            return $row;
        }
    }

    throw new RuntimeException('Nie znaleziono konta demo w aplikacji docelowej.');
}

function demo_build_redirect_url(string $targetBaseUrl, string $redirectAfterLogin): string
{
    $base = rtrim($targetBaseUrl, '/');
    $redirect = trim($redirectAfterLogin);

    if ($redirect === '') {
        return $base !== '' ? $base . '/' : '/';
    }

    if (preg_match('#^https?://#i', $redirect)) {
        return $redirect;
    }

    if ($base === '') {
        return '/' . ltrim($redirect, '/');
    }

    return $base . '/' . ltrim($redirect, '/');
}

function demo_insert_target_token(PDO $pdo, int $userId, string $token, string $redirectTo, string $expiresAt): void
{
    demo_ensure_autologiny_table($pdo);
    $columns = demo_table_columns($pdo, 'autologiny');

    $payload = [
        'user_id' => $userId,
        'token' => $token,
        'expires_at' => $expiresAt,
    ];

    if (in_array('redirect_to', $columns, true)) {
        $payload['redirect_to'] = $redirectTo;
    }
    if (in_array('uses', $columns, true)) {
        $payload['uses'] = 0;
    }
    if (in_array('used', $columns, true)) {
        $payload['used'] = 0;
    }

    $fieldNames = array_keys($payload);
    $placeholders = array_fill(0, count($fieldNames), '?');
    $sql = 'INSERT INTO autologiny (' . implode(', ', $fieldNames) . ') VALUES (' . implode(', ', $placeholders) . ')';
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_values($payload));
}

function demo_user_label(array $user): string
{
    $parts = [];
    foreach (['imie', 'nazwisko', 'nazwa'] as $column) {
        if (!empty($user[$column])) {
            $parts[] = (string) $user[$column];
        }
    }

    if (!empty($user['email'])) {
        $parts[] = (string) $user['email'];
    }

    return trim(implode(' ', $parts)) !== '' ? trim(implode(' ', $parts)) : ('ID ' . (int) ($user['id'] ?? 0));
}

function demo_discover_app_folders(): array
{
    $roots = array_unique([
        demo_startowa_root(),
        dirname(demo_startowa_root()),
    ]);

    $found = [];
    foreach ($roots as $root) {
        if (!is_dir($root)) {
            continue;
        }

        $items = scandir($root) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $root . DIRECTORY_SEPARATOR . $item;
            if (!is_dir($path)) {
                continue;
            }

            if (is_file($path . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'autologin-redirect.php')) {
                $found[strtolower($item)] = $path;
            }
        }
    }

    $startowaRoot = demo_startowa_root();
    $found['startowa'] = $startowaRoot;
    ksort($found);

    return $found;
}

function demo_infer_app_key_from_folder(string $folderName, array $presets): string
{
    $normalized = strtolower(trim($folderName));
    return array_key_exists($normalized, $presets) ? $normalized : 'custom';
}

function demo_default_base_url_from_folder(string $folderName): string
{
    $normalized = strtolower(trim($folderName));
    return $normalized === 'startowa' ? '' : '/' . $normalized;
}

function demo_auto_detect_target_user(PDO $pdo): array
{
    if (!demo_table_exists($pdo, 'uzytkownicy')) {
        throw new RuntimeException('Aplikacja docelowa nie ma tabeli uzytkownicy.');
    }

    $columns = demo_table_columns($pdo, 'uzytkownicy');
    if (!in_array('id', $columns, true)) {
        throw new RuntimeException('Tabela uzytkownicy nie zawiera kolumny id.');
    }

    $selectColumns = ['id'];
    foreach (['email', 'imie', 'nazwisko', 'nazwa', 'rola'] as $column) {
        if (in_array($column, $columns, true)) {
            $selectColumns[] = $column;
        }
    }

    $stmt = $pdo->query('SELECT ' . implode(', ', $selectColumns) . ' FROM uzytkownicy ORDER BY id ASC LIMIT 250');
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($rows)) {
        throw new RuntimeException('W aplikacji docelowej nie ma zadnych uzytkownikow.');
    }

    $scored = [];
    foreach ($rows as $row) {
        $parts = [];
        foreach (['email', 'imie', 'nazwisko', 'nazwa', 'rola'] as $column) {
            if (!empty($row[$column])) {
                $parts[] = mb_strtolower((string) $row[$column]);
            }
        }
        $haystack = implode(' ', $parts);
        $score = 0;

        foreach ([
            'demo' => 120,
            'gosc' => 110,
            'gość' => 110,
            'guest' => 110,
            'test' => 95,
            'sample' => 80,
            'przyklad' => 80,
            'przykład' => 80,
            'portfolio' => 70,
            'showcase' => 70,
        ] as $needle => $points) {
            if (str_contains($haystack, $needle)) {
                $score += $points;
            }
        }

        $role = mb_strtolower((string) ($row['rola'] ?? ''));
        if (in_array($role, ['admin', 'owner'], true)) {
            $score -= 40;
        }
        if (in_array($role, ['demo', 'guest', 'gosc', 'test'], true)) {
            $score += 60;
        }

        if (preg_match('/^demo@/i', (string) ($row['email'] ?? ''))) {
            $score += 30;
        }
        if (count($rows) === 1) {
            $score += 5;
        }

        $scored[] = ['score' => $score, 'row' => $row];
    }

    usort($scored, static function (array $left, array $right): int {
        if ($left['score'] === $right['score']) {
            return ((int) ($left['row']['id'] ?? 0)) <=> ((int) ($right['row']['id'] ?? 0));
        }
        return $right['score'] <=> $left['score'];
    });

    $best = $scored[0] ?? null;
    if ($best === null || (int) ($best['score'] ?? 0) <= 0) {
        throw new RuntimeException('Nie znaleziono konta demo automatycznie. Utworz konto z nazwa lub emailem zawierajacym demo/test/gosc albo uzyj trybu zaawansowanego.');
    }

    return (array) $best['row'];
}

$demoAppPresets = [
    'optivio' => [
        'label' => 'Optivio',
        'base_url' => '/optivio',
        'autologin_path' => 'core/autologin-redirect.php',
        'app_root_guess' => demo_guess_app_root('optivio'),
        'default_redirect' => 'views/nav.php',
        'auto_supported' => true,
    ],
    'startowa' => [
        'label' => 'Startowa / Server Hub',
        'base_url' => '',
        'autologin_path' => 'core/autologin-redirect.php',
        'app_root_guess' => demo_guess_app_root('startowa'),
        'default_redirect' => 'public/index.php',
        'auto_supported' => true,
    ],
    'dj' => [
        'label' => 'DamJanJot DJ',
        'base_url' => '/dj',
        'autologin_path' => 'core/autologin-redirect.php',
        'app_root_guess' => demo_guess_app_root('dj'),
        'default_redirect' => '/',
        'auto_supported' => false,
    ],
    'taskora' => [
        'label' => 'Taskora',
        'base_url' => '/taskora',
        'autologin_path' => 'core/autologin-redirect.php',
        'app_root_guess' => demo_guess_app_root('taskora'),
        'default_redirect' => '/',
        'auto_supported' => false,
    ],
    'taski' => [
        'label' => 'Taski',
        'base_url' => 'https://taski.j.pl',
        'autologin_path' => 'core/autologin-redirect.php',
        'app_root_guess' => demo_guess_app_root('taski'),
        'default_redirect' => '/',
        'auto_supported' => false,
    ],
    'custom' => [
        'label' => 'Wlasna aplikacja',
        'base_url' => '',
        'autologin_path' => 'core/autologin-redirect.php',
        'app_root_guess' => '',
        'default_redirect' => '/',
        'auto_supported' => true,
    ],
];

$discoveredAppFolders = demo_discover_app_folders();

$notice = '';
$error = '';
$generatedTokenData = null;

try {
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS startowa_demo_links (
            id INT AUTO_INCREMENT PRIMARY KEY,
            label VARCHAR(150) NOT NULL,
            public_key VARCHAR(64) NOT NULL UNIQUE,
            app_key VARCHAR(50) NOT NULL,
            app_name VARCHAR(120) NOT NULL,
            target_base_url VARCHAR(255) NOT NULL,
            autologin_path VARCHAR(255) NOT NULL DEFAULT "core/autologin-redirect.php",
            target_autologin_token VARCHAR(255) NOT NULL,
            demo_account_label VARCHAR(120) DEFAULT NULL,
            description TEXT DEFAULT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            click_count INT NOT NULL DEFAULT 0,
            last_used_at DATETIME DEFAULT NULL,
            expires_at DATETIME DEFAULT NULL,
            created_by_user_id INT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_public_key (public_key),
            INDEX idx_app_key (app_key),
            INDEX idx_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
} catch (Throwable $e) {
    $error = $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    try {
        if ($action === 'create_demo' || $action === 'generate_token_only') {
            $appKey = trim((string) ($_POST['app_key'] ?? 'custom'));
            $preset = $demoAppPresets[$appKey] ?? $demoAppPresets['custom'];
            $label = trim((string) ($_POST['label'] ?? ''));
            $targetBaseUrl = trim((string) ($_POST['target_base_url'] ?? $preset['base_url']));
            $autologinPath = trim((string) ($_POST['autologin_path'] ?? $preset['autologin_path']));
            $targetToken = trim((string) ($_POST['target_autologin_token'] ?? ''));
            $demoAccountLabel = trim((string) ($_POST['demo_account_label'] ?? ''));
            $description = trim((string) ($_POST['description'] ?? ''));
            $customPublicKey = strtolower(trim((string) ($_POST['public_key'] ?? '')));
            $expiresAtRaw = trim((string) ($_POST['expires_at'] ?? ''));
            $appRootPath = trim((string) ($_POST['app_root_path'] ?? ''));
            $demoUserEmail = trim((string) ($_POST['demo_user_email'] ?? ''));
            $demoUserId = (int) ($_POST['demo_user_id'] ?? 0);
            $redirectAfterLogin = trim((string) ($_POST['redirect_after_login'] ?? ($preset['default_redirect'] ?? '/')));
            $isGenerateOnly = $action === 'generate_token_only';

            if (!$isGenerateOnly && $label === '') {
                throw new RuntimeException('Podaj nazwe linku demo.');
            }
            if ($targetBaseUrl === '') {
                throw new RuntimeException('Podaj bazowy URL lub sciezke aplikacji docelowej.');
            }
            if ($autologinPath === '') {
                $autologinPath = 'core/autologin-redirect.php';
            }

            $publicKey = $customPublicKey !== '' ? preg_replace('/[^a-z0-9\-]/', '', $customPublicKey) : demo_generate_public_key();
            if (!$isGenerateOnly && ($publicKey === '' || strlen($publicKey) < 6)) {
                throw new RuntimeException('Publiczny klucz musi miec min. 6 znakow i skladac sie z a-z, 0-9 lub -.');
            }

            $expiresAt = null;
            if ($expiresAtRaw !== '') {
                $dt = DateTime::createFromFormat('Y-m-d\TH:i', $expiresAtRaw);
                if (!$dt) {
                    throw new RuntimeException('Niepoprawna data wygasniecia.');
                }
                $expiresAt = $dt->format('Y-m-d H:i:s');
            }

            if (!$isGenerateOnly) {
                $check = $pdo->prepare('SELECT COUNT(*) FROM startowa_demo_links WHERE public_key = ?');
                $check->execute([$publicKey]);
                if ((int) $check->fetchColumn() > 0) {
                    throw new RuntimeException('Taki publiczny klucz juz istnieje.');
                }
            }

            $tokenWasGenerated = false;
            $autoSupported = (bool) ($preset['auto_supported'] ?? false);
            if ($targetToken === '') {
                if (!$autoSupported) {
                    throw new RuntimeException('Dla tej aplikacji auto-generowanie nie jest gotowe. Podaj token recznie albo wybierz aplikacje kompatybilna.');
                }
                $resolvedRoot = demo_resolve_app_root($appKey, $appRootPath);
                if (!is_dir($resolvedRoot)) {
                    throw new RuntimeException('Nie znaleziono katalogu aplikacji docelowej: ' . $resolvedRoot);
                }

                $targetPdo = demo_connect_target_pdo($resolvedRoot);
                $targetUser = demo_fetch_target_user($targetPdo, $demoUserId, $demoUserEmail);
                $targetToken = demo_generate_target_token();
                $redirectTo = demo_build_redirect_url($targetBaseUrl, $redirectAfterLogin);
                $targetExpiry = $expiresAt ?? date('Y-m-d H:i:s', strtotime('+365 days'));
                demo_insert_target_token($targetPdo, (int) $targetUser['id'], $targetToken, $redirectTo, $targetExpiry);

                if ($demoAccountLabel === '') {
                    $demoAccountLabel = demo_user_label($targetUser);
                }
                $tokenWasGenerated = true;
            }

            if ($isGenerateOnly) {
                if (!$tokenWasGenerated) {
                    throw new RuntimeException('Tryb generowania tokenu bez zapisu wymaga pustego pola gotowego tokenu i kompatybilnej aplikacji.');
                }

                $autologinUrl = demo_build_redirect_url($targetBaseUrl, trim($autologinPath, '/'));
                if (!preg_match('#^https?://#i', $autologinUrl)) {
                    $autologinUrl = rtrim(demo_public_origin(), '/') . '/' . ltrim($autologinUrl, '/');
                }

                $separator = str_contains($autologinUrl, '?') ? '&' : '?';
                $generatedTokenData = [
                    'token' => $targetToken,
                    'autologin_url' => $autologinUrl . $separator . 'token=' . urlencode($targetToken),
                    'account_label' => $demoAccountLabel !== '' ? $demoAccountLabel : '-',
                    'app_label' => (string) $preset['label'],
                    'redirect_to' => $redirectTo,
                    'expires_at' => $targetExpiry,
                ];

                $notice = 'Token autologinu zostal wygenerowany. Nic nie zapisano w linkach demo.';
                goto render_page;
            }

            $insert = $pdo->prepare('INSERT INTO startowa_demo_links (
                label, public_key, app_key, app_name, target_base_url, autologin_path, target_autologin_token,
                demo_account_label, description, expires_at, created_by_user_id
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $insert->execute([
                $label,
                $publicKey,
                $appKey,
                $preset['label'],
                $targetBaseUrl,
                trim($autologinPath, '/'),
                $targetToken,
                $demoAccountLabel !== '' ? $demoAccountLabel : null,
                $description !== '' ? $description : null,
                $expiresAt,
                isset($_SESSION['id']) ? (int) $_SESSION['id'] : null,
            ]);

            $notice = $tokenWasGenerated
                ? 'Link demo zostal utworzony, a token autologinu wygenerowano automatycznie w aplikacji docelowej.'
                : 'Link demo zostal utworzony.';
        }

        if ($action === 'quick_generate_demo') {
            $selectedFolder = strtolower(trim((string) ($_POST['quick_folder'] ?? '')));
            if ($selectedFolder === '' || !isset($discoveredAppFolders[$selectedFolder])) {
                throw new RuntimeException('Wybierz poprawny folder aplikacji.');
            }

            $appRoot = $discoveredAppFolders[$selectedFolder];
            $appKey = demo_infer_app_key_from_folder($selectedFolder, $demoAppPresets);
            $preset = $demoAppPresets[$appKey] ?? $demoAppPresets['custom'];
            $targetBaseUrl = $preset['base_url'] !== '' ? $preset['base_url'] : demo_default_base_url_from_folder($selectedFolder);
            $autologinPath = $preset['autologin_path'] ?? 'core/autologin-redirect.php';
            $redirectAfterLogin = $preset['default_redirect'] ?? '/';
            $targetPdo = demo_connect_target_pdo($appRoot);
            $targetUser = demo_auto_detect_target_user($targetPdo);
            $targetToken = demo_generate_target_token();
            $targetExpiry = date('Y-m-d H:i:s', strtotime('+365 days'));
            $redirectTo = demo_build_redirect_url($targetBaseUrl, $redirectAfterLogin);

            demo_insert_target_token($targetPdo, (int) $targetUser['id'], $targetToken, $redirectTo, $targetExpiry);

            $label = ($preset['label'] ?? ucfirst($selectedFolder)) . ' Demo';
            $publicKey = demo_generate_public_key();
            $demoAccountLabel = demo_user_label($targetUser);
            $description = 'Auto-generated quick demo link.';

            $check = $pdo->prepare('SELECT COUNT(*) FROM startowa_demo_links WHERE public_key = ?');
            $check->execute([$publicKey]);
            while ((int) $check->fetchColumn() > 0) {
                $publicKey = demo_generate_public_key();
                $check->execute([$publicKey]);
            }

            $insert = $pdo->prepare('INSERT INTO startowa_demo_links (
                label, public_key, app_key, app_name, target_base_url, autologin_path, target_autologin_token,
                demo_account_label, description, expires_at, created_by_user_id
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $insert->execute([
                $label,
                $publicKey,
                $appKey,
                $preset['label'] ?? ucfirst($selectedFolder),
                $targetBaseUrl,
                trim($autologinPath, '/'),
                $targetToken,
                $demoAccountLabel,
                $description,
                $targetExpiry,
                isset($_SESSION['id']) ? (int) $_SESSION['id'] : null,
            ]);

            $generatedTokenData = [
                'token' => $targetToken,
                'autologin_url' => rtrim(demo_public_origin(), '/') . '/' . ltrim(demo_build_redirect_url($targetBaseUrl, trim($autologinPath, '/')), '/'). '?token=' . urlencode($targetToken),
                'account_label' => $demoAccountLabel,
                'app_label' => $preset['label'] ?? ucfirst($selectedFolder),
                'redirect_to' => $redirectTo,
                'expires_at' => $targetExpiry,
                'public_demo_url' => rtrim($publicOrigin ?? demo_public_origin(), '/') . '/demo.php?demo=' . urlencode($publicKey),
            ];

            $notice = 'Szybki link demo zostal wygenerowany automatycznie.';
        }

        if ($action === 'toggle_demo') {
            $demoId = (int) ($_POST['demo_id'] ?? 0);
            $isActive = (int) ($_POST['is_active'] ?? 0) === 1 ? 1 : 0;
            if ($demoId <= 0) {
                throw new RuntimeException('Nieprawidlowe ID linku demo.');
            }
            $stmt = $pdo->prepare('UPDATE startowa_demo_links SET is_active = ? WHERE id = ?');
            $stmt->execute([$isActive, $demoId]);
            $notice = $isActive === 1 ? 'Link demo zostal aktywowany.' : 'Link demo zostal wylaczony.';
        }

        if ($action === 'delete_demo') {
            $demoId = (int) ($_POST['demo_id'] ?? 0);
            if ($demoId <= 0) {
                throw new RuntimeException('Nieprawidlowe ID linku demo.');
            }
            $stmt = $pdo->prepare('DELETE FROM startowa_demo_links WHERE id = ?');
            $stmt->execute([$demoId]);
            $notice = 'Link demo zostal usuniety.';
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$rows = [];
if (startowa_table_exists($pdo, 'startowa_demo_links')) {
    $rows = $pdo->query('SELECT * FROM startowa_demo_links ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC);
}

$publicOrigin = demo_public_origin();

render_page:

panel_layout_start('Demo Links', 'Publiczne linki demo i automatyczne tokeny autologinu');
?>
<?php if ($notice !== ''): ?>
    <div class="alert alert-success py-2"><?php echo h($notice); ?></div>
<?php endif; ?>
<?php if ($error !== ''): ?>
    <div class="alert alert-danger py-2"><?php echo h($error); ?></div>
<?php endif; ?>
<?php if (is_array($generatedTokenData)): ?>
    <div class="card p-3 mb-3">
        <h2 class="h5 mb-3">Wygenerowany token</h2>
        <div class="row g-2">
            <div class="col-md-6">
                <div class="muted">Aplikacja</div>
                <div><?php echo h((string) $generatedTokenData['app_label']); ?></div>
            </div>
            <div class="col-md-6">
                <div class="muted">Konto demo</div>
                <div><?php echo h((string) $generatedTokenData['account_label']); ?></div>
            </div>
            <div class="col-md-6">
                <div class="muted">Token</div>
                <input class="form-control" value="<?php echo h((string) $generatedTokenData['token']); ?>" readonly>
            </div>
            <div class="col-md-6">
                <div class="muted">Wygasa</div>
                <input class="form-control" value="<?php echo h((string) $generatedTokenData['expires_at']); ?>" readonly>
            </div>
            <div class="col-12">
                <div class="muted">Redirect po loginie</div>
                <input class="form-control" value="<?php echo h((string) $generatedTokenData['redirect_to']); ?>" readonly>
            </div>
            <div class="col-12">
                <div class="muted">Gotowy link autologinu</div>
                <input class="form-control" value="<?php echo h((string) $generatedTokenData['autologin_url']); ?>" readonly>
            </div>
            <?php if (!empty($generatedTokenData['public_demo_url'])): ?>
                <div class="col-12">
                    <div class="muted">Publiczny link demo</div>
                    <input class="form-control" value="<?php echo h((string) $generatedTokenData['public_demo_url']); ?>" readonly>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<div class="card p-3 mb-3">
    <h2 class="h5 mb-3">Szybkie generowanie</h2>
    <form method="post" class="row g-2">
        <input type="hidden" name="action" value="quick_generate_demo">
        <div class="col-md-8">
            <label class="form-label mb-1">Folder aplikacji</label>
            <select class="form-select" name="quick_folder" required>
                <option value="">Wybierz folder</option>
                <?php foreach ($discoveredAppFolders as $folderName => $folderPath): ?>
                    <option value="<?php echo h($folderName); ?>"><?php echo h($folderName . ' -> ' . $folderPath); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4 d-flex align-items-end">
            <button class="btn btn-outline-light w-100" type="submit">Wygeneruj linki</button>
        </div>
        <div class="col-12 muted">
            Ten tryb sam wykrywa konto demo po nazwach typu demo, test, gosc, guest. Jesli nie znajdzie odpowiedniego konta, uzyj formularza zaawansowanego nizej.
        </div>
    </form>
</div>

<div class="card p-3 mb-3">
    <h2 class="h5 mb-2">Jak to dziala</h2>
    <div class="muted">
        Panel potrafi teraz nie tylko zapisac publiczny link demo, ale tez sam utworzyc token autologinu w wybranej aplikacji.
        Dla aplikacji kompatybilnych podajesz URL, katalog aplikacji i konto demo, a panel zrobi reszte.
    </div>
</div>

<div class="card p-3 mb-3">
    <h2 class="h5 mb-3">Nowy link demo</h2>
    <form method="post" class="row g-2" id="demoLinkForm">
        <input type="hidden" name="action" value="create_demo">
        <div class="col-md-4">
            <label class="form-label mb-1">Aplikacja</label>
            <select class="form-select" name="app_key" id="demoAppKey">
                <?php foreach ($demoAppPresets as $appKey => $preset): ?>
                    <option value="<?php echo h($appKey); ?>"><?php echo h($preset['label']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label mb-1">Nazwa linku demo</label>
            <input class="form-control" name="label" placeholder="np. Optivio Demo Portfolio">
        </div>
        <div class="col-md-4">
            <label class="form-label mb-1">Konto demo</label>
            <input class="form-control" name="demo_account_label" placeholder="opcjonalna etykieta konta demo">
        </div>
        <div class="col-md-6">
            <label class="form-label mb-1">URL lub sciezka aplikacji</label>
            <input class="form-control" name="target_base_url" id="demoTargetBaseUrl" placeholder="/optivio lub https://optivio.code-dj.pl" required>
        </div>
        <div class="col-md-6">
            <label class="form-label mb-1">Sciezka autologinu</label>
            <input class="form-control" name="autologin_path" id="demoAutologinPath" value="core/autologin-redirect.php" required>
        </div>

        <div class="col-12 pt-2"><strong>Tryb automatyczny</strong></div>
        <div class="col-md-6">
            <label class="form-label mb-1">Katalog aplikacji na serwerze</label>
            <input class="form-control" name="app_root_path" id="demoAppRootPath" placeholder="np. ../optivio albo pelna sciezka serwerowa">
        </div>
        <div class="col-md-6">
            <label class="form-label mb-1">Przekierowanie po zalogowaniu</label>
            <input class="form-control" name="redirect_after_login" id="demoRedirectAfterLogin" placeholder="np. views/nav.php albo public/index.php">
        </div>
        <div class="col-md-6">
            <label class="form-label mb-1">Email konta demo</label>
            <input class="form-control" name="demo_user_email" placeholder="np. demo@optivio.pl">
        </div>
        <div class="col-md-6">
            <label class="form-label mb-1">ID konta demo</label>
            <input class="form-control" type="number" min="1" name="demo_user_id" placeholder="opcjonalnie zamiast emaila">
        </div>

        <div class="col-12 pt-2"><strong>Tryb reczny</strong></div>
        <div class="col-md-6">
            <label class="form-label mb-1">Gotowy token autologinu</label>
            <input class="form-control" name="target_autologin_token" placeholder="jesli podasz, panel nie wygeneruje nowego tokenu">
        </div>
        <div class="col-md-3">
            <label class="form-label mb-1">Wlasny publiczny klucz</label>
            <input class="form-control" name="public_key" placeholder="opcjonalnie">
        </div>
        <div class="col-md-3">
            <label class="form-label mb-1">Wygasa</label>
            <input class="form-control" type="datetime-local" name="expires_at">
        </div>
        <div class="col-12">
            <label class="form-label mb-1">Opis / notatka</label>
            <textarea class="form-control" name="description" rows="3" placeholder="np. dane przykładowe dla portfolio, bez prawdziwych użytkownikow"></textarea>
        </div>
        <div class="col-12 d-flex gap-2 flex-wrap">
            <button class="btn btn-outline-light" type="submit">Utworz link demo</button>
            <button class="btn btn-outline-light" type="submit" name="action" value="generate_token_only">Generuj token bez zapisywania linku demo</button>
            <span class="muted align-self-center">Publiczny format: <?php echo h($publicOrigin); ?>/demo.php?demo=twoj-klucz</span>
        </div>
    </form>
</div>

<div class="card p-3">
    <h2 class="h5 mb-3">Istniejace linki demo</h2>
    <div class="table-responsive">
        <table class="table table-sm align-middle">
            <thead>
                <tr>
                    <th>Link</th>
                    <th>Aplikacja</th>
                    <th>Konto demo</th>
                    <th>Target</th>
                    <th>Statystyki</th>
                    <th>Akcje</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <?php $publicUrl = $publicOrigin . '/demo.php?demo=' . urlencode((string) $row['public_key']); ?>
                    <tr>
                        <td style="min-width:280px;">
                            <strong><?php echo h((string) $row['label']); ?></strong>
                            <div class="muted" style="font-size:12px;"><?php echo h($publicUrl); ?></div>
                            <?php if ((string) ($row['description'] ?? '') !== ''): ?>
                                <div class="muted" style="font-size:12px;"><?php echo h((string) $row['description']); ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong><?php echo h((string) $row['app_name']); ?></strong>
                            <div class="muted" style="font-size:12px;"><?php echo h((string) $row['app_key']); ?></div>
                        </td>
                        <td><?php echo h((string) ($row['demo_account_label'] ?? '-')); ?></td>
                        <td style="min-width:240px;">
                            <div class="muted" style="font-size:12px;"><?php echo h((string) $row['target_base_url']); ?></div>
                            <div class="muted" style="font-size:12px;"><?php echo h((string) $row['autologin_path']); ?></div>
                        </td>
                        <td>
                            <div><?php echo (int) ($row['click_count'] ?? 0); ?> wejsc</div>
                            <div class="muted" style="font-size:12px;">Status: <?php echo ((int) ($row['is_active'] ?? 0) === 1) ? 'aktywny' : 'wylaczony'; ?></div>
                            <div class="muted" style="font-size:12px;">Wygasa: <?php echo h((string) ($row['expires_at'] ?? '-')); ?></div>
                        </td>
                        <td>
                            <div class="d-grid gap-2">
                                <a class="btn btn-sm btn-outline-light" href="<?php echo h($publicUrl); ?>" target="_blank" rel="noopener">Otworz</a>
                                <form method="post">
                                    <input type="hidden" name="action" value="toggle_demo">
                                    <input type="hidden" name="demo_id" value="<?php echo (int) $row['id']; ?>">
                                    <input type="hidden" name="is_active" value="<?php echo ((int) ($row['is_active'] ?? 0) === 1) ? '0' : '1'; ?>">
                                    <button class="btn btn-sm btn-outline-light" type="submit"><?php echo ((int) ($row['is_active'] ?? 0) === 1) ? 'Wylacz' : 'Aktywuj'; ?></button>
                                </form>
                                <form method="post" onsubmit="return confirm('Usunac link demo <?php echo h((string) $row['label']); ?>?');">
                                    <input type="hidden" name="action" value="delete_demo">
                                    <input type="hidden" name="demo_id" value="<?php echo (int) $row['id']; ?>">
                                    <button class="btn btn-sm btn-outline-danger" type="submit">Usun</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($rows)): ?>
                    <tr>
                        <td colspan="6" class="text-center muted py-3">Brak zapisanych linkow demo.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
const demoPresets = <?php echo json_encode($demoAppPresets, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
const appSelectEl = document.getElementById('demoAppKey');
const baseUrlEl = document.getElementById('demoTargetBaseUrl');
const autologinEl = document.getElementById('demoAutologinPath');
const appRootEl = document.getElementById('demoAppRootPath');
const redirectEl = document.getElementById('demoRedirectAfterLogin');

function syncDemoPreset() {
    if (!appSelectEl) {
        return;
    }

    const preset = demoPresets[appSelectEl.value];
    if (!preset) {
        return;
    }

    if (baseUrlEl && (baseUrlEl.value.trim() === '' || baseUrlEl.dataset.autofill !== 'manual')) {
        baseUrlEl.value = preset.base_url || '';
        baseUrlEl.dataset.autofill = 'preset';
    }

    if (autologinEl && (autologinEl.value.trim() === '' || autologinEl.dataset.autofill !== 'manual')) {
        autologinEl.value = preset.autologin_path || 'core/autologin-redirect.php';
        autologinEl.dataset.autofill = 'preset';
    }

    if (appRootEl && (appRootEl.value.trim() === '' || appRootEl.dataset.autofill !== 'manual')) {
        appRootEl.value = preset.app_root_guess || '';
        appRootEl.dataset.autofill = 'preset';
    }

    if (redirectEl && (redirectEl.value.trim() === '' || redirectEl.dataset.autofill !== 'manual')) {
        redirectEl.value = preset.default_redirect || '/';
        redirectEl.dataset.autofill = 'preset';
    }
}

if (appSelectEl) {
    appSelectEl.addEventListener('change', syncDemoPreset);
}
[baseUrlEl, autologinEl, appRootEl, redirectEl].forEach((element) => {
    if (!element) {
        return;
    }

    element.addEventListener('input', () => {
        element.dataset.autofill = 'manual';
    });
});

syncDemoPreset();
</script>
<?php
panel_layout_end();
