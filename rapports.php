<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/sensors.php';
$user = require_login();
$uid = (int)$user['id'];
$s = user_settings($uid);
demo_tick($uid, $s);
$stats = week_stats($uid);
$week = weekly_optimal($uid, $s);
$sev = (string)($_GET['sev'] ?? '');
if (!in_array($sev, ['faible', 'moyenne', 'critique'], true)) $sev = '';
$alerts = recent_alerts($uid, 50, $sev);
$bestIdx = null; $best = -1;
foreach ($week as $i => $d) if ($d['pct'] !== null && $d['pct'] >= $best) { $best = $d['pct']; $bestIdx = $i; }
$pageTitle = 'Rapports'; $active = 'rapports';
$extraJs = ['pdf.js', 'dashboard.js'];
$guide = [['sel' => '#pdfBtn', 't' => 'Touchez ici pour télécharger votre rapport de la semaine en PDF.'], ['t' => 'Le filtre vous aide à ne voir que les alertes critiques.']];
require __DIR__ . '/includes/header.php';
$fmt = fn($v, $u) => $v === null ? '--' : $v . $u;
?>
<div class="d-flex justify-content-between align-items-center mb-4 gap-2">
  <h1 class="greet">Rapports <b>7 jours</b></h1>
  <div class="d-flex gap-2"><button class="btn btn-af btn-sm" type="button" id="pdfBtn"><i class="bi bi-file-earmark-pdf"></i> Télécharger le PDF</button><button class="btn btn-outline-af btn-sm" type="button" data-print><i class="bi bi-printer"></i></button></div>
</div>
<div class="row g-3 mb-3">
  <?php foreach ([['bi-thermometer-half', 'Température moyenne', $fmt($stats['temp'], '°C')], ['bi-droplet-half', 'Humidité moyenne', $fmt($stats['hum'], '%')], ['bi-bell-fill', "Nombre d'alertes", (string)$stats['alerts']], ['bi-camera-video-fill', 'Activité caméra', $fmt($stats['camera'], '%')]] as [$ic, $lab, $val]): ?>
    <div class="col-6 col-lg-3"><section class="card-af"><div class="card-head"><span><i class="bi <?= $ic ?>"></i> <?= $lab ?></span></div><div class="mid mt-2"><?= e($val) ?></div></section></div>
  <?php endforeach; ?>
</div>
<div class="row g-3">
  <div class="col-12 col-lg-5">
    <section class="card-af">
      <div class="card-head"><span><i class="bi bi-bar-chart-fill"></i> Taux de conditions optimales</span></div>
      <div class="bars" role="img" aria-label="Conditions optimales par jour">
        <?php foreach ($week as $i => $d): $h = $d['pct'] === null ? 8 : max(8, $d['pct']); ?>
          <div class="bar-col"><div class="bar-wrap"><div class="bar <?= $i === $bestIdx ? 'active' : '' ?>" style="height:<?= $h ?>%"><span class="bar-tip"><?= $d['pct'] === null ? '--' : $d['pct'] . '%' ?></span></div></div><span class="bar-lbl <?= $d['today'] ? 'today' : '' ?>"><?= e($d['label']) ?></span></div>
        <?php endforeach; ?>
      </div>
    </section>
  </div>
  <div class="col-12 col-lg-7">
    <section class="card-af">
      <div class="card-head">
        <span><i class="bi bi-clock-history"></i> Historique des alertes</span>
        <form method="get"><select name="sev" class="form-select form-select-sm rounded-pill" data-autosubmit aria-label="Filtrer par criticité">
          <option value="">Toutes</option>
          <?php foreach (['critique', 'moyenne', 'faible'] as $o): ?><option value="<?= $o ?>" <?= $sev === $o ? 'selected' : '' ?>><?= ucfirst($o) ?></option><?php endforeach; ?>
        </select></form>
      </div>
      <?php if (!$alerts): ?>
        <div class="empty"><i class="bi bi-check2-circle"></i><p>Aucune alerte pour ce filtre.</p></div>
      <?php else: ?>
        <div class="table-responsive"><table class="table table-af align-middle mb-0">
          <thead><tr><th>Alerte</th><th>Date</th><th>Criticité</th></tr></thead>
          <tbody>
          <?php foreach ($alerts as $a): $ts = strtotime($a['created_at']); ?>
            <tr><td><span class="ico"><i class="bi <?= alert_icon($a['type']) ?>"></i></span> <?= e($a['message']) ?></td><td class="text-nowrap"><?= date('d/m H:i', $ts) ?></td><td><span class="chip" data-level="<?= sev_class($a['severity']) ?>"><?= e(ucfirst($a['severity'])) ?></span></td></tr>
          <?php endforeach; ?>
          </tbody>
        </table></div>
      <?php endif; ?>
    </section>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
