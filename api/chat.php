<?php
/**
 * TCC RRS – AI chat endpoint (Groq)
 * POST JSON: { message, page, history: [{role, content}] }
 * Returns:   { reply } | { error }
 */
require_once __DIR__ . '/../includes/ai.php';
startSession();

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}
if (!aiEnabled()) {
    http_response_code(503);
    echo json_encode(['error' => 'AI assistant is not configured.']);
    exit;
}

$in      = json_decode(file_get_contents('php://input'), true) ?: [];
$message = trim(mb_substr((string)($in['message'] ?? ''), 0, 1000));
$page    = mb_substr((string)($in['page'] ?? ''), 0, 200);
$history = $in['history'] ?? [];

if ($message === '') {
    http_response_code(422);
    echo json_encode(['error' => 'Empty message.']);
    exit;
}

// Light per-session rate limit: 20 messages / minute
$_SESSION['ai_hits'] = array_filter(
    $_SESSION['ai_hits'] ?? [],
    fn($t) => $t > time() - 60
);
if (count($_SESSION['ai_hits']) >= 20) {
    http_response_code(429);
    echo json_encode(['error' => 'Too many messages — please wait a moment.']);
    exit;
}
$_SESSION['ai_hits'][] = time();

// Assemble conversation: system prompt + last 8 turns + new message
$messages = [['role' => 'system', 'content' => aiSystemPrompt($page)]];
foreach (array_slice($history, -8) as $h) {
    if (!is_array($h)) continue;
    $role = $h['role'] ?? '';
    if (!in_array($role, ['user', 'assistant'], true)) continue;
    $messages[] = ['role' => $role, 'content' => mb_substr((string)($h['content'] ?? ''), 0, 2000)];
}
$messages[] = ['role' => 'user', 'content' => $message];

$reply = groqChat($messages);
if ($reply === null) {
    http_response_code(502);
    echo json_encode(['error' => 'The assistant could not be reached. Check the Groq API key and try again.']);
    exit;
}

echo json_encode(['reply' => $reply]);
