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

/** The mail server settings; $override (the Settings form, for a test email) wins over what is saved */
function mail_config(array $override = []): array
{
    $c = [
        'host' => trim((string) setting('smtp_host', '')), 'port' => (int) setting('smtp_port', 587),
        'secure' => (string) setting('smtp_secure', 'tls'), 'user' => trim((string) setting('smtp_user', '')),
        'pass' => (string) setting('smtp_pass', ''), 'from' => trim((string) setting('mail_from', '')),
    ];
    foreach ($override as $k => $v) if ($v !== null && $v !== '') $c[$k] = $v;
    // Gmail and most hosts only send "From" the signed-in address
    if ($c['from'] === '') $c['from'] = valid_email($c['user']) ? $c['user'] : (string) setting('email', 'care@mangalamjewellers.in');
    return $c;
}

/** Why the last email was not sent ('' when it was) */
function mail_error(?string $set = null): string
{
    static $error = '';
    if ($set !== null) $error = $set;
    return $error;
}

function send_mail(string $to, string $subject, string $body, string $replyTo = '', array $override = [], bool $force = false): bool
{
    mail_error('');
    if (!$force && !setting('mail_enabled', false)) { mail_error('Sending emails is off in Settings › Notifications.'); return false; }
    $recipients = array_values(array_filter(array_map('trim', explode(',', $to)), 'valid_email'));
    if (!$recipients) { mail_error('There is no email address to send to.'); return false; }
    $c = mail_config($override);
    $name = (string) setting('store_name', 'Mangalam Jewellers');
    $headers = [
        'From' => mb_encode_mimeheader($name) . ' <' . $c['from'] . '>',
        'Reply-To' => $replyTo !== '' && valid_email($replyTo) ? $replyTo : '',
        'MIME-Version' => '1.0', 'Content-Type' => 'text/plain; charset=UTF-8', 'Content-Transfer-Encoding' => '8bit',
    ];
    $body = str_replace(["\r\n", "\r"], "\n", $body);
    try {
        if ($c['host'] !== '') {
            smtp_send($c, $recipients, mb_encode_mimeheader($subject), $body, $headers);
            return true;
        }
        // No mail server set: PHP's own mail() (works on most hosting; on XAMPP it needs an SMTP server)
        $lines = [];
        foreach ($headers as $k => $v) if ($v !== '') $lines[] = "$k: $v";
        $sent = @mail(implode(', ', $recipients), mb_encode_mimeheader($subject), str_replace("\n", "\r\n", $body), implode("\r\n", $lines));
        if (!$sent) throw new RuntimeException('The server could not send it with PHP mail(). Add your mail server (for example Gmail) under Settings › Notifications.');
        return true;
    } catch (Throwable $e) {
        mail_error($e->getMessage());
        error_log('Mangalam email to ' . implode(', ', $recipients) . ' not sent: ' . $e->getMessage());
        return false;
    }
}

