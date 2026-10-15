// Forum façon WhatsApp : liste de salons + conversation (actualisation toutes les 4 s)
(() => {
  'use strict';
  const wa = document.getElementById('wa');
  if (!wa || !window.AF) return;
  const { $ } = AF;
  const room = +wa.dataset.room, me = +wa.dataset.me, isAdmin = wa.dataset.admin === '1';
  const log = $('#waLog'), input = $('#waInput');
  let last = 0, lastDay = '', first = true, busy = false;
  const colors = ['#d35400', '#8e44ad', '#16a085', '#c0392b', '#2980b9', '#b9770e', '#117a65'];

  const dayLabel = d => {
    const same = (a, b) => a.toDateString() === b.toDateString();
    if (same(d, new Date())) return "Aujourd'hui";
    if (same(d, new Date(Date.now() - 864e5))) return 'Hier';
    return d.toLocaleDateString('fr-FR', { day: 'numeric', month: 'long' });
  };

  function add(m) {
    $('.wa-empty', log)?.remove();
    const d = new Date(m.ts);
    if (d.toDateString() !== lastDay) {
      lastDay = d.toDateString();
      const s = document.createElement('div');
      s.className = 'wa-day'; s.textContent = dayLabel(d);
      log.appendChild(s);
    }
    const b = document.createElement('div');
    b.className = 'wa-msg ' + (m.uid === me ? 'me' : 'other');
    b.dataset.id = m.id;
    if (m.uid !== me) {
      const n = document.createElement('span');
      n.className = 'wa-name'; n.style.color = colors[m.uid % colors.length]; n.textContent = m.name;
      b.appendChild(n);
    }
    const t = document.createElement('span');
    t.textContent = m.text;                    // textContent : pas d'injection HTML
    b.appendChild(t);
    const meta = document.createElement('span');
    meta.className = 'wa-meta';
    meta.textContent = d.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
    if (m.uid === me) { const k = document.createElement('i'); k.className = 'bi bi-check2-all'; meta.appendChild(k); }
    b.appendChild(meta);
    if (m.uid === me || isAdmin) {
      const x = document.createElement('button');
      x.type = 'button'; x.className = 'wa-del'; x.setAttribute('aria-label', 'Supprimer');
      x.innerHTML = '<i class="bi bi-trash"></i>';
      x.addEventListener('click', async () => {
        if (!confirm('Supprimer ce message ?')) return;
        const r = await AF.post('api/forum.php', { delete: m.id });
        if (r.ok) b.remove();
      });
      b.appendChild(x);
    }
    log.appendChild(b);
    last = Math.max(last, m.id);
  }

  async function load() {
    if (busy) return;
    busy = true;
    try {
      const r = await fetch(`api/forum.php?room=${room}&after=${last}`, { credentials: 'same-origin' });
      if (r.status === 401) { location.href = 'login.php'; return; }
      const d = await r.json();
      const list = d.messages || [];
      const near = log.scrollHeight - log.scrollTop - log.clientHeight < 140;
      list.forEach(add);
      if (first && !list.length) {
        const e = document.createElement('div');
        e.className = 'wa-empty';
        e.innerHTML = '<i class="bi bi-chat-heart fs-1"></i><p>Aucun message. Lancez la discussion !</p>';
        log.appendChild(e);
      }
      if (list.length && (near || first)) log.scrollTop = log.scrollHeight;
      first = false;
    } catch (e) { /* hors ligne : on réessaie au prochain tour */ }
    busy = false;
  }

  $('#waForm').addEventListener('submit', async e => {
    e.preventDefault();
    const text = input.value.trim();
    if (!text) return;
    input.value = '';
    const r = await AF.post('api/forum.php', { room, text });
    if (r.ok && r.data.message) { add(r.data.message); log.scrollTop = log.scrollHeight; }
    else { AF.toast(r.data.error || "Message non envoyé. Réessayez.", 'err'); input.value = text; }
    input.focus();
  });
  $('#waEmojiBtn').addEventListener('click', () => { const t = $('#waEmojis'); t.hidden = !t.hidden; });
  document.querySelectorAll('.wa-emoji').forEach(b => b.addEventListener('click', () => { input.value += b.dataset.e; input.focus(); }));

  load();
  setInterval(() => { if (!document.hidden) load(); }, 4000);
})();
