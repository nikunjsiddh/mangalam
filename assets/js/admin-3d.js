/* Mangalam Jewellers — Products › 3D view & customiser (the product editor's 3D card).
 *
 * The card keeps every setting in one hidden field, view3d (JSON), which is saved with the product and checked
 * by view3d_config() in app/lib/content.php. Beside the product's main photo it shows a live 3D preview
 * (assets/js/viewer3d.js), so the team can match a house ring design to the photo — "Match from photo & details"
 * fills in the ring type from the name and the metal and stone from the product's details and photo. A .glb model
 * can be uploaded instead (admin/api.php › upload.model). */
import { createViewer, DESIGNS, METALS, STONES, DESIGN_ICONS, find } from 'mangalam/viewer3d';

const card = document.querySelector('[data-view3d-card]');
if (card) init(card);

function init(card) {
  const $ = s => card.querySelector(s);
  const $$ = s => [...card.querySelectorAll(s)];
  const form = card.closest('form');
  const field = $('[data-v3d-value]');
  const status = $('[data-v3d-status]');
  const CSRF = document.querySelector('meta[name="csrf-token"]')?.content || '';

  let cfg = {};
  try { cfg = JSON.parse(field.value || '{}') || {}; } catch (err) { cfg = {}; }
  cfg = {
    enabled: false, source: 'design', model: '', metal: 'yellow', stone: 'diamond',
    metals: METALS.map(m => m.id), stones: STONES.map(s => s.id),
    ...cfg,
    design: { type: 'solitaire', stoneSize: 1, bandWidth: 1, prongs: 6, ...(cfg.design || {}) },
  };
  let modelInfo = null; // { name, size } for a model uploaded on this visit
  let viewer = null;

  /* ---------- Building the controls ---------- */
  const el = (tag, cls, text) => {
    const e = document.createElement(tag);
    if (cls) e.className = cls;
    if (text) e.textContent = text;
    return e;
  };
  $('[data-v3d-designs]').replaceChildren(...DESIGNS.map(d => {
    const label = el('label', 'v3d-design');
    const input = el('input');
    Object.assign(input, { type: 'radio', name: 'v3d-design', value: d.id });
    input.dataset.v3dDesign = '';
    const icon = el('span', 'v3d-design__icon');
    icon.innerHTML = DESIGN_ICONS[d.id];
    const text = el('span', 'v3d-design__text');
    text.append(el('strong', '', d.name), el('small', '', d.sub));
    label.append(input, icon, text);
    return label;
  }));
  for (const [kind, list] of [['metal', METALS], ['stone', STONES]]) {
    $(`[data-v3d-select="${kind}"]`).replaceChildren(...list.map(o => Object.assign(el('option', '', `${o.name} — ${o.sub}`), { value: o.id })));
  }
  for (const [kind, list] of [['metals', METALS], ['stones', STONES]]) {
    $(`[data-v3d-choices="${kind}"]`).replaceChildren(...list.map(o => {
      const label = el('label', 'v3d-choice');
      const input = Object.assign(el('input'), { type: 'checkbox', value: o.id });
      input.dataset.v3dChoice = kind;
      const chip = el('span', `v3d-choice__chip v3d-choice__chip--${kind}${kind === 'stones' && !o.tint ? ' is-diamond' : ''}`);
      chip.style.setProperty('--c', o.color);
      label.append(input, chip, el('span', '', o.name));
      return label;
    }));
  }

  /* ---------- Showing the settings ---------- */
  const design = () => find(DESIGNS, cfg.design.type);
  const hasStone = () => (cfg.source === 'model' ? !viewer?.stats || viewer.stats.stones > 0 : design().stone);
  function render() {
    $('[data-v3d-enabled]').checked = !!cfg.enabled;
    card.classList.toggle('is-off', !cfg.enabled);
    $$('[data-v3d-source]').forEach(r => (r.checked = r.value === cfg.source));
    $('[data-v3d-panel="design"]').hidden = cfg.source !== 'design';
    $('[data-v3d-panel="model"]').hidden = cfg.source !== 'model';
    $$('[data-v3d-design]').forEach(r => (r.checked = r.value === cfg.design.type));
    for (const key of ['stoneSize', 'bandWidth']) {
      $(`[data-v3d-range="${key}"]`).value = cfg.design[key];
      $(`[data-v3d-out="${key}"]`).textContent = `${Math.round(cfg.design[key] * 100)}%`;
    }
    $$('[data-v3d-prongs]').forEach(r => (r.checked = Number(r.value) === cfg.design.prongs));
    $('[data-v3d-prongs-field]').hidden = cfg.source !== 'design' || !design().prongs;
    $$('[data-v3d-stone-field]').forEach(f => (f.hidden = !hasStone()));
    $('[data-v3d-select="metal"]').value = cfg.metal;
    $('[data-v3d-select="stone"]').value = cfg.stone;
    $$('[data-v3d-choice]').forEach(c => {
      const kind = c.dataset.v3dChoice, first = kind === 'metals' ? cfg.metal : cfg.stone;
      c.checked = cfg[kind].includes(c.value);
      c.disabled = c.value === first; // the look shown first is always one of the choices
      c.closest('label').title = c.disabled ? 'Shown first, so always offered' : '';
    });
    const info = $('[data-v3d-model-info]');
    info.hidden = !cfg.model;
    if (cfg.model) {
      $('[data-v3d-model-name]').textContent = cfg.model.split('/').pop();
      $('[data-v3d-model-text]').textContent = modelText();
    }
    field.value = JSON.stringify(cfg);
  }
  function modelText() {
    const s = viewer?.stats, size = modelInfo ? ` (${modelInfo.size})` : '';
    if (!s) return `${size} — uploaded${cfg.source === 'model' ? '' : '. Choose “My 3D model” above to use it'}.`;
    if (!s.metals && !s.stones) return `${size} — no metal or stone materials were recognised, so it shows as it was made and the metal and stone choices do not change it.`;
    return `${size} — ${s.metals} metal ${s.metals === 1 ? 'part' : 'parts'} and ${s.stones} ${s.stones === 1 ? 'stone' : 'stones'} found; visitors can change ${s.metals && s.stones ? 'both' : s.metals ? 'the metal' : 'the stones'}.`;
  }

  /* ---------- The preview ---------- */
  async function show(animate = true) {
    if (!viewer) return;
    try {
      await viewer.apply({ ...cfg, modelUrl: cfg.source === 'model' && cfg.model ? `../${cfg.model}` : '' }, { animate });
      status.hidden = true;
    } catch (err) {
      console.error(err);
      status.hidden = false;
      status.textContent = 'This 3D model could not be opened. Check that it is a .glb file, or upload it again.';
    }
    render();
  }
  function startViewer() {
    if (viewer) return;
    try {
      viewer = createViewer($('[data-v3d-canvas]'), { theme: 'dark', zoom: false });
    } catch (err) {
      console.error(err);
      status.textContent = 'This browser could not start the 3D preview.';
      return;
    }
    show(false);
  }
  // The preview starts when the card comes into view (three.js is already loaded with this module)
  new IntersectionObserver((entries, io) => {
    if (entries.some(e => e.isIntersecting)) { io.disconnect(); startViewer(); }
  }, { rootMargin: '200px' }).observe(card);

  /* ---------- Changes ---------- */
  card.addEventListener('change', e => {
    const t = e.target;
    if (t.matches('[data-v3d-enabled]')) cfg.enabled = t.checked;
    else if (t.matches('[data-v3d-source]')) cfg.source = t.value;
    else if (t.matches('[data-v3d-design]')) cfg.design = { ...cfg.design, type: t.value };
    else if (t.matches('[data-v3d-prongs]')) cfg.design = { ...cfg.design, prongs: Number(t.value) };
    else if (t.matches('[data-v3d-range]')) cfg.design = { ...cfg.design, [t.dataset.v3dRange]: Number(t.value) };
    else if (t.matches('[data-v3d-select]')) {
      const kind = t.dataset.v3dSelect;
      cfg[kind] = t.value;
      const list = `${kind}s`;
      if (!cfg[list].includes(t.value)) cfg[list] = [...cfg[list], t.value];
    } else if (t.matches('[data-v3d-choice]')) {
      const kind = t.dataset.v3dChoice, order = (kind === 'metals' ? METALS : STONES).map(o => o.id);
      const set = new Set(cfg[kind]);
      if (t.checked) set.add(t.value); else set.delete(t.value);
      cfg[kind] = order.filter(id => set.has(id));
      render();
      return;
    } else if (t.matches('[data-v3d-file]')) {
      if (t.files[0]) uploadModel(t.files[0]);
      t.value = '';
      return;
    } else return;
    render();
    if (!t.matches('[data-v3d-enabled]')) show(!t.matches('[data-v3d-range]'));
  });
  // Sliders redraw as they move
  card.addEventListener('input', e => {
    if (!e.target.matches('[data-v3d-range]')) return;
    cfg.design = { ...cfg.design, [e.target.dataset.v3dRange]: Number(e.target.value) };
    render();
    show(false);
  });
  card.addEventListener('click', e => {
    const tool = e.target.closest('[data-v3d-tool]');
    if (tool && viewer) {
      if (tool.dataset.v3dTool === 'turn') {
        const on = tool.getAttribute('aria-pressed') !== 'true';
        tool.setAttribute('aria-pressed', String(on));
        viewer.setAutoRotate(on);
      } else {
        viewer.resetView();
      }
    }
    if (e.target.closest('[data-v3d-match]')) matchPhoto();
  });

  /* ---------- Uploading a .glb model ---------- */
  const zone = $('[data-v3d-dropzone]');
  ['dragenter', 'dragover'].forEach(type => zone.addEventListener(type, e => { e.preventDefault(); zone.classList.add('is-over'); }));
  ['dragleave', 'drop'].forEach(type => zone.addEventListener(type, () => zone.classList.remove('is-over')));
  zone.addEventListener('drop', e => {
    e.preventDefault();
    if (e.dataTransfer.files[0]) uploadModel(e.dataTransfer.files[0]);
  });
  async function uploadModel(file) {
    if (!/\.glb$/i.test(file.name)) {
      toast('Not a .glb file', 'Export the piece as binary glTF (.glb) and upload that.', true);
      return;
    }
    zone.classList.add('is-busy');
    const body = new FormData();
    body.append('action', 'upload.model');
    body.append('file', file);
    let res;
    try {
      res = await fetch('api.php', { method: 'POST', body, credentials: 'same-origin', headers: { 'X-CSRF-Token': CSRF, Accept: 'application/json' } }).then(r => r.json());
    } catch (err) {
      res = { ok: false, error: 'Could not reach the server. Check the connection and try again.' };
    }
    zone.classList.remove('is-busy');
    if (!res || !res.ok) {
      toast('The model was not uploaded', res?.error || 'Please try again.', true);
      return;
    }
    cfg.model = res.path;
    cfg.source = 'model';
    modelInfo = { name: res.name, size: res.size };
    render();
    await show(true);
    toast(res.message, res.text);
  }

  /* ---------- Match from photo ---------- */
  function mainPhoto() {
    const first = form.querySelector('[data-gallery] input[name="gallery[]"]');
    return first && first.value ? `../${first.value}` : '';
  }
  function syncPhoto() {
    const src = mainPhoto(), img = $('[data-v3d-photo]');
    if (src && img.getAttribute('src') !== src) img.src = src;
    img.hidden = !src;
    $('[data-v3d-nophoto]').hidden = !!src;
  }
  const gallery = form.querySelector('[data-gallery]');
  if (gallery) new MutationObserver(syncPhoto).observe(gallery, { childList: true, subtree: true, attributes: true, attributeFilter: ['value'] });
  syncPhoto();

  // The ring type comes from the name, the metal and stone from the product's details; the photo settles what
  // the details leave open (white or yellow metal for a diamond piece, the colour of a mixed or unnamed stone)
  async function matchPhoto() {
    const note = $('[data-v3d-match-note]');
    const field = n => form.querySelector(`[name="${n}"]`)?.value || '';
    const found = [];
    const name = field('name').toLowerCase();
    const words = [['halo', 'halo'], ['trilogy', 'trilogy'], ['three stone', 'trilogy'], ['three-stone', 'trilogy'], ['eternity', 'eternity'], ['band', 'band'], ['wedding', 'band'], ['solitaire', 'solitaire']];
    const byName = words.find(([w]) => name.includes(w));
    if (byName) {
      cfg.design = { ...cfg.design, type: byName[1] };
      found.push(`${find(DESIGNS, byName[1]).name} (from the name)`);
    } else if (field('stone') === 'None') {
      cfg.design = { ...cfg.design, type: 'band' };
      found.push('Plain band (no main stone in the details)');
    }
    let colours = null;
    if (mainPhoto()) {
      try { colours = await readColours(mainPhoto()); } catch (err) { console.error(err); }
    }
    const metal = { 'Rose gold': 'rose', Platinum: 'platinum', Silver: 'white' }[field('metal')];
    cfg.metal = metal || colours?.metal || 'yellow';
    found.push(`${find(METALS, cfg.metal).name} (from the ${metal || !colours?.metal ? 'details' : 'photo'})`);
    const stone = { Ruby: 'ruby', Emerald: 'emerald', Diamond: 'diamond', Polki: 'diamond', Kundan: 'diamond', Pearl: 'diamond' }[field('stone')];
    if (stone || colours?.stone || field('stone') === 'Mixed') {
      cfg.stone = stone || colours?.stone || 'diamond';
      found.push(`${find(STONES, cfg.stone).name} (from the ${stone || !colours?.stone ? 'details' : 'photo'})`);
    }
    for (const [kind, list] of [['metal', 'metals'], ['stone', 'stones']]) if (!cfg[list].includes(cfg[kind])) cfg[list] = [...cfg[list], cfg[kind]];
    note.textContent = `Matched: ${found.join(' · ')}. Compare the preview with the photo and adjust anything below.`;
    if (cfg.source === 'model' && !cfg.model) cfg.source = 'design';
    render();
    show(true);
  }

  /* ---------- Toasts, as the rest of the admin shows them ---------- */
  function toast(title, text, isError) {
    const box = document.querySelector('[data-toasts]'), tpl = document.getElementById('toast-template');
    if (!box || !tpl) return;
    const t = tpl.content.firstElementChild.cloneNode(true);
    t.querySelector('.toast__title').textContent = title;
    t.querySelector('.toast__text').textContent = text || '';
    t.querySelector('.toast__text').hidden = !text;
    t.classList.toggle('toast--error', !!isError);
    box.append(t);
    const dismiss = () => { t.classList.add('is-leaving'); setTimeout(() => t.remove(), 260); };
    t.querySelector('.toast__close').addEventListener('click', dismiss);
    setTimeout(dismiss, isError ? 8000 : 4800);
  }

  render();
}

