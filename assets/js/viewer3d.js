/* Mangalam Jewellers — the 3D jewel viewer, shared by the website's product page (assets/js/product3d.js) and
 * the admin (assets/js/admin-3d.js: the product editor's 3D card and Catalogue › 3D jewellery).
 *
 * A piece is either one of the house designs, built in code — rings, kangans and bangles, earrings (a pair) and
 * pendants (on a chain), each in several styles — or a .glb model uploaded in the admin. Either way its metal and
 * stones can be changed: the designs are made of our own materials, and in a model the metallic materials become
 * the metal and the glass-like ones become stones. Every change plays a small show — the piece turns, light
 * sweeps across the metal, the stones flash and gold dust bursts from the stone. */
import * as THREE from 'three';
import { OrbitControls } from 'three/addons/controls/OrbitControls.js';
import { GLTFLoader } from 'three/addons/loaders/GLTFLoader.js';

const TAU = Math.PI * 2;
const reduceMotion = matchMedia('(prefers-reduced-motion: reduce)').matches;

/* ---------- 1. The choices (the ids are what products.view3d stores; view3d_config() in app/lib/content.php checks them) ---------- */
// width: what the width slider changes; size: what a visitor is asked for when "Ask for their size" is on
export const KINDS = [
  { id: 'ring', name: 'Ring', sub: 'Rings and bands', width: 'Band width', size: { label: 'Ring size', list: ['6', '7', '8', '9', '10', '11', '12', '13', '14', '15', '16', '17', '18', '19', '20', '21', '22', '23', '24'] } },
  { id: 'bangle', name: 'Kangan & bangle', sub: 'Kangans, kadas, bangles', width: 'Kangan width', size: { label: 'Bangle size', list: ['2.2', '2.4', '2.6', '2.8', '2.10'] } },
  { id: 'earring', name: 'Earrings', sub: 'Shown as a pair', width: 'Earring size', size: null },
  { id: 'pendant', name: 'Pendant', sub: 'On a fine chain', width: 'Pendant size', size: { label: 'Chain length', list: ['16 in', '18 in', '20 in', '22 in'] } },
];
export const DESIGNS = [
  { id: 'solitaire', kind: 'ring', name: 'Solitaire', sub: 'One stone, claw set', stone: true, prongs: true },
  { id: 'halo', kind: 'ring', name: 'Halo', sub: 'Ringed with small stones', stone: true, prongs: false },
  { id: 'trilogy', kind: 'ring', name: 'Trilogy', sub: 'Three stones', stone: true, prongs: true },
  { id: 'eternity', kind: 'ring', name: 'Eternity', sub: 'Stones all the way round', stone: true, prongs: false },
  { id: 'band', kind: 'ring', name: 'Plain band', sub: 'Wedding band', stone: false, prongs: false },
  { id: 'kada', kind: 'bangle', name: 'Plain kada', sub: 'Polished, beaded edges', stone: false, prongs: false },
  { id: 'diamond-kangan', kind: 'bangle', name: 'Diamond kangan', sub: 'Stones all the way round', stone: true, prongs: false },
  { id: 'kundan-kada', kind: 'bangle', name: 'Kundan kada', sub: 'Large stones in gold cups', stone: true, prongs: false },
  { id: 'twisted', kind: 'bangle', name: 'Twisted rope', sub: 'Two strands, twisted', stone: false, prongs: false },
  { id: 'cuff', kind: 'bangle', name: 'Open cuff', sub: 'Pavé, a stone at each end', stone: true, prongs: true },
  { id: 'stud', kind: 'earring', name: 'Solitaire studs', sub: 'One stone, claw set', stone: true, prongs: true },
  { id: 'halo-stud', kind: 'earring', name: 'Halo studs', sub: 'Ringed with small stones', stone: true, prongs: false },
  { id: 'drop', kind: 'earring', name: 'Drops', sub: 'An oval stone below a stud', stone: true, prongs: false },
  { id: 'hoop', kind: 'earring', name: 'Hoops', sub: 'Stones along the front', stone: true, prongs: false },
  { id: 'jhumka', kind: 'earring', name: 'Jhumkas', sub: 'A bell with hanging pearls', stone: true, prongs: false },
  { id: 'solitaire-pendant', kind: 'pendant', name: 'Solitaire', sub: 'One stone, claw set', stone: true, prongs: true },
  { id: 'halo-pendant', kind: 'pendant', name: 'Halo', sub: 'Ringed with small stones', stone: true, prongs: false },
  { id: 'heart', kind: 'pendant', name: 'Open heart', sub: 'Stones along a heart', stone: true, prongs: false },
  { id: 'drop-pendant', kind: 'pendant', name: 'Drop', sub: 'An oval stone, diamond above', stone: true, prongs: false },
];
export const designsOf = kind => DESIGNS.filter(d => d.kind === kind);
export const METALS = [
  { id: 'yellow', name: 'Yellow gold', sub: '22 karat', color: '#f5c362', rough: 0.13 },
  { id: 'rose', name: 'Rose gold', sub: '18 karat', color: '#f2ae95', rough: 0.14 },
  { id: 'white', name: 'White gold', sub: '18 karat', color: '#ecebe7', rough: 0.11 },
  { id: 'platinum', name: 'Platinum', sub: 'Pt 950', color: '#cdd2d8', rough: 0.2 },
];
export const STONES = [
  { id: 'diamond', name: 'Diamond', sub: 'Heera', color: '#ffffff', tint: 0, ior: 2.42, disp: 0.07 },
  { id: 'ruby', name: 'Ruby', sub: 'Manik', color: '#e3133f', tint: 1, ior: 1.77, disp: 0.025 },
  { id: 'emerald', name: 'Emerald', sub: 'Panna', color: '#12b15f', tint: 1, ior: 1.58, disp: 0.02 },
  { id: 'sapphire', name: 'Blue sapphire', sub: 'Neelam', color: '#1f4ce6', tint: 1, ior: 1.77, disp: 0.025 },
  { id: 'pukhraj', name: 'Yellow sapphire', sub: 'Pukhraj', color: '#ffb300', tint: 1, ior: 1.77, disp: 0.025 },
  { id: 'amethyst', name: 'Amethyst', sub: 'Jamunia', color: '#9b3fe3', tint: 1, ior: 1.55, disp: 0.02 },
];
export const DESIGN_ICONS = {
  solitaire: '<svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.3" stroke-linejoin="round"><circle cx="24" cy="32" r="11"/><path d="M18.5 11h11l3.5 4.5-9 8.5-9-8.5z"/><path d="M15 15.5h18"/></svg>',
  halo: '<svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.3" stroke-linejoin="round"><circle cx="24" cy="34" r="10"/><circle cx="24" cy="15" r="9" stroke-dasharray="2 2.6"/><path d="M20.5 11.5h7l2.5 3-6 5.5-6-5.5z"/></svg>',
  trilogy: '<svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.3" stroke-linejoin="round"><circle cx="24" cy="32" r="11"/><path d="M20 10h8l3 4-7 7-7-7z"/><path d="M8 16h6l2 3-5 4.5L6 19zM34 16h6l2 3-5 4.5-5-4.5z"/></svg>',
  eternity: '<svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.3"><circle cx="24" cy="24" r="14"/><circle cx="24" cy="24" r="10"/><g stroke-dasharray="1.6 3.2" stroke-width="3"><circle cx="24" cy="24" r="12"/></g></svg>',
  band: '<svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.3"><ellipse cx="24" cy="24" rx="15" ry="15"/><ellipse cx="24" cy="24" rx="11" ry="11"/><path d="M13 14c3-2 6-3 11-3" stroke-linecap="round" opacity=".6"/></svg>',
  kada: '<svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.3"><ellipse cx="24" cy="24" rx="18" ry="10"/><ellipse cx="24" cy="24" rx="15" ry="7.5"/><g stroke-dasharray="1 2.4" stroke-width="2"><ellipse cx="24" cy="24" rx="19.6" ry="11.4"/></g></svg>',
  'diamond-kangan': '<svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.3"><ellipse cx="24" cy="24" rx="18" ry="10"/><ellipse cx="24" cy="24" rx="14" ry="7"/><g stroke-dasharray="1.4 2.6" stroke-width="3"><ellipse cx="24" cy="24" rx="16" ry="8.5"/></g></svg>',
  'kundan-kada': '<svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.3"><ellipse cx="24" cy="24" rx="18" ry="10"/><ellipse cx="24" cy="24" rx="14" ry="7"/><circle cx="24" cy="32.5" r="2.6"/><circle cx="13" cy="30" r="2.2"/><circle cx="35" cy="30" r="2.2"/><circle cx="8" cy="24" r="1.8"/><circle cx="40" cy="24" r="1.8"/></svg>',
  twisted: '<svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.3"><path d="M6 24c3-6 6-6 9 0s6 6 9 0 6-6 9 0 6 6 9 0"/><path d="M6 24c3 6 6 6 9 0s6-6 9 0 6 6 9 0 6-6 9 0" opacity=".6"/><ellipse cx="24" cy="24" rx="19" ry="11" stroke-dasharray="2 3" opacity=".4"/></svg>',
  cuff: '<svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"><path d="M14 36a15 15 0 1 1 20 0"/><path d="M17 33a11 11 0 1 1 14 0"/><circle cx="15" cy="36.5" r="2.6"/><circle cx="33" cy="36.5" r="2.6"/></svg>',
  stud: '<svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.3" stroke-linejoin="round"><circle cx="14" cy="24" r="6.5"/><circle cx="34" cy="24" r="6.5"/><path d="M11 21h6l1.5 2.5-4.5 4-4.5-4zM31 21h6l1.5 2.5-4.5 4-4.5-4z"/></svg>',
  'halo-stud': '<svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.3"><circle cx="14" cy="24" r="3.4"/><circle cx="34" cy="24" r="3.4"/><circle cx="14" cy="24" r="7" stroke-dasharray="1.6 2"/><circle cx="34" cy="24" r="7" stroke-dasharray="1.6 2"/></svg>',
  drop: '<svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.3"><circle cx="14" cy="10" r="2.6"/><circle cx="34" cy="10" r="2.6"/><path d="M14 13v6M34 13v6"/><ellipse cx="14" cy="29" rx="5" ry="8"/><ellipse cx="34" cy="29" rx="5" ry="8"/></svg>',
  hoop: '<svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.3"><circle cx="14" cy="26" r="9"/><circle cx="34" cy="26" r="9"/><g stroke-dasharray="1.2 2.2" stroke-width="2.6"><path d="M5.5 28a9 9 0 0 0 17 0M25.5 28a9 9 0 0 0 17 0"/></g></svg>',
  jhumka: '<svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.3" stroke-linejoin="round"><circle cx="14" cy="9" r="3.2"/><circle cx="34" cy="9" r="3.2"/><path d="M14 13c-2 0-3 2-3.5 5-.6 4-3 7-4.5 9h16c-1.5-2-3.9-5-4.5-9-.5-3-1.5-5-3.5-5zM34 13c-2 0-3 2-3.5 5-.6 4-3 7-4.5 9h16c-1.5-2-3.9-5-4.5-9-.5-3-1.5-5-3.5-5z"/><g stroke-dasharray="0.1 3.2" stroke-width="2.6" stroke-linecap="round"><path d="M7 31h14M27 31h14"/></g></svg>',
  'solitaire-pendant': '<svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.3" stroke-linejoin="round"><path d="M8 4c4 10 10 16 16 18 6-2 12-8 16-18" stroke-dasharray="1.4 1.6"/><circle cx="24" cy="23" r="2"/><path d="M18 28h12l3.5 4.5L24 42l-9.5-9.5z"/><path d="M14.5 32.5h19"/></svg>',
  'halo-pendant': '<svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.3"><path d="M8 4c4 10 10 16 16 18 6-2 12-8 16-18" stroke-dasharray="1.4 1.6"/><circle cx="24" cy="23" r="2"/><circle cx="24" cy="33" r="4.4"/><circle cx="24" cy="33" r="8" stroke-dasharray="1.6 2"/></svg>',
  heart: '<svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.3" stroke-linejoin="round"><path d="M8 4c4 10 10 16 16 18 6-2 12-8 16-18" stroke-dasharray="1.4 1.6"/><path d="M24 29c-2-3-9-3-9 3 0 5 6 8 9 11 3-3 9-6 9-11 0-6-7-6-9-3z"/><path d="M24 32.5c-1.5-1.8-5.5-1.6-5.5 1.6 0 2.8 3.4 4.9 5.5 6.9 2.1-2 5.5-4.1 5.5-6.9 0-3.2-4-3.4-5.5-1.6z" stroke-dasharray="1 1.6"/></svg>',
  'drop-pendant': '<svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.3"><path d="M8 4c4 10 10 16 16 18 6-2 12-8 16-18" stroke-dasharray="1.4 1.6"/><circle cx="24" cy="24" r="2.4"/><ellipse cx="24" cy="36" rx="5.5" ry="8"/></svg>',
};
export const find = (list, id) => list.find(o => o.id === id) || list[0];