/** A plain SMTP conversation (SSL on 465, STARTTLS on 587), with AUTH LOGIN when a user is set */
function smtp_send(array $c, array $to, string $subject, string $body, array $headers): void
{
    $secure = in_array($c['secure'], ['ssl', 'tls', 'none'], true) ? $c['secure'] : 'tls';
    $port = (int) $c['port'] ?: ($secure === 'ssl' ? 465 : 587);
    // Port 465 is encrypted from the first byte (SSL); 587 starts plain and switches with STARTTLS
    if ($port === 465) $secure = 'ssl';
    elseif ($port === 587 && $secure === 'ssl') $secure = 'tls';
    $context = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'SNI_enabled' => true, 'peer_name' => $c['host']]]);
    $fp = @stream_socket_client(($secure === 'ssl' ? 'ssl://' : 'tcp://') . $c['host'] . ':' . $port, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $context);
    if (!$fp) throw new RuntimeException("Could not connect to {$c['host']}:$port ($errstr). Check the server name and port.");
    stream_set_timeout($fp, 20);
    $read = function () use ($fp): array {
        $text = '';
        while (($line = fgets($fp, 1024)) !== false) {
            $text .= $line;
            if (strlen($line) < 4 || $line[3] === ' ') break;
        }
        // One line for messages: "530-5.7.0 Authentication…\r\n530 5.7.0 …" → "5.7.0 Authentication… 5.7.0 …"
        return [(int) substr($text, 0, 3), trim(preg_replace(['/(^|\n)\d{3}[ -]/', '/\s+/'], [' ', ' '], $text))];
    };
    $cmd = function (string $line, array $ok, string $what = '') use ($fp, $read): string {
        if ($line !== '') fwrite($fp, $line . "\r\n");
        [$code, $text] = $read();
        if (!in_array($code, $ok, true)) throw new RuntimeException(($what ?: 'The mail server refused the email') . ': ' . ($text ?: 'no answer'));
        return $text;
    };
    try {
        $cmd('', [220], 'The mail server did not greet us');
        $me = preg_replace('/[^a-z0-9.-]/i', '', (string) ($_SERVER['SERVER_NAME'] ?? '')) ?: 'localhost';
        $ehlo = $cmd("EHLO $me", [250]);
        if ($secure === 'tls') {
            if (stripos($ehlo, 'STARTTLS') === false) throw new RuntimeException('The mail server does not offer TLS on this port — try port 465 with SSL.');
            $cmd('STARTTLS', [220]);
            if (!@stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT)) throw new RuntimeException('Could not start a secure connection with the mail server.');
            $ehlo = $cmd("EHLO $me", [250]);
        }
        if ($c['user'] !== '') {
            $refused = 'The mail server refused the username or password' . (stripos($c['host'], 'gmail') !== false ? ' — Gmail only accepts a current App Password made on this same account' : '');
            // AUTH PLAIN when offered (one step), otherwise AUTH LOGIN
            if (preg_match('/\bAUTH[ =].*?(?<![\w-])PLAIN(?![\w-])/i', $ehlo)) {
                $cmd('AUTH PLAIN ' . base64_encode("\0" . $c['user'] . "\0" . $c['pass']), [235], $refused);
            } else {
                $cmd('AUTH LOGIN', [334], 'The mail server does not accept a sign-in');
                $cmd(base64_encode($c['user']), [334], 'The mail server refused the username');
                $cmd(base64_encode($c['pass']), [235], $refused);
            }
        }
        $cmd('MAIL FROM:<' . $c['from'] . '>', [250], 'The mail server refused the sender ' . $c['from']);
        foreach ($to as $rcpt) $cmd("RCPT TO:<$rcpt>", [250, 251], 'The mail server refused ' . $rcpt);
        $cmd('DATA', [354]);
        $head = 'Date: ' . date('r') . "\r\nTo: " . implode(', ', $to) . "\r\nSubject: $subject\r\n"
            . 'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . (explode('@', $c['from'])[1] ?? 'localhost') . ">\r\n";
        foreach ($headers as $k => $v) if ($v !== '') $head .= "$k: $v\r\n";
        // Lines starting with a dot are doubled so they don't end the message early
        $data = preg_replace('/^\./m', '..', str_replace("\n", "\r\n", $body));
        $cmd($head . "\r\n" . $data . "\r\n.", [250], 'The mail server did not accept the email');
        fwrite($fp, "QUIT\r\n");
    } finally {
        fclose($fp);
    }
}

/** Tells the team about something that arrived from the website; replies go to the visitor */
function notify_team(string $subject, string $body, string $replyTo = ''): bool
{
    $to = trim((string) setting('notify_email', '')) ?: (string) setting('email', '');
    return send_mail($to, 'Mangalam website: ' . $subject, $body . "\n\n— Sent by the website. Open the admin to reply.", $replyTo);
}

