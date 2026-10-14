/* Mangalam Jewellers — the 360° view editor (app/views/admin/partials/spin-editor.html), in the product editor and
 * in the drawer of Catalogue › 3D jewellery.
 *
 * The frames are made here, in the browser: from a turntable video — which never leaves the computer; frames are
 * taken at even steps between the start and the end of the turn the team chooses — or from a set of photos, in
 * file-name order. Each is cropped square around the piece (drag the first frame to centre it, "Closer" to fill
 * the frame), brightened if asked, and uploaded on its own (admin/api.php › spin.frame), so a long video never has
 * to travel. Whether it makes a full turn (and carries on round) or part of one (and swings back) is guessed from
 * whether the first and last frames match, and can be changed.
 *
 * The card keeps the result in one hidden field, spin (JSON), saved with the product (or by spin.save in the
 * drawer) and checked by spin_config() in app/lib/content.php. The live preview is the view visitors get
 * (assets/js/spin360.js). In the drawer the card is filled from a piece's Edit button (admin.js dispatches
 * admin:filled on the dialog), or from the product chosen when adding a 360° view.
 *
 * The cards of Catalogue › 3D jewellery › 360° views turn as the pointer moves across them (data-spin-scrub-card). */

const CSRF = document.querySelector('meta[name="csrf-token"]')?.content || '';
const clamp = (v, min, max) => Math.min(max, Math.max(min, v));
const parse = text => { try { return JSON.parse(text || 'null'); } catch (err) { return null; } };
const site = p => (/^(blob:|data:|https?:|\.\.\/)/.test(p) ? p : `../${p}`);
const iconHTML = name => `<svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><use href="#i-${name}"/></svg>`;
const SIDE = 1200; // the longest side the server keeps (SPIN_SIDE in app/lib/images.php)
const MIN_FRAMES = 8, MAX_FRAMES = 120;

const cards = [...document.querySelectorAll('[data-spin-card]')];
if (cards.length) {
  import(`./spin360.js?v=${cards[0].dataset.spinVersion || ''}`)
    .then(m => cards.forEach(card => init(card, m.createSpin)))
    .catch(err => { console.error(err); toast('The 360° editor could not start', 'Reload the page and try again.', true); });
}
scrubCards();

/* ---------- Talking to the server, and toasts as the rest of the admin shows them ---------- */
async function post(body) {
  try {
    const r = await fetch('api.php', { method: 'POST', body, credentials: 'same-origin', headers: { 'X-CSRF-Token': CSRF, Accept: 'application/json' } });
    return await r.json().catch(() => ({ ok: false, error: `The server answered in a way the admin did not understand (${r.status}).` }));
  } catch (err) {
    return { ok: false, error: 'Could not reach the server. Check the connection and try again.' };
  }
}
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

/* ---------- Pictures: videos, photos, crops ---------- */
const dims = img => [img.videoWidth || img.naturalWidth || img.width, img.videoHeight || img.naturalHeight || img.height];
const once = (el, ok) => new Promise((resolve, reject) => {
  el.addEventListener(ok, resolve, { once: true });
  el.addEventListener('error', () => reject(el.error || new Error('unreadable')), { once: true });
});
// Moves a video to a moment and waits until that frame can be drawn
function seekTo(video, t) {
  return new Promise(resolve => {
    const done = () => { video.removeEventListener('seeked', done); clearTimeout(timer); resolve(); };
    const timer = setTimeout(done, 5000);
    video.addEventListener('seeked', done);
    video.currentTime = clamp(t, 0, Math.max(0, video.duration - 0.001));
  });
}
// A photo, the right way up
async function picture(file) {
  if (window.createImageBitmap) {
    try { return await createImageBitmap(file, { imageOrientation: 'from-image' }); } catch (err) { /* read it below */ }
  }
  const img = new Image();
  img.src = URL.createObjectURL(file);
  try { await once(img, 'load'); } finally { URL.revokeObjectURL(img.src); }
  return img;
}
const toBlob = canvas => new Promise(resolve => canvas.toBlob(resolve, 'image/jpeg', 0.92));
// Brightness for browsers whose canvas has no filters
function brighten(ctx, size, k) {
  const data = ctx.getImageData(0, 0, size, size);
  const d = data.data;
  for (let i = 0; i < d.length; i += 4) { d[i] = d[i] * k; d[i + 1] = d[i + 1] * k; d[i + 2] = d[i + 2] * k; }
  ctx.putImageData(data, 0, 0);
}
const time = s => `${Math.floor(s / 60)}:${(s % 60).toFixed(1).padStart(4, '0')}`;