/* ---------- 2. Shared pieces: the faceted stone, the shank, a sparkle texture ---------- */
const shared = g => { g.userData.shared = true; return g; };

// A round brilliant, 1 unit across: table, star, bezel and girdle rings over the crown; two rings and the
// culet under it. Alternate rings are turned half a step, so every band of facets is a row of triangles.
function makeGemGeometry(n = 16) {
  const rings = [[0.28, 0.16, 0], [0.37, 0.128, 0.5], [0.46, 0.062, 0], [0.5, 0.012, 0.5], [0.5, -0.012, 0.5], [0.4, -0.11, 0], [0.2, -0.28, 0.5]];
  const pts = rings.map(([r, y, off]) => Array.from({ length: n }, (_, i) => {
    const a = ((i + off) / n) * TAU;
    return new THREE.Vector3(Math.cos(a) * r, y, Math.sin(a) * r);
  }));
  const pos = [];
  const ab = new THREE.Vector3(), ac = new THREE.Vector3(), mid = new THREE.Vector3();
  const tri = (a, b, c) => {
    ab.subVectors(b, a);
    ac.subVectors(c, a);
    mid.copy(a).add(b).add(c);
    if (ab.cross(ac).dot(mid) < 0) [b, c] = [c, b]; // every face points out of the stone
    pos.push(a.x, a.y, a.z, b.x, b.y, b.z, c.x, c.y, c.z);
  };
  const table = new THREE.Vector3(0, 0.16, 0), culet = new THREE.Vector3(0, -0.43, 0);
  for (let i = 0; i < n; i++) {
    const j = (i + 1) % n;
    tri(table, pts[0][i], pts[0][j]);
    tri(culet, pts[6][i], pts[6][j]);
  }
  for (let k = 0; k < rings.length - 1; k++) {
    const A = pts[k], B = pts[k + 1], oa = rings[k][2], ob = rings[k + 1][2];
    for (let i = 0; i < n; i++) {
      const j = (i + 1) % n;
      if (oa === ob) { tri(A[i], A[j], B[j]); tri(A[i], B[j], B[i]); }
      else if (ob > oa) { tri(A[i], A[j], B[i]); tri(B[i], A[j], B[j]); }
      else { tri(B[i], B[j], A[i]); tri(A[i], B[j], A[j]); }
    }
  }
  const g = new THREE.BufferGeometry();
  g.setAttribute('position', new THREE.Float32BufferAttribute(pos, 3));
  g.computeVertexNormals(); // not indexed, so every facet keeps its own flat normal
  return g;
}
const GEM = shared(makeGemGeometry());
const SPHERE = shared(new THREE.SphereGeometry(1, 20, 14));

// The shank: a rounded-square profile swept around the finger (or wrist). The ring stands in the XY plane (its
// hole faces the camera), the top is at +Y, and `taper` slims it towards the setting. An `arc` shorter than a full
// turn, starting at angle `start`, leaves it open (a cuff); its ends are then left for the caller to finish.
const R_IN = 0.86;
function makeBand({ thick = 0.13, width = 0.3, taper = 0, round = 3.4, segs = 256, prof = 40, rIn = R_IN, arc = TAU, start = 0 } = {}) {
  const atTop = phi => Math.max(0, Math.sin(phi)) ** 2;
  const thickAt = phi => thick * (1 - taper * 0.35 * atTop(phi));
  const widthAt = phi => width * (1 - taper * atTop(phi));
  const closed = arc >= TAU - 1e-6, rows = closed ? segs : segs + 1;
  const pos = new Float32Array(rows * prof * 3);
  let p = 0;
  for (let i = 0; i < rows; i++) {
    const phi = start + (i / segs) * arc, th = thickAt(phi), wd = widthAt(phi), cp = Math.cos(phi), sp = Math.sin(phi);
    for (let j = 0; j < prof; j++) {
      const t = (j / prof) * TAU, c = Math.cos(t), s = Math.sin(t);
      const u = Math.sign(c) * Math.abs(c) ** (2 / round), v = Math.sign(s) * Math.abs(s) ** (2 / round);
      const r = rIn + th * (u * 0.5 + 0.5);
      pos[p++] = r * cp; pos[p++] = r * sp; pos[p++] = v * wd * 0.5;
    }
  }
  const idx = [];
  for (let i = 0; i < segs; i++) {
    for (let j = 0; j < prof; j++) {
      const i2 = closed ? (i + 1) % segs : i + 1, j2 = (j + 1) % prof;
      const a = i * prof + j, b = i2 * prof + j, c = i2 * prof + j2, d = i * prof + j2;
      idx.push(a, b, d, b, c, d);
    }
  }
  const g = new THREE.BufferGeometry();
  g.setAttribute('position', new THREE.BufferAttribute(pos, 3));
  g.setIndex(idx);
  g.computeVertexNormals();
  // The outer face (j = 0) must face away from the centre
  const mid = Math.floor(rows / 2), midPhi = start + (mid / segs) * arc;
  if (g.attributes.normal.getX(mid * prof) * Math.cos(midPhi) + g.attributes.normal.getY(mid * prof) * Math.sin(midPhi) < 0) {
    for (let k = 0; k < idx.length; k += 3) [idx[k + 1], idx[k + 2]] = [idx[k + 2], idx[k + 1]];
    g.setIndex(idx);
    g.computeVertexNormals();
  }
  return { geometry: g, outerAt: phi => rIn + thickAt(phi), widthAt, thickAt, rIn };
}

function glintTexture() {
  const c = document.createElement('canvas');
  c.width = c.height = 256;
  const x = c.getContext('2d');
  const g = x.createRadialGradient(128, 128, 0, 128, 128, 128);
  g.addColorStop(0, 'rgba(255,255,255,1)');
  g.addColorStop(0.07, 'rgba(255,250,235,0.9)');
  g.addColorStop(0.22, 'rgba(255,232,190,0.16)');
  g.addColorStop(1, 'rgba(255,220,160,0)');
  x.fillStyle = g;
  x.fillRect(0, 0, 256, 256);
  x.globalCompositeOperation = 'lighter';
  const ray = (angle, len, w) => {
    x.save();
    x.translate(128, 128);
    x.rotate(angle);
    const lg = x.createLinearGradient(-len, 0, len, 0);
    lg.addColorStop(0, 'rgba(255,255,255,0)');
    lg.addColorStop(0.5, 'rgba(255,255,255,0.95)');
    lg.addColorStop(1, 'rgba(255,255,255,0)');
    x.fillStyle = lg;
    x.beginPath();
    x.moveTo(-len, 0); x.lineTo(0, -w); x.lineTo(len, 0); x.lineTo(0, w);
    x.closePath();
    x.fill();
    x.restore();
  };
  ray(0, 128, 3.2);
  ray(Math.PI / 2, 128, 3.2);
  ray(Math.PI / 4, 64, 2);
  ray(-Math.PI / 4, 64, 2);
  const t = new THREE.CanvasTexture(c);
  t.colorSpace = THREE.SRGBColorSpace;
  return t;
}
function radialTexture() {
  const c = document.createElement('canvas');
  c.width = c.height = 128;
  const x = c.getContext('2d');
  const g = x.createRadialGradient(64, 64, 0, 64, 64, 64);
  g.addColorStop(0, 'rgba(255,255,255,1)');
  g.addColorStop(0.45, 'rgba(255,255,255,0.35)');
  g.addColorStop(1, 'rgba(255,255,255,0)');
  x.fillStyle = g;
  x.fillRect(0, 0, 128, 128);
  return new THREE.CanvasTexture(c);
}

/* A photo studio in miniature — a dim dome with soft boxes and a few pin lights. Metals reflect it (through
 * PMREM) and the stones refract it (through a cube map), which is where the sparkle comes from. */
