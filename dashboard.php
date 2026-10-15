<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/sensors.php';
require __DIR__ . '/includes/robot.php';
$user = require_login();
$uid = (int)$user['id'];
ensure_zones($uid, $user['type_exploitation']);
$s = user_settings($uid);
demo_tick($uid, $s);                       // données simulées si APP_DEMO=true
$r = latest_reading($uid);
$st = $r ? reading_status($r, $s) : null;
$health = $r ? health_score((float)$r['temperature'], (float)$r['soil_humidity'], $s) : null;
$zones = zones_overview($uid, $s, $r);
[$insight, $insightQ] = farm_insight($r, $s, $zones);
$week = weekly_optimal($uid, $s);
$stats = week_stats($uid);
$alerts = recent_alerts($uid, 5);
$camQ = db()->prepare('SELECT id, name, type, url, zone_id FROM cameras WHERE user_id = ? ORDER BY id');
$camQ->execute([$uid]);
$cams = $camQ->fetchAll();
$bestIdx = null; $best = -1;
foreach ($week as $i => $d) if ($d['pct'] !== null && $d['pct'] >= $best) { $best = $d['pct']; $bestIdx = $i; }
$zonesList = $zones; $back = 'dashboard.php';
$pageTitle = 'Tableau de bord'; $active = 'dashboard';
$extraJs = ['pdf.js', 'dashboard.js'];
$guide = [
    ['sel' => '#stage', 't' => "Bonjour, je suis Armel ! Ici, vous voyez votre parcelle en direct. Ajoutez une caméra pour afficher vos vraies images."],
    ['sel' => '#zonesCard', 't' => 'Chaque zone a une couleur : vert = saine, jaune = à surveiller, rouge = action requise. Touchez une zone pour voir ses détails.'],
    ['sel' => '#addBtn', 't' => 'Touchez « Ajouter » : une page dédiée s'ouvre pour brancher une caméra ou un capteur.'],
    ['sel' => '#summaryCard', 't' => "Cet indice résume la santé de votre exploitation en un chiffre."],
    ['sel' => '#controlsCard', 't' => "Activez l'irrigation automatique ou réglez la ventilation ici."],
    ['t' => 'Une question ? Ouvrez « Armel IA » dans le menu, je réponds en français simple.'],
];
require __DIR__ . '/includes/header.php';
$lvlOf = array_column($zones, 'level');
$tagPos = [[29, 56], [55, 38], [78, 28]];
$fmt = fn($v, $u = '') => $v === null ? '--' : $v . $u;
$data = [
    'series' => humidity_series($uid), 'zones' => $zones,
    'cams' => array_map(fn($c) => ['id' => (int)$c['id'], 'name' => $c['name'], 'type' => $c['type'], 'url' => $c['url']], $cams),
    'insightQ' => $insightQ,
];
?>
<div data-dashboard data-model="<?= e(json_encode($data, JSON_UNESCAPED_UNICODE)) ?>">
  <div class="farmbar">
    <div class="farm-name"><span class="farm-ico"><i class="bi bi-flower2"></i></span><div><strong>Ferme de <?= e(explode(' ', $user['nom'])[0]) ?></strong><small><?= e(type_label($user['type_exploitation'])) ?> · <?= count($zones) ?> zones</small></div></div>
    <div class="d-flex flex-wrap gap-2 align-items-center">
      <span class="pill text-danger" id="netState" hidden><i class="bi bi-wifi-off"></i> Hors ligne : dernières données</span>
      <button class="btn btn-outline-af btn-sm" type="button" id="pdfBtn"><i class="bi bi-file-earmark-pdf"></i> Rapport PDF</button>
      <a class="btn btn-af btn-sm" id="addBtn" href="equipements.php"><i class="bi bi-plus-lg"></i> Ajouter caméra / capteur</a>
    </div>
  </div>

  <div class="dash-grid">
    <!-- Zones + conseil IA -->
    <div class="dg-zones">
      <section class="card-af zones-card" id="zonesCard">
        <?php foreach ($zones as $i => $z): ?>
          <button type="button" class="zone-item" data-zone="<?= $i ?>" data-level="<?= e($z['level']) ?>" aria-label="<?= e($z['name']) ?> : <?= e($z['label']) ?>">
            <div class="zi-top"><strong><?= e($z['name']) ?></strong><span class="chip" data-level="<?= e($z['level']) ?>" data-z="score"><?= $z['score'] !== null ? $z['score'] . '%' : '--' ?></span></div>
            <div class="zi-state"><i class="zdot"></i><span data-z="label"><?= e($z['label']) ?></span></div>
            <div class="zi-crop"><?= e($z['culture']) ?> · <span data-z="temp"><?= $fmt($z['temp'], '°C') ?></span> · <span data-z="hum"><?= $fmt($z['hum'], '%') ?></span></div>
          </button>
        <?php endforeach; ?>
      </section>
      <section class="card-af insight-card" id="insightCard">
        <div class="card-head"><span><i class="bi bi-stars"></i> Conseil IA</span></div>
        <p class="insight-q" data-bind="insight">« <?= e($insight) ?> »</p>
        <a class="btn btn-af w-100" href="armel.php?q=<?= e(rawurlencode($insightQ)) ?>">Voir la recommandation <i class="bi bi-arrow-right"></i></a>
      </section>
    </div>

    <!-- Centre : vue en direct -->
    <section class="stage-card" id="stage">
      <div class="stage-top">
        <div class="cam-tabs" id="camTabs" role="tablist" aria-label="Sources vidéo">
          <button type="button" class="cam-tab active" data-cam="sim" role="tab">Simulation</button>
          <?php foreach ($cams as $c): ?><button type="button" class="cam-tab" data-cam="<?= (int)$c['id'] ?>" role="tab"><i class="bi bi-camera-video-fill"></i> <?= e($c['name']) ?></button><?php endforeach; ?>
          <a class="cam-tab cam-add" href="equipements.php#camera" aria-label="Ajouter une caméra"><i class="bi bi-plus-lg"></i></a>
        </div>
        <div class="d-flex gap-2 align-items-center">
          <span class="live-badge" id="liveBadge"><i></i> <span id="liveTxt">SIMULATION</span></span>
          <button class="stage-btn" id="fsBtn" type="button" aria-label="Plein écran"><i class="bi bi-arrows-fullscreen"></i></button>
        </div>
      </div>
      <div class="stage-view" id="stageView">
        <div class="feed" id="feed"><?= field_svg($lvlOf) ?></div>
        <?php foreach ($zones as $i => $z): ?>
          <button type="button" class="zone-tag" data-zone="<?= $i ?>" data-level="<?= e($z['level']) ?>" style="left:<?= $tagPos[$i % 3][0] ?>%;top:<?= $tagPos[$i % 3][1] ?>%"><?= e($z['name']) ?><?= $z['score'] !== null ? ' · ' . $z['score'] . '%' : '' ?></button>
        <?php endforeach; ?>
        <div class="feed-caption" id="feedCaption" hidden></div>
        <div class="feed-msg" id="feedMsg" hidden></div>
        <div class="zone-pop" id="zonePop" hidden>
          <button class="zp-x" type="button" id="zpX" aria-label="Fermer">&times;</button>
          <div class="zp-title"><strong id="zpName"></strong><span class="chip" id="zpChip"></span></div>
          <div class="zp-line" id="zpLine"></div>
          <button class="btn btn-af btn-sm w-100 mb-2" type="button" id="zpIrrig"><i class="bi bi-droplet-fill"></i> Lancer l'irrigation</button>
          <a class="btn btn-light btn-sm w-100" id="zpMore" href="armel.php">Voir les détails</a>
        </div>
      </div>
    </section>

    <!-- Résumé + eau -->
    <div class="dg-side">
      <section class="card-af" id="summaryCard">
        <div class="card-head"><span>Résumé</span><span class="live"><i></i> En direct</span></div>
        <div class="gauge-wrap"><?= gauge_svg($health, 'sum') ?><div class="gauge-num"><span data-count="<?= (int)$health ?>" data-bind="health"><?= $health ?? '--' ?></span>%<small>Indice de santé</small></div></div>
        <ul class="sum-list">
          <li><span><i class="bi bi-wind"></i> Qualité de l'air</span><b><span data-bind="air_quality"><?= $r ? (int)$r['air_quality'] : '--' ?></span>/100</b></li>
          <li><span><i class="bi bi-droplet-half"></i> Humidité du sol</span><b><span data-bind="soil_humidity"><?= $r ? e(number_format((float)$r['soil_humidity'], 1, '.', '')) : '--' ?></span>%</b></li>
          <li><span><i class="bi bi-thermometer-half"></i> Température</span><b><span data-bind="temperature"><?= $r ? e(number_format((float)$r['temperature'], 1, '.', '')) : '--' ?></span>°C</b></li>
        </ul>
        <div class="small text-secondary mt-2">Mis à jour à <span data-bind="updated"><?= $r ? e(date('H:i', strtotime($r['created_at']))) : '--:--' ?></span></div>
      </section>
      <section class="card-af" id="controlsCard">
        <div class="card-head"><span><i class="bi bi-droplet-fill"></i> État de l'eau</span><span class="chip" data-level="<?= $st ? e($st['soil_level']) : 'ok' ?>" data-bind-level="soil_level"><span data-bind="soil_status"><?= $st ? e($st['soil_status']) : '--' ?></span></span></div>
        <div class="water-val"><span data-bind="hum_avg"><?= $fmt($stats['hum']) ?></span><small>% moy. 7 j</small></div>
        <div class="progress af-progress" role="progressbar" aria-label="Humidité moyenne du sol"><div class="progress-bar" style="width:<?= (int)($stats['hum'] ?? 0) ?>%"></div></div>
        <div class="ctrl">
          <div class="ctrl-row"><label for="irrigSw"><i class="bi bi-droplet"></i> Irrigation auto</label>
            <div class="form-check form-switch m-0"><input class="form-check-input" type="checkbox" role="switch" id="irrigSw" <?= $s['auto_irrigation'] ? 'checked' : '' ?>></div></div>
          <div class="ctrl-row col"><label for="ventRange"><i class="bi bi-fan"></i> Ventilation <b id="ventVal"><?= (int)$s['ventilation'] ?>%</b></label>
            <input class="form-range" type="range" min="0" max="100" step="5" id="ventRange" value="<?= (int)$s['ventilation'] ?>"></div>
        </div>
      </section>
    </div>
  </div>

  <div class="row g-3 mt-0">
    <div class="col-12 col-md-6 col-xl-4"><section class="card-af">
      <div class="card-head"><span><i class="bi bi-activity"></i> Lectures (7 jours)</span></div>
      <div class="mid mt-2"><span data-count="<?= readings_week_count($uid) ?>"><?= readings_week_count($uid) ?></span><small> mesures</small></div>
      <div class="mini-bars" aria-hidden="true"><?php foreach ($week as $d): ?><i style="height:<?= $d['pct'] === null ? 10 : max(10, $d['pct']) ?>%"></i><?php endforeach; ?></div>
    </section></div>
    <div class="col-12 col-md-6 col-xl-4"><section class="card-af">
      <div class="card-head"><span><i class="bi bi-heart-pulse-fill"></i> Santé globale</span></div>
      <div class="gauge-wrap sm"><?= gauge_svg($health, 'mini') ?><div class="gauge-num"><span><?= $health ?? '--' ?></span>%</div></div>
    </section></div>
    <div class="col-12 col-xl-4"><section class="card-af">
      <div class="card-head"><span><i class="bi bi-bar-chart-fill"></i> Conditions optimales</span><span class="chip">7 j</span></div>
      <div class="bars sm" role="img" aria-label="Conditions optimales par jour">
        <?php foreach ($week as $i => $d): $h = $d['pct'] === null ? 8 : max(8, $d['pct']); ?>
          <div class="bar-col"><div class="bar-wrap"><div class="bar <?= $i === $bestIdx ? 'active' : '' ?>" style="height:<?= $h ?>%"><span class="bar-tip"><?= $d['pct'] === null ? '--' : $d['pct'] . '%' ?></span></div></div><span class="bar-lbl <?= $d['today'] ? 'today' : '' ?>"><?= e($d['label']) ?></span></div>
        <?php endforeach; ?>
      </div>
    </section></div>
    <div class="col-12">
      <section class="card-af">
        <div class="card-head"><span><i class="bi bi-clock-history"></i> Alertes récentes</span><a class="icon-btn" href="rapports.php" aria-label="Voir tous les rapports"><i class="bi bi-arrow-up-right"></i></a></div>
        <?php if (!$alerts): ?>
          <div class="empty"><i class="bi bi-check2-circle"></i><p>Aucune alerte. Vos conditions sont bonnes.</p></div>
        <?php else: ?>
          <div class="table-responsive"><table class="table table-af align-middle mb-0">
            <thead><tr><th>Alerte</th><th>Date</th><th>Heure</th><th>Criticité</th></tr></thead>
            <tbody>
            <?php foreach ($alerts as $a): $ts = strtotime($a['created_at']); ?>
              <tr><td><span class="ico"><i class="bi <?= alert_icon($a['type']) ?>"></i></span> <?= e($a['message']) ?></td>
                <td><?= date('d/m/Y', $ts) ?></td><td><?= date('H:i', $ts) ?></td>
                <td><span class="chip" data-level="<?= sev_class($a['severity']) ?>"><?= e(ucfirst($a['severity'])) ?></span></td></tr>
            <?php endforeach; ?>
            </tbody>
          </table></div>
        <?php endif; ?>
      </section>
    </div>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
