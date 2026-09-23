/* Mangalam Jewellers — site behaviour.
 * Loading screen, header scroll state, dialogs & sheets, search, wishlist hearts,
 * and the data-driven parts of each page (catalogue, product, journal, article).
 */
(function () {
  "use strict";

  var UI = window.MJUI;
  var MJ = window.MJ;
  var icon = UI.icon, btn = UI.btn;
  var products = MJ.products, articles = MJ.articles;
  var formatPrice = MJ.formatPrice, capitalize = MJ.capitalize;
  var page = document.body.getAttribute("data-page");
  var params = new URLSearchParams(window.location.search);

  function $(sel, root) { return (root || document).querySelector(sel); }
  function $$(sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }
  function esc(v) {
    return String(v).replace(/[&<>"']/g, function (c) { return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]; });
  }

  /* ---------- Loading screen (first page view of the visit) ---------- */
  var loader = document.getElementById("loader");
  if (loader) {
    if (document.documentElement.classList.contains("mj-seen")) loader.remove();
    else setTimeout(function () {
      loader.remove();
      try { sessionStorage.setItem("mj-loaded", "1"); } catch (e) { /* storage unavailable */ }
    }, 1100);
  }

  /* ---------- Header: transparent over the home hero, solid once scrolled ---------- */
  var header = document.getElementById("site-header");
  if (header && header.hasAttribute("data-overlay")) {
    var brand = $("[data-brand]", header);
    var book = $("[data-book]", header);
    var HEADER_SOLID = ["border-gold/50", "bg-background/90", "shadow-[0_1px_0_rgba(216,179,106,0.25)]", "backdrop-blur-xl"];
    var HEADER_CLEAR = ["border-primary-foreground/20", "bg-transparent", "text-primary-foreground"];
    var BOOK_SOLID = ["bg-primary", "hover:bg-primary/90"];
    var BOOK_CLEAR = ["border", "border-gold", "bg-transparent", "hover:bg-gold", "hover:text-wine"];
    var swap = function (el, add, remove) { remove.forEach(function (c) { el.classList.remove(c); }); add.forEach(function (c) { el.classList.add(c); }); };
    var updateHeader = function () {
      var solid = window.scrollY > 24;
      swap(header, solid ? HEADER_SOLID : HEADER_CLEAR, solid ? HEADER_CLEAR : HEADER_SOLID);
      swap(brand, [solid ? "text-primary" : "text-primary-foreground"], [solid ? "text-primary-foreground" : "text-primary"]);
      swap(book, solid ? BOOK_SOLID : BOOK_CLEAR, solid ? BOOK_CLEAR : BOOK_SOLID);
    };
    updateHeader();
    window.addEventListener("scroll", updateHeader, { passive: true });
  }

  /* ---------- Dialogs & sheets ---------- */
  var stack = [];
  var AUTOFOCUS = "button:not([disabled]), input:not([disabled]):not([type=hidden]), textarea:not([disabled]), select:not([disabled])";
  var TABBABLE = "a[href], " + AUTOFOCUS + ", [tabindex]:not([tabindex='-1'])";

  function openModal(name, trigger) {
    var modal = $('[data-modal="' + name + '"]');
    if (!modal || stack.some(function (m) { return m.modal === modal; })) return;
    var panel = $("[data-panel]", modal), overlay = $("[data-overlay]", modal);
    if (!stack.length) {
      var gap = window.innerWidth - document.documentElement.clientWidth;
      document.body.style.overflow = "hidden";
      if (gap > 0) document.body.style.paddingRight = gap + "px";
    }
    modal.hidden = false;
    overlay.setAttribute("data-state", "open");
    panel.setAttribute("data-state", "open");
    stack.push({ modal: modal, trigger: trigger || null });
    modal.dispatchEvent(new CustomEvent("mj:open"));
    var first = $("[data-autofocus]", modal) || $(AUTOFOCUS, panel) || panel;
    first.focus({ preventScroll: true });
    if (first.select && first.hasAttribute("data-autofocus")) first.select();
  }

  function closeModal(modal, restoreFocus) {
    var index = stack.findIndex(function (m) { return m.modal === modal; });
    if (index === -1) return;
    var entry = stack.splice(index, 1)[0];
    var panel = $("[data-panel]", modal), overlay = $("[data-overlay]", modal);
    overlay.setAttribute("data-state", "closed");
    panel.setAttribute("data-state", "closed");
    var finished = false;
    var finish = function () {
      if (finished || panel.getAttribute("data-state") !== "closed") return;
      finished = true;
      modal.hidden = true;
      $$("form", modal).forEach(function (f) { f.reset(); });
      if (!stack.length) { document.body.style.overflow = ""; document.body.style.paddingRight = ""; }
    };
    panel.addEventListener("animationend", function onEnd(e) { if (e.target === panel) { panel.removeEventListener("animationend", onEnd); finish(); } });
    setTimeout(finish, 400);
    if (restoreFocus !== false && entry.trigger && document.contains(entry.trigger)) entry.trigger.focus({ preventScroll: true });
  }

  function setWish(el, on) {
    el.setAttribute("aria-pressed", String(on));
    if (el.hasAttribute("data-label-on")) el.setAttribute("aria-label", on ? el.getAttribute("data-label-on") : el.getAttribute("data-label-off"));
    var svg = $("svg", el);
    if (svg) { svg.classList.toggle("fill-primary", on); svg.classList.toggle("text-primary", on); }
  }

  document.addEventListener("click", function (e) {
    var wish = e.target.closest("[data-wish]");
    if (wish) {
      e.preventDefault();
      e.stopPropagation();
      var on = wish.getAttribute("aria-pressed") !== "true";
      var group = wish.getAttribute("data-wish-group");
      (group ? $$('[data-wish-group="' + group + '"]') : [wish]).forEach(function (el) { setWish(el, on); });
      return;
    }
    var opener = e.target.closest("[data-open]");
    if (opener) {
      e.preventDefault();
      var current = opener.closest("[data-modal]");
      if (current) closeModal(current, false);
      openModal(opener.getAttribute("data-open"), current ? null : opener);
      return;
    }
    var closer = e.target.closest("[data-close]");
    if (closer) {
      var modal = closer.closest("[data-modal]");
      if (modal) closeModal(modal);
    }
  });

  document.addEventListener("keydown", function (e) {
    var top = stack[stack.length - 1];
    if (!top) return;
    if (e.key === "Escape") {
      e.preventDefault();
      closeModal(top.modal);
    } else if (e.key === "Tab") {
      var panel = $("[data-panel]", top.modal);
      var items = $$(TABBABLE, panel).filter(function (el) { return el.getClientRects().length > 0; });
      if (!items.length) { e.preventDefault(); return; }
      var first = items[0], last = items[items.length - 1];
      if (e.shiftKey && (document.activeElement === first || !panel.contains(document.activeElement))) { e.preventDefault(); last.focus(); }
      else if (!e.shiftKey && (document.activeElement === last || !panel.contains(document.activeElement))) { e.preventDefault(); first.focus(); }
    }
  });

  /* Forms in the account / appointment dialogs close on submit; the newsletter stays put. */
  $$("form[data-close-on-submit]").forEach(function (form) {
    form.addEventListener("submit", function (e) {
      e.preventDefault();
      var modal = form.closest("[data-modal]");
      if (modal) closeModal(modal);
    });
  });
  $$("form[data-newsletter]").forEach(function (form) {
    form.addEventListener("submit", function (e) { e.preventDefault(); });
  });

  /* ---------- Search ---------- */
  var searchInput = document.getElementById("search-input");
  var searchResults = document.getElementById("search-results");
  if (searchInput && searchResults) {
    searchInput.addEventListener("input", function () {
      var q = searchInput.value.trim().toLowerCase();
      if (!q) { searchResults.hidden = true; searchResults.innerHTML = ""; return; }
      var results = products.filter(function (p) {
        return (p.name + " " + p.category + " " + p.metal + " " + p.style).toLowerCase().indexOf(q) !== -1;
      }).slice(0, 8);
      searchResults.hidden = false;
      searchResults.innerHTML = results.length
        ? results.map(function (p) {
          return '<a href="product.html?slug=' + p.slug + '" class="flex items-center gap-4 border-b border-border py-3">' +
            '<img src="' + p.image + '" alt="" class="size-16 object-cover" style="object-position: ' + p.imagePosition + '">' +
            '<span class="font-serif text-lg">' + esc(p.name) + "</span>" +
            '<span class="ml-auto text-xs capitalize text-muted-foreground">' + p.category + "</span></a>";
        }).join("")
        : '<div class="py-10 text-center"><p class="font-serif text-2xl">No pieces found</p>' +
          '<p class="mt-2 text-sm text-muted-foreground">Try another jewellery style or collection.</p>' +
          '<a href="jewellery.html" class="' + btn("default", "default", "mt-5 rounded-none text-[10px] tracking-[0.16em]") + '">VIEW ALL JEWELLERY</a></div>';
    });
  }

  /* ---------- Shared card templates ---------- */
  function productCard(p) {
    // Mirror alternate products so cards that share a category photograph read as distinct compositions.
    var flip = p.slug.split("").reduce(function (sum, ch) { return sum + ch.charCodeAt(0); }, 0) % 2 === 1;
    return '<article class="product-card group min-w-0">' +
      '<a href="product.html?slug=' + p.slug + '" class="block">' +
        '<div class="relative aspect-[4/5] overflow-hidden border border-transparent bg-secondary transition duration-500 group-hover:-translate-y-1 group-hover:border-gold">' +
          '<div class="' + (flip ? "size-full -scale-x-100" : "size-full") + '">' +
            '<img src="' + p.image + '" loading="lazy" width="800" height="800" alt="' + esc(p.name) + '" style="object-position: ' + p.imagePosition + '" class="size-full object-cover transition duration-500 group-hover:scale-[1.03] group-hover:brightness-105">' +
          "</div>" +
          '<button type="button" data-wish aria-pressed="false" aria-label="Add ' + esc(p.name) + ' to wishlist" data-label-on="Remove ' + esc(p.name) + ' from wishlist" data-label-off="Add ' + esc(p.name) + ' to wishlist" class="' + btn("ghost", "icon", "absolute right-2 top-2 bg-background/90 transition-opacity duration-500 lg:opacity-0 lg:group-hover:opacity-100") + '">' + icon("heart") + "</button>" +
          '<span class="absolute inset-x-3 bottom-3 bg-primary px-3 py-3 text-center text-[8px] tracking-[0.18em] text-primary-foreground opacity-0 transition duration-500 group-hover:opacity-100">VIEW DETAILS</span>' +
        "</div>" +
        '<p class="mt-4 text-[8px] uppercase tracking-[0.18em] text-muted-foreground">' + capitalize(p.category) + " · " + p.metal + "</p>" +
        '<h2 class="mt-1 truncate font-serif text-lg sm:text-xl">' + esc(p.name) + "</h2>" +
        '<p class="mt-2 text-xs">' + formatPrice(p.price) + "</p>" +
      "</a></article>";
  }

  function articleMeta(a, withDate) {
    return a.category.toUpperCase() + " · " + (withDate ? a.date.toUpperCase() + " · " : "") + a.readTime.toUpperCase();
  }

  /* ---------- Home ---------- */
  if (page === "home") {
    var featuredGrid = document.getElementById("featured-grid");
    if (featuredGrid) {
      featuredGrid.innerHTML = MJ.featuredSlugs
        .map(function (slug) { return products.filter(function (p) { return p.slug === slug; })[0]; })
        .filter(Boolean).map(productCard).join("");
    }

    var journalPreview = document.getElementById("journal-preview");
    if (journalPreview) {
      journalPreview.innerHTML = articles.slice(0, 3).map(function (a) {
        return '<a href="article.html?slug=' + a.slug + '" class="group block">' +
          '<div class="image-card aspect-[4/3] overflow-hidden bg-secondary"><img src="' + a.image + '" alt="' + esc(a.title) + '" loading="lazy" class="size-full object-cover" style="object-position: ' + a.imagePosition + '"></div>' +
          '<p class="mt-5 text-[9px] tracking-[0.2em] text-gold">' + articleMeta(a, false) + "</p>" +
          '<h3 class="mt-2 font-serif text-2xl font-normal">' + esc(a.title) + "</h3>" +
          '<p class="mt-3 text-sm leading-6 text-muted-foreground">' + esc(a.excerpt) + "</p></a>";
      }).join("");
    }

    var quoteBox = $("[data-testimonials]");
    if (quoteBox) {
      var dots = $$("[data-t-dot]", quoteBox);
      dots.forEach(function (dot, i) {
        dot.addEventListener("click", function () {
          var t = MJ.testimonials[i] || MJ.testimonials[0];
          $("[data-t-quote]", quoteBox).textContent = t.quote;
          $("[data-t-who]", quoteBox).textContent = t.who;
          $("[data-t-occasion]", quoteBox).textContent = t.occasion.toUpperCase();
          dots.forEach(function (d, j) { d.classList.toggle("bg-gold", j === i); d.classList.toggle("bg-border", j !== i); });
        });
      });
    }
  }

  /* ---------- Custom select (Radix-style sort menu) ---------- */
  function initSelect(root, onChange) {
    var trigger = $("[role=combobox]", root);
    var list = $("[role=listbox]", root);
    var options = $$("[role=option]", list);
    var open = false;
    var setOpen = function (value) {
      open = value;
      trigger.setAttribute("aria-expanded", String(value));
      trigger.setAttribute("data-state", value ? "open" : "closed");
      list.hidden = !value;
      list.setAttribute("data-state", value ? "open" : "closed");
      if (value) (options.filter(function (o) { return o.getAttribute("aria-selected") === "true"; })[0] || options[0]).focus();
    };
    var choose = function (opt) {
      options.forEach(function (o) {
        var on = o === opt;
        o.setAttribute("aria-selected", String(on));
        $("[data-indicator]", o).innerHTML = on ? icon("check", "h-4 w-4") : "";
      });
      $("[data-select-value]", root).textContent = opt.getAttribute("data-label");
      setOpen(false);
      trigger.focus();
      onChange(opt.getAttribute("data-value"));
    };
    trigger.addEventListener("click", function () { setOpen(!open); });
    trigger.addEventListener("keydown", function (e) {
      if (["ArrowDown", "ArrowUp", "Enter", " "].indexOf(e.key) !== -1) { e.preventDefault(); setOpen(true); }
    });
    options.forEach(function (opt, i) {
      opt.addEventListener("click", function () { choose(opt); });
      opt.addEventListener("mousemove", function () { if (document.activeElement !== opt) opt.focus({ preventScroll: true }); });
      opt.addEventListener("keydown", function (e) {
        if (e.key === "ArrowDown") { e.preventDefault(); options[Math.min(i + 1, options.length - 1)].focus(); }
        else if (e.key === "ArrowUp") { e.preventDefault(); options[Math.max(i - 1, 0)].focus(); }
        else if (e.key === "Enter" || e.key === " ") { e.preventDefault(); choose(opt); }
        else if (e.key === "Escape") { e.preventDefault(); e.stopPropagation(); setOpen(false); trigger.focus(); }
        else if (e.key === "Tab") { setOpen(false); }
      });
    });
    document.addEventListener("pointerdown", function (e) { if (open && !root.contains(e.target)) setOpen(false); });
  }

  /* ---------- Catalogue (all jewellery, bridal and category pages) ---------- */
  if (page === "catalog") {
    var category = document.body.getAttribute("data-category") || "";
    var metalParam = !category && params.get("metal");
    var styleParam = !category && params.get("style");
    var state = {
      metals: metalParam ? [capitalize(metalParam)] : [],
      styles: styleParam ? [capitalize(styleParam)] : [],
      price: "all",
      sort: "featured",
    };
    var PRICES = [["all", "All prices"], ["under", "Under ₹25,000"], ["mid", "₹25,000–₹50,000"], ["high", "₹50,000+"]];

    var filterGroup = function (title, body) {
      return '<div class="border-b border-border pb-6"><p class="mb-3 flex items-center justify-between text-[10px] tracking-[0.18em]">' + title + icon("chevron-down", "size-4") + "</p>" + body + "</div>";
    };
    var checkRow = function (panel, kind, value) {
      var id = panel + "-f-" + value.toLowerCase();
      return '<div class="flex min-h-10 items-center gap-3">' +
        '<button type="button" role="checkbox" id="' + id + '" aria-checked="false" data-state="unchecked" data-filter="' + kind + '" data-value="' + value + '" class="' + UI.checkbox() + '"></button>' +
        '<label for="' + id + '" class="' + UI.label("font-normal") + '">' + value + "</label></div>";
    };
    var panelHTML = function (panel) {
      return '<div class="mt-6 space-y-8 lg:mt-0 lg:pt-4">' +
        filterGroup("METAL", ["Gold", "Diamond"].map(function (v) { return checkRow(panel, "metal", v); }).join("")) +
        filterGroup("PRICE", PRICES.map(function (pr) {
          return '<label class="flex min-h-10 items-center gap-3 text-sm"><input type="radio" name="price-' + panel + '" value="' + pr[0] + '" data-price class="accent-primary">' + pr[1] + "</label>";
        }).join("")) +
        filterGroup("STYLE", ["Classic", "Traditional", "Modern", "Bridal"].map(function (v) { return checkRow(panel, "style", v); }).join("")) +
        "</div>";
    };

    $$("[data-filter-panel]").forEach(function (el) { el.innerHTML = panelHTML(el.getAttribute("data-filter-panel")); });

    var filtered = function () {
      return products.filter(function (p) {
        return (!category || p.category === category)
          && (!state.metals.length || state.metals.indexOf(p.metal) !== -1)
          && (!state.styles.length || state.styles.indexOf(p.style) !== -1)
          && (state.price === "all" || (state.price === "under" && p.price < 25000) || (state.price === "mid" && p.price >= 25000 && p.price <= 50000) || (state.price === "high" && p.price > 50000));
      }).sort(function (a, b) {
        return state.sort === "low" ? a.price - b.price : state.sort === "high" ? b.price - a.price : state.sort === "new" ? Number(b.isNew) - Number(a.isNew) : 0;
      });
    };

    var results = $("[data-catalog-results]");
    var renderCatalog = function () {
      $$("[data-filter]").forEach(function (box) {
        var list = box.getAttribute("data-filter") === "metal" ? state.metals : state.styles;
        var on = list.indexOf(box.getAttribute("data-value")) !== -1;
        box.setAttribute("aria-checked", String(on));
        box.setAttribute("data-state", on ? "checked" : "unchecked");
        box.innerHTML = on ? '<span data-state="checked" class="pointer-events-none grid place-content-center text-current">' + icon("check", "h-4 w-4") + "</span>" : "";
      });
      $$("[data-price]").forEach(function (radio) { radio.checked = radio.value === state.price; });
      var shown = filtered();
      $$("[data-catalog-count]").forEach(function (el) { el.textContent = shown.length + " PIECES"; });
      $$("[data-catalog-apply]").forEach(function (el) { el.textContent = "VIEW " + shown.length + " PIECES"; });
      results.innerHTML = shown.length
        ? '<div class="grid grid-cols-2 gap-x-3 gap-y-10 md:grid-cols-3 xl:grid-cols-4">' + shown.map(productCard).join("") + "</div>"
        : '<div class="py-24 text-center"><p class="font-serif text-3xl">No pieces match these filters</p>' +
          '<p class="mt-2 text-sm text-muted-foreground">Try widening your selection.</p>' +
          '<button type="button" data-clear-filters class="' + btn("outline", "default", "mt-6 rounded-none") + '">CLEAR FILTERS</button></div>';
    };

    document.addEventListener("click", function (e) {
      var box = e.target.closest("[data-filter]");
      if (box) {
        var key = box.getAttribute("data-filter") === "metal" ? "metals" : "styles";
        var value = box.getAttribute("data-value");
        state[key] = state[key].indexOf(value) !== -1 ? state[key].filter(function (x) { return x !== value; }) : state[key].concat(value);
        renderCatalog();
        return;
      }
      if (e.target.closest("[data-clear-filters]")) {
        state.metals = [];
        state.styles = [];
        state.price = "all";
        renderCatalog();
      }
    });
    document.addEventListener("change", function (e) {
      if (e.target.matches("[data-price]")) { state.price = e.target.value; renderCatalog(); }
    });

    var sortSelect = $("[data-select]");
    if (sortSelect) initSelect(sortSelect, function (value) { state.sort = value; renderCatalog(); });

    renderCatalog();
  }

  /* ---------- Product detail ---------- */
  if (page === "product") {
    var productRoot = document.getElementById("product-root");
    var slug = params.get("slug") || MJ.featuredSlugs[0];
    var product = products.filter(function (p) { return p.slug === slug; })[0];

    if (!product) {
      productRoot.className = "px-5 pb-24 pt-48 text-center";
      productRoot.innerHTML = '<h1 class="font-serif text-5xl">Piece not found</h1>' +
        '<p class="mt-3 text-sm text-muted-foreground">This jewel may have found its home already.</p>' +
        '<a href="jewellery.html" class="' + btn("default", "default", "mt-6 rounded-none text-[10px] tracking-[0.16em]") + '">VIEW ALL JEWELLERY</a>';
    } else {
      var views = [product.imagePosition, "50% 40%", "50% 65%"];
      var related = products.filter(function (p) { return p.category === product.category && p.slug !== product.slug; }).slice(0, 4);
      var THUMB_ON = ["border-gold"];
      var THUMB_OFF = ["border-transparent", "opacity-70", "hover:opacity-100"];
      var name = esc(product.name);

      productRoot.innerHTML =
        '<nav aria-label="Breadcrumb" class="mx-auto max-w-[1400px] text-[9px] tracking-[0.18em] text-muted-foreground">' +
          '<a href="index.html" class="hover:text-gold">HOME</a> / <a href="jewellery.html" class="hover:text-gold">JEWELLERY</a> / <a href="' + product.category + '.html" class="hover:text-gold">' + product.category.toUpperCase() + "</a>" +
        "</nav>" +
        '<div class="mx-auto mt-8 grid max-w-[1400px] gap-12 lg:grid-cols-[1.05fr_1fr]">' +
          "<div>" +
            '<div class="group relative aspect-square overflow-hidden bg-secondary">' +
              '<img data-main-image src="' + product.image + '" alt="' + name + '" class="size-full object-cover transition-transform duration-700 group-hover:scale-[1.4] group-hover:cursor-zoom-in" style="object-position: ' + views[0] + '">' +
              '<button type="button" data-wish data-wish-group="product" aria-pressed="false" aria-label="Add to wishlist" data-label-on="Remove from wishlist" data-label-off="Add to wishlist" class="' + btn("ghost", "icon", "absolute right-3 top-3 bg-background/90 lg:opacity-0 lg:group-hover:opacity-100") + '">' + icon("heart") + "</button>" +
            "</div>" +
            '<div class="mt-3 flex gap-3 overflow-x-auto pb-1">' +
              views.map(function (pos, i) {
                return '<button type="button" data-view="' + i + '" aria-label="View image ' + (i + 1) + '" class="size-20 shrink-0 overflow-hidden border transition ' + (i === 0 ? THUMB_ON : THUMB_OFF).join(" ") + '">' +
                  '<img src="' + product.image + '" alt="" class="size-full object-cover" style="object-position: ' + pos + '"></button>';
              }).join("") +
            "</div>" +
          "</div>" +
          '<div class="lg:py-8">' +
            '<p class="text-[9px] tracking-[0.22em] text-gold">' + product.collection.toUpperCase() + "</p>" +
            '<h1 class="mt-3 font-serif text-5xl font-normal leading-tight sm:text-6xl">' + name + "</h1>" +
            '<p class="mt-4 font-serif text-3xl">' + formatPrice(product.price) + "</p>" +
            '<p class="mt-6 max-w-lg text-sm leading-7 text-muted-foreground">' + esc(product.description) + "</p>" +
            '<dl class="mt-8 grid max-w-md grid-cols-3 gap-4 border-y border-border py-6 text-sm">' +
              '<div><dt class="text-[8px] tracking-[0.16em] text-muted-foreground">GOLD PURITY</dt><dd class="mt-2 font-serif text-xl">' + product.purity + "</dd></div>" +
              '<div><dt class="text-[8px] tracking-[0.16em] text-muted-foreground">STONE</dt><dd class="mt-2 font-serif text-xl">' + (product.metal === "Diamond" ? "Diamond" : "Gold work") + "</dd></div>" +
              '<div><dt class="text-[8px] tracking-[0.16em] text-muted-foreground">COLLECTION</dt><dd class="mt-2 font-serif text-xl capitalize">' + product.category + "</dd></div>" +
            "</dl>" +
            '<div class="mt-8 flex flex-col gap-3 sm:flex-row">' +
              '<button type="button" data-open="enquire" class="' + btn("default", "default", "h-12 flex-1 rounded-none text-[10px] tracking-[0.18em]") + '">' + icon("message-circle") + " ENQUIRE NOW</button>" +
              '<button type="button" data-wish data-wish-group="product" aria-pressed="false" class="' + btn("outline", "default", "h-12 flex-1 rounded-none border-gold text-[10px] tracking-[0.18em] hover:bg-secondary") + '">' + icon("heart") + " ADD TO WISHLIST</button>" +
            "</div>" +
            '<p class="mt-5 text-xs leading-6 text-muted-foreground">Handcrafted to order in Surat · BIS hallmarked · Insured delivery across India</p>' +
          "</div>" +
        "</div>" +
        (related.length
          ? '<section class="mx-auto mt-24 max-w-[1400px]">' +
              '<div class="mb-10 flex items-end justify-between">' +
                '<h2 class="font-serif text-5xl font-normal">You May Also Like</h2>' +
                '<a href="' + product.category + '.html" class="hidden items-center gap-2 text-[9px] tracking-[0.18em] hover:text-gold sm:flex">MORE ' + product.category.toUpperCase() + " " + icon("arrow-right", "size-3") + "</a>" +
              "</div>" +
              '<div class="grid grid-cols-2 gap-x-3 gap-y-10 md:grid-cols-4">' + related.map(productCard).join("") + "</div>" +
            "</section>"
          : "");

      var mainImage = $("[data-main-image]", productRoot);
      var thumbs = $$("[data-view]", productRoot);
      thumbs.forEach(function (thumb, i) {
        thumb.addEventListener("click", function () {
          mainImage.style.objectPosition = views[i];
          thumbs.forEach(function (t, j) {
            (j === i ? THUMB_OFF : THUMB_ON).forEach(function (c) { t.classList.remove(c); });
            (j === i ? THUMB_ON : THUMB_OFF).forEach(function (c) { t.classList.add(c); });
          });
        });
      });

      var enquire = $('[data-modal="enquire"]');
      if (enquire) {
        var message = "I would like to know more about the " + product.name + ".";
        $("[data-product-name]", enquire).textContent = product.name;
        var enquireForm = $("[data-enquire-form]", enquire);
        var enquireSent = $("[data-enquire-sent]", enquire);
        $("textarea", enquireForm).defaultValue = message;
        enquire.addEventListener("mj:open", function () {
          enquireForm.reset();
          enquireForm.hidden = false;
          enquireSent.hidden = true;
        });
        enquireForm.addEventListener("submit", function (e) {
          e.preventDefault();
          enquireForm.hidden = true;
          enquireSent.hidden = false;
        });
      }
    }
  }

  /* ---------- Journal ---------- */
  if (page === "journal") {
    var journalGrid = document.getElementById("journal-grid");
    journalGrid.innerHTML = articles.map(function (a) {
      return '<a href="article.html?slug=' + a.slug + '" class="group block">' +
        '<div class="image-card aspect-[16/10] overflow-hidden bg-secondary"><img src="' + a.image + '" alt="' + esc(a.title) + '" loading="lazy" class="size-full object-cover" style="object-position: ' + a.imagePosition + '"></div>' +
        '<p class="mt-5 text-[9px] tracking-[0.2em] text-gold">' + articleMeta(a, true) + "</p>" +
        '<h2 class="mt-2 font-serif text-3xl font-normal">' + esc(a.title) + "</h2>" +
        '<p class="mt-3 max-w-xl text-sm leading-6 text-muted-foreground">' + esc(a.excerpt) + "</p></a>";
    }).join("");
  }

  /* ---------- Journal article ---------- */
  if (page === "article") {
    var articleRoot = document.getElementById("article-root");
    var articleSlug = params.get("slug") || articles[0].slug;
    var article = articles.filter(function (a) { return a.slug === articleSlug; })[0];

    if (!article) {
      var missing = document.createElement("div");
      missing.className = "px-5 pb-24 pt-48 text-center";
      missing.innerHTML = '<h1 class="font-serif text-5xl">Article not found</h1>' +
        '<p class="mt-3 text-sm text-muted-foreground">The story you\'re looking for may have moved.</p>' +
        '<a href="journal.html" class="' + btn("default", "default", "mt-6 rounded-none text-[10px] tracking-[0.16em]") + '">ALL ARTICLES</a>';
      articleRoot.replaceWith(missing);
    } else {
      articleRoot.innerHTML =
        '<header class="mx-auto max-w-3xl px-5 pt-14 text-center">' +
          '<p class="text-[9px] tracking-[0.24em] text-gold">' + articleMeta(article, true) + "</p>" +
          '<h1 class="mt-5 font-serif text-5xl font-normal leading-tight sm:text-6xl">' + esc(article.title) + "</h1>" +
          '<p class="mt-6 font-serif text-xl font-light italic text-muted-foreground">' + esc(article.excerpt) + "</p>" +
        "</header>" +
        '<div class="mx-auto mt-12 max-w-5xl px-5"><div class="aspect-[16/9] overflow-hidden bg-secondary">' +
          '<img src="' + article.image + '" alt="' + esc(article.title) + '" class="size-full object-cover" style="object-position: ' + article.imagePosition + '">' +
        "</div></div>" +
        '<div class="mx-auto max-w-2xl px-5 py-14">' +
          article.body.map(function (para, i) { return '<p class="text-[15px] leading-8 text-foreground/85' + (i ? " mt-6" : "") + '">' + esc(para) + "</p>"; }).join("") +
        "</div>" +
        '<footer class="mx-auto max-w-2xl px-5 pb-24"><div class="h-px w-full bg-border"></div>' +
          '<a href="journal.html" class="' + btn("outline", "default", "mt-8 rounded-none border-gold text-[10px] tracking-[0.16em] hover:bg-secondary") + '">' + icon("arrow-left", "size-3") + " ALL ARTICLES</a>" +
        "</footer>";
    }
  }

  /* ---------- Contact ---------- */
  var contactForm = $("[data-contact-form]");
  if (contactForm) {
    contactForm.addEventListener("submit", function (e) {
      e.preventDefault();
      contactForm.hidden = true;
      $("[data-contact-sent]").hidden = false;
    });
  }
})();