function buildStudio() {
  const s = new THREE.Scene();
  s.add(new THREE.Mesh(
    new THREE.SphereGeometry(30, 64, 32),
    new THREE.ShaderMaterial({
      side: THREE.BackSide,
      depthWrite: false,
      vertexShader: 'varying vec3 vDir; void main(){ vDir = normalize(position); gl_Position = projectionMatrix * modelViewMatrix * vec4(position, 1.0); }',
      fragmentShader: `varying vec3 vDir;
        void main(){
          float y = vDir.y;
          vec3 low = vec3(0.03, 0.026, 0.023), mid = vec3(0.13, 0.11, 0.09), top = vec3(0.5, 0.46, 0.4);
          vec3 c = y < 0.0 ? mix(mid, low, smoothstep(0.0, -0.45, y)) : mix(mid, top, smoothstep(0.0, 0.85, y));
          gl_FragColor = vec4(c, 1.0);
        }`,
    })
  ));
  const box = (x, y, z, w, h, power) => {
    const m = new THREE.Mesh(new THREE.PlaneGeometry(w, h), new THREE.MeshBasicMaterial({ color: new THREE.Color().setScalar(power), side: THREE.DoubleSide }));
    m.position.set(x, y, z);
    m.lookAt(0, 0, 0);
    s.add(m);
  };
  box(0, 13, 0, 12, 12, 2.6);      // overhead
  box(-9, 2.5, 5, 2.4, 11, 7);     // left strip
  box(9, 3, 3, 2.4, 10, 5.5);      // right strip
  box(0, 3.5, 11, 7, 1.6, 8);      // front strip
  box(3, -2, -10, 10, 2.2, 3);     // low back
  box(-5, 6, -8, 3.5, 3.5, 9);     // back corner
  const lamp = new THREE.SphereGeometry(0.32, 12, 8);
  const lampMat = new THREE.MeshBasicMaterial({ color: new THREE.Color().setScalar(30) });
  for (let i = 0; i < 16; i++) {
    const a = (i / 16) * TAU + 0.3, el = 0.12 + (i % 4) * 0.19;
    const m = new THREE.Mesh(lamp, lampMat);
    m.position.set(Math.cos(a) * Math.cos(el) * 14, Math.sin(el) * 14, Math.sin(a) * Math.cos(el) * 14);
    s.add(m);
  }
  return s;
}

// A faceted-stone shader: the view ray refracts into the stone (one ray per colour, for fire), bounces off the
// pavilion facet under that point and the one across from it, and leaves through the crown; the facet's own
// reflection is added by Fresnel.
function makeGemMaterial(envTexture) {
  return new THREE.ShaderMaterial({
    uniforms: {
      uEnv: { value: envTexture },
      uEnvRot: { value: new THREE.Matrix3() },
      uColor: { value: new THREE.Color('#ffffff') },
      uTint: { value: 0 },
      uIor: { value: 2.42 },
      uDisp: { value: 0.07 },
      uTime: { value: 0 },
      uFlash: { value: 0 },
      uSparkle: { value: 1 },
    },
    vertexShader: `
      varying vec3 vWPos; varying vec3 vWNormal; varying vec3 vUp; varying vec3 vSide; varying vec3 vLPos; varying vec3 vLNormal; varying float vSeed;
      void main(){
        vec4 p = vec4(position, 1.0);
        vec3 n = normal, up = vec3(0.0, 1.0, 0.0), side = vec3(1.0, 0.0, 0.0);
        vSeed = 0.0;
        #ifdef USE_INSTANCING
          p = instanceMatrix * p;
          n = mat3(instanceMatrix) * n;
          up = mat3(instanceMatrix) * up;
          side = mat3(instanceMatrix) * side;
          vSeed = float(gl_InstanceID) * 7.31;
        #endif
        vec4 wp = modelMatrix * p;
        vWPos = wp.xyz;
        vWNormal = normalize(mat3(modelMatrix) * n);
        vUp = normalize(mat3(modelMatrix) * up);
        vSide = normalize(mat3(modelMatrix) * side);
        vLPos = position;
        vLNormal = normal;
        gl_Position = projectionMatrix * viewMatrix * wp;
      }`,
    fragmentShader: `
      uniform samplerCube uEnv; uniform mat3 uEnvRot; uniform vec3 uColor;
      uniform float uTint, uIor, uDisp, uTime, uFlash, uSparkle;
      varying vec3 vWPos; varying vec3 vWNormal; varying vec3 vUp; varying vec3 vSide; varying vec3 vLPos; varying vec3 vLNormal; varying float vSeed;

      float hash(vec3 p){ return fract(sin(dot(p, vec3(12.9898, 78.233, 45.164))) * 43758.5453); }
      vec3 env(vec3 d){ return textureCube(uEnv, uEnvRot * d).rgb; }
      vec3 trace(vec3 I, vec3 N, float ior, vec3 Np, vec3 Nq){
        vec3 t = refract(I, N, 1.0 / ior);
        if (dot(t, t) < 1e-4) t = reflect(I, N);
        return reflect(reflect(t, Np), Nq);
      }
      void main(){
        vec3 I = normalize(vWPos - cameraPosition);
        vec3 N = normalize(vWNormal);
        vec3 U = normalize(vUp);
        vec3 S = normalize(vSide), W = normalize(cross(U, S));

        // Which pavilion facet lies under this point (mains in the middle, lower-girdle facets further out),
        // and the facet across the stone that sends the light back up
        float seg = 6.2831853 / 16.0, r = length(vLPos.xz) * 2.0, outer = step(0.55, r);
        float idx = floor(atan(vLPos.z, vLPos.x) / seg + outer * 0.5);
        float a = (idx + 0.5 - outer * 0.5) * seg, tilt = mix(0.71, 0.77, outer);
        vec3 radial = cos(a) * S + sin(a) * W;
        vec3 seed = vec3(idx, outer, vSeed) + floor(vLNormal * 40.0);
        vec3 jitter = (vec3(hash(seed), hash(seed + 1.7), hash(seed + 3.1)) - 0.5) * 0.3;
        vec3 Np = normalize(-U * cos(tilt) + radial * sin(tilt) + jitter);
        vec3 Nq = normalize(-U * cos(tilt) - radial * sin(tilt) - jitter * 0.5);

        vec3 Nt = N - 2.0 * min(0.0, dot(N, U)) * U; // seen from the side, the pavilion lights up like the crown
        vec3 T = vec3(env(trace(I, Nt, uIor - uDisp, Np, Nq)).r,
                      env(trace(I, Nt, uIor, Np, Nq)).g,
                      env(trace(I, Nt, uIor + uDisp, Np, Nq)).b);
        T = pow(T, vec3(1.35)) * 1.3 + 0.045;

        float L = dot(T, vec3(0.2126, 0.7152, 0.0722));
        vec3 body = pow(uColor, vec3(1.7)) * (0.03 + 1.6 * L) + uColor * 0.015;
        T = mix(T, body, uTint);

        float cosi = clamp(dot(-I, N), 0.0, 1.0);
        float F = mix(0.08, 1.0, pow(1.0 - cosi, 5.0));
        vec3 R = env(reflect(I, N)) * mix(vec3(1.0), uColor, uTint * 0.35);
        vec3 col = mix(T, R, F);

        float h = hash(seed * 1.3);
        float twinkle = pow(max(0.0, sin(uTime * (1.1 + h * 2.2) + h * 40.0)), 48.0) * uSparkle;
        col += twinkle * mix(vec3(3.0), uColor * 4.0, uTint * 0.6);
        col += uFlash * mix(vec3(1.0), uColor, uTint) * 1.3;

        gl_FragColor = vec4(col, 1.0);
        #include <tonemapping_fragment>
        #include <colorspace_fragment>
      }`,
  });
}

/* ---------- 3. Small tweens (the website does not load GSAP) ---------- */
const ease = {
  inOut: t => (t < 0.5 ? 4 * t * t * t : 1 - (-2 * t + 2) ** 3 / 2),
  out: t => 1 - (1 - t) ** 3,
  expoOut: t => (t >= 1 ? 1 : 1 - 2 ** (-10 * t)),
  backOut: t => 1 + 2.5 * (t - 1) ** 3 + 1.5 * (t - 1) ** 2,
};

/* ---------- 4. The viewer ---------- */
/**
 * @param {HTMLCanvasElement} canvas  sized by CSS; the viewer follows its size
 * @param {object} options  theme: 'dark' | 'light' (glow or shadow under the piece), autoRotate, sparkle,
 *                          zoom: false to leave the mouse wheel to the page
 */
