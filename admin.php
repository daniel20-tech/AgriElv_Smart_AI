<?php
// Tableau de bord administrateur : statistiques, utilisateurs, avis, modération du forum
require __DIR__ . '/includes/bootstrap.php';
$me = require_admin();
$myId = (int)$me['id'];
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_or_die();
    $act = (string)($_POST['action'] ?? '');
    $id = (int)($_POST['id'] ?? 0);
    $tab = in_array($_POST['tab'] ?? '', ['stats', 'users', 'avis', 'forum'], true) ? $_POST['tab'] : 'users';
    if (in_array($act, ['toggle_active', 'toggle_role', 'delete_user'], true)) {
        $t = $pdo->prepare('SELECT id, role, is_active FROM users WHERE id = ?');
        $t->execute([$id]);
        $target = $t->fetch();
        if (!$target) flash('err', 'Utilisateur introuvable.');
        elseif ($id === $myId) flash('err', 'Vous ne pouvez pas modifier votre propre compte ici.');
        elseif ($act === 'toggle_active') {
            $pdo->prepare('UPDATE users SET is_active = ? WHERE id = ?')->execute([(int)$target['is_active'] ? 0 : 1, $id]);
            flash('ok', (int)$target['is_active'] ? 'Compte suspendu.' : 'Compte réactivé.');
        } elseif ($act === 'toggle_role') {
            $pdo->prepare('UPDATE users SET role = ? WHERE id = ?')->execute([$target['role'] === 'admin' ? 'user' : 'admin', $id]);
            flash('ok', 'Rôle mis à jour.');
        } else {
            $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
            flash('ok', 'Utilisateur supprimé avec toutes ses données.');
        }
    } elseif ($act === 'delete_review') {
        $pdo->prepare('DELETE FROM reviews WHERE id = ?')->execute([$id]);
        flash('ok', 'Avis supprimé.');
    } elseif ($act === 'delete_msg') {
        $pdo->prepare('DELETE FROM forum_messages WHERE id = ?')->execute([$id]);
        flash('ok', 'Message supprimé.');
    }
    redirect('admin.php?tab=' . $tab);
}

$tab = in_array($_GET['tab'] ?? '', ['stats', 'users', 'avis', 'forum'], true) ? $_GET['tab'] : 'stats';
$one = fn(string $sql) => (int)$pdo->query($sql)->fetchColumn();

