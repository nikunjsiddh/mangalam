/* Mangalam Jewellers — "View in 3D & customise" on the product page: the piece in the 3D viewer
 * (assets/js/viewer3d.js) — a ring, kangan, earrings or pendant — with the metals, stones and styles the team
 * allowed for it, and their size when the team asks for it (Catalogue › 3D jewellery, or Products › 3D view &
 * customiser in the admin); a picture the visitor can save, and an enquiry that names the design they chose.
 * main.js imports this module the first time a visitor opens the viewer. */
import { createViewer, KINDS, METALS, STONES, DESIGNS, DESIGN_ICONS, find } from 'mangalam/viewer3d';

const ICONS = {
  close: '<path d="M18 6 6 18"/><path d="m6 6 12 12"/>',
  turn: '<path d="M20 12a8 8 0 1 1-2.3-5.6"/><path d="M20 4v4h-4"/>',
  sparkle: '<path d="M12 3c.6 4.2 2.8 6.4 7 7-4.2.6-6.4 2.8-7 7-.6-4.2-2.8-6.4-7-7 4.2-.6 6.4-2.8 7-7z"/><path d="M19 16c.2 1.4.9 2.1 2.3 2.3-1.4.2-2.1.9-2.3 2.3-.2-1.4-.9-2.1-2.3-2.3 1.4-.2 2.1-.9 2.3-2.3z"/>',
  light: '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M2 12h2M20 12h2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
  reset: '<circle cx="12" cy="12" r="3"/><path d="M12 2v4M12 18v4M2 12h4M18 12h4"/>',
  save: '<path d="M12 15V3"/><path d="m7 10 5 5 5-5"/><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>',
  enquire: '<path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"/>',
};
const svg = name => `<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${ICONS[name]}</svg>`;
const esc = s => String(s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);

let dialog = null, viewer = null, current = null, hooks = {}, chosen = {};

function build() {
  dialog = document.createElement('dialog');
  dialog.className = 'v3d';
  dialog.setAttribute('aria-labelledby', 'v3d-title');
  dialog.innerHTML = `
    <div class="v3d__stage">
      <canvas class="v3d__canvas" aria-label="The piece in 3D — drag to turn it"></canvas>
      <p class="v3d__status" data-status role="status">Polishing the gold…</p>
      <p class="v3d__hint" data-hint>Drag to turn · Scroll or pinch to zoom</p>
      <div class="v3d__tools" role="toolbar" aria-label="View">
        <button type="button" class="v3d__tool" data-tool="turn" aria-pressed="true" aria-label="Turntable" title="Turntable">${svg('turn')}</button>
        <button type="button" class="v3d__tool" data-tool="sparkle" aria-pressed="true" aria-label="Sparkle" title="Sparkle">${svg('sparkle')}</button>
        <button type="button" class="v3d__tool" data-tool="light" aria-label="Light sweep" title="Light sweep">${svg('light')}</button>
        <button type="button" class="v3d__tool" data-tool="reset" aria-label="Reset view" title="Reset view">${svg('reset')}</button>
      </div>
    </div>
    <aside class="v3d__panel">
      <button type="button" class="v3d__close" data-close aria-label="Close the 3D view">${svg('close')}</button>
      <p class="v3d__eyebrow">View in 3D &amp; customise</p>
      <h2 class="v3d__title" id="v3d-title" data-title></h2>
      <p class="v3d__summary" data-summary></p>
      <div class="v3d__group" data-group="style">
        <p class="v3d__label">Style <span data-pick="style"></span></p>
        <div class="v3d__styles" data-options="style" role="group" aria-label="Style"></div>
      </div>
      <div class="v3d__group" data-group="metal">
        <p class="v3d__label">Metal <span data-pick="metal"></span></p>
        <div class="v3d__swatches" data-options="metal" role="group" aria-label="Metal"></div>
      </div>
      <div class="v3d__group" data-group="stone">
        <p class="v3d__label">Stone <span data-pick="stone"></span></p>
        <div class="v3d__swatches" data-options="stone" role="group" aria-label="Stone"></div>
      </div>
      <div class="v3d__group" data-group="size">
        <label class="v3d__label" for="v3d-size" data-size-label>Size</label>
        <select class="v3d__size" id="v3d-size" data-size></select>
        <p class="v3d__size-note">Not sure? Choose “I will be measured” and our concierge will size you when you visit.</p>
      </div>
      <figure class="v3d__photo"><img alt="" data-photo><figcaption>The piece as photographed. Try the choices above — every Mangalam jewel is made to order.</figcaption></figure>
      <div class="v3d__actions">
        <button type="button" class="btn btn--primary" data-enquire>${svg('enquire')} Enquire about this design</button>
        <button type="button" class="btn btn--outline" data-save>${svg('save')} Save image</button>
      </div>
      <p class="v3d__note">A 3D impression to help you choose. Each piece is finished by hand, so the made jewel may differ slightly in proportion and colour.</p>
    </aside>`;
  document.body.append(dialog);

  dialog.addEventListener('close', () => {
    document.documentElement.classList.remove('v3d-open');
    if (hooks.trigger && document.contains(hooks.trigger)) hooks.trigger.focus({ preventScroll: true });
  });
  dialog.addEventListener('click', e => {
    if (e.target.closest('[data-close]')) dialog.close();
    const opt = e.target.closest('[data-option]');
    if (opt) choose(opt.dataset.kind, opt.dataset.option);
    const tool = e.target.closest('[data-tool]');
    if (tool && viewer) useTool(tool);
    if (e.target.closest('[data-enquire]')) enquire();
    if (e.target.closest('[data-save]')) saveImage();
  });
  dialog.querySelector('[data-size]').addEventListener('change', e => { chosen.size = e.target.value; sync(); });
  dialog.querySelector('canvas').addEventListener('viewer:interact', () => dialog.querySelector('[data-hint]').classList.add('is-hidden'));
}

