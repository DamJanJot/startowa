<?php
header('Content-Type: text/html; charset=UTF-8');

if (is_file(__DIR__ . '/../core/access_control.php')) {
    require_once __DIR__ . '/../core/access_control.php';
} else {
    require_once __DIR__ . '/../core/db.php';

    if (!function_exists('startowa_start_session')) {
        function startowa_start_session(): void
        {
            if (session_status() !== PHP_SESSION_ACTIVE) {
                session_start();
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
        function startowa_refresh_access_cache(): void
        {
            unset($_SESSION['access']);
        }
    }

    if (!function_exists('startowa_has_app_access')) {
        function startowa_has_app_access(string $appKey): bool
        {
            $role = startowa_normalize_role((string) ($_SESSION['rola'] ?? 'user'));
            if ($appKey === 'admin_panel') {
                return in_array($role, ['admin', 'owner'], true);
            }

            return true;
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

    if (!function_exists('startowa_should_redirect_to_app')) {
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
    }

    if (!function_exists('startowa_login_redirect_target')) {
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
    }

    if (!function_exists('startowa_current_user_access')) {
        function startowa_current_user_access(): array
        {
            $role = startowa_normalize_role((string) ($_SESSION['rola'] ?? 'user'));

            if (in_array($role, ['uczen', 'student', 'nauczyciel', 'teacher'], true)) {
                return ['role' => $role, 'apps' => ['dashboard', 'neuronetix']];
            }

            if (in_array($role, ['admin', 'owner', 'administrator'], true)) {
                return ['role' => $role, 'apps' => ['dashboard', 'admin_panel', 'server_hub']];
            }

            return ['role' => $role, 'apps' => ['dashboard']];
        }
    }
}

startowa_start_session();

$errorMessage = '';
$successMessage = isset($_GET['registered']) && $_GET['registered'] === '1'
    ? 'Konto utworzone. Możesz się zalogować.'
    : '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST['email'] ?? '');
    $haslo = trim($_POST['haslo'] ?? '');

    if ($email === '' || $haslo === '') {
        $errorMessage = 'Uzupełnij adres e-mail i hasło.';
    } else {
        $stmt = $pdo->prepare('SELECT id, imie, nazwisko, email, haslo, rola FROM uzytkownicy WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row && password_verify($haslo, (string) ($row['haslo'] ?? ''))) {
            $_SESSION['loggedin'] = true;
            $_SESSION['id'] = (int) $row['id'];
            $_SESSION['imie'] = (string) ($row['imie'] ?? '');
            $_SESSION['nazwisko'] = (string) ($row['nazwisko'] ?? '');
            $_SESSION['email'] = (string) ($row['email'] ?? '');
            $_SESSION['rola'] = startowa_normalize_role((string) ($row['rola'] ?? 'user'));

            startowa_refresh_access_cache();

            $access = startowa_current_user_access();
            $allowedApps = (array) ($access['apps'] ?? []);
            $target = startowa_login_redirect_target((string) $_SESSION['rola'], $allowedApps);
            startowa_redirect($target);
        } else {
            $errorMessage = 'Nieprawidłowy email lub hasło.';
        }
    }
}
?>
<!doctype html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Logowanie</title>
    <meta name="description" content="Logowanie do konta">
    <link rel="icon" type="image/png" href="../assets/img/kossmo.png">
    <link rel="stylesheet" href="./assets/css/style.css">
</head>
<body>
    <div class="bg-wrap">
        <div class="bg-gradient bg-gradient-1"></div>
        <div class="bg-gradient bg-gradient-2"></div>
        <div class="bg-gradient bg-gradient-3"></div>
        <div class="orb-layer" id="orbLayer"></div>
        <div class="noise"></div>
    </div>

    <main class="login-page">
        <section class="login-card">
            <div class="login-left">
                <p class="eyebrow">Twoja strefa</p>
                <h1>Witaj ponownie</h1>
                <p class="subtitle">
                    Zaloguj się do swojego konta i przejdź do panelu użytkownika lub administratora.
                </p>

                <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" class="login-form" id="loginForm">
                    <div class="field">
                        <label for="email">Adres e-mail</label>
                        <input
                            type="email"
                            id="email"
                            name="email"
                            placeholder="np. jan@twojadomena.pl"
                            value="<?php echo htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                            required
                        >
                    </div>

                    <div class="field">
                        <label for="password">Hasło</label>
                        <div class="password-wrap">
                            <input
                                type="password"
                                id="password"
                                name="haslo"
                                placeholder="Wpisz hasło"
                                required
                            >
                            <button type="button" class="toggle-password" id="togglePassword" aria-label="Pokaż lub ukryj hasło">
                                Pokaż
                            </button>
                        </div>
                    </div>

                    <div class="form-row">
                        <label class="checkbox">
                            <input type="checkbox" id="rememberMe">
                            <span>Zapamiętaj mnie</span>
                        </label>

                        <a href="#" class="forgot-link">Nie pamiętasz hasła?</a>
                    </div>

                    <button type="submit" class="login-btn">Zaloguj się</button>

                    <p class="status-message <?php echo $errorMessage !== '' ? 'show' : ''; ?>" id="statusMessage">
                        <?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?>
                    </p>

                    <?php if ($successMessage !== ''): ?>
                        <p class="status-message show" style="color:#86efac;">
                            <?php echo htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8'); ?>
                        </p>
                    <?php endif; ?>
                </form>

                <div class="divider">
                    <span>konto użytkownika</span>
                </div>

                <div class="bottom-links">
                    <a class="register-link" href="./register.php">Nie masz konta? Załóż konto</a>
                </div>
            </div>

            <div class="login-right">
                <div class="info-box reveal">
                    <p class="info-label">Panel klienta</p>
                    <h2>Nowoczesny ekran logowania</h2>
                    <p>
                        Ten widok zachowuje Twoją obecną logikę PHP, sesje, połączenie z bazą i pobieranie ustawień z pliku środowiskowego.
                    </p>

                    <div class="stats">
                        <div class="stat-card">
                            <strong>PHP</strong>
                            <span>sesje i logowanie</span>
                        </div>
                        <div class="stat-card">
                            <strong>.env</strong>
                            <span>konfiguracja DB</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <script src="./assets/js/login.js"></script>
</body>
</html>