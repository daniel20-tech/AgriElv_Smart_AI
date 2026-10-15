// Dashboard : vue en direct (simulation / caméras / caméra de l'appareil), zones, temps réel, contrôles, rapport PDF
(() => {
  'use strict';
  if (!window.AF) return;
  const { $, $$ } = AF;

  // ---------- Bouton « Rapport PDF » (dashboard + page rapports) ----------
  const pdfBtn = $('#pdfBtn');
  pdfBtn?.addEventListener('click', async () => {
    const old = pdfBtn.innerHTML;
    pdfBtn.disabled = true;
    pdfBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Génération...';
    try {
      const r = await fetch('api/report.php', { credentials: 'same-origin' });
      if (!r.ok) throw new Error('http');
      AFPDF.report(await r.json());
      AF.toast('Rapport PDF téléchargé');
    } catch (e) { AF.toast('Impossible de générer le PDF. Réessayez.', 'err'); }
    pdfBtn.disabled = false;
    pdfBtn.innerHTML = old;
  });

  const dash = $('[data-dashboard]');
  if (!dash) return;
  let model = { series: [], zones: [], cams: [] };
  try { model = JSON.parse(dash.dataset.model); } catch (e) {}

  const feed = $('#feed'), msg = $('#feedMsg'), cap = $('#feedCaption'), pop = $('#zonePop');
  const tags = $$('.zone-tag'), items = $$('.zone-item');
  const simNode = feed.firstElementChild;       // vue simulée gardée en mémoire
  let stream = null, timer = null, curZone = -1;

  // ---------- Flux en direct ----------
  const stopFeed = () => {
    if (timer) { clearInterval(timer); timer = null; }
    if (stream) { stream.getTracks().forEach(t => t.stop()); stream = null; }
  };
  const showMsg = t => { msg.textContent = t || ''; msg.hidden = !t; };
  const setLive = (txt, sim) => { $('#liveTxt').textContent = txt; $('#liveBadge').classList.toggle('sim', !!sim); };

  async function selectCam(id) {
    stopFeed(); showMsg(''); closePop();
    $$('.cam-tab[data-cam]').forEach(b => b.classList.toggle('active', b.dataset.cam === String(id)));
    if (id === 'sim') {
      feed.replaceChildren(simNode);
      tags.forEach(t => { t.hidden = false; });
      cap.hidden = true; setLive('SIMULATION', true);
      return;
    }
    const cam = model.cams.find(c => String(c.id) === String(id));
    if (!cam) return;
    tags.forEach(t => { t.hidden = true; });
    cap.textContent = cam.name; cap.hidden = false; setLive('EN DIRECT', false);
    feed.replaceChildren();
    if (cam.type === 'mjpeg' || cam.type === 'snapshot') {
      if (!/^https?:\/\//i.test(cam.url || '')) return showMsg('Adresse de flux invalide.');
      const img = new Image();
      img.alt = 'Flux de ' + cam.name; img.referrerPolicy = 'no-referrer';
      img.onload = () => showMsg('');
      img.onerror = () => showMsg("Flux indisponible. Vérifiez l'adresse (HTTPS requis sur ce site) et que la caméra est allumée.");
      const load = () => { img.src = cam.type === 'snapshot' ? cam.url + (cam.url.includes('?') ? '&' : '?') + '_t=' + Date.now() : cam.url; };
      load();
      if (cam.type === 'snapshot') timer = setInterval(load, 3000);
      feed.appendChild(img);
    } else if (cam.type === 'video') {
      if (!/^https?:\/\//i.test(cam.url || '')) return showMsg('Adresse vidéo invalide.');
      const v = document.createElement('video');
      v.src = cam.url; v.autoplay = true; v.muted = true; v.loop = true; v.playsInline = true; v.controls = true;
      v.onerror = () => showMsg('Vidéo indisponible. Utilisez un lien direct .mp4 ou .webm en HTTPS.');
      feed.appendChild(v);
    } else {
      try {
        stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' }, audio: false });
        const v = document.createElement('video');
        v.srcObject = stream; v.autoplay = true; v.muted = true; v.playsInline = true;
        feed.appendChild(v);
      } catch (e) { showMsg("Accès à la caméra refusé ou indisponible (HTTPS requis)."); }
    }
  }
  $$('.cam-tab[data-cam]').forEach(b => b.addEventListener('click', () => selectCam(b.dataset.cam)));
  $('#fsBtn')?.addEventListener('click', () => {
    const st = $('#stage');
    if (document.fullscreenElement) document.exitFullscreen();
    else st.requestFullscreen?.().catch(() => AF.toast('Plein écran non disponible sur cet appareil.', 'err'));
  });

  // ---------- Zones ----------
  function closePop() { pop.hidden = true; curZone = -1; tags.forEach(t => t.classList.remove('active')); items.forEach(t => t.classList.remove('active')); }
  function openPop(i) {
    const z = model.zones[i];
    if (!z) return;
    curZone = i;
    tags.forEach(t => t.classList.toggle('active', +t.dataset.zone === i));
    items.forEach(t => t.classList.toggle('active', +t.dataset.zone === i));
    $('#zpName').textContent = z.name + ' · ' + z.culture;
    const chip = $('#zpChip');
    chip.dataset.level = z.level; chip.textContent = z.score !== null ? z.score + '%' : '--';
    $('#zpLine').textContent = z.score === null
      ? 'Zone non équipée : ajoutez un capteur pour la suivre.'
      : `${z.label} · ${z.temp}°C · humidité du sol ${z.hum}%` + (z.sim ? ' (valeurs simulées)' : '');
    $('#zpMore').href = 'armel.php?q=' + encodeURIComponent(`Que faire pour ${z.name} (${z.culture}) : ${z.label}, ${z.temp}°C et ${z.hum}% d'humidité du sol ?`);
    pop.hidden = false; pop.classList.remove('pop'); void pop.offsetWidth; pop.classList.add('pop');
  }
  tags.forEach(t => t.addEventListener('click', () => openPop(+t.dataset.zone)));
  items.forEach(t => t.addEventListener('click', () => { openPop(+t.dataset.zone); $('#stage').scrollIntoView({ behavior: AF.reduce ? 'auto' : 'smooth', block: 'nearest' }); }));
  $('#zpX').addEventListener('click', closePop);

  // ---------- Contrôles manuels ----------
  const sw = $('#irrigSw'), rng = $('#ventRange');
  sw?.addEventListener('change', async () => {
    const r = await AF.post('api/control.php', { auto_irrigation: sw.checked ? 1 : 0 });
    if (!r.ok) { sw.checked = !sw.checked; AF.toast('Action impossible. Réessayez.', 'err'); }
    else AF.toast(sw.checked ? 'Irrigation automatique activée' : 'Irrigation automatique désactivée');
  });
  let vt = null;
  rng?.addEventListener('input', () => { $('#ventVal').textContent = rng.value + '%'; clearTimeout(vt); vt = setTimeout(() => AF.post('api/control.php', { ventilation: +rng.value }), 500); });
  $('#zpIrrig')?.addEventListener('click', async () => {
    const r = await AF.post('api/control.php', { auto_irrigation: 1 });
    if (r.ok) { sw.checked = true; AF.toast('Irrigation automatique activée'); } else AF.toast('Action impossible. Réessayez.', 'err');
  });

  // ---------- Courbe, jauge, temps réel ----------
  const set = (name, val) => $$(`[data-bind="${name}"]`).forEach(el => { el.textContent = val; });
  const setLevel = (name, lvl) => $$(`[data-bind-level="${name}"]`).forEach(el => { el.dataset.level = lvl; });
  const setGauge = score => $$('.g-val').forEach(g => g.style.setProperty('--off', (251.33 * (1 - score / 100)).toFixed(2)));

  function renderZones(zs) {
    model.zones = zs;
    const plots = $$('.plot', simNode);
    zs.forEach((z, i) => {
      const it = items[i], tg = tags[i];
      if (it) {
        it.dataset.level = z.level;
        const put = (k, v) => { const el = $(`[data-z="${k}"]`, it); if (el) el.textContent = v; };
        put('score', z.score !== null ? z.score + '%' : '--'); put('label', z.label);
        put('temp', z.temp !== null ? z.temp + '°C' : '--'); put('hum', z.hum !== null ? z.hum + '%' : '--');
        const ch = $('[data-z="score"]', it); if (ch) ch.dataset.level = z.level;
      }
      if (tg) { tg.dataset.level = z.level; tg.textContent = z.name + (z.score !== null ? ' · ' + z.score + '%' : ''); }
      if (plots[i]) plots[i].setAttribute('class', 'plot lvl-' + z.level);
    });
    if (curZone >= 0 && !pop.hidden) openPop(curZone);
  }

  function apply(d) {
    const r = d.latest;
    if (!r) return;
    set('temperature', r.temperature); set('soil_humidity', r.soil_humidity);
    set('air_quality', r.air_quality); set('updated', r.time);
    set('soil_status', r.soil_status); set('health', d.health);
    if (d.hum_avg !== null && d.hum_avg !== undefined) set('hum_avg', d.hum_avg);
    setLevel('soil_level', r.soil_level);
    setGauge(d.health);
    if (d.zones) renderZones(d.zones);
  }

  async function refresh() {
    try {
      const res = await fetch('api/sensors.php', { credentials: 'same-origin', headers: { Accept: 'application/json' } });
      if (res.status === 401) { location.href = 'login.php'; return; }
      if (!res.ok) throw new Error('http');
      const d = await res.json();
      apply(d);
      $('#netState').hidden = true;
      try { localStorage.setItem('agrielv_last', JSON.stringify(d)); } catch (e) {}
    } catch (e) {
      // Hors ligne : on affiche les dernières données connues
      $('#netState').hidden = false;
      try { const c = JSON.parse(localStorage.getItem('agrielv_last') || 'null'); if (c) apply(c); } catch (e2) {}
    }
  }
  setInterval(() => { if (!document.hidden) refresh(); }, 15000);
  document.addEventListener('visibilitychange', () => { if (!document.hidden) refresh(); });
  addEventListener('beforeunload', stopFeed);
})();