function useTool(tool) {
  const kind = tool.dataset.tool;
  if (kind === 'turn' || kind === 'sparkle') {
    const on = tool.getAttribute('aria-pressed') !== 'true';
    tool.setAttribute('aria-pressed', String(on));
    if (kind === 'turn') viewer.setAutoRotate(on);
    else viewer.setSparkle(on);
  } else if (kind === 'light') {
    viewer.sweep();
  } else {
    viewer.resetView();
  }
}

function swatches(kind, ids) {
  const list = kind === 'metal' ? METALS : STONES;
  return ids.map(id => {
    const o = find(list, id);
    const cls = kind === 'stone' && !o.tint ? ' v3d__swatch--diamond' : '';
    return `<button type="button" class="v3d__swatch v3d__swatch--${kind}${cls}" data-kind="${kind}" data-option="${o.id}" aria-pressed="false" style="--c:${o.color}" title="${esc(o.name)}">
      <span class="v3d__chip" aria-hidden="true"></span><span class="v3d__name">${esc(o.name)}</span></button>`;
  }).join('');
}

// The other styles of the same kind the team lets visitors try
function styleButtons(ids) {
  return ids.map(id => {
    const d = find(DESIGNS, id);
    return `<button type="button" class="v3d__style" data-kind="style" data-option="${d.id}" aria-pressed="false" title="${esc(d.sub)}">
      <span class="v3d__style-icon" aria-hidden="true">${DESIGN_ICONS[d.id] || ''}</span><span class="v3d__name">${esc(d.name)}</span></button>`;
  }).join('');
}

const label = (kind, id) => find(kind === 'metal' ? METALS : STONES, id);
const kindOf = () => find(KINDS, current.view3d.kind || 'ring');
const UNMEASURED = 'I will be measured';
const styled = () => current.view3d.source === 'design' && (current.view3d.styles || []).length > 1;
const sized = () => !!(current.view3d.sizes && kindOf().size);
function hasStones() {
  const v = current.view3d;
  if (v.source === 'model') return !viewer || !viewer.stats || viewer.stats.stones > 0;
  return find(DESIGNS, chosen.style || v.design.type).stone;
}
function hasMetal() {
  const v = current.view3d;
  return v.source !== 'model' || !viewer || !viewer.stats || viewer.stats.metals > 0;
}
function summary() {
  const m = label('metal', chosen.metal), s = label('stone', chosen.stone);
  const parts = [hasStones() ? `${m.name} · ${s.name}` : m.name];
  if (styled()) parts.unshift(find(DESIGNS, chosen.style).name);
  if (sized() && chosen.size && chosen.size !== UNMEASURED) parts.push(`${kindOf().size.label} ${chosen.size}`);
  return parts.join(' · ');
}
function sync() {
  dialog.querySelectorAll('[data-option]').forEach(b => b.setAttribute('aria-pressed', String(chosen[b.dataset.kind] === b.dataset.option)));
  dialog.querySelector('[data-pick="metal"]').textContent = label('metal', chosen.metal).sub;
  dialog.querySelector('[data-pick="stone"]').textContent = label('stone', chosen.stone).sub;
  dialog.querySelector('[data-pick="style"]').textContent = styled() ? find(DESIGNS, chosen.style).sub : '';
  dialog.querySelector('[data-group="style"]').hidden = !styled();
  dialog.querySelector('[data-group="size"]').hidden = !sized();
  dialog.querySelector('[data-summary]').textContent = summary();
  dialog.querySelector('[data-group="metal"]').hidden = !hasMetal() || current.view3d.metals.length < 2;
  dialog.querySelector('[data-group="stone"]').hidden = !hasStones() || current.view3d.stones.length < 2;
}