/** Once a day, from 7 AM: today's appointments to the team (sent by the first page someone opens after that) */
function maybe_send_daily_summary(): void
{
    try {
        $today = date('Y-m-d');
        if ((int) date('G') < 7 || !setting('mail_enabled', false) || !setting('notify_summary', true) || setting('summary_sent_on', '') === $today) return;
        save_settings(['summary_sent_on' => $today]);
        $list = rows("SELECT * FROM appointments WHERE date = ? AND status <> 'cancelled' ORDER BY time = '', STR_TO_DATE(time, '%h:%i %p'), id", [$today]);
        $lines = array_map(fn ($a) => ($a['time'] ?: 'Time to confirm') . ' — ' . $a['name'] . ' · ' . $a['interest']
            . ($a['phone'] ? ' · ' . $a['phone'] : '') . ($a['consultant'] ? ' · with ' . $a['consultant'] : '') . ($a['status'] === 'pending' ? ' (not yet confirmed)' : ''), $list);
        $body = $list ? implode("\n", $lines) : 'No appointments today.';
        notify_team(count($list) . ' appointment' . (count($list) === 1 ? '' : 's') . ' today, ' . date('l j F'), $body);
    } catch (Throwable $e) {
        error_log('Mangalam daily summary: ' . $e->getMessage());
    }
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
        'view3d' => ($v = view3d_config($p['view3d'] ?? null)) && $v['enabled'] ? $v : null,
    ];
}

/* ---------- The 3D view: a house ring design or an uploaded .glb model (assets/js/viewer3d.js draws both) ---------- */

const VIEW3D_DESIGNS = ['solitaire', 'halo', 'trilogy', 'eternity', 'band'];
const VIEW3D_METALS = ['yellow', 'rose', 'white', 'platinum'];
const VIEW3D_STONES = ['diamond', 'ruby', 'emerald', 'sapphire', 'pukhraj', 'amethyst'];

/**
 * A product's 3D settings (products.view3d), checked and complete — or null when it has none.
 * Visitors can choose among `metals` and `stones`; `metal` and `stone` are what the piece first shows.
 * @param mixed $raw the stored JSON, or the decoded array
 */
function view3d_config($raw): ?array
{
    $v = is_array($raw) ? $raw : json_decode((string) $raw, true);
    if (!is_array($v)) return null;
    $pick = fn ($x, array $list) => in_array($x, $list, true) ? $x : $list[0];
    $num = fn ($x, float $min, float $max) => is_numeric($x) ? round(max($min, min($max, (float) $x)), 2) : 1.0;
    $choices = function ($x, array $list, string $default) {
        $chosen = array_values(array_intersect($list, is_array($x) ? $x : $list));
        return array_values(array_intersect($list, array_merge($chosen, [$default]))); // the first look is always offered
    };
    $d = is_array($v['design'] ?? null) ? $v['design'] : [];
    $model = clean_model_path($v['model'] ?? '');
    $metal = $pick($v['metal'] ?? '', VIEW3D_METALS);
    $stone = $pick($v['stone'] ?? '', VIEW3D_STONES);
    return [
        'enabled' => !empty($v['enabled']),
        'source' => ($v['source'] ?? '') === 'model' && $model !== '' ? 'model' : 'design',
        'design' => [
            'type' => $pick($d['type'] ?? '', VIEW3D_DESIGNS),
            'stoneSize' => $num($d['stoneSize'] ?? 1, 0.7, 1.4),
            'bandWidth' => $num($d['bandWidth'] ?? 1, 0.7, 1.5),
            'prongs' => (int) ($d['prongs'] ?? 6) === 4 ? 4 : 6,
        ],
        'model' => $model,
        'metal' => $metal,
        'stone' => $stone,
        'metals' => $choices($v['metals'] ?? null, VIEW3D_METALS, $metal),
        'stones' => $choices($v['stones'] ?? null, VIEW3D_STONES, $stone),
    ];
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
