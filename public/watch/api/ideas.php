<?php
header('Content-Type: application/json; charset=UTF-8');

$dataFile = __DIR__ . '/../data/ideas.json';

function readIdeas(string $path): array
{
    if (!file_exists($path)) {
        return [];
    }

    $raw = file_get_contents($path);
    if ($raw === false || $raw === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function writeIdeas(string $path, array $items): bool
{
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        return false;
    }

    return file_put_contents($path, json_encode($items, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) !== false;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    $items = readIdeas($dataFile);
    echo json_encode(['items' => $items]);
    exit;
}

if ($method === 'POST') {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    $text = trim((string)($data['text'] ?? ''));

    if ($text === '') {
        http_response_code(400);
        echo json_encode(['error' => 'Pusty pomysł']);
        exit;
    }

    $items = readIdeas($dataFile);
    array_unshift($items, [
        'text' => htmlspecialchars($text, ENT_QUOTES, 'UTF-8'),
        'createdAt' => date('Y-m-d H:i:s'),
    ]);

    $items = array_slice($items, 0, 150);

    if (!writeIdeas($dataFile, $items)) {
        http_response_code(500);
        echo json_encode(['error' => 'Nie udało się zapisać']);
        exit;
    }

    echo json_encode(['ok' => true, 'count' => count($items)]);
    exit;
}

if ($method === 'DELETE') {
    if (!writeIdeas($dataFile, [])) {
        http_response_code(500);
        echo json_encode(['error' => 'Nie udało się wyczyścić']);
        exit;
    }

    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
