/* Mangalam Jewellers — shared UI helpers.
 * Lucide icons plus the shadcn/ui class recipes (button, input, dialog …) that the
 * original React site used. `cn()` is a small tailwind-merge: later classes win over
 * earlier ones from the same group, exactly like the original `cn()` helper.
 */
(function (root) {
  "use strict";

  /* ---------- Icons (lucide 0.575) ---------- */
  var ICONS = {
    "arrow-right": '<path d="M5 12h14"/><path d="m12 5 7 7-7 7"/>',
    "arrow-left": '<path d="m12 19-7-7 7-7"/><path d="M19 12H5"/>',
    "arrow-up-right": '<path d="M7 7h10v10"/><path d="M7 17 17 7"/>',
    "calendar-days": '<path d="M8 2v4"/><path d="M16 2v4"/><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18"/><path d="M8 14h.01"/><path d="M12 14h.01"/><path d="M16 14h.01"/><path d="M8 18h.01"/><path d="M12 18h.01"/><path d="M16 18h.01"/>',
    check: '<path d="M20 6 9 17l-5-5"/>',
    "chevron-down": '<path d="m6 9 6 6 6-6"/>',
    clock: '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
    heart: '<path d="M2 9.5a5.5 5.5 0 0 1 9.591-3.676.56.56 0 0 0 .818 0A5.49 5.49 0 0 1 22 9.5c0 2.29-1.5 4-3 5.5l-5.492 5.313a2 2 0 0 1-3 .019L5 15c-1.5-1.5-3-3.2-3-5.5"/>',
    instagram: '<rect width="20" height="20" x="2" y="2" rx="5" ry="5"/><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/><line x1="17.5" x2="17.51" y1="6.5" y2="6.5"/>',
    mail: '<path d="m22 7-8.991 5.727a2 2 0 0 1-2.009 0L2 7"/><rect x="2" y="4" width="20" height="16" rx="2"/>',
    "map-pin": '<path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0"/><circle cx="12" cy="10" r="3"/>',
    menu: '<path d="M4 5h16"/><path d="M4 12h16"/><path d="M4 19h16"/>',
    "message-circle": '<path d="M2.992 16.342a2 2 0 0 1 .094 1.167l-1.065 3.29a1 1 0 0 0 1.236 1.168l3.413-.998a2 2 0 0 1 1.099.092 10 10 0 1 0-4.777-4.719"/>',
    phone: '<path d="M13.832 16.568a1 1 0 0 0 1.213-.303l.355-.465A2 2 0 0 1 17 15h3a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2A18 18 0 0 1 2 4a2 2 0 0 1 2-2h3a2 2 0 0 1 2 2v3a2 2 0 0 1-.8 1.6l-.468.351a1 1 0 0 0-.292 1.233 14 14 0 0 0 6.392 6.384"/>',
    search: '<path d="m21 21-4.34-4.34"/><circle cx="11" cy="11" r="8"/>',
    "shopping-bag": '<path d="M16 10a4 4 0 0 1-8 0"/><path d="M3.103 6.034h17.794"/><path d="M3.4 5.467a2 2 0 0 0-.4 1.2V20a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6.667a2 2 0 0 0-.4-1.2l-2-2.667A2 2 0 0 0 17 2H7a2 2 0 0 0-1.6.8z"/>',
    "sliders-horizontal": '<path d="M10 5H3"/><path d="M12 19H3"/><path d="M14 3v4"/><path d="M16 17v4"/><path d="M21 12h-9"/><path d="M21 19h-5"/><path d="M21 5h-7"/><path d="M8 10v4"/><path d="M8 12H3"/>',
    "user-round": '<circle cx="12" cy="8" r="5"/><path d="M20 21a8 8 0 0 0-16 0"/>',
    x: '<path d="M18 6 6 18"/><path d="m6 6 12 12"/>',
  };

  function icon(name, cls) {
    return '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-' + name + (cls ? " " + cls : "") + '" aria-hidden="true">' + ICONS[name] + "</svg>";
  }

  /* ---------- cn(): tailwind-merge (lite) ---------- */
  function splitVariant(cls) {
    var depth = 0, cut = -1;
    for (var i = 0; i < cls.length; i++) {
      var ch = cls[i];
      if (ch === "[" || ch === "(") depth++;
      else if (ch === "]" || ch === ")") depth--;
      else if (ch === ":" && depth === 0) cut = i;
    }
    return cut === -1 ? { variant: "", utility: cls } : { variant: cls.slice(0, cut), utility: cls.slice(cut + 1) };
  }

  function groupOf(u) {
    u = u.replace(/^!/, "").replace(/^-/, "");
    var m;
    if (/^(block|inline-block|inline|flex|inline-flex|grid|inline-grid|hidden|contents|table)$/.test(u)) return "display";
    if (/^rounded(-|$)/.test(u)) return "rounded";
    if (/^text-(xs|sm|base|lg|xl|\dxl|\[\d[^\]]*\])$/.test(u)) return "font-size";
    if (/^text-(left|center|right|justify|start|end)$/.test(u)) return "text-align";
    if (/^text-/.test(u)) return "text-color";
    if (/^bg-/.test(u)) return "bg";
    if (/^p-/.test(u)) return "p";
    if ((m = u.match(/^(px|py|pt|pr|pb|pl)-/))) return m[1];
    if ((m = u.match(/^(min-h|min-w|max-h|max-w|size|h|w)-/))) return m[1];
    if (/^border(-\d+|-\[\d[^\]]*\])?$/.test(u)) return "border-w";
    if (/^border-(x|y|t|r|b|l|s|e)(-|$)/.test(u)) return u;
    if (/^border-/.test(u)) return "border-color";
    if (/^shadow(-|$)/.test(u)) return "shadow";
    if (/^transition(-|$)/.test(u)) return "transition";
    if (/^ring(-\d+)?$/.test(u)) return "ring-w";
    if (/^ring-offset/.test(u)) return u;
    if (/^ring-/.test(u)) return "ring-color";
    if (/^font-(thin|extralight|light|normal|medium|semibold|bold|extrabold|black)$/.test(u)) return "font-weight";
    if (/^font-/.test(u)) return "font-family";
    if (/^leading-/.test(u)) return "leading";
    if (/^tracking-/.test(u)) return "tracking";
    if (/^opacity-/.test(u)) return "opacity";
    if (/^gap-/.test(u)) return "gap";
    if ((m = u.match(/^(m[trblxy]?)-/))) return m[1];
    return u;
  }

  var CONFLICTS = { p: ["px", "py", "pt", "pr", "pb", "pl"], "font-size": ["leading"], size: ["w", "h"] };

  function cn() {
    var list = [];
    for (var a = 0; a < arguments.length; a++) if (arguments[a]) list = list.concat(String(arguments[a]).split(/\s+/).filter(Boolean));
    var seen = {}, out = [];
    for (var i = list.length - 1; i >= 0; i--) {
      var parts = splitVariant(list[i]);
      var group = groupOf(parts.utility);
      var key = parts.variant + "|" + group;
      if (seen[key]) continue;
      seen[key] = true;
      (CONFLICTS[group] || []).forEach(function (g) { seen[parts.variant + "|" + g] = true; });
      out.push(list[i]);
    }
    return out.reverse().join(" ");
  }

  /* ---------- shadcn/ui recipes ---------- */
  var BUTTON_BASE = "inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium cursor-pointer transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:pointer-events-none disabled:opacity-50 disabled:cursor-not-allowed [&_svg]:pointer-events-none [&_svg]:size-4 [&_svg]:shrink-0";
  var BUTTON_VARIANTS = {
    default: "bg-primary text-primary-foreground shadow hover:bg-primary/90",
    outline: "border border-input bg-background shadow-sm hover:bg-accent hover:text-accent-foreground",
    ghost: "hover:bg-accent hover:text-accent-foreground",
    link: "text-primary underline-offset-4 hover:underline",
  };
  var BUTTON_SIZES = { default: "h-9 px-4 py-2", icon: "h-9 w-9" };

  function btn(variant, size, cls) {
    return cn(BUTTON_BASE, BUTTON_VARIANTS[variant || "default"], BUTTON_SIZES[size || "default"], cls);
  }

  var INPUT = "flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-base shadow-sm transition-colors placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50 md:text-sm";
  var TEXTAREA = "flex min-h-[60px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-base shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50 md:text-sm";
  var CHECKBOX = "grid place-content-center peer h-4 w-4 shrink-0 rounded-sm border border-primary shadow cursor-pointer focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50 data-[state=checked]:bg-primary data-[state=checked]:text-primary-foreground";
  var LABEL = "text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70";

  root.MJUI = {
    icon: icon,
    cn: cn,
    btn: btn,
    input: function (cls) { return cn(INPUT, cls); },
    textarea: function (cls) { return cn(TEXTAREA, cls); },
    checkbox: function (cls) { return cn(CHECKBOX, cls); },
    label: function (cls) { return cn(LABEL, cls); },
  };
})(typeof window !== "undefined" ? window : globalThis);
