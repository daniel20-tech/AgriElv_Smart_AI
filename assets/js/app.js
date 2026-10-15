// AgriElv Smart AI - JS commun (aucune dépendance) :
// chargement, animations (clic, scroll, compteurs), robot Armel, mascotte guide, thème, chat Armel
(() => {
  'use strict';
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => [...r.querySelectorAll(s)];
  const AF = window.AF = {
    $, $$,
    csrf: $('meta[name=csrf]')?.content || '',
    reduce: matchMedia('(prefers-reduced-motion: reduce)').matches,
  };

  // Requête POST JSON (avec jeton CSRF)
  AF.post = async (url, body) => {
    const r = await fetch(url, {
      method: 'POST', credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': AF.csrf },
      body: JSON.stringify(body),
    });
    let data = {};
    try { data = await r.json(); } catch (e) { /* réponse vide */ }
    return { ok: r.ok, status: r.status, data };
  };

  // Petit message animé en bas d'écran
  AF.toast = (msg, type) => {
    const t = document.createElement('div');
    t.className = 'af-toast' + (type === 'err' ? ' err' : '');
    t.setAttribute('role', 'status');
    t.textContent = msg;               // textContent : pas d'injection HTML
    document.body.appendChild(t);
    requestAnimationFrame(() => t.classList.add('show'));
    setTimeout(() => { t.classList.remove('show'); setTimeout(() => t.remove(), 400); }, 3200);
  };

  // ---------- Écran de chargement « Bienvenue sur AgriElv » (1 fois par session) ----------
  const splash = $('#splash');
  if (splash) {
    let seen = false;
    try { seen = sessionStorage.getItem('af_splash') === '1'; } catch (e) {}
    if (seen) splash.remove();
    else {
      try { sessionStorage.setItem('af_splash', '1'); } catch (e) {}
      const t0 = Date.now();
      const hide = () => { splash.classList.add('hide'); setTimeout(() => splash.remove(), 700); };
      const done = () => setTimeout(hide, Math.max(0, 1500 - (Date.now() - t0)));
      document.readyState === 'complete' ? done() : addEventListener('load', done);
    }
  }

  // ---------- Barre de progression + transition entre pages ----------
  const bar = $('#navProgress');
  const startNav = () => {
    document.body.classList.add('leaving');
    if (bar) { bar.classList.add('on'); bar.style.width = '70%'; }
  };
  document.addEventListener('click', e => {
    const a = e.target.closest('a[href]');
    if (!a || e.defaultPrevented || e.metaKey || e.ctrlKey || e.shiftKey || a.target === '_blank' || a.hasAttribute('download')) return;
    const u = new URL(a.href, location.href);
    if (u.origin !== location.origin || (u.pathname === location.pathname && u.search === location.search)) return;
    startNav();
  });
  document.addEventListener('submit', e => { if (!e.defaultPrevented) startNav(); });
  addEventListener('pageshow', () => {
    document.body.classList.remove('leaving');
    if (bar) { bar.style.width = '0'; bar.classList.remove('on'); }
  });

  // ---------- Ondulation au clic ----------
  const RIPPLE = '.btn,.af-btn,.af-google,.tile,.chip-btn,.rail a,.tabbar a,.zone-item,.cam-tab,.wa-room,.icon-btn,.wa-send,.stage-btn';
  document.addEventListener('pointerdown', e => {
    if (AF.reduce) return;
    const el = e.target.closest(RIPPLE);
    if (!el || el.disabled) return;
    el.classList.add('rip');
    const r = el.getBoundingClientRect();
    const d = Math.max(r.width, r.height);
    const s = document.createElement('span');
    s.className = 'ripple';
    s.style.cssText = `width:${d}px;height:${d}px;left:${e.clientX - r.left - d / 2}px;top:${e.clientY - r.top - d / 2}px`;
    el.appendChild(s);
    setTimeout(() => s.remove(), 700);
  });

  // ---------- Apparition au scroll ----------
  if (!AF.reduce && 'IntersectionObserver' in window) {
    const io = new IntersectionObserver(entries => {
      let n = 0;
      entries.forEach(en => {
        if (!en.isIntersecting) return;
        en.target.style.setProperty('--d', (n++ * 0.07).toFixed(2) + 's');
        en.target.classList.add('reveal-in');
        io.unobserve(en.target);
      });
    }, { threshold: 0.06 });
    $$('.card-af,.tile,.farmbar,.stage-card,.review,.wa,.admin-tabs').forEach(el => { el.classList.add('reveal-init'); io.observe(el); });
  }

  // ---------- Compteurs animés ----------
  $$('[data-count]').forEach(el => {
    const to = parseInt(el.dataset.count, 10);
    if (isNaN(to) || AF.reduce || to === 0) return;
    const t0 = performance.now(), dur = 1100;
    el.textContent = '0';
    const step = t => {
      const p = Math.min(1, (t - t0) / dur);
      el.textContent = Math.round(to * (1 - Math.pow(1 - p, 3)));
      if (p < 1) requestAnimationFrame(step);
    };
    requestAnimationFrame(step);
  });

  // ---------- Barre du haut : ombre au scroll ----------
  const top = $('#topbar');
  if (top) addEventListener('scroll', () => top.classList.toggle('scrolled', scrollY > 8), { passive: true });

  // ---------- Thème clair / sombre ----------
  $('#themeBtn')?.addEventListener('click', async () => {
    const next = document.documentElement.dataset.bsTheme === 'dark' ? 'light' : 'dark';
    document.documentElement.dataset.bsTheme = next;
    try { await AF.post('api/theme.php', { theme: next }); } catch (e) { /* hors ligne : thème gardé localement */ }
  });

  // ---------- Gestionnaires génériques (sans attribut inline : CSP) ----------
  $$('[data-print]').forEach(b => b.addEventListener('click', () => window.print()));
  $$('select[data-autosubmit]').forEach(s => s.addEventListener('change', () => s.form.submit()));
  $$('form[data-confirm]').forEach(f => f.addEventListener('submit', e => { if (!confirm(f.dataset.confirm)) { e.preventDefault(); e.stopImmediatePropagation(); } }));
  // Bouton « Copier » (clé capteur, commande curl)
  $$('[data-copy]').forEach(b => b.addEventListener('click', async () => {
    const el = $(b.dataset.copy);
    if (!el) return;
    try { await navigator.clipboard.writeText(el.textContent.trim()); AF.toast('Copié dans le presse-papiers'); }
    catch (e) { const r = document.createRange(); r.selectNodeContents(el); const s = getSelection(); s.removeAllRanges(); s.addRange(r); AF.toast('Sélectionné : faites Copier', 'err'); }
  }));
  const ctype = $('#ctype');
  if (ctype) {
    const sync = () => { const dev = ctype.value === 'device'; $('#curlWrap').hidden = dev; $('#curl').required = !dev; };
    ctype.addEventListener('change', sync); sync();
  }

  // ---------- Mot de passe : afficher / masquer ----------
  $$('.af-eye').forEach(b => b.addEventListener('click', () => {
    const inp = b.parentElement.querySelector('input');
    const show = inp.type === 'password';
    inp.type = show ? 'text' : 'password';
    b.innerHTML = `<i class="bi ${show ? 'bi-eye-slash' : 'bi-eye'}"></i>`;
  }));

  // ---------- Robot Armel : yeux qui suivent le curseur, tilt 3D, saut au clic ----------
  const robots = $$('.robot');
  if (robots.length && !AF.reduce) {
    let raf = 0;
    addEventListener('pointermove', e => {
      if (raf) return;
      raf = requestAnimationFrame(() => {
        raf = 0;
        robots.forEach(r => {
          const b = r.getBoundingClientRect();
          if (!b.width) return;
          const dx = (e.clientX - (b.left + b.width / 2)) / Math.max(innerWidth, 1);
          const dy = (e.clientY - (b.top + b.height * 0.4)) / Math.max(innerHeight, 1);
          r.style.setProperty('--px', (Math.max(-1, Math.min(1, dx * 3)) * 5).toFixed(2));
          r.style.setProperty('--py', (Math.max(-1, Math.min(1, dy * 3)) * 4).toFixed(2));
          if (r.classList.contains('robot-xl') || r.classList.contains('robot-xs')) {
            r.style.setProperty('--ry', (dx * 22).toFixed(1) + 'deg');
            r.style.setProperty('--rx', (-dy * 14).toFixed(1) + 'deg');
          }
        });
      });
    }, { passive: true });
  }
  robots.forEach(r => r.addEventListener('click', () => { r.classList.remove('jump'); void r.getBoundingClientRect(); r.classList.add('jump'); setTimeout(() => r.classList.remove('jump'), 750); }));
  // Page de connexion : le robot se cache les yeux sur le champ mot de passe, tremble en cas d'erreur
  const main = $('.robot-xl');
  if (main) {
    const pw = $('#password');
    pw?.addEventListener('focus', () => { main.dataset.state = 'shy'; });
    pw?.addEventListener('blur', () => { main.dataset.state = 'idle'; });
    if ($('#authError')) { main.dataset.state = 'error'; setTimeout(() => { main.dataset.state = 'idle'; }, 700); }
  }

  // ---------- Mascotte guide 3D (visite guidée par page) ----------
  const guide = $('#guide');
  if (guide) {
    let steps = [];
    try { steps = JSON.parse(guide.dataset.steps); } catch (e) {}
    if (steps.length) {
      const bubble = $('#guideBubble'), txt = $('#guideText'), dots = $('#gDots');
      const key = 'af_guide_' + guide.dataset.page;
      let i = 0, hl = null;
      const clear = () => { hl?.classList.remove('tour-hl'); hl = null; };
      const show = n => {
        i = Math.max(0, Math.min(steps.length - 1, n));
        const s = steps[i];
        txt.textContent = s.t;
        dots.innerHTML = steps.map((_, k) => `<i class="${k === i ? 'on' : ''}"></i>`).join('');
        $('#gPrev').style.visibility = i ? 'visible' : 'hidden';
        $('#gNext').innerHTML = i === steps.length - 1 ? 'Compris <i class="bi bi-check2"></i>' : 'Suivant <i class="bi bi-chevron-right"></i>';
        clear();
        if (s.sel) {
          const t = $(s.sel);
          if (t) { hl = t; t.classList.add('tour-hl'); t.scrollIntoView({ behavior: AF.reduce ? 'auto' : 'smooth', block: 'center' }); }
        }
        bubble.hidden = false;
        bubble.classList.remove('pop'); void bubble.offsetWidth; bubble.classList.add('pop');
      };
      const close = () => { clear(); bubble.hidden = true; try { localStorage.setItem(key, '1'); } catch (e) {} };
      guide.hidden = false;
      bubble.hidden = true;
      $('#gNext').addEventListener('click', () => (i >= steps.length - 1 ? close() : show(i + 1)));
      $('#gPrev').addEventListener('click', () => show(i - 1));
      $('#guideX').addEventListener('click', close);
      $('#guideBot').addEventListener('click', () => (bubble.hidden ? show(0) : close()));
      let seen = false;
      try { seen = localStorage.getItem(key) === '1'; } catch (e) {}
      if (!seen) setTimeout(() => show(0), 2600);
    }
  }

  // ---------- Chat Armel ----------
  const form = $('#chatForm');
  if (form) {
    const log = $('#chatLog'), input = $('#chatInput');
    const scroll = () => { log.scrollTop = log.scrollHeight; };
    const bubble = (text, cls) => {
      const b = document.createElement('div');
      b.className = 'msg ' + cls;
      b.textContent = text;
      log.appendChild(b); scroll();
      return b;
    };
    scroll();
    async function send(text) {
      text = text.trim();
      if (!text) return;
      bubble(text, 'me');
      const wait = bubble('Armel écrit...', 'bot typing');
      input.value = ''; input.disabled = true;
      try {
        const { data } = await AF.post('api/armel.php', { message: text });
        wait.classList.remove('typing');
        wait.textContent = data.reply || data.error || 'Une erreur est survenue. Réessayez.';
      } catch (e) {
        wait.classList.remove('typing');
        wait.textContent = 'Pas de connexion. Vérifiez votre réseau puis renvoyez votre question.';
      }
      input.disabled = false; input.focus(); scroll();
    }
    form.addEventListener('submit', e => { e.preventDefault(); send(input.value); });
    $$('#chipsRow [data-q]').forEach(b => b.addEventListener('click', () => send(b.dataset.q)));
    const auto = form.dataset.autosend;       // question envoyée depuis le Conseil IA du dashboard
    if (auto) setTimeout(() => send(auto), 700);
  }
})();
