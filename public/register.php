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
$conn->set_charset('utf8mb4');

if ($conn->connect_error) {
    die('Połączenie nieudane: ' . $conn->connect_error);
}

$errorMessage = '';
$successMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $imie = trim($_POST['imie'] ?? '');
    $nazwisko = trim($_POST['nazwisko'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $haslo = trim($_POST['haslo'] ?? '');
    $haslo2 = trim($_POST['haslo2'] ?? '');

    if ($imie === '' || $nazwisko === '' || $email === '' || $haslo === '' || $haslo2 === '') {
        $errorMessage = 'Uzupełnij wszystkie pola.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errorMessage = 'Podaj poprawny adres e-mail.';
    } elseif (mb_strlen($haslo) < 6) {
        $errorMessage = 'Hasło musi mieć minimum 6 znaków.';
    } elseif ($haslo !== $haslo2) {
        $errorMessage = 'Hasła nie są takie same.';
    } else {
        $checkSql = 'SELECT id FROM uzytkownicy WHERE email = ? LIMIT 1';
        $checkStmt = $conn->prepare($checkSql);

        if (!$checkStmt) {
            $errorMessage = 'Wystąpił błąd podczas sprawdzania użytkownika.';
        } else {
            $checkStmt->bind_param('s', $email);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();

            if ($checkResult->num_rows > 0) {
                $errorMessage = 'Konto z tym adresem e-mail już istnieje.';
            } else {
                $hashedPassword = password_hash($haslo, PASSWORD_DEFAULT);
                $insertSql = 'INSERT INTO uzytkownicy (imie, nazwisko, email, haslo, rola) VALUES (?, ?, ?, ?, ?)';
                $insertStmt = $conn->prepare($insertSql);

                if (!$insertStmt) {
                    $errorMessage = 'Wystąpił błąd podczas rejestracji.';
                } else {
                    $defaultRole = 'user';
                    $insertStmt->bind_param('sssss', $imie, $nazwisko, $email, $hashedPassword, $defaultRole);

                    if ($insertStmt->execute()) {
                        header('Location: login.php?registered=1');
                        exit();
                    }

                    $errorMessage = 'Nie udało się utworzyć konta.';
                    $insertStmt->close();
                }
            }

            $checkStmt->close();
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
    <title>Rejestracja</title>
    <meta name="description" content="Załóż nowe konto">
    <link rel="icon" type="image/png" href="../assets/img/kossmo.png">
    <link rel="stylesheet" href="./assets/css/style.css">
    <style>
        .register-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .register-grid .field {
            margin: 0;
        }

        .success-message {
            min-height: 20px;
            margin: 2px 0 0;
            color: #86efac;
            font-size: 14px;
            opacity: 1;
        }

        @media (max-width: 700px) {
            .register-grid {
                grid-template-columns: 1fr;
                gap: 10px;
            }
        }
    </style>
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
                <h1>Załóż konto</h1>
                <p class="subtitle">
                    Utwórz konto, aby dostać dostęp do panelu użytkownika.
                </p>

                <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="login-form" id="registerForm">
                    <div class="register-grid">
                        <div class="field">
                            <label for="imie">Imię</label>
                            <input
                                type="text"
                                id="imie"
                                name="imie"
                                placeholder="Np. Jan"
                                value="<?php echo htmlspecialchars($_POST['imie'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                required
                            >
                        </div>

                        <div class="field">
                            <label for="nazwisko">Nazwisko</label>
                            <input
                                type="text"
                                id="nazwisko"
                                name="nazwisko"
                                placeholder="Np. Kowalski"
                                value="<?php echo htmlspecialchars($_POST['nazwisko'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                required
                            >
                        </div>
                    </div>

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

                    <div class="register-grid">
                        <div class="field">
                            <label for="password">Hasło</label>
                            <div class="password-wrap">
                                <input
                                    type="password"
                                    id="password"
                                    name="haslo"
                                    placeholder="Min. 6 znaków"
                                    required
                                >
                                <button type="button" class="toggle-password" id="togglePassword" aria-label="Pokaż lub ukryj hasło">
                                    Pokaż
                                </button>
                            </div>
                        </div>

                        <div class="field">
                            <label for="password2">Powtórz hasło</label>
                            <input
                                type="password"
                                id="password2"
                                name="haslo2"
                                placeholder="Powtórz hasło"
                                required
                            >
                        </div>
                    </div>

                    <button type="submit" class="login-btn">Utwórz konto</button>

                    <p class="status-message <?php echo $errorMessage !== '' ? 'show' : ''; ?>" id="statusMessage">
                        <?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?>
                    </p>

                    <?php if ($successMessage !== ''): ?>
                        <p class="success-message"><?php echo htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8'); ?></p>
                    <?php endif; ?>
                </form>

                <div class="divider">
                    <span>konto użytkownika</span>
                </div>

                <div class="bottom-links">
                    <a class="register-link" href="./login.php">Masz konto? Zaloguj się</a>
                </div>
            </div>

            <div class="login-right">
                <div class="info-box reveal">
                    <p class="info-label">Panel klienta</p>
                    <h2>Rejestracja</h2>
                    <p>
                        Tworzenie konta zapisuje bezpieczny hash hasła i automatycznie ustawia rolę użytkownika.
                    </p>

                    <div class="stats">
                        <div class="stat-card">
                            <strong>SHA</strong>
                            <span>hash hasła</span>
                        </div>
                        <div class="stat-card">
                            <strong>USER</strong>
                            <span>domyślna rola</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <script src="./assets/js/login.js"></script>
    <script>
        const password2 = document.getElementById('password2');
        const togglePassword = document.getElementById('togglePassword');
        const password = document.getElementById('password');

        if (togglePassword && password && password2) {
            togglePassword.addEventListener('click', () => {
                const isPassword = password.type === 'password';
                password.type = isPassword ? 'text' : 'password';
                password2.type = isPassword ? 'text' : 'password';
                togglePassword.textContent = isPassword ? 'Ukryj' : 'Pokaż';
            });
        }
    </script>
</body>
</html>
