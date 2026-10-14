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

`database/seed.json` holds the website's content as it was before the database existed (read from the old `assets/js/data.js`, `src/build.mjs` and the page templates), with the catalogue as corrected from the product shoot in October 2026, and `database/schema.sql` the tables. The installer imports:

| | |
| --- | --- |
| Catalogue | 49 pieces with their 120 photographs, prices, metals, purity, style, collection line, publish status and New / Featured labels; 9 categories with their menu images, banners, headings and introductions; 6 collections and their rules; the 4 shop-the-look pins |
| Website | 6 journal stories, 3 testimonials, the heading, introduction, banner and search listing of every page, the homepage hero, figures, bridal panels and section order, the offer popup and the announcement bar, 5 category offers (bridal, necklaces, earrings, bangles, rings — sample wording and discounts to edit before launch), contact details, opening hours and social links |
| Media library | Every photograph and logo in `assets/images/` (182 files) |
| Team | The owner's account, and the role permissions from the design |
| Sample data (optional) | The enquiries, appointments, subscribers and colleagues shown in the admin design — for seeing the screens full; delete them before going live |

## The admin

Sign in at `admin/login.html`. Each screen saves straight to the database; the website shows the change on the next page load.

| Screen | What it manages |
| --- | --- |
| Dashboard | Enquiries per week, upcoming appointments, pieces on the website, subscribers, and what needs attention — all counted from the database (choose 7 days, 30 days, 90 days or 12 months) |
| Products | Add, edit, duplicate, hide, publish, move and delete pieces (one at a time or ticked together), upload and reorder photos (drag to reorder; the first is the main photo, the second shows on hover), price and offer price, specifications, HUID, search listing, publish date, and the 3D view & customiser (below). Export to CSV |
| Categories | Add, edit, reorder (drag), show or hide in the menu, menu image and page banner, page heading and introduction, delete |
| 3D jewellery | Every piece with a 3D view in one place — rings, kangans & bangles, earrings and pendants — filtered by kind and by whether it is on the website. Add a 3D view to any product, edit it beside the product photo with a live preview, switch it on or off, or remove it (see *3D view & customiser* below) |
| Collections | Add, edit, reorder, show on the homepage; each gathers its pieces by a rule (category, metal or style) |
| Homepage | Section order and visibility, hero photograph, headline, introduction and figures, shop-the-look pins (drag onto the photo), the signature pieces (they lead the homepage showcase, followed by every other piece, with a tab per category and New in) and the four bridal panels. Changes go live when you press *Publish changes* |
| Pages & banners | Banner, heading, introduction and search listing of every page and category page |
| Journal | Write, edit, schedule, publish, unpublish, duplicate and delete stories, with a cover photograph and photos in the text |
| Testimonials | Add, edit, reorder, show on the homepage, delete |
| Media library | Upload into Campaign, Products, Brand or Other; alt text; see where each image is used; delete images nothing uses |
| Enquiries | Every conversation with customers (see *Conversations* below): enquiries from product pages and the contact form, customers' email replies and messages from their conversation page, and messages the team starts with *New message*. Answer by email (in the house style, from a template or freely, with a preview), by WhatsApp, or add a note for the team; change status, edit contact details, copy the customer's conversation link, book an appointment, archive, delete. Export to CSV |
| Email templates | The words of every email customers receive: the three sent automatically (enquiry received, appointment requested, appointment confirmed — each can be switched off) and the reply templates the team chooses from in Enquiries. Fields such as `{first_name}` and `{product}` fill in for each customer; a live preview shows the email as it will arrive; *Send me a test* emails it to you |
| Appointments | Month calendar and the next two weeks; requests from the website arrive as *Pending*; book, confirm, complete, cancel or delete |
| Subscribers | Newsletter sign-ups from the footer: add, unsubscribe, remove, export to CSV |
| Offers page | Everything on /offers.html, in two tabs. **Offers:** for each offer, its category (or all jewellery), eyebrow, title, discount (“Up to” or “Flat”, and what it is off), message, small print, button, dates, and a gallery of photographs (drag to reorder; the first is the cover) — optionally followed by the category's pieces; switch on or off, drag to reorder (the first is the feature), delete. **Page content:** the heading, introduction, banner and figures at the top; the category buttons and their first label; how many pieces show under each offer; the feature card, countdown and *Book a viewing* switches; the *How the offers work* steps (up to four, each with an icon, title and text); the closing band's heading, text and buttons; the message for when no offer is live; and the search listing. Each section can be switched off |
| Offers & announcements | The offer popup's wording, discount, link, dates and timing, and the announcement bar's messages |
| Settings | Store details and the website address used in emails, contact and opening hours, social links, the homepage's search listing, email notifications and reading customers' replies, motion switches, maintenance mode |
| Users & roles | Invite colleagues (you get a link to send them), change roles, make password-reset links, remove people, and choose what each role may do |

