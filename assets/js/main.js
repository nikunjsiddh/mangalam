/* Mangalam Jewellers — site behaviour.
 * Loader, header, mega menu, dialogs & drawers, wishlist (saved in localStorage), search,
 * scroll animations, and the data-driven pages (catalogue, product, journal, article).
 */
(function () {
  "use strict";

  var MJ = window.MJ;
  var icon = window.MJUI.icon;
  var products = MJ.products, articles = MJ.articles;
  var formatPrice = MJ.formatPrice, capitalize = MJ.capitalize;
  var html = document.documentElement;
  var page = document.body.getAttribute("data-page");
  var params = new URLSearchParams(window.location.search);
  var reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  var canHover = window.matchMedia("(hover: hover) and (pointer: fine)").matches;

  function $(sel, root) { return (root || document).querySelector(sel); }
  function $$(sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }
  function esc(v) {
    return String(v).replace(/[&<>"']/g, function (c) { return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]; });
  }
  function bySlug(slug) { return products.filter(function (p) { return p.slug === slug; })[0]; }
  function hash(str) { return str.split("").reduce(function (sum, ch) { return sum + ch.charCodeAt(0); }, 0); }

  /* ---------- Loader: shown on the first page of a visit ---------- */
  var loader = document.getElementById("loader");
  var loaded = false;
  var loadedQueue = [];
  function afterLoad(fn) { if (loaded) fn(); else loadedQueue.push(fn); }
  function finishLoading() {
    if (loaded) return;
    loaded = true;
    html.classList.add("is-loaded");
    loadedQueue.forEach(function (fn) { fn(); });
    loadedQueue = [];
    if (loader) {
      loader.classList.add("is-done");
      setTimeout(function () { loader.remove(); }, 900);
    }
    try { sessionStorage.setItem("mj-loaded", "1"); } catch (e) { /* storage unavailable */ }
  }
  if (loader && !html.classList.contains("mj-seen")) {
    var started = Date.now();
    var whenReady = function () { setTimeout(finishLoading, Math.max(0, 1400 - (Date.now() - started))); };
    if (document.readyState === "complete") whenReady(); else window.addEventListener("load", whenReady);
    setTimeout(finishLoading, 3000);
  } else {
    if (loader) loader.remove();
    requestAnimationFrame(finishLoading);
  }

  /* ---------- Toast ---------- */
  var toast = document.createElement("div");
  toast.className = "toast";
  toast.setAttribute("role", "status");
  toast.setAttribute("aria-live", "polite");
  document.body.appendChild(toast);
  var toastTimer;
  function showToast(message) {
    toast.innerHTML = icon("check") + "<span>" + esc(message) + "</span>";
    toast.classList.add("is-visible");
    clearTimeout(toastTimer);
    toastTimer = setTimeout(function () { toast.classList.remove("is-visible"); }, 2600);
  }

  /* ---------- Scroll engine: header, progress, parallax and scroll-linked depth (one rAF loop) ---------- */
  var toTop = $("[data-to-top]");
  var progress = $("[data-progress]");
  var heroEl = $(".hero"), pageHero = $(".page-hero");
  var parallax = reduceMotion ? [] : $$("[data-parallax]");
  var floats = reduceMotion ? [] : $$("[data-float]");
  var expands = reduceMotion ? [] : $$("[data-expand]");
  var tickers = reduceMotion ? [] : $$(".ticker");
  var steps = $$("[data-steps]");
  var ticking = false, lastY = window.scrollY, headerHidden = false;
  function clamp(v, min, max) { return Math.max(min, Math.min(max, v)); }
  function onScroll() {
    var y = window.scrollY;
    var vh = window.innerHeight;
    html.classList.toggle("is-scrolled", y > 60);

    // Header tucks away on the way down, returns on the way up (never while a dialog or the mega menu is open).
    var dy = y - lastY;
    if (!reduceMotion && (Math.abs(dy) > 8 || y < 160)) {
      var hide = dy > 0 && y > vh * 0.7 && document.body.style.overflow !== "hidden" && !(megaItem && megaItem.matches(":hover"));
      if (y < 160) hide = false;
      if (hide !== headerHidden) { headerHidden = hide; html.classList.toggle("is-header-hidden", hide); }
      lastY = y;
    }
    var docMax = document.documentElement.scrollHeight - vh;
    if (progress) progress.style.setProperty("--sp", docMax > 0 ? (y / docMax).toFixed(4) : 0);

    if (!reduceMotion) {
      if (heroEl) heroEl.style.setProperty("--hero-p", clamp(y / heroEl.offsetHeight, 0, 1).toFixed(3));
      if (pageHero) pageHero.style.setProperty("--ph-p", clamp(y / pageHero.offsetHeight, 0, 1).toFixed(3));
      // Dark bands open from an inset card to full bleed as they arrive.
      expands.forEach(function (el) {
        var r = el.getBoundingClientRect();
        if (r.top > vh || r.bottom < 0) return;
        el.style.setProperty("--xp", clamp((vh - r.top) / (vh * 0.75), 0, 1).toFixed(3));
      });
      // Layered elements drift at their own speed for depth.
      floats.forEach(function (el) {
        var r = el.parentElement.getBoundingClientRect();
        if (r.bottom < -200 || r.top > vh + 200) return;
        var speed = parseFloat(el.getAttribute("data-float")) || 0.1;
        el.style.translate = "0 " + clamp((r.top + r.height / 2 - vh / 2) * -speed, -140, 140).toFixed(1) + "px";
      });
      // The word ticker picks up pace with the scroll.
      tickers.forEach(function (el) {
        var r = el.getBoundingClientRect();
        if (r.bottom < 0 || r.top > vh) return;
        el.style.setProperty("--tx", Math.min(0, (r.top - vh) * 0.5).toFixed(1));
      });
    }
    if (toTop) {
      var max = document.documentElement.scrollHeight - vh;
      toTop.classList.toggle("is-visible", y > vh * 0.8);
      toTop.style.setProperty("--p", max > 0 ? Math.min(1, y / max).toFixed(3) : 0);
    }
    parallax.forEach(function (el) {
      var r = el.parentElement.getBoundingClientRect();
      if (r.bottom < -100 || r.top > vh + 100) return;
      // The image is taller than its frame; drift it within that spare height.
      var speed = parseFloat(el.getAttribute("data-parallax")) || 0.12;
      var spare = Math.max(0, el.offsetHeight - r.height) / 2;
      var offset = Math.max(-spare, Math.min(spare, (r.top + r.height / 2 - vh / 2) * speed));
      el.style.translate = "0 " + (-spare - offset).toFixed(1) + "px";
    });
    steps.forEach(function (el) {
      var r = el.getBoundingClientRect();
      var p = Math.min(1, Math.max(0, (vh * 0.75 - r.top) / r.height));
      el.style.setProperty("--progress", p.toFixed(3));
    });
    var buy = $(".mobile-buy");
    if (buy) buy.classList.toggle("is-visible", y > 700);
    ticking = false;
  }
  window.addEventListener("scroll", function () {
    if (!ticking) { ticking = true; requestAnimationFrame(onScroll); }
  }, { passive: true });
  window.addEventListener("resize", onScroll);
  onScroll();
  if (toTop) toTop.addEventListener("click", function () { window.scrollTo({ top: 0, behavior: reduceMotion ? "auto" : "smooth" }); });

  /* ---------- Mega menu (hover on desktop, tap-to-open on touch, Esc to close) ---------- */
  var megaItem = $(".nav__item--mega");
  if (megaItem) {
    var megaLink = $(".nav__link", megaItem);
    var lastPointer = "mouse";
    var setMega = function (open) {
      megaLink.setAttribute("aria-expanded", String(open));
      if (!open) megaItem.classList.remove("is-open");
    };
    megaItem.addEventListener("mouseenter", function () { megaItem.classList.remove("is-closed"); setMega(true); });
    megaItem.addEventListener("mouseleave", function () { megaItem.classList.remove("is-closed"); setMega(false); });
    megaItem.addEventListener("focusin", function () { if (!megaItem.classList.contains("is-closed")) setMega(true); });
    megaItem.addEventListener("focusout", function (e) {
      if (!megaItem.contains(e.relatedTarget)) { megaItem.classList.remove("is-closed"); setMega(false); }
    });
    megaLink.addEventListener("pointerdown", function (e) { lastPointer = e.pointerType; });
    megaLink.addEventListener("click", function (e) {
      if (lastPointer !== "mouse" && !megaItem.classList.contains("is-open")) {
        e.preventDefault();
        megaItem.classList.add("is-open");
        setMega(true);
      }
    });
    megaItem.addEventListener("keydown", function (e) {
      if (e.key === "Escape") { megaItem.classList.add("is-closed"); setMega(false); megaLink.focus(); }
      if (e.key === "ArrowDown" && document.activeElement === megaLink) {
        e.preventDefault();
        megaItem.classList.remove("is-closed");
        setMega(true);
        var first = $(".mega a", megaItem);
        if (first) first.focus();
      }
    });
    document.addEventListener("pointerdown", function (e) { if (!megaItem.contains(e.target)) setMega(false); });
  }
  // A keyboard user tabbing into the header brings it back into view.
  var siteHeader = document.getElementById("site-header");
  if (siteHeader) siteHeader.addEventListener("focusin", function () { headerHidden = false; html.classList.remove("is-header-hidden"); });

  /* Mobile menu sub-list */
  $$("[data-menu-toggle]").forEach(function (btn) {
    btn.addEventListener("click", function () {
      var sub = document.getElementById(btn.getAttribute("aria-controls"));
      var open = btn.getAttribute("aria-expanded") !== "true";
      btn.setAttribute("aria-expanded", String(open));
      sub.hidden = !open;
    });
  });

  /* ---------- Dialogs, drawers & sheets ---------- */
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
    var first = $("[data-autofocus]", modal) || panel;
    first.focus({ preventScroll: true });
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
      if (!stack.length) { document.body.style.overflow = ""; document.body.style.paddingRight = ""; }
    };
    panel.addEventListener("animationend", function onEnd(e) { if (e.target === panel) { panel.removeEventListener("animationend", onEnd); finish(); } });
    setTimeout(finish, 450);
    if (restoreFocus !== false && entry.trigger && document.contains(entry.trigger)) entry.trigger.focus({ preventScroll: true });
  }

  document.addEventListener("click", function (e) {
    var wish = e.target.closest("[data-wish]");
    if (wish) {
      e.preventDefault();
      toggleWish(wish.getAttribute("data-wish"));
      return;
    }
    var remove = e.target.closest("[data-wish-remove]");
    if (remove) {
      toggleWish(remove.getAttribute("data-wish-remove"));
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

  /* Forms: each shows its own confirmation; nothing is sent (front-end template). */
  function successMarkup(title, text) {
    return '<div class="form-success"><span class="form-success__icon">' + icon("check") + "</span><h3>" + esc(title) + "</h3><p>" + esc(text) + "</p></div>";
  }
  $$("form[data-success]").forEach(function (form) {
    var holder = document.createElement("div");
    holder.hidden = true;
    form.after(holder);
    form.addEventListener("submit", function (e) {
      e.preventDefault();
      holder.innerHTML = successMarkup(form.getAttribute("data-success"), form.getAttribute("data-success-text"));
      form.hidden = true;
      holder.hidden = false;
    });
    var modal = form.closest("[data-modal]");
    if (modal) modal.addEventListener("mj:open", function () { form.reset(); form.hidden = false; holder.hidden = true; });
  });
  $$("form[data-close-on-submit]").forEach(function (form) {
    form.addEventListener("submit", function (e) {
      e.preventDefault();
      var modal = form.closest("[data-modal]");
      if (modal) closeModal(modal);
      form.reset();
    });
  });
  $$("form[data-newsletter]").forEach(function (form) {
    form.addEventListener("submit", function (e) {
      e.preventDefault();
      var note = $("[data-newsletter-note]", form.parentElement);
      if (note) { note.textContent = "Thank you — you are on the list for private notes from the house."; note.classList.add("is-success"); }
      form.reset();
    });
  });
  $$("input[type=date]").forEach(function (input) { input.min = new Date().toISOString().slice(0, 10); });

  /* ---------- Wishlist (saved in this browser) ---------- */
  var WISH_KEY = "mj-wishlist";
  var wishlist = [];
  try { wishlist = (JSON.parse(localStorage.getItem(WISH_KEY)) || []).filter(bySlug); } catch (e) { wishlist = []; }

  function toggleWish(slug) {
    var p = bySlug(slug);
    if (!p) return;
    var on = wishlist.indexOf(slug) === -1;
    wishlist = on ? wishlist.concat(slug) : wishlist.filter(function (s) { return s !== slug; });
    try { localStorage.setItem(WISH_KEY, JSON.stringify(wishlist)); } catch (e) { /* storage unavailable */ }
    syncWish();
    showToast(on ? p.name + " saved to your wishlist" : p.name + " removed from your wishlist");
  }

  function syncWish() {
    $$("[data-wish]").forEach(function (btn) {
      var slug = btn.getAttribute("data-wish");
      var p = bySlug(slug);
      var on = wishlist.indexOf(slug) !== -1;
      btn.setAttribute("aria-pressed", String(on));
      if (p && !btn.hasAttribute("data-wish-text")) btn.setAttribute("aria-label", (on ? "Remove " : "Save ") + p.name + (on ? " from" : " to") + " wishlist");
      var text = $("[data-wish-label]", btn);
      if (text) text.textContent = on ? "Saved to wishlist" : "Add to wishlist";
    });
    $$("[data-wish-count]").forEach(function (badge) {
      badge.textContent = wishlist.length;
      badge.hidden = !wishlist.length;
    });
    var list = $("[data-wish-list]");
    if (!list) return;
    $("[data-wish-empty]").hidden = wishlist.length > 0;
    $("[data-wish-foot]").hidden = !wishlist.length;
    list.hidden = !wishlist.length;
    list.innerHTML = wishlist.map(function (slug) {
      var p = bySlug(slug);
      var url = "product.html?slug=" + p.slug;
      return '<li class="drawer__item"><a href="' + url + '"><img src="' + p.image + '" alt="" style="object-position:' + p.imagePosition + '"></a>' +
        '<div><a class="drawer__name" href="' + url + '">' + esc(p.name) + '</a><p class="drawer__price">' + formatPrice(p.price) + "</p></div>" +
        '<button type="button" class="drawer__remove" data-wish-remove="' + p.slug + '" aria-label="Remove ' + esc(p.name) + ' from wishlist">' + icon("x") + "</button></li>";
    }).join("");
  }

  /* ---------- Search ---------- */
  var searchInput = document.getElementById("search-input");
  var searchResults = document.getElementById("search-results");
  function runSearch() {
    var words = searchInput.value.trim().toLowerCase().split(/\s+/).filter(Boolean);
    if (!words.length) { searchResults.hidden = true; searchResults.innerHTML = ""; return; }
    var results = products.filter(function (p) {
      var hay = (p.name + " " + p.category + " " + p.metal + " " + p.style + " " + p.collection + " " + p.purity).toLowerCase();
      return words.every(function (w) { return hay.indexOf(w) !== -1; });
    }).slice(0, 10);
    searchResults.hidden = false;
    searchResults.innerHTML = results.length
      ? results.map(function (p) {
        return '<a class="search__result" href="product.html?slug=' + p.slug + '">' +
          '<img src="' + p.image + '" alt="" style="object-position:' + p.imagePosition + '">' +
          '<span><span class="search__result-name">' + esc(p.name) + '</span><span class="search__result-meta">' + p.category + " · " + p.metal + "</span></span>" +
          '<span class="search__result-price">' + formatPrice(p.price) + "</span></a>";
      }).join("")
      : '<div class="search__empty"><p>No pieces found</p><p>Try another style — ring, jhumka, diamond or bridal.</p></div>';
  }
  if (searchInput && searchResults) {
    searchInput.addEventListener("input", runSearch);
    $$("[data-search-term]").forEach(function (chip) {
      chip.addEventListener("click", function () {
        searchInput.value = chip.getAttribute("data-search-term");
        runSearch();
        searchInput.focus();
      });
    });
  }

  /* ---------- Product card ---------- */
  var ALT_VIEWS = ["40% 38%", "62% 55%", "50% 30%", "45% 66%"];
  function wishButton(p, cls) {
    return '<button type="button" class="' + cls + '" data-wish="' + p.slug + '" aria-pressed="false" aria-label="Save ' + esc(p.name) + ' to wishlist">' + icon("heart") + "</button>";
  }
  function productCard(p, i) {
    var h = hash(p.slug);
    var url = "product.html?slug=" + p.slug;
    return '<article class="pcard' + (h % 2 ? " is-flip" : "") + '" data-reveal style="--d:' + ((i || 0) % 4 * 0.09).toFixed(2) + 's">' +
      '<div class="pcard__visual">' +
        '<a class="pcard__media" href="' + url + '" tabindex="-1" aria-hidden="true">' +
          '<img class="pcard__img" src="' + p.image + '" alt="" loading="lazy" width="800" height="800" style="object-position:' + p.imagePosition + '">' +
          '<img class="pcard__img pcard__img--alt" src="' + p.image + '" alt="" loading="lazy" width="800" height="800" style="object-position:' + ALT_VIEWS[h % ALT_VIEWS.length] + '">' +
          (p.isNew ? '<span class="pcard__badge">New</span>' : "") +
        "</a>" +
        wishButton(p, "pcard__wish") +
        '<div class="pcard__actions">' +
          '<a class="pcard__action" href="' + url + '">' + icon("eye") + "View piece</a>" +
          '<button type="button" class="pcard__action" data-open="appointment">' + icon("calendar") + "Try in store</button>" +
        "</div>" +
      "</div>" +
      '<div class="pcard__body">' +
        '<p class="pcard__meta">' + capitalize(p.category) + " · " + p.purity + " " + p.metal + "</p>" +
        '<h3 class="pcard__name"><a href="' + url + '">' + esc(p.name) + "</a></h3>" +
        '<p class="pcard__price">' + formatPrice(p.price) + "</p>" +
      "</div></article>";
  }
  function renderCards(el, list) {
    el.innerHTML = list.map(productCard).join("");
    syncWish();
    watch(el);
  }

  /* ---------- Scroll motion setup ---------- */
  // Photographs open with a curtain as they scroll in.
  var CURTAINS = [
    [".bento__item", "mask"], [".coll-card", "mask"], [".insta__item", "mask"], [".jcard__media", "mask"],
    [".arch-frame", "mask"], [".craft__main", "mask-left"], [".craft__small", "mask-down"],
  ];
  CURTAINS.forEach(function (pair) { $$(pair[0]).forEach(function (el) { el.setAttribute("data-reveal", pair[1]); }); });
  $$(".cat-arch").forEach(function (el) { el.setAttribute("data-reveal", "rise"); });

  // Headlines are split into words that rise in one after another.
  function splitWords(el) {
    if (el.classList.contains("split-text")) return;
    var n = 0;
    var wrap = function (node) {
      Array.prototype.slice.call(node.childNodes).forEach(function (child) {
        if (child.nodeType === 3) {
          var frag = document.createDocumentFragment();
          child.textContent.split(/(\s+)/).forEach(function (part) {
            if (!part) return;
            if (/^\s+$/.test(part)) { frag.appendChild(document.createTextNode(part)); return; }
            var w = document.createElement("span");
            w.className = "w";
            w.style.setProperty("--wi", n++);
            w.textContent = part;
            frag.appendChild(w);
          });
          child.replaceWith(frag);
        } else if (child.nodeType === 1 && child.tagName !== "BR") {
          // Keep gold-gradient words whole so the metallic fill still paints.
          if (child.classList.contains("text-metal")) {
            var unit = document.createElement("span");
            unit.className = "w";
            unit.style.setProperty("--wi", n++);
            child.replaceWith(unit);
            unit.appendChild(child);
          } else wrap(child);
        }
      });
    };
    wrap(el);
    el.classList.add("split-text");
  }
  var SPLIT = ".section-title, .page-hero__title, .heritage__title, .cta-final__title, .manifesto__text, .quote-band__text, .newsletter h2, .process__body h2";
  $$(SPLIT).forEach(splitWords);

  // Line icons trace their strokes when their card appears.
  $$(".trust__icon svg, .value__icon svg").forEach(function (svg) {
    svg.classList.add("draw");
    $$("path, circle, rect, line, polyline", svg).forEach(function (shape) { shape.setAttribute("pathLength", "1"); });
  });

  // Children of a [data-stagger] group follow one another.
  $$("[data-stagger]").forEach(function (group) {
    var step = parseFloat(group.getAttribute("data-stagger")) || 0.09;
    $$("[data-reveal]", group).forEach(function (el, i) { el.style.setProperty("--d", (i * step).toFixed(2) + "s"); });
  });
  function countUp(el) {
    var target = parseFloat(el.getAttribute("data-count"));
    var dur = 1800, start = null;
    var fmt = function (v) { return Math.round(v).toLocaleString("en-IN"); };
    var tick = function (t) {
      if (!start) start = t;
      var k = Math.min(1, (t - start) / dur);
      el.textContent = fmt(target * (1 - Math.pow(1 - k, 3)));
      if (k < 1) requestAnimationFrame(tick);
    };
    requestAnimationFrame(tick);
  }
  // A fully clipped element never counts as "on screen", so curtained images are watched through their parent.
  var proxies = new Map();
  var io = "IntersectionObserver" in window && new IntersectionObserver(function (entries) {
    entries.forEach(function (entry) {
      if (!entry.isIntersecting) return;
      if (entry.intersectionRatio < 0.12 && entry.intersectionRect.height < window.innerHeight * 0.2) return;
      var t = entry.target;
      if (t.hasAttribute("data-reveal") || t.classList.contains("split-text")) t.classList.add("is-visible");
      (proxies.get(t) || []).forEach(function (el) { el.classList.add("is-visible"); });
      proxies.delete(t);
      io.unobserve(t);
    });
  }, { threshold: [0, 0.06, 0.12, 0.25], rootMargin: "0px 0px -6% 0px" });
  function track(el) {
    if (!/^mask/.test(el.getAttribute("data-reveal") || "")) { io.observe(el); return; }
    var host = el.parentElement;
    if (!proxies.has(host)) proxies.set(host, []);
    proxies.get(host).push(el);
    io.observe(host);
  }
  var countIO = io && new IntersectionObserver(function (entries) {
    entries.forEach(function (entry) {
      if (!entry.isIntersecting) return;
      countUp(entry.target);
      countIO.unobserve(entry.target);
    });
  }, { threshold: 0.6 });

  // Start watching once the loader has lifted, so nothing plays behind it.
  function watch(root) {
    afterLoad(function () {
      $$("[data-reveal]:not(.is-visible), .split-text:not(.is-visible)", root).forEach(function (el) {
        if (io) track(el); else el.classList.add("is-visible");
      });
      $$("[data-count]", root).forEach(function (el) {
        if (el.hasAttribute("data-counted")) return;
        el.setAttribute("data-counted", "");
        if (countIO) countIO.observe(el); else countUp(el);
      });
    });
  }
  watch(document);

  /* ---------- Hotspots (tap to open on touch screens) ---------- */
  $$(".hotspot").forEach(function (spot) {
    spot.addEventListener("click", function (e) {
      if (e.target.closest("a")) return;
      var open = !spot.classList.contains("is-open");
      $$(".hotspot.is-open").forEach(function (s) { s.classList.remove("is-open"); s.setAttribute("aria-expanded", "false"); });
      spot.classList.toggle("is-open", open);
      spot.setAttribute("aria-expanded", String(open));
    });
    spot.addEventListener("keydown", function (e) {
      if (e.key === "Enter" || e.key === " ") { e.preventDefault(); spot.click(); }
    });
  });
  document.addEventListener("pointerdown", function (e) {
    if (!e.target.closest(".hotspot")) $$(".hotspot.is-open").forEach(function (s) { s.classList.remove("is-open"); s.setAttribute("aria-expanded", "false"); });
  });

  /* ---------- Home ---------- */
  if (page === "home") {
    /* Featured pieces with tabs */
    var featuredGrid = $("[data-featured]");
    var sets = {
      all: MJ.featuredSlugs.map(bySlug).filter(Boolean),
      gold: products.filter(function (p) { return p.metal === "Gold" && p.category !== "bridal"; }).slice(0, 8),
      diamond: products.filter(function (p) { return p.metal === "Diamond"; }).slice(0, 8),
      bridal: products.filter(function (p) { return p.category === "bridal"; }).slice(0, 8),
      new: products.filter(function (p) { return p.isNew; }).slice(0, 8),
    };
    if (featuredGrid) {
      renderCards(featuredGrid, sets.all);
      var tabs = $$("[data-tab]");
      var selectTab = function (tab, focus) {
        tabs.forEach(function (t) {
          var on = t === tab;
          t.setAttribute("aria-selected", String(on));
          t.tabIndex = on ? 0 : -1;
        });
        if (focus) tab.focus();
        featuredGrid.classList.add("is-swapping");
        setTimeout(function () {
          renderCards(featuredGrid, sets[tab.getAttribute("data-tab")] || sets.all);
          featuredGrid.classList.remove("is-swapping");
        }, reduceMotion ? 0 : 320);
      };
      tabs.forEach(function (tab, i) {
        tab.addEventListener("click", function () { if (tab.getAttribute("aria-selected") !== "true") selectTab(tab); });
        tab.addEventListener("keydown", function (e) {
          var dir = e.key === "ArrowRight" ? 1 : e.key === "ArrowLeft" ? -1 : 0;
          if (dir) { e.preventDefault(); selectTab(tabs[(i + dir + tabs.length) % tabs.length], true); }
        });
      });
    }

    /* Bridal panels: hover (desktop) or tap (touch) to expand */
    var panels = $$(".panel");
    var activate = function (panel) { panels.forEach(function (p) { p.classList.toggle("is-active", p === panel); }); };
    panels.forEach(function (panel) {
      panel.addEventListener("mouseenter", function () { if (canHover) activate(panel); });
      panel.addEventListener("focus", function () { activate(panel); });
      panel.addEventListener("click", function (e) {
        if (!panel.classList.contains("is-active")) { e.preventDefault(); activate(panel); }
      });
    });

    /* Loupe on the closer-look image */
    var stage = $("[data-loupe]");
    if (stage && canHover) {
      var lens = $(".loupe", stage);
      var img = $("img", stage);
      var Z = 2.6, R = 95;
      lens.style.backgroundImage = 'url("' + img.getAttribute("src") + '")';
      stage.addEventListener("mouseenter", function () { stage.classList.add("is-zooming"); });
      stage.addEventListener("mouseleave", function () { stage.classList.remove("is-zooming"); });
      stage.addEventListener("mousemove", function (e) {
        var r = stage.getBoundingClientRect();
        var x = e.clientX - r.left, y = e.clientY - r.top;
        lens.style.translate = x + "px " + y + "px";
        lens.style.backgroundSize = r.width * Z + "px " + r.height * Z + "px";
        lens.style.backgroundPosition = -(x * Z - R) + "px " + -(y * Z - R) + "px";
      });
    }
  }

  /* ---------- Testimonial slider ---------- */
  $$("[data-tslider]").forEach(function (slider) {
    var slides = $$(".tslide", slider);
    var dots = $$("[data-dot]", slider.parentElement);
    var index = 0, timer = null;
    var go = function (n) {
      index = (n + slides.length) % slides.length;
      slides.forEach(function (s, i) {
        s.classList.toggle("is-active", i === index);
        s.setAttribute("aria-hidden", String(i !== index));
      });
      dots.forEach(function (d, i) { d.setAttribute("aria-current", String(i === index)); });
    };
    var play = function () { if (!reduceMotion) { clearInterval(timer); timer = setInterval(function () { go(index + 1); }, 7000); } };
    var stop = function () { clearInterval(timer); };
    dots.forEach(function (d, i) { d.addEventListener("click", function () { go(i); play(); }); });
    $$("[data-prev]", slider.parentElement).forEach(function (b) { b.addEventListener("click", function () { go(index - 1); play(); }); });
    $$("[data-next]", slider.parentElement).forEach(function (b) { b.addEventListener("click", function () { go(index + 1); play(); }); });
    slider.addEventListener("mouseenter", stop);
    slider.addEventListener("mouseleave", play);
    slider.addEventListener("focusin", stop);
    go(0);
    play();
  });

  /* ---------- Custom select (sort menu) ---------- */
  function initSelect(root, onChange) {
    var trigger = $("[role=combobox]", root);
    var list = $("[role=listbox]", root);
    var options = $$("[role=option]", list);
    var open = false;
    var setOpen = function (value) {
      open = value;
      trigger.setAttribute("aria-expanded", String(value));
      list.hidden = !value;
      if (value) (options.filter(function (o) { return o.getAttribute("aria-selected") === "true"; })[0] || options[0]).focus();
    };
    var choose = function (opt) {
      options.forEach(function (o) {
        var on = o === opt;
        o.setAttribute("aria-selected", String(on));
        $("[data-indicator]", o).innerHTML = on ? icon("check") : "";
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
    var pool = products.filter(function (p) { return !category || p.category === category; });
    var metalParam = !category && params.get("metal");
    var styleParam = !category && params.get("style");
    var state = {
      metals: metalParam ? [capitalize(metalParam)] : [],
      styles: styleParam ? [capitalize(styleParam)] : [],
      price: "all",
      sort: "featured",
    };
    var PRICES = [["all", "All prices"], ["under", "Under ₹25,000"], ["mid", "₹25,000 – ₹50,000"], ["high", "Above ₹50,000"]];
    var inPrice = function (p, key) {
      return key === "all" || (key === "under" && p.price < 25000) || (key === "mid" && p.price >= 25000 && p.price <= 50000) || (key === "high" && p.price > 50000);
    };

    var group = function (title, body) {
      return '<div class="filter-group"><p class="filter-group__title">' + title + "</p>" + body + "</div>";
    };
    var checkRow = function (kind, value) {
      var count = pool.filter(function (p) { return p[kind] === value; }).length;
      if (!count) return "";
      return '<label class="check"><input type="checkbox" data-filter="' + kind + '" value="' + value + '"><span class="check__box">' + icon("check") + "</span>" + value + '<span class="check__count">' + count + "</span></label>";
    };
    var panelHTML = function (panel) {
      return group("Metal", ["Gold", "Diamond"].map(function (v) { return checkRow("metal", v); }).join("")) +
        group("Price", PRICES.map(function (pr) {
          return '<label class="check check--radio"><input type="radio" name="price-' + panel + '" value="' + pr[0] + '" data-price><span class="check__box"></span>' + pr[1] + "</label>";
        }).join("")) +
        group("Style", ["Classic", "Traditional", "Modern", "Bridal"].map(function (v) { return checkRow("style", v); }).join(""));
    };
    $$("[data-filter-panel]").forEach(function (el) { el.innerHTML = panelHTML(el.getAttribute("data-filter-panel")); });

    var filtered = function () {
      var list = pool.filter(function (p) {
        return (!state.metals.length || state.metals.indexOf(p.metal) !== -1)
          && (!state.styles.length || state.styles.indexOf(p.style) !== -1)
          && inPrice(p, state.price);
      });
      return list.slice().sort(function (a, b) {
        return state.sort === "low" ? a.price - b.price : state.sort === "high" ? b.price - a.price : state.sort === "new" ? Number(b.isNew) - Number(a.isNew) : 0;
      });
    };

    var results = $("[data-catalog-results]");
    var active = $("[data-catalog-active]");
    var renderCatalog = function () {
      $$("[data-filter]").forEach(function (box) {
        var list = box.getAttribute("data-filter") === "metal" ? state.metals : state.styles;
        box.checked = list.indexOf(box.value) !== -1;
      });
      $$("[data-price]").forEach(function (radio) { radio.checked = radio.value === state.price; });
      var shown = filtered();
      $$("[data-catalog-count]").forEach(function (el) { el.innerHTML = "<strong>" + shown.length + "</strong> " + (shown.length === 1 ? "piece" : "pieces"); });
      $$("[data-catalog-apply]").forEach(function (el) { el.textContent = "View " + shown.length + " pieces"; });

      var pills = state.metals.map(function (v) { return ["metal", v, v]; })
        .concat(state.styles.map(function (v) { return ["style", v, v]; }))
        .concat(state.price !== "all" ? [["price", state.price, PRICES.filter(function (pr) { return pr[0] === state.price; })[0][1]]] : []);
      active.innerHTML = pills.length
        ? pills.map(function (p) { return '<button type="button" class="pill" data-unfilter="' + p[0] + '" data-value="' + p[1] + '">' + esc(p[2]) + icon("x") + "</button>"; }).join("") +
          '<button type="button" class="pill pill--clear" data-clear-filters>Clear all</button>'
        : "";

      results.innerHTML = shown.length
        ? '<div class="product-grid product-grid--3">' + shown.map(productCard).join("") + "</div>"
        : '<div class="empty-state"><p class="empty-state__title">No pieces match these filters</p><p>Try widening your selection.</p>' +
          '<button type="button" class="btn btn--outline" data-clear-filters>Clear filters</button></div>';
      syncWish();
      watch(results);
    };

    document.addEventListener("change", function (e) {
      var box = e.target.closest("[data-filter]");
      if (box) {
        var key = box.getAttribute("data-filter") === "metal" ? "metals" : "styles";
        state[key] = box.checked ? state[key].concat(box.value) : state[key].filter(function (x) { return x !== box.value; });
        renderCatalog();
      }
      if (e.target.matches("[data-price]")) { state.price = e.target.value; renderCatalog(); }
    });
    document.addEventListener("click", function (e) {
      var pill = e.target.closest("[data-unfilter]");
      if (pill) {
        var kind = pill.getAttribute("data-unfilter"), value = pill.getAttribute("data-value");
        if (kind === "price") state.price = "all";
        else { var k = kind === "metal" ? "metals" : "styles"; state[k] = state[k].filter(function (x) { return x !== value; }); }
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

    var sortSelect = $("[data-select]");
    if (sortSelect) initSelect(sortSelect, function (value) { state.sort = value; renderCatalog(); });
    renderCatalog();
  }

  /* ---------- Product detail ---------- */
  if (page === "product") {
    var productRoot = document.getElementById("product-root");
    var product = bySlug(params.get("slug") || MJ.featuredSlugs[0]);

    if (!product) {
      productRoot.innerHTML = '<div class="container empty-state"><p class="empty-state__title">Piece not found</p><p>This jewel may have found its home already.</p>' +
        '<a class="btn btn--primary" href="jewellery.html">View all jewellery ' + icon("arrow-right") + "</a></div>";
    } else {
      document.title = product.name + " — Mangalam Jewellers";
      var views = [
        { pos: product.imagePosition, scale: 1 },
        { pos: "35% 35%", scale: 1.45 },
        { pos: "62% 62%", scale: 1.8 },
      ];
      var related = products.filter(function (p) { return p.category === product.category && p.slug !== product.slug; }).slice(0, 4);
      var name = esc(product.name);
      var stone = product.metal === "Diamond" ? "Diamond" : "Gold work";

      productRoot.innerHTML =
        '<div class="container">' +
          '<nav class="breadcrumb breadcrumb--dark" aria-label="Breadcrumb"><a href="index.html">Home</a><span class="breadcrumb__sep"></span><a href="jewellery.html">Jewellery</a><span class="breadcrumb__sep"></span><a href="' + product.category + '.html">' + capitalize(product.category) + '</a><span class="breadcrumb__sep"></span><span aria-current="page">' + name + "</span></nav>" +
          '<div class="product__grid">' +
            '<div class="gallery">' +
              '<div class="gallery__thumbs">' + views.map(function (v, i) {
                return '<button type="button" class="gallery__thumb" data-view="' + i + '" aria-label="View image ' + (i + 1) + '" aria-current="' + (i === 0) + '"><img src="' + product.image + '" alt="" style="object-position:' + v.pos + ";scale:" + v.scale + '"></button>';
              }).join("") + "</div>" +
              '<div class="gallery__main" data-zoom>' +
                '<img data-main-image src="' + product.image + '" alt="' + name + '" style="object-position:' + views[0].pos + '">' +
                wishButton(product, "pcard__wish") +
                (canHover ? '<span class="gallery__hint">' + icon("zoom-in") + "Hover to zoom</span>" : "") +
              "</div>" +
            "</div>" +
            '<div class="pinfo">' +
              '<p class="pinfo__collection">' + esc(product.collection) + "</p>" +
              '<h1 class="pinfo__name">' + name + "</h1>" +
              '<p class="pinfo__price">' + formatPrice(product.price) + "<span>" + product.purity + " · " + esc(product.metal) + "</span></p>" +
              '<p class="pinfo__desc">' + esc(product.description) + "</p>" +
              '<dl class="specs"><div><dt>Gold purity</dt><dd>' + product.purity + "</dd></div><div><dt>Stone</dt><dd>" + stone + "</dd></div><div><dt>Category</dt><dd>" + product.category + "</dd></div></dl>" +
              '<div class="pinfo__actions">' +
                '<button type="button" class="btn btn--primary" data-open="enquire">' + icon("message-circle") + " Enquire now</button>" +
                '<button type="button" class="btn btn--outline" data-wish="' + product.slug + '" data-wish-text aria-pressed="false">' + icon("heart") + ' <span data-wish-label>Add to wishlist</span></button>' +
              "</div>" +
              '<p class="pinfo__try">' + icon("calendar") + ' Prefer to see it in person? <button type="button" data-open="appointment">Book a private viewing</button></p>' +
              '<ul class="assure"><li>' + icon("shield-check") + "BIS hallmarked</li><li>" + icon("sparkles") + "Handcrafted in Surat</li><li>" + icon("truck") + "Insured delivery</li></ul>" +
              '<div class="accordion">' +
                '<details open><summary>Product details' + icon("plus") + '</summary><div class="accordion__body">' + name + " is part of " + esc(product.collection) + ". Crafted in " + product.purity + " " + (product.metal === "Diamond" ? "gold with diamonds" : "gold") + " and handcrafted to order in our Surat atelier by master karigars. Every piece carries a BIS hallmark — your assurance of its purity.</div></details>" +
                '<details><summary>Caring for your jewel' + icon("plus") + '</summary><div class="accordion__body">Keep gold away from perfume and chlorine, wipe it gently with a soft cloth after wear, and store each piece separately. Bring it home to us once a year and our karigars will clean, inspect and re-polish it.</div></details>' +
                '<details><summary>Delivery &amp; appointments' + icon("plus") + '</summary><div class="accordion__body">Insured delivery across India. Prefer to see it first? Book a private appointment at Mangalam House, Ring Road, Surat — our concierge will confirm your time.</div></details>' +
              "</div>" +
            "</div>" +
          "</div>" +
          (related.length
            ? '<section class="related"><header class="section-head section-head--split"><div><p class="eyebrow">Complete the look</p><h2 class="section-title">You may also <em>love</em></h2></div>' +
              '<a class="link-arrow" href="' + product.category + '.html">More ' + product.category + " " + icon("arrow-right") + "</a></header>" +
              '<div class="product-grid" data-related></div></section>'
            : "") +
        "</div>" +
        '<div class="mobile-buy"><div><strong>' + formatPrice(product.price) + "</strong></div>" +
          '<button type="button" class="btn btn--primary btn--sm" data-open="enquire">' + icon("message-circle") + " Enquire</button></div>";
      document.body.classList.add("has-mobile-buy");

      $$(SPLIT, productRoot).forEach(splitWords);
      watch(productRoot);
      var relatedGrid = $("[data-related]", productRoot);
      if (relatedGrid) renderCards(relatedGrid, related);

      var mainImage = $("[data-main-image]", productRoot);
      var thumbs = $$("[data-view]", productRoot);
      thumbs.forEach(function (thumb, i) {
        thumb.addEventListener("click", function () {
          mainImage.style.opacity = "0";
          setTimeout(function () {
            mainImage.style.objectPosition = views[i].pos;
            mainImage.style.scale = views[i].scale;
            mainImage.style.opacity = "1";
          }, 180);
          thumbs.forEach(function (t, j) { t.setAttribute("aria-current", String(j === i)); });
        });
      });

      var zoom = $("[data-zoom]", productRoot);
      if (canHover) {
        zoom.addEventListener("mouseenter", function () { zoom.classList.add("is-zoom"); });
        zoom.addEventListener("mouseleave", function () { zoom.classList.remove("is-zoom"); });
        zoom.addEventListener("mousemove", function (e) {
          var r = zoom.getBoundingClientRect();
          mainImage.style.setProperty("--zx", ((e.clientX - r.left) / r.width * 100).toFixed(1) + "%");
          mainImage.style.setProperty("--zy", ((e.clientY - r.top) / r.height * 100).toFixed(1) + "%");
        });
      }

      var enquire = $('[data-modal="enquire"]');
      if (enquire) {
        $("[data-product-name]", enquire).textContent = product.name;
        var message = $("textarea", enquire);
        message.defaultValue = "I would like to know more about the " + product.name + ".";
      }
    }
  }

  /* ---------- Journal filters ---------- */
  var journalFilters = $$("[data-journal-filter]");
  if (journalFilters.length) {
    var cards = $$("[data-journal-grid] .jcard");
    journalFilters.forEach(function (btn) {
      btn.addEventListener("click", function () {
        var cat = btn.getAttribute("data-journal-filter");
        journalFilters.forEach(function (b) { b.classList.toggle("is-active", b === btn); b.setAttribute("aria-pressed", String(b === btn)); });
        cards.forEach(function (card) {
          var show = cat === "all" || card.getAttribute("data-cat") === cat;
          card.hidden = !show;
          if (show) { card.style.animation = "none"; void card.offsetWidth; card.style.animation = "cardIn .7s var(--ease-out) both"; }
        });
      });
    });
  }

  /* ---------- Journal article ---------- */
  if (page === "article") {
    var articleRoot = document.getElementById("article-root");
    var article = articles.filter(function (a) { return a.slug === (params.get("slug") || articles[0].slug); })[0];

    if (!article) {
      articleRoot.innerHTML = '<section class="page-hero"><div class="container"><h1 class="page-hero__title">Article not found</h1>' +
        '<p class="page-hero__lead">The story you are looking for may have moved.</p>' +
        '<p style="margin-top:32px"><a class="btn btn--light" href="journal.html">All articles ' + icon("arrow-right") + "</a></p></div></section>";
    } else {
      document.title = article.title + " — Mangalam Journal";
      var more = articles.filter(function (a) { return a.slug !== article.slug; }).slice(0, 3);
      var shareUrl = encodeURIComponent(window.location.href);
      articleRoot.innerHTML =
        '<header class="page-hero page-hero--tall article-hero">' +
          '<img class="page-hero__bg" src="' + article.image + '" alt="" style="object-position:' + article.imagePosition + '">' +
          '<div class="container">' +
            '<nav class="breadcrumb" aria-label="Breadcrumb"><a href="index.html">Home</a><span class="breadcrumb__sep"></span><a href="journal.html">Journal</a><span class="breadcrumb__sep"></span><span aria-current="page">' + esc(article.category) + "</span></nav>" +
            '<h1 class="page-hero__title">' + esc(article.title) + "</h1>" +
            '<p class="page-hero__lead">' + esc(article.excerpt) + "</p>" +
            '<p class="breadcrumb" style="margin-top:26px">' + esc(article.date) + '<span class="breadcrumb__sep"></span>' + esc(article.readTime) + "</p>" +
          "</div>" +
        "</header>" +
        '<div class="container article-cover" data-reveal="zoom"><div class="article-cover__img"><img src="' + article.image + '" alt="' + esc(article.title) + '" style="object-position:' + article.imagePosition + '"></div></div>' +
        '<div class="container container--narrow article-body">' +
          '<div class="prose">' + article.body.map(function (para, i) { return "<p" + (i ? "" : ' class="dropcap"') + ">" + esc(para) + "</p>"; }).join("") + "</div>" +
          '<div class="article-share">' +
            '<a class="link-arrow" href="journal.html">' + icon("arrow-left") + " All articles</a>" +
            '<div class="article-share__links">' +
              '<a href="https://www.facebook.com/sharer/sharer.php?u=' + shareUrl + '" target="_blank" rel="noopener" aria-label="Share on Facebook">' + icon("facebook") + "</a>" +
              '<a href="mailto:?subject=' + encodeURIComponent(article.title) + "&body=" + shareUrl + '" aria-label="Share by email">' + icon("mail") + "</a>" +
              '<button type="button" data-copy-link aria-label="Copy link">' + icon("link") + "</button>" +
            "</div>" +
          "</div>" +
        "</div>" +
        '<section class="section section--pearl"><div class="container">' +
          '<header class="section-head"><p class="eyebrow eyebrow--center">Keep reading</p><h2 class="section-title">More from the <em>journal</em></h2></header>' +
          '<div class="journal-grid">' + more.map(function (a) {
            return '<a class="jcard" href="article.html?slug=' + a.slug + '"><div class="jcard__media"><img src="' + a.image + '" alt="" loading="lazy" style="object-position:' + a.imagePosition + '"><span class="jcard__cat">' + esc(a.category) + "</span></div>" +
              '<p class="jcard__meta">' + esc(a.date) + " · " + esc(a.readTime) + '</p><h3 class="jcard__title">' + esc(a.title) + "</h3></a>";
          }).join("") + "</div>" +
        "</div></section>";

      var copy = $("[data-copy-link]", articleRoot);
      copy.addEventListener("click", function () {
        if (navigator.clipboard) navigator.clipboard.writeText(window.location.href).then(function () { showToast("Link copied"); });
      });
      // This content arrived after the motion setup ran, so wire it up here.
      pageHero = $(".page-hero", articleRoot);
      $$(".jcard__media", articleRoot).forEach(function (el) { el.setAttribute("data-reveal", "mask"); });
      $$(SPLIT, articleRoot).forEach(splitWords);
      watch(articleRoot);
    }
  }

  /* ---------- Footer year ---------- */
  $$("[data-year]").forEach(function (el) { el.textContent = new Date().getFullYear(); });

  syncWish();
})();
