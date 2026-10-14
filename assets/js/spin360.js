/* Mangalam Jewellers — the 360° view: the piece itself, photographed as it turns. Its frames are made in the admin
 * from a turntable video or a set of photos (Catalogue › 3D jewellery › 360° views, or the product editor) and
 * products.spin lists them (spin_config() in app/lib/content.php).
 *
 * On the product page it takes the place of the main photograph (main.js imports this module the first time a
 * visitor opens it); the admin's 360° editor (assets/js/admin-spin.js) uses it for its live preview.
 *
 * Drag or swipe to turn it (it glides on a little after a flick), the arrow keys step a frame, double-click or the
 * zoom button looks closer — then the pointer (or a drag, on touch screens) moves around the piece. It turns by
 * itself until the visitor takes over, and only while it is on screen. A full turn carries on round; part of a turn
 * swings back at its ends. Frames load coarse to fine, so it can be turned long before the last one arrives. */

const ICONS = {
  play: '<path d="M7 4.5v15l12.5-7.5z"/>',
  pause: '<rect x="6" y="4" width="4" height="16" rx="1"/><rect x="14" y="4" width="4" height="16" rx="1"/>',
  zoom: '<circle cx="11" cy="11" r="7.5"/><path d="m21 21-4.6-4.6"/><path d="M11 8v6M8 11h6"/>',
  unzoom: '<circle cx="11" cy="11" r="7.5"/><path d="m21 21-4.6-4.6"/><path d="M8 11h6"/>',
  full: '<path d="M8 3H5a2 2 0 0 0-2 2v3"/><path d="M21 8V5a2 2 0 0 0-2-2h-3"/><path d="M3 16v3a2 2 0 0 0 2 2h3"/><path d="M16 21h3a2 2 0 0 0 2-2v-3"/>',
  unfull: '<path d="M8 3v3a2 2 0 0 1-2 2H3"/><path d="M21 8h-3a2 2 0 0 1-2-2V3"/><path d="M3 16h3a2 2 0 0 1 2 2v3"/><path d="M16 21v-3a2 2 0 0 1 2-2h3"/>',
  turn: '<path d="M16.5 7.5C15.6 4.2 14 2 12 2 9.2 2 7 6.5 7 12s2.2 10 5 10c.3 0 .7-.1 1-.2"/><path d="m15.2 13.7 3.8 1.9-1.9 3.8"/><path d="M19 15.6c-1.8.9-4.3 1.4-7 1.4-5.5 0-10-2.2-10-5s4.5-5 10-5c4.8 0 8.9 1.7 9.8 4"/>',
};
const svg = name => `<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${ICONS[name]}</svg>`;

