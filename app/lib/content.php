<?php
/* Reading the catalogue, journal and settings from the database, in the shapes the website and admin use. */

const METALS = ['Gold', 'Diamond', 'Rose gold', 'Platinum', 'Silver'];
// The introduction on Shop by metal pages (jewellery.html?metal=…)
const METAL_LEADS = [
    'Gold' => 'Gold jewellery handcrafted in our Surat atelier — heritage, bridal and everyday pieces, every one BIS hallmarked.',
    'Diamond' => 'Diamond jewellery set by hand — necklaces, earrings, bangles and bracelets that catch the light.',
    'Rose gold' => 'Rose gold pieces with a soft, warm glow.',
    'Platinum' => 'Platinum pieces, cool and enduring.',
    'Silver' => 'Silver jewellery finished with the same care as our gold — fine 999 and sterling 925 silver pieces.',
];
// Gold in carats; silver in parts per thousand (999 fine, 925 sterling)
const PURITIES = ['24K', '22K', '18K', '14K', '999', '925'];
const STONES = ['None', 'Diamond', 'Polki', 'Kundan', 'Ruby', 'Emerald', 'Pearl', 'Mixed'];
const STYLES = ['Classic', 'Traditional', 'Modern', 'Bridal'];
const LINES = ['Mangalam Signature', 'The Bridal Edit', 'Sacred Bonds'];
const TOPICS = ['Bridal', 'Guide', 'Heritage', 'Style'];
const INTERESTS = ['Bridal jewellery', 'Gold jewellery', 'Diamond jewellery', 'Bespoke commission'];
const ENQUIRY_TOPICS = ['General enquiry', 'Bridal consultation', 'Bespoke commission', 'Care & repairs'];
const SUBSCRIBER_SOURCES = ['Website footer', 'Offer popup', 'In store'];
const HOME_SECTIONS = [
    // key => [name, description, icon, where it is edited]
    'hero' => ['Hero', 'Campaign photograph, headline and shop-the-look pins', 'image', '#hero-editor'],
    'trust' => ['Promise strip', 'Hallmarked, handmade, insured delivery, consultations', 'shield-check', ''],
    'categories' => ['Shop by category', '', 'layout-grid', 'categories.html'],
    'collections' => ['Collections', '', 'layers', 'collections.html'],
    'featured' => ['Signature showcase', 'Signature pieces first, then every piece, by category', 'gem', '#featured-editor'],
    'promise' => ['The Mangalam promise', 'Portrait, seal and four counters', 'award', ''],
    'bridal' => ['The bridal edit', 'Four expanding bridal panels', 'sparkles', '#bridal-editor'],
    'craft' => ['Craftsmanship', 'Atelier photograph and three steps', 'wrench', ''],
    'heritage' => ['Heritage', 'Gujarati headline between two portraits', 'star', ''],
    'closer' => ['A closer look', 'Magnifier photograph and featured price', 'zoom-in', ''],
    'manifesto' => ['Manifesto', '“Some jewellery is worn…”', 'quote', ''],
    'testimonials' => ['Testimonials', '', 'message-circle', 'testimonials.html'],
    'journal' => ['From the journal', 'The four latest stories', 'book-open', 'journal.html'],
    'instagram' => ['Instagram', 'Six photo tiles', 'instagram', ''],
    'visit' => ['Appointment band', 'Private consultation invitation', 'calendar', ''],
];

/* ---------- Settings ---------- */

function settings(bool $reload = false): array
{
    static $settings = null;
    if ($settings === null || $reload) {
        $settings = [];
        foreach (rows('SELECT name, value FROM settings') as $r) {
            $v = $r['value'];
            $decoded = json_decode((string) $v, true);
            $settings[$r['name']] = json_last_error() === JSON_ERROR_NONE ? $decoded : $v;
        }
    }
    return $settings;
}

function setting(string $name, $default = null)
{
    $s = settings();
    return array_key_exists($name, $s) ? $s[$name] : $default;
}

