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
- **Offer popup:** the wording is in `src/partials/offer.html`; the percentage and the button's link are `offerPercent` / `offerHref` in the `site` object of `src/build.mjs`; the timing is `OFFER_DELAY` / `OFFER_SECONDS` in `assets/js/main.js`

## Offer popup

On a visitor's first page the offer opens by itself 1.5 seconds after the page has loaded. A gold line along its foot counts down 7 seconds (it pauses while the pointer is over the offer), then the offer folds into the small "30% off" tab at the middle of the right edge. The tab stays on every page; clicking it opens the offer again, and then it stays open until closed. The offer opens by itself only once per visit — to see it again while testing, open the site in a new browser tab.

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
| `data-reveal` | Fades up when scrolled into view. Variants: `left`, `right`, `zoom`, `fade`, `blur`, `rise`; image curtains `mask` (rises), `mask-down`, `mask-left`, `mask-right` — the photo frame wipes open while the photo settles from a zoom; and `shutter` — the frame opens in vertical bands (set `--bands` on the element to change their number, default 4) |
| `data-stagger="0.1"` | On a parent: its `data-reveal` children follow one another by that many seconds |
| `data-parallax` | On an image inside a frame that hides its overflow: drifts up and down inside the frame as the page scrolls. An optional value scales the drift (`0.5` = half) |
| `data-float="0.15"` | Drifts at its own speed for depth (negative = opposite direction) |
| `data-expand` | On a section: opens from an inset rounded card to full width as it arrives |
| `data-count="200"` | Counts up to the number |

Headlines (`.section-title`, `.page-hero__title` …) animate word by word automatically, section eyebrows and the logo ornament draw themselves in, and icons in `.trust__icon` / `.value__icon` trace their outlines.

Every photograph gets the rising curtain and the drift automatically — product cards, category arches, collection and journal cards, arch frames, bridal panels, Instagram tiles and more (the lists are `CURTAINS` and `PARALLAX` in `main.js`; the closer-look stage and the product gallery reveal but stay still, so the magnifier and zoom line up). The home and inner-page heroes open in five bands once the page has loaded. On desktop, mouse-wheel and trackpad scrolling glides smoothly; touch screens, the keyboard and the scrollbar keep native scrolling.

The site animates for every visitor. Windows tells browsers to "reduce motion" whenever *Settings → Accessibility → Visual effects → Animation effects* is off (the *Adjust for best performance* option switches it off too), which used to freeze the announcement bar and most effects on such PCs. To give those visitors a calmer site again, set `CALM_FOR_REDUCED_MOTION` to `true` at the top of `assets/js/main.js`: they then keep the fades, curtains and counters and lose smooth scrolling, parallax, zooms, shutters, drifting layers and moving marquees.
