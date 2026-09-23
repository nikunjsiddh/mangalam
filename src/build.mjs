/* Mangalam Jewellers — page generator.
 *
 *   npm run build        (or: node src/build.mjs)
 *
 * Every .html page in the project root is generated from:
 *   src/partials/   layout, header, footer and dialogs shared by all pages
 *   src/pages/      the <main> content of each page
 *   assets/js/data.js  products, journal and testimonials (also used in the browser)
 *
 * Template syntax: {{name}} inserts a variable, {{icon:name}} an icon, {{> partial}} a partial.
 */
import { readFileSync, writeFileSync } from "node:fs";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";
import { createRequire } from "node:module";

const SRC = dirname(fileURLToPath(import.meta.url));
const ROOT = join(SRC, "..");
const require = createRequire(import.meta.url);
require("../assets/js/ui.js");
require("../assets/js/data.js");
const { icon } = globalThis.MJUI;
const MJ = globalThis.MJ;
const { IMG, products, articles, testimonials, categories, categoryCopy, capitalize, formatPrice } = MJ;

/* ---------- Site details (edit here) ---------- */
const FOUNDED = 1962;
const site = {
  phone: "+91 261 000 0000",
  phoneHref: "tel:+912610000000",
  email: "care@mangalamjewellers.in",
  addressHtml: "Mangalam House, Ring Road,<br>Surat, Gujarat 395002",
  hours: "Mon – Sat · 10:30 AM – 8:30 PM",
  mapsUrl: "https://www.google.com/maps/search/?api=1&amp;query=Ring+Road+Surat+Gujarat+395002",
  instagram: "https://instagram.com",
  facebook: "https://facebook.com",
  youtube: "https://youtube.com",
  years: String(new Date().getFullYear() - FOUNDED),
};

/* ---------- Templating ---------- */
const read = (path) => readFileSync(join(SRC, path), "utf8");
const esc = (v) => String(v).replace(/[&<>"]/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;" })[c]);

function render(tpl, vars) {
  return tpl
    .replace(/\{\{>\s*([\w-]+)\s*\}\}/g, (_, name) => render(read(`partials/${name}.html`), vars))
    .replace(/\{\{icon:([a-z-]+)(?::([^}]+))?\}\}/g, (_, name, cls) => icon(name, cls))
    .replace(/\{\{(\w+)\}\}/g, (_, key) => {
      if (!(key in vars)) throw new Error(`Template variable "${key}" is not defined`);
      return vars[key];
    });
}

/* ---------- Shared fragments ---------- */
const count = (fn) => products.filter(fn).length;
const bySlug = (slug) => products.find((p) => p.slug === slug);
const label = (c) => (c === "bridal" ? "Bridal" : capitalize(c));

const collections = [
  { title: "Bridal", kicker: "Sets for the unforgettable day", href: "bridal.html", image: IMG.bridal, pos: "center", n: count((p) => p.category === "bridal") },
  { title: "Gold", kicker: "The warmth of 22K tradition", href: "jewellery.html?metal=Gold", image: IMG.necklaces, pos: "center", n: count((p) => p.metal === "Gold") },
  { title: "Diamond", kicker: "Light, held forever", href: "jewellery.html?metal=Diamond", image: IMG.rings, pos: "center", n: count((p) => p.metal === "Diamond") },
  { title: "Heritage", kicker: "Motifs passed through generations", href: "jewellery.html?style=Traditional", image: IMG.bangles, pos: "center", n: count((p) => p.style === "Traditional") },
  { title: "Everyday", kicker: "Quietly extraordinary", href: "jewellery.html?style=Modern", image: IMG.pendants, pos: "50% 62%", n: count((p) => p.style === "Modern") },
  { title: "Statement", kicker: "Jewels that begin conversations", href: "jewellery.html?style=Classic", image: IMG.bracelets, pos: "50% 45%", n: count((p) => p.style === "Classic") },
];

const fragments = {
  megaCats: categories.map((c) =>
    `<li><a href="${c}.html"><span class="mega__thumb"><img src="${IMG[c]}" alt="" loading="lazy" width="800" height="800"></span>${label(c)}</a></li>`).join("\n                "),

  menuCats: categories.filter((c) => c !== "bridal").map((c) => `<li><a href="${c}.html">${label(c)}</a></li>`).join(""),

  topbarItems: ["BIS hallmarked gold", `Handcrafted in Surat since ${FOUNDED}`, "Insured delivery across India", "Private bridal consultations", "Crafted with tradition · Designed for generations"]
    .concat(["BIS hallmarked gold", `Handcrafted in Surat since ${FOUNDED}`, "Insured delivery across India", "Private bridal consultations", "Crafted with tradition · Designed for generations"])
    .map((t) => `<li>${t}</li>`).join(""),
};

