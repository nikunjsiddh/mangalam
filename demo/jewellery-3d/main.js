/* Mangalam Jewellers — 3D ring studio (demo).
 * The ring is built in code (band, prongs, faceted stones) and drawn with three.js. Scrolling the story moves it
 * (GSAP ScrollTrigger); the studio lets the visitor change the setting, stone, metal and backdrop, and every
 * change plays a small show: the ring turns, light sweeps across the metal, the stones flash and gold dust bursts. */
import * as THREE from 'three';
import { OrbitControls } from 'three/addons/controls/OrbitControls.js';

const { gsap, ScrollTrigger } = window;
const TAU = Math.PI * 2;
const $ = (s, root = document) => root.querySelector(s);
const $$ = (s, root = document) => [...root.querySelectorAll(s)];
const wait = ms => new Promise(r => setTimeout(r, ms));
const reduceMotion = matchMedia('(prefers-reduced-motion: reduce)').matches;

/* ---------- 1. The choices ---------- */
const STYLES = [
  { id: 'solitaire', name: 'Solitaire', sub: 'Six-prong classic' },
  { id: 'halo', name: 'Halo', sub: 'Pavé halo & band' },
  { id: 'trilogy', name: 'Trilogy', sub: 'Three stones' },
];
const GEMS = [
  { id: 'diamond', name: 'Diamond', sub: 'Heera', color: '#ffffff', tint: 0, ior: 2.42, disp: 0.07 },
  { id: 'ruby', name: 'Ruby', sub: 'Manik', color: '#e3133f', tint: 1, ior: 1.77, disp: 0.025 },
  { id: 'emerald', name: 'Emerald', sub: 'Panna', color: '#12b15f', tint: 1, ior: 1.58, disp: 0.02 },
  { id: 'sapphire', name: 'Blue sapphire', sub: 'Neelam', color: '#1f4ce6', tint: 1, ior: 1.77, disp: 0.025 },
  { id: 'pukhraj', name: 'Yellow sapphire', sub: 'Pukhraj', color: '#ffb300', tint: 1, ior: 1.77, disp: 0.025 },
  { id: 'amethyst', name: 'Amethyst', sub: 'Jamunia', color: '#9b3fe3', tint: 1, ior: 1.55, disp: 0.02 },
];
const METALS = [
  { id: 'yellow', name: 'Yellow gold', sub: '22 karat', color: '#f5c362', rough: 0.13 },
  { id: 'rose', name: 'Rose gold', sub: '18 karat', color: '#f2ae95', rough: 0.14 },
  { id: 'white', name: 'White gold', sub: '18 karat', color: '#ecebe7', rough: 0.11 },
  { id: 'platinum', name: 'Platinum', sub: 'Pt 950', color: '#cdd2d8', rough: 0.2 },
];
const BACKDROPS = [
  { id: 'wine', name: 'Midnight wine', sub: 'House colour', stops: ['#5B1A2F', '#2E0A16', '#12040A'] },
  { id: 'onyx', name: 'Onyx', sub: 'Gallery black', stops: ['#2B2521', '#151110', '#070606'] },
  { id: 'ivory', name: 'Ivory silk', sub: 'Daylight', stops: ['#FFFFFF', '#FBF8F2', '#EFE4CF'] },
  { id: 'champagne', name: 'Champagne', sub: 'Warm glow', stops: ['#FCF3E2', '#EEDCBB', '#D2B48B'] },
];
const STYLE_ICONS = {
  solitaire: '<svg viewBox="0 0 48 48"><circle cx="24" cy="32" r="11"/><path d="M18.5 11h11l3.5 4.5-9 8.5-9-8.5z"/><path d="M15 15.5h18"/></svg>',
  halo: '<svg viewBox="0 0 48 48"><circle cx="24" cy="34" r="10"/><circle cx="24" cy="15" r="9" stroke-dasharray="2 2.6"/><path d="M20.5 11.5h7l2.5 3-6 5.5-6-5.5z"/></svg>',
  trilogy: '<svg viewBox="0 0 48 48"><circle cx="24" cy="32" r="11"/><path d="M20 10h8l3 4-7 7-7-7z"/><path d="M8 16h6l2 3-5 4.5L6 19zM34 16h6l2 3-5 4.5-5-4.5z"/></svg>',
};

const state = { style: 'solitaire', gem: 'diamond', metal: 'yellow', backdrop: 'wine', mode: 'story', tab: 'setting', autoRotate: !reduceMotion, sparkle: true };
const find = (list, id) => list.find(o => o.id === id);

/* ---------- 2. Renderer, camera, light ---------- */
function fail(err) {
  console.error(err);
  $('#fallback').hidden = false;
  $('#loader').classList.add('is-done');
}
if (!gsap || !ScrollTrigger) {
  fail(new Error('GSAP did not load'));
  throw new Error('GSAP did not load');
}
gsap.registerPlugin(ScrollTrigger);
ScrollTrigger.config({ ignoreMobileResize: true });
// The story always starts at the top, with the entrance
if ('scrollRestoration' in history) history.scrollRestoration = 'manual';
scrollTo(0, 0);

const canvas = $('#stage');
let renderer;
try {
  renderer = new THREE.WebGLRenderer({ canvas, antialias: true, alpha: true, powerPreference: 'high-performance' });
} catch (err) {
  fail(err);
  throw err;
}
renderer.setPixelRatio(Math.min(devicePixelRatio, 2));
renderer.setSize(innerWidth, innerHeight, false);
renderer.setClearColor(0x000000, 0);
renderer.toneMapping = THREE.NeutralToneMapping;
renderer.toneMappingExposure = 1.05;

const scene = new THREE.Scene();
const camera = new THREE.PerspectiveCamera(32, innerWidth / innerHeight, 0.1, 100);
const STORY_CAM = new THREE.Vector3(0, 0, 7);
camera.position.copy(STORY_CAM);

const key = new THREE.DirectionalLight(0xfff1dc, 1.4);
key.position.set(3, 5, 4);
scene.add(key);

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
const studioScene = buildStudio();
const pmrem = new THREE.PMREMGenerator(renderer);
scene.environment = pmrem.fromScene(studioScene, 0.01).texture;
const cubeRT = new THREE.WebGLCubeRenderTarget(512, { type: THREE.HalfFloatType });
new THREE.CubeCamera(0.1, 60, cubeRT).update(renderer, studioScene);

