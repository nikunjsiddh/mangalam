/* Mangalam Jewellers — admin panel generator.
 *
 * The admin panel is a design: a screen for every part of the website the team will manage — products,
 * categories, collections, the homepage, page banners, the journal, testimonials, media, enquiries,
 * appointments, subscribers, the offer popup, settings and the team. It is built from the website's own
 * data (assets/js/data.js, the site settings in src/build.mjs and the generated pages), plus the sample
 * customers in sample-data.mjs. Nothing is saved: every button shows what that step looks like, and a
 * toast says it was a preview. Connect a backend to make them work.
 *
 * Called at the end of src/build.mjs (npm run build). Writes admin/*.html from:
 *   src/admin/layout.html, layout-auth.html   page shells (the sign-in page has no sidebar)
 *   src/admin/partials/                        sidebar, top bar and the shared dialogs
 *   src/admin/pages/                           the content of each screen
 * Styles: assets/css/admin.css · Behaviour: assets/js/admin.js
 */
import { readFileSync, writeFileSync, mkdirSync, readdirSync, statSync, openSync, readSync, closeSync } from "node:fs";
import { dirname, join, extname } from "node:path";
import { fileURLToPath } from "node:url";
import { ADMIN_ICONS } from "./icons.mjs";
import { team, roles, permissions, enquiries, appointments, subscribers, figures, notifications } from "./sample-data.mjs";

const DIR = dirname(fileURLToPath(import.meta.url));
const MONTHS = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
const MONTHS_LONG = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
const WEEKDAYS = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"];
const WEEKDAYS_LONG = ["Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"];