/* Home-only fragments */
function homeFragments() {
  const hotspots = [
    { slug: "bridal-jhumka", x: 68.2, y: 27.5 },
    { slug: "diamond-collar-necklace", x: 70.4, y: 60.5 },
    { slug: "heritage-gold-bangles", x: 85.6, y: 51.5, flip: true },
    { slug: "floral-diamond-ring", x: 89.2, y: 81.6, flip: true },
  ];
  const heroHotspots = hotspots.map(({ slug, x, y, flip }) => {
    const p = bySlug(slug);
    return `<div class="hotspot hotspot--desktop${flip ? " hotspot--flip" : ""}" style="--x:${x}%;--y:${y}%" tabindex="0" role="button" aria-expanded="false" aria-label="Shop the look: ${esc(p.name)}">
            <span class="hotspot__card">
              <img src="${p.image}" alt="" loading="lazy" style="object-position:${p.imagePosition}">
              <span><span class="hotspot__label">Shop the look</span><a class="hotspot__name" href="product.html?slug=${p.slug}">${esc(p.name)}</a><span class="hotspot__price">${formatPrice(p.price)}</span></span>
            </span>
          </div>`;
  }).join("\n          ");

  const sparklePos = [[22, 18, 0], [34, 78, 1.2], [52, 58, 2.1], [18, 44, 3], [66, 26, 0.8], [44, 88, 2.6], [28, 64, 1.8], [72, 72, 3.6], [38, 34, 2.9], [58, 12, 1.5], [14, 30, 4.2], [80, 50, 5]];
  const sparkles = sparklePos.map(([t, l, d]) => `<span style="top:${t}%;left:${l}%;--d:${d}s"></span>`).join("");

  const categoryArches = categories.map((c) => `
      <a class="cat-arch" href="${c}.html" data-reveal>
        <span class="cat-arch__frame"><span class="cat-arch__img"><img src="${IMG[c]}" alt="" loading="lazy" width="800" height="800"></span></span>
        <span class="cat-arch__name">${label(c)}</span>
        <span class="cat-arch__count">${count((p) => p.category === c)} designs</span>
      </a>`).join("");

  const bento = collections.map((c) => `
      <a class="bento__item" href="${c.href}" data-reveal="zoom">
        <img src="${c.image}" alt="" loading="lazy" style="object-position:${c.pos}">
        <span class="bento__tag">${c.n} pieces</span>
        <span class="bento__body"><span class="bento__kicker">${c.kicker}</span><span class="bento__title">${c.title}</span><span class="bento__cta">Explore ${icon("arrow-right")}</span></span>
      </a>`).join("");

  const initials = (name) => name.split(" ").map((w) => w[0]).join("").slice(0, 2);
  const testimonialSlides = testimonials.map((t, i) => `
      <figure class="tslide${i === 0 ? " is-active" : ""}" aria-hidden="${i !== 0}">
        <div class="tslide__mark" aria-hidden="true">&ldquo;</div>
        <blockquote>${esc(t.quote)}</blockquote>
        <figcaption><span class="tslide__avatar" aria-hidden="true">${initials(t.who)}</span><span><span class="tslide__who">${esc(t.who)}</span><span class="tslide__occasion">${esc(t.occasion)}</span></span></figcaption>
      </figure>`).join("");
  const testimonialDots = testimonials.map((t, i) => `<button type="button" data-dot aria-label="Show story ${i + 1}" aria-current="${i === 0}"></button>`).join("");

  const [lead, ...rest] = articles;
  const journalMag = `
      <a class="jcard" href="article.html?slug=${lead.slug}" data-reveal="left">
        <div class="jcard__media"><img src="${lead.image}" alt="" loading="lazy" style="object-position:${lead.imagePosition}"><span class="jcard__cat">${lead.category}</span></div>
        <p class="jcard__meta">${lead.date} · ${lead.readTime}</p>
        <h3 class="jcard__title">${esc(lead.title)}</h3>
        <p class="jcard__excerpt">${esc(lead.excerpt)}</p>
        <span class="link-arrow">Read the story ${icon("arrow-right")}</span>
      </a>
      <div class="journal-mag__side" data-stagger>${rest.slice(0, 3).map((a) => `
        <a class="jcard jcard--row" href="article.html?slug=${a.slug}" data-reveal="right">
          <div class="jcard__media"><img src="${a.image}" alt="" loading="lazy" style="object-position:${a.imagePosition}"></div>
          <div><p class="jcard__meta">${a.category} · ${a.readTime}</p><h3 class="jcard__title">${esc(a.title)}</h3></div>
        </a>`).join("")}
      </div>`;

  const insta = [IMG.hero, IMG.bridal, IMG.bangles, IMG.necklaces, IMG.craft, IMG.earrings];
  const instaTiles = insta.map((src) => `
      <a class="insta__item" href="${site.instagram}" target="_blank" rel="noopener" aria-label="Mangalam Jewellers on Instagram" data-reveal>
        <img src="${src}" alt="" loading="lazy">${icon("instagram")}
      </a>`).join("");

  const tickerItems = ["Gold", "Diamond", "Polki", "Kundan", "Temple", "Bridal", "Heritage"].map((w) => `<li>${w}</li>`).join("");

  const closer = bySlug("temple-gold-necklace");
  return {
    heroHotspots, sparkles, categoryArches, bento, testimonialSlides, testimonialDots, journalMag, instaTiles, tickerItems,
    closerName: esc(closer.name), closerPrice: formatPrice(closer.price), closerSlug: closer.slug,
  };
}

