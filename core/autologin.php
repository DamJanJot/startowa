<?php
require_once __DIR__ . '/access_control.php';

startowa_start_session();

$token = $_GET['token'] ?? '';
if (!$token) {
    echo "Brak tokena.";
    exit;
}

// Szukamy tokena w bazie (bez sprawdzania 'used')
$stmt = $pdo->prepare("SELECT * FROM autologiny WHERE token = ? AND expires_at > NOW()");
$stmt->execute([$token]);
$row = $stmt->fetch();

if ($row) {
    // Pobieramy dane użytkownika
    $user_stmt = $pdo->prepare("SELECT id, imie, nazwisko, email, rola FROM uzytkownicy WHERE id = ?");
    $user_stmt->execute([$row['user_id']]);
    $user = $user_stmt->fetch();

    if ($user) {
        $_SESSION['loggedin'] = true;
        $_SESSION['id'] = $user['id'];
        $_SESSION['imie'] = $user['imie'] ?? '';
        $_SESSION['nazwisko'] = $user['nazwisko'] ?? '';
        $_SESSION['email'] = $user['email'];
        $_SESSION['rola'] = startowa_normalize_role((string) ($user['rola'] ?? 'user'));
        startowa_refresh_access_cache();

        // Nie oznaczamy tokena jako 'used' – działa wielokrotnie
        startowa_redirect('public/index.php');
    }
}

echo "Token nieważny lub wygasł.";
exit;
?>
