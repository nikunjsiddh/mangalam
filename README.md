# Mangalam Jewellers — website and admin

The website for Mangalam Jewellers, designed around the client's logo (antique gold `#A57E00`) with a midnight-wine and ivory palette, and the admin panel the team uses to run it. Everything the website shows — products and their photographs, categories, collections, the homepage, page banners, the journal, testimonials, the offer and announcements, contact details and settings — is kept in a MySQL database and edited in the admin. Enquiries, appointment requests and newsletter sign-ups from the website are saved there too.

Built with PHP 8 and MySQL/MariaDB, so it runs on XAMPP and on ordinary shared hosting.

- Website: **http://localhost/mangalam/**
- Admin: **http://localhost/mangalam/admin/**

## Installing

### On this computer (XAMPP)

The database `mangalam` is already installed here. The owner's sign-in is in `database/owner-credentials.txt` — sign in, change the password from **Your profile** (top right), then delete that file.

To install from scratch (a new computer, or to start again from the original content):

1. Start Apache and MySQL in the XAMPP control panel.
2. Delete `app/config.php` if it exists (this makes the installer replace everything in the database).
3. Open **http://localhost/mangalam/install.php**, keep the database details (XAMPP: user `root`, no password), choose the owner's email and password, and press *Install*.

Or from the command line: `php database/install-cli.php --owner-email=you@example.com --owner-password=…` (add `--no-samples` to leave out the sample customers, `--force` to replace an existing installation).

### On hosting

1. Upload the project folder (not the `.zip` photo deliveries or the raw photo folders).
2. Create a MySQL database and user in the hosting control panel.
3. Open `https://your-domain/install.php` and enter that database's details. Untick the sample data for a live site.
4. In the admin, **Settings › Notifications**, turn on *Send emails from the website* once the host can send mail.
5. In `app/config.php`, set `'debug' => false`.

The installer switches itself off once the site is installed.

## What the installer imports

`database/seed.json` holds the website's content as it was before the database existed (read from the old `assets/js/data.js`, `src/build.mjs` and the page templates), and `database/schema.sql` the tables. The installer imports:

| | |
| --- | --- |
| Catalogue | 46 products with their 94 photographs, prices, metals, purity, style, collection line and New / Featured labels; 8 categories with their menu images, banners, headings and introductions; 6 collections and their rules; the 4 shop-the-look pins |
| Website | 6 journal stories, 3 testimonials, the heading, introduction, banner and search listing of every page, the homepage hero, figures, bridal panels and section order, the offer popup and the announcement bar, contact details, opening hours and social links |
| Media library | Every photograph and logo in `assets/images/` (130 files) |
| Team | The owner's account, and the role permissions from the design |
| Sample data (optional) | The enquiries, appointments, subscribers and colleagues shown in the admin design — for seeing the screens full; delete them before going live |

## The admin

Sign in at `admin/login.html`. Each screen saves straight to the database; the website shows the change on the next page load.

| Screen | What it manages |
| --- | --- |
| Dashboard | Enquiries per week, upcoming appointments, pieces on the website, subscribers, and what needs attention — all counted from the database (choose 7 days, 30 days, 90 days or 12 months) |
| Products | Add, edit, duplicate, hide, publish, move and delete pieces (one at a time or ticked together), upload and reorder photos (drag to reorder; the first is the main photo, the second shows on hover), price and offer price, specifications, HUID, search listing, publish date. Export to CSV |
| Categories | Add, edit, reorder (drag), show or hide in the menu, menu image and page banner, page heading and introduction, delete |
| Collections | Add, edit, reorder, show on the homepage; each gathers its pieces by a rule (category, metal or style) |
| Homepage | Section order and visibility, hero photograph, headline, introduction and figures, shop-the-look pins (drag onto the photo), the featured pieces (Signature tab) and the four bridal panels. Changes go live when you press *Publish changes* |
| Pages & banners | Banner, heading, introduction and search listing of every page and category page |
| Journal | Write, edit, schedule, publish, unpublish, duplicate and delete stories, with a cover photograph and photos in the text |
| Testimonials | Add, edit, reorder, show on the homepage, delete |
| Media library | Upload into Campaign, Products, Brand or Other; alt text; see where each image is used; delete images nothing uses |
| Enquiries | Messages from product pages and the contact form: reply, change status, archive, delete, book an appointment. Export to CSV |
| Appointments | Month calendar and the next two weeks; requests from the website arrive as *Pending*; book, confirm, complete, cancel or delete |
| Subscribers | Newsletter sign-ups from the footer: add, unsubscribe, remove, export to CSV |
| Offers & announcements | The offer popup's wording, discount, link, dates and timing, and the announcement bar's messages |
| Settings | Store details, contact and opening hours, social links, the homepage's search listing, email notifications, motion switches, maintenance mode |
| Users & roles | Invite colleagues (you get a link to send them), change roles, make password-reset links, remove people, and choose what each role may do |

Roles: the **Owner** can do everything; **Manager**, **Editor** and **Sales** can do what is ticked for them in *Users & roles › Roles & permissions*. People only see the screens their role allows.

Headings use a small mark-up: put `*stars*` around words for the gold italics, and `|` where a new line starts — for example `Three generations | of *trust.*`.

**Photos.** Product photographs should be square, 1200 × 1200 px; each gets a `-sm` copy for cards and lists. If PHP's GD extension is on, large photographs are resized automatically; on this XAMPP it is off (in `C:\xampp2\php\php.ini`, remove the `;` in front of `extension=gd` and restart Apache to turn it on), so upload photographs at web size.

