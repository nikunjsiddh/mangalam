/* Mangalam Jewellers — admin panel behaviour.
 *
 * The admin is a design for now: every control works so the screens can be reviewed, but nothing is
 * saved. Anything that would change data shows a toast saying what would happen; once a backend is
 * connected, those toasts are where its requests go.
 *
 * Sidebar, menus, tooltips, dialogs and drawers (filled in from the button that opens them),
 * confirmations, toasts, tabs, list search / filters / sorting / pages, row selection, drag to
 * reorder, photo previews, the text editor, live previews, charts, and the enquiries, media,
 * homepage, offer, settings and sign-in screens.
 */
(function () {
  "use strict";

  var $ = function (sel, root) { return (root || document).querySelector(sel); };
  var $$ = function (sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); };
  var clamp = function (v, min, max) { return Math.min(Math.max(v, min), max); };
  var root = document.documentElement;
  var page = document.body.getAttribute("data-page");
  var params = new URLSearchParams(location.search);
  var wide = window.matchMedia("(min-width: 1100px)");
  var MJ = window.MJ;
  var iconHTML = function (name) { return '<svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><use href="#i-' + name + '"/></svg>'; };

  /* ---------- Toasts ---------- */
  var toastBox = $("[data-toasts]"), toastTemplate = $("#toast-template");
  function toast(title, text) {
    if (!toastBox || !toastTemplate) return;
    var el = toastTemplate.content.firstElementChild.cloneNode(true);
    $(".toast__title", el).textContent = title;
    $(".toast__text", el).textContent = text || "Design preview — nothing was saved.";
    toastBox.appendChild(el);
    var timer = setTimeout(dismiss, 4800);
    function dismiss() {
      clearTimeout(timer);
      el.classList.add("is-leaving");
      setTimeout(function () { el.remove(); }, 260);
    }
    $(".toast__close", el).addEventListener("click", dismiss);
    while (toastBox.children.length > 3) toastBox.firstElementChild.remove();
  }

  document.addEventListener("click", function (e) {
    var el = e.target.closest("[data-toast]");
    if (!el || el.tagName === "FORM" || el.type === "submit") return;
    if (el.tagName === "A") e.preventDefault();
    toast(el.getAttribute("data-toast"), el.getAttribute("data-toast-text"));
  });
  document.addEventListener("change", function (e) {
    var el = e.target.closest("[data-change-toast]");
    if (el) toast(el.getAttribute("data-change-toast"), el.getAttribute("data-change-toast-text"));
  });

  /* ---------- Sidebar ---------- */
  var scrim = $("[data-sidebar-close]");
  function setSideOpen(open) {
    root.classList.toggle("side-open", open);
    if (scrim) scrim.hidden = !open;
  }
  $$("[data-sidebar-toggle]").forEach(function (btn) {
    btn.addEventListener("click", function () {
      if (wide.matches) {
        var mini = root.classList.toggle("side-mini");
        try { localStorage.setItem("mj-admin-sidebar", mini ? "mini" : "full"); } catch (err) { /* storage unavailable */ }
      } else {
        setSideOpen(!root.classList.contains("side-open"));
      }
    });
  });
  if (scrim) scrim.addEventListener("click", function () { setSideOpen(false); });
  wide.addEventListener("change", function () { setSideOpen(false); });

  /* ---------- Menus (placed beside their button, so no scrolling box can clip them) ---------- */
  var openMenu = null;
  function closeMenu(returnFocus) {
    if (!openMenu) return;
    var current = openMenu;
    openMenu = null;
    current.menu.hidden = true;
    current.trigger.setAttribute("aria-expanded", "false");
    if (returnFocus) current.trigger.focus();
  }
  function placeMenu(trigger, menu) {
    var r = trigger.getBoundingClientRect();
    var w = menu.offsetWidth, h = menu.offsetHeight;
    var top = r.bottom + 6;
    if (top + h > window.innerHeight - 8 && r.top - h - 6 > 8) top = r.top - h - 6;
    menu.style.left = clamp(r.right - w, 8, window.innerWidth - w - 8) + "px";
    menu.style.top = top + "px";
  }
  document.addEventListener("click", function (e) {
    var trigger = e.target.closest("[data-menu-trigger]");
    if (trigger) {
      var menu = trigger.parentElement.querySelector("[data-menu]");
      var same = openMenu && openMenu.menu === menu;
      closeMenu();
      if (!same && menu) {
        menu.hidden = false;
        placeMenu(trigger, menu);
        trigger.setAttribute("aria-expanded", "true");
        openMenu = { trigger: trigger, menu: menu };
      }
      return;
    }
    if (openMenu && (!openMenu.menu.contains(e.target) || e.target.closest("[role=menuitem]"))) closeMenu();
  });
  document.addEventListener("keydown", function (e) {
    if (!openMenu) return;
    var items = $$("[role=menuitem], a, button", openMenu.menu);
    var index = items.indexOf(document.activeElement);
    if (e.key === "ArrowDown" || e.key === "ArrowUp") {
      e.preventDefault();
      var step = e.key === "ArrowDown" ? 1 : -1;
      items[(index + step + items.length) % items.length].focus();
    }
  });
  window.addEventListener("resize", function () { closeMenu(); });
  document.addEventListener("scroll", function (e) { if (openMenu && !openMenu.menu.contains(e.target)) closeMenu(); }, true);

  /* ---------- Tooltips ---------- */
  var tip = document.createElement("div");
  tip.className = "tooltip";
  tip.setAttribute("role", "tooltip");
  document.body.appendChild(tip);
  var tipFor = null;
  function showTip(el, html) {
    var isSideLink = el.classList.contains("side__link");
    if (isSideLink && !(wide.matches && root.classList.contains("side-mini"))) return;
    tipFor = el;
    if (html) { tip.textContent = ""; tip.appendChild(html); } else tip.textContent = el.getAttribute("data-tip");
    tip.classList.add("is-shown");
    var r = el.getBoundingClientRect(), w = tip.offsetWidth, h = tip.offsetHeight;
    var side = isSideLink ? "right" : el.getAttribute("data-tip-pos") || "top";
    var x = r.left + r.width / 2 - w / 2, y = r.top - h - 8;
    if (side === "right") { x = r.right + 10; y = r.top + r.height / 2 - h / 2; }
    if (side === "bottom" || y < 8) y = r.bottom + 8;
    tip.style.left = clamp(x, 8, window.innerWidth - w - 8) + "px";
    tip.style.top = clamp(y, 8, window.innerHeight - h - 8) + "px";
  }
  function hideTip() { tipFor = null; tip.classList.remove("is-shown"); }
  document.addEventListener("pointerover", function (e) {
    var el = e.target.closest("[data-tip]");
    if (el && el !== tipFor) showTip(el);
    else if (!el && tipFor && !tipFor.hasAttribute("data-chart-tip")) hideTip();
  });
  document.addEventListener("focusin", function (e) { var el = e.target.closest("[data-tip]"); if (el) showTip(el); });
  document.addEventListener("focusout", hideTip);
  document.addEventListener("scroll", hideTip, true);

  /* ---------- Dialogs and drawers ---------- */
  function setField(form, key, value) {
    var field = form.elements.namedItem(key);
    if (!field) return;
    if (typeof RadioNodeList !== "undefined" && field instanceof RadioNodeList) {
      Array.prototype.forEach.call(field, function (r) { r.checked = r.value === String(value); });
    } else if (field.type === "checkbox") field.checked = Boolean(value);
    else field.value = value;
  }
  function refreshFields(form) {
    $$("[data-slug-target]", form).forEach(function (t) { t.toggleAttribute("data-touched", Boolean(t.value)); });
    $$("input, textarea, select", form).forEach(function (f) { f.dispatchEvent(new Event("input", { bubbles: true })); });
  }
  function fillDialog(dialog, data) {
    var form = $("form", dialog);
    if (form) form.reset();
    $$(".field.is-invalid", dialog).forEach(function (f) { f.classList.remove("is-invalid"); });
    $$("[data-fill-text]", dialog).forEach(function (el) {
      var v = data[el.getAttribute("data-fill-text")];
      if (v !== undefined) el.textContent = v;
    });
    $$("[data-fill-src]", dialog).forEach(function (img) {
      var v = data[img.getAttribute("data-fill-src")];
      if (v) { img.src = v; img.hidden = false; } else { img.removeAttribute("src"); img.hidden = true; }
    });
    if (!form) return;
    Object.keys(data).forEach(function (key) { setField(form, key, data[key]); });
    refreshFields(form);
  }
  document.addEventListener("click", function (e) {
    var opener = e.target.closest("[data-dialog-open]");
    if (opener) {
      var dialog = document.getElementById(opener.getAttribute("data-dialog-open"));
      if (!dialog) return;
      var data = opener.getAttribute("data-fill");
      if (data) fillDialog(dialog, JSON.parse(data));
      else { var form = $("form", dialog); if (form) { form.reset(); refreshFields(form); } }
      dialog.showModal();
      return;
    }
    if (e.target.closest("[data-dialog-close]")) {
      var host = e.target.closest("dialog");
      if (host) host.close();
    }
  });
  $$("dialog").forEach(function (dialog) {
    // A click on the dimmed backdrop closes it (only when the press also started there)
    var pressedOnBackdrop = false;
    dialog.addEventListener("pointerdown", function (e) { pressedOnBackdrop = e.target === dialog; });
    dialog.addEventListener("click", function (e) { if (e.target === dialog && pressedOnBackdrop) dialog.close(); });
  });

  /* ---------- Confirmations ---------- */
  var confirmDialog = $("#confirm-dialog"), confirmFrom = null;
  document.addEventListener("click", function (e) {
    var trigger = e.target.closest("[data-confirm]");
    if (!trigger || !confirmDialog) return;
    confirmFrom = trigger;
    $("[data-confirm-title]", confirmDialog).textContent = trigger.getAttribute("data-confirm");
    $("[data-confirm-text]", confirmDialog).textContent = trigger.getAttribute("data-confirm-text") || "This can't be undone.";
    $("[data-confirm-ok]", confirmDialog).textContent = trigger.getAttribute("data-confirm-action") || "Delete";
    confirmDialog.returnValue = "";
    confirmDialog.showModal();
  });
  if (confirmDialog) confirmDialog.addEventListener("close", function () {
    var trigger = confirmFrom;
    confirmFrom = null;
    if (!trigger || confirmDialog.returnValue !== "ok") return;
    var host = trigger.closest("dialog");
    if (host && host.open) host.close();
    trigger.dispatchEvent(new CustomEvent("admin:confirmed", { bubbles: true }));
    toast(trigger.getAttribute("data-done") || "Done");
  });

  /* ---------- Forms: check what is required, then show what saving would do ---------- */
  $$("form[data-demo]").forEach(function (form) {
    form.addEventListener("submit", function (e) {
      e.preventDefault();
      var missing = $$("[required]", form).filter(function (f) {
        if (f.disabled || !f.getClientRects().length) return false;
        return f.type === "checkbox" ? !f.checked : !f.value.trim();
      });
      if (missing.length) {
        missing.forEach(function (f) { var field = f.closest(".field, .check-row"); if (field) field.classList.add("is-invalid"); });
        missing[0].focus();
        toast("Please fill in the highlighted fields", "They are needed before this can be saved.");
        return;
      }
      var dialog = form.closest("dialog");
      if (dialog) dialog.close();
      if (form.classList.contains("composer")) form.reset();
      var bar = $("[data-savebar]", form);
      if (bar) bar.hidden = true;
      toast(form.getAttribute("data-toast") || "Saved", form.getAttribute("data-toast-text"));
    });
  });
  document.addEventListener("input", function (e) {
    var field = e.target.closest && e.target.closest(".is-invalid");
    if (field && e.isTrusted) field.classList.remove("is-invalid");
  });

  /* ---------- Tabs ---------- */
  $$("[role=tablist]").forEach(function (list) {
    var tabs = $$("[role=tab]", list);
    function select(tab, focus) {
      tabs.forEach(function (t) {
        var on = t === tab;
        t.setAttribute("aria-selected", String(on));
        t.tabIndex = on ? 0 : -1;
        var panel = document.getElementById(t.getAttribute("aria-controls"));
        if (panel) panel.hidden = !on;
      });
      if (focus) tab.focus();
    }
    tabs.forEach(function (tab, i) {
      tab.addEventListener("click", function () { select(tab); });
      tab.addEventListener("keydown", function (e) {
        var step = { ArrowRight: 1, ArrowDown: 1, ArrowLeft: -1, ArrowUp: -1 }[e.key];
        if (!step) return;
        e.preventDefault();
        select(tabs[(i + step + tabs.length) % tabs.length], true);
      });
    });
  });

  /* ---------- Lists: search, filters, sorting and pages ---------- */
  $$("[data-list]").forEach(function (list) {
    var scope = list.closest("[data-list-scope]") || document;
    var items = $$("[data-item]", list);
    var perPage = Number(list.getAttribute("data-per-page")) || 0;
    var pageNo = 1;
    var search = $("[data-list-search]", scope);
    var filters = $$("[data-list-filter]", scope);
    var chips = $$("[data-list-chip]", scope);
    var sort = $("[data-list-sort]", scope);
    var pager = $("[data-pager]", scope);
    var empty = $("[data-list-empty]", scope);
    var chipState = {};

    function setChip(chip) {
      var key = chip.getAttribute("data-list-chip");
      chips.forEach(function (c) {
        if (c.getAttribute("data-list-chip") !== key) return;
        var on = c === chip;
        c.classList.toggle("is-active", on);
        c.setAttribute("aria-pressed", String(on));
      });
      chipState[key] = chip.getAttribute("data-value");
    }
    function matches(item) {
      var q = search ? search.value.trim().toLowerCase() : "";
      if (q && (item.getAttribute("data-search") || item.textContent.toLowerCase()).indexOf(q) === -1) return false;
      for (var i = 0; i < filters.length; i++) {
        var want = filters[i].value;
        if (want && item.getAttribute("data-" + filters[i].getAttribute("data-list-filter")) !== want) return false;
      }
      for (var key in chipState) {
        if (chipState[key] && (" " + (item.getAttribute("data-" + key) || "") + " ").indexOf(" " + chipState[key] + " ") === -1) return false;
      }
      return true;
    }
    function renderPager(total, pages) {
      if (!pager) return;
      if (!perPage || !total) { pager.innerHTML = ""; return; }
      var html = "<span>Showing <strong>" + ((pageNo - 1) * perPage + 1) + "–" + Math.min(total, pageNo * perPage) + "</strong> of " + total + '</span><div class="pager__pages">' +
        '<button type="button" class="pager__btn" data-page="' + (pageNo - 1) + '"' + (pageNo === 1 ? " disabled" : "") + ' aria-label="Previous page">' + iconHTML("chevron-left") + "</button>";
      for (var p = 1; p <= pages; p++) html += '<button type="button" class="pager__btn" data-page="' + p + '"' + (p === pageNo ? ' aria-current="page"' : "") + ">" + p + "</button>";
      pager.innerHTML = html + '<button type="button" class="pager__btn" data-page="' + (pageNo + 1) + '"' + (pageNo === pages ? " disabled" : "") + ' aria-label="Next page">' + iconHTML("chevron-right") + "</button></div>";
    }
    function apply() {
      var shown = items.filter(matches);
      if (sort && sort.value) {
        var parts = sort.value.split(":"), key = "data-sort-" + parts[0], dir = parts[1] === "desc" ? -1 : 1;
        shown.sort(function (a, b) {
          var x = a.getAttribute(key), y = b.getAttribute(key);
          return (isNaN(x) || isNaN(y) ? x.localeCompare(y) : x - y) * dir;
        });
      }
      shown.concat(items.filter(function (item) { return shown.indexOf(item) === -1; })).forEach(function (item) { list.appendChild(item); });
      var pages = perPage ? Math.max(1, Math.ceil(shown.length / perPage)) : 1;
      pageNo = clamp(pageNo, 1, pages);
      items.forEach(function (item) { item.hidden = true; });
      shown.forEach(function (item, i) { item.hidden = perPage ? i < (pageNo - 1) * perPage || i >= pageNo * perPage : false; });
      if (empty) empty.hidden = shown.length > 0;
      renderPager(shown.length, pages);
      list.dispatchEvent(new CustomEvent("admin:listchange", { bubbles: true }));
    }

    chips.forEach(function (chip) { if (chip.classList.contains("is-active")) setChip(chip); });
    // Filters can come from the address: products.html?category=rings, enquiries.html?status=new …
    params.forEach(function (value, key) {
      if (key === "search" && search) search.value = value;
      filters.forEach(function (f) { if (f.getAttribute("data-list-filter") === key) f.value = value; });
      chips.forEach(function (c) { if (c.getAttribute("data-list-chip") === key && c.getAttribute("data-value") === value) setChip(c); });
    });

    if (search) search.addEventListener("input", function () { pageNo = 1; apply(); });
    filters.forEach(function (f) { f.addEventListener("change", function () { pageNo = 1; apply(); }); });
    chips.forEach(function (chip) { chip.addEventListener("click", function () { setChip(chip); pageNo = 1; apply(); }); });
    if (sort) sort.addEventListener("change", apply);
    if (pager) pager.addEventListener("click", function (e) {
      var btn = e.target.closest("[data-page]");
      if (!btn || btn.disabled) return;
      pageNo = Number(btn.getAttribute("data-page"));
      apply();
      var box = list.closest(".card") || list;
      if (box.getBoundingClientRect().top < 0) window.scrollTo({ top: box.getBoundingClientRect().top + window.scrollY - 90, behavior: "smooth" });
    });
    $$("[data-list-reset]", scope).forEach(function (btn) {
      btn.addEventListener("click", function () {
        if (search) search.value = "";
        filters.forEach(function (f) { f.value = ""; });
        chips.forEach(function (c) { if (!c.getAttribute("data-value")) setChip(c); });
        pageNo = 1;
        apply();
      });
    });
    apply();
  });

  /* ---------- Row selection and the bulk bar ---------- */
  $$("[data-check-all]").forEach(function (all) {
    var table = all.closest("table");
    var scope = all.closest("[data-list-scope]") || table.parentElement;
    var bar = $("[data-bulkbar]", scope), count = bar && $("[data-bulk-count]", bar);
    var boxes = function () { return $$("[data-check-row]", table); };
    var visible = function () { return boxes().filter(function (c) { return !c.closest("tr").hidden; }); };
    function sync() {
      var shown = visible(), picked = boxes().filter(function (c) { return c.checked; });
      boxes().forEach(function (c) { c.closest("tr").classList.toggle("is-selected", c.checked); });
      all.checked = shown.length > 0 && shown.every(function (c) { return c.checked; });
      all.indeterminate = !all.checked && shown.some(function (c) { return c.checked; });
      if (bar) { bar.hidden = !picked.length; count.textContent = picked.length; }
    }
    all.addEventListener("change", function () { visible().forEach(function (c) { c.checked = all.checked; }); sync(); });
    table.addEventListener("change", function (e) { if (e.target.matches("[data-check-row]")) sync(); });
    table.addEventListener("admin:listchange", sync);
    if (bar) $("[data-bulk-clear]", bar).addEventListener("click", function () { boxes().forEach(function (c) { c.checked = false; }); sync(); });
  });

  /* ---------- Web addresses, character counts and live previews ---------- */
  var slugify = function (v) { return v.toLowerCase().normalize("NFKD").replace(/[̀-ͯ]/g, "").replace(/[^a-z0-9]+/g, "-").replace(/(^-|-$)/g, ""); };
  $$("[data-slug-source]").forEach(function (source) {
    var target = $("[data-slug-target]", source.form || document);
    if (!target) return;
    target.toggleAttribute("data-touched", Boolean(target.value));
    target.addEventListener("input", function (e) { if (e.isTrusted) target.setAttribute("data-touched", ""); });
    source.addEventListener("input", function () { if (!target.hasAttribute("data-touched")) target.value = slugify(source.value); });
  });

  function updateCount(field) {
    var holder = field.closest(".field"), out = holder && $(".field__count", holder);
    if (!out) return;
    var ideal = Number(field.getAttribute("data-count-ideal")) || field.maxLength;
    out.textContent = field.value.length + " / " + ideal;
    out.classList.toggle("is-over", field.value.length > ideal);
  }
  $$("[data-count]").forEach(function (f) { updateCount(f); f.addEventListener("input", function () { updateCount(f); }); });

  $$("[data-bind]").forEach(function (input) {
    input.addEventListener("input", function () {
      var value = input.value.trim() || input.placeholder;
      $$('[data-bound="' + input.getAttribute("data-bind") + '"]').forEach(function (el) { el.textContent = value; });
    });
  });

  /* ---------- Photos: they preview here; uploading needs the backend ---------- */
  var thumbTemplate = document.createElement("template");
  thumbTemplate.innerHTML = '<li class="gthumb" data-sort-item draggable="true"><img alt="" draggable="false"><span class="gthumb__tag" data-gthumb-tag></span>' +
    '<button type="button" class="gthumb__remove" data-remove-closest=".gthumb" aria-label="Remove photo">' + iconHTML("x") + "</button></li>";
  function galleryThumb(src) {
    var li = thumbTemplate.content.firstElementChild.cloneNode(true);
    li.querySelector("img").src = src;
    return li;
  }
  function relabelGallery(list) {
    $$("[data-gthumb-tag]", list).forEach(function (tag, i) { tag.textContent = i === 0 ? "Main" : i === 1 ? "On hover" : ""; });
  }
  function renumber(list) { $$("[data-position]", list).forEach(function (el, i) { el.textContent = i + 1; }); }

  $$("[data-dropzone]").forEach(function (zone) {
    var input = $("input[type=file]", zone);
    var target = $(input.getAttribute("data-upload-into"), zone.closest("form") || document);
    function add(files) {
      var images = Array.prototype.filter.call(files, function (f) { return /^image\//.test(f.type); });
      images.forEach(function (file) { target.appendChild(galleryThumb(URL.createObjectURL(file))); });
      if (target.hasAttribute("data-gallery")) relabelGallery(target);
      if (images.length) toast(images.length === 1 ? "Photo added" : images.length + " photos added", "Previewed here only — uploading starts once the backend is connected.");
    }
    ["dragenter", "dragover"].forEach(function (type) { zone.addEventListener(type, function (e) { e.preventDefault(); zone.classList.add("is-over"); }); });
    ["dragleave", "drop"].forEach(function (type) { zone.addEventListener(type, function () { zone.classList.remove("is-over"); }); });
    zone.addEventListener("drop", function (e) { e.preventDefault(); add(e.dataTransfer.files); });
    input.addEventListener("change", function () { add(input.files); input.value = ""; });
  });

  document.addEventListener("change", function (e) {
    var input = e.target.closest("[data-image-input]");
    if (!input || !input.files || !input.files[0]) return;
    var holder = input.closest(".image-field, .panel-edit__media, .hero-edit__photo");
    var img = holder && $("[data-image-preview]", holder);
    if (img) { img.src = URL.createObjectURL(input.files[0]); img.hidden = false; }
    var note = holder && $(".image-field__empty", holder);
    if (note) note.hidden = true;
    input.value = "";
    toast("Photo replaced", "Previewed here only — uploading starts once the backend is connected.");
  });

  document.addEventListener("click", function (e) {
    var btn = e.target.closest("[data-remove-closest]");
    if (!btn) return;
    var item = btn.closest(btn.getAttribute("data-remove-closest"));
    if (!item) return;
    var list = item.parentElement;
    item.remove();
    if (list.hasAttribute("data-gallery")) relabelGallery(list);
    renumber(list);
    list.dispatchEvent(new CustomEvent("admin:itemremoved", { bubbles: true }));
  });

  /* ---------- Drag to reorder ---------- */
  $$("[data-sortable]").forEach(function (list) {
    var dragging = null;
    list.addEventListener("pointerdown", function (e) {
      var item = e.target.closest("[data-sort-item]");
      // Fields and buttons inside an item keep working normally; drag from anywhere else
      if (item) item.draggable = !e.target.closest("input, textarea, select, button, a, label");
    });
    list.addEventListener("dragstart", function (e) {
      var item = e.target.closest("[data-sort-item]");
      if (!item || item.parentElement !== list) return;
      dragging = item;
      item.classList.add("is-dragging");
      e.dataTransfer.effectAllowed = "move";
      try { e.dataTransfer.setData("text/plain", ""); } catch (err) { /* not needed everywhere */ }
    });
    list.addEventListener("dragover", function (e) {
      if (!dragging) return;
      e.preventDefault();
      var over = e.target.closest("[data-sort-item]");
      if (!over || over === dragging || over.parentElement !== list) return;
      var r = over.getBoundingClientRect();
      var sideways = r.width < list.clientWidth * 0.6;
      var after = sideways ? e.clientX > r.left + r.width / 2 : e.clientY > r.top + r.height / 2;
      list.insertBefore(dragging, after ? over.nextSibling : over);
    });
    list.addEventListener("dragend", function () {
      if (!dragging) return;
      dragging.classList.remove("is-dragging");
      dragging = null;
      if (list.hasAttribute("data-gallery")) relabelGallery(list);
      renumber(list);
      list.dispatchEvent(new CustomEvent("admin:reordered", { bubbles: true }));
      if (list.getAttribute("data-sort-toast")) toast(list.getAttribute("data-sort-toast"));
    });
  });

  /* ---------- Text editor ---------- */
  $$("[data-rte]").forEach(function (rte) {
    var area = $(".rte__area", rte);
    $$("[data-cmd]", rte).forEach(function (btn) {
      btn.addEventListener("mousedown", function (e) { e.preventDefault(); }); // keep the selection in the text
      btn.addEventListener("click", function () {
        var cmd = btn.getAttribute("data-cmd"), value = btn.getAttribute("data-value") || null;
        if (cmd === "insertImage") { toast("Choose a photo", "Photos come from the media library once the backend is connected."); return; }
        area.focus();
        if (cmd === "createLink") { value = window.prompt("Link address", "https://"); if (!value) return; }
        if (cmd === "formatBlock") value = document.queryCommandValue("formatBlock") === value ? "p" : value;
        document.execCommand(cmd, false, value);
      });
    });
  });

  /* ---------- Charts ---------- */
  var SVG = "http://www.w3.org/2000/svg";
  function svgEl(tag, attrs, parent) {
    var el = document.createElementNS(SVG, tag);
    for (var k in attrs) el.setAttribute(k, attrs[k]);
    if (parent) parent.appendChild(el);
    return el;
  }
  function niceMax(v) {
    var step = Math.pow(10, Math.floor(Math.log10(v))), n = v / step;
    return (n <= 1 ? 1 : n <= 2 ? 2 : n <= 2.5 ? 2.5 : n <= 5 ? 5 : 10) * step;
  }
  // One series of weekly values: a 2px line over a light wash, a crosshair and tooltip on hover, arrow keys to step through
  $$("[data-line-chart]").forEach(function (el) {
    var data = JSON.parse(el.getAttribute("data-series"));
    var unit = el.getAttribute("data-unit") || "";
    var svg = svgEl("svg", { "aria-hidden": "true" });
    var tipBox = document.createElement("div");
    tipBox.className = "chart__tip";
    el.appendChild(svg);
    el.appendChild(tipBox);
    var geo = null, active = -1, cross, hoverDot;
    function draw() {
      var W = el.clientWidth, H = el.clientHeight;
      if (!W || !H) return;
      var pad = { l: 34, r: 34, t: 14, b: 30 };
      var max = niceMax(Math.max.apply(null, data.map(function (d) { return d.value; })));
      var x = function (i) { return pad.l + (i * (W - pad.l - pad.r)) / (data.length - 1); };
      var y = function (v) { return H - pad.b - (v / max) * (H - pad.t - pad.b); };
      geo = { x: x, y: y, pad: pad, W: W };
      svg.setAttribute("viewBox", "0 0 " + W + " " + H);
      svg.textContent = "";
      for (var t = 0; t <= 5; t++) {
        var value = (max / 5) * t, yy = Math.round(y(value)) + 0.5;
        svgEl("line", { class: t ? "grid-line" : "baseline", x1: pad.l, x2: W - pad.r + 12, y1: yy, y2: yy }, svg);
        svgEl("text", { class: "tick", x: pad.l - 10, y: yy + 4, "text-anchor": "end" }, svg).textContent = value;
      }
      var every = W < 520 ? 3 : 2;
      data.forEach(function (d, i) {
        if ((data.length - 1 - i) % every) return;
        svgEl("text", { class: "tick", x: x(i), y: H - 8, "text-anchor": "middle" }, svg).textContent = d.label;
      });
      var points = data.map(function (d, i) { return x(i).toFixed(1) + "," + y(d.value).toFixed(1); });
      svgEl("path", { class: "area", d: "M" + x(0) + "," + y(0) + " L" + points.join(" L") + " L" + x(data.length - 1) + "," + y(0) + " Z" }, svg);
      svgEl("polyline", { class: "line", points: points.join(" ") }, svg);
      cross = svgEl("line", { class: "cross", x1: 0, x2: 0, y1: pad.t, y2: H - pad.b, visibility: "hidden" }, svg);
      var last = data.length - 1;
      svgEl("circle", { class: "dot", cx: x(last), cy: y(data[last].value), r: 4.5 }, svg);
      svgEl("text", { class: "end-label", x: x(last) + 10, y: y(data[last].value) + 4 }, svg).textContent = data[last].value;
      hoverDot = svgEl("circle", { class: "dot", cx: 0, cy: 0, r: 5, visibility: "hidden" }, svg);
      if (active >= 0) show(active);
    }
    function show(i) {
      if (!geo) return;
      active = i;
      var d = data[i], cx = geo.x(i), cy = geo.y(d.value);
      cross.setAttribute("x1", cx);
      cross.setAttribute("x2", cx);
      hoverDot.setAttribute("cx", cx);
      hoverDot.setAttribute("cy", cy);
      cross.setAttribute("visibility", "visible");
      hoverDot.setAttribute("visibility", "visible");
      tipBox.textContent = "";
      var strong = document.createElement("strong"), label = document.createElement("span");
      strong.textContent = d.value + " " + unit;
      label.textContent = "Week of " + d.label;
      tipBox.appendChild(strong);
      tipBox.appendChild(label);
      tipBox.classList.add("is-shown");
      var left = cx + 14;
      if (left + tipBox.offsetWidth > geo.W) left = cx - tipBox.offsetWidth - 14;
      tipBox.style.transform = "translate(" + left + "px," + Math.max(0, cy - tipBox.offsetHeight - 10) + "px)";
    }
    function hide() {
      active = -1;
      tipBox.classList.remove("is-shown");
      if (cross) { cross.setAttribute("visibility", "hidden"); hoverDot.setAttribute("visibility", "hidden"); }
    }
    el.addEventListener("pointermove", function (e) {
      if (!geo) return;
      var step = (geo.W - geo.pad.l - geo.pad.r) / (data.length - 1);
      show(clamp(Math.round((e.clientX - el.getBoundingClientRect().left - geo.pad.l) / step), 0, data.length - 1));
    });
    el.addEventListener("pointerleave", hide);
    el.addEventListener("blur", hide);
    el.addEventListener("keydown", function (e) {
      var step = { ArrowRight: 1, ArrowLeft: -1 }[e.key];
      if (!step && e.key !== "Home" && e.key !== "End") return;
      e.preventDefault();
      show(e.key === "Home" ? 0 : e.key === "End" ? data.length - 1 : clamp((active < 0 ? data.length : active) + step, 0, data.length - 1));
    });
    if ("ResizeObserver" in window) new ResizeObserver(draw).observe(el);
    draw();
  });

  $$("[data-chart-toggle]").forEach(function (btn) {
    var card = btn.closest(".card"), chart = $("[data-line-chart]", card), table = $(".chart-table", card);
    btn.addEventListener("click", function () {
      var showTable = btn.getAttribute("aria-pressed") !== "true";
      btn.setAttribute("aria-pressed", String(showTable));
      $("span", btn).textContent = showTable ? "Show chart" : "Show table";
      chart.hidden = showTable;
      table.hidden = !showTable;
    });
  });

  // Bars: the value leads in the tooltip, the category follows
  $$("[data-chart-tip]").forEach(function (bar) {
    function show() {
      var parts = bar.getAttribute("data-chart-tip").split("|");
      var box = document.createElement("div"), strong = document.createElement("strong"), label = document.createElement("span");
      strong.textContent = parts[1];
      label.textContent = parts[0];
      box.className = "tooltip__stack";
      box.appendChild(strong);
      box.appendChild(label);
      showTip(bar, box);
    }
    bar.addEventListener("pointerenter", show);
    bar.addEventListener("focus", show);
    bar.addEventListener("pointerleave", hideTip);
  });

  /* ---------- Enquiries ---------- */
  var inbox = $("[data-inbox]");
  if (inbox) {
    var SAVED_REPLIES = {
      visit: "Thank you for writing to Mangalam, {name}. We would love to show you the piece in person — Mangalam House on Ring Road is open Monday to Saturday, 10:30 AM to 8:30 PM. Shall I book a private viewing for you?",
      bridal: "Congratulations, {name}! Our bridal consultations are private and unhurried — about ninety minutes with a designer. Tell us two or three dates that suit you and we will prepare pieces before you arrive.",
      care: "Hello {name}, cleaning and repairs are free for the life of every Mangalam piece. Bring it to Mangalam House any day and our karigars will look at it while you wait.",
    };
    inbox.addEventListener("click", function (e) {
      var open = e.target.closest("[data-thread-open]");
      if (open) {
        var id = open.getAttribute("data-thread-open");
        $$("[data-thread-open]", inbox).forEach(function (b) { b.setAttribute("aria-current", String(b === open)); });
        $$("[data-thread]", inbox).forEach(function (t) { t.hidden = t.getAttribute("data-thread") !== id; });
        open.classList.remove("is-unread");
        inbox.classList.add("is-reading");
        $(".inbox__thread", inbox).scrollTop = 0;
        if (!wide.matches) window.scrollTo(0, inbox.getBoundingClientRect().top + window.scrollY - 80);
      }
      if (e.target.closest("[data-thread-back]")) inbox.classList.remove("is-reading");
    });
    inbox.addEventListener("change", function (e) {
      var pick = e.target.closest("[data-saved-reply]");
      if (!pick || !pick.value) return;
      var text = $("textarea", pick.closest("form"));
      text.value = SAVED_REPLIES[pick.value].replace("{name}", text.getAttribute("data-reply-for"));
      text.focus();
      pick.value = "";
    });
  }

  /* ---------- Media library ---------- */
  var mediaInfo = $("[data-media-info]");
  if (mediaInfo) {
    $$("[data-media]").forEach(function (t) { t.setAttribute("aria-pressed", "false"); });
    document.addEventListener("click", function (e) {
      var tile = e.target.closest("[data-media]");
      if (!tile) return;
      var data = JSON.parse(tile.getAttribute("data-media"));
      $$("[data-media]").forEach(function (t) { t.setAttribute("aria-pressed", String(t === tile)); });
      $("[data-media-empty]", mediaInfo).hidden = true;
      $("[data-media-body]", mediaInfo).hidden = false;
      $$("[data-media-field]", mediaInfo).forEach(function (el) {
        var key = el.getAttribute("data-media-field"), value = data[key];
        if (key === "src") { el.src = value; el.alt = data.alt || ""; }
        else if (key === "used") {
          el.textContent = "";
          var list = value.length ? value.slice(0, 6) : ["Not used on the website yet"];
          list.forEach(function (text) { var li = document.createElement("li"); li.textContent = text; if (!value.length) li.className = "is-none"; el.appendChild(li); });
          if (value.length > 6) { var more = document.createElement("li"); more.className = "is-none"; more.textContent = "and " + (value.length - 6) + " more"; el.appendChild(more); }
        } else if (el.tagName === "INPUT" || el.tagName === "TEXTAREA") el.value = value || "";
        else el.textContent = value || "";
      });
      if (!wide.matches) mediaInfo.scrollIntoView({ behavior: "smooth", block: "start" });
    });
  }
  document.addEventListener("click", function (e) {
    var btn = e.target.closest("[data-copy-from]");
    if (!btn) return;
    var field = $(btn.getAttribute("data-copy-from"));
    var text = field.value || field.textContent;
    var done = function () { toast("Address copied", text); };
    if (navigator.clipboard) navigator.clipboard.writeText(text).then(done, function () { field.select(); toast("Press Ctrl + C to copy", text); });
    else { field.select(); toast("Press Ctrl + C to copy", text); }
  });

  /* ---------- Homepage: shop-the-look pins and featured pieces ---------- */
  var stage = $("[data-hotspot-stage]"), pinList = $("[data-pin-list]");
  if (stage && pinList) {
    var pinRowTemplate = $("[data-pin-row]", pinList).cloneNode(true);
    var rowFor = function (n) { return $('[data-pin-row="' + n + '"]', pinList); };
    var highlight = function (n) {
      $$(".pin", stage).forEach(function (p) { p.classList.toggle("is-active", p.getAttribute("data-pin") === n); });
      $$("[data-pin-row]", pinList).forEach(function (r) { r.classList.toggle("is-active", r.getAttribute("data-pin-row") === n); });
    };
    var placePin = function (pin, x, y) {
      x = clamp(x, 2, 98);
      y = clamp(y, 2, 98);
      pin.style.setProperty("--x", x.toFixed(1) + "%");
      pin.style.setProperty("--y", y.toFixed(1) + "%");
      var pos = $("[data-pin-pos]", rowFor(pin.getAttribute("data-pin")) || document.createElement("div"));
      if (pos) pos.textContent = x.toFixed(1) + "% across · " + y.toFixed(1) + "% down";
    };
    stage.addEventListener("pointerdown", function (e) {
      var pin = e.target.closest(".pin");
      if (!pin) return;
      e.preventDefault();
      pin.setPointerCapture(e.pointerId);
      pin.classList.add("is-dragging");
      highlight(pin.getAttribute("data-pin"));
      var move = function (ev) {
        var r = stage.getBoundingClientRect();
        placePin(pin, ((ev.clientX - r.left) / r.width) * 100, ((ev.clientY - r.top) / r.height) * 100);
      };
      var up = function () {
        pin.classList.remove("is-dragging");
        pin.removeEventListener("pointermove", move);
        pin.removeEventListener("pointerup", up);
        pin.removeEventListener("pointercancel", up);
      };
      pin.addEventListener("pointermove", move);
      pin.addEventListener("pointerup", up);
      pin.addEventListener("pointercancel", up);
    });
    stage.addEventListener("keydown", function (e) {
      var pin = e.target.closest(".pin");
      var d = { ArrowLeft: [-1, 0], ArrowRight: [1, 0], ArrowUp: [0, -1], ArrowDown: [0, 1] }[e.key];
      if (!pin || !d) return;
      e.preventDefault();
      var step = e.shiftKey ? 5 : 0.5;
      placePin(pin, parseFloat(pin.style.getPropertyValue("--x")) + d[0] * step, parseFloat(pin.style.getPropertyValue("--y")) + d[1] * step);
    });
    stage.addEventListener("focusin", function (e) { var pin = e.target.closest(".pin"); if (pin) highlight(pin.getAttribute("data-pin")); });
    pinList.addEventListener("pointerover", function (e) { var row = e.target.closest("[data-pin-row]"); if (row) highlight(row.getAttribute("data-pin-row")); });
    pinList.addEventListener("change", function (e) {
      var select = e.target.closest("[data-pin-product]");
      if (!select || !MJ) return;
      var p = MJ.products.filter(function (x) { return x.slug === select.value; })[0];
      var row = select.closest("[data-pin-row]"), n = row.getAttribute("data-pin-row");
      if (p) {
        $("[data-pin-thumb]", row).src = "../" + p.thumb;
        var pin = $('.pin[data-pin="' + n + '"]', stage);
        if (pin) pin.setAttribute("aria-label", "Pin " + n + ", " + p.name + ". Drag to move.");
      }
    });
    document.addEventListener("admin:confirmed", function (e) {
      var n = e.target.getAttribute && e.target.getAttribute("data-remove-pin");
      if (!n) return;
      var pin = $('.pin[data-pin="' + n + '"]', stage), row = rowFor(n);
      if (pin) pin.remove();
      if (row) row.remove();
    });
    $("[data-add-pin]").addEventListener("click", function () {
      var numbers = $$("[data-pin-row]", pinList).map(function (r) { return Number(r.getAttribute("data-pin-row")); });
      var n = String(Math.max.apply(null, [0].concat(numbers)) + 1);
      var row = pinRowTemplate.cloneNode(true);
      row.setAttribute("data-pin-row", n);
      $(".pinrow__num", row).textContent = n;
      var select = $("select", row);
      select.selectedIndex = 0;
      select.setAttribute("aria-label", "Piece shown by pin " + n);
      if (MJ) $("[data-pin-thumb]", row).src = "../" + MJ.products[0].thumb;
      var remove = $("[data-remove-pin]", row);
      remove.setAttribute("data-remove-pin", n);
      remove.setAttribute("data-confirm", "Remove pin " + n + "?");
      remove.setAttribute("aria-label", "Remove pin " + n);
      pinList.appendChild(row);
      var pin = document.createElement("button");
      pin.type = "button";
      pin.className = "pin";
      pin.textContent = n;
      pin.setAttribute("data-pin", n);
      pin.setAttribute("aria-label", "Pin " + n + ". Drag to move.");
      stage.appendChild(pin);
      placePin(pin, 50, 50);
      highlight(n);
      toast("Pin added", "Drag it onto the jewel it should point to.");
    });
  }

  var pickAdd = $("[data-pick-add]");
  if (pickAdd && MJ) pickAdd.addEventListener("click", function () {
    var select = $("[data-pick-select]"), list = $(".picks");
    var p = MJ.products.filter(function (x) { return x.slug === select.value; })[0];
    if (!p) { select.focus(); return; }
    var li = document.createElement("li");
    li.className = "pick";
    li.setAttribute("data-sort-item", "");
    li.draggable = true;
    li.innerHTML = '<span class="pick__num" data-position></span><img alt="" draggable="false"><span class="pick__text"><span class="pick__name"></span><span class="pick__meta"></span></span>' +
      '<button type="button" class="icon-btn icon-btn--sm" data-remove-closest=".pick">' + iconHTML("x") + "</button>";
    $("img", li).src = "../" + p.thumb;
    $(".pick__name", li).textContent = p.name;
    $(".pick__meta", li).textContent = MJ.capitalize(p.category) + " · " + MJ.formatPrice(p.price);
    $("button", li).setAttribute("aria-label", "Remove " + p.name + " from featured pieces");
    list.appendChild(li);
    renumber(list);
    select.value = "";
    toast("Added to featured pieces", p.name + " now appears in the Signature tab.");
  });

  /* ---------- Offer and announcements ---------- */
  var announcementList = $("[data-announcements]");
  if (announcementList) {
    var announcementTemplate = $(".arow", announcementList).cloneNode(true);
    var renderAnnouncements = function () {
      var out = $("[data-announcement-preview]");
      var texts = $$("[data-announcement]", announcementList).map(function (i) { return i.value.trim(); }).filter(Boolean);
      out.textContent = "";
      texts.concat(texts).forEach(function (text) { var li = document.createElement("li"); li.textContent = text; out.appendChild(li); });
    };
    announcementList.addEventListener("input", renderAnnouncements);
    announcementList.addEventListener("admin:reordered", renderAnnouncements);
    announcementList.addEventListener("admin:itemremoved", renderAnnouncements);
    $("[data-add-announcement]").addEventListener("click", function () {
      var row = announcementTemplate.cloneNode(true), input = $("input", row);
      input.value = "";
      input.placeholder = "A new message";
      input.setAttribute("aria-label", "New message");
      announcementList.appendChild(row);
      input.focus();
    });
  }
  var offerToggle = $("[data-offer-toggle]");
  if (offerToggle) offerToggle.addEventListener("change", function () {
    $("[data-offer-preview]").classList.toggle("is-off", !offerToggle.checked);
    toast(offerToggle.checked ? "Offer switched on" : "Offer switched off", offerToggle.checked ? "It would open on each visitor's first page again." : "Visitors would no longer see the popup or its tab.");
  });

  /* ---------- Settings: unsaved changes and opening hours ---------- */
  $$("form[data-dirty-watch]").forEach(function (form) {
    var bar = $("[data-savebar]", form);
    var show = function (e) { if (e.isTrusted) bar.hidden = false; };
    form.addEventListener("input", show);
    form.addEventListener("change", show);
    form.addEventListener("reset", function () {
      setTimeout(function () {
        bar.hidden = true;
        $$("[data-hours-toggle]", form).forEach(syncHours);
      }, 0);
      toast("Changes discarded", "Everything is back as it was.");
    });
  });
  function syncHours(toggle) {
    var row = toggle.closest(".hours__row");
    $$(".input", row).forEach(function (i) { i.disabled = !toggle.checked; });
    toggle.parentElement.lastElementChild.textContent = toggle.checked ? "Open" : "Closed";
  }
  document.addEventListener("change", function (e) { if (e.target.matches("[data-hours-toggle]")) syncHours(e.target); });

  /* ---------- Product and story forms: open the piece named in the address ---------- */
  function paragraphs(el, texts) {
    el.textContent = "";
    texts.forEach(function (t) { var p = document.createElement("p"); p.textContent = t; el.appendChild(p); });
  }
  function fillProduct(p, form) {
    var featured = MJ.featuredSlugs.indexOf(p.slug) !== -1;
    [["name", p.name], ["slug", p.slug], ["summary", p.description], ["price", p.price], ["metal", p.metal], ["purity", p.purity],
      ["stone", p.metal === "Diamond" ? "Diamond" : "None"], ["category", p.category], ["style", p.style], ["collection", p.collection],
      ["isNew", p.isNew], ["featured", featured], ["seoTitle", p.name + " — Mangalam Jewellers"], ["seoDesc", p.description]]
      .forEach(function (pair) { setField(form, pair[0], pair[1]); });
    var set = function (key, fn) { $$('[data-fill-product="' + key + '"]').forEach(fn); };
    set("title", function (el) { el.textContent = p.name; });
    set("crumb", function (el) { el.textContent = p.name; });
    set("desc", function (el) { el.textContent = MJ.capitalize(p.category) + " · " + p.purity + " " + p.metal + " · " + MJ.formatPrice(p.price); });
    set("view", function (el) { el.href = "../product.html?slug=" + p.slug; });
    set("description", function (el) { paragraphs(el, [p.description, "Every Mangalam piece carries a BIS hallmark and is handcrafted to order in our Surat atelier."]); });
    set("gallery", function (el) {
      el.textContent = "";
      p.gallery.forEach(function (src) { el.appendChild(galleryThumb("../" + MJ.small(src))); });
      relabelGallery(el);
    });
    var map = $("#product-collections");
    set("collections", function (el) { el.textContent = (map && JSON.parse(map.textContent)[p.slug] || []).join(", ") || "None yet"; });
    document.title = p.name + " — Mangalam Admin";
    refreshFields(form);
  }
  function fillArticle(a, form) {
    [["title", a.title], ["slug", a.slug], ["excerpt", a.excerpt], ["category", a.category], ["readTime", parseInt(a.readTime, 10)],
      ["seoTitle", a.title + " — Mangalam Journal"], ["seoDesc", a.excerpt]].forEach(function (pair) { setField(form, pair[0], pair[1]); });
    var set = function (key, fn) { $$('[data-fill-article="' + key + '"]').forEach(fn); };
    set("title", function (el) { el.textContent = a.title; });
    set("crumb", function (el) { el.textContent = a.title; });
    set("desc", function (el) { el.textContent = a.category + " · published " + a.date; });
    set("view", function (el) { el.href = "../article.html?slug=" + a.slug; });
    set("body", function (el) { paragraphs(el, a.body); });
    var cover = $(".image-field--cover img", form);
    if (cover) { cover.src = "../" + a.image; cover.hidden = false; }
    document.title = a.title + " — Mangalam Admin";
    refreshFields(form);
  }
  var slug = params.get("slug"), editorForm = $("form.editor-page");
  if (slug && MJ && editorForm && /-edit\.html$/.test(location.pathname)) {
    if (page === "product-form") {
      var product = MJ.products.filter(function (x) { return x.slug === slug; })[0];
      if (product) fillProduct(product, editorForm);
    } else if (page === "article-form") {
      var article = MJ.articles.filter(function (x) { return x.slug === slug; })[0];
      if (article) fillArticle(article, editorForm);
    }
  }

  /* ---------- Sign in, greeting, search shortcut, Escape ---------- */
  var login = $("[data-login]");
  if (login) login.addEventListener("submit", function (e) { e.preventDefault(); location.href = "index.html"; });
  $$("[data-password-toggle]").forEach(function (btn) {
    btn.addEventListener("click", function () {
      var input = btn.parentElement.querySelector("input");
      var show = input.type === "password";
      input.type = show ? "text" : "password";
      btn.setAttribute("aria-pressed", String(show));
      btn.setAttribute("aria-label", show ? "Hide password" : "Show password");
    });
  });

  var greeting = $("[data-greeting]");
  if (greeting) {
    var hour = new Date().getHours();
    greeting.textContent = hour < 12 ? "Good morning" : hour < 17 ? "Good afternoon" : "Good evening";
  }

  var globalSearch = $("[data-global-search]");
  if (globalSearch) {
    document.addEventListener("keydown", function (e) {
      if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === "k") { e.preventDefault(); globalSearch.focus(); globalSearch.select(); }
    });
    globalSearch.addEventListener("keydown", function (e) {
      if (e.key === "Enter" && globalSearch.value.trim()) location.href = "products.html?search=" + encodeURIComponent(globalSearch.value.trim());
    });
  }

  document.addEventListener("keydown", function (e) {
    if (e.key !== "Escape") return;
    if (openMenu) { closeMenu(true); return; }
    hideTip();
    if (root.classList.contains("side-open")) setSideOpen(false);
  });
})();
