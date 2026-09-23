# Mangalam Jewellers — website

Static HTML/CSS/JS website for Mangalam Jewellers, designed around the client's logo (antique gold `#A57E00`) with a midnight-wine and ivory palette.

Open it through XAMPP: **http://localhost/mangalam/**

## Pages

| File | Contents |
| --- | --- |
| `index.html` | Hero with "shop the look" hotspots, promise strip, shop by category, collections, featured pieces (tabs), the Mangalam promise (counters), bridal edit, craftsmanship, heritage, closer look (magnifier), manifesto, testimonials, journal, Instagram, appointment |
| `collections.html` | The six collections |
| `jewellery.html` | Full catalogue with filters and sorting (supports `?metal=Gold`, `?metal=Diamond`, `?style=Traditional` …) |
| `bridal.html`, `rings.html`, `necklaces.html`, `earrings.html`, `bangles.html`, `bracelets.html`, `pendants.html`, `mangalsutra.html` | Category pages |
| `product.html?slug=…` | Product page: gallery with hover zoom, enquiry, wishlist |
| `about.html`, `craftsmanship.html` | Story, timeline, values, process |
| `journal.html`, `article.html?slug=…` | Journal with topic filter, and articles |
| `contact.html` | Contact details, visit card and message form |
| `404.html` | Not-found page |

Every page shares the announcement bar, header (with the Jewellery mega menu), footer, back-to-top button, and the menu, search, wishlist, bag, account and appointment panels.

## Files

```
assets/css/mangalam.css      the design system — colours, type, components, animations (edit directly)
assets/js/data.js            products, journal articles, testimonials — edit content here
assets/js/ui.js              icon set
assets/js/main.js            behaviour: loader, header, mega menu, dialogs, wishlist, search,
                             scroll animations, catalogue, product and article pages
assets/images/brand/         logo files extracted from the client's .ai artwork (full, horizontal,
                             emblem, wordmark, ornament) + PNG versions and the app icon
assets/images/               hero, craft and product photography, favicon
src/build.mjs                generates every .html page
src/partials/                layout, header, footer and dialogs shared by all pages
src/pages/                   the main content of each page
```

## Editing

The `.html` files in the project root are **generated**. Edit the sources in `src/`, then rebuild (requires Node.js, no install needed):

```
npm run build
```

- **Header, footer, menus, dialogs:** `src/partials/`
- **Page content:** `src/pages/` (`home.html` is `index.html`; `catalog.html` is used for all category pages)
- **Phone, email, address, hours, social links:** the `site` object at the top of `src/build.mjs`
- **Products, articles, testimonials:** `assets/js/data.js` — then rebuild so counts, the hero hotspots and the journal pages update
- **Colours and fonts:** the tokens at the top of `assets/css/mangalam.css`

The logo is applied with CSS masks (`.brand-logo`, `.brand-emblem`, `.brand-full`, `.ornament`), which gives it the animated metallic gold finish. It needs the site to be served over http(s) — as it is through XAMPP — rather than opened as a local file.

## Notes

- Forms (newsletter, account, appointment, enquiry, contact) are front-end only: they show a confirmation but do not send anything. Connect them to your mail or CRM service before going live.
- The wishlist is saved in the visitor's browser (localStorage).
- Phone number, email, address and social links are placeholders carried over from the original template.
- The product photographs had thin white borders and slivers of neighbouring images along some edges; these were cropped out.
## Scroll animations

Handled by `assets/js/main.js` (one scroll loop) and section 37 of `mangalam.css`. Hooks you can add to any element:

| Attribute | Effect |
| --- | --- |
| `data-reveal` | Fades up when scrolled into view. Variants: `left`, `right`, `zoom`, `fade`, `blur`, `rise`, and image curtains `mask`, `mask-down`, `mask-left`, `mask-right` |
| `data-stagger="0.1"` | On a parent: its `data-reveal` children follow one another by that many seconds |
| `data-parallax="0.1"` | On an image taller than its frame: drifts inside the frame |
| `data-float="0.15"` | Drifts at its own speed for depth (negative = opposite direction) |
| `data-expand` | On a section: opens from an inset rounded card to full width as it arrives |
| `data-count="200"` | Counts up to the number |

Headlines (`.section-title`, `.page-hero__title` …) animate word by word automatically, section eyebrows and the logo ornament draw themselves in, and icons in `.trust__icon` / `.value__icon` trace their outlines.

If a visitor's system asks for reduced motion (Windows: *Settings → Accessibility → Visual effects → Animation effects* turned off), the site keeps the gentle fades, curtains and counters and leaves out parallax, zooms, drifting layers and moving marquees.
