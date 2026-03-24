<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/access_control.php';

header('Content-Type: text/html; charset=UTF-8');

function demo_output_error(string $message, int $status = 400): never
{
    http_response_code($status);
    echo '<!doctype html><html lang="pl"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Link demo</title></head><body style="font-family:Segoe UI,Tahoma,sans-serif;background:#0b1220;color:#e2e8f0;padding:40px;">';
    echo '<h1 style="margin-top:0;">Link demo niedostepny</h1>';
    echo '<p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>';
    echo '</body></html>';
    exit;
}

function demo_absolute_target(string $baseUrl): string
{
    if (preg_match('#^https?://#i', $baseUrl)) {
        return rtrim($baseUrl, '/');
    }

    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;
    $scheme = $isHttps ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    return $scheme . '://' . $host . '/' . ltrim($baseUrl, '/');
}

if (!startowa_table_exists($pdo, 'startowa_demo_links')) {
    demo_output_error('Tabela linkow demo nie istnieje jeszcze w bazie.', 503);
}

$demoKey = strtolower(trim((string) ($_GET['demo'] ?? '')));
if ($demoKey === '') {
    demo_output_error('Brak identyfikatora linku demo.');
}

$stmt = $pdo->prepare('SELECT * FROM startowa_demo_links WHERE public_key = ? AND is_active = 1 LIMIT 1');
$stmt->execute([$demoKey]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    demo_output_error('Taki link demo nie istnieje albo zostal wylaczony.', 404);
}

if (!empty($row['expires_at']) && strtotime((string) $row['expires_at']) < time()) {
    demo_output_error('Ten link demo wygasl.', 410);
}

$targetBaseUrl = trim((string) ($row['target_base_url'] ?? ''));
$autologinPath = trim((string) ($row['autologin_path'] ?? 'core/autologin-redirect.php'), '/');
$targetToken = trim((string) ($row['target_autologin_token'] ?? ''));

if ($targetBaseUrl === '' || $targetToken === '') {
    demo_output_error('Link demo jest niekompletny.', 500);
}

$targetUrl = demo_absolute_target($targetBaseUrl) . '/' . $autologinPath . '?token=' . urlencode($targetToken);

$pdo->prepare('UPDATE startowa_demo_links SET click_count = click_count + 1, last_used_at = NOW() WHERE id = ?')->execute([(int) $row['id']]);

header('Location: ' . $targetUrl);
exit;
