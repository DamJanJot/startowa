<?php
header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../../../core/env_loader.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
$prompt = trim((string)($data['prompt'] ?? ''));

if ($prompt === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Brak promptu']);
    exit;
}

$apiKey = getenv('OPENAI_API_KEY');

if (!$apiKey) {
    $demo = 'Demo: To jest odpowiedź lokalna. Dodaj OPENAI_API_KEY w .env, aby uruchomić pełny tryb AI.';
    echo json_encode(['answer' => $demo, 'demo' => true]);
    exit;
}

$payload = [
    'model' => 'gpt-4o-mini',
    'messages' => [
        ['role' => 'system', 'content' => 'Odpowiadasz krótko po polsku. Maksymalnie 3 zdania.'],
        ['role' => 'user', 'content' => $prompt],
    ],
    'temperature' => 0.6,
    'max_tokens' => 120,
];

$ch = curl_init('https://api.openai.com/v1/chat/completions');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $apiKey,
    'Content-Type: application/json',
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr = curl_error($ch);
curl_close($ch);

if ($response === false || $curlErr) {
    http_response_code(502);
    echo json_encode(['error' => 'Błąd połączenia z AI']);
    exit;
}

$decoded = json_decode($response, true);

if ($httpCode >= 400) {
    $err = $decoded['error']['message'] ?? 'Błąd API AI';
    http_response_code($httpCode);
    echo json_encode(['error' => $err]);
    exit;
}

$answer = trim((string)($decoded['choices'][0]['message']['content'] ?? ''));
if ($answer === '') {
    $answer = 'Brak odpowiedzi z modelu.';
}

echo json_encode(['answer' => $answer]);
