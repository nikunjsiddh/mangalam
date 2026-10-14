/* Mangalam Jewellers — the 3D view & customiser (app/views/admin/partials/view3d-editor.html), in the product
 * editor and in the drawer of Catalogue › 3D jewellery.
 *
 * The card keeps every setting in one hidden field, view3d (JSON), which is saved with the product and checked
 * by view3d_config() in app/lib/content.php. A live 3D preview (assets/js/viewer3d.js) sits beside the product's
 * main photo so the team can match a house design to it — a ring, kangan, earrings or pendant, each in several
 * styles — and "Match from photo & details" guesses the kind from the category, the style from the name, and the
 * metal and stone from the details and photo. A .glb model can be uploaded instead (admin/api.php › upload.model).
 * The team also chooses what visitors may change: metals, stones, other styles of the same kind, and whether they
 * are asked for their size.
 *
 * In the drawer the card is filled from the piece's Edit button (admin.js dispatches admin:filled on the dialog),
 * or from the product chosen when adding a new 3D piece. */
import { createViewer, KINDS, DESIGNS, METALS, STONES, DESIGN_ICONS, designsOf, find } from 'mangalam/viewer3d';

const card = document.querySelector('[data-view3d-card]');
if (card) init(card);

function init(card) {
  const $ = s => card.querySelector(s);
  const $$ = s => [...card.querySelectorAll(s)];
  const form = card.closest('form');
  const field = $('[data-v3d-value]');
  const status = $('[data-v3d-status]');
  const CSRF = document.querySelector('meta[name="csrf-token"]')?.content || '';
  let GUESS = { kinds: {}, words: {} };
  try { GUESS = JSON.parse(card.dataset.v3dGuess || '{}'); } catch (err) { /* keep the empty lists */ }
  const DESIGN_LABEL = { ring: 'Ring design', bangle: 'Kangan design', earring: 'Earring style', pendant: 'Pendant design' };

  // In the drawer, the product being edited: { name, category, metal, stone, photo }
  let product = null;
  const normal = raw => {
    const c = {
      enabled: false, kind: 'ring', source: 'design', model: '', metal: 'yellow', stone: 'diamond',
      metals: METALS.map(m => m.id), stones: STONES.map(s => s.id), styles: [], sizes: false,
      ...(raw || {}),
    };
    if (!find(KINDS, c.kind) || find(KINDS, c.kind).id !== c.kind) c.kind = 'ring';
    const ids = designsOf(c.kind).map(d => d.id);
    c.design = { type: ids[0], stoneSize: 1, bandWidth: 1, prongs: 6, pair: false, ...(c.design || {}) };
    if (!ids.includes(c.design.type)) c.design.type = ids[0];
    c.styles = ids.filter(id => id === c.design.type || (c.styles || []).includes(id));
    return c;
  };
  const parse = text => { try { return JSON.parse(text || '{}') || {}; } catch (err) { return {}; } };
  let cfg = normal(parse(field.value));
  let modelInfo = null; // { name, size } for a model uploaded on this visit
  let viewer = null;

  /* ---------- Building the controls ---------- */
  const el = (tag, cls, text) => {
    const e = document.createElement(tag);
    if (cls) e.className = cls;
    if (text) e.textContent = text;
    return e;
  };
  $('[data-v3d-kinds]').replaceChildren(...KINDS.map(k => {
    const label = el('label');
    const input = Object.assign(el('input'), { type: 'radio', name: 'v3d-kind', value: k.id });
    input.dataset.v3dKind = '';
    label.append(input, el('span', '', k.name));
    return label;
  }));
  function buildDesigns() {
    $('[data-v3d-designs]').replaceChildren(...designsOf(cfg.kind).map(d => {
      const label = el('label', 'v3d-design');
      const input = Object.assign(el('input'), { type: 'radio', name: 'v3d-design', value: d.id });
      input.dataset.v3dDesign = '';
      const icon = el('span', 'v3d-design__icon');
      icon.innerHTML = DESIGN_ICONS[d.id] || '';
      const text = el('span', 'v3d-design__text');
      text.append(el('strong', '', d.name), el('small', '', d.sub));
      label.append(input, icon, text);
      return label;
    }));
    $('[data-v3d-choices="styles"]').replaceChildren(...designsOf(cfg.kind).map(d => {
      const label = el('label', 'v3d-choice v3d-choice--style');
      const input = Object.assign(el('input'), { type: 'checkbox', value: d.id });
      input.dataset.v3dChoice = 'styles';
      const icon = el('span', 'v3d-choice__icon');
      icon.innerHTML = DESIGN_ICONS[d.id] || '';
      label.append(input, icon, el('span', '', d.name));
      return label;
    }));
  }
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
  buildDesigns();
  let builtKind = cfg.kind;

  /* ---------- Showing the settings ---------- */
  const kindOf = () => find(KINDS, cfg.kind);
  const design = () => find(DESIGNS, cfg.design.type);
  const hasStone = () => (cfg.source === 'model' ? !viewer?.stats || viewer.stats.stones > 0 : design().stone);
  function render() {
    if (builtKind !== cfg.kind) { buildDesigns(); builtKind = cfg.kind; }
    const k = kindOf();
    $('[data-v3d-enabled]').checked = !!cfg.enabled;
    card.classList.toggle('is-off', !cfg.enabled);
    $$('[data-v3d-kind]').forEach(r => (r.checked = r.value === cfg.kind));
    $$('[data-v3d-source]').forEach(r => (r.checked = r.value === cfg.source));
    $('[data-v3d-panel="design"]').hidden = cfg.source !== 'design';
    $('[data-v3d-panel="model"]').hidden = cfg.source !== 'model';
    $('[data-v3d-design-label]').textContent = DESIGN_LABEL[cfg.kind];
    $('[data-v3d-width-label]').textContent = k.width;
    $$('[data-v3d-design]').forEach(r => (r.checked = r.value === cfg.design.type));
    for (const key of ['stoneSize', 'bandWidth']) {
      $(`[data-v3d-range="${key}"]`).value = cfg.design[key];
      $(`[data-v3d-out="${key}"]`).textContent = `${Math.round(cfg.design[key] * 100)}%`;
    }
    $$('[data-v3d-prongs]').forEach(r => (r.checked = Number(r.value) === cfg.design.prongs));
    $('[data-v3d-prongs-field]').hidden = cfg.source !== 'design' || !design().prongs;
    $('[data-v3d-pair]').checked = !!cfg.design.pair;
    $('[data-v3d-pair-field]').hidden = cfg.kind !== 'bangle';
    $$('[data-v3d-stone-field]').forEach(f => (f.hidden = !hasStone()));
    $('[data-v3d-select="metal"]').value = cfg.metal;
    $('[data-v3d-select="stone"]').value = cfg.stone;
    $$('[data-v3d-choice]').forEach(c => {
      const kind = c.dataset.v3dChoice, first = kind === 'metals' ? cfg.metal : kind === 'stones' ? cfg.stone : cfg.design.type;
      c.checked = cfg[kind].includes(c.value);
      c.disabled = c.value === first; // the look shown first is always one of the choices
      c.closest('label').title = c.disabled ? 'Shown first, so always offered' : '';
    });
    $('[data-v3d-styles-field]').hidden = cfg.source !== 'design';
    $('[data-v3d-sizes-field]').hidden = !k.size;
    $('[data-v3d-sizes]').checked = !!cfg.sizes && !!k.size;
    if (k.size) $('[data-v3d-size-label]').textContent = k.size.label.toLowerCase();
    $('[data-v3d-visitor-summary]').textContent = visitorSummary();
    const info = $('[data-v3d-model-info]');
    info.hidden = !cfg.model;
    if (cfg.model) {
      $('[data-v3d-model-name]').textContent = cfg.model.split('/').pop();
      $('[data-v3d-model-text]').textContent = modelText();
    }
    field.value = JSON.stringify(cfg);
  }
  // What a visitor sees in the 3D view, in one sentence
  function visitorSummary() {
    const k = kindOf(), parts = [];
    if (cfg.metals.length > 1) parts.push(`${cfg.metals.length} metals`);
    if (hasStone() && cfg.stones.length > 1) parts.push(`${cfg.stones.length} stones`);
    if (cfg.source === 'design' && cfg.styles.length > 1) parts.push(`${cfg.styles.length} styles`);
    if (cfg.sizes && k.size) parts.push(`their ${k.size.label.toLowerCase()}`);
    const what = parts.length ? `Visitors choose ${parts.length > 1 ? parts.slice(0, -1).join(', ') + ' and ' + parts[parts.length - 1] : parts[0]}` : 'Visitors see the piece as it is, without choices';
    return `${what}; their choice goes into the enquiry they send.`;
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
  // A new kind starts on the style its name suggests (or its first), offering only that style
  function setKind(kind) {
    cfg.kind = kind;
    const ids = designsOf(kind).map(d => d.id);
    const guessed = guessType(kind, productInfo().name);
    cfg.design = { ...cfg.design, type: guessed || ids[0], pair: false };
    cfg.styles = [cfg.design.type];
    cfg.sizes = !!find(KINDS, kind).size;
    if (cfg.source === 'model' && !cfg.model) cfg.source = 'design';
  }
  card.addEventListener('change', e => {
    const t = e.target;
    if (!e.isTrusted) return; // programmatic resets (opening the drawer) are followed by a fresh load
    if (t.matches('[data-v3d-enabled]')) cfg.enabled = t.checked;
    else if (t.matches('[data-v3d-kind]')) setKind(t.value);
    else if (t.matches('[data-v3d-source]')) cfg.source = t.value;
    else if (t.matches('[data-v3d-design]')) {
      const only = cfg.styles.length === 1 && cfg.styles[0] === cfg.design.type;
      cfg.design = { ...cfg.design, type: t.value };
      cfg.styles = designsOf(cfg.kind).map(d => d.id).filter(id => id === t.value || (!only && cfg.styles.includes(id)));
    } else if (t.matches('[data-v3d-prongs]')) cfg.design = { ...cfg.design, prongs: Number(t.value) };
    else if (t.matches('[data-v3d-pair]')) cfg.design = { ...cfg.design, pair: t.checked };
    else if (t.matches('[data-v3d-range]')) cfg.design = { ...cfg.design, [t.dataset.v3dRange]: Number(t.value) };
    else if (t.matches('[data-v3d-sizes]')) { cfg.sizes = t.checked; render(); return; }
    else if (t.matches('[data-v3d-select]')) {
      const kind = t.dataset.v3dSelect;
      cfg[kind] = t.value;
      const list = `${kind}s`;
      if (!cfg[list].includes(t.value)) cfg[list] = [...cfg[list], t.value];
    } else if (t.matches('[data-v3d-choice]')) {
      const kind = t.dataset.v3dChoice;
      const order = kind === 'metals' ? METALS.map(o => o.id) : kind === 'stones' ? STONES.map(o => o.id) : designsOf(cfg.kind).map(d => d.id);
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
    if (!e.isTrusted || !e.target.matches('[data-v3d-range]')) return;
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

  /* ---------- In the 3D jewellery drawer: a piece opened from its Edit button, or a product chosen to add ---------- */
  function load(settings, info) {
    product = info || null;
    cfg = normal(settings);
    modelInfo = null;
    $('[data-v3d-match-note]').textContent = 'Picks the kind from the category and the style from the name, and the metal and stone from the details, using the main photo where they leave it open.';
    render();
    syncPhoto();
    show(false);
  }
  const dialog = card.closest('dialog');
  if (dialog) {
    dialog.addEventListener('admin:filled', e => {
      const d = e.detail || {};
      const pick = dialog.querySelector('[data-v3d-pick-field]');
      if (pick) pick.hidden = !!d.id; // a new 3D piece starts by choosing its product
      load(parse(d.view3d), d.product || null);
    });
    const picker = dialog.querySelector('[data-v3d-product]');
    if (picker) picker.addEventListener('change', () => {
      const o = picker.selectedOptions[0];
      if (!o || !o.value) return;
      load({ ...parse(o.dataset.guess), enabled: true }, parse(o.dataset.product));
    });
  }

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
    toast(res.message, product ? 'Save to keep it with this piece.' : res.text);
  }

  /* ---------- Match from photo ---------- */
  // The product's details: from the product editor's own fields, or (in the drawer) from the piece opened
  function productInfo() {
    if (product) return { name: product.name || '', category: product.category || '', metal: product.metal || '', stone: product.stone || '' };
    const value = n => form?.querySelector(`[name="${n}"]`)?.value || '';
    return { name: value('name'), category: value('category'), metal: value('metal'), stone: value('stone') };
  }
  function guessType(kind, name) {
    const words = GUESS.words?.[kind] || {}, lower = String(name || '').toLowerCase();
    const hit = Object.keys(words).find(w => lower.includes(w));
    return hit ? words[hit] : '';
  }
  function mainPhoto() {
    if (product) return product.photo ? `../${product.photo}` : '';
    const first = form?.querySelector('[data-gallery] input[name="gallery[]"]');
    return first && first.value ? `../${first.value}` : '';
  }
  function syncPhoto() {
    const src = mainPhoto(), img = $('[data-v3d-photo]');
    if (src && img.getAttribute('src') !== src) img.src = src;
    img.hidden = !src;
    $('[data-v3d-nophoto]').hidden = !!src;
  }
  const gallery = form?.querySelector('[data-gallery]');
  if (gallery) new MutationObserver(syncPhoto).observe(gallery, { childList: true, subtree: true, attributes: true, attributeFilter: ['value'] });
  syncPhoto();

  // The kind comes from the category, the style from the name, the metal and stone from the product's details;
  // the photo settles what the details leave open (white or yellow metal for a diamond piece, the colour of a mixed
  // or unnamed stone)
  async function matchPhoto() {
    const note = $('[data-v3d-match-note]');
    const info = productInfo(), found = [];
    const kind = GUESS.kinds?.[info.category] || cfg.kind;
    if (kind !== cfg.kind) {
      setKind(kind);
      found.push(`${find(KINDS, kind).name} (from the category)`);
    }
    const byName = guessType(kind, info.name);
    if (byName) {
      cfg.design = { ...cfg.design, type: byName };
      found.push(`${find(DESIGNS, byName).name} (from the name)`);
    } else if (kind === 'ring' && info.stone === 'None') {
      cfg.design = { ...cfg.design, type: 'band' };
      found.push('Plain band (no main stone in the details)');
    }
    if (!cfg.styles.includes(cfg.design.type)) cfg.styles = designsOf(cfg.kind).map(d => d.id).filter(id => id === cfg.design.type || cfg.styles.includes(id));
    let colours = null;
    if (mainPhoto()) {
      try { colours = await readColours(mainPhoto()); } catch (err) { console.error(err); }
    }
    const metal = { 'Rose gold': 'rose', Platinum: 'platinum', Silver: 'white' }[info.metal];
    cfg.metal = metal || colours?.metal || 'yellow';
    found.push(`${find(METALS, cfg.metal).name} (from the ${metal || !colours?.metal ? 'details' : 'photo'})`);
    const stone = { Ruby: 'ruby', Emerald: 'emerald', Diamond: 'diamond', Polki: 'diamond', Kundan: 'diamond', Pearl: 'diamond' }[info.stone];
    if (stone || colours?.stone || info.stone === 'Mixed') {
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
