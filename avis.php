<?php
require __DIR__ . '/includes/bootstrap.php';
$user = require_login();
$uid = (int)$user['id'];
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_or_die();
    $rating = (int)($_POST['rating'] ?? 0);
    $comment = trim((string)($_POST['comment'] ?? ''));
    if ($rating < 1 || $rating > 5) $errors[] = 'Choisissez une note de 1 à 5 étoiles.';
    if (mb_strlen($comment) < 3 || mb_strlen($comment) > 500) $errors[] = 'Votre commentaire doit faire entre 3 et 500 caractères.';
    if (!$errors) {
        $c = db()->prepare('SELECT COUNT(*) FROM reviews WHERE user_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)');
        $c->execute([$uid]);
        if ((int)$c->fetchColumn() > 0) $errors[] = 'Vous avez déjà publié un avis récemment. Réessayez dans 1 heure.';
    }
    if (!$errors) {
        db()->prepare('INSERT INTO reviews (user_id, rating, comment) VALUES (?,?,?)')->execute([$uid, $rating, $comment]);
        flash('ok', 'Avis publié. Merci !');
        redirect('avis.php');
    }
}
$avg = db()->query('SELECT ROUND(AVG(rating),1) a, COUNT(*) n FROM reviews')->fetch();
$list = db()->query('SELECT r.rating, r.comment, r.created_at, u.nom FROM reviews r JOIN users u ON u.id = r.user_id ORDER BY r.created_at DESC, r.id DESC LIMIT 30')->fetchAll();
$pageTitle = 'Avis'; $active = 'avis';
$guide = [['sel' => '.stars', 't' => 'Touchez les étoiles pour noter l\'application, puis écrivez un petit commentaire.']];
require __DIR__ . '/includes/header.php';
$stars = function (int $n): string {
    $h = '';
    for ($i = 1; $i <= 5; $i++) $h .= '<i class="bi ' . ($i <= $n ? 'bi-star-fill' : 'bi-star') . '"></i>';
    return $h;
};
?>
<h1 class="greet mb-4">Avis de la <b>communauté</b></h1>
<div class="row g-3">
  <div class="col-12 col-lg-5">
    <form method="post" class="card-af" novalidate>
      <?= csrf_field() ?>
      <div class="card-head"><span><i class="bi bi-pencil-square"></i> Donner mon avis</span></div>
      <?php if ($errors): ?><div class="alert alert-danger rounded-4 py-2 mt-2" role="alert"><?php foreach ($errors as $er): ?><div><?= e($er) ?></div><?php endforeach; ?></div><?php endif; ?>
      <div class="stars my-3" role="radiogroup" aria-label="Note">
        <?php for ($i = 5; $i >= 1; $i--): ?>
          <input type="radio" name="rating" id="s<?= $i ?>" value="<?= $i ?>" required><label for="s<?= $i ?>" title="<?= $i ?> étoile(s)"><i class="bi bi-star-fill"></i></label>
        <?php endfor; ?>
      </div>
      <textarea class="form-control mb-3" name="comment" rows="4" maxlength="500" placeholder="Ce qui vous plaît, ce qu'on peut améliorer..." required></textarea>
      <button class="btn btn-af w-100">Publier mon avis</button>
    </form>
  </div>
  <div class="col-12 col-lg-7">
    <section class="card-af">
      <div class="card-head"><span><i class="bi bi-people-fill"></i> Avis des utilisateurs</span>
        <?php if ((int)$avg['n'] > 0): ?><span class="chip"><i class="bi bi-star-fill"></i> <?= e($avg['a']) ?>/5 (<?= (int)$avg['n'] ?>)</span><?php endif; ?></div>
      <?php if (!$list): ?>
        <div class="empty"><i class="bi bi-chat-heart"></i><p>Aucun avis pour l'instant. Soyez le premier.</p></div>
      <?php endif; ?>
      <?php foreach ($list as $r): ?>
        <article class="review">
          <div class="avatar sm"><?= e(mb_strtoupper(mb_substr($r['nom'], 0, 1))) ?></div>
          <div class="flex-grow-1">
            <div class="d-flex justify-content-between"><strong><?= e(explode(' ', $r['nom'])[0]) ?></strong><small class="text-secondary"><?= date('d/m/Y', strtotime($r['created_at'])) ?></small></div>
            <div class="star-row"><?= $stars((int)$r['rating']) ?></div>
            <p class="mb-0"><?= e($r['comment']) ?></p>
          </div>
        </article>
      <?php endforeach; ?>
    </section>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