function collectionCards() {
  return collections.map((c, i) => `
      <a class="coll-card" href="${c.href}" data-reveal>
        <img src="${c.image}" alt="" loading="lazy" style="object-position:${c.pos}">
        <span class="coll-card__body"><span class="coll-card__num">0${i + 1} · ${c.n} pieces</span><span class="coll-card__title">${c.title}</span><span class="coll-card__text">${c.kicker}</span><span class="coll-card__cta">Explore ${icon("arrow-right")}</span></span>
      </a>`).join("");
}

function journalFragments() {
  const [lead, ...rest] = articles;
  const cats = [...new Set(articles.map((a) => a.category))];
  return {
    journalFeature: `
      <a class="jcard feature-post" href="article.html?slug=${lead.slug}" data-reveal>
        <div class="jcard__media"><img src="${lead.image}" alt="" style="object-position:${lead.imagePosition}"><span class="jcard__cat">Featured story</span></div>
        <div>
          <p class="jcard__meta">${lead.category} · ${lead.date} · ${lead.readTime}</p>
          <h2 class="jcard__title">${esc(lead.title)}</h2>
          <p class="jcard__excerpt">${esc(lead.excerpt)}</p>
          <span class="link-arrow">Read the story ${icon("arrow-right")}</span>
        </div>
      </a>`,
    journalChips: ['<button type="button" class="chip chip--text is-active" data-journal-filter="all" aria-pressed="true">All stories</button>']
      .concat(cats.map((c) => `<button type="button" class="chip chip--text" data-journal-filter="${c}" aria-pressed="false">${c}</button>`)).join(""),
    journalGrid: rest.map((a) => `
      <a class="jcard" href="article.html?slug=${a.slug}" data-cat="${a.category}" data-reveal>
        <div class="jcard__media"><img src="${a.image}" alt="" loading="lazy" style="object-position:${a.imagePosition}"><span class="jcard__cat">${a.category}</span></div>
        <p class="jcard__meta">${a.date} · ${a.readTime}</p>
        <h2 class="jcard__title">${esc(a.title)}</h2>
        <p class="jcard__excerpt">${esc(a.excerpt)}</p>
      </a>`).join(""),
  };
}

function catalogChips(active) {
  const all = `<a class="chip chip--text" href="jewellery.html"${active === "" ? ' aria-current="page"' : ""}>All jewellery</a>`;
  return all + categories.map((c) =>
    `<a class="chip" href="${c}.html"${active === c ? ' aria-current="page"' : ""}><img src="${IMG[c]}" alt="" width="34" height="34">${label(c)}</a>`).join("");
}