function init(card, createSpin) {
  const $ = s => card.querySelector(s);
  const $$ = s => [...card.querySelectorAll(s)];
  const form = card.closest('form'), dialog = card.closest('dialog');
  const field = $('[data-spin-value]');
  const stage = $('[data-spin-stage]');
  const strip = $('[data-spin-strip]');
  const scrub = $('[data-spin-scrub]');
  const fromCanvas = $('[data-spin-end="from"]'), toCanvas = $('[data-spin-end="to"]');

  const normal = raw => ({ enabled: true, loop: true, reverse: false, autoplay: true, seconds: 8, start: 0, source: 'video', video: '', ...(raw || {}) });
  let cfg = normal(null);
  // Each frame: { key, path (once uploaded), url (shown), blob (until uploaded), state: ready | uploading | failed }
  let frames = [], nextKey = 1;
  let product = null; // in the drawer: { name, slug }
  let view = null, busy = false, uploads = 0, set = '', stripShown = '';
  // The video or photos frames are being made from, and how they are cropped
  let source = null;
  const crop = { zoom: 1, x: 0.5, y: 0.5, light: 1 };
  const times = { from: 0, to: 1 };

  /* ---------- The settings and what they show ---------- */
  function load(raw, info) {
    product = info || null;
    for (const f of frames) if (f.url.startsWith('blob:')) URL.revokeObjectURL(f.url);
    cfg = normal(raw);
    if (!raw) cfg.enabled = true;
    frames = (raw?.frames || []).map(path => ({ key: nextKey++, path, url: site(path), state: 'ready' }));
    set = '';
    clearSource();
    render();
  }
  function value() {
    const done = frames.filter(f => f.path);
    if (!done.length) return '';
    const startKey = frames[cfg.start]?.key;
    const start = Math.max(0, done.findIndex(f => f.key === startKey));
    return JSON.stringify({ ...cfg, start, frames: done.map(f => f.path) });
  }
  function render() {
    cfg.start = clamp(cfg.start, 0, Math.max(0, frames.length - 1));
    field.value = value();
    $('[data-spin-enabled]').checked = !!cfg.enabled;
    $$('[data-spin-loop]').forEach(r => { r.checked = r.value === (cfg.loop ? '1' : '0'); });
    $('[data-spin-autoplay]').checked = !!cfg.autoplay;
    $('[data-spin-reverse]').checked = !!cfg.reverse;
    $('[data-spin-seconds]').value = cfg.seconds;
    $('[data-spin-out="seconds"]').textContent = `${cfg.seconds} s`;
    $('[data-spin-seconds-label]').textContent = cfg.loop ? 'One turn takes' : 'One swing takes';
    $('[data-spin-seconds-field]').hidden = !cfg.autoplay;
    card.classList.toggle('is-off', !cfg.enabled);
    $('[data-spin-count-text]').textContent = frames.length === 1 ? '1 frame' : `${frames.length} frames`;
    $('[data-spin-frames-field]').hidden = !frames.length;
    $('[data-spin-empty]').hidden = frames.length > 0;
    $('[data-spin-scrub-row]').hidden = frames.length < 2;
    renderStrip();
    preview();
    summary();
  }
  function summary() {
    const n = frames.length, note = $('[data-spin-summary]');
    if (!n) { note.textContent = 'Make the frames first: from a turntable video, or a set of photos taken all the way round.'; return; }
    const how = `Visitors see ${n} frames ${cfg.loop ? 'turning all the way round' : 'swinging back and forth'}${cfg.autoplay ? `, ${cfg.loop ? 'once' : 'one swing'} every ${cfg.seconds} seconds until they drag it` : ' as they drag it'}, and can look closer.`;
    const warn = n < MIN_FRAMES ? ` Add at least ${MIN_FRAMES} frames for it to show.` : !cfg.enabled ? ' It is switched off, so the product page does not show it yet.' : '';
    note.textContent = how + warn;
  }

  /* ---------- The frames, in turning order ---------- */
  function renderStrip() {
    const shown = frames.map(f => `${f.key}:${f.state}`).join(',') + `|${cfg.start}`;
    if (shown === stripShown) return;
    stripShown = shown;
    strip.replaceChildren(...frames.map((f, i) => {
      const li = document.createElement('li');
      li.className = `spin-thumb${i === cfg.start ? ' is-start' : ''}${f.state !== 'ready' ? ` is-${f.state}` : ''}`;
      li.dataset.sortItem = '';
      li.draggable = true;
      li.dataset.key = f.key;
      li.innerHTML = `<img alt="" draggable="false" loading="lazy"><span class="spin-thumb__n" data-position>${i + 1}</span>`
        + `<button type="button" class="spin-thumb__remove" data-spin-remove aria-label="Leave out frame ${i + 1}">${iconHTML('x')}</button>`;
      li.querySelector('img').src = f.url;
      return li;
    }));
  }
  strip.addEventListener('click', e => {
    const li = e.target.closest('.spin-thumb');
    if (!li) return;
    const i = frames.findIndex(f => String(f.key) === li.dataset.key);
    if (e.target.closest('[data-spin-remove]')) {
      const [gone] = frames.splice(i, 1);
      if (gone.url.startsWith('blob:')) URL.revokeObjectURL(gone.url);
      if (i < cfg.start || cfg.start >= frames.length) cfg.start = Math.max(0, cfg.start - 1);
      render();
      return;
    }
    view?.pause();
    view?.go(i);
  });
  // admin.js moves the items when they are dragged; the frames follow, and the first one shown stays the same
  strip.addEventListener('admin:reordered', () => {
    const startKey = frames[cfg.start]?.key;
    const order = [...strip.children].map(li => li.dataset.key);
    frames.sort((a, b) => order.indexOf(String(a.key)) - order.indexOf(String(b.key)));
    cfg.start = Math.max(0, frames.findIndex(f => f.key === startKey));
    render();
  });
  $('[data-spin-reverse-order]').addEventListener('click', () => {
    const startKey = frames[cfg.start]?.key;
    frames.reverse();
    cfg.start = Math.max(0, frames.findIndex(f => f.key === startKey));
    render();
  });
  $('[data-spin-clear]').addEventListener('click', () => {
    for (const f of frames) if (f.url.startsWith('blob:')) URL.revokeObjectURL(f.url);
    frames = [];
    cfg.start = 0;
    render();
    toast('Every frame removed', 'Nothing is deleted from the website until you save.');
  });

  /* ---------- The live preview ---------- */
  function preview() {
    if (!frames.length) { view?.destroy(); view = null; return; }
    const opts = { frames: frames.map(f => f.url), loop: cfg.loop, reverse: cfg.reverse, autoplay: cfg.autoplay, seconds: cfg.seconds, start: cfg.start };
    if (!view) {
      view = createSpin(stage, opts, {
        label: product?.name || form?.querySelector('[name="name"]')?.value || 'The piece',
        hint: false,
        onFrame: i => { scrub.value = i + 1; $('[data-spin-scrub-out]').textContent = `${i + 1} / ${frames.length}`; },
      });
    } else view.set(opts);
    scrub.max = String(Math.max(2, frames.length));
    $('[data-spin-scrub-out]').textContent = `${(view.frame || 0) + 1} / ${frames.length}`;
  }
  scrub.addEventListener('input', () => { if (!view) return; view.pause(); view.go(Number(scrub.value) - 1); });
  $('[data-spin-set-start]').addEventListener('click', () => {
    if (!view) return;
    cfg.start = view.frame;
    render();
    toast(`Frame ${cfg.start + 1} shows first`, 'Visitors see it first, and the turn starts from it.');
  });

  /* ---------- How it turns ---------- */
  $('[data-spin-enabled]').addEventListener('change', e => { cfg.enabled = e.target.checked; render(); });
  $$('[data-spin-loop]').forEach(r => r.addEventListener('change', () => { if (r.checked) { cfg.loop = r.value === '1'; render(); } }));
  $('[data-spin-autoplay]').addEventListener('change', e => {
    cfg.autoplay = e.target.checked;
    render();
    if (cfg.autoplay) view?.play(); else view?.pause();
  });
  $('[data-spin-seconds]').addEventListener('input', e => { cfg.seconds = Number(e.target.value) || 8; render(); });
  $('[data-spin-reverse]').addEventListener('change', e => { cfg.reverse = e.target.checked; render(); });

  /* ---------- Where the frames come from: a video or photos ---------- */
  const kind = () => $$('[data-spin-source]').find(r => r.checked)?.value || 'video';
  const count = () => Number($$('[data-spin-count]').find(r => r.checked)?.value || 36);
  function showDrops() {
    for (const k of ['video', 'photos']) $(`[data-spin-drop="${k}"]`).hidden = !!source || kind() !== k;
  }
  $$('[data-spin-source]').forEach(r => r.addEventListener('change', () => { clearSource(); }));
  $$('[data-spin-count]').forEach(r => r.addEventListener('change', makeLabel));
  for (const k of ['video', 'photos']) {
    const zone = $(`[data-spin-drop="${k}"]`), input = $(`[data-spin-file="${k}"]`);
    ['dragenter', 'dragover'].forEach(type => zone.addEventListener(type, () => zone.classList.add('is-over')));
    ['dragleave', 'drop'].forEach(type => zone.addEventListener(type, () => zone.classList.remove('is-over')));
    input.addEventListener('change', () => {
      if (!input.files.length) return;
      if (k === 'video') openVideo(input.files[0]); else openPhotos(input.files);
      input.value = '';
    });
  }
  $('[data-spin-discard]').addEventListener('click', clearSource);

  function clearSource() {
    if (source?.kind === 'video') {
      for (const v of [source.a, source.b]) { v.removeAttribute('src'); v.load(); }
      URL.revokeObjectURL(source.url);
    }
    for (const p of [source?.first, source?.last]) p?.close?.();
    source = null;
    $('[data-spin-framing]').hidden = true;
    showDrops();
  }

  async function openVideo(file) {
    clearSource();
    const url = URL.createObjectURL(file);
    const make = () => Object.assign(document.createElement('video'), { muted: true, playsInline: true, preload: 'auto', src: url });
    const a = make(), b = make();
    try {
      await Promise.all([once(a, 'loadeddata'), once(b, 'loadeddata')]);
      if (!a.videoWidth || !Number.isFinite(a.duration) || a.duration < 0.5) throw new Error('no picture');
    } catch (err) {
      for (const v of [a, b]) { v.removeAttribute('src'); v.load(); }
      URL.revokeObjectURL(url);
      toast(`“${file.name}” could not be opened`, 'This browser cannot read that video. Export it as MP4 (H.264) — most phones and cameras can — and try again.', true);
      return;
    }
    source = { kind: 'video', name: file.name, url, a, b, w: a.videoWidth, h: a.videoHeight, duration: a.duration };
    times.from = 0;
    times.to = a.duration;
    for (const end of ['from', 'to']) {
      const range = $(`[data-spin-time="${end}"]`);
      range.max = String(a.duration);
      range.step = String(Math.max(0.01, a.duration / 1000));
      range.value = String(times[end]);
    }
    $('[data-spin-source-name]').textContent = file.name;
    $('[data-spin-source-meta]').textContent = `· ${source.w} × ${source.h} · ${time(a.duration)} · stays on this computer`;
    startFraming();
    await Promise.all([seekEnd('from'), seekEnd('to')]);
  }

  async function openPhotos(list) {
    let files = [...list].filter(f => /^image\//.test(f.type)).sort((x, y) => x.name.localeCompare(y.name, undefined, { numeric: true }));
    if (files.length < MIN_FRAMES) {
      toast(`At least ${MIN_FRAMES} photos are needed`, 'Choose every photo of the turn at once — 24 to 72 work best.', true);
      return;
    }
    if (files.length > MAX_FRAMES) {
      toast(`Only the first ${MAX_FRAMES} photos are used`, 'Fewer photos load faster; 36 to 72 are plenty.');
      files = files.slice(0, MAX_FRAMES);
    }
    clearSource();
    try {
      const [first, last] = await Promise.all([picture(files[0]), picture(files[files.length - 1])]);
      const [w, h] = dims(first);
      source = { kind: 'photos', name: `${files.length} photos`, files, first, last, w, h };
    } catch (err) {
      toast('The photos could not be opened', 'Use JPEG or PNG photos.', true);
      return;
    }
    $('[data-spin-source-name]').textContent = source.name;
    $('[data-spin-source-meta]').textContent = `· ${files[0].name} to ${files[files.length - 1].name} · ${source.w} × ${source.h}`;
    startFraming();
    paintEnds();
  }

  function startFraming() {
    Object.assign(crop, { zoom: 1, x: 0.5, y: 0.5, light: 1 });
    $('[data-spin-crop="zoom"]').value = '1';
    $('[data-spin-crop="light"]').value = '1';
    $('[data-spin-framing]').hidden = false;
    $$('[data-spin-video-only]').forEach(el => { el.hidden = source.kind !== 'video'; });
    showDrops();
    outputs();
    makeLabel();
  }
  function makeLabel() {
    $('[data-spin-make-label]').textContent = source?.kind === 'photos' ? `Use these ${source.files.length} photos` : `Make ${count()} frames`;
  }
  function outputs() {
    $('[data-spin-out="zoom"]').textContent = `×${crop.zoom.toFixed(2).replace(/\.?0+$/, '')}`;
    const pct = Math.round((crop.light - 1) * 100);
    $('[data-spin-out="light"]').textContent = pct ? `${pct > 0 ? '+' : ''}${pct}%` : 'as shot';
    $('[data-spin-out="from"]').textContent = time(times.from);
    $('[data-spin-out="to"]').textContent = time(times.to);
  }

  // The square taken from each picture: the zoom sets its size, the drag its centre
  function region(sw, sh) {
    const s = Math.min(sw, sh) / crop.zoom;
    return { s, x: clamp(crop.x * sw, s / 2, sw - s / 2) - s / 2, y: clamp(crop.y * sh, s / 2, sh - s / 2) - s / 2 };
  }
  function paint(ctx, img, size) {
    const [sw, sh] = dims(img);
    const r = region(sw, sh);
    const filter = 'filter' in ctx;
    ctx.clearRect(0, 0, size, size);
    if (filter) ctx.filter = crop.light !== 1 ? `brightness(${crop.light})` : 'none';
    ctx.drawImage(img, r.x, r.y, r.s, r.s, 0, 0, size, size);
    if (filter) ctx.filter = 'none';
    else if (crop.light !== 1) brighten(ctx, size, crop.light);
  }
  function endPicture(end) {
    if (!source) return null;
    if (source.kind === 'video') return end === 'from' ? source.a : source.b;
    return end === 'from' ? source.first : source.last;
  }
  function paintEnd(end) {
    const canvas = end === 'from' ? fromCanvas : toCanvas, img = endPicture(end);
    if (!img) return;
    const size = Math.max(120, Math.round(canvas.clientWidth * Math.min(2, window.devicePixelRatio || 1)));
    if (canvas.width !== size) canvas.width = canvas.height = size;
    paint(canvas.getContext('2d'), img, size);
  }
  function paintEnds() { paintEnd('from'); paintEnd('to'); }
  // Seeks one end of the turn, keeping only the latest moment asked for while a seek is under way
  const seeking = { from: null, to: null }, wanted = { from: null, to: null };
  function seekEnd(end) {
    if (source?.kind !== 'video') return Promise.resolve();
    wanted[end] = times[end];
    if (seeking[end]) return seeking[end];
    seeking[end] = (async () => {
      while (wanted[end] !== null && source?.kind === 'video') {
        const t = wanted[end];
        wanted[end] = null;
        await seekTo(endPicture(end), t);
        paintEnd(end);
      }
      seeking[end] = null;
    })();
    return seeking[end];
  }
  for (const end of ['from', 'to']) {
    $(`[data-spin-time="${end}"]`).addEventListener('input', e => {
      if (!source || source.kind !== 'video') return;
      const gap = Math.min(0.5, source.duration / 4);
      times[end] = Number(e.target.value);
      // The turn keeps a little length: moving one end past the other pushes it along
      const other = end === 'from' ? 'to' : 'from';
      if (end === 'from' && times.to - times.from < gap) times.to = Math.min(source.duration, times.from + gap);
      if (end === 'to' && times.to - times.from < gap) times.from = Math.max(0, times.to - gap);
      $(`[data-spin-time="${other}"]`).value = String(times[other]);
      outputs();
      seekEnd(end);
      seekEnd(other);
    });
  }
  for (const key of ['zoom', 'light']) {
    $(`[data-spin-crop="${key}"]`).addEventListener('input', e => { crop[key] = Number(e.target.value); outputs(); paintEnds(); });
  }
  // Drag the first frame to centre the piece; the wheel comes closer
  let panning = null;
  fromCanvas.addEventListener('pointerdown', e => {
    if (!source) return;
    fromCanvas.setPointerCapture(e.pointerId);
    panning = { x: e.clientX, y: e.clientY, cx: crop.x, cy: crop.y };
  });
  fromCanvas.addEventListener('pointermove', e => {
    if (!panning || !source) return;
    const { s } = region(source.w, source.h);
    const k = s / fromCanvas.clientWidth;
    const half = s / 2;
    crop.x = clamp(panning.cx - ((e.clientX - panning.x) * k) / source.w, half / source.w, 1 - half / source.w);
    crop.y = clamp(panning.cy - ((e.clientY - panning.y) * k) / source.h, half / source.h, 1 - half / source.h);
    paintEnds();
  });
  const endPan = () => { panning = null; };
  fromCanvas.addEventListener('pointerup', endPan);
  fromCanvas.addEventListener('pointercancel', endPan);
  fromCanvas.addEventListener('wheel', e => {
    if (!source) return;
    e.preventDefault();
    crop.zoom = clamp(crop.zoom * (e.deltaY < 0 ? 1.06 : 1 / 1.06), 1, 3);
    $('[data-spin-crop="zoom"]').value = String(crop.zoom);
    outputs();
    paintEnds();
  }, { passive: false });

  // Do the first and last frames show the same view? Then they make a full turn.
  function endsMatch() {
    const S = 24, c = document.createElement('canvas');
    c.width = S;
    c.height = S * 2;
    const x = c.getContext('2d', { willReadFrequently: true });
    x.drawImage(fromCanvas, 0, 0, S, S);
    x.drawImage(toCanvas, 0, S, S, S);
    const d = x.getImageData(0, 0, S, S * 2).data, half = S * S * 4;
    let diff = 0;
    for (let i = 0; i < half; i += 4) diff += Math.abs(d[i] - d[half + i]) + Math.abs(d[i + 1] - d[half + i + 1]) + Math.abs(d[i + 2] - d[half + i + 2]);
    return diff / (S * S * 3) < 9;
  }

  /* ---------- Making the frames, then uploading them ---------- */
  const progressBox = $('[data-spin-progress]');
  function progress(done, total, text) {
    progressBox.hidden = false;
    $('[data-spin-progress-bar]').style.transform = `scaleX(${total ? done / total : 0})`;
    $('[data-spin-progress-text]').textContent = text;
  }
  function working(on) {
    busy = on;
    card.classList.toggle('is-working', on);
    $('[data-spin-make]').disabled = on;
    $('[data-spin-discard]').disabled = on;
  }
  $('[data-spin-make]').addEventListener('click', async () => {
    if (!source || busy) return;
    working(true);
    const loop = endsMatch();
    const n = source.kind === 'video' ? count() : source.files.length;
    const { s } = region(source.w, source.h);
    const size = Math.min(SIDE, Math.round(s));
    const canvas = document.createElement('canvas');
    canvas.width = canvas.height = size;
    const ctx = canvas.getContext('2d', { willReadFrequently: !('filter' in CanvasRenderingContext2D.prototype) });
    const blobs = [];
    try {
      for (let i = 0; i < n; i++) {
        if (source.kind === 'video') {
          progress(i, n, `Taking frame ${i + 1} of ${n} from the video…`);
          // A full turn leaves out its last moment, which is the first one again
          await seekTo(source.a, times.from + ((times.to - times.from) * i) / (loop ? n : n - 1));
          paint(ctx, source.a, size);
        } else {
          progress(i, n, `Cropping photo ${i + 1} of ${n}…`);
          const img = await picture(source.files[i]);
          paint(ctx, img, size);
          img.close?.();
        }
        blobs.push(await toBlob(canvas));
      }
    } catch (err) {
      console.error(err);
      toast('The frames could not be made', 'Try again, or choose another video or photos.', true);
      working(false);
      progressBox.hidden = true;
      return;
    }
    if (source.kind === 'video') seekEnd('from');
    // The new frames replace the old ones (deleted from the website once this is saved)
    for (const f of frames) if (f.url.startsWith('blob:')) URL.revokeObjectURL(f.url);
    frames = blobs.map(blob => ({ key: nextKey++, blob, url: URL.createObjectURL(blob), path: null, state: 'uploading' }));
    Object.assign(cfg, { loop, start: 0, source: source.kind, video: source.kind === 'video' ? source.name : '' });
    set = '';
    render();
    const ok = await upload();
    working(false);
    if (ok) {
      toast(`${frames.length} frames made`, `${loop ? 'The first and last frames match, so it turns all the way round' : 'The first and last frames differ, so it swings back and forth'} — change that under “How it turns”. ${form?.dataset.save === 'product.save' ? 'Save the product to keep them.' : 'Save to keep them.'}`);
      clearSource();
    }
  });

  // Uploads the frames not on the server yet, three at a time (the first alone: it makes their folder)
  async function upload() {
    const todo = frames.filter(f => f.state !== 'ready');
    if (!todo.length) return true;
    uploads++;
    let done = 0, failed = null;
    const named = product?.name || form?.querySelector('[name="name"]')?.value || 'spin';
    const one = async f => {
      f.state = 'uploading';
      const body = new FormData();
      body.append('action', 'spin.frame');
      body.append('set', set);
      body.append('named', named);
      body.append('index', String(frames.indexOf(f) + 1));
      body.append('file', f.blob, `frame-${frames.indexOf(f) + 1}.jpg`);
      const res = await post(body);
      if (!res || !res.ok) { f.state = 'failed'; failed = res?.error || 'Please try again.'; render(); return; }
      set = res.set;
      f.path = res.path;
      f.state = 'ready';
      delete f.blob;
      done++;
      progress(done, todo.length, `Uploading frame ${done} of ${todo.length}…`);
      render();
    };
    progress(0, todo.length, `Uploading frame 1 of ${todo.length}…`);
    await one(todo[0]);
    if (!failed) {
      const rest = todo.slice(1);
      let next = 0;
      await Promise.all([0, 1, 2].map(async () => { while (next < rest.length && !failed) await one(rest[next++]); }));
    }
    uploads--;
    progressBox.hidden = true;
    render();
    if (failed) {
      toast('Some frames were not uploaded', `${failed} Press “Upload the rest” to try again, or remove them.`, true);
      showRetry(true);
      return false;
    }
    showRetry(false);
    return true;
  }
  const retry = document.createElement('button');
  retry.type = 'button';
  retry.className = 'btn btn--outline btn--sm';
  retry.innerHTML = `${iconHTML('refresh-cw')} Upload the rest`;
  retry.hidden = true;
  $('[data-spin-frames-field] .row-actions').prepend(retry);
  function showRetry(on) { retry.hidden = !on; }
  retry.addEventListener('click', async () => {
    if (busy) return;
    working(true);
    if (await upload()) toast('Every frame uploaded', 'Save to keep them.');
    working(false);
  });

  // Saving waits for the frames
  form?.addEventListener('submit', e => {
    const why = uploads || busy ? ['The frames are still on their way', 'Save again once they have all uploaded.']
      : frames.some(f => f.state === 'failed') ? ['Some frames did not upload', 'Press “Upload the rest”, or remove them, then save.'] : null;
    if (!why) return;
    e.preventDefault();
    e.stopImmediatePropagation();
    toast(why[0], why[1], true);
  }, true);

  /* ---------- In the 3D jewellery drawer: a piece opened from its Edit button, or a product chosen to add ---------- */
  if (dialog) {
    dialog.addEventListener('admin:filled', e => {
      const d = e.detail || {};
      const pick = dialog.querySelector('[data-spin-pick-field]');
      if (pick) pick.hidden = !!d.id; // a new 360° view starts by choosing its product
      load(typeof d.spin === 'string' ? parse(d.spin) : d.spin || null, d.product || null);
    });
    dialog.addEventListener('close', () => { view?.pause(); clearSource(); });
    const picker = dialog.querySelector('[data-spin-product]');
    if (picker) picker.addEventListener('change', () => { product = parse(picker.selectedOptions[0]?.dataset.product) || null; });
  }

  load(parse(field.value), null);
}

/* ---------- 360° view cards: they turn as the pointer moves across them ---------- */
function scrubCards() {
  document.querySelectorAll('[data-spin-scrub-card]').forEach(media => {
    const frames = parse(media.dataset.frames) || [];
    const img = media.querySelector('img');
    const start = Number(media.dataset.start) || 0;
    if (frames.length < 2 || !img) return;
    let loaded = false;
    media.addEventListener('pointerenter', () => {
      if (loaded) return;
      loaded = true;
      frames.forEach(f => { new Image().src = site(f); });
    });
    media.addEventListener('pointermove', e => {
      const r = media.getBoundingClientRect();
      const i = Math.floor(clamp((e.clientX - r.left) / r.width, 0, 0.999) * frames.length);
      img.src = site(frames[(start + i) % frames.length]);
    });
    media.addEventListener('pointerleave', () => { img.src = site(frames[start]); });
  });
}
