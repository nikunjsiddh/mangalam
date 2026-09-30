<?php
/* Reading the catalogue, journal and settings from the database, in the shapes the website and admin use. */

const METALS = ['Gold', 'Diamond', 'Rose gold', 'Platinum', 'Silver'];
const PURITIES = ['24K', '22K', '18K', '14K'];
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
    'featured' => ['Featured pieces', 'Signature, Gold, Diamond, Bridal and New in', 'gem', '#featured-editor'],
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

/* ---------- Email (only when "Send emails from the website" is on in Settings › Notifications) ---------- */

function send_mail(string $to, string $subject, string $body, string $replyTo = ''): bool
{
    if (!setting('mail_enabled', false) || $to === '') return false;
    $from = (string) setting('email', 'care@mangalamjewellers.in');
    $headers = 'From: ' . mb_encode_mimeheader((string) setting('store_name', 'Mangalam Jewellers')) . " <$from>\r\n"
        . ($replyTo ? "Reply-To: $replyTo\r\n" : '')
        . "MIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit";
    return @mail($to, mb_encode_mimeheader($subject), $body, $headers);
}

/** Tells the team about something that arrived from the website */
function notify_team(string $subject, string $body): bool
{
    $to = trim((string) setting('notify_email', setting('email', '')));
    return send_mail($to, 'Mangalam website: ' . $subject, $body . "\n\n— Sent by the website. Open the admin to reply.");
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
    ];
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
