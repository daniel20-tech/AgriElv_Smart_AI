// Mini générateur de PDF 100 % JavaScript (aucune librairie) : texte, rectangles, lignes, multi-pages.
(() => {
  'use strict';
  const PW = 595.28, PH = 841.89;
  const MAP = { '’': "'", '‘': "'", '“': '"', '”': '"', '–': '-', '—': '-', '…': '...', 'œ': 'oe', 'Œ': 'OE', '€': 'EUR', '→': '->', '\u202f': ' ', '\u00a0': ' ' };

  // Échappe le texte pour un flux PDF (Helvetica / WinAnsi : accents français OK)
  function esc(s) {
    let o = '';
    for (const ch of String(s)) {
      const c = MAP[ch] ?? ch;
      for (const k of c) {
        const n = k.charCodeAt(0);
        if (k === '\\' || k === '(' || k === ')') o += '\\' + k;
        else if (n < 32) o += ' ';
        else if (n < 127) o += k;
        else if (n <= 255) o += '\\' + n.toString(8).padStart(3, '0');
        else o += '?';
      }
    }
    return o;
  }
  const col = c => c.map(v => (v / 255).toFixed(3)).join(' ');

  class Doc {
    constructor() { this.pages = []; this.addPage(); }
    addPage() { this.ops = []; this.pages.push(this.ops); }
    width(s, size, bold) { return String(s).length * size * (bold ? 0.57 : 0.52); }
    // y = distance depuis le haut de la page (en points)
    text(x, y, s, { size = 11, bold = false, color = [20, 28, 22], align = 'left' } = {}) {
      let X = x;
      if (align !== 'left') { const w = this.width(s, size, bold); X = align === 'right' ? x - w : x - w / 2; }
      this.ops.push(`BT /${bold ? 'F2' : 'F1'} ${size} Tf ${col(color)} rg ${X.toFixed(2)} ${(PH - y).toFixed(2)} Td (${esc(s)}) Tj ET`);
    }
    rect(x, y, w, h, color) { this.ops.push(`${col(color)} rg ${x.toFixed(2)} ${(PH - y - h).toFixed(2)} ${w.toFixed(2)} ${h.toFixed(2)} re f`); }
    line(x1, y1, x2, y2, color = [220, 228, 222], wd = 0.8) {
      this.ops.push(`${col(color)} RG ${wd} w ${x1.toFixed(2)} ${(PH - y1).toFixed(2)} m ${x2.toFixed(2)} ${(PH - y2).toFixed(2)} l S`);
    }
    build() {
      const o = [], n = this.pages.length;
      o[1] = '<< /Type /Catalog /Pages 2 0 R >>';
      o[2] = `<< /Type /Pages /Kids [${this.pages.map((_, i) => (5 + i * 2) + ' 0 R').join(' ')}] /Count ${n} >>`;
      o[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
      o[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
      this.pages.forEach((ops, i) => {
        const pid = 5 + i * 2, cid = pid + 1, stream = ops.join('\n');
        o[pid] = `<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ${PW} ${PH}] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents ${cid} 0 R >>`;
        o[cid] = `<< /Length ${stream.length} >>\nstream\n${stream}\nendstream`;
      });
      let out = '%PDF-1.4\n';
      const offs = [];
      for (let i = 1; i < o.length; i++) { offs[i] = out.length; out += `${i} 0 obj\n${o[i]}\nendobj\n`; }
      const xref = out.length;
      out += `xref\n0 ${o.length}\n0000000000 65535 f \n`;
      for (let i = 1; i < o.length; i++) out += String(offs[i]).padStart(10, '0') + ' 00000 n \n';
      out += `trailer\n<< /Size ${o.length} /Root 1 0 R >>\nstartxref\n${xref}\n%%EOF`;
      return new Blob([out], { type: 'application/pdf' });   // contenu 100 % ASCII : octets = caractères
    }
  }

  const GREEN = [15, 123, 71], DARK = [20, 28, 22], MUTED = [110, 126, 117], LINE = [226, 233, 228];
  const LV = { ok: [47, 168, 79], warn: [243, 198, 35], bad: [239, 74, 58], none: [160, 170, 165] };
  const SEV = { faible: LV.ok, moyenne: LV.warn, critique: LV.bad };

  function report(d) {
    const doc = new Doc();
    const M = 40;
    let y = 0;
    const footer = () => doc.text(PW / 2, PH - 22, `AgriElv Smart AI - Rapport hebdomadaire - page ${doc.pages.length}`, { size: 8, color: MUTED, align: 'center' });
    const ensure = h => { if (y + h > PH - 50) { footer(); doc.addPage(); y = 50; } };
    const title = t => { ensure(40); doc.text(M, y, t, { size: 13, bold: true, color: GREEN }); y += 8; doc.line(M, y, PW - M, y, LINE, 1); y += 20; };

    // En-tête
    doc.rect(0, 0, PW, 92, GREEN);
    doc.text(M, 42, 'AgriElv Smart AI', { size: 24, bold: true, color: [255, 255, 255] });
    doc.text(M, 64, `Rapport hebdomadaire - ${d.nom} (${d.type})`, { size: 11, color: [225, 245, 232] });
    doc.text(PW - M, 64, `Généré le ${d.generated}`, { size: 9, color: [225, 245, 232], align: 'right' });
    y = 128;

    // Indicateurs
    title('Indicateurs des 7 derniers jours');
    const s = d.stats, f = (v, u) => (v === null || v === undefined ? '--' : v + u);
    const kp = [['Température moyenne', f(s.temp, ' °C')], ['Humidité moyenne', f(s.hum, ' %')], ["Nombre d'alertes", String(s.alerts)], ['Activité caméra', f(s.camera, ' %')]];
    const bw = (PW - 2 * M - 3 * 10) / 4;
    kp.forEach(([l, v], i) => {
      const x = M + i * (bw + 10);
      doc.rect(x, y - 10, bw, 58, [242, 247, 244]);
      doc.text(x + 10, y + 8, l, { size: 8.5, color: MUTED });
      doc.text(x + 10, y + 34, v, { size: 16, bold: true, color: DARK });
    });
    y += 76;

    // Santé actuelle
    title('État actuel');
    if (d.latest) {
      const L = d.latest;
      doc.text(M, y, `Indice de santé : ${L.health} %`, { size: 12, bold: true });
      doc.text(M, y + 18, `Température ${L.temperature} °C   |   Humidité du sol ${L.soil_humidity} %   |   Qualité de l'air ${L.air_quality}/100`, { size: 10, color: MUTED });
      doc.text(M, y + 34, `Seuils d'alerte : température > ${d.thresholds.temp} °C, humidité < ${d.thresholds.hum} %`, { size: 9, color: MUTED });
      y += 56;
    } else { doc.text(M, y, 'Aucune mesure disponible pour le moment.', { size: 10, color: MUTED }); y += 30; }

    // Barres : conditions optimales
    title('Conditions optimales par jour');
    const bh = 90, baseY = y + bh, n = d.week.length, slot = (PW - 2 * M) / n;
    d.week.forEach((w, i) => {
      const h = w.pct === null ? 4 : Math.max(4, bh * w.pct / 100);
      const x = M + i * slot + slot * 0.2;
      doc.rect(x, baseY - h, slot * 0.6, h, w.today ? GREEN : [150, 205, 175]);
      doc.text(x + slot * 0.3, baseY + 13, w.label, { size: 8.5, color: MUTED, align: 'center' });
      doc.text(x + slot * 0.3, baseY - h - 5, w.pct === null ? '--' : w.pct + '%', { size: 8.5, bold: true, align: 'center' });
    });
    y = baseY + 36;

    // Zones
    if (d.zones && d.zones.length) {
      title('Zones');
      d.zones.forEach(z => {
        ensure(24);
        doc.rect(M, y - 9, 8, 8, LV[z.level] || LV.none);
        doc.text(M + 16, y, `${z.name} - ${z.culture}`, { size: 10, bold: true });
        doc.text(PW - M, y, `${z.label}${z.score !== null ? ' (' + z.score + ' %)' : ''}${z.sim ? ' - simulé' : ''}`, { size: 10, color: MUTED, align: 'right' });
        y += 20;
      });
      y += 10;
    }

    // Alertes
    title('Historique des alertes');
    if (!d.alerts.length) doc.text(M, y, 'Aucune alerte sur la période. Bravo !', { size: 10, color: MUTED });
    d.alerts.forEach(a => {
      ensure(22);
      doc.rect(M, y - 9, 8, 8, SEV[a.sev] || LV.ok);
      doc.text(M + 16, y, a.date, { size: 9, color: MUTED });
      doc.text(M + 100, y, a.msg.length > 66 ? a.msg.slice(0, 63) + '...' : a.msg, { size: 9.5 });
      doc.text(PW - M, y, a.sev, { size: 9, bold: true, color: DARK, align: 'right' });
      y += 18;
    });
    footer();

    // Téléchargement
    const url = URL.createObjectURL(doc.build());
    const a = document.createElement('a');
    a.href = url;
    a.download = 'rapport-agrielv-' + new Date().toISOString().slice(0, 10) + '.pdf';
    document.body.appendChild(a); a.click(); a.remove();
    setTimeout(() => URL.revokeObjectURL(url), 4000);
  }

  window.AFPDF = { report, Doc };
})();