export function createViewer(canvas, options = {}) {
  const opts = { theme: 'dark', autoRotate: !reduceMotion, sparkle: true, ...options };
  const renderer = new THREE.WebGLRenderer({ canvas, antialias: true, alpha: true, powerPreference: 'high-performance' });
  renderer.setPixelRatio(Math.min(devicePixelRatio, 2));
  renderer.setClearColor(0x000000, 0);
  renderer.toneMapping = THREE.NeutralToneMapping;
  renderer.toneMappingExposure = 1.05;

  const scene = new THREE.Scene();
  const camera = new THREE.PerspectiveCamera(30, 1, 0.05, 100);
  const key = new THREE.DirectionalLight(0xfff1dc, 1.4);
  key.position.set(3, 5, 4);
  scene.add(key);

  const studioScene = buildStudio();
  const pmrem = new THREE.PMREMGenerator(renderer);
  const envMap = pmrem.fromScene(studioScene, 0.01).texture;
  scene.environment = envMap;
  const cubeRT = new THREE.WebGLCubeRenderTarget(512, { type: THREE.HalfFloatType });
  new THREE.CubeCamera(0.1, 60, cubeRT).update(renderer, studioScene);

  const metalMat = new THREE.MeshStandardMaterial({ color: METALS[0].color, metalness: 1, roughness: METALS[0].rough, envMapIntensity: 1.35 });
  const gemMat = makeGemMaterial(cubeRT.texture);
  // Pearls (the jhumka's) keep their own lustre whatever the metal and stone
  const pearlMat = new THREE.MeshPhysicalMaterial({ color: '#f6efe4', roughness: 0.24, metalness: 0, clearcoat: 1, clearcoatRoughness: 0.1, sheen: 0.7, sheenColor: new THREE.Color('#ffe6f0'), envMapIntensity: 1.2 });
  const glintMat = new THREE.SpriteMaterial({ map: glintTexture(), color: 0xfff6e6, blending: THREE.AdditiveBlending, depthTest: false, depthWrite: false, transparent: true, toneMapped: false });
  const floorMat = new THREE.MeshBasicMaterial({ map: radialTexture(), transparent: true, depthWrite: false, toneMapped: false });
  const floor = new THREE.Mesh(new THREE.PlaneGeometry(1, 1), floorMat);
  floor.rotation.x = -Math.PI / 2;
  scene.add(floor);

  const controls = new OrbitControls(camera, canvas);
  Object.assign(controls, { enableDamping: true, dampingFactor: 0.07, enablePan: false, rotateSpeed: 0.7, zoomSpeed: 0.8, minPolarAngle: 0.15, maxPolarAngle: Math.PI * 0.75 });
  let dragging = false, resumeTimer;
  controls.addEventListener('start', () => {
    dragging = true;
    clearTimeout(resumeTimer);
    canvas.dispatchEvent(new CustomEvent('viewer:interact'));
  });
  controls.addEventListener('end', () => { resumeTimer = setTimeout(() => (dragging = false), 1400); });

  // rig (display angle) › spinner (turntable and the turn on every change) › the piece
  const rig = new THREE.Group();
  const spinner = new THREE.Group();
  rig.add(spinner);
  scene.add(rig);
  let piece = null, current = '', radius = 1.3;
  const state = { metal: '', stone: '' };
  const spin = { turn: 0, pulse: 0 };
  const fx = { env: 0, sparkle: opts.sparkle ? 1 : 0, glintBoost: 0, grow: 1 };

  /* Tweens, run by the render loop */
  const tweens = new Set();
  function tween(target, to, seconds, curve = ease.inOut, done) {
    for (const t of tweens) if (t.target === target) for (const k in to) delete t.to[k];
    const from = {};
    for (const k in to) from[k] = target[k];
    tweens.add({ target, from, to, ms: seconds * 1000, curve, done, start: performance.now() });
    wake();
  }
  function stepTweens(now) {
    for (const t of tweens) {
      const p = Math.min(1, (now - t.start) / t.ms), e = t.curve(p);
      for (const k in t.to) t.target[k] = t.from[k] + (t.to[k] - t.from[k]) * e;
      if (p >= 1) {
        tweens.delete(t);
        if (t.done) t.done();
      }
    }
  }

  /* Particles: a burst of gold dust on every change */
  const BURST = 140;
  const bPos = new Float32Array(BURST * 3), bVel = new Float32Array(BURST * 3), bLife = new Float32Array(BURST), bDecay = new Float32Array(BURST), bSize = new Float32Array(BURST);
  const burstGeo = new THREE.BufferGeometry();
  burstGeo.setAttribute('position', new THREE.BufferAttribute(bPos, 3).setUsage(THREE.DynamicDrawUsage));
  burstGeo.setAttribute('aLife', new THREE.BufferAttribute(bLife, 1).setUsage(THREE.DynamicDrawUsage));
  burstGeo.setAttribute('aSize', new THREE.BufferAttribute(bSize, 1));
  const burstMat = new THREE.ShaderMaterial({
    uniforms: { uScale: { value: 1 }, uColor: { value: new THREE.Color('#ffffff') } },
    vertexShader: `
      uniform float uScale; attribute float aLife; attribute float aSize; varying float vLife;
      void main(){
        vLife = aLife;
        vec4 mv = modelViewMatrix * vec4(position, 1.0);
        gl_Position = projectionMatrix * mv;
        gl_PointSize = aSize * uScale / -mv.z * (0.35 + 0.65 * aLife);
      }`,
    fragmentShader: `
      uniform vec3 uColor; varying float vLife;
      void main(){
        vec2 p = gl_PointCoord - 0.5;
        float core = pow(smoothstep(0.5, 0.0, length(p)), 3.0);
        float rays = max(smoothstep(0.05, 0.0, abs(p.x)) * smoothstep(0.5, 0.0, abs(p.y)), smoothstep(0.05, 0.0, abs(p.y)) * smoothstep(0.5, 0.0, abs(p.x)));
        float a = (core + rays * 0.7) * vLife;
        if (a < 0.01) discard;
        gl_FragColor = vec4(mix(uColor, vec3(1.0), 0.35), min(a, 1.0));
        #include <colorspace_fragment>
      }`,
    transparent: true,
    depthWrite: false,
  });
  const burstPts = new THREE.Points(burstGeo, burstMat);
  burstPts.frustumCulled = false;
  scene.add(burstPts);
  let burstAlive = false;

  const _p = new THREE.Vector3(), _n = new THREE.Vector3(), _v = new THREE.Vector3(), _m3 = new THREE.Matrix3(), _m4 = new THREE.Matrix4(), _box = new THREE.Box3(), _sphere = new THREE.Sphere();
  function burst(color) {
    if (reduceMotion || !piece) return;
    burstMat.uniforms.uColor.value.set(color);
    piece.userData.anchor.getWorldPosition(_p);
    const sp0 = radius * 0.9;
    for (let i = 0; i < BURST; i++) {
      const u = Math.random() * 2 - 1, th = Math.random() * TAU, sq = Math.sqrt(1 - u * u), sp = sp0 * (0.6 + Math.random() * 1.8);
      bPos[i * 3] = _p.x; bPos[i * 3 + 1] = _p.y; bPos[i * 3 + 2] = _p.z;
      bVel[i * 3] = sq * Math.cos(th) * sp; bVel[i * 3 + 1] = u * sp * 0.8 + sp0 * 0.4; bVel[i * 3 + 2] = sq * Math.sin(th) * sp;
      bLife[i] = 1;
      bDecay[i] = 0.55 + Math.random() * 0.8;
      bSize[i] = radius * (0.025 + Math.random() * 0.06);
    }
    burstGeo.attributes.aSize.needsUpdate = true;
    burstAlive = true;
    wake();
  }
  function updateBurst(dt) {
    if (!burstAlive) return;
    const drag = Math.exp(-2.6 * dt);
    let alive = false;
    for (let i = 0; i < BURST; i++) {
      if (bLife[i] <= 0) continue;
      const k = i * 3;
      bVel[k] *= drag; bVel[k + 1] = bVel[k + 1] * drag - 0.6 * radius * dt; bVel[k + 2] *= drag;
      bPos[k] += bVel[k] * dt; bPos[k + 1] += bVel[k + 1] * dt; bPos[k + 2] += bVel[k + 2] * dt;
      bLife[i] = Math.max(0, bLife[i] - dt * bDecay[i]);
      if (bLife[i] > 0) alive = true;
    }
    burstGeo.attributes.position.needsUpdate = true;
    burstGeo.attributes.aLife.needsUpdate = true;
    burstAlive = alive;
  }

  /* ---------- Building a house design ---------- */
  function addGlint(ctx, parent, pos, normal, size) {
    const s = new THREE.Sprite(glintMat);
    s.position.copy(pos);
    s.scale.setScalar(1e-4);
    s.userData = { n: normal.clone().normalize(), size, phase: Math.random() * TAU, speed: 0.7 + Math.random() * 1.1 };
    parent.add(s);
    ctx.glints.push(s);
  }
  // A point on the crown of a stone `D` across, `f` of the way out from the centre
  const crownPoint = (D, a, f) => {
    const r = f * 0.5, y = r <= 0.28 ? 0.16 : 0.16 - ((r - 0.28) / 0.22) * 0.148;
    return new THREE.Vector3(Math.cos(a) * r * D, (y + 0.015) * D, Math.sin(a) * r * D);
  };
  const crownNormal = (a, f) => { const k = f * 0.5 <= 0.28 ? 0 : 0.75; return new THREE.Vector3(Math.cos(a) * k, 1, Math.sin(a) * k); };

  const _m = new THREE.Matrix4(), _mb = new THREE.Matrix4(), _q = new THREE.Quaternion(), _up = new THREE.Vector3(0, 1, 0), _dir = new THREE.Vector3(), _pos = new THREE.Vector3(), _scl = new THREE.Vector3();
  const FRONT = new THREE.Vector3(0, 0, 1), _qFront = new THREE.Quaternion().setFromUnitVectors(_up, FRONT);
  const mesh = (geometry, material) => new THREE.Mesh(geometry, material);
  // Turns a part built table-up (+Y) so it faces the viewer (+Z): earrings and pendants
  const faceFront = o => { o.rotation.x = Math.PI / 2; return o; };

  // A stone in a claw setting, in its own frame: table up (+Y), girdle at the origin, the claws reaching `lift` down
  function clawHead(ctx, { D, lift, prongs = 4, rail = true, base = false, glints = 3 }) {
    const g = D / 2, pr = Math.max(0.016, 0.04 * D), a0 = prongs === 6 ? Math.PI / 6 : Math.PI / 4;
    const h = new THREE.Group();
    const stone = mesh(GEM, gemMat);
    stone.scale.setScalar(D);
    h.add(stone);
    for (let k = 0; k < prongs; k++) {
      const a = a0 + (k / prongs) * TAU, c = Math.cos(a), s = Math.sin(a);
      const at = (r, y) => new THREE.Vector3(r * c, y, r * s);
      const tip = at(0.86 * g, 0.095 * D);
      const path = new THREE.CatmullRomCurve3([at(0.26 * g, -lift - 0.05), at(0.5 * g, -0.33 * D), at(g + pr * 0.6, -0.03 * D), at(0.98 * g + pr * 0.4, 0.05 * D), tip]);
      h.add(mesh(new THREE.TubeGeometry(path, 48, pr, 10), metalMat));
      const cap = mesh(SPHERE, metalMat);
      cap.position.copy(tip);
      cap.scale.setScalar(pr * 1.2);
      h.add(cap);
    }
    if (rail) {
      const t = mesh(new THREE.TorusGeometry(0.76 * g, pr * 0.72, 10, 80), metalMat);
      t.rotation.x = Math.PI / 2;
      t.position.y = -0.19 * D;
      h.add(t);
    }
    if (base) {
      const t = mesh(new THREE.TorusGeometry(0.36 * g, pr * 0.8, 10, 64), metalMat);
      t.rotation.x = Math.PI / 2;
      t.position.y = -lift + 0.025;
      h.add(t);
    }
    for (let k = 0; k < glints; k++) {
      const a = Math.random() * TAU, f = 0.15 + Math.random() * 0.75;
      addGlint(ctx, h, crownPoint(D, a, f), crownNormal(a, f), D * 0.9);
    }
    return h;
  }
  // The same, raised `lift` above a band at angle `phi` (π/2 is the top)
  function mount(ctx, band, { phi = Math.PI / 2, lift, parent = ctx.group, ...rest }) {
    const h = clawHead(ctx, { lift, ...rest });
    const rr = band.outerAt(phi) + lift;
    h.position.set(Math.cos(phi) * rr, Math.sin(phi) * rr, 0);
    h.rotation.z = phi - Math.PI / 2;
    parent.add(h);
    return h;
  }
  // A ring of small stones `hd` across round a centre stone of radius `g`, in that stone's frame; returns its radius
  function halo(ctx, parent, g, hd = 0.088) {
    const hr = g + hd * 0.75, n = Math.round((TAU * hr) / (hd * 1.18));
    const stones = new THREE.InstancedMesh(GEM, gemMat, n);
    const beads = new THREE.InstancedMesh(SPHERE, metalMat, n);
    for (let k = 0; k < n; k++) {
      const a = (k / n) * TAU, c = Math.cos(a), sn = Math.sin(a);
      _q.setFromUnitVectors(_up, _dir.set(c * 0.28, 1, sn * 0.28).normalize());
      stones.setMatrixAt(k, _m.compose(_pos.set(c * hr, -0.006, sn * hr), _q, _scl.setScalar(hd)));
      const b = a + Math.PI / n;
      beads.setMatrixAt(k, _m.compose(_pos.set(Math.cos(b) * hr, 0.012, Math.sin(b) * hr), _q.identity(), _scl.setScalar(hd * 0.142)));
      if (k % 5 === 0) addGlint(ctx, parent, new THREE.Vector3(c * hr, 0.03, sn * hr), new THREE.Vector3(c * 0.28, 1, sn * 0.28), hd * 3.6);
    }
    const seatGeo = new THREE.TorusGeometry(hr, hd * 0.52, 12, 96);
    seatGeo.rotateX(Math.PI / 2);
    const seat = mesh(seatGeo, metalMat);
    seat.scale.y = 0.55;
    seat.position.y = -hd * 0.51;
    parent.add(stones, beads, seat);
    return hr;
  }
  // Small stones set into the outer face of a band at the angles given (a row `z` off the middle), a bead either side
  function pave(ctx, band, angles, D, { z = 0, parent = ctx.group, beads = true } = {}) {
    const stones = new THREE.InstancedMesh(GEM, gemMat, angles.length);
    const beadMesh = beads ? new THREE.InstancedMesh(SPHERE, metalMat, angles.length * 2) : null;
    angles.forEach((phi, i) => {
      const r = band.outerAt(phi), zb = D * 0.5 + 0.014;
      _dir.set(Math.cos(phi), Math.sin(phi), 0);
      _q.setFromUnitVectors(_up, _dir);
      stones.setMatrixAt(i, _m.compose(_pos.copy(_dir).multiplyScalar(r - 0.006).setZ(z), _q, _scl.setScalar(D)));
      if (!beadMesh) return;
      beadMesh.setMatrixAt(i * 2, _m.compose(_pos.copy(_dir).multiplyScalar(r + 0.002).setZ(z - zb), _q, _scl.setScalar(0.012)));
      beadMesh.setMatrixAt(i * 2 + 1, _m.compose(_pos.copy(_dir).multiplyScalar(r + 0.002).setZ(z + zb), _q, _scl.setScalar(0.012)));
    });
    parent.add(stones);
    if (beadMesh) parent.add(beadMesh);
    return stones;
  }
  // Stones facing the viewer at the points given (earrings, pendants)
  function frontStones(parent, points, D) {
    const stones = new THREE.InstancedMesh(GEM, gemMat, points.length);
    points.forEach((p, i) => stones.setMatrixAt(i, _m.compose(_pos.copy(p), _qFront, _scl.setScalar(D))));
    parent.add(stones);
    return stones;
  }
  // A small stone in a plain rim, facing the viewer
  function bezelStone(ctx, parent, D, y) {
    const st = faceFront(mesh(GEM, gemMat));
    st.scale.setScalar(D);
    st.position.set(0, y, 0);
    const rim = mesh(new THREE.TorusGeometry(D * 0.53, Math.max(0.012, D * 0.09), 10, 48), metalMat);
    rim.position.set(0, y, 0.004);
    parent.add(st, rim);
    addGlint(ctx, parent, new THREE.Vector3(D * 0.12, y + D * 0.1, D * 0.2), FRONT, D * 1.6);
  }
  // An oval stone `D` wide hanging from `top`, in a rim with four claws; returns the stone's centre
  function ovalDrop(ctx, parent, D, top) {
    const H = D * 1.45, cy = top - H / 2 - 0.03;
    const st = faceFront(mesh(GEM, gemMat));
    st.scale.set(D, D, H);
    st.position.set(0, cy, 0);
    const rim = mesh(new THREE.TorusGeometry(D * 0.52, 0.016, 10, 72), metalMat);
    rim.scale.y = 1.45;
    rim.position.set(0, cy, -0.004);
    const loop = mesh(new THREE.TorusGeometry(0.03, 0.01, 8, 24), metalMat);
    loop.rotation.y = Math.PI / 2;
    loop.position.set(0, top - 0.015, 0);
    parent.add(st, rim, loop);
    for (const a of [Math.PI / 4, (3 * Math.PI) / 4, (5 * Math.PI) / 4, (7 * Math.PI) / 4]) {
      const claw = mesh(SPHERE, metalMat);
      claw.scale.setScalar(0.022);
      claw.position.set(Math.cos(a) * D * 0.5, cy + Math.sin(a) * H * 0.5, 0.035);
      parent.add(claw);
    }
    for (const [x, y] of [[0.12, 0.22], [-0.18, -0.1], [0.05, -0.3]]) addGlint(ctx, parent, new THREE.Vector3(x * D, cy + y * H, D * 0.24), FRONT, D * 1.2);
    return new THREE.Vector3(0, cy, 0);
  }
  // A fine chain of oval links from `from` to `to`, bowed through `via`; alternate links turn a quarter
  const LINK = shared(new THREE.TorusGeometry(0.03, 0.0085, 6, 18));
  function chain(parent, from, via, to) {
    const curve = new THREE.QuadraticBezierCurve3(from, via, to);
    const n = Math.max(2, Math.round(curve.getLength() / 0.046));
    const links = new THREE.InstancedMesh(LINK, metalMat, n);
    const t = new THREE.Vector3(), a = new THREE.Vector3(), b = new THREE.Vector3(), p = new THREE.Vector3();
    for (let i = 0; i < n; i++) {
      curve.getPointAt(i / (n - 1), p);
      curve.getTangentAt(i / (n - 1), t).normalize();
      a.crossVectors(t, FRONT);
      if (a.lengthSq() < 1e-6) a.set(1, 0, 0);
      a.normalize();
      b.crossVectors(t, a);
      if (i % 2) _mb.makeBasis(t, b, a.clone().negate()); else _mb.makeBasis(t, a, b);
      _mb.scale(_scl.set(1.4, 1, 1)).setPosition(p);
      links.setMatrixAt(i, _mb);
    }
    parent.add(links);
  }
  const anchorAt = (parent, x, y, z) => {
    const a = new THREE.Object3D();
    a.position.set(x, y, z);
    parent.add(a);
    return a;
  };

  /* Rings */
  function buildRing(ctx, d) {
    const s = d.stoneSize, w = d.bandWidth;
    let band, anchor;
    if (d.type === 'halo') {
      band = makeBand({ thick: 0.115, width: 0.21 * w, taper: 0.12, round: 3.2 });
      ctx.group.add(mesh(band.geometry, metalMat));
      const D = 0.44 * s, g = D / 2, lift = 0.43 * D + 0.08;
      anchor = mount(ctx, band, { D, lift, prongs: 4, glints: 4 });
      const hr = halo(ctx, anchor, g);
      for (let k = 0; k < 4; k++) {
        const a = (k / 4) * TAU, c = Math.cos(a), sn = Math.sin(a);
        const at = (r, y) => new THREE.Vector3(r * c, y, r * sn);
        anchor.add(mesh(new THREE.TubeGeometry(new THREE.CatmullRomCurve3([at(0.2 * g, -lift - 0.04), at(0.62 * hr, -lift * 0.5), at(hr, -0.05)]), 32, 0.016, 8), metalMat));
      }
      const shoulder = [];
      for (const side of [-1, 1]) for (let k = 0; k < 8; k++) shoulder.push(Math.PI / 2 + side * (0.44 + k * 0.1));
      pave(ctx, band, shoulder, 0.072);
      addGlint(ctx, ctx.group, new THREE.Vector3(Math.cos(1.94) * 1.01, Math.sin(1.94) * 1.01, 0), new THREE.Vector3(Math.cos(1.94), Math.sin(1.94), 0), 0.22);
    } else if (d.type === 'trilogy') {
      band = makeBand({ thick: 0.13, width: 0.27 * w, taper: 0.15, round: 3.4 });
      ctx.group.add(mesh(band.geometry, metalMat));
      const D = 0.5 * s, Ds = 0.32 * s, liftS = 0.43 * Ds + 0.06;
      anchor = mount(ctx, band, { D, lift: 0.43 * D + 0.075, prongs: d.prongs, base: true, glints: 4 });
      const phi = Math.asin(Math.min(0.9, (D / 2 + Ds / 2 + 0.05) / (band.outerAt(Math.PI / 2) + liftS)));
      for (const side of [-1, 1]) mount(ctx, band, { phi: Math.PI / 2 + side * phi, D: Ds, lift: liftS, prongs: 4, glints: 2 });
    } else if (d.type === 'eternity') {
      band = makeBand({ thick: 0.12, width: 0.2 * w, taper: 0, round: 3 });
      ctx.group.add(mesh(band.geometry, metalMat));
      const D = Math.min(0.1 * s, 0.2 * w - 0.05), n = Math.floor((TAU * band.outerAt(0)) / (D * 1.2));
      pave(ctx, band, Array.from({ length: n }, (_, k) => Math.PI / 2 + (k / n) * TAU), D);
      for (let k = 0; k < 5; k++) {
        const phi = Math.PI / 2 + (k - 2) * ((2 * TAU) / n);
        addGlint(ctx, ctx.group, new THREE.Vector3(Math.cos(phi), Math.sin(phi), 0).multiplyScalar(band.outerAt(phi) + 0.03), new THREE.Vector3(Math.cos(phi), Math.sin(phi), 0), D * 2.4);
      }
      anchor = anchorAt(ctx.group, 0, band.outerAt(Math.PI / 2), 0);
    } else if (d.type === 'band') {
      band = makeBand({ thick: 0.15, width: 0.42 * w, taper: 0, round: 2.4 });
      ctx.group.add(mesh(band.geometry, metalMat));
      anchor = anchorAt(ctx.group, 0, band.outerAt(Math.PI / 2), 0);
    } else {
      band = makeBand({ thick: 0.135, width: 0.3 * w, taper: 0.35, round: 3.6 });
      ctx.group.add(mesh(band.geometry, metalMat));
      const D = 0.62 * s;
      anchor = mount(ctx, band, { D, lift: 0.43 * D + 0.05, prongs: d.prongs, base: true, glints: 6 });
    }
    return anchor;
  }

  /* Kangans and bangles: a band at wrist scale, wider than it is thick; a pair stands side by side */
  const BANGLE_WIDTH = { kada: 1.35, 'kundan-kada': 1.4, cuff: 1.1 };
  function buildBangle(ctx, d) {
    const s = d.stoneSize, width = 0.2 * d.bandWidth * (BANGLE_WIDTH[d.type] || 1);
    const sparkleAt = (grp, band, angles, size) => angles.forEach(phi => {
      _dir.set(Math.cos(phi), Math.sin(phi), 0);
      addGlint(ctx, grp, _dir.clone().multiplyScalar(band.outerAt(phi) + 0.02), _dir, size);
    });
    const one = z => {
      const grp = new THREE.Group();
      grp.position.z = z;
      ctx.group.add(grp);
      let band;
      if (d.type === 'diamond-kangan') {
        band = makeBand({ thick: 0.075, width, round: 3.2, segs: 320 });
        grp.add(mesh(band.geometry, metalMat));
        const rows = width > 0.17 ? 2 : 1, D = Math.min(0.068 * s, (width / rows) * 0.8), n = Math.floor((TAU * band.outerAt(0)) / (D * 1.16));
        for (let r = 0; r < rows; r++) {
          const off = r ? Math.PI / n : 0;
          pave(ctx, band, Array.from({ length: n }, (_, k) => (k / n) * TAU + off), D, { z: rows === 2 ? (r ? 1 : -1) * D * 0.56 : 0, parent: grp, beads: rows === 1 });
        }
        sparkleAt(grp, band, [1.3, 1.62, 1.95, 2.3], D * 2.6);
      } else if (d.type === 'kundan-kada') {
        band = makeBand({ thick: 0.09, width, round: 2.6, segs: 320 });
        grp.add(mesh(band.geometry, metalMat));
        const n = 12, D = Math.min(0.21 * s, width * 0.8), outer = band.outerAt(0);
        const stones = new THREE.InstancedMesh(GEM, gemMat, n);
        const cups = new THREE.InstancedMesh(shared(new THREE.CylinderGeometry(0.5, 0.42, 1, 28)), metalMat, n);
        const rims = new THREE.InstancedMesh(shared(new THREE.TorusGeometry(0.5, 0.075, 8, 40)), metalMat, n);
        const beads = new THREE.InstancedMesh(SPHERE, metalMat, n * 3);
        for (let k = 0; k < n; k++) {
          const phi = (k / n) * TAU;
          _dir.set(Math.cos(phi), Math.sin(phi), 0);
          _q.setFromUnitVectors(_up, _dir);
          stones.setMatrixAt(k, _m.compose(_pos.copy(_dir).multiplyScalar(outer + D * 0.21), _q, _scl.setScalar(D)));
          cups.setMatrixAt(k, _m.compose(_pos.copy(_dir).multiplyScalar(outer + D * 0.04), _q, _scl.set(D * 1.16, D * 0.36, D * 1.16)));
          rims.setMatrixAt(k, _m.compose(_pos.copy(_dir).multiplyScalar(outer + D * 0.19), _q.setFromUnitVectors(FRONT, _dir), _scl.setScalar(D * 1.12)));
          for (let b = 0; b < 3; b++) {
            const pb = phi + ((b + 1) / 4) * (TAU / n);
            beads.setMatrixAt(k * 3 + b, _m.compose(_pos.set(Math.cos(pb), Math.sin(pb), 0).multiplyScalar(outer + 0.004), _q.identity(), _scl.setScalar(b === 1 ? 0.026 : 0.017)));
          }
        }
        grp.add(stones, cups, rims, beads);
        sparkleAt(grp, { outerAt: () => outer + D * 0.3 }, [Math.PI / 2, Math.PI / 2 + TAU / n, Math.PI / 2 - TAU / n], D * 1.8);
      } else if (d.type === 'twisted') {
        const R0 = R_IN + 0.06, rs = 0.052 * d.bandWidth, tube = 0.05 * d.bandWidth, twists = 20;
        for (const phase of [0, Math.PI]) {
          const pts = [];
          for (let i = 0; i < 480; i++) {
            const phi = (i / 480) * TAU, a = twists * phi + phase, rr = R0 + rs * Math.cos(a);
            pts.push(new THREE.Vector3(Math.cos(phi) * rr, Math.sin(phi) * rr, rs * Math.sin(a)));
          }
          grp.add(mesh(new THREE.TubeGeometry(new THREE.CatmullRomCurve3(pts, true), 960, tube, 10, true), metalMat));
        }
        band = { outerAt: () => R0 + rs + tube };
        sparkleAt(grp, band, [1.45, 1.9], 0.16);
      } else if (d.type === 'cuff') {
        const gap = TAU * 0.17, start = -Math.PI / 2 + gap / 2, arc = TAU - gap, thick = 0.085;
        band = makeBand({ thick, width, round: 3, arc, start, segs: 300 });
        grp.add(mesh(band.geometry, metalMat));
        for (const phi of [start, start + arc]) {
          const cap = mesh(SPHERE, metalMat);
          cap.scale.set(thick * 0.56, thick * 0.56, width * 0.5);
          cap.position.set(Math.cos(phi) * (band.rIn + thick / 2), Math.sin(phi) * (band.rIn + thick / 2), 0);
          grp.add(cap);
        }
        // A stone at each end, tilted forward so it faces the wearer's gaze rather than the floor
        const Dt = 0.3 * s;
        for (const phi of [start + 0.36, start + arc - 0.36]) mount(ctx, band, { phi, D: Dt, lift: 0.43 * Dt + 0.03, prongs: d.prongs, base: true, glints: 3, parent: grp }).rotateX(0.75);
        const Dp = Math.min(0.06 * s, width * 0.42), from = start + 0.62, to = start + arc - 0.62;
        const n = Math.floor(((to - from) * band.outerAt(0)) / (Dp * 1.18));
        pave(ctx, band, Array.from({ length: n }, (_, k) => from + ((k + 0.5) / n) * (to - from)), Dp, { parent: grp });
      } else {
        const thick = 0.1;
        band = makeBand({ thick, width, round: 2.1, segs: 320 });
        grp.add(mesh(band.geometry, metalMat));
        const bead = 0.02 * d.bandWidth, r = band.rIn + thick * 0.5, n = Math.floor((TAU * r) / (bead * 2.25));
        const beads = new THREE.InstancedMesh(SPHERE, metalMat, n * 2);
        for (let k = 0; k < n; k++) {
          const phi = (k / n) * TAU;
          for (const side of [0, 1]) beads.setMatrixAt(k * 2 + side, _m.compose(_pos.set(Math.cos(phi) * r, Math.sin(phi) * r, (side ? 1 : -1) * (width / 2 + bead * 0.35)), _q.identity(), _scl.setScalar(bead)));
        }
        const rib = mesh(new THREE.TorusGeometry(band.rIn + thick + 0.002, 0.011, 8, 256), metalMat);
        grp.add(beads, rib);
        sparkleAt(grp, band, [1.55, 2.1], 0.2);
      }
      return band;
    };
    const apart = width / 2 + 0.035;
    const band = d.pair ? (one(-apart), one(apart)) : one(0);
    ctx.tilt = [0.55, -0.45];
    return anchorAt(ctx.group, 0, band.outerAt(Math.PI / 2), 0);
  }

  /* Earrings: a pair facing the viewer, turned a little towards each other; the size slider scales them */
  function earStud(e, z, back = 0.06) {
    const post = mesh(new THREE.CylinderGeometry(0.016, 0.016, 0.34, 10), metalMat);
    post.rotation.x = Math.PI / 2;
    post.position.z = z - 0.17;
    e.add(post);
    if (!back) return;
    const nut = mesh(new THREE.TorusGeometry(back, back * 0.36, 8, 28), metalMat);
    nut.scale.set(1.35, 0.85, 1);
    nut.position.z = z - 0.29;
    e.add(nut);
  }
  function buildEarrings(ctx, d) {
    const s = d.stoneSize;
    let anchor = null;
    const one = side => {
      const e = new THREE.Group();
      let gap = 0.5, focus = new THREE.Vector3();
      if (d.type === 'halo-stud') {
        const D = 0.3 * s, h = clawHead(ctx, { D, lift: 0.08, prongs: 4, rail: false, glints: 3 });
        const hr = halo(ctx, h, D / 2, 0.075);
        e.add(faceFront(h));
        earStud(e, -0.08);
        gap = hr + 0.2;
      } else if (d.type === 'drop') {
        bezelStone(ctx, e, 0.16, 0);
        const ring = mesh(new THREE.TorusGeometry(0.032, 0.01, 8, 24), metalMat);
        ring.rotation.y = Math.PI / 2;
        ring.position.y = -0.112;
        const link = mesh(new THREE.CylinderGeometry(0.011, 0.011, 0.2, 8), metalMat);
        link.position.y = -0.24;
        e.add(ring, link);
        focus = ovalDrop(ctx, e, 0.36 * s, -0.34);
        earStud(e, -0.02, 0.038);
        gap = 0.26 * s + 0.16;
      } else if (d.type === 'hoop') {
        const R = 0.42, tube = 0.045, D = 0.058 * s;
        const hoop = mesh(new THREE.TorusGeometry(R, tube, 14, 140), metalMat);
        hoop.position.y = -R;
        e.add(hoop);
        const from = -Math.PI + 0.32, to = -0.32, n = Math.floor(((to - from) * R) / (D * 1.12)), pts = [];
        for (let k = 0; k < n; k++) {
          const a = from + ((k + 0.5) / n) * (to - from);
          pts.push(new THREE.Vector3(Math.cos(a) * R, Math.sin(a) * R - R, tube * 0.86));
        }
        frontStones(e, pts, D);
        [2, Math.floor(n / 2), n - 3].forEach(k => pts[k] && addGlint(ctx, e, pts[k].clone().setZ(tube + 0.02), FRONT, D * 2.6));
        earStud(e, -0.02, 0);
        focus.set(0, -R * 2 + 0.05, 0);
        gap = R + 0.12;
      } else if (d.type === 'jhumka') {
        // A flower of stones on the stud, a ring, then the bell with a band of stones and pearls round its rim
        const head = clawHead(ctx, { D: 0.17 * s, lift: 0.06, prongs: 4, rail: false, glints: 2 });
        e.add(faceFront(head));
        const petals = [];
        for (let k = 0; k < 8; k++) petals.push(new THREE.Vector3(Math.cos((k / 8) * TAU) * 0.15, Math.sin((k / 8) * TAU) * 0.15, -0.01));
        frontStones(e, petals, 0.07);
        const disc = mesh(SPHERE, metalMat);
        disc.scale.set(0.2, 0.2, 0.055);
        disc.position.z = -0.045;
        const ring = mesh(new THREE.TorusGeometry(0.035, 0.011, 8, 24), metalMat);
        ring.rotation.y = Math.PI / 2;
        ring.position.y = -0.245;
        e.add(disc, ring);
        const prof = [[0.02, 0], [0.07, -0.02], [0.11, -0.07], [0.15, -0.16], [0.19, -0.27], [0.24, -0.36], [0.29, -0.42], [0.315, -0.445], [0.305, -0.46]];
        const bellGeo = new THREE.LatheGeometry(prof.reverse().map(([x, y]) => new THREE.Vector2(x, y)), 64);
        const bell = mesh(bellGeo, metalMat);
        const top = -0.28;
        bell.position.y = top;
        e.add(bell);
        const n = 12;
        const stones = new THREE.InstancedMesh(GEM, gemMat, n);
        for (let k = 0; k < n; k++) {
          const a = (k / n) * TAU;
          _dir.set(Math.cos(a), 0.35, Math.sin(a)).normalize();
          _q.setFromUnitVectors(_up, _dir);
          stones.setMatrixAt(k, _m.compose(_pos.set(Math.cos(a) * 0.205, top - 0.255, Math.sin(a) * 0.205), _q, _scl.setScalar(0.07)));
        }
        const rimBeads = new THREE.InstancedMesh(SPHERE, metalMat, 30);
        for (let k = 0; k < 30; k++) {
          const a = (k / 30) * TAU;
          rimBeads.setMatrixAt(k, _m.compose(_pos.set(Math.cos(a) * 0.31, top - 0.452, Math.sin(a) * 0.31), _q.identity(), _scl.setScalar(0.015)));
        }
        const pearls = new THREE.InstancedMesh(SPHERE, pearlMat, 14);
        for (let k = 0; k < 14; k++) {
          const a = ((k + 0.5) / 14) * TAU;
          pearls.setMatrixAt(k, _m.compose(_pos.set(Math.cos(a) * 0.29, top - 0.52, Math.sin(a) * 0.29), _q.identity(), _scl.setScalar(0.034)));
        }
        e.add(stones, rimBeads, pearls);
        addGlint(ctx, e, new THREE.Vector3(0, top - 0.25, 0.23), FRONT, 0.2);
        earStud(e, -0.06, 0.05);
        focus.set(0, top - 0.25, 0.2);
        gap = 0.4;
      } else {
        const D = 0.46 * s, h = clawHead(ctx, { D, lift: 0.1, prongs: d.prongs, base: true, glints: 4 });
        e.add(faceFront(h));
        earStud(e, -0.1);
        gap = D * 0.5 + 0.2;
      }
      e.scale.setScalar(d.bandWidth);
      e.position.x = side * gap * d.bandWidth;
      e.rotation.y = -side * 0.28;
      ctx.group.add(e);
      if (!anchor) anchor = anchorAt(e, focus.x, focus.y, focus.z + 0.05);
    };
    one(-1);
    one(1);
    ctx.tilt = [0.08, 0];
    return anchor;
  }

  /* Pendants: the piece hangs from a bail on a fine chain; the size slider scales it */
  function heartShape(k, scale = 1, cy = 2.5) {
    // The heart is drawn in a 22 × 19 box with its dip at the top; (x, y) map to ((x − 5)k, (12 − y)k), scaled about (0, cy·k)
    const P = (x, y) => [(x - 5) * k * scale, ((12 - y) - cy) * k * scale + cy * k];
    const sh = new THREE.Shape();
    const curve = (a, b, c) => sh.bezierCurveTo(...P(...a), ...P(...b), ...P(...c));
    sh.moveTo(...P(5, 5));
    curve([5, 5], [4, 0], [0, 0]);
    curve([-6, 0], [-6, 7], [-6, 7]);
    curve([-6, 11], [-3, 15.4], [5, 19]);
    curve([12, 15.4], [16, 11], [16, 7]);
    curve([16, 7], [16, 0], [10, 0]);
    curve([7, 0], [5, 5], [5, 5]);
    return sh;
  }
  function buildPendant(ctx, d) {
    const s = d.stoneSize, p = new THREE.Group();
    let focus = new THREE.Vector3();
    if (d.type === 'halo-pendant') {
      const D = 0.3 * s, h = clawHead(ctx, { D, lift: 0.08, prongs: 4, rail: false, glints: 3 });
      const hr = halo(ctx, h, D / 2, 0.075);
      faceFront(h).position.y = -(hr + 0.06);
      const link = mesh(new THREE.CylinderGeometry(0.014, 0.014, 0.08, 8), metalMat);
      link.position.y = -0.04;
      p.add(h, link);
      focus.set(0, h.position.y, 0);
    } else if (d.type === 'heart') {
      const k = 0.04, depth = 0.045;
      const outer = heartShape(k);
      outer.holes.push(heartShape(k, 0.7));
      const geo = new THREE.ExtrudeGeometry(outer, { depth, bevelEnabled: true, bevelThickness: 0.014, bevelSize: 0.012, bevelSegments: 3, curveSegments: 64 });
      geo.translate(0, -7 * k, -depth / 2);
      p.add(mesh(geo, metalMat));
      const D = 0.05 * s, mid = heartShape(k, 0.85), dip = new THREE.Vector2(0, 7 * k);
      const all = mid.getSpacedPoints(Math.floor(mid.getLength() / (D * 1.22)));
      all.pop();
      const pts = all.filter(v => v.distanceTo(dip) > D * 1.6).map(v => new THREE.Vector3(v.x, v.y - 7 * k, depth / 2 + 0.016));
      frontStones(p, pts, D);
      [0, Math.floor(pts.length / 3), Math.floor((2 * pts.length) / 3)].forEach(i => pts[i] && addGlint(ctx, p, pts[i].clone().setZ(depth + 0.03), FRONT, D * 2.8));
      focus.set(0, -0.2, 0);
    } else if (d.type === 'drop-pendant') {
      bezelStone(ctx, p, 0.13, -0.09);
      focus = ovalDrop(ctx, p, 0.36 * s, -0.16);
      const link = mesh(new THREE.CylinderGeometry(0.012, 0.012, 0.04, 8), metalMat);
      link.position.y = -0.01;
      p.add(link);
    } else {
      const D = 0.42 * s, h = clawHead(ctx, { D, lift: 0.1, prongs: d.prongs, base: false, glints: 5 });
      faceFront(h).position.y = -(D / 2 + 0.06);
      const link = mesh(new THREE.CylinderGeometry(0.014, 0.014, 0.08, 8), metalMat);
      link.position.y = -0.04;
      p.add(h, link);
      focus.set(0, h.position.y, 0);
    }
    const bail = mesh(new THREE.TorusGeometry(0.07, 0.02, 10, 40), metalMat);
    bail.rotation.y = Math.PI / 2;
    bail.position.y = 0.07;
    p.add(bail);
    const top = new THREE.Vector3(0, 0.13, 0);
    chain(p, top, new THREE.Vector3(-0.14, 0.42, 0), new THREE.Vector3(-0.46, 0.86, -0.16));
    chain(p, top, new THREE.Vector3(0.14, 0.42, 0), new THREE.Vector3(0.46, 0.86, -0.16));
    p.scale.setScalar(d.bandWidth);
    ctx.group.add(p);
    ctx.tilt = [0.06, 0];
    return anchorAt(p, focus.x, focus.y, focus.z + 0.08);
  }

  function buildDesign(d) {
    const ctx = { group: new THREE.Group(), glints: [], tilt: null };
    const kind = find(DESIGNS, d.type).kind;
    const anchor = kind === 'bangle' ? buildBangle(ctx, d) : kind === 'earring' ? buildEarrings(ctx, d) : kind === 'pendant' ? buildPendant(ctx, d) : buildRing(ctx, d);
    ctx.group.userData = { glints: ctx.glints, anchor, design: true, tilt: ctx.tilt || [0.3, -0.5] };
    return ctx.group;
  }

  /* ---------- Loading an uploaded model ---------- */
  const METAL_NAME = /metal|gold|silver|platinum|band|shank|prong|claw|setting|head|mount/i;
  const STONE_NAME = /diamond|gem|stone|crystal|ruby|emerald|sapphire|amethyst|topaz|glass|brilliant/i;
  let loader = null;
  async function buildModel(url) {
    loader = loader || new GLTFLoader();
    const gltf = await loader.loadAsync(url);
    const root = gltf.scene;
    let metals = 0, stones = 0;
    root.traverse(o => {
      if (!o.isMesh) return;
      const swap = m => {
        if (!m) return m;
        const name = `${m.name} ${o.name}`;
        const glassy = (m.transmission || 0) > 0.2 || (m.transparent && m.opacity < 0.95) || STONE_NAME.test(name);
        if (glassy && !METAL_NAME.test(m.name)) { stones++; return gemMat; }
        if ((m.metalness || 0) >= 0.5 || METAL_NAME.test(name)) { metals++; return metalMat; }
        return m;
      };
      o.material = Array.isArray(o.material) ? o.material.map(swap) : swap(o.material);
      if (o.material === gemMat && !o.geometry.attributes.normal) o.geometry.computeVertexNormals();
    });
    // Centre it and bring it to the size of the house designs
    _box.setFromObject(root).getBoundingSphere(_sphere);
    const k = 1.3 / (_sphere.radius || 1);
    root.scale.setScalar(k);
    root.position.copy(_sphere.center).multiplyScalar(-k);
    const group = new THREE.Group();
    group.add(root);
    const anchor = new THREE.Object3D();
    anchor.position.set(0, 0.6, 0);
    group.add(anchor);
    group.userData = { glints: [], anchor, design: false, stats: { metals, stones } };
    return group;
  }

  function disposePiece(m) {
    m.traverse(o => {
      if (o.isInstancedMesh) o.dispose();
      if (o.geometry && !o.geometry.userData.shared && !o.isSprite) o.geometry.dispose();
    });
    m.removeFromParent();
  }

  // Swap in a new piece: the old one spins away, the new one grows in with a burst of dust
  function show(next, animate) {
    // Centre the piece on the turntable, then frame the camera on it
    next.updateMatrixWorld(true);
    _box.setFromObject(next).getCenter(_p);
    const holder = new THREE.Group();
    next.position.sub(_p);
    holder.add(next);
    holder.userData = next.userData;
    const old = piece;
    piece = holder;
    spinner.add(holder);
    const tilt = next.userData.tilt || [0.12, 0]; // each kind of piece has its own display angle; a model faces front
    rig.rotation.set(tilt[0], tilt[1], 0);
    _box.setFromObject(holder).getBoundingSphere(_sphere);
    radius = _sphere.radius;
    fit(!old || !animate);
    if (old) {
      old.userData.glints.forEach(s => (s.visible = false));
      if (animate && !reduceMotion) {
        tween(old.scale, { x: 0.001, y: 0.001, z: 0.001 }, 0.45, ease.inOut, () => disposePiece(old));
        tween(old.rotation, { y: old.rotation.y + 2.2 }, 0.45);
      } else {
        disposePiece(old);
      }
    }
    if (animate && !reduceMotion) {
      holder.scale.setScalar(0.001);
      holder.rotation.y = -2.4;
      setTimeout(() => {
        tween(holder.rotation, { y: 0 }, 1.3, ease.expoOut);
        tween(holder.scale, { x: 1, y: 1, z: 1 }, 1.1, ease.backOut);
        burst('#E3C77A');
      }, old ? 320 : 0);
      sweep();
    }
    wake();
  }

  /* ---------- Camera ---------- */
  const VIEW_DIR = new THREE.Vector3(0, 0.3, 1).normalize();
  function frameDistance() {
    const v = THREE.MathUtils.degToRad(camera.fov / 2), h = Math.atan(Math.tan(v) * camera.aspect);
    return (radius / Math.sin(Math.min(v, h))) * 1.2;
  }
  // Frames the piece; a new piece keeps the angle the viewer was turned to, and Reset view returns to the front
  let framed = false;
  function fit(jump, reset = false) {
    const d = frameDistance();
    controls.minDistance = d * 0.4;
    controls.maxDistance = d * 2.2;
    const dir = framed && !reset ? camera.position.clone().sub(controls.target).normalize() : VIEW_DIR;
    framed = true;
    const to = dir.clone().multiplyScalar(d);
    controls.target.set(0, 0, 0);
    if (jump) camera.position.copy(to);
    else tween(camera.position, { x: to.x, y: to.y, z: to.z }, 1.1);
    camera.lookAt(controls.target);
  }

  /* ---------- Metal and stone ---------- */
  function nudge() {
    if (reduceMotion) return;
    tween(spin, { pulse: Math.round(spin.pulse / TAU) * TAU + TAU }, 1.7, ease.inOut);
  }
  function sweep() {
    if (reduceMotion) return;
    tween(fx, { env: Math.round(fx.env / TAU) * TAU + TAU }, 2.4, ease.inOut);
  }
  function setMetal(id, animate = true) {
    if (state.metal === id) return;
    const first = !state.metal;
    state.metal = id;
    const m = find(METALS, id), c = new THREE.Color(m.color);
    if (!animate || first) {
      metalMat.color.copy(c);
      metalMat.roughness = m.rough;
      return;
    }
    tween(metalMat.color, { r: c.r, g: c.g, b: c.b }, 1, ease.out);
    tween(metalMat, { roughness: m.rough }, 1);
    burst(m.color);
    sweep();
    nudge();
  }
  function setStone(id, animate = true) {
    if (state.stone === id) return;
    const first = !state.stone;
    state.stone = id;
    const g = find(STONES, id), c = new THREE.Color(g.color), u = gemMat.uniforms;
    if (!animate || first) {
      u.uColor.value.copy(c);
      u.uTint.value = g.tint;
      u.uIor.value = g.ior;
      u.uDisp.value = g.disp;
      return;
    }
    tween(u.uColor.value, { r: c.r, g: c.g, b: c.b }, 0.9, ease.out);
    tween(u.uTint, { value: g.tint }, 0.9, ease.out);
    tween(u.uIor, { value: g.ior }, 0.9);
    tween(u.uDisp, { value: g.disp }, 0.9);
    u.uFlash.value = 0.9;
    tween(u.uFlash, { value: 0 }, 1.2, ease.out);
    fx.glintBoost = 1;
    tween(fx, { glintBoost: 0 }, 1.6, ease.out);
    burst(g.tint ? g.color : '#ffffff');
    nudge();
  }

  /* ---------- Theme: a warm glow under the piece on dark backgrounds, a soft shadow on light ones ---------- */
  function setTheme(theme) {
    opts.theme = theme;
    const light = theme === 'light';
    floorMat.color.set(light ? '#4A2A14' : '#E3C77A');
    floorMat.blending = burstMat.blending = light ? THREE.NormalBlending : THREE.AdditiveBlending;
    floorMat.opacity = light ? 0.3 : 0.2;
    floorMat.needsUpdate = burstMat.needsUpdate = true;
    wake();
  }

  /* ---------- The render loop: runs only while the canvas is on screen ---------- */
  const clock = new THREE.Clock();
  let onScreen = true, running = false;
  function updateGlints(t) {
    for (const s of piece.userData.glints) {
      const d = s.userData;
      _m3.getNormalMatrix(s.parent.matrixWorld);
      _n.copy(d.n).applyMatrix3(_m3).normalize();
      s.getWorldPosition(_p);
      _v.copy(camera.position).sub(_p).normalize();
      const facing = THREE.MathUtils.smoothstep(_n.dot(_v), 0.3, 0.9);
      const tw = Math.max(0, Math.sin(t * d.speed + d.phase)) ** 14;
      s.scale.setScalar(Math.max(1e-4, d.size * facing * (tw * fx.sparkle + fx.glintBoost * 0.8)));
    }
  }
  function frame(now) {
    const dt = Math.min(clock.getDelta(), 0.05), t = clock.elapsedTime;
    stepTweens(now);
    if (opts.autoRotate && !dragging) spin.turn += dt * 0.38;
    spinner.rotation.y = spin.turn + spin.pulse;
    const envAngle = fx.env + t * 0.05;
    scene.environmentRotation.y = envAngle;
    gemMat.uniforms.uEnvRot.value.setFromMatrix4(_m4.makeRotationY(-envAngle));
    gemMat.uniforms.uTime.value = t;
    gemMat.uniforms.uSparkle.value = fx.sparkle;
    floor.scale.setScalar(radius * 3);
    floor.position.set(0, -radius * 0.95, 0);
    controls.update();
    scene.updateMatrixWorld();
    if (piece) updateGlints(t);
    updateBurst(dt);
    renderer.render(scene, camera);
  }
  function wake() {
    const want = onScreen && !document.hidden;
    if (want === running) return;
    running = want;
    if (want) clock.getDelta();
    renderer.setAnimationLoop(want ? frame : null);
  }
  const io = new IntersectionObserver(entries => {
    onScreen = entries[entries.length - 1].isIntersecting;
    wake();
  });
  io.observe(canvas);
  const onVisibility = () => wake();
  document.addEventListener('visibilitychange', onVisibility);

  function resize() {
    const w = canvas.clientWidth, h = canvas.clientHeight;
    if (!w || !h) return;
    renderer.setSize(w, h, false);
    camera.aspect = w / h;
    camera.updateProjectionMatrix();
    burstMat.uniforms.uScale.value = (renderer.getPixelRatio() * h * 0.5) / Math.tan(THREE.MathUtils.degToRad(camera.fov / 2));
    if (piece) {
      const d = frameDistance();
      controls.minDistance = d * 0.4;
      controls.maxDistance = d * 2.2;
    }
  }
  const ro = new ResizeObserver(resize);
  ro.observe(canvas);
  resize();
  camera.position.copy(VIEW_DIR).multiplyScalar(6);
  controls.enableZoom = opts.zoom !== false;
  setTheme(opts.theme);

  /* ---------- The viewer's answer ---------- */
  return {
    METALS, STONES, DESIGNS,
    get stats() { return piece ? piece.userData.stats || null : null; },

    /**
     * Shows a piece as its settings describe it.
     * @param {object} cfg  { source: 'design' | 'model', design: { type, stoneSize, bandWidth, prongs, pair }, modelUrl, metal, stone }
     *                      (design.type names the kind too: a ring, kangan, earring or pendant style)
     * @returns {Promise<object|null>} for a model, how many metal and stone parts were found
     */
    async apply(cfg, { animate = true } = {}) {
      const useModel = cfg.source === 'model' && cfg.modelUrl;
      const key = useModel ? `model:${cfg.modelUrl}` : `design:${JSON.stringify(cfg.design)}`;
      if (key !== current) {
        current = key;
        let next;
        try {
          next = useModel ? await buildModel(cfg.modelUrl) : buildDesign({ type: 'solitaire', stoneSize: 1, bandWidth: 1, prongs: 6, pair: false, ...cfg.design });
        } catch (err) {
          if (current === key) current = '';
          throw err;
        }
        if (current !== key) { disposePiece(next); return null; } // a newer choice arrived while this one loaded
        show(next, animate);
      }
      if (cfg.metal) setMetal(cfg.metal, animate);
      if (cfg.stone) setStone(cfg.stone, animate);
      return piece.userData.stats || null;
    },
    setMetal, setStone, setTheme, sweep, burst,
    setAutoRotate(on) { opts.autoRotate = on; },
    setSparkle(on) {
      tween(fx, { sparkle: on ? 1 : 0 }, 0.6);
      if (on) { fx.glintBoost = 1; tween(fx, { glintBoost: 0 }, 1.4); }
    },
    resetView() {
      fit(false, true);
      tween(spin, { turn: Math.round(spin.turn / TAU) * TAU }, 1.1);
    },
    resize,

    /** A picture of the piece: the backdrop, the piece and two lines of text, in a 4:5 frame */
    snapshot({ stops = ['#5B1A2F', '#2E0A16', '#12040A'], title = '', sub = '', light = false } = {}) {
      const ratio = renderer.getPixelRatio();
      renderer.setPixelRatio(Math.max(ratio, Math.min(3, 1800 / Math.max(1, canvas.clientHeight))));
      resize();
      frame(performance.now());
      const src = renderer.domElement, W = 1200, H = 1500;
      const out = document.createElement('canvas');
      out.width = W;
      out.height = H;
      const x = out.getContext('2d');
      const g = x.createRadialGradient(W / 2, H * 0.42, 0, W / 2, H * 0.42, H * 0.75);
      g.addColorStop(0, stops[0]);
      g.addColorStop(0.5, stops[1]);
      g.addColorStop(1, stops[2]);
      x.fillStyle = g;
      x.fillRect(0, 0, W, H);
      let sh = src.height * 0.92, sw = (sh * W) / H;
      if (sw > src.width) [sw, sh] = [src.width, (src.width * H) / W];
      x.drawImage(src, (src.width - sw) / 2, Math.max(0, (src.height - sh) / 2 - src.height * 0.03), sw, sh, 0, 0, W, H);
      renderer.setPixelRatio(ratio);
      resize();
      x.textAlign = 'center';
      x.fillStyle = light ? '#7A5C00' : '#E3C77A';
      x.font = 'italic 400 60px "Cormorant Garamond", serif';
      if (title) x.fillText(title, W / 2, H - 170);
      x.font = '400 26px "Jost", sans-serif';
      x.fillStyle = light ? 'rgba(36,26,23,0.75)' : 'rgba(251,248,242,0.8)';
      if (sub) x.fillText(sub, W / 2, H - 116);
      x.font = '500 20px "Jost", sans-serif';
      x.fillStyle = light ? '#7A5C00' : '#E3C77A';
      if ('letterSpacing' in x) x.letterSpacing = '8px';
      x.fillText('MANGALAM JEWELLERS', W / 2, H - 56);
      return out.toDataURL('image/png');
    },

    dispose() {
      renderer.setAnimationLoop(null);
      io.disconnect();
      ro.disconnect();
      document.removeEventListener('visibilitychange', onVisibility);
      controls.dispose();
      if (piece) disposePiece(piece);
      pmrem.dispose();
      envMap.dispose();
      cubeRT.dispose();
      renderer.dispose();
    },
  };
}
