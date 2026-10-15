<?php
require __DIR__ . '/includes/bootstrap.php';
$user = require_login();
$uid = (int)$user['id'];
$q = db()->prepare('SELECT role, content FROM (SELECT id, role, content FROM chat_messages WHERE user_id = ? ORDER BY id DESC LIMIT 30) x ORDER BY id ASC');
$q->execute([$uid]);
$msgs = $q->fetchAll();
$autoQ = mb_substr(trim((string)($_GET['q'] ?? '')), 0, 300);
$pageTitle = 'Armel IA'; $active = 'armel';
$guide = [['sel' => '#chatForm', 't' => 'Écrivez ici votre question sur vos cultures ou vos animaux.'], ['sel' => '#chipsRow', 't' => 'Pas d\'idée ? Touchez une question suggérée.']];
require __DIR__ . '/includes/header.php';
?>
<div class="d-flex align-items-center gap-3 mb-4">
  <span class="armel-ava"><i class="bi bi-robot"></i></span>
  <div><h1 class="greet mb-0">Armel <b>IA</b></h1><small class="text-secondary">Assistant agricole et élevage, Cameroun</small></div>
</div>
<section class="card-af chat">
  <div class="chat-log" id="chatLog" aria-live="polite">
    <?php if (!$msgs): ?>
      <div class="msg bot">Bonjour <?= e(explode(' ', $user['nom'])[0]) ?>, je suis Armel. Que voulez-vous savoir sur vos cultures ou vos animaux ?</div>
    <?php endif; ?>
    <?php foreach ($msgs as $m): ?>
      <div class="msg <?= $m['role'] === 'user' ? 'me' : 'bot' ?>"><?= e($m['content']) ?></div>
    <?php endforeach; ?>
  </div>
  <div class="chips-row" id="chipsRow">
    <?php foreach (['Mon cacao jaunit, que faire ?', 'Comment protéger mes poulets de la chaleur ?', 'Quand arroser mes tomates ?', 'Mon sol est trop sec'] as $c): ?>
      <button type="button" class="chip chip-btn" data-q="<?= e($c) ?>"><?= e($c) ?></button>
    <?php endforeach; ?>
  </div>
  <form id="chatForm" class="chat-form" autocomplete="off" data-autosend="<?= e($autoQ) ?>">
    <input class="form-control form-control-lg" id="chatInput" maxlength="800" placeholder="Écrivez votre question..." aria-label="Votre question" required>
    <button class="btn btn-af btn-lg" aria-label="Envoyer"><i class="bi bi-send-fill"></i></button>
  </form>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
