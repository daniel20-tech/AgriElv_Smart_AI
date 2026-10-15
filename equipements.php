<?php
// Page dédiée : ajouter / gérer les caméras et les capteurs (aucune fenêtre modale)
require __DIR__ . '/includes/bootstrap.php';
$user = require_login();
$uid = (int)$user['id'];
ensure_zones($uid, $user['type_exploitation']);

$zq = db()->prepare('SELECT id, name, culture FROM zones WHERE user_id = ? ORDER BY id');
$zq->execute([$uid]);
$zones = $zq->fetchAll();
$zoneOpts = '<option value="">Aucune zone</option>';
foreach ($zones as $z) $zoneOpts .= '<option value="' . (int)$z['id'] . '">' . e($z['name'] . ' - ' . $z['culture']) . '</option>';

$cq = db()->prepare('SELECT c.id, c.name, c.type, z.name AS zone FROM cameras c LEFT JOIN zones z ON z.id = c.zone_id WHERE c.user_id = ? ORDER BY c.id');
$cq->execute([$uid]);
$cameras = $cq->fetchAll();
$dq = db()->prepare('SELECT d.id, d.name, d.kind, d.last_seen, z.name AS zone FROM devices d LEFT JOIN zones z ON z.id = d.zone_id WHERE d.user_id = ? ORDER BY d.id');
$dq->execute([$uid]);
$devices = $dq->fetchAll();

$newKey = $_SESSION['newkey'] ?? null;      // clé affichée une seule fois
unset($_SESSION['newkey']);
$base = rtrim((string)env('APP_URL', ''), '/');
$typeLbl = ['mjpeg' => 'Flux MJPEG', 'snapshot' => 'Image rafraîchie', 'video' => 'Vidéo', 'device' => "Caméra de l'appareil"];
$kindLbl = ['mixte' => 'Multi-mesures', 'temperature' => 'Température', 'humidite' => 'Humidité du sol', 'air' => "Qualité de l'air"];

$pageTitle = 'Équipements'; $active = 'equipements';
$guide = [
    ['sel' => '#camera', 't' => "Ici, ajoutez une caméra : collez l'adresse de son flux, ou choisissez la caméra de ce téléphone."],
    ['sel' => '#capteur', 't' => 'Ici, créez un capteur. Vous recevrez une clé secrète à copier dans le capteur.'],
    ['t' => 'Ensuite, retournez sur le tableau de bord : vos caméras apparaissent en haut de la vue en direct.'],
];
require __DIR__ . '/includes/header.php';
?>
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
  <h1 class="greet">Mes <b>équipements</b></h1>
  <a class="btn btn-outline-af btn-sm" href="dashboard.php"><i class="bi bi-arrow-left"></i> Retour au tableau de bord</a>
</div>

<?php if ($newKey): ?>
<section class="card-af key-card mb-3" id="newKey">
  <div class="card-head"><span><i class="bi bi-key-fill"></i> Clé du capteur « <?= e($newKey['name']) ?> »</span></div>
  <div class="alert alert-warning rounded-4 small mt-2 mb-2">Copiez cette clé maintenant : elle ne sera plus affichée.</div>
  <div class="key-row"><code id="keyVal" class="user-select-all"><?= e($newKey['key']) ?></code><button type="button" class="btn btn-af btn-sm" data-copy="#keyVal"><i class="bi bi-clipboard"></i> Copier</button></div>
  <pre id="keyCurl" class="code-box small mt-3 mb-2">curl -X POST <?= e($base) ?>/api/sensors.php \
 -H "X-Device-Key: <?= e($newKey['key']) ?>" \
 -H "Content-Type: application/json" \
 -d '{"temperature":28.4,"soil_humidity":55,"air_quality":78,"camera_online":1,"motion":0}'</pre>
  <button type="button" class="btn btn-outline-af btn-sm" data-copy="#keyCurl"><i class="bi bi-clipboard"></i> Copier la commande</button>
</section>
<?php endif; ?>

