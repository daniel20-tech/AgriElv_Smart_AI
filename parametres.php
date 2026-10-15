<?php
require __DIR__ . '/includes/bootstrap.php';
$user = require_login();
$uid = (int)$user['id'];
$s = user_settings($uid);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_or_die();
    $nom = trim((string)($_POST['nom'] ?? ''));
    $type = (string)($_POST['type'] ?? '');
    $tmax = (float)($_POST['alert_temp_max'] ?? 32);
    $hmin = (int)($_POST['alert_humidity_min'] ?? 40);
    $theme = ($_POST['theme'] ?? 'light') === 'dark' ? 'dark' : 'light';
    $motion = isset($_POST['alert_motion']) ? 1 : 0;
    $ok = mb_strlen($nom) >= 2 && mb_strlen($nom) <= 80
        && in_array($type, ['agriculture', 'elevage', 'mixte'], true)
        && $tmax >= 20 && $tmax <= 50 && $hmin >= 5 && $hmin <= 90;
    if ($ok) {
        db()->prepare('UPDATE users SET nom = ?, type_exploitation = ? WHERE id = ?')->execute([$nom, $type, $uid]);
        db()->prepare('UPDATE settings SET alert_temp_max = ?, alert_humidity_min = ?, alert_motion = ?, theme = ? WHERE user_id = ?')
            ->execute([$tmax, $hmin, $motion, $theme, $uid]);
        flash('ok', 'Paramètres enregistrés.');
    } else {
        flash('err', 'Valeurs invalides : température entre 20 et 50°C, humidité entre 5 et 90%, nom de 2 à 80 caractères.');
    }
    redirect('parametres.php');
}
$zq = db()->prepare('SELECT id, name, culture FROM zones WHERE user_id = ? ORDER BY id');
$zq->execute([$uid]);
$zonesList = $zq->fetchAll();
$back = 'parametres.php';
$dq = db()->prepare('SELECT d.id, d.name, d.kind, d.last_seen, z.name AS zone FROM devices d LEFT JOIN zones z ON z.id = d.zone_id WHERE d.user_id = ? ORDER BY d.id');
$dq->execute([$uid]);
$devices = $dq->fetchAll();
$cq = db()->prepare('SELECT c.id, c.name, c.type, z.name AS zone FROM cameras c LEFT JOIN zones z ON z.id = c.zone_id WHERE c.user_id = ? ORDER BY c.id');
$cq->execute([$uid]);
$cameras = $cq->fetchAll();
$pageTitle = 'Paramètres'; $active = 'parametres';
$guide = [['sel' => '#alertCard', 't' => 'Réglez ici à partir de quelle température ou humidité vous voulez être alerté.'], ['sel' => '#equipCard', 't' => 'Vos caméras et capteurs sont listés ici. Vous pouvez en ajouter ou en retirer.']];
require __DIR__ . '/includes/header.php';
?>
<h1 class="greet mb-4">Paramètres</h1>
<div class="row g-3">
  <div class="col-12 col-lg-7">
    <form method="post" class="card-af" id="alertCard" novalidate>
      <?= csrf_field() ?>
      <div class="card-head"><span><i class="bi bi-person-fill"></i> Mon profil</span></div>
      <label class="form-label mt-2" for="nom">Nom</label>
      <input class="form-control mb-3" id="nom" name="nom" value="<?= e($user['nom']) ?>" maxlength="80" required>
      <label class="form-label" for="email">Email</label>
      <input class="form-control mb-3" id="email" value="<?= e($user['email']) ?>" disabled>
      <label class="form-label" for="type">Type d'exploitation</label>
      <select class="form-select mb-4" id="type" name="type">
        <?php foreach (['agriculture', 'elevage', 'mixte'] as $t): ?><option value="<?= $t ?>" <?= $user['type_exploitation'] === $t ? 'selected' : '' ?>><?= type_label($t) ?></option><?php endforeach; ?>
      </select>
      <div class="card-head"><span><i class="bi bi-bell-fill"></i> Notifications</span></div>
      <div class="row g-3 mt-0 mb-3">
        <div class="col-6"><label class="form-label" for="tmax">Alerte si température &gt; (°C)</label><input class="form-control" id="tmax" name="alert_temp_max" type="number" step="0.5" min="20" max="50" value="<?= e($s['alert_temp_max']) ?>"></div>
        <div class="col-6"><label class="form-label" for="hmin">Alerte si humidité &lt; (%)</label><input class="form-control" id="hmin" name="alert_humidity_min" type="number" min="5" max="90" value="<?= e($s['alert_humidity_min']) ?>"></div>
      </div>
      <div class="form-check form-switch mb-4">
        <input class="form-check-input" type="checkbox" role="switch" id="motion" name="alert_motion" value="1" <?= $s['alert_motion'] ? 'checked' : '' ?>>
        <label class="form-check-label" for="motion">Alerter en cas de mouvement détecté</label>
      </div>
      <div class="card-head"><span><i class="bi bi-palette-fill"></i> Apparence</span></div>
      <div class="btn-group w-100 my-3" role="group" aria-label="Thème">
        <input type="radio" class="btn-check" name="theme" id="th1" value="light" <?= $s['theme'] === 'light' ? 'checked' : '' ?>><label class="btn btn-outline-af" for="th1"><i class="bi bi-sun-fill"></i> Clair</label>
        <input type="radio" class="btn-check" name="theme" id="th2" value="dark" <?= $s['theme'] === 'dark' ? 'checked' : '' ?>><label class="btn btn-outline-af" for="th2"><i class="bi bi-moon-stars-fill"></i> Sombre</label>
      </div>
      <button class="btn btn-af w-100">Enregistrer les paramètres</button>
    </form>
  </div>
  <div class="col-12 col-lg-5">
    <div class="d-flex flex-column gap-3">
      <section class="card-af" id="equipCard">
        <div class="card-head"><span><i class="bi bi-cpu-fill"></i> Mes équipements</span>
          <a class="btn btn-af btn-sm" href="equipements.php" aria-label="Ajouter un équipement"><i class="bi bi-plus-lg"></i></a></div>
        <?php if (!$devices && !$cameras): ?><div class="empty"><i class="bi bi-plug"></i><p>Aucun équipement. <a href="equipements.php">Ajoutez votre première caméra ou votre premier capteur</a>.</p></div><?php endif; ?>
        <?php foreach ($devices as $d): ?>
          <div class="equip"><span class="ico"><i class="bi bi-cpu"></i></span>
            <div class="flex-grow-1"><strong><?= e($d['name']) ?></strong><small class="d-block text-secondary"><?= e($d['zone'] ?? 'Sans zone') ?> · <?= $d['last_seen'] ? 'vu ' . e(date('d/m H:i', strtotime($d['last_seen']))) : 'jamais connecté' ?></small></div>
            <form method="post" action="equipment.php" data-confirm="Supprimer ce capteur ?"><?= csrf_field() ?><input type="hidden" name="action" value="del_sensor"><input type="hidden" name="id" value="<?= (int)$d['id'] ?>"><input type="hidden" name="back" value="parametres.php"><button class="icon-btn" aria-label="Supprimer"><i class="bi bi-trash"></i></button></form></div>
        <?php endforeach; ?>
        <?php foreach ($cameras as $c): ?>
          <div class="equip"><span class="ico"><i class="bi bi-camera-video"></i></span>
            <div class="flex-grow-1"><strong><?= e($c['name']) ?></strong><small class="d-block text-secondary"><?= e($c['zone'] ?? 'Sans zone') ?> · <?= e($c['type']) ?></small></div>
            <form method="post" action="equipment.php" data-confirm="Supprimer cette caméra ?"><?= csrf_field() ?><input type="hidden" name="action" value="del_camera"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><input type="hidden" name="back" value="parametres.php"><button class="icon-btn" aria-label="Supprimer"><i class="bi bi-trash"></i></button></form></div>
        <?php endforeach; ?>
      </section>
      <section class="card-af">
        <div class="card-head"><span><i class="bi bi-box-arrow-right"></i> Session</span></div>
        <form method="post" action="logout.php" class="mt-3"><?= csrf_field() ?>
          <button class="btn btn-outline-danger w-100">Se déconnecter</button></form>
      </section>
    </div>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