function save_settings(array $values): void
{
    $stmt = db()->prepare('INSERT INTO settings (name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)');
    foreach ($values as $name => $value) {
        $stmt->execute([$name, json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
    }
    settings(true);
}

/** Opening hours grouped into runs of days with the same times: [["Mon", "Sat", "10:30", "20:30"], …] */
function hours_groups(): array
{
    $groups = [];
    foreach ((array) setting('hours', []) as $h) {
        $last = $groups ? $groups[count($groups) - 1] : null;
        if (empty($h['open'])) { $groups[] = null; continue; }
        if ($last && $last['from'] === $h['from'] && $last['to'] === $h['to']) {
            $groups[count($groups) - 1]['last'] = $h['day'];
        } else {
            $groups[] = ['first' => $h['day'], 'last' => $h['day'], 'from' => $h['from'], 'to' => $h['to']];
        }
    }
    return array_values(array_filter($groups));
}

/** "Mon – Sat · 10:30 AM – 8:30 PM" */
function hours_short(): string
{
    $parts = array_map(fn ($g) => ($g['first'] === $g['last'] ? $g['first'] : $g['first'] . ' – ' . $g['last']) . ' · ' . clock_label($g['from']) . ' – ' . clock_label($g['to']), hours_groups());
    return $parts ? implode('; ', $parts) : 'By appointment';
}

/** "Monday – Saturday<br>10:30 AM – 8:30 PM" (for the Contact page) */
function hours_long_html(): string
{
    $long = ['Mon' => 'Monday', 'Tue' => 'Tuesday', 'Wed' => 'Wednesday', 'Thu' => 'Thursday', 'Fri' => 'Friday', 'Sat' => 'Saturday', 'Sun' => 'Sunday'];
    $parts = array_map(fn ($g) => ($g['first'] === $g['last'] ? $long[$g['first']] : $long[$g['first']] . ' – ' . $long[$g['last']]) . '<br>' . clock_label($g['from']) . ' – ' . clock_label($g['to']), hours_groups());
    return $parts ? implode('<br>', $parts) : 'By appointment';
}

/** "Mangalam House, Ring Road, Surat, Gujarat 395002" (both address lines, for emails) */
function store_address(): string
{
    return implode(', ', array_filter(array_map(fn ($l) => trim((string) $l, " ,"), [setting('address_line1', ''), setting('address_line2', '')])));
}

/** Contact details and links shared by every page of the website */
function site_vars(): array
{
    $phone = (string) setting('phone', '');
    return [
        'phone' => e($phone),
        'phoneHref' => 'tel:' . preg_replace('/[^\d+]/', '', $phone),
        'email' => e(setting('email', '')),
        'addressHtml' => e(setting('address_line1', '')) . '<br>' . e(setting('address_line2', '')),
        'hours' => e(hours_short()),
        'hoursLong' => hours_long_html(),
        'mapsUrl' => e(setting('maps_url', '')),
        'instagram' => e(setting('instagram', '')),
        'facebook' => e(setting('facebook', '')),
        'youtube' => e(setting('youtube', '')),
        'years' => (string) (int) (date('Y') - (int) setting('founded', 1962)),
        'founded' => e(setting('founded', '1962')),
        'offerPercent' => e(setting('offer_percent', '')),
        'offerHref' => e(setting('offer_href', 'jewellery.html')),
    ];
}

/* ---------- Catalogue ---------- */

/** Every category in menu order */
function categories(): array
{
    static $cats = null;
    if ($cats === null) $cats = rows('SELECT * FROM categories ORDER BY sort_order, id');
    return $cats;
}

function category_by_slug(string $slug): ?array
{
    foreach (categories() as $c) if ($c['slug'] === $slug) return $c;
    return null;
}

/** Name shown for a category ("Rings"); pieces without one are "Uncategorised" */
function category_name(?string $slug): string
{
    $c = $slug ? category_by_slug($slug) : null;
    return $c ? $c['name'] : 'Uncategorised';
}

/** The small copy of a photograph that sits beside it ("ring.jpg" → "ring-sm.jpg") */
function small_image(string $src): string
{
    return preg_replace('/\.(jpe?g|png|webp|gif)$/i', '-sm.$1', $src);
}

/** SQL condition for pieces visitors can see */
const PRODUCT_LIVE = "p.status = 'published' AND (p.publish_on IS NULL OR p.publish_on <= CURDATE())";

/**
 * Products with their category and photographs.
 * @param bool $liveOnly only what the website shows
 */
function products(bool $liveOnly = true): array
{
    static $cache = [];
    $key = $liveOnly ? 'live' : 'all';
    if (!isset($cache[$key])) {
        $list = rows('SELECT p.*, c.slug AS category, c.image AS category_image, c.sort_order AS category_sort
            FROM products p LEFT JOIN categories c ON c.id = p.category_id'
            . ($liveOnly ? ' WHERE ' . PRODUCT_LIVE : '') . '
            ORDER BY (c.id IS NULL), c.sort_order, p.sort_order, p.id');
        $photos = [];
        if ($list) {
            $ids = array_column($list, 'id');
            foreach (rows('SELECT product_id, path FROM product_images WHERE product_id IN (' . placeholders($ids) . ') ORDER BY sort_order, id', $ids) as $r) {
                $photos[$r['product_id']][] = $r['path'];
            }
        }
        foreach ($list as &$p) {
            $p['gallery'] = $photos[$p['id']] ?? [];
            $p['image'] = $p['gallery'][0] ?? ($p['category_image'] ?: 'assets/images/brand/mangalam-emblem-512.png');
            $p['thumb'] = $p['gallery'] ? small_image($p['gallery'][0]) : $p['image'];
            $p['is_live'] = $p['status'] === 'published' && (!$p['publish_on'] || $p['publish_on'] <= date('Y-m-d'));
        }
        unset($p);
        $cache[$key] = $list;
    }
    return $cache[$key];
}

function product_by_slug(string $slug, bool $liveOnly = true): ?array
{
    foreach (products($liveOnly) as $p) if ($p['slug'] === $slug) return $p;
    return null;
}

/** Featured pieces in their Signature-tab order */
function featured_products(bool $liveOnly = true): array
{
    $list = array_values(array_filter(products($liveOnly), fn ($p) => (int) $p['featured_order'] > 0));
    usort($list, fn ($a, $b) => $a['featured_order'] <=> $b['featured_order']);
    return $list;
}

/** "₹ 1,23,000", or "Price on request" when the price is hidden */
function product_price_text(array $p): string
{
    if (!(int) $p['show_price']) return 'Price on request';
    return inr($p['offer_price'] !== null ? $p['offer_price'] : $p['price']);
}

/** A product as the website's scripts read it (window.MJ.products) */
function product_public(array $p): array
{
    return [
        'slug' => $p['slug'],
        'name' => $p['name'],
        'category' => $p['category'] ?? '',
        'price' => (int) $p['price'],
        'offerPrice' => $p['offer_price'] !== null ? (int) $p['offer_price'] : null,
        'showPrice' => (bool) $p['show_price'],
        'enquire' => (bool) $p['allow_enquiry'],
        'metal' => $p['metal'],
        'style' => $p['style'],
        'image' => $p['image'],
        'thumb' => $p['thumb'],
        'gallery' => $p['gallery'],
        'imagePosition' => $p['image_position'],
        'description' => (string) $p['summary'],
        'details' => (string) $p['details'],
        'purity' => $p['purity'],
        'stone' => $p['stone'],
        'collection' => $p['line'],
        'isNew' => (bool) $p['is_new'],
        'view3d' => ($v = view3d_config($p['view3d'] ?? null)) && $v['enabled'] ? $v : null,
        'spin' => spin_public($p),
    ];
}

/* ---------- Category offers (the Offers page, and the ribbon on each category's page) ---------- */

/** SQL condition for offers visitors can see: switched on, started, and not yet past their last day */
const OFFER_LIVE = 'o.is_live = 1 AND (o.starts_on IS NULL OR o.starts_on <= CURDATE()) AND (o.ends_on IS NULL OR o.ends_on >= CURDATE())';

/**
 * Category offers in their order, with their category and the photographs that are still on disk.
 * @param bool $liveOnly only what the website shows
 */
function category_offers(bool $liveOnly = true): array
{
    static $cache = [];
    $key = $liveOnly ? 'live' : 'all';
    if (!isset($cache[$key])) {
        try {
            $list = rows('SELECT o.*, c.slug AS category, c.name AS category_name, c.image AS category_image, c.banner AS category_banner
                FROM offers o LEFT JOIN categories c ON c.id = o.category_id'
                . ($liveOnly ? ' WHERE ' . OFFER_LIVE : '') . '
                ORDER BY o.sort_order, o.id');
        } catch (PDOException $e) {
            // After an update the table is made on the admin's next visit (upgrade_schema); until then there are no offers
            if ($e->getCode() !== '42S02') throw $e;
            $list = [];
        }
        foreach ($list as &$o) {
            $images = json_decode((string) $o['images'], true);
            $o['images'] = array_values(array_filter(is_array($images) ? $images : [], fn ($p) => is_string($p) && $p !== '' && is_file(ROOT_DIR . '/' . $p)));
            $o['category'] = $o['category'] ?? '';
            $o['category_name'] = $o['category_name'] ?? 'All jewellery';
            $o['category_image'] = $o['category_image'] ?? '';
            $o['category_banner'] = $o['category_banner'] ?? '';
            $o['href'] = $o['category'] !== '' ? $o['category'] . '.html' : 'jewellery.html';
            $o['anchor'] = 'offer-' . ($o['category'] !== '' ? $o['category'] : 'all') . '-' . $o['id'];
        }
        unset($o);
        $cache[$key] = $list;
    }
    return $cache[$key];
}

/** The live offer for a category's page ('' = All jewellery), or null */
function offer_for_category(string $slug): ?array
{
    foreach (category_offers(true) as $o) if ($o['category'] === $slug) return $o;
    return null;
}

/** live, scheduled (starts later), ended, or off (switched off) */
function offer_state(array $o): string
{
    if (!(int) $o['is_live']) return 'off';
    $today = date('Y-m-d');
    if ($o['starts_on'] && $o['starts_on'] > $today) return 'scheduled';
    if ($o['ends_on'] && $o['ends_on'] < $today) return 'ended';
    return 'live';
}

/** Icons the Offers page's steps can use */
const OFFER_STEP_ICONS = ['gem' => 'Gem', 'calendar' => 'Calendar', 'gift' => 'Gift', 'shield-check' => 'Hallmark shield', 'sparkles' => 'Sparkles',
    'truck' => 'Delivery', 'award' => 'Award', 'heart' => 'Heart', 'clock' => 'Clock', 'message-circle' => 'Message', 'map-pin' => 'Map pin', 'images' => 'Photos'];

/** What the Offers page shows around the offers (setting offers_page, edited in the admin under Offers page › Page content) */
const OFFERS_PAGE_DEFAULTS = [
    'stats' => true,
    'chips' => true, 'chips_all' => 'All offers',
    'timer' => true, 'pieces' => 4, 'viewing' => true, 'feature' => true,
    'steps_on' => true, 'steps_eyebrow' => 'How the offers work', 'steps_title' => 'Savings, *simply*',
    'steps' => [
        ['icon' => 'gem', 'title' => 'Choose your piece', 'text' => 'Browse the offer, open its gallery and save the pieces you love to your wishlist.'],
        ['icon' => 'calendar', 'title' => 'Visit or enquire', 'text' => 'Book a private viewing at Mangalam House, or send an enquiry from any piece.'],
        ['icon' => 'gift', 'title' => 'Saved at billing', 'text' => 'The offer is applied when you buy, on the day\'s gold rate — no codes to remember.'],
        ['icon' => 'shield-check', 'title' => 'Hallmarked, always', 'text' => 'Every offer piece carries its BIS hallmark and HUID, exactly like the rest of our jewellery.'],
    ],
    'cta_on' => true, 'cta_title' => 'See the offers *in person*',
    'cta_text' => 'Our concierge will set aside the pieces you like before you arrive, so the offer is ready when you are.',
    'cta_primary' => 'Book a private viewing', 'cta_secondary' => 'Browse all jewellery', 'cta_href' => 'jewellery.html',
    'empty_title' => 'New offers are on their way',
    'empty_text' => 'Our next seasonal offers are being prepared. Browse the collection in the meantime, or ask our concierge about this week\'s gold rate.',
];

/** The Offers page's settings, with the defaults filling anything not saved yet */
function offers_page_settings(): array
{
    $saved = setting('offers_page', []);
    $s = array_replace(OFFERS_PAGE_DEFAULTS, is_array($saved) ? array_intersect_key($saved, OFFERS_PAGE_DEFAULTS) : []);
    $s['steps'] = array_values(array_filter(is_array($s['steps']) ? $s['steps'] : [], 'is_array'));
    return $s;
}

/** "Up to 25% off making charges", "Flat 10% off" */
function offer_deal_text(array $o): string
{
    return ($o['deal_type'] === 'flat' ? 'Flat ' : 'Up to ') . (int) $o['percent'] . '% off' . ($o['deal_on'] !== '' ? ' ' . $o['deal_on'] : '');
}

/* ---------- The 3D view: a house design or an uploaded .glb model (assets/js/viewer3d.js draws both) ---------- */

// The kinds of piece and their house designs, in the order the admin offers them (the same ids as DESIGNS in viewer3d.js)
const VIEW3D_KINDS = [
    'ring' => ['name' => 'Ring', 'plural' => 'Rings', 'size' => 'ring size', 'designs' => ['solitaire' => 'Solitaire', 'halo' => 'Halo', 'trilogy' => 'Trilogy', 'eternity' => 'Eternity', 'band' => 'Plain band']],
    'bangle' => ['name' => 'Kangan & bangle', 'plural' => 'Kangans & bangles', 'size' => 'bangle size', 'designs' => ['kada' => 'Plain kada', 'diamond-kangan' => 'Diamond kangan', 'kundan-kada' => 'Kundan kada', 'twisted' => 'Twisted rope', 'cuff' => 'Open cuff']],
    'earring' => ['name' => 'Earrings', 'plural' => 'Earrings', 'size' => '', 'designs' => ['stud' => 'Solitaire studs', 'halo-stud' => 'Halo studs', 'drop' => 'Drops', 'hoop' => 'Hoops', 'jhumka' => 'Jhumkas']],
    'pendant' => ['name' => 'Pendant', 'plural' => 'Pendants', 'size' => 'chain length', 'designs' => ['solitaire-pendant' => 'Solitaire', 'halo-pendant' => 'Halo', 'heart' => 'Open heart', 'drop-pendant' => 'Drop']],
];
// Designs without a main stone (visitors are not offered stones for them)
const VIEW3D_NO_STONE = ['band', 'kada', 'twisted'];
const VIEW3D_METALS = ['yellow', 'rose', 'white', 'platinum'];
const VIEW3D_METAL_NAMES = ['yellow' => 'Yellow gold', 'rose' => 'Rose gold', 'white' => 'White gold', 'platinum' => 'Platinum'];
const VIEW3D_STONES = ['diamond', 'ruby', 'emerald', 'sapphire', 'pukhraj', 'amethyst'];
const VIEW3D_STONE_NAMES = ['diamond' => 'Diamond', 'ruby' => 'Ruby', 'emerald' => 'Emerald', 'sapphire' => 'Blue sapphire', 'pukhraj' => 'Yellow sapphire', 'amethyst' => 'Amethyst'];

// How a new 3D view is guessed (here, and by "Match from photo & details" in assets/js/admin-3d.js, which is given
// these lists): the kind from the product's category, then the design from the first of these words in its name
const VIEW3D_GUESS = [
    'kinds' => ['rings' => 'ring', 'bangles' => 'bangle', 'bracelets' => 'bangle', 'earrings' => 'earring', 'pendants' => 'pendant', 'necklaces' => 'pendant', 'chains' => 'pendant'],
    'words' => [
        'ring' => ['halo' => 'halo', 'trilogy' => 'trilogy', 'three stone' => 'trilogy', 'three-stone' => 'trilogy', 'eternity' => 'eternity', 'band' => 'band', 'wedding' => 'band', 'solitaire' => 'solitaire'],
        'bangle' => ['kundan' => 'kundan-kada', 'polki' => 'kundan-kada', 'cuff' => 'cuff', 'twist' => 'twisted', 'rope' => 'twisted', 'diamond' => 'diamond-kangan', 'pave' => 'diamond-kangan', 'kada' => 'kada'],
        'earring' => ['jhumka' => 'jhumka', 'jhumki' => 'jhumka', 'hoop' => 'hoop', 'bali' => 'hoop', 'chandbali' => 'drop', 'drop' => 'drop', 'halo' => 'halo-stud', 'stud' => 'stud'],
        'pendant' => ['heart' => 'heart', 'halo' => 'halo-pendant', 'drop' => 'drop-pendant', 'solitaire' => 'solitaire-pendant'],
    ],
];

/** Which kind of piece a category's products are, for a new 3D view (rings → ring, bangles → kangan …) */
function view3d_kind_for(?string $category): string
{
    return VIEW3D_GUESS['kinds'][$category ?? ''] ?? 'ring';
}

/** A first 3D view for a product that has none: its kind from the category, its design from its name, metal and stone from its details */
function view3d_guess(?array $p): array
{
    $name = mb_strtolower((string) ($p['name'] ?? ''));
    $kind = view3d_kind_for($p['category'] ?? null);
    $type = '';
    foreach (VIEW3D_GUESS['words'][$kind] as $word => $design) {
        if (str_contains($name, $word)) { $type = $design; break; }
    }
    if ($type === '' && $kind === 'ring' && ($p['stone'] ?? '') === 'None') $type = 'band';
    $metal = ['Rose gold' => 'rose', 'Platinum' => 'platinum', 'Silver' => 'white'][$p['metal'] ?? ''] ?? 'yellow';
    $stone = ['Ruby' => 'ruby', 'Emerald' => 'emerald'][$p['stone'] ?? ''] ?? 'diamond';
    return view3d_config(['enabled' => false, 'kind' => $kind, 'design' => ['type' => $type, 'pair' => $kind === 'bangle' && preg_match('/\b(pair|set of)\b|kadas|bangles/', $name)],
        'metal' => $metal, 'stone' => $stone, 'sizes' => $kind !== 'earring']);
}

/**
 * A product's 3D settings (products.view3d), checked and complete — or null when it has none.
 * `kind` is ring, bangle (kangans and bangles), earring or pendant, and `design.type` one of its designs.
 * Visitors can choose among `metals`, `stones` and `styles` (other designs of the same kind), and are asked for
 * their size (ring or bangle size, chain length) when `sizes` is on; `metal`, `stone` and `design.type` are what
 * the piece first shows. Settings saved before there were kinds are rings.
 * @param mixed $raw the stored JSON, or the decoded array
 */
function view3d_config($raw): ?array
{
    $v = is_array($raw) ? $raw : json_decode((string) $raw, true);
    if (!is_array($v)) return null;
    $pick = fn ($x, array $list) => in_array($x, $list, true) ? $x : $list[0];
    $num = fn ($x, float $min, float $max) => is_numeric($x) ? round(max($min, min($max, (float) $x)), 2) : 1.0;
    $choices = function ($x, array $list, string $default, bool $allWhenMissing = true) {
        $chosen = array_values(array_intersect($list, is_array($x) ? $x : ($allWhenMissing ? $list : [])));
        return array_values(array_intersect($list, array_merge($chosen, [$default]))); // the first look is always offered
    };
    $d = is_array($v['design'] ?? null) ? $v['design'] : [];
    $kind = isset(VIEW3D_KINDS[$v['kind'] ?? '']) ? $v['kind'] : 'ring';
    $designs = array_keys(VIEW3D_KINDS[$kind]['designs']);
    $type = $pick($d['type'] ?? '', $designs);
    $model = clean_model_path($v['model'] ?? '');
    $metal = $pick($v['metal'] ?? '', VIEW3D_METALS);
    $stone = $pick($v['stone'] ?? '', VIEW3D_STONES);
    return [
        'enabled' => !empty($v['enabled']),
        'kind' => $kind,
        'source' => ($v['source'] ?? '') === 'model' && $model !== '' ? 'model' : 'design',
        'design' => [
            'type' => $type,
            'stoneSize' => $num($d['stoneSize'] ?? 1, 0.7, 1.4),
            'bandWidth' => $num($d['bandWidth'] ?? 1, 0.7, 1.5),
            'prongs' => (int) ($d['prongs'] ?? 6) === 4 ? 4 : 6,
            'pair' => $kind === 'bangle' && !empty($d['pair']),
        ],
        'model' => $model,
        'metal' => $metal,
        'stone' => $stone,
        'metals' => $choices($v['metals'] ?? null, VIEW3D_METALS, $metal),
        'stones' => $choices($v['stones'] ?? null, VIEW3D_STONES, $stone),
        'styles' => $choices($v['styles'] ?? null, $designs, $type, false),
        'sizes' => !empty($v['sizes']) && $kind !== 'earring',
    ];
}

/** "Diamond kangan · Yellow gold" — how a 3D view is described in lists */
function view3d_label(array $v): string
{
    $style = $v['source'] === 'model' ? 'Own 3D model' : VIEW3D_KINDS[$v['kind']]['designs'][$v['design']['type']];
    return $style . ' · ' . VIEW3D_METAL_NAMES[$v['metal']] . ($v['source'] === 'design' && in_array($v['design']['type'], VIEW3D_NO_STONE, true) ? '' : ' with ' . mb_strtolower(VIEW3D_STONE_NAMES[$v['stone']]));
}

/* ---------- The 360° view: the piece itself, photographed as it turns (assets/js/spin360.js shows it) ---------- */

const SPIN_MIN_FRAMES = 8;
const SPIN_MAX_FRAMES = 120;

/**
 * A product's 360° view (products.spin), checked and complete — or null when it has none.
 * `frames` are its photographs in turning order (assets/images/spins/…, made by the admin from a turntable video
 * or a set of photos); `loop` is on when they make a full turn (it carries on round), off for part of a turn (it
 * swings back at the ends); it turns by itself (`autoplay`) once in `seconds`, starting at frame `start`;
 * `reverse` swaps the way a drag turns it. `source` and `video` only say how it was made.
 * Frames no longer on disk are left out.
 * @param mixed $raw the stored JSON, or the decoded array
 */
function spin_config($raw): ?array
{
    $s = is_array($raw) ? $raw : json_decode((string) $raw, true);
    if (!is_array($s)) return null;
    $frames = [];
    foreach (is_array($s['frames'] ?? null) ? $s['frames'] : [] as $f) {
        $f = clean_spin_frame($f);
        if ($f !== '' && !in_array($f, $frames, true)) $frames[] = $f;
    }
    $frames = array_slice($frames, 0, SPIN_MAX_FRAMES);
    if (!$frames) return null;
    $seconds = is_numeric($s['seconds'] ?? null) ? (float) $s['seconds'] : 8.0;
    return [
        'enabled' => !empty($s['enabled']),
        'frames' => $frames,
        'loop' => !array_key_exists('loop', $s) || !empty($s['loop']),
        'reverse' => !empty($s['reverse']),
        'autoplay' => !array_key_exists('autoplay', $s) || !empty($s['autoplay']),
        'seconds' => round(max(3, min(30, $seconds)), 1),
        'start' => max(0, min(count($frames) - 1, (int) ($s['start'] ?? 0))),
        'source' => ($s['source'] ?? '') === 'photos' ? 'photos' : 'video',
        'video' => mb_substr(trim((string) ($s['video'] ?? '')), 0, 120),
    ];
}

/** What the website's scripts get: a switched-on 360° view with enough frames to turn, or null */
function spin_public(array $p): ?array
{
    $s = spin_config($p['spin'] ?? null);
    if (!$s || !$s['enabled'] || count($s['frames']) < SPIN_MIN_FRAMES) return null;
    unset($s['enabled'], $s['source'], $s['video']);
    return $s;
}

/** "36 frames · full turn · turns by itself in 8 s" — how a 360° view is described in lists */
function spin_label(array $s): string
{
    return plural(count($s['frames']), 'frame') . ' · ' . ($s['loop'] ? 'full turn' : 'part turn, swings back')
        . ($s['autoplay'] ? ' · turns by itself in ' . rtrim(rtrim(number_format($s['seconds'], 1), '0'), '.') . ' s' : ' · turns when dragged');
}

/** A 360° frame's address if it is one of ours and still on disk (assets/images/spins/<set>/<frame>.jpg), otherwise '' */
function clean_spin_frame($path): string
{
    $path = preg_replace('#^(\.\./)+#', '', trim((string) $path));
    return preg_match('#^assets/images/spins/[a-z0-9][a-z0-9-]{0,80}/[a-z0-9-]{1,40}\.jpg$#', $path) && is_file(ROOT_DIR . '/' . $path) ? $path : '';
}

/** A 3D model's address if it is one of ours and still on disk (assets/models/….glb), otherwise '' */
function clean_model_path($path): string
{
    $path = trim((string) $path);
    return preg_match('#^assets/models/[a-z0-9][a-z0-9-]*\.glb$#', $path) && is_file(ROOT_DIR . '/' . $path) ? $path : '';
}

/* ---------- Collections: each gathers its pieces by a rule ---------- */

function collections(bool $homeOnly = false): array
{
    $list = rows('SELECT * FROM collections' . ($homeOnly ? ' WHERE on_home = 1' : '') . ' ORDER BY sort_order, id');
    $live = products(true);
    foreach ($list as &$c) {
        $c['n'] = count(array_filter($live, fn ($p) => collection_matches($c, $p)));
        $c['href'] = collection_href($c);
    }
    unset($c);
    return $list;
}

function collection_matches(array $c, array $p): bool
{
    $field = $c['rule_field'];
    return strtolower((string) ($p[$field] ?? '')) === strtolower($c['rule_value']);
}

function collection_href(array $c): string
{
    return $c['rule_field'] === 'category' ? $c['rule_value'] . '.html' : 'jewellery.html?' . $c['rule_field'] . '=' . rawurlencode($c['rule_value']);
}

function collection_rule_text(array $c): string
{
    $value = $c['rule_field'] === 'category' ? category_name($c['rule_value']) : $c['rule_value'];
    return capitalize($c['rule_field']) . ' is ' . $value;
}

/* ---------- Journal ---------- */

const ARTICLE_LIVE = "status IN ('published','scheduled') AND published_on IS NOT NULL AND published_on <= CURDATE()";

function articles(bool $liveOnly = true): array
{
    return rows('SELECT * FROM articles' . ($liveOnly ? ' WHERE ' . ARTICLE_LIVE : '') . ' ORDER BY (published_on IS NULL) DESC, published_on DESC, id DESC');
}

/** A story as the website's scripts read it (window.MJ.articles) */
function article_public(array $a): array
{
    return [
        'slug' => $a['slug'],
        'title' => $a['title'],
        'category' => $a['category'],
        'readTime' => $a['read_time'] . ' min read',
        'date' => $a['published_on'] ? (new DateTimeImmutable($a['published_on']))->format('F Y') : '',
        'excerpt' => $a['excerpt'],
        'image' => $a['image'],
        'imagePosition' => $a['image_position'],
        'body' => (string) $a['body'],
    ];
}

function page_row(string $key): array
{
    static $pages = null;
    if ($pages === null) {
        $pages = [];
        foreach (rows('SELECT * FROM pages') as $r) $pages[$r['page_key']] = $r;
    }
    return $pages[$key] ?? ['page_key' => $key, 'name' => $key, 'kind' => 'Page', 'heading' => '', 'lead' => '', 'banner' => '', 'seo_title' => 'Mangalam Jewellers', 'seo_desc' => ''];
}

/** Changes whenever anything on the website changes, so browsers fetch the new data.js */
function content_version(): string
{
    $stamps = rows("SELECT MAX(updated_at) AS t FROM products UNION ALL SELECT MAX(updated_at) FROM categories
        UNION ALL SELECT MAX(updated_at) FROM articles UNION ALL SELECT MAX(updated_at) FROM testimonials
        UNION ALL SELECT MAX(updated_at) FROM settings UNION ALL SELECT COUNT(*) FROM product_images
        UNION ALL SELECT COALESCE(SUM(id), 0) FROM product_images UNION ALL SELECT COUNT(*) FROM products
        UNION ALL SELECT CURDATE()");
    return substr(md5(implode('|', array_column($stamps, 't'))), 0, 10);
}