if ($tab === 'stats') {
    $k = [
        ['bi-people-fill', 'Utilisateurs', $one('SELECT COUNT(*) FROM users')],
        ['bi-person-plus-fill', 'Inscrits (7 j)', $one('SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)')],
        ['bi-activity', 'Actifs (7 j)', $one('SELECT COUNT(DISTINCT user_id) FROM sensor_readings WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)')],
        ['bi-bell-fill', 'Alertes', $one('SELECT COUNT(*) FROM alerts')],
        ['bi-database-fill', 'Mesures', $one('SELECT COUNT(*) FROM sensor_readings')],
        ['bi-camera-video-fill', 'Caméras', $one('SELECT COUNT(*) FROM cameras')],
        ['bi-cpu-fill', 'Capteurs', $one('SELECT COUNT(*) FROM devices')],
        ['bi-robot', 'Messages Armel', $one("SELECT COUNT(*) FROM chat_messages WHERE role = 'user'")],
        ['bi-chat-dots-fill', 'Messages forum', $one('SELECT COUNT(*) FROM forum_messages')],
        ['bi-star-fill', 'Avis', $one('SELECT COUNT(*) FROM reviews')],
    ];
    $avg = $pdo->query('SELECT ROUND(AVG(rating),1) FROM reviews')->fetchColumn();
    $types = $pdo->query('SELECT type_exploitation t, COUNT(*) n FROM users GROUP BY type_exploitation')->fetchAll(PDO::FETCH_KEY_PAIR);
    $totalU = max(1, array_sum($types));
    $su = [];
    foreach ($pdo->query('SELECT DATE(created_at) d, COUNT(*) n FROM users WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 13 DAY) GROUP BY DATE(created_at)')->fetchAll() as $r) $su[$r['d']] = (int)$r['n'];
    $days = [];
    for ($i = 13; $i >= 0; $i--) { $d = date('Y-m-d', strtotime("-$i day")); $days[] = ['label' => date('d/m', strtotime($d)), 'n' => $su[$d] ?? 0]; }
    $maxD = max(1, max(array_column($days, 'n')));
} elseif ($tab === 'users') {
    $qs = trim((string)($_GET['q'] ?? ''));
    if ($qs !== '') {
        $st = $pdo->prepare('SELECT id, nom, email, type_exploitation, role, is_active, created_at FROM users WHERE nom LIKE ? OR email LIKE ? ORDER BY id DESC LIMIT 100');
        $like = '%' . addcslashes($qs, '%_\\') . '%';
        $st->execute([$like, $like]);
    } else {
        $st = $pdo->query('SELECT id, nom, email, type_exploitation, role, is_active, created_at FROM users ORDER BY id DESC LIMIT 100');
    }
    $users = $st->fetchAll();
} elseif ($tab === 'avis') {
    $reviews = $pdo->query('SELECT r.id, r.rating, r.comment, r.created_at, u.nom, u.email FROM reviews r JOIN users u ON u.id = r.user_id ORDER BY r.id DESC LIMIT 100')->fetchAll();
} else {
    $msgs = $pdo->query('SELECT m.id, m.content, m.created_at, u.nom, r.name AS room FROM forum_messages m JOIN users u ON u.id = m.user_id JOIN forum_rooms r ON r.id = m.room_id ORDER BY m.id DESC LIMIT 60')->fetchAll();
}
$pageTitle = 'Administration'; $active = 'admin';
$guide = [['t' => "Espace administrateur : suivez les statistiques du site, gérez les comptes, les avis et le forum."]];
require __DIR__ . '/includes/header.php';
$tabs = ['stats' => ['bi-graph-up', 'Statistiques'], 'users' => ['bi-people-fill', 'Utilisateurs'], 'avis' => ['bi-star-fill', 'Avis'], 'forum' => ['bi-chat-dots-fill', 'Forum']];
$typeLbl = ['agriculture' => 'Agriculture', 'elevage' => 'Élevage', 'mixte' => 'Mixte'];
function admin_form(string $action, int $id, string $tab, string $icon, string $label, string $confirm = '', string $cls = 'icon-btn'): string
{
    return '<form method="post" class="d-inline"' . ($confirm ? ' data-confirm="' . e($confirm) . '"' : '') . '>' . csrf_field()
        . '<input type="hidden" name="action" value="' . e($action) . '"><input type="hidden" name="id" value="' . $id . '"><input type="hidden" name="tab" value="' . e($tab) . '">'
        . '<button class="' . $cls . '" title="' . e($label) . '" aria-label="' . e($label) . '"><i class="bi ' . $icon . '"></i></button></form>';
}
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
  <h1 class="greet">Espace <b>admin</b></h1>
  <div class="admin-tabs" role="tablist">
    <?php foreach ($tabs as $k2 => [$ic, $lb]): ?><a class="<?= $tab === $k2 ? 'active' : '' ?>" href="admin.php?tab=<?= $k2 ?>"><i class="bi <?= $ic ?>"></i> <?= $lb ?></a><?php endforeach; ?>
  </div>
</div>

<?php if ($tab === 'stats'): ?>
  <div class="row g-3 mb-3">
    <?php foreach ($k as [$ic, $lb, $v]): ?>
      <div class="col-6 col-md-4 col-xl"><section class="card-af kpi"><i class="bi <?= $ic ?>"></i><div class="mid"><span data-count="<?= $v ?>"><?= $v ?></span></div><small><?= $lb ?></small></section></div>
    <?php endforeach; ?>
  </div>
  <div class="row g-3">
    <div class="col-12 col-lg-7"><section class="card-af">
      <div class="card-head"><span><i class="bi bi-person-plus-fill"></i> Inscriptions (14 jours)</span></div>
      <div class="bars sm" role="img" aria-label="Inscriptions par jour">
        <?php foreach ($days as $d): ?><div class="bar-col"><div class="bar-wrap"><div class="bar <?= $d['n'] === $maxD && $d['n'] > 0 ? 'active' : '' ?>" style="height:<?= max(6, (int)round(100 * $d['n'] / $maxD)) ?>%"><span class="bar-tip"><?= $d['n'] ?></span></div></div><span class="bar-lbl"><?= e(substr($d['label'], 0, 2)) ?></span></div><?php endforeach; ?>
      </div>
    </section></div>
    <div class="col-12 col-lg-5"><section class="card-af">
      <div class="card-head"><span><i class="bi bi-pie-chart-fill"></i> Types d'exploitation</span><span class="chip"><i class="bi bi-star-fill"></i> <?= $avg !== false && $avg !== null ? e($avg) : '--' ?>/5</span></div>
      <?php foreach ($typeLbl as $key => $lab): $n = (int)($types[$key] ?? 0); $pc = (int)round(100 * $n / $totalU); ?>
        <div class="dist"><div class="d-flex justify-content-between"><span><?= $lab ?></span><b><?= $n ?> (<?= $pc ?>%)</b></div><div class="progress af-progress"><div class="progress-bar" style="width:<?= $pc ?>%"></div></div></div>
      <?php endforeach; ?>
    </section></div>
  </div>