// The view's own styles, shared by the website and the admin (both define the colour and font tokens used here);
// where it sits on each page is up to mangalam.css and admin.css
const CSS = `
.spin { position: absolute; inset: 0; overflow: hidden; background: radial-gradient(120% 95% at 50% 45%, #2a0b16 0%, #14040a 62%, #0b0205 100%); -webkit-user-select: none; user-select: none; }
.spin [hidden] { display: none !important; }
.spin__canvas { position: absolute; inset: 0; display: block; width: 100%; height: 100%; outline: none; cursor: grab; touch-action: pan-y; }
.spin.is-dragging .spin__canvas { cursor: grabbing; }
.spin.is-zoomed .spin__canvas { cursor: move; touch-action: none; }
.spin__canvas:focus-visible { box-shadow: inset 0 0 0 2px var(--gold-soft, #E3C77A); }
.spin__status { position: absolute; inset: 0; display: grid; place-content: center; justify-items: center; gap: 14px; padding: 24px; margin: 0; font-family: var(--font-display, serif); font-size: 19px; font-style: italic; text-align: center; color: var(--gold-pale, #F3E6C2); pointer-events: none; }
.spin__ring { perspective: 200px; }
.spin__ring .icon { width: 38px; height: 38px; color: var(--gold-soft, #E3C77A); animation: spinTurn 1.8s linear infinite; }
.spin__hint { position: absolute; left: 50%; bottom: 18px; display: inline-flex; align-items: center; gap: 8px; margin: 0; padding: 8px 14px; border-radius: 99px; background: rgba(20, 4, 10, 0.62); color: var(--gold-pale, #F3E6C2); font-size: 10.5px; font-weight: 500; letter-spacing: 0.2em; text-transform: uppercase; white-space: nowrap; pointer-events: none; -webkit-backdrop-filter: blur(6px); backdrop-filter: blur(6px); transform: translateX(-50%); animation: spinHint 2.6s ease-in-out infinite; }
.spin__hint .icon { width: 16px; height: 16px; color: var(--gold-soft, #E3C77A); }
.spin__tools { position: absolute; left: 14px; bottom: 14px; display: flex; gap: 8px; }
.spin__tool { display: grid; place-items: center; width: 40px; height: 40px; padding: 0; border: 0; border-radius: 50%; background: rgba(20, 4, 10, 0.62); color: var(--gold-pale, #F3E6C2); cursor: pointer; -webkit-backdrop-filter: blur(6px); backdrop-filter: blur(6px); transition: background-color 0.2s, transform 0.2s; }
.spin__tool:hover { background: rgba(20, 4, 10, 0.88); transform: translateY(-1px); }
.spin__tool:focus-visible { outline: 2px solid var(--gold-soft, #E3C77A); outline-offset: 2px; }
.spin__tool .icon { width: 18px; height: 18px; }
.spin__bar { position: absolute; left: 0; right: 0; bottom: 0; height: 2px; background: rgba(255, 255, 255, 0.08); pointer-events: none; transition: opacity 0.6s 0.5s; }
.spin__bar > span { display: block; height: 100%; background: var(--gold-soft, #E3C77A); transform: scaleX(0); transform-origin: left; transition: transform 0.3s; }
.spin__bar.is-done { opacity: 0; }
.spin.is-full { background: #0b0205; }
.spin.is-full .spin__tools { top: auto; left: 24px; bottom: 24px; }
@keyframes spinTurn { to { transform: rotateY(360deg); } }
@keyframes spinHint { 0%, 100% { transform: translateX(-50%); } 50% { transform: translateX(calc(-50% + 7px)); } }
@media (prefers-reduced-motion: reduce) { .spin__hint { animation: none; } }`;
function addStyles() {
  if (document.getElementById('spin360-styles')) return;
  const style = document.createElement('style');
  style.id = 'spin360-styles';
  style.textContent = CSS;
  document.head.append(style);
}
const ZOOM = 2.2;
const FRICTION = 0.93; // how quickly a flick slows down, per frame drawn

/**
 * Puts a 360° view into `host` (an element the view fills).
 * @param {HTMLElement} host
 * @param {object} cfg   { frames: [urls], loop, reverse, autoplay, seconds, start }
 * @param {object} opts  label: what the piece is called; controls: show the play / zoom / full-screen buttons;
 *                       hint: show "Drag to turn" until the first drag; onFrame(i): told each time another frame shows
 * @returns the view: go(i), play(), pause(), set(cfg), frame, playing, destroy()
 */