/**
 * Reads what a product photograph can say about the piece. Jewellery sits in the middle of the shot while skin,
 * fabric and backdrop reach the edges, so only colours found more in the centre than at the edges count:
 * metal is 'white' when the centre is mostly white metal, otherwise 'yellow' (skin and gold share their hues, so
 * rose gold is left to the product's details), and stone is a clearly coloured one, or null.
 */
function readColours(src) {
  return new Promise((resolve, reject) => {
    const img = new Image();
    img.onload = () => {
      const S = 160, c = document.createElement('canvas');
      c.width = c.height = S;
      const x = c.getContext('2d', { willReadFrequently: true });
      const k = Math.max(S / img.naturalWidth, S / img.naturalHeight);
      x.drawImage(img, (S - img.naturalWidth * k) / 2, (S - img.naturalHeight * k) / 2, img.naturalWidth * k, img.naturalHeight * k);
      const d = x.getImageData(0, 0, S, S).data;
      const tally = () => ({ gold: 0, white: 0, ruby: 0, emerald: 0, sapphire: 0, amethyst: 0, n: 0 });
      const centre = tally(), edge = tally();
      for (let py = 0; py < S; py++) {
        for (let px = 0; px < S; px++) {
          const m = Math.max(Math.abs(px - S / 2), Math.abs(py - S / 2)) / (S / 2);
          const t = m < 0.45 ? centre : m > 0.7 ? edge : null;
          if (!t) continue;
          const o = (py * S + px) * 4, r = d[o], g = d[o + 1], b = d[o + 2];
          const max = Math.max(r, g, b), min = Math.min(r, g, b), v = max / 255, s = max ? (max - min) / max : 0;
          t.n++;
          if (v < 0.14) continue;
          let h = 0;
          if (max !== min) {
            h = max === r ? ((g - b) / (max - min)) % 6 : max === g ? (b - r) / (max - min) + 2 : (r - g) / (max - min) + 4;
            h = (h * 60 + 360) % 360;
          }
          if (s < 0.14 && v > 0.45) t.white++;
          else if (h >= 30 && h < 62 && s >= 0.35) t.gold++;
          if (s > 0.45 && v > 0.18) {
            if (h >= 330 || h < 12) t.ruby++;
            else if (h >= 80 && h < 175) t.emerald++;
            else if (h >= 185 && h < 255) t.sapphire++;
            else if (h >= 255 && h < 330) t.amethyst++;
          }
        }
      }
      // How much more of each colour the centre has than the edges, in percent
      const extra = key => (centre[key] / centre.n - edge[key] / edge.n) * 100;
      const metal = extra('white') > 4 && extra('white') > extra('gold') + 3 ? 'white' : extra('gold') > 3 ? 'yellow' : null;
      const [stone, amount] = ['ruby', 'emerald', 'sapphire', 'amethyst'].map(id => [id, extra(id)]).sort((a, b) => b[1] - a[1])[0];
      resolve({ metal, stone: amount > 3 ? stone : null });
    };
    img.onerror = reject;
    img.src = src;
  });
}
