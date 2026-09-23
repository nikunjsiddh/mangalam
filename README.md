# Mangalam Jewellers — HTML template

Static HTML/CSS/JS version of the Lovable project “Remix of Gilded Narrative” (Mangalam Jewellers). Every page, section, image and interaction of the original React site is reproduced as plain HTML.

Open it through XAMPP: **http://localhost/mangalam/**

## Pages

| File | Original route |
| --- | --- |
| `index.html` | `/` — hero, collections, signature pieces, featured, promise, bridal edit, craftsmanship, heritage, closer look, manifesto, testimonials, journal, Instagram |
| `collections.html` | `/collections` |
| `jewellery.html` | `/jewellery` (supports `?metal=Gold`, `?metal=Diamond`, `?style=Traditional` …) |
| `bridal.html`, `rings.html`, `necklaces.html`, `earrings.html`, `bangles.html`, `bracelets.html`, `pendants.html`, `mangalsutra.html` | category pages with filters and sorting |
| `product.html?slug=…` | `/product/$slug` |
| `about.html`, `craftsmanship.html` | `/about`, `/craftsmanship` |
| `journal.html`, `article.html?slug=…` | `/journal`, `/journal/$slug` |
| `contact.html` | `/contact` |
| `404.html` | not-found page |

Every page includes the announcement bar, header, footer, newsletter, and the menu, search, wishlist, bag, account and appointment panels.

## Files

```
assets/css/style.css   compiled stylesheet (Tailwind CSS v4 output, do not edit by hand)
assets/js/data.js      products, journal articles, testimonials: edit content here
assets/js/ui.js        icons and button/input class recipes
assets/js/main.js      behaviour: loader, header, dialogs, search, filters, product & article pages
assets/images/         hero, craft and product photography + favicon
src/tailwind.css       stylesheet source (theme colours, fonts, animations)
```

## Editing

- **Text and layout:** edit the `.html` files directly. The header and footer are repeated in each page.
- **Products and articles:** edit `assets/js/data.js`. The catalogue, product pages, search and journal update automatically.
- **New Tailwind classes:** if you add classes that are not already used somewhere, rebuild the CSS:

  ```
  npm install
  npm run build:css
  ```

## Differences from the original

Three bugs in the original were fixed:

1. Between 1024px and 1279px wide, the original shows neither the navigation links nor the menu button. Here the menu button stays visible until the full navigation appears.
2. `/journal/<article>` in the original shows the journal list instead of the article. `article.html` shows the article itself.
3. The newsletter email field in the footer had ivory text on an ivory background. The typed text is now dark.

Forms (newsletter, account, appointment, enquiry, contact) are front-end only, like the original: they show their confirmation state but do not send anything.