/* ---------- 3. Materials ---------- */
const metalMat = new THREE.MeshStandardMaterial({ color: METALS[0].color, metalness: 1, roughness: METALS[0].rough, envMapIntensity: 1.35 });

// A faceted-stone shader: the view ray refracts into the stone (one ray per colour, for fire), bounces off the
// pavilion twice and leaves through the crown; the facet's reflection is added by Fresnel.
const gemMat = new THREE.ShaderMaterial({
  uniforms: {
    uEnv: { value: cubeRT.texture },
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
const glintMat = new THREE.SpriteMaterial({ map: glintTexture(), color: 0xfff6e6, blending: THREE.AdditiveBlending, depthTest: false, depthWrite: false, transparent: true, toneMapped: false });

/* ---------- 4. Geometry: the stone, the band, the setting ---------- */
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

// The shank: a rounded-square profile swept around the finger. The ring stands in the XY plane (its hole faces
// the camera), the top is at +Y, and `taper` slims it towards the setting.
const R_IN = 0.86;
function makeBand({ thick = 0.13, width = 0.3, taper = 0, round = 3.4, segs = 256, prof = 40 } = {}) {
  const atTop = phi => Math.max(0, Math.sin(phi)) ** 2;
  const thickAt = phi => thick * (1 - taper * 0.35 * atTop(phi));
  const widthAt = phi => width * (1 - taper * atTop(phi));
  const pos = new Float32Array(segs * prof * 3);
  let p = 0;
  for (let i = 0; i < segs; i++) {
    const phi = (i / segs) * TAU, th = thickAt(phi), wd = widthAt(phi), cp = Math.cos(phi), sp = Math.sin(phi);
    for (let j = 0; j < prof; j++) {
      const t = (j / prof) * TAU, c = Math.cos(t), s = Math.sin(t);
      const u = Math.sign(c) * Math.abs(c) ** (2 / round), v = Math.sign(s) * Math.abs(s) ** (2 / round);
      const r = R_IN + th * (u * 0.5 + 0.5);
      pos[p++] = r * cp; pos[p++] = r * sp; pos[p++] = v * wd * 0.5;
    }
  }
  const idx = [];
  for (let i = 0; i < segs; i++) {
    for (let j = 0; j < prof; j++) {
      const i2 = (i + 1) % segs, j2 = (j + 1) % prof;
      const a = i * prof + j, b = i2 * prof + j, c = i2 * prof + j2, d = i * prof + j2;
      idx.push(a, b, d, b, c, d);
    }
  }
  const g = new THREE.BufferGeometry();
  g.setAttribute('position', new THREE.BufferAttribute(pos, 3));
  g.setIndex(idx);
  g.computeVertexNormals();
  if (g.attributes.normal.getY((segs / 4) * prof) < 0) { // the outer face at the top must face up
    for (let k = 0; k < idx.length; k += 3) [idx[k + 1], idx[k + 2]] = [idx[k + 2], idx[k + 1]];
    g.setIndex(idx);
    g.computeVertexNormals();
  }
  return { geometry: g, outerAt: phi => R_IN + thickAt(phi), widthAt };
}

function addGlint(ctx, parent, pos, normal, size) {
  const s = new THREE.Sprite(glintMat);
  s.position.copy(pos);
  s.scale.setScalar(1e-4);
  s.userData = { n: normal.clone().normalize(), size, phase: Math.random() * TAU, speed: 0.7 + Math.random() * 1.1 };
  parent.add(s);
  ctx.glints.push(s);
}
// A point on the crown of a stone `D` across, `f` of the way out from the centre
function crownPoint(D, a, f) {
  const r = f * 0.5, y = r <= 0.28 ? 0.16 : 0.16 - ((r - 0.28) / 0.22) * 0.148;
  return new THREE.Vector3(Math.cos(a) * r * D, (y + 0.015) * D, Math.sin(a) * r * D);
}
const crownNormal = (a, f) => { const k = f * 0.5 <= 0.28 ? 0 : 0.75; return new THREE.Vector3(Math.cos(a) * k, 1, Math.sin(a) * k); };

// A stone in a claw setting, raised `lift` above the band at angle `phi` (π/2 is the top of the ring)
function mount(ctx, band, { phi = Math.PI / 2, D, lift, prongs = 4, a0 = Math.PI / 4, rail = true, base = false, glints = 3 }) {
  const g = D / 2, pr = Math.max(0.016, 0.04 * D);
  const h = new THREE.Group();
  const rr = band.outerAt(phi) + lift;
  h.position.set(Math.cos(phi) * rr, Math.sin(phi) * rr, 0);
  h.rotation.z = phi - Math.PI / 2;
  const stone = new THREE.Mesh(GEM, gemMat);
  stone.scale.setScalar(D);
  h.add(stone);
  for (let k = 0; k < prongs; k++) {
    const a = a0 + (k / prongs) * TAU, c = Math.cos(a), s = Math.sin(a);
    const at = (r, y) => new THREE.Vector3(r * c, y, r * s);
    const tip = at(0.86 * g, 0.095 * D);
    const path = new THREE.CatmullRomCurve3([at(0.26 * g, -lift - 0.05), at(0.5 * g, -0.33 * D), at(g + pr * 0.6, -0.03 * D), at(0.98 * g + pr * 0.4, 0.05 * D), tip]);
    h.add(new THREE.Mesh(new THREE.TubeGeometry(path, 48, pr, 10), metalMat));
    const cap = new THREE.Mesh(SPHERE, metalMat);
    cap.position.copy(tip);
    cap.scale.setScalar(pr * 1.2);
    h.add(cap);
  }
  if (rail) {
    const t = new THREE.Mesh(new THREE.TorusGeometry(0.76 * g, pr * 0.72, 10, 80), metalMat);
    t.rotation.x = Math.PI / 2;
    t.position.y = -0.19 * D;
    h.add(t);
  }
  if (base) {
    const t = new THREE.Mesh(new THREE.TorusGeometry(0.36 * g, pr * 0.8, 10, 64), metalMat);
    t.rotation.x = Math.PI / 2;
    t.position.y = -lift + 0.025;
    h.add(t);
  }
  for (let k = 0; k < glints; k++) {
    const a = Math.random() * TAU, f = 0.15 + Math.random() * 0.75;
    addGlint(ctx, h, crownPoint(D, a, f), crownNormal(a, f), D * 0.9);
  }
  ctx.group.add(h);
  return h;
}

const _m = new THREE.Matrix4(), _q = new THREE.Quaternion(), _up = new THREE.Vector3(0, 1, 0), _dir = new THREE.Vector3(), _pos = new THREE.Vector3(), _scl = new THREE.Vector3();
const newRing = () => ({ group: new THREE.Group(), glints: [] });
function finish(ctx, anchor) {
  ctx.group.userData = { glints: ctx.glints, anchor };
  return ctx.group;
}

function buildSolitaire() {
  const ctx = newRing();
  const band = makeBand({ thick: 0.135, width: 0.3, taper: 0.35, round: 3.6 });
  ctx.group.add(new THREE.Mesh(band.geometry, metalMat));
  const h = mount(ctx, band, { D: 0.62, lift: 0.32, prongs: 6, a0: Math.PI / 6, base: true, glints: 6 });
  return finish(ctx, h);
}

function buildHalo() {
  const ctx = newRing();
  const band = makeBand({ thick: 0.115, width: 0.21, taper: 0.12, round: 3.2 });
  ctx.group.add(new THREE.Mesh(band.geometry, metalMat));
  const D = 0.44, g = D / 2, lift = 0.27;
  const h = mount(ctx, band, { D, lift, prongs: 4, a0: Math.PI / 4, glints: 4 });

  // The halo: a ring of small stones on a metal seat, with a bead between each pair
  const n = 18, hr = g + 0.066, d = 0.088;
  const stones = new THREE.InstancedMesh(GEM, gemMat, n);
  const beads = new THREE.InstancedMesh(SPHERE, metalMat, n);
  for (let k = 0; k < n; k++) {
    const a = (k / n) * TAU, c = Math.cos(a), s = Math.sin(a);
    _q.setFromUnitVectors(_up, _dir.set(c * 0.28, 1, s * 0.28).normalize());
    stones.setMatrixAt(k, _m.compose(_pos.set(c * hr, -0.006, s * hr), _q, _scl.setScalar(d)));
    const b = a + Math.PI / n;
    beads.setMatrixAt(k, _m.compose(_pos.set(Math.cos(b) * hr, 0.012, Math.sin(b) * hr), _q.identity(), _scl.setScalar(0.0125)));
    if (k % 5 === 0) addGlint(ctx, h, new THREE.Vector3(c * hr, 0.03, s * hr), new THREE.Vector3(c * 0.28, 1, s * 0.28), 0.32);
  }
  const seatGeo = new THREE.TorusGeometry(hr, 0.046, 12, 96);
  seatGeo.rotateX(Math.PI / 2);
  const seat = new THREE.Mesh(seatGeo, metalMat);
  seat.scale.y = 0.55;
  seat.position.y = -0.045;
  h.add(stones, beads, seat);
  for (let k = 0; k < 4; k++) {
    const a = (k / 4) * TAU, c = Math.cos(a), s = Math.sin(a);
    const at = (r, y) => new THREE.Vector3(r * c, y, r * s);
    const path = new THREE.CatmullRomCurve3([at(0.2 * g, -lift - 0.04), at(0.62 * hr, -lift * 0.5), at(hr, -0.05)]);
    h.add(new THREE.Mesh(new THREE.TubeGeometry(path, 32, 0.016, 8), metalMat));
  }

  // Pavé down both shoulders
  const per = 8, pave = new THREE.InstancedMesh(GEM, gemMat, per * 2), pb = new THREE.InstancedMesh(SPHERE, metalMat, per * 4);
  let i = 0, j = 0;
  for (const side of [-1, 1]) {
    for (let k = 0; k < per; k++) {
      const phi = Math.PI / 2 + side * (0.44 + k * 0.1), r = band.outerAt(phi);
      _dir.set(Math.cos(phi), Math.sin(phi), 0);
      _q.setFromUnitVectors(_up, _dir);
      pave.setMatrixAt(i++, _m.compose(_pos.copy(_dir).multiplyScalar(r - 0.006), _q, _scl.setScalar(0.072)));
      for (const z of [-0.05, 0.05]) pb.setMatrixAt(j++, _m.compose(_pos.copy(_dir).multiplyScalar(r + 0.002).setZ(z), _q, _scl.setScalar(0.012)));
      if (k === 2) addGlint(ctx, ctx.group, _dir.clone().multiplyScalar(r + 0.03), _dir, 0.22);
    }
  }
  ctx.group.add(pave, pb);
  return finish(ctx, h);
}

function buildTrilogy() {
  const ctx = newRing();
  const band = makeBand({ thick: 0.13, width: 0.27, taper: 0.15, round: 3.4 });
  ctx.group.add(new THREE.Mesh(band.geometry, metalMat));
  const h = mount(ctx, band, { D: 0.5, lift: 0.29, prongs: 4, a0: Math.PI / 4, base: true, glints: 4 });
  for (const side of [-1, 1]) mount(ctx, band, { phi: Math.PI / 2 + side * 0.42, D: 0.32, lift: 0.2, prongs: 4, a0: Math.PI / 4, glints: 2 });
  return finish(ctx, h);
}

const BUILDERS = { solitaire: buildSolitaire, halo: buildHalo, trilogy: buildTrilogy };
function disposeModel(m) {
  m.traverse(o => {
    if (o.isInstancedMesh) o.dispose();
    if (o.geometry && !o.geometry.userData.shared && !o.isSprite) o.geometry.dispose();
  });
  m.removeFromParent();
}

/* ---------- 5. The scene graph: rig (story / studio pose) › spinner (turntable) › ring ---------- */
const rig = new THREE.Group();
const spinner = new THREE.Group();
spinner.position.y = -0.2; // the stone makes the ring top-heavy; this centres band and stone together
rig.add(spinner);
scene.add(rig);
let model = null;

// The glow (dark backdrops) or soft shadow (light backdrops) under the ring in the studio
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
const floorMat = new THREE.MeshBasicMaterial({ map: radialTexture(), color: '#E3C77A', transparent: true, opacity: 0, depthWrite: false, blending: THREE.AdditiveBlending, toneMapped: false });
const floor = new THREE.Mesh(new THREE.PlaneGeometry(3.6, 3.6), floorMat);
floor.rotation.x = -Math.PI / 2;
floor.userData.max = 0.22;
scene.add(floor);

/* ---------- 6. Particles: drifting gold dust, and a burst on every change ---------- */
const pointScale = () => (renderer.getPixelRatio() * innerHeight * 0.5) / Math.tan(THREE.MathUtils.degToRad(camera.fov / 2));

const DUST = 240;
const dustGeo = new THREE.BufferGeometry();
{
  const p = new Float32Array(DUST * 3), seed = new Float32Array(DUST);
  for (let i = 0; i < DUST; i++) {
    p[i * 3] = (Math.random() - 0.5) * 14;
    p[i * 3 + 1] = (Math.random() - 0.5) * 8;
    p[i * 3 + 2] = -5 + Math.random() * 7;
    seed[i] = Math.random();
  }
  dustGeo.setAttribute('position', new THREE.BufferAttribute(p, 3));
  dustGeo.setAttribute('aSeed', new THREE.BufferAttribute(seed, 1));
}
const dustMat = new THREE.ShaderMaterial({
  uniforms: { uTime: { value: 0 }, uScale: { value: pointScale() }, uColor: { value: new THREE.Color('#E3C77A') }, uOpacity: { value: 0.8 } },
  vertexShader: `
    uniform float uTime, uScale; attribute float aSeed; varying float vA;
    void main(){
      vec3 p = position;
      p.y = mod(p.y + uTime * (0.04 + aSeed * 0.07) + 4.0, 8.0) - 4.0;
      p.x += sin(uTime * 0.3 + aSeed * 20.0) * 0.15;
      vec4 mv = modelViewMatrix * vec4(p, 1.0);
      gl_Position = projectionMatrix * mv;
      gl_PointSize = (0.012 + aSeed * 0.03) * uScale / -mv.z;
      vA = 0.2 + 0.8 * pow(0.5 + 0.5 * sin(uTime * (0.6 + aSeed) + aSeed * 40.0), 3.0);
    }`,
  fragmentShader: `
    uniform vec3 uColor; uniform float uOpacity; varying float vA;
    void main(){
      float a = smoothstep(0.5, 0.0, length(gl_PointCoord - 0.5)) * vA * uOpacity;
      if (a < 0.01) discard;
      gl_FragColor = vec4(uColor, a);
      #include <colorspace_fragment>
    }`,
  transparent: true,
  depthWrite: false,
  blending: THREE.AdditiveBlending,
});
const dust = new THREE.Points(dustGeo, dustMat);
dust.frustumCulled = false;
scene.add(dust);

const BURST = 160;
const bPos = new Float32Array(BURST * 3), bVel = new Float32Array(BURST * 3), bLife = new Float32Array(BURST), bDecay = new Float32Array(BURST), bSize = new Float32Array(BURST);
const burstGeo = new THREE.BufferGeometry();
burstGeo.setAttribute('position', new THREE.BufferAttribute(bPos, 3).setUsage(THREE.DynamicDrawUsage));
burstGeo.setAttribute('aLife', new THREE.BufferAttribute(bLife, 1).setUsage(THREE.DynamicDrawUsage));
burstGeo.setAttribute('aSize', new THREE.BufferAttribute(bSize, 1));
const burstMat = new THREE.ShaderMaterial({
  uniforms: { uScale: { value: pointScale() }, uColor: { value: new THREE.Color('#ffffff') } },
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
  blending: THREE.AdditiveBlending,
});
const burstPts = new THREE.Points(burstGeo, burstMat);
burstPts.frustumCulled = false;
scene.add(burstPts);
let burstAlive = false;

const _p = new THREE.Vector3(), _n = new THREE.Vector3(), _v = new THREE.Vector3(), _m3 = new THREE.Matrix3(), _m4 = new THREE.Matrix4();
function burst(color) {
  if (reduceMotion || !model) return;
  burstMat.uniforms.uColor.value.set(color);
  model.userData.anchor.getWorldPosition(_p);
  for (let i = 0; i < BURST; i++) {
    const u = Math.random() * 2 - 1, th = Math.random() * TAU, sq = Math.sqrt(1 - u * u), sp = 0.8 + Math.random() * 2.4;
    bPos[i * 3] = _p.x; bPos[i * 3 + 1] = _p.y; bPos[i * 3 + 2] = _p.z;
    bVel[i * 3] = sq * Math.cos(th) * sp; bVel[i * 3 + 1] = u * sp * 0.8 + 0.5; bVel[i * 3 + 2] = sq * Math.sin(th) * sp;
    bLife[i] = 1;
    bDecay[i] = 0.55 + Math.random() * 0.8;
    bSize[i] = 0.03 + Math.random() * 0.08;
  }
  burstGeo.attributes.aSize.needsUpdate = true;
  burstAlive = true;
}
function updateBurst(dt) {
  if (!burstAlive) return;
  const drag = Math.exp(-2.6 * dt);
  let alive = false;
  for (let i = 0; i < BURST; i++) {
    if (bLife[i] <= 0) continue;
    const k = i * 3;
    bVel[k] *= drag; bVel[k + 1] = bVel[k + 1] * drag - 0.6 * dt; bVel[k + 2] *= drag;
    bPos[k] += bVel[k] * dt; bPos[k + 1] += bVel[k + 1] * dt; bPos[k + 2] += bVel[k + 2] * dt;
    bLife[i] = Math.max(0, bLife[i] - dt * bDecay[i]);
    if (bLife[i] > 0) alive = true;
  }
  burstGeo.attributes.position.needsUpdate = true;
  burstGeo.attributes.aLife.needsUpdate = true;
  burstAlive = alive;
}

/* ---------- 7. Poses: where the ring sits in each part of the story, and in the studio ---------- */
const isPhone = () => innerWidth < 760;
function poses() {
  if (isPhone()) {
    return {
      hero: { x: 0, y: 0.98, s: 0.52, rx: 0.28, ry: -0.65, rz: 0.12 },
      forever: { x: 0, y: 1.02, s: 0.5, rx: 0.06, ry: Math.PI - 0.9, rz: -0.32 },
      emotions: { x: 0, y: 0.98, s: 0.6, rx: 1.05, ry: TAU - 0.2, rz: 0 },
      studio: { x: 0, y: 0, s: 1, rx: 0.3, ry: -0.5, rz: 0 },
    };
  }
  const halfW = Math.tan(THREE.MathUtils.degToRad(camera.fov / 2)) * STORY_CAM.z * camera.aspect;
  const side = Math.min(halfW * 0.42, 1.9), k = Math.min(1, halfW / 3.1);
  return {
    hero: { x: side, y: 0, s: k, rx: 0.28, ry: -0.65, rz: 0.12 },
    forever: { x: -side, y: 0, s: 1.1 * k, rx: 0.06, ry: Math.PI - 0.9, rz: -0.32 },
    emotions: { x: side * 0.92, y: -0.05, s: 1.25 * k, rx: 1.05, ry: TAU - 0.2, rz: 0 },
    studio: { x: 0, y: 0, s: 1, rx: 0.3, ry: -0.5, rz: 0 },
  };
}
// In the studio the camera backs off until the ring fits between the summary and the dock, then aims so the
// ring sits in the middle of that space.
function studioCam() {
  const tan = Math.tan(THREE.MathUtils.degToRad(camera.fov / 2));
  const top = isPhone() ? 128 : 156;
  const bottom = innerHeight - $('.dock').offsetHeight - (isPhone() ? 54 : 66);
  const room = Math.max(140, bottom - top);
  const d = THREE.MathUtils.clamp(Math.max((2.7 * innerHeight) / (room * 2 * tan), 2.5 / (0.86 * 2 * tan * camera.aspect)), 4, 16);
  const shift = (innerHeight / 2 - (top + bottom) / 2) / (innerHeight / (2 * d * tan));
  controls.maxDistance = Math.max(14, d * 1.4);
  return { pos: new THREE.Vector3(0, 0.15, 1).normalize().multiplyScalar(d).add(new THREE.Vector3(0, -shift, 0)), target: -shift };
}

const POSE_KEYS = ['x', 'y', 's', 'rx', 'ry', 'rz'];
const storyPose = { ...poses().hero };
let studioPose = poses().studio;
const blend = { v: 0 };                 // 0 = story, 1 = studio
const intro = { s: 1, ry: 0, y: 0 };    // the entrance after loading
const spin = { turn: 0, pulse: 0 };     // turntable and the turn played on every change
const fx = { env: 0, sparkle: 1, glintBoost: 0 };
const pointer = { x: 0, y: 0, sx: 0, sy: 0 };

/* ---------- 8. Applying a choice ---------- */
function nudge() {
  if (reduceMotion) return;
  gsap.to(spin, { pulse: Math.round(spin.pulse / TAU) * TAU + TAU, duration: 1.7, ease: 'power3.inOut', overwrite: true });
}
function sweepLight() {
  if (reduceMotion) return;
  gsap.to(fx, { env: Math.round(fx.env / TAU) * TAU + TAU, duration: 2.4, ease: 'power2.inOut', overwrite: 'auto' });
}

function applyGem(id, animate = true) {
  const g = find(GEMS, id), c = new THREE.Color(g.color), u = gemMat.uniforms;
  if (!animate) {
    u.uColor.value.copy(c);
    Object.assign(u.uTint, { value: g.tint });
    Object.assign(u.uIor, { value: g.ior });
    Object.assign(u.uDisp, { value: g.disp });
    return;
  }
  gsap.to(u.uColor.value, { r: c.r, g: c.g, b: c.b, duration: 0.9, ease: 'power2.out' });
  gsap.to(u.uTint, { value: g.tint, duration: 0.9, ease: 'power2.out' });
  gsap.to(u.uIor, { value: g.ior, duration: 0.9 });
  gsap.to(u.uDisp, { value: g.disp, duration: 0.9 });
  gsap.fromTo(u.uFlash, { value: 0.9 }, { value: 0, duration: 1.2, ease: 'power2.out' });
  gsap.fromTo(fx, { glintBoost: 1 }, { glintBoost: 0, duration: 1.6, ease: 'power2.out' });
  burst(g.tint ? g.color : '#ffffff');
  nudge();
}

function applyMetal(id, animate = true) {
  const m = find(METALS, id), c = new THREE.Color(m.color);
  if (!animate) {
    metalMat.color.copy(c);
    metalMat.roughness = m.rough;
    return;
  }
  gsap.to(metalMat.color, { r: c.r, g: c.g, b: c.b, duration: 1, ease: 'power2.out' });
  gsap.to(metalMat, { roughness: m.rough, duration: 1 });
  burst(m.color);
  sweepLight();
  nudge();
}

function applyStyle(id, animate = true) {
  const next = BUILDERS[id]();
  const old = model;
  model = next;
  spinner.add(next);
  if (!animate || !old) {
    if (old) disposeModel(old);
    return;
  }
  old.userData.glints.forEach(s => (s.visible = false));
  gsap.to(old.scale, { x: 0.001, y: 0.001, z: 0.001, duration: 0.45, ease: 'power2.in', onComplete: () => disposeModel(old) });
  gsap.to(old.rotation, { y: '+=2.2', duration: 0.45, ease: 'power2.in' });
  next.scale.setScalar(0.001);
  gsap.fromTo(next.rotation, { y: -2.4 }, { y: 0, duration: 1.3, delay: 0.32, ease: 'expo.out' });
  gsap.to(next.scale, { x: 1, y: 1, z: 1, duration: 1.1, delay: 0.32, ease: 'back.out(1.5)', onStart: () => burst('#E3C77A') });
  sweepLight();
}

const isLight = id => id === 'ivory' || id === 'champagne';
function applyBackdrop(id) {
  document.body.dataset.backdrop = id;
  const light = isLight(id), blending = light ? THREE.NormalBlending : THREE.AdditiveBlending;
  for (const mat of [dustMat, burstMat, floorMat]) {
    mat.blending = blending;
    mat.needsUpdate = true;
  }
  dustMat.uniforms.uColor.value.set(light ? '#A57E00' : '#E3C77A');
  dustMat.uniforms.uOpacity.value = light ? 0.5 : 0.8;
  floorMat.color.set(light ? '#4A2A14' : '#E3C77A');
  floor.userData.max = light ? 0.32 : 0.22;
  $('meta[name="theme-color"]').content = light ? '#FBF8F2' : id === 'onyx' ? '#070606' : '#1D060E';
}

const APPLY = { style: applyStyle, gem: applyGem, metal: applyMetal, backdrop: applyBackdrop };
function choose(key, id) {
  if (state[key] === id) {
    nudge();
    return;
  }
  state[key] = id;
  APPLY[key](id, true);
  syncUI();
}

/* ---------- 9. Studio interface ---------- */
const optionsEl = $('#options');
const summaryEl = $('#summary');
const chipsEl = $('#storyGems');
const TABS = { setting: ['style', STYLES], gem: ['gem', GEMS], metal: ['metal', METALS], backdrop: ['backdrop', BACKDROPS] };

function el(tag, cls, text) {
  const e = document.createElement(tag);
  if (cls) e.className = cls;
  if (text) e.textContent = text;
  return e;
}
function swatch(tab, o) {
  const s = el('span', 'opt__swatch');
  s.setAttribute('aria-hidden', 'true');
  if (tab === 'setting') {
    s.classList.add('sw-style');
    s.innerHTML = STYLE_ICONS[o.id];
  } else if (tab === 'gem') {
    s.classList.add('sw-gem');
    if (!o.tint) s.classList.add('sw-gem--diamond');
    s.style.setProperty('--c', o.color);
  } else if (tab === 'metal') {
    s.classList.add('sw-metal');
    s.style.setProperty('--c', o.color);
  } else {
    s.classList.add('sw-backdrop');
    s.style.background = `radial-gradient(circle at 35% 30%, ${o.stops[0]}, ${o.stops[1]} 55%, ${o.stops[2]})`;
  }
  return s;
}
function ripple(btn, e) {
  const r = el('span', 'ripple'), box = btn.getBoundingClientRect();
  r.style.left = `${(e.clientX || box.left + box.width / 2) - box.left}px`;
  r.style.top = `${(e.clientY || box.top + box.height / 2) - box.top}px`;
  btn.append(r);
  setTimeout(() => r.remove(), 900);
}
function renderOptions() {
  const [key, list] = TABS[state.tab];
  optionsEl.replaceChildren(...list.map((o, i) => {
    const b = el('button', 'opt');
    b.type = 'button';
    b.dataset.id = o.id;
    b.style.setProperty('--i', i);
    b.setAttribute('aria-pressed', String(state[key] === o.id));
    b.append(swatch(state.tab, o), el('span', 'opt__name', o.name), el('span', 'opt__sub', o.sub));
    b.addEventListener('click', e => {
      ripple(b, e);
      choose(key, o.id);
    });
    return b;
  }));
}
$$('.tab').forEach(t => t.addEventListener('click', () => {
  state.tab = t.dataset.tab;
  $$('.tab').forEach(x => x.setAttribute('aria-selected', String(x === t)));
  renderOptions();
}));

GEMS.forEach(g => {
  const b = el('button', 'chip');
  b.type = 'button';
  b.dataset.id = g.id;
  const dot = el('span', `gem-dot${g.tint ? '' : ' gem-dot--diamond'}`);
  dot.style.setProperty('--c', g.color);
  b.append(dot, g.name);
  b.addEventListener('click', () => choose('gem', g.id));
  chipsEl.append(b);
});

const summaryText = () => `${find(STYLES, state.style).name} · ${find(GEMS, state.gem).name} · ${find(METALS, state.metal).name}`;
let summaryTimer;
function syncUI() {
  const [key] = TABS[state.tab];
  $$('.opt', optionsEl).forEach(b => b.setAttribute('aria-pressed', String(state[key] === b.dataset.id)));
  $$('.chip', chipsEl).forEach(b => b.setAttribute('aria-pressed', String(state.gem === b.dataset.id)));
  const text = summaryText();
  if (summaryEl.textContent === text) return;
  clearTimeout(summaryTimer);
  summaryEl.classList.add('is-changing');
  summaryTimer = setTimeout(() => {
    summaryEl.textContent = text;
    summaryEl.classList.remove('is-changing');
  }, 300);
}

const controls = new OrbitControls(camera, canvas);
Object.assign(controls, { enabled: false, enableDamping: true, dampingFactor: 0.07, enablePan: false, minDistance: 3.4, maxDistance: 14, rotateSpeed: 0.7, zoomSpeed: 0.8, minPolarAngle: 0.15, maxPolarAngle: Math.PI * 0.72 });
let dragging = false, resumeTimer;
controls.addEventListener('start', () => {
  dragging = true;
  clearTimeout(resumeTimer);
  $('#hint').classList.add('is-hidden');
});
controls.addEventListener('end', () => {
  resumeTimer = setTimeout(() => (dragging = false), 1400);
});

function moveCamera(pos, targetY, duration = 1.4) {
  gsap.to(camera.position, { x: pos.x, y: pos.y, z: pos.z, duration, ease: 'power3.inOut', overwrite: true });
  gsap.to(controls.target, { x: 0, y: targetY, z: 0, duration, ease: 'power3.inOut', overwrite: true, onUpdate: () => camera.lookAt(controls.target) });
}

function enterStudio() {
  if (state.mode === 'studio') return;
  state.mode = 'studio';
  document.body.classList.add('is-studio');
  $('#studio').setAttribute('aria-hidden', 'false');
  studioPose = poses().studio;
  renderOptions();
  syncUI();
  controls.enabled = true;
  const c = studioCam();
  moveCamera(c.pos, c.target);
  gsap.to(blend, { v: 1, duration: 1.4, ease: 'power3.inOut', overwrite: true });
  sweepLight();
  setTimeout(() => burst('#E3C77A'), 700);
}
function exitStudio() {
  if (state.mode !== 'studio') return;
  state.mode = 'story';
  document.body.classList.remove('is-studio');
  $('#studio').setAttribute('aria-hidden', 'true');
  controls.enabled = false;
  moveCamera(STORY_CAM, 0, 1.2);
  gsap.to(blend, { v: 0, duration: 1.2, ease: 'power3.inOut', overwrite: true });
  gsap.to(spin, { turn: Math.round(spin.turn / TAU) * TAU, duration: 1.2, ease: 'power3.inOut' });
}
$$('[data-enter-studio]').forEach(b => b.addEventListener('click', enterStudio));
$('#studioToggle').addEventListener('click', () => (state.mode === 'studio' ? exitStudio() : enterStudio()));
$$('[data-goto]').forEach(b => b.addEventListener('click', () => {
  exitStudio();
  $(`#${b.dataset.goto}`).scrollIntoView({ behavior: reduceMotion ? 'auto' : 'smooth' });
}));
addEventListener('keydown', e => {
  if (e.key === 'Escape' && state.mode === 'studio' && !$('#shot').open) exitStudio();
});

// Tools
const toolRotate = $('#toolRotate'), toolSparkle = $('#toolSparkle');
toolRotate.setAttribute('aria-pressed', String(state.autoRotate));
toolRotate.addEventListener('click', () => {
  state.autoRotate = !state.autoRotate;
  toolRotate.setAttribute('aria-pressed', String(state.autoRotate));
});
toolSparkle.addEventListener('click', () => {
  state.sparkle = !state.sparkle;
  toolSparkle.setAttribute('aria-pressed', String(state.sparkle));
  gsap.to(fx, { sparkle: state.sparkle ? 1 : 0, duration: 0.6 });
  if (state.sparkle) gsap.fromTo(fx, { glintBoost: 1 }, { glintBoost: 0, duration: 1.4 });
});
$('#toolLight').addEventListener('click', e => {
  const b = e.currentTarget;
  b.classList.add('is-busy');
  setTimeout(() => b.classList.remove('is-busy'), 1600);
  sweepLight();
});
$('#toolReset').addEventListener('click', () => {
  const c = studioCam();
  moveCamera(c.pos, c.target, 1.1);
  gsap.to(spin, { turn: Math.round(spin.turn / TAU) * TAU, duration: 1.1, ease: 'power3.inOut' });
});

// Save the design as an image: the backdrop, the ring and its name, in a 4:5 frame
function snapshot() {
  const ratio = renderer.getPixelRatio();
  renderer.setPixelRatio(Math.max(ratio, Math.min(3, 1800 / innerHeight))); // draw this one frame sharper
  renderer.setSize(innerWidth, innerHeight, false);
  renderer.render(scene, camera);
  const src = renderer.domElement, W = 1200, H = 1500;
  const out = el('canvas');
  out.width = W;
  out.height = H;
  const x = out.getContext('2d');
  const bd = find(BACKDROPS, state.backdrop), light = isLight(state.backdrop);
  const g = x.createRadialGradient(W / 2, H * 0.42, 0, W / 2, H * 0.42, H * 0.75);
  g.addColorStop(0, bd.stops[0]);
  g.addColorStop(0.5, bd.stops[1]);
  g.addColorStop(1, bd.stops[2]);
  x.fillStyle = g;
  x.fillRect(0, 0, W, H);
  // A 4:5 crop of the canvas with the ring a little above the middle
  let sh = src.height * 0.9, sw = (sh * W) / H;
  if (sw > src.width) [sw, sh] = [src.width, (src.width * H) / W];
  _p.copy(rig.position).project(camera);
  const cx = (_p.x * 0.5 + 0.5) * src.width, cy = (-_p.y * 0.5 + 0.5) * src.height;
  const sx = THREE.MathUtils.clamp(cx - sw / 2, 0, src.width - sw), sy = THREE.MathUtils.clamp(cy - sh * 0.42, 0, src.height - sh);
  x.drawImage(src, sx, sy, sw, sh, 0, 0, W, H);
  renderer.setPixelRatio(ratio);
  renderer.setSize(innerWidth, innerHeight, false);
  x.textAlign = 'center';
  x.fillStyle = light ? '#7A5C00' : '#E3C77A';
  x.font = 'italic 400 64px "Cormorant Garamond", serif';
  x.fillText(`${find(STYLES, state.style).name} ring`, W / 2, H - 170);
  x.font = '400 26px "Jost", sans-serif';
  x.fillStyle = light ? 'rgba(36,26,23,0.75)' : 'rgba(251,248,242,0.8)';
  x.fillText(`${find(GEMS, state.gem).name}  ·  ${find(METALS, state.metal).name}`, W / 2, H - 116);
  x.font = '500 20px "Jost", sans-serif';
  x.fillStyle = light ? '#7A5C00' : '#E3C77A';
  if ('letterSpacing' in x) x.letterSpacing = '8px';
  x.fillText('MANGALAM JEWELLERS', W / 2, H - 56);
  return out.toDataURL('image/png');
}
function openShot() {
  const url = snapshot();
  $('#shotImg').src = url;
  $('#shotDownload').href = url;
  $('#shotTitle').textContent = `${find(STYLES, state.style).name} ring`;
  const specs = [['Setting', find(STYLES, state.style)], ['Stone', find(GEMS, state.gem)], ['Metal', find(METALS, state.metal)]];
  $('#shotSpecs').replaceChildren(...specs.flatMap(([label, o]) => [el('dt', '', label), el('dd', '', `${o.name} — ${o.sub}`)]));
  $('#shot').showModal();
}
$('#toolShot').addEventListener('click', openShot);
$('#saveDesign').addEventListener('click', openShot);
$('#shotClose').addEventListener('click', () => $('#shot').close());
$('#shot').addEventListener('click', e => {
  if (e.target === e.currentTarget) e.currentTarget.close();
});

/* ---------- 10. The story: scroll moves the ring between poses ---------- */
const poseFns = name => Object.fromEntries(POSE_KEYS.map(k => [k, () => poses()[name][k]]));
gsap.timeline({
  defaults: { ease: 'power1.inOut' },
  scrollTrigger: { trigger: '#story', start: 'top top', end: 'bottom bottom', scrub: 1.2, invalidateOnRefresh: true },
})
  .fromTo(storyPose, poseFns('hero'), poseFns('forever'))
  .fromTo(storyPose, poseFns('forever'), { ...poseFns('emotions'), immediateRender: false });

const dots = $$('.dots button');
$$('.panel').forEach((panel, i) => {
  ScrollTrigger.create({ trigger: panel, start: 'top 62%', end: 'bottom 38%', toggleClass: { targets: panel, className: 'is-in' } });
  ScrollTrigger.create({ trigger: panel, start: 'top center', end: 'bottom center', onToggle: self => self.isActive && dots.forEach((d, j) => d.classList.toggle('is-active', i === j)) });
});
gsap.fromTo('.bigword', { xPercent: -38, yPercent: -50 }, { xPercent: -62, yPercent: -50, ease: 'none', scrollTrigger: { trigger: '#forever', start: 'top bottom', end: 'bottom top', scrub: true } });

addEventListener('pointermove', e => {
  pointer.x = (e.clientX / innerWidth) * 2 - 1;
  pointer.y = (e.clientY / innerHeight) * 2 - 1;
}, { passive: true });

/* ---------- 11. Every frame ---------- */
const glowEl = $('#glow');
const clock = new THREE.Clock();
const lerp = (a, b, t) => a + (b - a) * t;

function updateGlints(t) {
  if (!model) return;
  for (const s of model.userData.glints) {
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

function frame() {
  const dt = Math.min(clock.getDelta(), 0.05), t = clock.elapsedTime;
  const m = blend.v, idle = (1 - m) * (reduceMotion ? 0 : 1);
  pointer.sx += (pointer.x - pointer.sx) * 0.05;
  pointer.sy += (pointer.y - pointer.sy) * 0.05;

  const P = storyPose, S = studioPose;
  rig.position.set(lerp(P.x, S.x, m), lerp(P.y, S.y, m) + intro.y + Math.sin(t * 1.1) * 0.045 * idle, 0);
  rig.rotation.set(
    lerp(P.rx, S.rx, m) + (Math.sin(t * 0.7) * 0.03 + pointer.sy * 0.12) * idle,
    lerp(P.ry, S.ry, m) + intro.ry + (Math.sin(t * 0.35) * 0.18 + pointer.sx * 0.22) * idle,
    lerp(P.rz, S.rz, m)
  );
  rig.scale.setScalar(lerp(P.s, S.s, m) * intro.s);
  if (state.mode === 'studio' && state.autoRotate && !dragging) spin.turn += dt * 0.38;
  spinner.rotation.y = spin.turn + spin.pulse;

  const envAngle = fx.env + t * 0.05;
  scene.environmentRotation.y = envAngle;
  gemMat.uniforms.uEnvRot.value.setFromMatrix4(_m4.makeRotationY(-envAngle));
  gemMat.uniforms.uTime.value = t;
  gemMat.uniforms.uSparkle.value = fx.sparkle;
  dustMat.uniforms.uTime.value = t;

  floor.position.set(rig.position.x, rig.position.y - 1.45 * rig.scale.x, 0);
  floor.scale.setScalar(rig.scale.x);
  floorMat.opacity = floor.userData.max * m;

  if (controls.enabled) controls.update();
  scene.updateMatrixWorld();
  updateGlints(t);
  updateBurst(dt);

  _p.set(rig.position.x, rig.position.y + 0.3 * rig.scale.x, 0).project(camera);
  glowEl.style.transform = `translate3d(${(_p.x * 0.5 + 0.5) * innerWidth}px, ${(-_p.y * 0.5 + 0.5) * innerHeight}px, 0) translate(-50%, -50%) scale(${0.6 + rig.scale.x * 0.5})`;

  renderer.render(scene, camera);
  requestAnimationFrame(frame);
}

function resize() {
  renderer.setSize(innerWidth, innerHeight, false);
  camera.aspect = innerWidth / innerHeight;
  camera.updateProjectionMatrix();
  dustMat.uniforms.uScale.value = burstMat.uniforms.uScale.value = pointScale();
  studioPose = poses().studio;
  if (state.mode === 'studio') {
    const c = studioCam();
    moveCamera(c.pos, c.target, 0.6);
  }
}
addEventListener('resize', resize);

/* ---------- 12. Loading ---------- */
async function boot() {
  const t0 = performance.now();
  const bar = $('#loaderBar'), text = $('#loaderText');
  const steps = ['Polishing the gold', 'Cutting the stone', 'Setting the prongs', 'Lighting the studio'];
  let step = 0;
  const cycle = setInterval(() => {
    step = (step + 1) % steps.length;
    text.textContent = steps[step];
  }, 700);
  const progress = v => (bar.style.transform = `scaleX(${v})`);

  progress(0.3);
  applyMetal(state.metal, false);
  applyGem(state.gem, false);
  applyStyle(state.style, false);
  applyBackdrop(state.backdrop);
  syncUI();
  progress(0.55);
  await Promise.race([document.fonts.ready, wait(2500)]);
  if (renderer.compileAsync) await renderer.compileAsync(scene, camera);
  progress(0.85);
  requestAnimationFrame(frame);
  await wait(Math.max(0, 1600 - (performance.now() - t0)));
  progress(1);
  await wait(450);
  clearInterval(cycle);

  scrollTo(0, 0);
  ScrollTrigger.refresh();
  document.body.classList.remove('is-loading');
  $('#loader').classList.add('is-done');
  if (!reduceMotion) {
    gsap.fromTo(intro, { s: 0.45, ry: -2.8, y: -0.5 }, { s: 1, ry: 0, y: 0, duration: 2.8, ease: 'expo.out' });
    setTimeout(() => burst('#E3C77A'), 500);
  }
}
boot().catch(fail);