/* ---------- Pages ---------- */
const pages = [
  {
    file: "index.html", page: "home", nav: "home", main: "home",
    title: "Mangalam Jewellers — Where Tradition Becomes Timeless",
    description: "Mangalam Jewellers crafts premium Gujarati heritage jewellery — gold, diamond and bridal pieces handcrafted in Surat since 1962.",
    vars: homeFragments(),
  },
  {
    file: "collections.html", page: "collections", nav: "collections", main: "collections",
    title: "Collections — Mangalam Jewellers",
    description: "Explore Mangalam's collections — bridal, gold, diamond, heritage, everyday and statement jewellery.",
    vars: { collectionCards: collectionCards() },
  },
  {
    file: "jewellery.html", page: "catalog", nav: "jewellery", main: "catalog",
    title: "All Jewellery — Mangalam Jewellers",
    description: "Explore timeless gold, diamond and bridal jewellery shaped by generations of Indian artistry.",
    vars: { category: "", crumb: "All jewellery", heading: "All <em>Jewellery</em>", lead: "Explore timeless gold, diamond and bridal jewellery shaped by generations of Indian artistry.", heroImage: IMG.necklaces, total: String(products.length), chips: catalogChips("") },
  },
  ...categories.map((c) => ({
    file: `${c}.html`, page: "catalog", nav: c === "bridal" ? "bridal" : "jewellery", main: "catalog", category: c,
    title: `${c === "bridal" ? "The Bridal Edit" : label(c)} — Mangalam Jewellers`,
    description: categoryCopy[c],
    vars: {
      category: c, crumb: label(c),
      heading: c === "bridal" ? "The Bridal <em>Edit</em>" : `${label(c)}`,
      lead: categoryCopy[c], heroImage: IMG[c], total: String(count((p) => p.category === c)), chips: catalogChips(c),
    },
  })),
  {
    file: "product.html", page: "product", nav: "jewellery", main: "product", solid: true, modals: ["modal-enquire"],
    title: "Product — Mangalam Jewellers",
    description: "Handcrafted Mangalam jewellery — BIS hallmarked and made to order in Surat.",
  },
  {
    file: "about.html", page: "about", nav: "about", main: "about",
    title: "About Us — Mangalam Jewellers",
    description: "Three generations of trust — the story of Mangalam Jewellers, a family atelier in Surat since 1962.",
  },
  {
    file: "craftsmanship.html", page: "craftsmanship", nav: "craftsmanship", main: "craftsmanship",
    title: "Craftsmanship — Mangalam Jewellers",
    description: "Inside the Mangalam atelier — design, craft and finish by master karigars in Surat.",
  },
  {
    file: "journal.html", page: "journal", nav: "journal", main: "journal",
    title: "Journal — Mangalam Jewellers",
    description: "Guides, heritage and inspiration — written by the people who make the jewellery.",
    vars: journalFragments(),
  },
  {
    file: "article.html", page: "article", nav: "journal", main: "article",
    title: "Journal — Mangalam Jewellers",
    description: "Stories from the Mangalam atelier.",
  },
  {
    file: "contact.html", page: "contact", nav: "contact", main: "contact",
    title: "Contact & Appointments — Mangalam Jewellers",
    description: "Visit Mangalam House in Surat, book a private consultation, or ask us anything about gold, diamonds and design.",
  },
  {
    file: "404.html", page: "not-found", nav: "", main: "404",
    title: "Page Not Found — Mangalam Jewellers",
    description: "The page you are looking for could not be found.",
  },
];

for (const p of pages) {
  if (p.main === "catalog") p.modals = ["modal-filters"];
  const vars = { ...site, ...fragments, ...(p.vars || {}) };
  const main = render(read(`pages/${p.main}.html`), vars);
  let out = render(read("partials/layout.html"), {
    ...vars,
    title: esc(p.title),
    description: esc(p.description),
    page: p.page,
    bodyAttrs: p.category ? ` data-category="${p.category}"` : "",
    headerClass: p.solid ? " is-solid" : "",
    main,
    extraModals: (p.modals || []).map((m) => render(read(`partials/${m}.html`), vars)).join("\n"),
  });
  if (p.nav) out = out.replaceAll(`data-nav="${p.nav}"`, `data-nav="${p.nav}" aria-current="page"`);
  writeFileSync(join(ROOT, p.file), out);
  console.log("built", p.file.padEnd(20), (out.length / 1024).toFixed(1) + " KB");
}