export function createSpin(host, cfg, opts = {}) {
  addStyles();
  const el = document.createElement('div');
  el.className = 'spin';
  el.innerHTML = `
    <canvas class="spin__canvas" tabindex="0" role="slider" aria-roledescription="360° view"></canvas>
    <div class="spin__status" data-spin-status role="status"><span class="spin__ring" aria-hidden="true">${svg('turn')}</span><span data-spin-status-text>Loading the 360° view…</span></div>
    <p class="spin__hint" data-spin-hint hidden>${svg('turn')}<span>Drag to turn</span></p>
    <div class="spin__tools" role="toolbar" aria-label="360° view" hidden>
      <button type="button" class="spin__tool" data-spin-tool="play" aria-label="Pause turning" title="Pause turning">${svg('pause')}</button>
      <button type="button" class="spin__tool" data-spin-tool="zoom" aria-label="Look closer" title="Look closer">${svg('zoom')}</button>
      <button type="button" class="spin__tool" data-spin-tool="full" aria-label="Full screen" title="Full screen" hidden>${svg('full')}</button>
    </div>
    <span class="spin__bar" aria-hidden="true"><span data-spin-bar></span></span>`;
  host.append(el);
  const canvas = el.querySelector('canvas');
  const ctx = canvas.getContext('2d');
  const status = el.querySelector('[data-spin-status]');
  const hint = el.querySelector('[data-spin-hint]');
  const tools = el.querySelector('.spin__tools');
  const bar = el.querySelector('[data-spin-bar]');
  const btn = name => el.querySelector(`[data-spin-tool="${name}"]`);
  canvas.setAttribute('aria-label', `${opts.label || 'The piece'} — 360° view. Drag to turn it, or use the arrow keys.`);
  tools.hidden = opts.controls === false;
  if (document.fullscreenEnabled && opts.controls !== false) btn('full').hidden = false;

  let c = {}, frames = [], pos = 0, shown = -1, dir = 1, playing = false, velocity = 0, zoomed = false;
  let pan = { x: 0.5, y: 0.5 }, drag = null, raf = 0, last = 0, visible = true, destroyed = false, touched = false, loadToken = 0;
  const n = () => frames.length;
  const wrap = i => ((Math.round(i) % n()) + n()) % n();
  const index = () => (c.loop ? wrap(pos) : Math.max(0, Math.min(n() - 1, Math.round(pos))));

  /* ---------- Frames: loaded coarse to fine, from the first one shown ---------- */
  function order(count, from) {
    const seen = new Set([0]), out = [0];
    for (let step = 2 ** Math.floor(Math.log2(Math.max(1, count))); step >= 1; step /= 2) {
      for (let i = 0; i < count; i += step) if (!seen.has(i)) { seen.add(i); out.push(i); }
    }
    return out.map(i => (i + from) % count);
  }
  function load() {
    const token = ++loadToken;
    const queue = order(n(), c.start || 0);
    let done = 0, active = 0;
    const next = () => {
      if (token !== loadToken || destroyed) return;
      while (active < 6 && queue.length) {
        const f = frames[queue.shift()];
        if (f.ready) { done++; continue; }
        active++;
        const img = new Image();
        img.decoding = 'async';
        const finish = ok => {
          if (token !== loadToken) return;
          active--; done++;
          if (ok) { f.img = img; f.ready = true; }
          progress(done);
          draw(true);
          next();
        };
        img.onload = () => (img.decode ? img.decode().catch(() => {}) : Promise.resolve()).then(() => finish(true));
        img.onerror = () => finish(false);
        img.src = f.src;
      }
      progress(done);
    };
    next();
  }
  function progress(done) {
    const share = n() ? done / n() : 1;
    bar.style.transform = `scaleX(${share})`;
    bar.parentElement.classList.toggle('is-done', share >= 1);
    const any = frames.some(f => f.ready);
    if (any && !status.hidden) {
      status.hidden = true;
      if (opts.hint !== false && !touched) hint.hidden = false;
      start();
    } else if (!any) {
      el.querySelector('[data-spin-status-text]').textContent = done >= n() && n() ? 'The 360° view could not be loaded.' : `Loading the 360° view… ${Math.round(share * 100)}%`;
    }
  }
  // The frame wanted, or the nearest one that has arrived
  function nearest(i) {
    for (let d = 0; d < n(); d++) {
      for (const j of [i + d, i - d]) {
        const k = c.loop ? ((j % n()) + n()) % n() : j;
        if (k >= 0 && k < n() && frames[k].ready) return frames[k];
      }
    }
    return null;
  }

  /* ---------- Drawing ---------- */
  function size() {
    const dpr = Math.min(window.devicePixelRatio || 1, 2);
    const w = Math.round(canvas.clientWidth * dpr), h = Math.round(canvas.clientHeight * dpr);
    if (w && h && (canvas.width !== w || canvas.height !== h)) { canvas.width = w; canvas.height = h; draw(true); }
  }
  function draw(force) {
    const i = index();
    if (!n() || (!force && i === shown)) return;
    const f = nearest(i);
    if (!f) return;
    const cw = canvas.width, ch = canvas.height, iw = f.img.naturalWidth, ih = f.img.naturalHeight;
    if (!cw || !ch) return;
    // Fills the stage (contain in full screen, where the stage is not square)
    const full = document.fullscreenElement === el;
    let k = full ? Math.min(cw / iw, ch / ih) : Math.max(cw / iw, ch / ih);
    if (zoomed) k *= ZOOM;
    const dw = iw * k, dh = ih * k;
    const dx = dw > cw ? (cw - dw) * pan.x : (cw - dw) / 2;
    const dy = dh > ch ? (ch - dh) * pan.y : (ch - dh) / 2;
    ctx.clearRect(0, 0, cw, ch);
    ctx.imageSmoothingQuality = 'high';
    ctx.drawImage(f.img, dx, dy, dw, dh);
    if (i !== shown) {
      shown = i;
      canvas.setAttribute('aria-valuenow', String(i + 1));
      canvas.setAttribute('aria-valuetext', `Frame ${i + 1} of ${n()}`);
      opts.onFrame?.(i);
    }
  }

  /* ---------- Turning by itself, and gliding on after a flick ---------- */
  function tick(t) {
    raf = 0;
    if (destroyed) return;
    const dt = last ? Math.min(64, t - last) : 16;
    last = t;
    if (drag) { /* the visitor is turning it */ }
    else if (Math.abs(velocity) > 0.002) {
      move(velocity * dt);
      velocity *= FRICTION ** (dt / 16);
    } else if (playing && visible && !zoomed) {
      move(dir * dt * n() / (c.seconds * 1000));
    }
    draw();
    if (drag || Math.abs(velocity) > 0.002 || (playing && visible && !zoomed)) raf = requestAnimationFrame(tick);
    else last = 0;
  }
  function wake() { if (!raf && !destroyed) raf = requestAnimationFrame(tick); }
  function move(by) {
    pos += by;
    if (c.loop) { pos = ((pos % n()) + n()) % n(); return; }
    // Part of a turn: it swings back at the ends
    if (pos > n() - 1) { pos = n() - 1; dir = -1; velocity = 0; }
    if (pos < 0) { pos = 0; dir = 1; velocity = 0; }
  }
  function start() {
    if (c.autoplay && !touched) setPlaying(true);
    else draw(true);
  }
  function setPlaying(on) {
    playing = on && n() > 1;
    const b = btn('play');
    b.innerHTML = svg(playing ? 'pause' : 'play');
    b.setAttribute('aria-label', playing ? 'Pause turning' : 'Turn by itself');
    b.title = b.getAttribute('aria-label');
    if (playing) wake();
  }
  function takeOver() {
    touched = true;
    hint.hidden = true;
    if (playing) setPlaying(false);
  }

  /* ---------- Dragging, flicking, zooming ---------- */
  const sign = () => (c.reverse ? 1 : -1);
  function framesPerPixel() {
    // A drag across the whole view turns it once round (or across all the frames of part of a turn)
    return n() / Math.max(200, canvas.clientWidth);
  }
  canvas.addEventListener('pointerdown', e => {
    if (e.button !== 0 || !n()) return;
    canvas.setPointerCapture(e.pointerId);
    takeOver();
    velocity = 0;
    drag = { x: e.clientX, y: e.clientY, pos, pan: { ...pan }, samples: [{ x: e.clientX, t: e.timeStamp }], moved: false, touch: e.pointerType !== 'mouse' };
    el.classList.add('is-dragging');
    wake();
  });
  canvas.addEventListener('pointermove', e => {
    if (zoomed && !drag && e.pointerType === 'mouse') {
      // Zoomed in: the piece follows the pointer
      const r = canvas.getBoundingClientRect();
      pan = { x: Math.max(0, Math.min(1, (e.clientX - r.left) / r.width)), y: Math.max(0, Math.min(1, (e.clientY - r.top) / r.height)) };
      draw(true);
      return;
    }
    if (!drag) return;
    const dx = e.clientX - drag.x, dy = e.clientY - drag.y;
    if (Math.abs(dx) > 3 || Math.abs(dy) > 3) drag.moved = true;
    if (zoomed) {
      const r = canvas.getBoundingClientRect();
      pan = { x: Math.max(0, Math.min(1, drag.pan.x - dx / r.width)), y: Math.max(0, Math.min(1, drag.pan.y - dy / r.height)) };
      draw(true);
      return;
    }
    pos = drag.pos;
    move(dx * framesPerPixel() * sign());
    drag.samples.push({ x: e.clientX, t: e.timeStamp });
    if (drag.samples.length > 6) drag.samples.shift();
    draw();
  });
  const release = e => {
    if (!drag) return;
    const d = drag;
    drag = null;
    el.classList.remove('is-dragging');
    if (!zoomed && d.moved) {
      // A flick glides on, slowing down
      const s = d.samples.filter(p => e.timeStamp - p.t < 120);
      if (s.length > 1) {
        const v = (s[s.length - 1].x - s[0].x) / Math.max(1, s[s.length - 1].t - s[0].t);
        velocity = Math.max(-0.25, Math.min(0.25, v * framesPerPixel() * sign()));
      }
    } else if (!d.moved && d.touch) {
      // Two taps in quick succession look closer (or step back)
      if (e.timeStamp - (release.tap || 0) < 320) { setZoom(!zoomed, e); release.tap = 0; } else release.tap = e.timeStamp;
    }
    wake();
  };
  canvas.addEventListener('pointerup', release);
  canvas.addEventListener('pointercancel', release);
  canvas.addEventListener('dblclick', e => setZoom(!zoomed, e));
  canvas.addEventListener('keydown', e => {
    const step = { ArrowLeft: -1, ArrowRight: 1, ArrowUp: 1, ArrowDown: -1 }[e.key];
    if (step) { e.preventDefault(); takeOver(); go(index() + step * (c.reverse ? -1 : 1)); }
    else if (e.key === 'Home' || e.key === 'End') { e.preventDefault(); takeOver(); go(e.key === 'Home' ? 0 : n() - 1); }
    else if (e.key === '+' || e.key === '=') setZoom(true);
    else if (e.key === '-' || e.key === 'Escape') { if (zoomed) { e.preventDefault(); setZoom(false); } }
    else if (e.key === ' ') { e.preventDefault(); touched = true; hint.hidden = true; setPlaying(!playing); }
  });
  function setZoom(on, e) {
    zoomed = on;
    if (on && e && e.clientX !== undefined) {
      const r = canvas.getBoundingClientRect();
      pan = { x: (e.clientX - r.left) / r.width, y: (e.clientY - r.top) / r.height };
    } else if (!on) pan = { x: 0.5, y: 0.5 };
    if (on) takeOver();
    el.classList.toggle('is-zoomed', on);
    const b = btn('zoom');
    b.innerHTML = svg(on ? 'unzoom' : 'zoom');
    b.setAttribute('aria-label', on ? 'Step back' : 'Look closer');
    b.title = b.getAttribute('aria-label');
    b.setAttribute('aria-pressed', String(on));
    draw(true);
    wake();
  }
  tools.addEventListener('click', e => {
    const b = e.target.closest('[data-spin-tool]');
    if (!b) return;
    const tool = b.dataset.spinTool;
    if (tool === 'play') { touched = true; hint.hidden = true; setPlaying(!playing); }
    else if (tool === 'zoom') setZoom(!zoomed);
    else if (document.fullscreenElement === el) document.exitFullscreen();
    else el.requestFullscreen?.().catch(() => {});
  });
  const onFull = () => {
    const full = document.fullscreenElement === el;
    el.classList.toggle('is-full', full);
    const b = btn('full');
    b.innerHTML = svg(full ? 'unfull' : 'full');
    b.setAttribute('aria-label', full ? 'Leave full screen' : 'Full screen');
    b.title = b.getAttribute('aria-label');
    requestAnimationFrame(size);
  };
  document.addEventListener('fullscreenchange', onFull);

  /* ---------- On screen or not ---------- */
  const ro = new ResizeObserver(size);
  ro.observe(canvas);
  const io = 'IntersectionObserver' in window ? new IntersectionObserver(entries => {
    visible = entries[entries.length - 1].isIntersecting && !document.hidden;
    if (visible) wake();
  }) : null;
  io?.observe(el);
  const onVisibility = () => { visible = !document.hidden; if (visible) wake(); };
  document.addEventListener('visibilitychange', onVisibility);

  /* ---------- What the page can ask of it ---------- */
  function set(next) {
    const oldSrcs = frames.map(f => f.src).join('\n');
    c = { loop: true, reverse: false, autoplay: true, seconds: 8, start: 0, ...next };
    c.seconds = Math.max(1, Number(c.seconds) || 8);
    const srcs = (c.frames || []).filter(Boolean);
    if (srcs.join('\n') !== oldSrcs) {
      const kept = new Map(frames.map(f => [f.src, f]));
      frames = srcs.map(src => kept.get(src) || { src, img: null, ready: false });
      pos = Math.max(0, Math.min(frames.length - 1, c.start || 0));
      shown = -1;
      canvas.setAttribute('aria-valuemin', '1');
      canvas.setAttribute('aria-valuemax', String(frames.length));
      if (!frames.some(f => f.ready)) { status.hidden = false; hint.hidden = true; }
      if (frames.length) load(); else { ctx.clearRect(0, 0, canvas.width, canvas.height); status.hidden = true; }
    }
    if (!c.loop) pos = Math.max(0, Math.min(n() - 1, pos));
    if (frames.some(f => f.ready)) { if (c.autoplay && !touched) setPlaying(true); else if (!c.autoplay) setPlaying(false); }
    draw(true);
  }
  function go(i) {
    if (!n()) return;
    velocity = 0;
    pos = c.loop ? ((i % n()) + n()) % n() : Math.max(0, Math.min(n() - 1, i));
    draw();
  }
  set(cfg);
  size();

  return {
    el,
    go,
    set,
    play: () => setPlaying(true),
    pause: () => { touched = true; setPlaying(false); },
    get frame() { return index(); },
    get playing() { return playing; },
    focus: () => canvas.focus({ preventScroll: true }),
    destroy() {
      destroyed = true;
      loadToken++;
      cancelAnimationFrame(raf);
      ro.disconnect();
      io?.disconnect();
      document.removeEventListener('fullscreenchange', onFull);
      document.removeEventListener('visibilitychange', onVisibility);
      if (document.fullscreenElement === el) document.exitFullscreen().catch(() => {});
      el.remove();
    },
  };
}
