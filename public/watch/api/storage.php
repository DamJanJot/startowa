<?php
header('Content-Type: application/json; charset=UTF-8');

$resource = $_GET['resource'] ?? '';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

$notesTable = 'watch_voice_notes';
$notesFile = __DIR__ . '/../data/voice_notes.json';
$recordingsMetaFile = __DIR__ . '/../data/recordings.json';
$recordingsDir = __DIR__ . '/../uploads/recordings';

function respond(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function readJsonBody(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function ensureDir(string $dir): bool
{
    if (is_dir($dir)) {
        return true;
    }
    return mkdir($dir, 0775, true) || is_dir($dir);
}

function readJsonList(string $path): array
{
    if (!file_exists($path)) {
        return [];
    }

    $raw = file_get_contents($path);
    if ($raw === false || $raw === '') {
        return [];
    }

    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function writeJsonList(string $path, array $items): bool
{
    $dir = dirname($path);
    if (!ensureDir($dir)) {
        return false;
    }

    return file_put_contents($path, json_encode($items, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) !== false;
}

function getPdoOrNull(): ?PDO
{
    require_once __DIR__ . '/../../../core/env_loader.php';

    $host = getenv('DB_HOST') ?: '';
    $name = getenv('DB_NAME') ?: '';
    $user = getenv('DB_USER') ?: '';
    $pass = getenv('DB_PASS') ?: '';

    if ($host === '' || $name === '' || $user === '') {
        return null;
    }

    try {
        $pdo = new PDO(
            "mysql:host={$host};dbname={$name};charset=utf8mb4",
            $user,
            $pass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        return $pdo;
    } catch (Throwable $e) {
        return null;
    }
}

function notesGetFromFile(string $notesFile): array
{
    $items = readJsonList($notesFile);
    return ['items' => array_values($items)];
}

function notesPostToFile(string $notesFile, string $text): array
{
    $items = readJsonList($notesFile);
    $maxId = 0;
    foreach ($items as $it) {
        $id = (int)($it['id'] ?? 0);
        if ($id > $maxId) {
            $maxId = $id;
        }
    }

    $newItem = [
        'id' => $maxId + 1,
        'text' => $text,
        'date' => date('Y-m-d H:i:s'),
    ];

    array_unshift($items, $newItem);
    $items = array_slice($items, 0, 150);

    if (!writeJsonList($notesFile, $items)) {
        respond(['error' => 'Blad zapisu notatki (plik fallback)'], 500);
    }

    return ['ok' => true, 'id' => (int)$newItem['id'], 'fallback' => true];
}

function notesDeleteFromFile(string $notesFile, int $id): array
{
    $items = readJsonList($notesFile);
    $kept = [];
    $deleted = false;

    foreach ($items as $item) {
        if (!$deleted && (int)($item['id'] ?? 0) === $id) {
            $deleted = true;
            continue;
        }
        $kept[] = $item;
    }

    if (!$deleted) {
        respond(['error' => 'Nie znaleziono notatki'], 404);
    }

    if (!writeJsonList($notesFile, $kept)) {
        respond(['error' => 'Blad usuwania notatki (plik fallback)'], 500);
    }

    return ['ok' => true, 'fallback' => true];
}

if ($resource === 'notes') {
    $pdo = getPdoOrNull();

    if ($method === 'GET') {
        if ($pdo instanceof PDO) {
            try {
                $pdo->exec("CREATE TABLE IF NOT EXISTS {$notesTable} (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    note_text TEXT NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

                $stmt = $pdo->query("SELECT id, note_text, created_at FROM {$notesTable} ORDER BY id DESC LIMIT 150");
                $items = [];
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $items[] = [
                        'id' => (int)$row['id'],
                        'text' => (string)$row['note_text'],
                        'date' => (string)$row['created_at'],
                    ];
                }

                respond(['items' => $items]);
            } catch (Throwable $e) {
                respond(notesGetFromFile($notesFile));
            }
        }

        respond(notesGetFromFile($notesFile));
    }

    if ($method === 'POST') {
        $body = readJsonBody();
        $text = trim((string)($body['text'] ?? ''));

        if ($text === '') {
            respond(['error' => 'Pusty tekst notatki'], 400);
        }

        if ($pdo instanceof PDO) {
            try {
                $pdo->exec("CREATE TABLE IF NOT EXISTS {$notesTable} (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    note_text TEXT NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

                $stmt = $pdo->prepare("INSERT INTO {$notesTable} (note_text) VALUES (:txt)");
                $stmt->execute([':txt' => $text]);
                respond(['ok' => true, 'id' => (int)$pdo->lastInsertId()]);
            } catch (Throwable $e) {
                respond(notesPostToFile($notesFile, $text));
            }
        }

        respond(notesPostToFile($notesFile, $text));
    }

    if ($method === 'DELETE') {
        $body = readJsonBody();
        $id = (int)($body['id'] ?? 0);

        if ($id <= 0) {
            respond(['error' => 'Nieprawidlowe id notatki'], 400);
        }

        if ($pdo instanceof PDO) {
            try {
                $stmt = $pdo->prepare("DELETE FROM {$notesTable} WHERE id = :id");
                $stmt->execute([':id' => $id]);
                if ($stmt->rowCount() > 0) {
                    respond(['ok' => true]);
                }
            } catch (Throwable $e) {
                respond(notesDeleteFromFile($notesFile, $id));
            }
        }

        respond(notesDeleteFromFile($notesFile, $id));
    }

    respond(['error' => 'Method not allowed'], 405);
}

if ($resource === 'recordings') {
    if ($method === 'GET') {
        $items = readJsonList($recordingsMetaFile);
        respond(['items' => $items]);
    }

    if ($method === 'POST') {
        if (!isset($_FILES['audio']) || !is_array($_FILES['audio'])) {
            respond(['error' => 'Brak pliku audio'], 400);
        }

        if (!ensureDir($recordingsDir)) {
            respond(['error' => 'Nie mozna utworzyc katalogu nagran'], 500);
        }

        $file = $_FILES['audio'];
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            respond(['error' => 'Blad uploadu nagrania'], 400);
        }

        $tmpPath = (string)$file['tmp_name'];
        $mime = '';
        if (function_exists('mime_content_type')) {
            $mime = (string)(mime_content_type($tmpPath) ?: '');
        }
        if ($mime === '') {
            $mime = (string)($file['type'] ?? 'application/octet-stream');
        }

        $allowedMimes = [
            'audio/webm',
            'audio/ogg',
            'audio/wav',
            'audio/mpeg',
            'audio/mp4',
            'audio/x-m4a',
            'audio/aac',
            'application/octet-stream',
        ];

        if (!in_array($mime, $allowedMimes, true)) {
            respond(['error' => 'Nieobslugiwany format audio'], 400);
        }

        $ext = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
        if ($ext === '') {
            $ext = 'webm';
        }
        $safeExt = preg_replace('/[^a-z0-9]/', '', $ext);
        if ($safeExt === '') {
            $safeExt = 'webm';
        }

        $id = 'rec_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4));
        $fileName = $id . '.' . $safeExt;
        $destPath = $recordingsDir . '/' . $fileName;

        if (!move_uploaded_file($tmpPath, $destPath)) {
            respond(['error' => 'Nie udalo sie zapisac pliku'], 500);
        }

        $items = readJsonList($recordingsMetaFile);
        array_unshift($items, [
            'id' => $id,
            'fileName' => $fileName,
            'url' => './uploads/recordings/' . rawurlencode($fileName),
            'mime' => $mime,
            'size' => (int)filesize($destPath),
            'date' => date('Y-m-d H:i:s'),
        ]);

        $items = array_slice($items, 0, 200);
        if (!writeJsonList($recordingsMetaFile, $items)) {
            respond(['error' => 'Nagranie zapisane, ale nie udalo sie zapisac metadanych'], 500);
        }

        respond(['ok' => true, 'item' => $items[0]]);
    }

    if ($method === 'DELETE') {
        $body = readJsonBody();
        $id = trim((string)($body['id'] ?? ''));
        if ($id === '') {
            respond(['error' => 'Brak id nagrania'], 400);
        }

        $items = readJsonList($recordingsMetaFile);
        $kept = [];
        $deleted = false;

        foreach ($items as $item) {
            if (!$deleted && isset($item['id']) && (string)$item['id'] === $id) {
                $fileName = (string)($item['fileName'] ?? '');
                if ($fileName !== '') {
                    $filePath = $recordingsDir . '/' . $fileName;
                    if (is_file($filePath)) {
                        @unlink($filePath);
                    }
                }
                $deleted = true;
                continue;
            }
            $kept[] = $item;
        }

        if (!$deleted) {
            respond(['error' => 'Nie znaleziono nagrania'], 404);
        }

        if (!writeJsonList($recordingsMetaFile, $kept)) {
            respond(['error' => 'Usunieto plik, ale nie udalo sie zapisac metadanych'], 500);
        }

        respond(['ok' => true]);
    }

    respond(['error' => 'Method not allowed'], 405);
}

respond(['error' => 'Nieznany resource'], 404);

