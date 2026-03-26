<?php
header('Content-Type: text/html; charset=UTF-8');

if (is_file(__DIR__ . '/../core/access_control.php')) {
    require_once __DIR__ . '/../core/access_control.php';
} else {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
        header('Location: login.php');
        exit();
    }

    if (!function_exists('startowa_require_login')) {
        function startowa_require_login(): void
        {
            if (session_status() !== PHP_SESSION_ACTIVE) {
                session_start();
            }
            if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
                header('Location: login.php');
                exit();
            }
        }
    }

    if (!function_exists('startowa_normalize_role')) {
        function startowa_normalize_role(?string $role): string
        {
            $normalized = strtolower(trim((string) $role));
            return $normalized !== '' ? $normalized : 'user';
        }
    }

    if (!function_exists('startowa_refresh_access_cache')) {
        function startowa_refresh_access_cache(): void {}
    }

    if (!function_exists('startowa_current_user_access')) {
        function startowa_current_user_access(): array
        {
            return ['apps' => ['dashboard', 'dj', 'optivio', 'taski', 'taskora']];
        }
    }

    if (!function_exists('startowa_redirect')) {
        function startowa_redirect(string $path): void
        {
            $normalized = ltrim($path, '/');
            if (strpos($normalized, 'public/') === 0) {
                $normalized = substr($normalized, 7);
            }

            header('Location: ' . $normalized);
            exit();
        }
    }
}

startowa_require_login();

$userName = $_SESSION['imie'] ?? 'Uzytkownik';
$userRole = startowa_normalize_role((string) ($_SESSION['rola'] ?? 'user'));
$userEmail = $_SESSION['email'] ?? (strtolower(preg_replace('/\s+/', '.', $userName)) . '@local');

startowa_refresh_access_cache();
$access = startowa_current_user_access();
$allowedApps = (array) ($access['apps'] ?? []);

if (in_array($userRole, ['admin', 'owner'], true) && in_array('admin_panel', $allowedApps, true)) {
    startowa_redirect('public/admin/index.php');
}

$appLinks = function_exists('startowa_app_catalog') ? startowa_app_catalog() : [
    'dashboard' => ['label' => 'Server Hub', 'url' => '/public/index.php', 'icon' => '🏠', 'desc' => 'Panel glowny i skroty do aplikacji'],
    'dj' => ['label' => 'DamJanJot DJ', 'url' => 'https://app-dj.code-dj.pl', 'icon' => '🎵', 'desc' => 'Panel DJ - sety, playlisty, rynek'],
    'optivio' => ['label' => 'Optivio', 'url' => 'https://optivio.code-dj.pl', 'icon' => '📊', 'desc' => 'Moduly, galeria, notatnik i todo'],
    'taski' => ['label' => 'Taski', 'url' => 'https://taski.j.pl', 'icon' => '✅', 'desc' => 'Zarzadzanie zadaniami'],
    'taskora' => ['label' => 'Taskora', 'url' => 'https://taskora.code-dj.pl', 'icon' => '📋', 'desc' => 'Workspace i projekty'],
    'neuronetix' => ['label' => 'Neuronetix', 'url' => '/neuronetix/index.php', 'icon' => '🧠', 'desc' => 'Panel edukacyjny: uczen i nauczyciel'],
    'admin_panel' => ['label' => 'Panel Admina', 'url' => '/public/admin/index.php', 'icon' => '🛡', 'desc' => 'Role, dostepy i relacje uzytkownikow'],
];