<?php elseif ($tab === 'users'): ?>
  <section class="card-af">
    <form method="get" class="d-flex gap-2 mb-2"><input type="hidden" name="tab" value="users"><input class="form-control rounded-pill" name="q" value="<?= e($qs) ?>" placeholder="Rechercher un nom ou un email" aria-label="Rechercher"><button class="btn btn-af"><i class="bi bi-search"></i></button></form>
    <div class="table-responsive"><table class="table table-af align-middle mb-0">
      <thead><tr><th>Utilisateur</th><th>Type</th><th>Rôle</th><th>Statut</th><th>Inscrit le</th><th class="text-end">Actions</th></tr></thead>
      <tbody>
      <?php foreach ($users as $u): $self = (int)$u['id'] === $myId; ?>
        <tr>
          <td><strong><?= e($u['nom']) ?></strong><small class="d-block text-secondary"><?= e($u['email']) ?></small></td>
          <td><?= e($typeLbl[$u['type_exploitation']] ?? '') ?></td>
          <td><span class="chip" data-level="<?= $u['role'] === 'admin' ? 'warn' : 'ok' ?>"><?= e($u['role']) ?></span></td>
          <td><span class="chip" data-level="<?= (int)$u['is_active'] ? 'ok' : 'bad' ?>"><?= (int)$u['is_active'] ? 'Actif' : 'Suspendu' ?></span></td>
          <td class="text-nowrap"><?= e(date('d/m/Y', strtotime($u['created_at']))) ?></td>
          <td class="text-end text-nowrap">
            <?php if ($self): ?><small class="text-secondary">Vous</small><?php else: ?>
              <?= admin_form('toggle_active', (int)$u['id'], 'users', (int)$u['is_active'] ? 'bi-slash-circle' : 'bi-check-circle', (int)$u['is_active'] ? 'Suspendre' : 'Réactiver', (int)$u['is_active'] ? 'Suspendre ce compte ?' : '') ?>
              <?= admin_form('toggle_role', (int)$u['id'], 'users', 'bi-shield-lock', $u['role'] === 'admin' ? 'Retirer admin' : 'Promouvoir admin', 'Changer le rôle de cet utilisateur ?') ?>
              <?= admin_form('delete_user', (int)$u['id'], 'users', 'bi-trash', 'Supprimer', 'Supprimer définitivement cet utilisateur et toutes ses données ?') ?>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <?php if (!$users): ?><div class="empty"><i class="bi bi-people"></i><p>Aucun utilisateur trouvé.</p></div><?php endif; ?>
  </section>

<?php elseif ($tab === 'avis'): ?>
  <section class="card-af">
    <?php if (!$reviews): ?><div class="empty"><i class="bi bi-chat-heart"></i><p>Aucun avis.</p></div><?php endif; ?>
    <?php foreach ($reviews as $r): ?>
      <article class="review">
        <div class="avatar sm"><?= e(mb_strtoupper(mb_substr($r['nom'], 0, 1))) ?></div>
        <div class="flex-grow-1"><div class="d-flex justify-content-between"><strong><?= e($r['nom']) ?> <small class="text-secondary fw-normal"><?= e($r['email']) ?></small></strong><small class="text-secondary"><?= e(date('d/m/Y', strtotime($r['created_at']))) ?></small></div>
          <div class="star-row"><?php for ($i = 1; $i <= 5; $i++): ?><i class="bi <?= $i <= (int)$r['rating'] ? 'bi-star-fill' : 'bi-star' ?>"></i><?php endfor; ?></div>
          <p class="mb-0"><?= e($r['comment']) ?></p></div>
        <?= admin_form('delete_review', (int)$r['id'], 'avis', 'bi-trash', 'Supprimer', 'Supprimer cet avis ?') ?>
      </article>
    <?php endforeach; ?>
  </section>

<?php else: ?>
  <section class="card-af">
    <?php if (!$msgs): ?><div class="empty"><i class="bi bi-chat-dots"></i><p>Aucun message.</p></div><?php endif; ?>
    <?php foreach ($msgs as $m): ?>
      <article class="review">
        <div class="avatar sm"><?= e(mb_strtoupper(mb_substr($m['nom'], 0, 1))) ?></div>
        <div class="flex-grow-1"><div class="d-flex justify-content-between"><strong><?= e($m['nom']) ?> <span class="chip"><?= e($m['room']) ?></span></strong><small class="text-secondary"><?= e(date('d/m H:i', strtotime($m['created_at']))) ?></small></div>
          <p class="mb-0"><?= e($m['content']) ?></p></div>
        <?= admin_form('delete_msg', (int)$m['id'], 'forum', 'bi-trash', 'Supprimer', 'Supprimer ce message ?') ?>
      </article>
    <?php endforeach; ?>
  </section>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
