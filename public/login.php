<?php
session_start();
header('Content-Type: text/html; charset=UTF-8');

require_once __DIR__ . '/../core/env_loader.php';

$host = getenv('DB_HOST');
$dbname = getenv('DB_NAME');
$username = getenv('DB_USER');
$password = getenv('DB_PASS');

if (!$host || !$dbname || !$username) {
    die('Brak konfiguracji DB w zmiennych środowiskowych (DB_HOST, DB_NAME, DB_USER, DB_PASS).');
}

$conn = new mysqli($host, $username, $password, $dbname);
$conn->set_charset("utf8mb4");

if ($conn->connect_error) {
    die("Połączenie nieudane: " . $conn->connect_error);
}

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
        $sql = "SELECT id, imie, nazwisko, haslo, rola FROM uzytkownicy WHERE email = ?";
        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            $errorMessage = 'Wystąpił błąd podczas przygotowania zapytania.';
        } else {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();

                if (password_verify($haslo, $row['haslo'])) {
                    $_SESSION['loggedin'] = true;
                    $_SESSION['id'] = $row['id'];
                    $_SESSION['imie'] = $row['imie'];
                    $_SESSION['nazwisko'] = $row['nazwisko'];
                    $_SESSION['rola'] = $row['rola'];

                    if ($row['rola'] === 'admin') {
                        header("Location: admin/index.php");
                    } else {
                        header("Location: index.php?id=" . $row['id']);
                    }
                    exit();
                } else {
                    $errorMessage = 'Błędne hasło.';
                }
            } else {
                $errorMessage = 'Nie znaleziono użytkownika z tym adresem e-mail.';
            }

            $stmt->close();
        }
    }
}

$conn->close();
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