$visibleApps = [];
foreach ($appLinks as $key => $meta) {
    if (in_array($key, $allowedApps, true)) {
        $visibleApps[$key] = $meta;
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

        .bg { position: fixed; inset: 0; pointer-events: none; }
        .blob { position: absolute; border-radius: 50%; filter: blur(80px); opacity: 0.18; animation: float 14s ease-in-out infinite; }
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
            grid-template-columns: 300px 1fr;
            height: 100vh;
            transition: grid-template-columns 0.2s ease;
        }

        .app.collapsed { grid-template-columns: 1fr; }

        .sidebar {
            border-right: 1px solid var(--line);
            background: var(--panel);
            backdrop-filter: blur(10px);
            padding: 14px;
            display: grid;
            grid-template-rows: auto 1fr auto;
            gap: 12px;
            min-height: 0;
            overflow: hidden;
        }

        .app.collapsed .sidebar {
            position: fixed;
            top: 12px;
            left: 12px;
            width: 50px;
            height: 50px;
            overflow: hidden;
            border-radius: 14px;
            padding: 7px;
            border-right: none;
            border: 1px solid var(--line);
            z-index: 20;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 2px 12px rgba(0,0,0,0.35);
        }

        .app.collapsed .sidebar > * { display: none; }
        .app.collapsed .sidebar .brand { display: flex; }
        .app.collapsed .collapse-btn { display: none; }
        .app.collapsed .top { padding-left: 74px; }
        .app.collapsed .sidebar:hover { border-color: var(--acc); box-shadow: 0 4px 18px rgba(56,189,248,0.35); }

        .brand { display: flex; align-items: center; gap: 10px; }

        .logo {
            width: 36px; height: 36px; border-radius: 10px;
            display: grid; place-items: center;
            background: linear-gradient(130deg, var(--acc), var(--acc2));
            color: #062333; font-weight: 800; flex-shrink: 0;
        }

        .brand-name { font-size: 18px; font-weight: 800; letter-spacing: 0.3px; }

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
            left: 300px;
            top: 14px;
            z-index: 5;
            border-left: none;
            border-top-left-radius: 0;
            border-bottom-left-radius: 0;
        }

        .side-mid { min-height: 0; overflow-y: auto; display: grid; align-content: start; gap: 6px; }

        .nav-section {
            border: 1px solid var(--line);
            border-radius: 10px;
            background: rgba(255,255,255,0.03);
            padding: 10px;
            display: grid;
            gap: 4px;
        }

        .nav-section h4 {
            margin: 0 0 6px;
            font-size: 11px;
            color: var(--muted);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 7px 9px;
            border-radius: 8px;
            color: var(--txt);
            text-decoration: none;
            font-size: 13px;
            cursor: pointer;
            border: 1px solid transparent;
            transition: border-color 0.15s, background 0.15s;
        }

        .nav-link:hover { border-color: var(--line); background: rgba(255,255,255,0.05); }
        .nav-link.active { border-color: var(--acc); background: rgba(56,189,248,0.1); color: var(--acc); }
        .nav-icon { font-size: 15px; width: 20px; text-align: center; }

        .side-foot { display: grid; gap: 8px; }

        .profile-trigger {
            border: 1px solid var(--line);
            border-radius: 10px;
            background: var(--panel);
            padding: 8px;
            display: flex;
            gap: 10px;
            align-items: center;
            cursor: pointer;
        }

        .avatar {
            width: 34px; height: 34px; border-radius: 10px;
            display: grid; place-items: center;
            background: linear-gradient(130deg, var(--acc), var(--acc2));
            color: #062333; font-weight: 700; flex-shrink: 0;
        }

        .profile-name { font-size: 13px; font-weight: 600; }
        .profile-email { font-size: 11px; color: var(--muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

        .btn { border: 1px solid var(--line); border-radius: 10px; background: var(--panel); color: var(--txt); padding: 9px 11px; cursor: pointer; font-size: 13px; }
        .btn:hover { border-color: var(--acc); }
        .btn.warn { border-color: rgba(253,164,175,0.55); color: #fecdd3; }

        .user-menu { border: 1px solid var(--line); border-radius: 10px; padding: 8px; background: rgba(255,255,255,0.03); display: none; gap: 8px; }
        .user-menu.open { display: grid; }

        .main { display: grid; grid-template-rows: auto 1fr; min-height: 0; overflow: hidden; }

        .top {
            border-bottom: 1px solid var(--line);
            padding: 12px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: rgba(255,255,255,0.03);
            height: 64px;
        }

        .top-right {
            display: flex;
            align-items: center;
            gap: 8px;
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
            z-index: 30;
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

        .top-title { font-size: 15px; font-weight: 600; color: var(--muted); }

        .content { min-height: 0; overflow-y: auto; padding: 28px 32px; }

        .welcome-heading { font-size: 26px; font-weight: 800; margin: 0 0 4px; }
        .welcome-sub { font-size: 14px; color: var(--muted); margin: 0 0 28px; }
        .accent { color: var(--acc); }

        .cards { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 14px; }

        .card {
            border: 1px solid var(--line);
            border-radius: 14px;
            background: var(--panel);
            padding: 18px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            cursor: pointer;
            transition: border-color 0.2s, transform 0.2s, background 0.2s;
            text-decoration: none;
            color: var(--txt);
        }
        .card:hover { border-color: var(--acc); background: rgba(56,189,248,0.08); transform: translateY(-2px); }

        .card-icon {
            font-size: 26px; width: 46px; height: 46px; border-radius: 12px;
            background: rgba(255,255,255,0.06); display: grid; place-items: center;
        }

        .card-title { font-size: 14px; font-weight: 700; }
        .card-sub { font-size: 12px; color: var(--muted); line-height: 1.4; }

        .app.collapsed .hide-when-collapsed { display: none; }

        @media (max-width: 900px) {
            .app { grid-template-columns: 1fr; }
            .sidebar { max-height: 200px; overflow: auto; border-right: none; border-bottom: 1px solid var(--line); }
            .collapse-btn { display: none; }
            .content { padding: 16px; }
            .app-switch-menu { right: auto; left: 0; }
        }

        @media (max-width: 600px) {
            .cards { grid-template-columns: 1fr 1fr; }
            .welcome-heading { font-size: 20px; }
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
    <button id="collapseBtn" class="collapse-btn">&lt;</button>

    <aside class="sidebar">
        <div class="brand">
            <div class="logo">H</div>
            <div class="brand-name hide-when-collapsed">Server Hub</div>
        </div>

        <nav class="side-mid hide-when-collapsed">
            <div class="nav-section">
                <h4>Nawigacja</h4>
                <a class="nav-link active" href="index.php">
                    <span class="nav-icon">&#127968;</span> Dashboard
                </a>
            </div>
            <div class="nav-section">
                <h4>Aplikacje</h4>
                <?php if (empty($visibleApps)): ?>
                    <div class="nav-link" style="cursor:default; opacity:.75;">Brak przypisanych aplikacji</div>
                <?php else: ?>
                    <?php foreach ($visibleApps as $app): ?>
                        <a class="nav-link" href="<?php echo htmlspecialchars($app['url'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener">
                            <span class="nav-icon"><?php echo $app['icon']; ?></span> <?php echo htmlspecialchars($app['label'], ENT_QUOTES, 'UTF-8'); ?>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </nav>

        <div class="side-foot">
            <div id="profileTrigger" class="profile-trigger hide-when-collapsed">
                <div class="avatar"><?php echo strtoupper(substr($userName, 0, 1)); ?></div>
                <div style="min-width:0; overflow:hidden;">
                    <div class="profile-name"><?php echo htmlspecialchars($userName); ?></div>
                    <div class="profile-email"><?php echo htmlspecialchars($userEmail); ?></div>
                </div>
            </div>
            <div id="userMenu" class="user-menu hide-when-collapsed">
                <button class="btn warn" onclick="window.location.href='logout.php'">Wyloguj</button>
            </div>
        </div>
    </aside>

    <main class="main">
        <div class="top">
            <span class="top-title">Witaj, <strong><?php echo htmlspecialchars($userName); ?></strong></span>
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
                                <a class="app-switch-item" href="<?php echo htmlspecialchars($app['url'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener">
                                    <span><?php echo htmlspecialchars((string) $app['icon'], ENT_QUOTES, 'UTF-8'); ?></span>
                                    <span><?php echo htmlspecialchars((string) $app['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="content">
            <div class="welcome-heading">
                Cze&#347;&#263;, <span class="accent"><?php echo htmlspecialchars(explode(' ', $userName)[0]); ?></span> &#128075;
            </div>
            <div class="welcome-sub">Wybierz aplikacj&#281; lub skr&#243;t poni&#380;ej.</div>

            <div class="cards">
                <?php if (empty($visibleApps)): ?>
                    <div class="card" style="cursor:default;">
                        <div class="card-icon">&#9888;</div>
                        <div class="card-title">Brak dostepu do aplikacji</div>
                        <div class="card-sub">Skontaktuj sie z administratorem, aby przypisal Ci dostep do modulow.</div>
                    </div>
                <?php else: ?>
                    <?php foreach ($visibleApps as $app): ?>
                        <a class="card" href="<?php echo htmlspecialchars($app['url'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener">
                            <div class="card-icon"><?php echo $app['icon']; ?></div>
                            <div class="card-title"><?php echo htmlspecialchars($app['label'], ENT_QUOTES, 'UTF-8'); ?></div>
                            <div class="card-sub"><?php echo htmlspecialchars($app['desc'], ENT_QUOTES, 'UTF-8'); ?></div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<script>
    const appEl = document.getElementById('app');
    const collapseBtnEl = document.getElementById('collapseBtn');

    collapseBtnEl.addEventListener('click', (e) => {
        e.stopPropagation();
        appEl.classList.toggle('collapsed');
        collapseBtnEl.textContent = appEl.classList.contains('collapsed') ? '>' : '<';
    });

    document.querySelector('.sidebar').addEventListener('click', () => {
        if (appEl.classList.contains('collapsed')) {
            appEl.classList.remove('collapsed');
            collapseBtnEl.textContent = '<';
        }
    });

    document.getElementById('profileTrigger').addEventListener('click', () => {
        if (appEl.classList.contains('collapsed')) return;
        document.getElementById('userMenu').classList.toggle('open');
    });

    const appSwitch = document.getElementById('appSwitch');
    const appSwitchBtn = document.getElementById('appSwitchBtn');

    appSwitchBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        const isOpen = appSwitch.classList.toggle('open');
        appSwitchBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });

    document.addEventListener('click', (e) => {
        if (!appSwitch.contains(e.target)) {
            appSwitch.classList.remove('open');
            appSwitchBtn.setAttribute('aria-expanded', 'false');
        }
    });
</script>
</body>
</html>