function choose(kind, id) {
  chosen[kind] = id;
  if (kind === 'metal') viewer?.setMetal(id);
  else if (kind === 'stone') viewer?.setStone(id);
  else if (viewer) {
    // Another style of the same piece, in the metal and stone already chosen
    viewer.apply({ ...current.view3d, design: { ...current.view3d.design, type: id }, metal: chosen.metal, stone: chosen.stone }, { animate: true })
      .then(sync).catch(err => console.error(err));
  }
  sync();
}

function enquire() {
  const what = hasStones() ? `${label('metal', chosen.metal).name.toLowerCase()} with ${label('stone', chosen.stone).name.toLowerCase()}` : label('metal', chosen.metal).name.toLowerCase();
  const style = styled() ? `, in the ${find(DESIGNS, chosen.style).name.toLowerCase()} style` : '';
  const sizeName = sized() ? kindOf().size.label.toLowerCase() : '';
  const size = !sized() || !chosen.size ? '' : chosen.size === UNMEASURED ? ` I would like to be measured for my ${sizeName}.` : ` My ${sizeName} is ${chosen.size}.`;
  dialog.close();
  if (hooks.onEnquire) hooks.onEnquire(`I would like to know more about the ${current.name} in ${what}${style}, as I designed it in the 3D view.${size}`);
}

function saveImage() {
  if (!viewer) return;
  const a = document.createElement('a');
  a.href = viewer.snapshot({ title: current.name, sub: summary() });
  a.download = `${current.slug}${styled() ? '-' + chosen.style : ''}-${chosen.metal}${hasStones() ? '-' + chosen.stone : ''}.png`;
  document.body.append(a);
  a.click();
  a.remove();
}

/**
 * Opens the viewer for a product (window.MJ.products entry with a view3d section).
 * @param {object} p  the product
 * @param {object} h  trigger: the button that opened it; onEnquire(text): opens the enquiry form with that message
 */
export async function open3D(p, h = {}) {
  if (!dialog) build();
  hooks = h;
  const fresh = current !== p;
  current = p;
  const v = p.view3d;
  if (fresh) {
    chosen = { metal: v.metal, stone: v.stone, style: v.design.type, size: '' };
    dialog.querySelector('[data-title]').textContent = p.name;
    dialog.querySelector('[data-options="style"]').innerHTML = styleButtons(v.styles || [v.design.type]);
    const k = find(KINDS, v.kind || 'ring');
    if (k.size) {
      dialog.querySelector('[data-size-label]').textContent = k.size.label;
      dialog.querySelector('[data-size]').innerHTML = `<option value="">Choose your ${esc(k.size.label.toLowerCase())}</option>`
        + k.size.list.map(s => `<option>${esc(s)}</option>`).join('') + `<option>${UNMEASURED}</option>`;
    }
    dialog.querySelector('[data-options="metal"]').innerHTML = swatches('metal', v.metals);
    dialog.querySelector('[data-options="stone"]').innerHTML = swatches('stone', v.stones);
    const photo = dialog.querySelector('[data-photo]');
    photo.src = p.thumb;
    photo.alt = p.name;
  }
  sync();
  document.documentElement.classList.add('v3d-open');
  dialog.showModal();

  const status = dialog.querySelector('[data-status]');
  if (!viewer) {
    try {
      viewer = createViewer(dialog.querySelector('canvas'), { theme: 'dark' });
    } catch (err) {
      console.error(err);
      status.textContent = 'Your browser could not start the 3D view. Please try a recent version of Chrome, Safari, Edge or Firefox.';
      return;
    }
  }
  if (!fresh) return;
  status.hidden = false;
  status.textContent = 'Polishing the gold…';
  try {
    await viewer.apply({ ...v, modelUrl: v.model ? new URL(v.model, document.baseURI).href : '' }, { animate: true });
    status.hidden = true;
    sync();
  } catch (err) {
    console.error(err);
    status.textContent = 'The 3D model could not be loaded. Please try again later.';
  }
}
