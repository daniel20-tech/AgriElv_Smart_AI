<?php
// API forum.
//  GET  ?room=ID&after=ID : messages (les 50 derniers si after=0)
//  POST {room, text}      : publier   |  POST {delete: ID} : supprimer le sien (ou tout si admin)
require __DIR__ . '/../includes/bootstrap.php';
$user = require_login(true);
$uid = (int)$user['id'];

function room_exists(int $id): bool
{
    $q = db()->prepare('SELECT 1 FROM forum_rooms WHERE id = ?');
    $q->execute([$id]);
    return (bool)$q->fetchColumn();
}
function msg_out(array $m): array
{
    return ['id' => (int)$m['id'], 'uid' => (int)$m['user_id'], 'name' => explode(' ', (string)$m['nom'])[0], 'text' => $m['content'], 'ts' => date('c', strtotime($m['created_at']))];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_or_die(true);
    $in = json_decode(file_get_contents('php://input', false, null, 0, 4096) ?: '', true);
    if (!is_array($in)) json_out(['error' => 'JSON invalide'], 400);

    if (isset($in['delete'])) {
        $id = (int)$in['delete'];
        if ($user['role'] === 'admin') db()->prepare('DELETE FROM forum_messages WHERE id = ?')->execute([$id]);
        else db()->prepare('DELETE FROM forum_messages WHERE id = ? AND user_id = ?')->execute([$id, $uid]);
        json_out(['ok' => true]);
    }
    $room = (int)($in['room'] ?? 0);
    $text = trim((string)($in['text'] ?? ''));
    if (!room_exists($room)) json_out(['error' => 'Salon introuvable'], 404);
    if ($text === '' || mb_strlen($text) > 1000) json_out(['error' => 'Message vide ou trop long (1000 caractères max).'], 422);
    $c = db()->prepare('SELECT COUNT(*) FROM forum_messages WHERE user_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)');
    $c->execute([$uid]);
    if ((int)$c->fetchColumn() >= 15) json_out(['error' => 'Vous écrivez trop vite. Patientez un instant.'], 429);
    db()->prepare('INSERT INTO forum_messages (room_id, user_id, content) VALUES (?,?,?)')->execute([$room, $uid, $text]);
    $id = (int)db()->lastInsertId();
    json_out(['message' => msg_out(['id' => $id, 'user_id' => $uid, 'nom' => $user['nom'], 'content' => $text, 'created_at' => date('Y-m-d H:i:s')])], 201);
}

$room = (int)($_GET['room'] ?? 0);
$after = max(0, (int)($_GET['after'] ?? 0));
if (!room_exists($room)) json_out(['error' => 'Salon introuvable'], 404);
if ($after === 0) {
    $q = db()->prepare('SELECT * FROM (SELECT m.id, m.user_id, m.content, m.created_at, u.nom FROM forum_messages m JOIN users u ON u.id = m.user_id WHERE m.room_id = ? ORDER BY m.id DESC LIMIT 50) x ORDER BY id ASC');
    $q->execute([$room]);
} else {
    $q = db()->prepare('SELECT m.id, m.user_id, m.content, m.created_at, u.nom FROM forum_messages m JOIN users u ON u.id = m.user_id WHERE m.room_id = ? AND m.id > ? ORDER BY m.id ASC LIMIT 100');
    $q->execute([$room, $after]);
}
json_out(['messages' => array_map('msg_out', $q->fetchAll())]);
