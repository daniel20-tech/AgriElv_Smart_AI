<?php
// API chat Armel : POST JSON {"message": "..."} + en-tête X-CSRF-Token
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/sensors.php';
require __DIR__ . '/../includes/armel.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['error' => 'Méthode non autorisée'], 405);
$user = require_login(true);
csrf_or_die(true);
$uid = (int)$user['id'];

$in = json_decode(file_get_contents('php://input', false, null, 0, 4096) ?: '', true);
$msg = trim((string)($in['message'] ?? ''));
if ($msg === '' || mb_strlen($msg) > 800) json_out(['error' => 'Message vide ou trop long (800 caractères max).'], 422);

// Limite : 10 messages par minute
$c = db()->prepare("SELECT COUNT(*) FROM chat_messages WHERE user_id = ? AND role = 'user' AND created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)");
$c->execute([$uid]);
if ((int)$c->fetchColumn() >= 10) json_out(['error' => 'Vous écrivez trop vite. Patientez une minute.'], 429);

// Historique récent (doit commencer par un message "user")
$h = db()->prepare('SELECT role, content FROM (SELECT id, role, content FROM chat_messages WHERE user_id = ? ORDER BY id DESC LIMIT 10) x ORDER BY id ASC');
$h->execute([$uid]);
$history = array_map(fn($m) => ['role' => $m['role'], 'content' => $m['content']], $h->fetchAll());
while ($history && $history[0]['role'] !== 'user') array_shift($history);
$history[] = ['role' => 'user', 'content' => $msg];

$reading = latest_reading($uid);
$reply = armel_llm($history, armel_system_prompt($user, $reading)) ?? armel_fallback($msg, $reading);

$ins = db()->prepare('INSERT INTO chat_messages (user_id, role, content) VALUES (?, ?, ?)');
$ins->execute([$uid, 'user', $msg]);
$ins->execute([$uid, 'assistant', $reply]);
json_out(['reply' => $reply]);