/* ---------- Helpers ---------- */
const esc = (v) => String(v).replace(/[&<>"]/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;" })[c]);
const attrJSON = (o) => esc(JSON.stringify(o));
const asset = (p) => "../" + p; // site paths are relative to the project root; the admin lives one folder down
const inr = (n) => "₹ " + n.toLocaleString("en-IN");
const strip = (html) => html.replace(/<br\s*\/?>/g, " ").replace(/<[^>]+>/g, "").replace(/&amp;/g, "&").replace(/&middot;/g, "·").replace(/&#10022;/g, "").replace(/\s+/g, " ").trim();
const initials = (name) => name.split(/\s+/).map((w) => w[0]).join("").slice(0, 2).toUpperCase();
const tint = (name) => [...name].reduce((a, c) => a + c.charCodeAt(0), 0) % 4;
const avatar = (name, size) => `<span class="avatar avatar--t${tint(name)}${size ? " avatar--" + size : ""}" aria-hidden="true">${initials(name)}</span>`;
const addDays = (d, n) => { const x = new Date(d); x.setDate(x.getDate() + n); return x; };
const shortDate = (d) => `${d.getDate()} ${MONTHS[d.getMonth()]}`;
const longDate = (d) => `${d.getDate()} ${MONTHS[d.getMonth()]} ${d.getFullYear()}`;
const isoDate = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}-${String(d.getDate()).padStart(2, "0")}`;
const fileSize = (bytes) => (bytes >= 1048576 ? (bytes / 1048576).toFixed(1) + " MB" : Math.round(bytes / 1024) + " KB");
const plural = (n, one, many = one + "s") => `${n} ${n === 1 ? one : many}`;

const STATUS = {
  published: ["ok", "Published"], draft: ["neutral", "Draft"], scheduled: ["info", "Scheduled"],
  new: ["info", "New"], replied: ["ok", "Replied"], closed: ["neutral", "Closed"],
  confirmed: ["ok", "Confirmed"], pending: ["warn", "Pending"], completed: ["neutral", "Completed"], cancelled: ["danger", "Cancelled"],
  subscribed: ["ok", "Subscribed"], unsubscribed: ["neutral", "Unsubscribed"],
  active: ["ok", "Active"], invited: ["warn", "Invited"],
};
const badge = (key) => `<span class="badge badge--${STATUS[key][0]}">${STATUS[key][1]}</span>`;

/* Width and height of a JPEG, PNG or SVG, read from its header */
function imageSize(file) {
  const ext = extname(file).toLowerCase();
  if (ext === ".svg") {
    const m = readFileSync(file, "utf8").match(/viewBox="[\d.-]+\s+[\d.-]+\s+([\d.]+)\s+([\d.]+)"/);
    return m ? [Math.round(+m[1]), Math.round(+m[2])] : null;
  }
  const buf = Buffer.alloc(256 * 1024);
  const fd = openSync(file, "r");
  const n = readSync(fd, buf, 0, buf.length, 0);
  closeSync(fd);
  if (ext === ".png") return [buf.readUInt32BE(16), buf.readUInt32BE(20)];
  for (let i = 2; i + 9 < n;) { // JPEG: walk the segments to the frame header
    if (buf[i] !== 0xff) { i++; continue; }
    const marker = buf[i + 1];
    if (marker >= 0xc0 && marker <= 0xcf && ![0xc4, 0xc8, 0xcc].includes(marker)) return [buf.readUInt16BE(i + 7), buf.readUInt16BE(i + 5)];
    i += 2 + buf.readUInt16BE(i + 2);
  }
  return null;
}

/* A 12-point sparkline: the trend in a quiet ink, the current value in gold */
function sparkline(values, w = 104, h = 34) {
  const min = Math.min(...values), max = Math.max(...values), pad = 4;
  const x = (i) => (pad + (i * (w - pad * 2)) / (values.length - 1)).toFixed(1);
  const y = (v) => (h - pad - ((v - min) / (max - min || 1)) * (h - pad * 2)).toFixed(1);
  const last = values.length - 1;
  return `<svg class="spark" viewBox="0 0 ${w} ${h}" width="${w}" height="${h}" aria-hidden="true"><polyline points="${values.map((v, i) => `${x(i)},${y(v)}`).join(" ")}"/><circle cx="${x(last)}" cy="${y(values[last])}" r="3.5"/></svg>`;
}

export function buildAdmin({ ROOT, MJ, ICONS, site, collections, hotspots, announcements, sitePages, label }) {
  // Icons point into a sprite at the top of each page, which holds only the icons that page uses
  const allIcons = { ...ICONS, ...ADMIN_ICONS };
  const icon = (name, cls) => {
    if (!allIcons[name]) throw new Error("Unknown icon: " + name);
    return `<svg class="icon${cls ? " " + cls : ""}" viewBox="0 0 24 24" aria-hidden="true"><use href="#i-${name}"/></svg>`;
  };
  const SCRIPT_ICONS = ["chevron-left", "chevron-right", "x"]; // admin.js draws these itself (pager, new photos and picks)
  const sprite = (html) => {
    const names = [...new Set([...html.matchAll(/href="#i-([a-z0-9-]+)"/g)].map((m) => m[1]).concat(SCRIPT_ICONS))].sort();
    return `<svg class="icon-sprite" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">${names.map((n) => `<symbol id="i-${n}" viewBox="0 0 24 24">${allIcons[n]}</symbol>`).join("")}</svg>`;
  };
  const read = (path) => readFileSync(join(DIR, path), "utf8");
  const render = (tpl, vars) => tpl
    .replace(/\{\{>\s*([\w-]+)\s*\}\}/g, (_, name) => render(read(`partials/${name}.html`), vars))
    .replace(/\{\{icon:([a-z0-9-]+)\}\}/g, (_, name) => icon(name))
    .replace(/\{\{(\w+)\}\}/g, (_, key) => {
      if (!(key in vars)) throw new Error(`Admin template variable "${key}" is not defined`);
      return vars[key];
    });

  const { IMG, products, articles, testimonials, categories, categoryCopy, featuredSlugs, small, capitalize } = MJ;
  const bySlug = (slug) => products.find((p) => p.slug === slug);
  const featured = new Set(featuredSlugs);
  const needPhotos = products.filter((p) => !p.gallery.length);
  const today = new Date();
  today.setHours(0, 0, 0, 0);
  const siteHtml = (file) => readFileSync(join(ROOT, file), "utf8");
  const home = siteHtml("index.html");

  /* ---------- Small UI pieces ---------- */
  const switchOnly = (ariaLabel, checked = true, attrs = "") => `<label class="switch"${attrs}><input type="checkbox"${checked ? " checked" : ""} aria-label="${esc(ariaLabel)}"><span class="switch__track" aria-hidden="true"></span></label>`;
  const moreMenu = (name, items) => `<div class="dropdown"><button type="button" class="icon-btn icon-btn--sm" data-menu-trigger aria-haspopup="menu" aria-expanded="false" aria-label="More actions for ${esc(name)}">${icon("ellipsis")}</button><div class="menu" data-menu role="menu" hidden>${items.join("")}</div></div>`;
  const menuLink = (href, ic, text) => `<a role="menuitem" href="${href}"${href.startsWith("../") ? ' target="_blank" rel="noopener"' : ""}>${icon(ic)}${text}</a>`;
  const menuToast = (ic, text, toast, toastText) => `<button type="button" role="menuitem" data-toast="${esc(toast)}"${toastText ? ` data-toast-text="${esc(toastText)}"` : ""}>${icon(ic)}${text}</button>`;
  const menuDelete = (what, detail, done, action = "Delete") => `<hr><button type="button" role="menuitem" class="is-danger" data-confirm="${esc(`${action} ${what}?`)}" data-confirm-text="${esc(detail)}" data-confirm-action="${action}" data-done="${esc(done)}">${icon("trash-2")}${action}</button>`;
  const option = (value, text, selected) => `<option value="${esc(value)}"${selected ? " selected" : ""}>${esc(text)}</option>`;
  const radios = (name, values, checked) => values.map((v) => `<label><input type="radio" name="${name}" value="${esc(v)}"${v === checked ? " checked" : ""}><span>${esc(v)}</span></label>`).join("");

  /* ---------- Shared facts ---------- */
  const collectionRule = (c) => {
    const query = c.href.split("?")[1];
    const [field, value] = query ? query.split("=") : ["category", c.href.replace(".html", "")];
    return { field, value, text: `${capitalize(field)} is ${field === "category" ? label(value) : value}` };
  };
  const collectionsOf = (p) => collections.filter((c) => { const r = collectionRule(c); return String(p[r.field]).toLowerCase() === r.value.toLowerCase(); }).map((c) => c.title);
  const newEnquiries = enquiries.filter((e) => e.status === "new").length;
  const upcoming = appointments.map((a) => ({ ...a, date: addDays(today, a.day) })).filter((a) => a.day >= 0 && a.status !== "cancelled");

  // Every page of the website with its banner, heading and search listing, read from the generated pages
  const PAGE_NAMES = { "index.html": "Home", "collections.html": "Collections", "jewellery.html": "All jewellery", "product.html": "Product page", "about.html": "About us", "craftsmanship.html": "Craftsmanship", "journal.html": "Journal", "article.html": "Journal story", "contact.html": "Contact", "404.html": "Page not found" };
  const sitePageInfo = sitePages.map((p) => {
    const html = siteHtml(p.file);
    const kind = p.category ? "Category" : ["product.html", "article.html"].includes(p.file) ? "Template" : "Page";
    const banner = (html.match(/class="(?:page-hero__bg|hero__img)" src="([^"]+)"/) || [])[1] || "";
    const heading = strip((html.match(/<h1[^>]*>([\s\S]*?)<\/h1>/) || [, ""])[1]) || (p.file === "product.html" ? "Each product's name" : "Each story's title");
    const lead = strip((html.match(/class="(?:page-hero__lead|hero__lead)"[^>]*>([\s\S]*?)<\/p>/) || [, ""])[1]);
    const seo = p.title.length > 60 ? ["warn", "Title too long"] : p.description.length < 70 ? ["warn", "Description short"] : p.description.length > 160 ? ["warn", "Description long"] : ["ok", "Good"];
    return { ...p, name: PAGE_NAMES[p.file] || label(p.category), kind, banner, heading, lead, seo };
  });
  const seoIssues = sitePageInfo.filter((p) => p.seo[0] !== "ok").length;

  /* ---------- Navigation extras ---------- */
  const notificationItems = notifications.map((n) => `
            <a class="notice${n.unread ? " is-unread" : ""}" href="${n.href}"><span class="notice__icon">${icon(n.icon)}</span><span><span class="notice__text">${esc(n.text)}</span><span class="notice__when">${n.when}</span></span></a>`).join("");

  /* ---------- Dashboard ---------- */
  const weekStart = addDays(today, -((today.getDay() + 6) % 7));
  const weeks = figures.weeklyEnquiries.map((value, i) => ({ label: shortDate(addDays(weekStart, (i - 11) * 7)), value }));
  const thisWeek = weeks[11].value, lastWeek = weeks[10].value;
  const weekDelta = Math.round(((thisWeek - lastWeek) / lastWeek) * 100);
  const kpi = (o) => `
        <a class="card kpi" href="${o.href}">
          <span class="kpi__top"><span class="kpi__label">${o.label}</span><span class="kpi__icon">${icon(o.icon)}</span></span>
          <span class="kpi__value">${o.value}</span>
          <span class="kpi__foot"><span class="kpi__note">${o.note}</span>${o.spark || ""}</span>
        </a>`;
  const todayCount = upcoming.filter((a) => a.day === 0).length;
  const kpiTiles = [
    kpi({ href: "enquiries.html", label: "Enquiries this week", icon: "inbox", value: thisWeek, note: `<span class="delta delta--up">${icon("trending-up")}${weekDelta >= 0 ? "+" : ""}${weekDelta}%</span> vs last week`, spark: sparkline(figures.weeklyEnquiries) }),
    kpi({ href: "appointments.html", label: "Upcoming appointments", icon: "calendar-check", value: upcoming.length, note: `<strong>${todayCount}</strong> today · next 2 weeks` }),
    kpi({ href: "products.html", label: "Pieces on the website", icon: "gem", value: products.length, note: `<span class="kpi__warn">${icon("triangle-alert")}${needPhotos.length} need photos</span>` }),
    kpi({ href: "subscribers.html", label: "Newsletter subscribers", icon: "mail", value: figures.subscriberGrowth[11].toLocaleString("en-IN"), note: `<span class="delta delta--up">${icon("trending-up")}+${figures.subscribersThisMonth}</span> this month`, spark: sparkline(figures.subscriberGrowth) }),
  ].join("");
  const weeksTable = weeks.map((w) => `<tr><td>Week of ${w.label}</td><td class="num">${w.value}</td></tr>`).join("");

  const catCounts = categories.map((c) => ({ c, n: products.filter((p) => p.category === c).length })).sort((a, b) => b.n - a.n);
  const maxCount = Math.max(...catCounts.map((x) => x.n));
  const categoryBars = catCounts.map(({ c, n }) => `
            <li><a class="hbar" href="products.html?category=${c}" data-chart-tip="${esc(`${label(c)}|${plural(n, "piece")} · ${Math.round((n / products.length) * 100)}% of the catalogue`)}">
              <span class="hbar__label">${label(c)}</span>
              <span class="hbar__track"><span class="hbar__fill" style="width:${((n / maxCount) * 100).toFixed(1)}%"></span></span>
              <span class="hbar__value">${n}</span>
            </a></li>`).join("");

  const subjectOf = (e) => (e.product ? bySlug(e.product).name : e.topic);
  const recentEnquiries = enquiries.slice(0, 5).map((e) => `
              <tr>
                <td><a class="cell-main" href="enquiries.html">${avatar(e.name)}<span><span class="cell-title">${esc(e.name)}</span><span class="cell-sub">${e.email}</span></span></a></td>
                <td>${esc(subjectOf(e))}</td>
                <td class="cell-muted">${e.source === "product" ? "Product page" : "Contact form"}</td>
                <td class="cell-muted nowrap">${e.ago}${/min|hr/.test(e.ago) ? " ago" : ""}</td>
                <td>${badge(e.status)}</td>
              </tr>`).join("");

  const dayLabel = (a) => (a.day === 0 ? "Today" : a.day === 1 ? "Tomorrow" : `${WEEKDAYS[a.date.getDay()]} ${shortDate(a.date)}`);
  const apptFill = (a) => ({ title: a.name, name: a.name, phone: a.phone, date: isoDate(a.date || addDays(today, a.day)), time: a.time, interest: a.interest, consultant: a.consultant, status: a.status, notes: a.notes || "" });
  const upcomingList = upcoming.slice(0, 5).map((a) => `
            <li><button type="button" class="agenda__item" data-dialog-open="appointment-dialog" data-fill="${attrJSON(apptFill(a))}">
              <span class="agenda__date"><strong>${a.date.getDate()}</strong>${MONTHS[a.date.getMonth()]}</span>
              <span class="agenda__body"><span class="cell-title">${esc(a.name)}</span><span class="cell-sub">${dayLabel(a)}, ${a.time} · ${a.interest}</span></span>
              ${badge(a.status)}
            </button></li>`).join("");

  const attention = [
    needPhotos.length && { tone: "warn", icon: "image", text: `${plural(needPhotos.length, `${label(needPhotos[0].category).toLowerCase()} piece`)} ${needPhotos.length === 1 ? "has" : "have"} no photographs yet`, href: "products.html?flags=photos", cta: "View" },
    { tone: "warn", icon: "image", text: "The atelier photograph on Craftsmanship, About and Contact predates the new shoot", href: "media.html?folder=other", cta: "Replace" },
    newEnquiries && { tone: "info", icon: "inbox", text: `${plural(newEnquiries, "new enquiry", "new enquiries")} waiting for a reply`, href: "enquiries.html?status=new", cta: "Reply" },
    seoIssues && { tone: "info", icon: "globe", text: `${plural(seoIssues, "page")} could use a better search listing`, href: "pages.html", cta: "Review" },
    { tone: "ok", icon: "megaphone", text: `The offer popup (up to ${site.offerPercent}% off) is live on every page`, href: "offers.html", cta: "Edit" },
  ].filter(Boolean).map((a) => `
            <li class="todo todo--${a.tone}"><span class="todo__icon">${icon(a.icon)}</span><span class="todo__text">${esc(a.text)}</span><a class="btn btn--ghost btn--sm" href="${a.href}">${a.cta}${icon("chevron-right")}</a></li>`).join("");

  /* ---------- Products ---------- */
  const updatedOn = (i) => addDays(today, -((i * 5) % 34));
  const productRows = products.map((p, i) => {
    const flags = [featured.has(p.slug) && "featured", p.isNew && "new", !p.gallery.length && "photos"].filter(Boolean);
    const tags = (featured.has(p.slug) ? '<span class="tag tag--gold">Featured</span>' : "") + (p.isNew ? '<span class="tag">New</span>' : "") + (p.gallery.length ? "" : '<span class="tag tag--warn">Needs photos</span>');
    const d = updatedOn(i);
    const name = esc(p.name);
    return `
              <tr data-item data-search="${esc([p.name, p.category, p.metal, p.style, p.collection, p.purity].join(" ").toLowerCase())}" data-category="${p.category}" data-metal="${p.metal}" data-style="${p.style}" data-flags="${flags.join(" ")}" data-sort-price="${p.price}" data-sort-name="${name}" data-sort-updated="${d.getTime()}">
                <td class="col-check"><input class="check" type="checkbox" data-check-row aria-label="Select ${name}"></td>
                <td><a class="cell-main" href="product-edit.html?slug=${p.slug}"><img class="thumb" src="${asset(p.thumb)}" alt="" loading="lazy" width="48" height="48"><span><span class="cell-title">${name}</span><span class="cell-sub">${esc(p.collection)}</span>${tags ? `<span class="tags">${tags}</span>` : ""}</span></a></td>
                <td>${label(p.category)}</td>
                <td class="nowrap">${p.purity} ${p.metal}</td>
                <td class="num">${inr(p.price)}</td>
                <td>${badge("published")}</td>
                <td class="cell-muted nowrap">${longDate(d)}</td>
                <td class="col-actions"><div class="row-actions">
                  <a class="icon-btn icon-btn--sm" href="product-edit.html?slug=${p.slug}" aria-label="Edit ${name}" data-tip="Edit">${icon("pencil")}</a>
                  ${moreMenu(p.name, [
                    menuLink(`../product.html?slug=${p.slug}`, "external-link", "View on website"),
                    menuToast("copy", "Duplicate", "Product duplicated", `“${p.name} (copy)” was added as a draft.`),
                    menuToast("eye-off", "Hide from website", "Product hidden", `“${p.name}” is no longer shown on the website.`),
                    menuDelete(`“${p.name}”`, "It will be removed from the website and the catalogue. This can't be undone.", "Product deleted"),
                  ])}
                </div></td>
              </tr>`;
  }).join("");
  const categoryOptions = categories.map((c) => option(c, label(c))).join("");
  const productChips = [["", "All pieces", products.length], ["featured", "Featured", featured.size], ["new", "New", products.filter((p) => p.isNew).length], ["photos", "Needs photos", needPhotos.length]]
    .map(([v, t, n], i) => `<button type="button" class="chip${i ? "" : " is-active"}" data-list-chip="flags" data-value="${v}" aria-pressed="${!i}">${t}<span class="chip__count">${n}</span></button>`).join("");

  /* ---------- Product form (add and edit) ---------- */
  const galleryThumb = (src, i) => `
                <li class="gthumb" data-sort-item draggable="true">
                  <img src="${asset(small(src))}" alt="" loading="lazy" draggable="false">
                  <span class="gthumb__tag" data-gthumb-tag>${i === 0 ? "Main" : i === 1 ? "On hover" : ""}</span>
                  <button type="button" class="gthumb__remove" data-remove-closest=".gthumb" aria-label="Remove photo">${icon("x")}</button>
                </li>`;
  const productForm = (p) => {
    const edit = Boolean(p);
    const val = (v) => (edit ? esc(v) : "");
    const seoTitle = edit ? `${p.name} — Mangalam Jewellers` : "";
    return {
      page: "product-form",
      formTitle: edit ? esc(p.name) : "Add a product",
      formCrumb: edit ? esc(p.name) : "Add product",
      formDesc: edit ? `${label(p.category)} · last edited ${longDate(updatedOn(products.indexOf(p)))}` : "Fill in the details, add photos, then publish it to the website.",
      formBadge: badge(edit ? "published" : "draft"),
      submitLabel: edit ? "Update product" : "Publish product",
      savedToast: edit ? "Product updated" : "Product published",
      statusRadios: radios("status", ["Published", "Draft", "Hidden"], edit ? "Published" : "Draft"),
      publishDate: isoDate(today),
      viewHref: edit ? `../product.html?slug=${p.slug}` : "../jewellery.html",
      fName: val(p?.name), fSlug: val(p?.slug), fPrice: edit ? p.price : "",
      fSummary: edit ? esc(p.description) : "",
      fDescription: edit ? `<p>${esc(p.description)}</p><p>Every Mangalam piece carries a BIS hallmark and is handcrafted to order in our Surat atelier.</p>` : "",
      fSeoTitle: esc(seoTitle), fSeoDesc: val(p?.description),
      seoPreviewTitle: esc(seoTitle || "Product name — Mangalam Jewellers"),
      seoPreviewUrl: edit ? `product.html?slug=${p.slug}` : "product.html?slug=…",
      seoPreviewDesc: edit ? esc(p.description) : "A short description of the piece for search results.",
      metalOptions: ["Gold", "Diamond", "Rose gold", "Platinum", "Silver"].map((m) => option(m, m, edit && p.metal === m)).join(""),
      purityRadios: radios("purity", ["24K", "22K", "18K", "14K"], edit ? p.purity : "22K"),
      stoneOptions: ["None", "Diamond", "Polki", "Kundan", "Ruby", "Emerald", "Pearl", "Mixed"].map((s) => option(s, s, edit && (p.metal === "Diamond" ? s === "Diamond" : s === "None"))).join(""),
      categorySelect: categories.map((c) => option(c, label(c), edit && p.category === c)).join(""),
      styleRadios: radios("style", ["Classic", "Traditional", "Modern", "Bridal"], edit ? p.style : ""),
      lineOptions: ["Mangalam Signature", "The Bridal Edit", "Sacred Bonds"].map((c) => option(c, c, edit && p.collection === c)).join(""),
      inCollections: edit ? esc(collectionsOf(p).join(", ") || "None yet") : "Worked out from the details once saved",
      isNewChecked: edit && p.isNew ? " checked" : "",
      featuredChecked: edit && featured.has(p.slug) ? " checked" : "",
      galleryThumbs: edit ? p.gallery.map(galleryThumb).join("") : "",
      deleteCard: edit ? `
          <section class="card card--danger">
            <div class="card__body">
              <h2 class="card__title">Delete this product</h2>
              <p class="hint mt-6">It is removed from the website, search and every collection.</p>
              <button type="button" class="btn btn--danger-ghost btn--sm mt-12" data-confirm="Delete “${esc(p.name)}”?" data-confirm-text="It will be removed from the website and the catalogue. This can't be undone." data-confirm-action="Delete" data-done="Product deleted">${icon("trash-2")} Delete product</button>
            </div>
          </section>` : "",
      collectionsJSON: edit ? JSON.stringify(Object.fromEntries(products.map((x) => [x.slug, collectionsOf(x)]))) : "{}",
    };
  };

  /* ---------- Categories ---------- */
  const pageOf = (file) => sitePageInfo.find((p) => p.file === file);
  const categoryRows = categories.map((c) => {
    const n = products.filter((p) => p.category === c).length;
    const pg = pageOf(`${c}.html`);
    const fill = { title: `Edit ${label(c)}`, name: label(c), slug: c, heading: pg.heading, intro: categoryCopy[c], seoTitle: pg.title, seoDesc: pg.description, image: asset(IMG[c]), banner: asset(pg.banner) };
    return `
              <tr data-sort-item draggable="true">
                <td class="col-grip"><span class="grip" aria-hidden="true">${icon("grip-vertical")}</span></td>
                <td><div class="cell-main"><img class="thumb thumb--arch" src="${asset(IMG[c])}" alt="" loading="lazy"><span><span class="cell-title">${label(c)}</span><span class="cell-sub">/${c}.html</span></span></div></td>
                <td class="cell-desc">${esc(categoryCopy[c])}</td>
                <td class="num">${n}</td>
                <td>${switchOnly(`Show ${label(c)} in the menu`)}</td>
                <td class="col-actions"><div class="row-actions">
                  <button type="button" class="btn btn--outline btn--sm" data-dialog-open="category-drawer" data-fill="${attrJSON(fill)}">${icon("pencil")} Edit</button>
                  ${moreMenu(label(c), [menuLink(`../${c}.html`, "external-link", "View on website"), menuDelete(`the ${label(c)} category`, `Its ${plural(n, "piece")} stay in the catalogue without a category.`, "Category deleted")])}
                </div></td>
              </tr>`;
  }).join("");

  /* ---------- Collections ---------- */
  const valueOptions = `<optgroup label="Categories">${categories.map((c) => option(c, label(c))).join("")}</optgroup><optgroup label="Metals">${option("Gold", "Gold")}${option("Diamond", "Diamond")}</optgroup><optgroup label="Styles">${["Classic", "Traditional", "Modern", "Bridal"].map((s) => option(s, s)).join("")}</optgroup>`;
  const collectionCards = collections.map((c) => {
    const r = collectionRule(c);
    const fill = { title: `Edit ${c.title}`, name: c.title, kicker: c.kicker, image: asset(c.image), field: r.field, value: r.value };
    return `
          <article class="coll" data-sort-item draggable="true">
            <div class="coll__media">
              <img src="${asset(c.image)}" alt="" loading="lazy" draggable="false" style="object-position:${c.pos}">
              <span class="coll__count">${plural(c.n, "piece")}</span>
              <span class="grip coll__grip" aria-hidden="true">${icon("grip-vertical")}</span>
            </div>
            <div class="coll__body">
              <p class="coll__kicker">${esc(c.kicker)}</p>
              <h2 class="coll__title">${c.title}</h2>
              <p class="coll__rule">${icon("list-filter")}${esc(r.text)}</p>
            </div>
            <div class="coll__foot">
              <label class="switch switch--text"><input type="checkbox" checked><span class="switch__track" aria-hidden="true"></span><span>On homepage</span></label>
              <div class="row-actions">
                <button type="button" class="btn btn--outline btn--sm" data-dialog-open="collection-drawer" data-fill="${attrJSON(fill)}">${icon("pencil")} Edit</button>
                ${moreMenu(c.title, [menuLink(asset(c.href), "external-link", "View on website"), menuDelete(`the ${c.title} collection`, "The pieces stay in the catalogue; only this grouping is removed.", "Collection deleted")])}
              </div>
            </div>
          </article>`;
  }).join("");

  /* ---------- Homepage ---------- */
  const homeSections = [
    ["Hero", "Campaign photograph, headline and shop-the-look pins", "image", "#hero-editor"],
    ["Promise strip", "Hallmarked, handmade, insured delivery, consultations", "shield-check"],
    ["Shop by category", `${plural(categories.length, "category arch", "category arches")}`, "layout-grid", "categories.html"],
    ["Collections", `${plural(collections.length, "collection tile")}`, "layers", "collections.html"],
    ["Featured pieces", "Signature, Gold, Diamond, Bridal and New in", "gem", "#featured-editor"],
    ["The Mangalam promise", "Portrait, seal and four counters", "award"],
    ["The bridal edit", "Four expanding bridal panels", "sparkles", "#bridal-editor"],
    ["Craftsmanship", "Atelier photograph and three steps", "wrench"],
    ["Heritage", "Gujarati headline between two portraits", "star"],
    ["A closer look", "Magnifier photograph and featured price", "zoom-in"],
    ["Manifesto", "“Some jewellery is worn…”", "quote"],
    ["Testimonials", `${plural(testimonials.length, "customer story", "customer stories")}`, "message-circle", "testimonials.html"],
    ["From the journal", "The four latest stories", "book-open", "journal.html"],
    ["Instagram", "Six photo tiles", "instagram"],
    ["Appointment band", "Private consultation invitation", "calendar"],
  ];
  const sectionRows = homeSections.map(([name, desc, ic, href], i) => `
            <li class="srow" data-sort-item draggable="true">
              <span class="grip" aria-hidden="true">${icon("grip-vertical")}</span>
              <span class="srow__icon">${icon(ic)}</span>
              <span class="srow__text"><span class="srow__name">${name}</span><span class="srow__desc">${esc(desc)}</span></span>
              ${i === 0 ? '<span class="tag tag--soft">Always shown</span>' : switchOnly(`Show ${name} on the homepage`)}
              ${href ? `<a class="icon-btn icon-btn--sm" href="${href}" aria-label="Edit ${name}" data-tip="Edit">${icon("pencil")}</a>` : '<span class="icon-btn icon-btn--sm is-placeholder" aria-hidden="true"></span>'}
            </li>`).join("");

  const heroLines = [...home.matchAll(/<span class="line"><span style="--i:\d">([\s\S]*?)<\/span><\/span>/g)].map((m) => strip(m[1]));
  const heroStats = [...home.matchAll(/<li><strong>([^<]+)<\/strong><span>([^<]+)<\/span><\/li>/g)].slice(0, 3);
  const heroFields = {
    heroEyebrow: esc(strip((home.match(/class="eyebrow eyebrow--light" data-hero[^>]*>([\s\S]*?)<\/p>/) || [, ""])[1])),
    heroLine1: esc(heroLines[0] || ""), heroLine2: esc(heroLines[1] || ""),
    heroLead: esc(strip((home.match(/class="hero__lead"[^>]*>([\s\S]*?)<\/p>/) || [, ""])[1])),
    heroStats: heroStats.map((m, i) => `
                <div class="stat-edit"><input class="input" value="${esc(m[1])}" aria-label="Figure ${i + 1}"><input class="input" value="${esc(m[2])}" aria-label="Figure ${i + 1} caption"></div>`).join(""),
  };
  const productOptions = (selected) => products.map((p) => option(p.slug, p.name, p.slug === selected)).join("");
  const heroPins = hotspots.map((h, i) => `<button type="button" class="pin" style="--x:${h.x}%;--y:${h.y}%" data-pin="${i + 1}" aria-label="Pin ${i + 1}, ${esc(bySlug(h.slug).name)}. Drag to move.">${i + 1}</button>`).join("");
  const pinRows = hotspots.map((h, i) => `
                <li class="pinrow" data-pin-row="${i + 1}">
                  <span class="pinrow__num">${i + 1}</span>
                  <img class="thumb thumb--sm" src="${asset(bySlug(h.slug).thumb)}" alt="" loading="lazy" data-pin-thumb>
                  <span class="pinrow__body">
                    <select class="select select--sm" aria-label="Piece shown by pin ${i + 1}" data-pin-product>${productOptions(h.slug)}</select>
                    <span class="pinrow__pos" data-pin-pos>${h.x}% across · ${h.y}% down</span>
                  </span>
                  <button type="button" class="icon-btn icon-btn--sm" data-confirm="Remove pin ${i + 1}?" data-confirm-text="The pin disappears from the hero; the piece stays in the catalogue." data-confirm-action="Remove" data-done="Pin removed" data-remove-pin="${i + 1}" aria-label="Remove pin ${i + 1}">${icon("trash-2")}</button>
                </li>`).join("");
  const featuredPicks = featuredSlugs.map((slug, i) => {
    const p = bySlug(slug);
    return `
              <li class="pick" data-sort-item draggable="true">
                <span class="pick__num" data-position>${i + 1}</span>
                <img src="${asset(p.thumb)}" alt="" loading="lazy" draggable="false">
                <span class="pick__text"><span class="pick__name">${esc(p.name)}</span><span class="pick__meta">${label(p.category)} · ${inr(p.price)}</span></span>
                <button type="button" class="icon-btn icon-btn--sm" data-remove-closest=".pick" aria-label="Remove ${esc(p.name)} from featured pieces">${icon("x")}</button>
              </li>`;
  }).join("");
  const bridalPanels = [...home.matchAll(/<a class="panel[^"]*" href="[^"]*"[^>]*>\s*<img src="([^"]+)"[\s\S]*?<span class="panel__title">([^<]+)<\/span><span class="panel__text">([^<]+)<\/span>/g)].map((m, i) => `
              <div class="panel-edit">
                <div class="panel-edit__media"><img src="${asset(m[1])}" alt="" loading="lazy" data-image-preview><label class="panel-edit__replace">${icon("upload")}<span>Replace</span><input type="file" accept="image/*" data-image-input></label></div>
                <div class="panel-edit__fields">
                  <input class="input" value="${esc(strip(m[2]))}" aria-label="Panel ${i + 1} title">
                  <textarea class="textarea" rows="3" aria-label="Panel ${i + 1} text">${esc(strip(m[3]))}</textarea>
                </div>
              </div>`).join("");

  /* ---------- Pages & banners ---------- */
  const pageRows = sitePageInfo.map((p, i) => {
    const fill = { title: `Edit ${p.name}`, heading: p.heading, intro: p.lead, seoTitle: p.title, seoDesc: p.description, banner: p.banner ? asset(p.banner) : "", url: p.file };
    const editable = p.kind !== "Template";
    return `
              <tr data-item data-search="${esc(`${p.name} ${p.file} ${p.heading}`.toLowerCase())}" data-kind="${p.kind}">
                <td><div class="cell-main">${p.banner ? `<img class="thumb thumb--wide" src="${asset(p.banner)}" alt="" loading="lazy">` : `<span class="thumb thumb--wide thumb--icon">${icon("file-text")}</span>`}<span><span class="cell-title">${p.name}</span><span class="cell-sub">/${p.file}</span></span></div></td>
                <td><span class="tag tag--soft">${p.kind}</span></td>
                <td class="cell-desc">${esc(p.heading)}</td>
                <td><span class="badge badge--${p.seo[0]}">${p.seo[1]}</span></td>
                <td class="cell-muted nowrap">${longDate(addDays(today, -((i * 3) % 21)))}</td>
                <td class="col-actions"><div class="row-actions">
                  ${editable ? `<button type="button" class="btn btn--outline btn--sm" data-dialog-open="page-drawer" data-fill="${attrJSON(fill)}">${icon("pencil")} Edit</button>` : `<a class="btn btn--outline btn--sm" href="${p.file === "product.html" ? "products.html" : "journal.html"}">${icon("pencil")} Edit ${p.file === "product.html" ? "products" : "stories"}</a>`}
                  <a class="icon-btn icon-btn--sm" href="../${p.file}" target="_blank" rel="noopener" aria-label="View ${p.name} on the website" data-tip="View">${icon("external-link")}</a>
                </div></td>
              </tr>`;
  }).join("");

  /* ---------- Journal ---------- */
  const journalCats = [...new Set(articles.map((a) => a.category))];
  const articleCards = articles.map((a) => `
          <article class="post card" data-item data-category="${a.category}" data-status="published" data-search="${esc(`${a.title} ${a.category} ${a.excerpt}`.toLowerCase())}">
            <a class="post__media" href="article-edit.html?slug=${a.slug}"><img src="${asset(a.image)}" alt="" loading="lazy" style="object-position:${a.imagePosition}"><span class="tag tag--light post__cat">${a.category}</span></a>
            <div class="post__body">
              <p class="post__meta">${a.date} · ${a.readTime}</p>
              <h2 class="post__title"><a href="article-edit.html?slug=${a.slug}">${esc(a.title)}</a></h2>
              <p class="post__excerpt">${esc(a.excerpt)}</p>
            </div>
            <div class="post__foot">
              ${badge("published")}
              <div class="row-actions">
                <a class="btn btn--outline btn--sm" href="article-edit.html?slug=${a.slug}">${icon("pencil")} Edit</a>
                ${moreMenu(a.title, [menuLink(`../article.html?slug=${a.slug}`, "external-link", "View on website"), menuToast("copy", "Duplicate", "Story duplicated", `“${a.title} (copy)” was saved as a draft.`), menuToast("eye-off", "Unpublish", "Story unpublished", "It is now a draft and hidden from the website."), menuDelete(`“${a.title}”`, "The story will be removed from the journal. This can't be undone.", "Story deleted")])}
              </div>
            </div>
          </article>`).join("");
  const journalChips = [["", "All stories", articles.length], ...journalCats.map((c) => [c, c, articles.filter((a) => a.category === c).length])]
    .map(([v, t, n], i) => `<button type="button" class="chip${i ? "" : " is-active"}" data-list-chip="category" data-value="${v}" aria-pressed="${!i}">${t}<span class="chip__count">${n}</span></button>`).join("");

  const articleForm = (a) => {
    const edit = Boolean(a);
    const val = (v) => (edit ? esc(v) : "");
    return {
      page: "article-form",
      formTitle: edit ? esc(a.title) : "Write a story",
      formCrumb: edit ? esc(a.title) : "New story",
      formDesc: edit ? `${a.category} · published ${a.date}` : "Write it, add a cover photograph, then publish it to the journal.",
      formBadge: badge(edit ? "published" : "draft"),
      submitLabel: edit ? "Update story" : "Publish story",
      savedToast: edit ? "Story updated" : "Story published",
      statusRadios: radios("status", ["Published", "Draft", "Scheduled"], edit ? "Published" : "Draft"),
      viewHref: edit ? `../article.html?slug=${a.slug}` : "../journal.html",
      fTitle: val(a?.title), fSlug: val(a?.slug), fExcerpt: val(a?.excerpt), fReadTime: edit ? parseInt(a.readTime, 10) : "",
      fBody: edit ? a.body.map((para) => `<p>${esc(para)}</p>`).join("") : "",
      fSeoTitle: edit ? esc(`${a.title} — Mangalam Journal`) : "", fSeoDesc: val(a?.excerpt),
      seoPreviewTitle: esc(edit ? `${a.title} — Mangalam Journal` : "Story title — Mangalam Journal"),
      seoPreviewUrl: edit ? `article.html?slug=${a.slug}` : "article.html?slug=…",
      seoPreviewDesc: edit ? esc(a.excerpt) : "A one-line summary for search results.",
      categoryRadios: radios("category", ["Bridal", "Guide", "Heritage", "Style"], edit ? a.category : "Bridal"),
      authorOptions: team.filter((t) => t.status === "active").map((t) => option(t.name, t.name, t.role === "Editor")).join(""),
      coverImage: edit ? `<img src="${asset(a.image)}" alt="" data-image-preview>` : `<img src="" alt="" data-image-preview hidden>`,
      coverEmpty: edit ? " hidden" : "",
      publishDate: isoDate(today),
      deleteCard: edit ? `
          <section class="card card--danger">
            <div class="card__body">
              <h2 class="card__title">Delete this story</h2>
              <p class="hint mt-6">It is removed from the journal and the homepage.</p>
              <button type="button" class="btn btn--danger-ghost btn--sm mt-12" data-confirm="Delete “${esc(a.title)}”?" data-confirm-text="The story will be removed from the journal. This can't be undone." data-confirm-action="Delete" data-done="Story deleted">${icon("trash-2")} Delete story</button>
            </div>
          </section>` : "",
    };
  };

  /* ---------- Testimonials ---------- */
  const testimonialCards = testimonials.map((t) => {
    const fill = { title: `Edit ${t.who}'s story`, quote: t.quote, who: t.who, occasion: t.occasion };
    return `
          <article class="card qcard" data-sort-item draggable="true">
            <span class="grip qcard__grip" aria-hidden="true">${icon("grip-vertical")}</span>
            <blockquote class="qcard__quote">${esc(t.quote)}</blockquote>
            <div class="qcard__who">${avatar(t.who)}<span><span class="cell-title">${esc(t.who)}</span><span class="cell-sub">${esc(t.occasion)}</span></span></div>
            <div class="qcard__foot">
              <label class="switch switch--text"><input type="checkbox" checked><span class="switch__track" aria-hidden="true"></span><span>On homepage</span></label>
              <div class="row-actions">
                <button type="button" class="btn btn--outline btn--sm" data-dialog-open="testimonial-dialog" data-fill="${attrJSON(fill)}">${icon("pencil")} Edit</button>
                <button type="button" class="icon-btn icon-btn--sm" data-confirm="Delete ${esc(t.who)}'s story?" data-confirm-text="It will no longer appear on the homepage." data-confirm-action="Delete" data-done="Testimonial deleted" aria-label="Delete ${esc(t.who)}'s story">${icon("trash-2")}</button>
              </div>
            </div>
          </article>`;
  }).join("");

  /* ---------- Media library ---------- */
  const htmlByPage = sitePageInfo.map((p) => [p.name, siteHtml(p.file)]);
  const usedOn = (rel) => {
    const smallRel = small(rel);
    const out = htmlByPage.filter(([, html]) => html.includes(rel) || html.includes(smallRel)).map(([name]) => name);
    products.forEach((p) => { if (p.gallery.includes(rel) || p.image === rel) out.push(p.name); });
    articles.forEach((a) => { if (a.image === rel) out.push(`Journal: ${a.title}`); });
    return [...new Set(out)];
  };
  const mediaFolders = [["campaign", "Campaign"], ["products", "Products"], ["brand", "Brand"], ["other", "Other"]];
  const media = [];
  const addMedia = (dir, folder, filter) => {
    for (const name of readdirSync(join(ROOT, dir)).sort()) {
      const rel = `${dir}/${name}`;
      if (!/\.(jpe?g|png|svg)$/i.test(name) || (filter && !filter(name))) continue;
      const size = imageSize(join(ROOT, rel));
      media.push({ rel, name, folder, bytes: statSync(join(ROOT, rel)).size, dims: size ? `${size[0]} × ${size[1]}` : "" });
    }
  };
  addMedia("assets/images/campaign", "campaign");
  addMedia("assets/images/products", "products", (n) => !n.endsWith("-sm.jpg"));
  addMedia("assets/images/brand", "brand");
  addMedia("assets/images", "other", (n) => /\.jpe?g$/i.test(n));
  const mediaBytes = media.reduce((a, m) => a + m.bytes, 0);
  const mediaTiles = media.map((m) => {
    const used = usedOn(m.rel);
    const folderName = mediaFolders.find(([k]) => k === m.folder)[1];
    const thumbSrc = m.folder === "products" ? small(m.rel) : m.rel;
    const info = { src: asset(m.rel), name: m.name, folder: folderName, dims: m.dims, size: fileSize(m.bytes), url: m.rel, alt: m.folder === "products" && used[0] ? used[0] : "", used };
    return `
            <li data-item data-folder="${m.folder}" data-search="${esc(m.name.toLowerCase())}">
              <button type="button" class="tile${m.folder === "brand" ? " tile--brand" : ""}" data-media="${attrJSON(info)}" aria-label="${esc(m.name)}">
                <span class="tile__img"><img src="${asset(thumbSrc)}" alt="" loading="lazy" draggable="false"></span>
                <span class="tile__name">${esc(m.name)}</span>
                <span class="tile__meta">${m.dims ? m.dims + " · " : ""}${fileSize(m.bytes)}</span>
              </button>
            </li>`;
  }).join("");
  const mediaChips = [["", "All", media.length], ...mediaFolders.map(([k, t]) => [k, t, media.filter((m) => m.folder === k).length])]
    .map(([v, t, n], i) => `<button type="button" class="chip${i ? "" : " is-active"}" data-list-chip="folder" data-value="${v}" aria-pressed="${!i}">${t}<span class="chip__count">${n}</span></button>`).join("");

  /* ---------- Enquiries ---------- */
  const enquiryItems = enquiries.map((e, i) => `
            <li data-item data-status="${e.status}" data-source="${e.source}" data-search="${esc(`${e.name} ${subjectOf(e)} ${e.message}`.toLowerCase())}">
              <button type="button" class="inbox__item${e.status === "new" ? " is-unread" : ""}" data-thread-open="${i}"${i === 0 ? ' aria-current="true"' : ""}>
                ${avatar(e.name)}
                <span class="inbox__body">
                  <span class="inbox__top"><span class="inbox__name">${esc(e.name)}</span><span class="inbox__time">${e.ago}</span></span>
                  <span class="inbox__subject">${esc(subjectOf(e))}</span>
                  <span class="inbox__snippet">${esc(e.message)}</span>
                  <span class="inbox__meta">${badge(e.status)}<span class="inbox__source">${e.source === "product" ? "Product page" : "Contact form"}</span></span>
                </span>
              </button>
            </li>`).join("");
  const enquiryThreads = enquiries.map((e, i) => {
    const p = e.product && bySlug(e.product);
    const first = e.name.split(" ")[0];
    return `
          <article class="thread" data-thread="${i}"${i ? " hidden" : ""} aria-label="Enquiry from ${esc(e.name)}">
            <header class="thread__head">
              <button type="button" class="icon-btn thread__back" data-thread-back aria-label="Back to all enquiries">${icon("arrow-left")}</button>
              ${avatar(e.name, "lg")}
              <div class="thread__who">
                <h2 class="thread__name">${esc(e.name)}</h2>
                <p class="thread__contact"><a href="mailto:${e.email}">${icon("mail")}${e.email}</a><a href="tel:${e.phone.replace(/\s/g, "")}">${icon("phone")}${e.phone}</a></p>
              </div>
              <div class="thread__tools">
                <select class="select select--sm" aria-label="Status of this enquiry" data-change-toast="Status updated">${["new", "replied", "closed"].map((s) => option(s, STATUS[s][1], s === e.status)).join("")}</select>
                ${moreMenu(e.name, [
                  `<button type="button" role="menuitem" data-dialog-open="appointment-dialog" data-fill="${attrJSON({ title: "New appointment", name: e.name, phone: e.phone, date: isoDate(addDays(today, 3)), status: "pending", notes: `From enquiry: ${subjectOf(e)}` })}">${icon("calendar-check")}Book an appointment</button>`,
                  menuToast("archive", "Archive", "Enquiry archived", "It has moved out of the inbox."),
                  menuDelete("this enquiry", "The message and any replies will be removed. This can't be undone.", "Enquiry deleted"),
                ])}
              </div>
            </header>
            <dl class="facts">
              <div><dt>Received</dt><dd>${e.when}</dd></div>
              <div><dt>From</dt><dd>${e.source === "product" ? "Product page" : "Contact form"}</dd></div>
              <div><dt>Topic</dt><dd>${esc(p ? "Product enquiry" : e.topic)}</dd></div>
            </dl>
            ${p ? `<a class="thread__product" href="../product.html?slug=${p.slug}" target="_blank" rel="noopener"><img class="thumb" src="${asset(p.thumb)}" alt="" loading="lazy"><span><span class="cell-title">${esc(p.name)}</span><span class="cell-sub">${label(p.category)} · ${inr(p.price)}</span></span>${icon("external-link")}</a>` : ""}
            <div class="msg msg--in"><p>${esc(e.message)}</p><span class="msg__meta">${esc(first)} · ${e.when}</span></div>
            ${e.reply ? `<div class="msg msg--out"><p>${esc(e.reply.text)}</p><span class="msg__meta">${esc(e.reply.by)} · ${e.reply.when}</span></div>` : ""}
            <form class="composer" data-demo data-toast="Reply sent" data-toast-text="${esc(first)} will receive it by email once the backend is connected.">
              <label class="sr-only" for="reply-${i}">Reply to ${esc(e.name)}</label>
              <textarea class="composer__text" id="reply-${i}" rows="4" placeholder="Write a reply to ${esc(first)}…" data-reply-for="${esc(first)}"></textarea>
              <div class="composer__foot">
                <select class="select select--sm" aria-label="Insert a saved reply" data-saved-reply>
                  <option value="">Saved replies…</option>
                  <option value="visit">Invite to visit</option>
                  <option value="bridal">Bridal consultation</option>
                  <option value="care">Care &amp; repairs</option>
                </select>
                <button type="submit" class="btn btn--primary btn--sm">${icon("send")} Send reply</button>
              </div>
            </form>
          </article>`;
  }).join("");
  const enquiryChips = [["", "All", enquiries.length], ...["new", "replied", "closed"].map((s) => [s, STATUS[s][1], enquiries.filter((e) => e.status === s).length])]
    .map(([v, t, n], i) => `<button type="button" class="chip chip--sm${i ? "" : " is-active"}" data-list-chip="status" data-value="${v}" aria-pressed="${!i}">${t}<span class="chip__count">${n}</span></button>`).join("");

  /* ---------- Appointments ---------- */
  const monthStart = new Date(today.getFullYear(), today.getMonth(), 1);
  const monthEnd = new Date(today.getFullYear(), today.getMonth() + 1, 0);
  const gridStart = addDays(monthStart, -((monthStart.getDay() + 6) % 7));
  const gridEnd = addDays(monthEnd, (7 - monthEnd.getDay()) % 7);
  const dated = appointments.map((a) => ({ ...a, date: addDays(today, a.day) }));
  const STATUS_ICON = { confirmed: "check", pending: "clock", completed: "check-check", cancelled: "x" };
  const calendarDays = [];
  for (let d = new Date(gridStart); d <= gridEnd; d = addDays(d, 1)) {
    const items = dated.filter((a) => isoDate(a.date) === isoDate(d));
    const cls = (d.getMonth() !== today.getMonth() ? " is-out" : "") + (isoDate(d) === isoDate(today) ? " is-today" : "");
    calendarDays.push(`
              <div class="cal__day${cls}"><span class="cal__num">${d.getDate()}</span>${items.map((a) => `<button type="button" class="appt appt--${a.status}" data-dialog-open="appointment-dialog" data-fill="${attrJSON(apptFill(a))}" aria-label="${esc(`${a.time}, ${a.name}, ${STATUS[a.status][1]}`)}">${icon(STATUS_ICON[a.status])}<span class="appt__time">${a.time.replace(":00", "").replace(" ", "").toLowerCase()}</span><span class="appt__name">${esc(a.name)}</span></button>`).join("")}</div>`);
  }
  const agendaGroups = [];
  upcoming.forEach((a) => {
    const key = dayLabel(a);
    let group = agendaGroups.find((g) => g.key === key);
    if (!group) agendaGroups.push((group = { key, date: a.date, items: [] }));
    group.items.push(a);
  });
  const agenda = agendaGroups.map((g) => `
            <li class="agenda__group">
              <p class="agenda__day">${g.key}${/Today|Tomorrow/.test(g.key) ? ` · ${WEEKDAYS[g.date.getDay()]} ${shortDate(g.date)}` : ""}</p>
              <ul>${g.items.map((a) => `
                <li><button type="button" class="agenda__item" data-dialog-open="appointment-dialog" data-fill="${attrJSON(apptFill(a))}">
                  <span class="agenda__time">${a.time.split(" ")[0]}<small>${a.time.split(" ")[1]}</small></span>
                  <span class="agenda__body"><span class="cell-title">${esc(a.name)}</span><span class="cell-sub">${a.interest} · with ${a.consultant.split(" ")[0]}</span></span>
                  ${badge(a.status)}
                </button></li>`).join("")}
              </ul>
            </li>`).join("");
  const timeSlots = [];
  for (let m = 10 * 60 + 30; m <= 20 * 60; m += 30) {
    const h = Math.floor(m / 60), mm = m % 60;
    timeSlots.push(`${((h + 11) % 12) + 1}:${String(mm).padStart(2, "0")} ${h < 12 ? "AM" : "PM"}`);
  }
  const consultants = team.filter((t) => t.status === "active" && t.role !== "Editor");

  /* ---------- Subscribers ---------- */
  const subscriberRows = subscribers.map(([email, source, ago, status = "subscribed"]) => {
    const d = addDays(today, -ago);
    return `
              <tr data-item data-status="${status}" data-source="${esc(source)}" data-search="${esc(email)}" data-sort-date="${d.getTime()}">
                <td class="col-check"><input class="check" type="checkbox" data-check-row aria-label="Select ${email}"></td>
                <td><span class="cell-main">${avatar(email.split("@")[0].replace(/[._]/g, " "), "sm")}<span class="cell-title">${email}</span></span></td>
                <td class="cell-muted">${esc(source)}</td>
                <td class="cell-muted nowrap">${longDate(d)}</td>
                <td>${badge(status)}</td>
                <td class="col-actions"><div class="row-actions"><button type="button" class="icon-btn icon-btn--sm" data-confirm="Remove ${email}?" data-confirm-text="They will stop receiving the newsletter and be removed from this list." data-confirm-action="Remove" data-done="Subscriber removed" aria-label="Remove ${email}">${icon("trash-2")}</button></div></td>
              </tr>`;
  }).join("");
  const unsubscribed = subscribers.filter((s) => s[3] === "unsubscribed").length;

  /* ---------- Offer & announcements ---------- */
  const offerTpl = readFileSync(join(ROOT, "src/partials/offer.html"), "utf8");
  const offerTitle = offerTpl.match(/class="offer__title"[^>]*>([\s\S]*?)<em[^>]*>([\s\S]*?)<\/em>/);
  const mainJs = readFileSync(join(ROOT, "assets/js/main.js"), "utf8");
  const offer = {
    offerEyebrow: esc(strip((offerTpl.match(/class="offer__eyebrow">([\s\S]*?)<\/p>/) || [, ""])[1])),
    offerTitle1: esc(strip(offerTitle[1])), offerTitle2: esc(strip(offerTitle[2])),
    offerText: esc(strip((offerTpl.match(/class="offer__text">([\s\S]*?)<\/p>/) || [, ""])[1])),
    offerCta: esc(strip((offerTpl.match(/offer__cta"[^>]*>([^{<]+)/) || [, ""])[1])),
    offerNote: esc(strip((offerTpl.match(/class="offer__note">([\s\S]*?)<\/p>/) || [, ""])[1])),
    offerPercent: site.offerPercent,
    offerDelay: +(mainJs.match(/OFFER_DELAY = (\d+)/) || [, 1500])[1] / 1000,
    offerSeconds: +(mainJs.match(/OFFER_SECONDS = (\d+)/) || [, 7])[1],
    offerLinks: [["jewellery.html", "All jewellery"], ["collections.html", "Collections"], ["bridal.html", "The bridal edit"], ...categories.filter((c) => c !== "bridal").map((c) => [`${c}.html`, label(c)])]
      .map(([v, t]) => option(v, t, v === site.offerHref)).join(""),
    offerStart: isoDate(addDays(today, -6)), offerEnd: isoDate(addDays(today, 24)),
  };
  const announcementRows = announcements.map((a, i) => `
              <li class="arow" data-sort-item draggable="true">
                <span class="grip" aria-hidden="true">${icon("grip-vertical")}</span>
                <input class="input" value="${esc(a)}" aria-label="Message ${i + 1}" data-announcement>
                <button type="button" class="icon-btn icon-btn--sm" data-remove-closest=".arow" aria-label="Remove message ${i + 1}">${icon("x")}</button>
              </li>`).join("");
  const announcementPreview = announcements.concat(announcements).map((a) => `<li>${esc(a)}</li>`).join(""); // twice, so the marquee loops seamlessly

  /* ---------- Settings ---------- */
  const [addrLine1, addrLine2] = site.addressHtml.split("<br>");
  const hours = WEEKDAYS.slice(1).concat("Sun").map((d, i) => {
    const open = i < 6;
    return `
                <div class="hours__row">
                  <span class="hours__day">${d}</span>
                  <label class="switch switch--text"><input type="checkbox"${open ? " checked" : ""} data-hours-toggle><span class="switch__track" aria-hidden="true"></span><span>${open ? "Open" : "Closed"}</span></label>
                  <input class="input input--time" type="time" value="10:30" aria-label="${d} opens"${open ? "" : " disabled"}>
                  <span class="hours__to">to</span>
                  <input class="input input--time" type="time" value="20:30" aria-label="${d} closes"${open ? "" : " disabled"}>
                </div>`;
  }).join("");

  /* ---------- Users & roles ---------- */
  const userRows = team.map((u, i) => `
              <tr>
                <td><div class="cell-main">${avatar(u.name)}<span><span class="cell-title">${esc(u.name)}${i === 0 ? ' <span class="tag tag--soft">You</span>' : ""}</span><span class="cell-sub">${u.email}</span></span></div></td>
                <td>${i === 0 ? '<span class="badge badge--gold">Owner</span>' : `<select class="select select--sm select--inline" aria-label="Role of ${esc(u.name)}" data-change-toast="Role updated">${roles.map((r) => option(r.name, r.name, r.name === u.role)).join("")}</select>`}</td>
                <td>${badge(u.status)}</td>
                <td class="cell-muted">${u.last}</td>
                <td class="col-actions"><div class="row-actions">${i === 0 ? "" : u.status === "invited"
                  ? `<button type="button" class="btn btn--ghost btn--sm" data-toast="Invitation sent again" data-toast-text="${u.email}">${icon("refresh-cw")} Resend</button>${moreMenu(u.name, [menuDelete(`the invitation for ${u.email}`, "The link in their email will stop working.", "Invitation cancelled", "Cancel")])}`
                  : moreMenu(u.name, [menuToast("lock", "Reset password", "Password reset email sent", u.email), menuDelete(`${u.name} from the team`, "They will lose access to the admin straight away.", "Team member removed", "Remove")])}</div></td>
              </tr>`).join("");
  const roleHeads = roles.map((r) => `<th scope="col" class="center"><span class="role-head">${r.name}<small>${r.desc}</small></span></th>`).join("");
  const permissionRows = permissions.map(([what, ...allowed]) => `
              <tr><th scope="row">${what}</th>${allowed.map((ok, j) => `<td class="center"><input class="check" type="checkbox"${ok ? " checked" : ""}${j === 0 ? " disabled" : ""} aria-label="${esc(`${roles[j].name}: ${what}`)}"></td>`).join("")}</tr>`).join("");
  const roleOptions = roles.slice(1).map((r) => option(r.name, `${r.name} — ${r.desc}`, r.name === "Editor")).join("");

  /* ---------- Pages ---------- */
  const shared = {
    productCount: products.length, newEnquiries, notificationItems,
    notificationCount: notifications.filter((n) => n.unread).length,
    meName: team[0].name, meFirst: team[0].name.split(" ")[0], meRole: team[0].role, meInitials: initials(team[0].name), meTint: tint(team[0].name),
    todayLabel: `${WEEKDAYS_LONG[today.getDay()]}, ${today.getDate()} ${MONTHS_LONG[today.getMonth()]} ${today.getFullYear()}`,
    siteName: "Mangalam Jewellers", categoryOptions, timeSlotOptions: timeSlots.map((t) => option(t, t)).join(""),
    consultantOptions: consultants.map((t) => option(t.name, t.name)).join(""),
    interestOptions: ["Bridal jewellery", "Gold jewellery", "Diamond jewellery", "Bespoke commission"].map((t) => option(t, t)).join(""),
    extraScripts: "",
  };
  const withData = '\n  <script src="../assets/js/data.js"></script>';

  const adminPages = [
    { file: "index.html", main: "dashboard", nav: "dashboard", title: "Dashboard",
      vars: { kpiTiles, weeksJSON: attrJSON(weeks), weeksTable, weeksTotal: figures.weeklyEnquiries.reduce((a, b) => a + b, 0), categoryBars, recentEnquiries, upcomingList, attention, offerPercent: site.offerPercent, offerTitle1: offer.offerTitle1, offerTitle2: offer.offerTitle2, categoryCount: categories.length } },
    { file: "products.html", main: "products", nav: "products", title: "Products",
      vars: { productRows, productChips, categoryTotal: categories.length } },
    { file: "product-new.html", main: "product-form", nav: "products", title: "Add product", vars: { ...productForm(null), extraScripts: withData } },
    { file: "product-edit.html", main: "product-form", nav: "products", title: "Edit product", vars: { ...productForm(bySlug(featuredSlugs[0])), extraScripts: withData } },
    { file: "categories.html", main: "categories", nav: "categories", title: "Categories", vars: { categoryRows, categoryTotal: categories.length } },
    { file: "collections.html", main: "collections", nav: "collections", title: "Collections", vars: { collectionCards, collectionTotal: collections.length, valueOptions } },
    { file: "homepage.html", main: "homepage", nav: "homepage", title: "Homepage",
      vars: { sectionRows, sectionTotal: homeSections.length, ...heroFields, heroPins, pinRows, featuredPicks, bridalPanels, productOptions: productOptions(""), heroImage: asset("assets/images/campaign/hero.jpg"), extraScripts: withData } },
    { file: "pages.html", main: "pages", nav: "pages", title: "Pages & banners", vars: { pageRows, pageTotal: sitePageInfo.length, seoIssues } },
    { file: "journal.html", main: "journal", nav: "journal", title: "Journal", vars: { articleCards, journalChips, articleTotal: articles.length } },
    { file: "article-new.html", main: "article-form", nav: "journal", title: "New story", vars: { ...articleForm(null), extraScripts: withData } },
    { file: "article-edit.html", main: "article-form", nav: "journal", title: "Edit story", vars: { ...articleForm(articles[0]), extraScripts: withData } },
    { file: "testimonials.html", main: "testimonials", nav: "testimonials", title: "Testimonials", vars: { testimonialCards, testimonialTotal: testimonials.length } },
    { file: "media.html", main: "media", nav: "media", title: "Media library", vars: { mediaTiles, mediaChips, mediaTotal: media.length, mediaSize: fileSize(mediaBytes) } },
    { file: "enquiries.html", main: "enquiries", nav: "enquiries", title: "Enquiries", vars: { enquiryItems, enquiryThreads, enquiryChips, enquiryTotal: enquiries.length } },
    { file: "appointments.html", main: "appointments", nav: "appointments", title: "Appointments",
      vars: { calendarDays: calendarDays.join(""), agenda, monthLabel: `${MONTHS_LONG[today.getMonth()]} ${today.getFullYear()}`, upcomingTotal: upcoming.length, todayTotal: todayCount, pendingTotal: upcoming.filter((a) => a.status === "pending").length } },
    { file: "subscribers.html", main: "subscribers", nav: "subscribers", title: "Subscribers",
      vars: { subscriberRows, subscriberTotal: figures.subscriberGrowth[11].toLocaleString("en-IN"), subscribersMonth: figures.subscribersThisMonth, unsubscribed, subscriberSpark: sparkline(figures.subscriberGrowth), listed: subscribers.length } },
    { file: "offers.html", main: "offers", nav: "offers", title: "Offers & announcements", vars: { ...offer, announcementRows, announcementPreview } },
    { file: "settings.html", main: "settings", nav: "settings", title: "Settings",
      vars: { sitePhone: esc(site.phone), siteEmail: esc(site.email), addrLine1: esc(addrLine1), addrLine2: esc(addrLine2), siteMaps: site.mapsUrl, siteInstagram: site.instagram, siteFacebook: site.facebook, siteYoutube: site.youtube, founded: new Date().getFullYear() - Number(site.years), hours, calmChecked: /CALM_FOR_REDUCED_MOTION\s*=\s*true/.test(mainJs) ? " checked" : "", homeTitle: esc(sitePages[0].title), homeDesc: esc(sitePages[0].description) } },
    { file: "users.html", main: "users", nav: "users", title: "Users & roles", vars: { userRows, roleHeads, permissionRows, roleOptions, teamTotal: team.length } },
    { file: "login.html", main: "login", layout: "layout-auth", title: "Sign in", vars: {} },
  ];

  mkdirSync(join(ROOT, "admin"), { recursive: true });
  for (const p of adminPages) {
    const vars = { ...shared, page: p.main, ...p.vars, title: esc(p.title) };
    const main = render(read(`pages/${p.main}.html`), vars);
    let out = render(read(`${p.layout || "layout"}.html`), { ...vars, main });
    out = out.replace(/(<body[^>]*>)/, `$1\n  ${sprite(out)}`);
    if (p.nav) out = out.replaceAll(`data-nav="${p.nav}"`, `data-nav="${p.nav}" aria-current="page"`);
    writeFileSync(join(ROOT, "admin", p.file), out);
    console.log("built", ("admin/" + p.file).padEnd(26), (out.length / 1024).toFixed(1) + " KB");
  }
}