**Email.** Emails (notifications to the team, replies to enquiries, invitations) are sent only when *Settings › Notifications › Send emails from the website* is on. Until then everything is still saved in the admin, and replies can be sent with *Open in email*.

## Files

```
index.php                 every page of the website (/, /rings.html, /product.html?slug=…) and /data.js
api.php                   the website's forms: enquiries, the contact form, appointment requests, the newsletter
install.php               the installer (switches itself off once installed)
.htaccess                 sends the .html addresses to index.php; keeps app/ and database/ private
admin/index.php           every admin screen (admin/*.html)
admin/api.php             the admin's saves, deletes, uploads and CSV exports
app/config.php            this computer's database details (not in git; made by the installer)
app/bootstrap.php         loaded first by every page
app/lib/                  database, templates and icons, content queries, sign-in and permissions, uploads
app/site/render.php       builds the website's pages and data.js from the database
app/admin/                the admin's screens (pages.php), actions (actions.php) and small pieces (ui.php)
app/views/site/           the website's templates: partials/ (layout, header, footer, dialogs, offer), pages/
app/views/admin/          the admin's templates: layouts, partials/, pages/, and its icons
database/schema.sql       the tables
database/seed.json        the website's original content, imported by the installer
database/installer.php    the installation itself (used by install.php and install-cli.php)
assets/css/mangalam.css   the website's design system — colours, type, components, animations
assets/css/admin.css      the admin's styles
assets/js/main.js         the website's behaviour: loader, menus, dialogs, wishlist, search, scroll animations,
                          and the catalogue, product, journal and story pages (drawn from data.js)
assets/js/admin.js        the admin's behaviour: lists, dialogs, drag to reorder, uploads, editors, charts,
                          and sending changes to admin/api.php
assets/js/ui.js           the icon set (shared by the browser and the PHP templates)
assets/images/            brand/ (logo files), campaign/ (the shoot), products/ (each piece, with -sm copies),
                          uploads/ (images uploaded to the Other folder), icons/ (home-screen and install icons)
favicon.ico, assets/images/favicon.svg, site.webmanifest
                          the browser-tab icon (the emblem thickened and enlarged so it reads at 16px, with a gold
                          rim for dark tab bars), the iPhone home-screen icon (brand/apple-touch-icon.png) and the
                          Android / install icons in icons/, all made from brand/mangalam-emblem.svg
```

The templates use `{{name}}` for a value, `{{icon:name}}` for an icon and `{{> partial}}` for another template. Page design and fixed wording are edited in `app/views/`; anything the team changes often is in the admin.

### Database tables

`products`, `product_images`, `categories`, `collections`, `hotspots` (shop-the-look pins), `pages`, `articles`, `testimonials`, `media`, `settings` (contact details, hours, offer, announcements, homepage hero and sections, switches — JSON values), `enquiries`, `enquiry_replies`, `appointments`, `subscribers`, `users`, `role_permissions`.

## Offer popup

On a visitor's first page the offer opens by itself (1.5 seconds after the page has loaded, unless changed in the admin). A gold line along its foot counts down 7 seconds (it pauses while the pointer is over the offer), then the offer folds into the small "30% off" tab at the middle of the right edge. The tab stays on every page; clicking it opens the offer again, and then it stays open until closed. The offer opens by itself only once per visit — to see it again while testing, open the site in a new browser tab. The wording, discount, link, dates and timing are in the admin under **Offers & announcements**.

The logo is applied with CSS masks (`.brand-logo`, `.brand-emblem`, `.brand-full`, `.ornament`), which gives it the animated metallic gold finish. It needs the site to be served over http(s) — as it is through XAMPP — rather than opened as a local file.

## Notes

- The website's account and bag panels are design only; there are no customer accounts or online payments.
- The wishlist is saved in the visitor's browser (localStorage).
- Phone number, email, address and social links are placeholders carried over from the original template — change them in **Settings**.
- The photographs come from the client's two shoots. The campaign photos were converted from Adobe RGB to sRGB so their colour holds in every browser; the product photos arrived straight from the camera and were given levels and a midtone lift (several were very dark). Only the mangalsutra pieces and the atelier (`mangalam-craft.jpg`) still use the earlier artwork — neither was part of the shoots.

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

Every photograph gets the rising curtain and the drift automatically — product cards, category arches, collection and journal cards, arch frames, bridal panels, Instagram tiles and more (the lists are `CURTAINS` and `PARALLAX` in `main.js`; the closer-look stage and the product gallery reveal but stay still, so the magnifier and zoom line up). The home and inner-page heroes open in five bands once the page has loaded. On desktop, mouse-wheel and trackpad scrolling glides smoothly; touch screens, the keyboard and the scrollbar keep native scrolling. The loading screen, smooth scrolling and the calmer motion below can be switched in the admin under **Settings › Motion & brand**.

The site animates for every visitor. Windows tells browsers to "reduce motion" whenever *Settings → Accessibility → Visual effects → Animation effects* is off (the *Adjust for best performance* option switches it off too), which used to freeze the announcement bar and most effects on such PCs. To give those visitors a calmer site, switch on *A calmer website for visitors whose computer asks for reduced motion* in **Settings › Motion & brand**: they then keep the fades, curtains and counters and lose smooth scrolling, parallax, zooms, shutters, drifting layers and moving marquees.
