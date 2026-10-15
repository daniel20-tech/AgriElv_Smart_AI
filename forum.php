<?php
// Forum de discussion façon WhatsApp : salons à gauche, conversation à droite
require __DIR__ . '/includes/bootstrap.php';
$user = require_login();
$uid = (int)$user['id'];
$rooms = db()->query('SELECT r.id, r.name, r.icon, r.description,
  (SELECT content FROM forum_messages m WHERE m.room_id = r.id ORDER BY m.id DESC LIMIT 1) AS last_msg,
  (SELECT created_at FROM forum_messages m WHERE m.room_id = r.id ORDER BY m.id DESC LIMIT 1) AS last_at
  FROM forum_rooms r ORDER BY r.id')->fetchAll();
$roomId = (int)($_GET['room'] ?? ($rooms[0]['id'] ?? 0));
$cur = null;
foreach ($rooms as $r) if ((int)$r['id'] === $roomId) $cur = $r;
if (!$cur && $rooms) { $cur = $rooms[0]; $roomId = (int)$cur['id']; }
$pageTitle = 'Forum'; $active = 'forum';
$extraJs = ['forum.js'];
$guide = [
    ['sel' => '#waList', 't' => 'Choisissez un salon : général, cultures, élevage ou marché.'],
    ['sel' => '#waForm', 't' => 'Écrivez votre message ici. Soyez respectueux : tout le monde peut vous lire.'],
    ['t' => "Astuce : partagez vos astuces et vos questions, la communauté vous répondra."],
];
require __DIR__ . '/includes/header.php';
?>
<div class="wa <?= isset($_GET['room']) ? 'in-room' : '' ?>" id="wa" data-room="<?= (int)$roomId ?>" data-me="<?= $uid ?>" data-admin="<?= $user['role'] === 'admin' ? 1 : 0 ?>">
  <aside class="wa-list" id="waList" aria-label="Salons">
    <div class="wa-list-head"><i class="bi bi-people-fill"></i> Forum AgriElv</div>
    <?php foreach ($rooms as $r): ?>
      <a class="wa-room <?= (int)$r['id'] === $roomId ? 'active' : '' ?>" href="forum.php?room=<?= (int)$r['id'] ?>" data-room="<?= (int)$r['id'] ?>">
        <span class="wa-ava"><i class="bi <?= e($r['icon']) ?>"></i></span>
        <span class="wa-room-txt"><strong><?= e($r['name']) ?></strong><small><?= $r['last_msg'] ? e(mb_strimwidth($r['last_msg'], 0, 38, '…')) : e($r['description']) ?></small></span>
        <small class="wa-time"><?= $r['last_at'] ? e(date('H:i', strtotime($r['last_at']))) : '' ?></small>
      </a>
    <?php endforeach; ?>
  </aside>
  <section class="wa-chat" aria-label="Conversation">
    <header class="wa-head">
      <a class="wa-back" href="forum.php" aria-label="Retour aux salons"><i class="bi bi-arrow-left"></i></a>
      <span class="wa-ava"><i class="bi <?= e($cur['icon'] ?? 'bi-chat') ?>"></i></span>
      <div><strong><?= e($cur['name'] ?? 'Forum') ?></strong><small class="d-block"><?= e($cur['description'] ?? '') ?></small></div>
    </header>
    <div class="wa-log" id="waLog" aria-live="polite"></div>
    <div class="wa-emojis" id="waEmojis" hidden>
      <?php foreach (['😀', '🌱', '🌽', '🐔', '🐖', '🐄', '💧', '☀️', '🌧️', '👍', '🙏', '🎉'] as $em): ?><button type="button" class="wa-emoji" data-e="<?= $em ?>"><?= $em ?></button><?php endforeach; ?>
    </div>
    <form class="wa-form" id="waForm" autocomplete="off">
      <button type="button" class="wa-ico" id="waEmojiBtn" aria-label="Emojis"><i class="bi bi-emoji-smile"></i></button>
      <input id="waInput" class="form-control" maxlength="1000" placeholder="Écrivez un message" aria-label="Votre message" required>
      <button class="wa-send" aria-label="Envoyer"><i class="bi bi-send-fill"></i></button>
    </form>
  </section>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