Roles: the **Owner** can do everything; **Manager**, **Editor** and **Sales** can do what is ticked for them in *Users & roles › Roles & permissions*. People only see the screens their role allows.

Headings use a small mark-up: put `*stars*` around words for the gold italics, and `|` where a new line starts — for example `Three generations | of *trust.*`.

**Photos.** Upload photographs as they are, straight from the camera or phone. The admin scales each one to web size in the browser before it is sent (products 1200 px on the longer side, campaign 2400 px, other 2000 px), so even a 20 MB original uploads in a moment. The server then compresses it again and makes, beside it:

- a small `-sm` copy for cards, menus and thumbnails (products 600 px, campaign and other photographs 800 px);
- light **AVIF** and **WebP** copies of both (`ring.jpg.avif`, `ring.jpg.webp`), each kept only when it is clearly lighter.

Pages keep naming the `.jpg`; `.htaccess` hands each browser the lightest copy it can show at that same address (AVIF, then WebP, then the JPEG for the rest), so nothing in the database changes. The toast after an upload says how much lighter the photo became, and **Media library** shows what visitors download. Photographs that have no copies yet — uploaded before this, or copied in by hand — are listed by the **Optimise** button there, which makes them in batches; from the command line, `php database/optimise-images.php` does the same for every photograph. All of this needs PHP's GD extension (it is on in this XAMPP; `extension=gd` in `C:\xampp2\php\php.ini`, then restart Apache). Without it, photos are still scaled in the browser but get no small or AVIF / WebP copies.

In October 2026 every photograph got its copies: the 338 photo files (originals and small copies) went from 51.8 MB as JPEG to 19.1 MB as visitors download them, and the homepage's images from 13.8 MB to 2.9 MB.