<div class="row g-3 mb-3">
  <div class="col-12 col-lg-6">
    <form method="post" action="equipment.php" class="card-af eq-card" id="camera" novalidate>
      <?= csrf_field() ?><input type="hidden" name="action" value="add_camera"><input type="hidden" name="back" value="equipements.php">
      <div class="card-head"><span><i class="bi bi-camera-video-fill"></i> Ajouter une caméra</span></div>
      <label class="form-label mt-3" for="cname">Nom</label>
      <input class="form-control mb-3" id="cname" name="name" maxlength="60" placeholder="Ex. Caméra poulailler" required>
      <label class="form-label" for="ctype">Type de flux</label>
      <select class="form-select mb-2" id="ctype" name="type">
        <option value="mjpeg">Flux MJPEG (URL en direct)</option>
        <option value="snapshot">Image fixe rafraîchie (snapshot URL)</option>
        <option value="video">Vidéo (lien .mp4 / .webm)</option>
        <option value="device">Caméra de cet appareil (téléphone/PC)</option>
      </select>
      <div id="curlWrap"><label class="form-label mt-2" for="curl">Adresse du flux (https://...)</label>
        <input class="form-control" id="curl" name="url" type="url" maxlength="500" placeholder="https://exemple.com/stream.mjpg"></div>
      <p class="small text-secondary mt-2 mb-3">Sur un site en HTTPS, l'adresse du flux doit aussi être en HTTPS. Une caméra RTSP doit passer par un convertisseur MJPEG.</p>
      <label class="form-label" for="czone">Zone</label>
      <select class="form-select mb-4" id="czone" name="zone_id"><?= $zoneOpts ?></select>
      <button class="btn btn-af w-100">Ajouter la caméra</button>
    </form>
  </div>
  <div class="col-12 col-lg-6">
    <form method="post" action="equipment.php" class="card-af eq-card" id="capteur" novalidate>
      <?= csrf_field() ?><input type="hidden" name="action" value="add_sensor"><input type="hidden" name="back" value="equipements.php">
      <div class="card-head"><span><i class="bi bi-cpu-fill"></i> Ajouter un capteur</span></div>
      <label class="form-label mt-3" for="sname">Nom</label>
      <input class="form-control mb-3" id="sname" name="name" maxlength="80" placeholder="Ex. Capteur parcelle cacao" required>
      <label class="form-label" for="skind">Type</label>
      <select class="form-select mb-3" id="skind" name="kind">
        <option value="mixte">Multi-mesures (température + humidité + air)</option>
        <option value="temperature">Température</option><option value="humidite">Humidité du sol</option><option value="air">Qualité de l'air</option>
      </select>
      <label class="form-label" for="szone">Zone</label>
      <select class="form-select mb-3" id="szone" name="zone_id"><?= $zoneOpts ?></select>
      <p class="small text-secondary mb-4">Une clé secrète sera générée et affichée une seule fois, en haut de cette page.</p>
      <button class="btn btn-af w-100">Créer le capteur</button>
    </form>
  </div>
</div>

<div class="row g-3">
  <div class="col-12 col-lg-6"><section class="card-af">
    <div class="card-head"><span><i class="bi bi-camera-video"></i> Mes caméras (<?= count($cameras) ?>/6)</span></div>
    <?php if (!$cameras): ?><div class="empty"><i class="bi bi-camera-video-off"></i><p>Aucune caméra pour l'instant.</p></div><?php endif; ?>
    <?php foreach ($cameras as $c): ?>
      <div class="equip"><span class="ico"><i class="bi bi-camera-video"></i></span>
        <div class="flex-grow-1"><strong><?= e($c['name']) ?></strong><small class="d-block text-secondary"><?= e($c['zone'] ?? 'Sans zone') ?> · <?= e($typeLbl[$c['type']] ?? $c['type']) ?></small></div>
        <form method="post" action="equipment.php" data-confirm="Supprimer cette caméra ?"><?= csrf_field() ?><input type="hidden" name="action" value="del_camera"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><input type="hidden" name="back" value="equipements.php"><button class="icon-btn" aria-label="Supprimer la caméra <?= e($c['name']) ?>"><i class="bi bi-trash"></i></button></form></div>
    <?php endforeach; ?>
  </section></div>
  <div class="col-12 col-lg-6"><section class="card-af">
    <div class="card-head"><span><i class="bi bi-cpu"></i> Mes capteurs (<?= count($devices) ?>/10)</span></div>
    <?php if (!$devices): ?><div class="empty"><i class="bi bi-plug"></i><p>Aucun capteur pour l'instant.</p></div><?php endif; ?>
    <?php foreach ($devices as $d): ?>
      <div class="equip"><span class="ico"><i class="bi bi-cpu"></i></span>
        <div class="flex-grow-1"><strong><?= e($d['name']) ?></strong><small class="d-block text-secondary"><?= e($kindLbl[$d['kind']] ?? '') ?> · <?= e($d['zone'] ?? 'Sans zone') ?> · <?= $d['last_seen'] ? 'vu ' . e(date('d/m H:i', strtotime($d['last_seen']))) : 'jamais connecté' ?></small></div>
        <form method="post" action="equipment.php" data-confirm="Supprimer ce capteur ?"><?= csrf_field() ?><input type="hidden" name="action" value="del_sensor"><input type="hidden" name="id" value="<?= (int)$d['id'] ?>"><input type="hidden" name="back" value="equipements.php"><button class="icon-btn" aria-label="Supprimer le capteur <?= e($d['name']) ?>"><i class="bi bi-trash"></i></button></form></div>
    <?php endforeach; ?>
  </section></div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
