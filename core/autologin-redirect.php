<?php
require_once __DIR__ . '/access_control.php';

startowa_start_session();

$token = $_GET['token'] ?? '';
if (!$token) {
    echo "Brak tokena.";
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM autologiny WHERE token = ? AND expires_at > NOW()");
$stmt->execute([$token]);
$row = $stmt->fetch();

if ($row) {
    $pdo->prepare("UPDATE autologiny SET uses = uses + 1 WHERE id = ?")->execute([$row['id']]);

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

        $redirect = $row['redirect_to'] ?? '/';
        header("Location: " . $redirect);
        exit;
    }
}

echo "Token nieważny lub wygasł.";
exit;
?>