**Email.** Emails (notifications to the team, the customers' emails, invitations) are sent only when *Settings › Notifications › Send emails from the website* is on, and every one goes out in the house style — midnight wine, gold and ivory — with a plain-text copy. Until then everything is still saved in the admin, and replies stay readable on the customer's conversation page.

## Files

```
index.php                 every page of the website (/, /rings.html, /product.html?slug=…) and /data.js
api.php                   the website's forms: enquiries, the contact form, a customer writing on their conversation page,
                          appointment requests, the newsletter
install.php               the installer (switches itself off once installed)
.htaccess                 sends the .html addresses to index.php; keeps app/ and database/ private; serves the AVIF / WebP
                          copies of photographs; lets browsers keep images and versioned styles and scripts
admin/index.php           every admin screen (admin/*.html)
admin/api.php             the admin's saves, deletes, uploads and CSV exports
app/config.php            this computer's database details (not in git; made by the installer)
app/bootstrap.php         loaded first by every page
app/lib/                  database, templates and icons, content queries, sign-in and permissions, uploads; mail.php (sending,
                          the house email layout, reading replies by IMAP) and conversations.php (enquiries as conversations, templates)
app/site/render.php       builds the website's pages and data.js from the database
app/admin/                the admin's screens (pages.php), actions (actions.php) and small pieces (ui.php)
app/views/site/           the website's templates: partials/ (layout, header, footer, dialogs, offer), pages/
app/views/admin/          the admin's templates: layouts, partials/, pages/, and its icons
database/schema.sql       the tables
database/seed.json        the website's original content, imported by the installer
database/updates/         changes for a database that is already installed (run once on the live site; see below)
database/installer.php    the installation itself (used by install.php and install-cli.php)
database/optimise-images.php  makes the small and AVIF / WebP copies of every photograph (command line)
database/fetch-mail.php   reads customers' email replies into Enquiries (command line, for a cron job)
assets/css/mangalam.css   the website's design system — colours, type, components, animations
assets/css/admin.css      the admin's styles
assets/js/main.js         the website's behaviour: loader, menus, dialogs, wishlist, search, scroll animations,
                          and the catalogue, product, journal and story pages (drawn from data.js)
assets/js/admin.js        the admin's behaviour: lists, dialogs, drag to reorder, uploads, editors, charts,
                          and sending changes to admin/api.php
assets/js/ui.js           the icon set (shared by the browser and the PHP templates)
assets/js/viewer3d.js     the 3D jewel viewer (three.js): the house designs (rings, kangans & bangles, earrings,
                          pendants), uploaded .glb models, metals, stones and effects — shared by the two files below
assets/js/product3d.js    "View in 3D & customise" on product pages (loaded only when a visitor opens it)
assets/js/admin-3d.js     the 3D editor (partials/view3d-editor.html), in the product editor and Catalogue › 3D
                          jewellery: live preview beside the photo, matching, visitor choices, .glb upload
assets/models/            3D models (.glb) uploaded in the product editor
demo/jewellery-3d/        a stand-alone scroll-story showcase of the 3D ring studio (/demo/jewellery-3d/)
assets/images/            brand/ (logo files), campaign/ (the shoot), products/ (each piece, with -sm copies),
                          uploads/ (images uploaded to the Other folder), icons/ (home-screen and install icons)
favicon.ico, assets/images/favicon.svg, site.webmanifest
                          the browser-tab icon (the emblem thickened and enlarged so it reads at 16px, with a gold
                          rim for dark tab bars), the iPhone home-screen icon (brand/apple-touch-icon.png) and the
                          Android / install icons in icons/, all made from brand/mangalam-emblem.svg
```

The templates use `{{name}}` for a value, `{{icon:name}}` for an icon and `{{> partial}}` for another template. Page design and fixed wording are edited in `app/views/`; anything the team changes often is in the admin.

### Database tables

`products` (`view3d` holds the 3D view settings as JSON), `product_images`, `categories`, `collections`, `offers` (category offers; `images` is a JSON list), `hotspots` (shop-the-look pins), `pages`, `articles`, `testimonials`, `media`, `settings` (contact details, hours, offer, announcements, homepage hero and sections, switches — JSON values), `enquiries` (each a conversation; `token` is the customer's private link), `enquiry_replies` (every later message, either way: `direction`, `channel` — email, WhatsApp, conversation page, phone, note — and `seen_at`), `message_templates`, `appointments`, `subscribers`, `users`, `role_permissions`.

## Conversations with customers

Every enquiry is a conversation that both sides can carry on:

- **The customer** gets an automatic email in the house style as soon as they enquire (*Email templates › Enquiry received*), and a private **conversation page** (`conversation.html?t=…`, linked from every email and shown after they send the form). There they read everything the team has written to them — by email or WhatsApp — and write again; notes and call notes stay with the team. They can also simply **reply to any email**.
- **The team** answers in *Enquiries*: **Email** (subject, an optional large heading with `*gold italics*`, the text, optionally the piece as a card; *Preview* shows it as it will arrive), **WhatsApp** (opens WhatsApp Web or the app with the message written, and keeps a copy in the conversation), or **Note** (for the team — or what the customer said on WhatsApp or the phone). *Templates…* fills in a reply from *Email templates*. Each conversation shows the customer's other conversations, appointments and newsletter status, when they last opened their page (*Seen*), and whether each email was sent. *New message* starts a conversation with anyone, by email or WhatsApp.
- **Replies by email.** With *Settings › Notifications › Replies by email* on, the website reads the mailbox (IMAP — no PHP extension needed) every few minutes while the admin is open, when *Check for replies* is pressed, or from a cron job running `php database/fetch-mail.php`. A reply joins its conversation by the email it answers, or by the reference in its subject (`[MJ-0012]`, only from the customer's own address); the quoted earlier messages and signatures are trimmed off. Newsletters, automatic replies and the store's own emails are left out, and nothing in the mailbox is changed. Optionally, other emails from customers start new conversations. For Gmail: switch on IMAP in Gmail's settings, and use imap.gmail.com, SSL 993 and the same App Password as for sending.
- A customer writing again (by email or on their page) marks the conversation *New*, with a *Wrote again* tag and a line in the bell; the team is emailed too (*A new enquiry arrives*).
- On the live site, set **Settings › General › Website address**, so links and photographs in emails point to it. On this computer emails show the logo in type rather than as a picture, because a mailbox cannot reach `localhost`.

The database is updated by itself the first time the admin (or a website form) is used after uploading the code (`upgrade_schema()` in `app/lib/db.php`): the conversation columns, the `message_templates` table with ten starting templates, and the *Edit email templates* permission (given to Managers).

## 3D view & customiser

In **Catalogue › 3D jewellery** (or **Products › edit a piece › 3D view & customiser**, the same editor) the team can show a piece in 3D on its page — a ring, a kangan or bangle, a pair of earrings or a pendant on its chain. Visitors turn it, zoom in, try what the team allows — other metals, stones and styles, and their size — save a picture, and send an enquiry that names the design they chose ("… in white gold with ruby, in the diamond kangan style, as I designed it in the 3D view. My bangle size is 2.6."). Pieces with a 3D view carry a small “3D” label in the catalogue.

| Kind | House designs | Visitors can be asked for |
| --- | --- | --- |
| Ring | Solitaire, Halo, Trilogy, Eternity, Plain band | Ring size (6–24) |
| Kangan & bangle | Plain kada, Diamond kangan, Kundan kada, Twisted rope, Open cuff — one bangle or a pair side by side | Bangle size (2.2–2.10) |
| Earrings (shown as a pair) | Solitaire studs, Halo studs, Drops, Hoops, Jhumkas (with hanging pearls) | — |
| Pendant (on a fine chain) | Solitaire, Halo, Open heart, Drop | Chain length (16–22 in) |

- **What visitors can customise** is chosen per piece: which metals and stones they may try (the ones shown first are always included), which other styles of the same kind (*Other styles to try*), and whether they are asked for their size, which goes into their enquiry ("I will be measured" is always offered).
- **Adding a piece:** *Add 3D piece* lists the products without a 3D view; choosing one guesses its kind from the category (bangles and bracelets → kangan, necklaces and pendants → pendant …) and its style from words in its name ("kundan", "cuff", "jhumka", "hoop", "heart"…; a name with "kadas" or "pair" shows a pair). The lists are `VIEW3D_GUESS` in `app/lib/content.php`.

- **A house design** is drawn in code, with sliders for the stone size and the width (band, kangan) or size (earrings, pendant), and 4 or 6 claws where a stone is claw set. The 3D preview sits beside the main photo so the team can match the two by eye. *Match from photo & details* fills in the kind from the category and the style from the name ("halo", "kundan", "jhumka"…), the metal and stone from the Metal and Main stone fields, and uses the photo for what those leave open (white or yellow metal for a diamond piece, the colour of a mixed stone).
- **My 3D model (.glb)** shows the exact piece. Ask the CAD designer (Matrix, RhinoGold, Blender…) for a binary glTF 2.0 export up to 30 MB, with the metal and the stones as separate materials: metallic materials become the metal and glass-like (transmissive) ones, or materials named diamond / gem / stone / ruby…, become the stones, so visitors can change both. A model made by an AI photo-to-3D service also uploads, but it usually comes as one textured piece, so its metal and stones cannot be changed.
- A normal photograph cannot be turned into an accurate 3D model automatically; that is why a design is matched to the photo, or a model is uploaded.

The viewer loads three.js from cdn.jsdelivr.net (with integrity checks) only when a visitor opens it, so it needs an internet connection. The `view3d` column is added to an existing database the first time the admin is opened (`upgrade_schema()` in `app/lib/db.php`).

## Offer popup

On a visitor's first page the offer opens by itself (1.5 seconds after the page has loaded, unless changed in the admin). A gold line along its foot counts down 7 seconds (it pauses while the pointer is over the offer), then the offer folds into the small "30% off" tab at the middle of the right edge. The tab stays on every page; clicking it opens the offer again, and then it stays open until closed. The offer opens by itself only once per visit — to see it again while testing, open the site in a new browser tab. The wording, discount, link, dates and timing are in the admin under **Offers & announcements**.

## Offers page and category offers

**/offers.html** shows each live category offer (switched on, started, and not past its last day) as its own showcase: a mosaic of its photographs with a turning gold seal for the discount, the category, title, the big discount figure, a live countdown to the offer's last day (it runs to the end of that day, Indian time), the button to the category's page, a few of the category's pieces, and the small print. The first offer is the feature, in wine and gold; the rest alternate sides. Chips at the top show one category's offer at a time (`offers.html?category=rings` opens on it).

Clicking any photograph opens the **gallery**: the offer's photographs and then (when *Also show the category's pieces* is on) the main photo of each of the category's pieces, with its name, price and a *View piece* link. Arrow keys, the on-screen arrows, the thumbnails and swiping on a phone move through it; Esc or a click on the dark space closes it.

While an offer is live, its category's page shows a ribbon above the pieces linking to it, *All jewellery* shows a ribbon to every offer, and *Offers* appears in the menus. The popup offer's button can open the Offers page (**Offers & announcements › Button opens**). The whole page is edited in the admin under **Offers page**: the offers on its first tab, and everything around them on **Page content** (kept in the `offers_page` setting, with the page's heading, banner and search listing in its `pages` row — the defaults are `OFFERS_PAGE_DEFAULTS` in `app/lib/content.php`). Behaviour is in `assets/js/main.js` (Offers section) and styles in section 31b of `mangalam.css`.

The logo is applied with CSS masks (`.brand-logo`, `.brand-emblem`, `.brand-full`, `.ornament`), which gives it the animated metallic gold finish. It needs the site to be served over http(s) — as it is through XAMPP — rather than opened as a local file.

## Updating the live site (catalogue, October 2026)

The live database already has its own enquiries, appointments and team, so it is updated rather than reinstalled:

1. Upload `assets/images/` (with the `-sm`, `.avif` and `.webp` copies beside the photographs), `.htaccess` and the changed code (`app/`, `assets/css/`, `assets/js/`, `database/`). Then open **Media library** in the live admin: if it offers **Optimise**, press it to make copies for photographs that were uploaded on the live site.
2. In Hostinger › phpMyAdmin, open the website's database and import `database/updates/2026-10-08-catalogue.sql`.

The update finds every piece by its web address, so it is safe to run more than once; it leaves enquiries, appointments, subscribers, users, prices and weights as they are.

**Offers page (10 October 2026).** Upload the changed code, then import `database/updates/2026-10-10-offers.sql` (or simply open the admin once — it makes the same change). It adds the offers table and the Offers page's heading and banner, but no offers: the *Offers* menu link and the category ribbons appear once the first offer is published under **Offers page** (until then the page says new offers are on their way). The rest of the page shows its default wording until it is changed on **Page content**.

## Notes

- The website's account and bag panels are design only; there are no customer accounts or online payments.
- The wishlist is saved in the visitor's browser (localStorage).
- Phone number, email, address and social links are placeholders carried over from the original template — change them in **Settings**.
- The photographs come from the client's shoots. The campaign photos were converted from Adobe RGB to sRGB so their colour holds in every browser; the product photos arrived straight from the camera and were given levels and a midtone lift (several were very dark). Only the atelier (`mangalam-craft.jpg`) still uses the earlier artwork — it was not part of the shoots.
- Each piece shows only its own photographs, the first as the main photo and the second on hover. The Rajwadi bridal set, the peacock kadas and the rose-gold diamond drop necklace were filmed but not photographed, so their photos are sharp frames taken from the shoot videos (1080 px, enlarged to 1200).
- **Shop by metal.** Silver (like gold and diamond) is a metal, not a category: silver pieces sit in their own category (a silver chain under Chains, a silver coin watch under Watches) with *Metal* set to Silver and *Purity* 999 or 925. *Jewellery › Shop by metal › Silver jewellery* (`jewellery.html?metal=Silver`) lists them all with its own heading; Gold and Diamond work the same way.
- Pieces with *Show price* off read “Price on request” and are left out of the price filters. The pieces added from the October shoot start that way; the prices and weights of the earlier pieces are placeholders from the original template — enter the real ones in **Products** before launch.

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

Every photograph gets the rising curtain and the drift automatically — product cards, category arches, collection and journal cards, arch frames, bridal panels, Instagram tiles and more (the lists are `CURTAINS` and `PARALLAX` in `main.js`; the closer-look stage and the product gallery reveal but stay still, so the magnifier and zoom line up). The home and inner-page heroes open in five bands once the page has loaded. On desktop, mouse-wheel and trackpad scrolling glides smoothly; touch screens, the keyboard and the scrollbar keep native scrolling. The loading screen, smooth scrolling and the calmer motion below can be switched in the admin under **Settings › Motion & brand**. The loading screen shows only on the first page of a visit and lifts as soon as the first photograph is ready (at least 0.9 s for the logo to draw in, at most 2 s); it no longer waits for every image on the page.

The site animates for every visitor. Windows tells browsers to "reduce motion" whenever *Settings → Accessibility → Visual effects → Animation effects* is off (the *Adjust for best performance* option switches it off too), which used to freeze the announcement bar and most effects on such PCs. To give those visitors a calmer site, switch on *A calmer website for visitors whose computer asks for reduced motion* in **Settings › Motion & brand**: they then keep the fades, curtains and counters and lose smooth scrolling, parallax, zooms, shutters, drifting layers and moving marquees.
