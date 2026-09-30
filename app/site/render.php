<?php
/* Mangalam Jewellers — the website's pages, rendered from the database on each request.
 *
 * The templates are in app/views/site/ (layout and shared parts in partials/, each page's content in pages/).
 * Catalogue and category pages, the product page, the journal story page, search and the wishlist are
 * finished in the browser by assets/js/main.js from window.MJ, which data.js (site_data_js) provides. */

function site_label(string $slug): string
{
    return category_name($slug);
}

function nav_categories(): array
{
    return array_values(array_filter(categories(), fn ($c) => (int) $c['in_menu'] === 1));
}

/** Parts shared by every page: menus and the announcement bar */
function site_fragments(): array
{
    $cats = nav_categories();
    $announcements = array_values(array_filter(array_map('trim', (array) setting('announcements', [])), 'strlen'));
    return [
        'megaCats' => implode("\n                ", array_map(fn ($c) =>
            '<li><a href="' . e($c['slug']) . '.html"><span class="mega__thumb"><img src="' . e($c['image']) . '" alt="" loading="lazy" width="900" height="1200"></span>' . e($c['name']) . '</a></li>', $cats)),
        // The mobile menu and the footer list the jewellery categories; Bridal has its own link beside them
        'menuCats' => implode('', array_map(fn ($c) => '<li><a href="' . e($c['slug']) . '.html">' . e($c['name']) . '</a></li>', array_filter($cats, fn ($c) => $c['slug'] !== 'bridal'))),
        'footerCats' => implode("\n", array_map(fn ($c) => '            <li><a href="' . e($c['slug']) . '.html">' . e($c['name']) . '</a></li>', array_filter($cats, fn ($c) => $c['slug'] !== 'bridal'))),
        // Shown twice over so the marquee never runs dry
        'topbarItems' => implode('', array_map(fn ($t) => '<li>' . e($t) . '</li>', array_merge($announcements, $announcements))),
    ];
}

function pct($v): string
{
    return rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');
}

function count_live(callable $fn): int
{
    return count(array_filter(products(true), $fn));
}

/* ---------- Homepage ---------- */

function home_fragments(): array
{
    $live = products(true);
    $hotspots = rows('SELECT h.*, p.slug FROM hotspots h JOIN products p ON p.id = h.product_id ORDER BY h.sort_order, h.id');
    $pins = [];
    foreach ($hotspots as $h) {
        $p = product_by_slug($h['slug']);
        if (!$p) continue; // the piece is hidden or unpublished
        $pins[] = '<div class="hotspot hotspot--desktop' . ($h['flip'] ? ' hotspot--flip' : '') . '" style="--x:' . pct($h['x']) . '%;--y:' . pct($h['y']) . '%" tabindex="0" role="button" aria-expanded="false" aria-label="Shop the look: ' . e($p['name']) . '">
            <span class="hotspot__card">
              <img src="' . e($p['thumb']) . '" alt="" loading="lazy" style="object-position:' . e($p['image_position']) . '">
              <span><span class="hotspot__label">Shop the look</span><a class="hotspot__name" href="product.html?slug=' . e($p['slug']) . '">' . e($p['name']) . '</a><span class="hotspot__price">' . product_price_text($p) . '</span></span>
            </span>
          </div>';
    }

    $sparklePos = [[22, 18, 0], [34, 78, 1.2], [52, 58, 2.1], [18, 44, 3], [66, 26, 0.8], [44, 88, 2.6], [28, 64, 1.8], [72, 72, 3.6], [38, 34, 2.9], [58, 12, 1.5], [14, 30, 4.2], [80, 50, 5]];
    $sparkles = implode('', array_map(fn ($s) => '<span style="top:' . $s[0] . '%;left:' . $s[1] . '%;--d:' . $s[2] . 's"></span>', $sparklePos));

    $categoryArches = implode('', array_map(fn ($c) => '
      <a class="cat-arch" href="' . e($c['slug']) . '.html" data-reveal>
        <span class="cat-arch__frame"><span class="cat-arch__img"><img src="' . e($c['image']) . '" alt="" loading="lazy" width="900" height="1200"></span></span>
        <span class="cat-arch__name">' . e($c['name']) . '</span>
        <span class="cat-arch__count">' . count_live(fn ($p) => $p['category'] === $c['slug']) . ' designs</span>
      </a>', nav_categories()));

    $bento = implode('', array_map(fn ($c) => '
      <a class="bento__item" href="' . e($c['href']) . '" data-reveal="zoom">
        <img src="' . e($c['image']) . '" alt="" loading="lazy" style="object-position:' . e($c['image_pos']) . '">
        <span class="bento__tag">' . $c['n'] . ' pieces</span>
        <span class="bento__body"><span class="bento__kicker">' . e($c['kicker']) . '</span><span class="bento__title">' . e($c['title']) . '</span><span class="bento__cta">Explore ' . site_icon('arrow-right') . '</span></span>
      </a>', collections(true)));

    $testimonials = rows('SELECT * FROM testimonials WHERE on_home = 1 ORDER BY sort_order, id');
    $testimonialSlides = '';
    $testimonialDots = '';
    foreach ($testimonials as $i => $t) {
        $testimonialSlides .= '
      <figure class="tslide' . ($i === 0 ? ' is-active' : '') . '" aria-hidden="' . ($i !== 0 ? 'true' : 'false') . '">
        <div class="tslide__mark" aria-hidden="true">&ldquo;</div>
        <blockquote>' . e($t['quote']) . '</blockquote>
        <figcaption><span class="tslide__avatar" aria-hidden="true">' . e(initials($t['who'])) . '</span><span><span class="tslide__who">' . e($t['who']) . '</span><span class="tslide__occasion">' . e($t['occasion']) . '</span></span></figcaption>
      </figure>';
        $testimonialDots .= '<button type="button" data-dot aria-label="Show story ' . ($i + 1) . '" aria-current="' . ($i === 0 ? 'true' : 'false') . '"></button>';
    }

    $stories = array_map('article_public', array_values(array_filter(articles(true), fn ($a) => (int) $a['on_home'] === 1)));
    $journalMag = '';
    if ($stories) {
        $lead = $stories[0];
        $journalMag = '
      <a class="jcard" href="article.html?slug=' . e($lead['slug']) . '" data-reveal="left">
        <div class="jcard__media"><img src="' . e($lead['image']) . '" alt="" loading="lazy" style="object-position:' . e($lead['imagePosition']) . '"><span class="jcard__cat">' . e($lead['category']) . '</span></div>
        <p class="jcard__meta">' . e($lead['date']) . ' · ' . e($lead['readTime']) . '</p>
        <h3 class="jcard__title">' . e($lead['title']) . '</h3>
        <p class="jcard__excerpt">' . e($lead['excerpt']) . '</p>
        <span class="link-arrow">Read the story ' . site_icon('arrow-right') . '</span>
      </a>
      <div class="journal-mag__side" data-stagger>' . implode('', array_map(fn ($a) => '
        <a class="jcard jcard--row" href="article.html?slug=' . e($a['slug']) . '" data-reveal="right">
          <div class="jcard__media"><img src="' . e($a['image']) . '" alt="" loading="lazy" style="object-position:' . e($a['imagePosition']) . '"></div>
          <div><p class="jcard__meta">' . e($a['category']) . ' · ' . e($a['readTime']) . '</p><h3 class="jcard__title">' . e($a['title']) . '</h3></div>
        </a>', array_slice($stories, 1, 3))) . '
      </div>';
    }

    $instagram = e(setting('instagram', ''));
    $instaTiles = implode('', array_map(fn ($n) => '
      <a class="insta__item" href="' . $instagram . '" target="_blank" rel="noopener" aria-label="Mangalam Jewellers on Instagram" data-reveal>
        <img src="assets/images/campaign/insta-' . $n . '.jpg" alt="" loading="lazy">' . site_icon('instagram') . '
      </a>', [1, 2, 3, 4, 5, 6]));

    $tickerItems = implode('', array_map(fn ($w) => '<li>' . $w . '</li>', ['Gold', 'Diamond', 'Polki', 'Kundan', 'Temple', 'Bridal', 'Heritage']));

    // The choker shown in the closer-look photograph
    $closer = product_by_slug('polki-bridal-choker') ?? ($live[0] ?? null);

    $heroImage = (string) setting('hero_image', 'assets/images/campaign/hero.jpg');
    $heroStats = implode("\n", array_map(fn ($s) => '            <li><strong>' . e($s[0]) . '</strong><span>' . e($s[1]) . '</span></li>', (array) setting('hero_stats', [])));
    $panels = (array) setting('bridal_panels', []);
    $bridalPanels = implode("\n", array_map(fn ($i) => '          <a class="panel' . ($i === 0 ? ' is-active' : '') . '" href="' . e($panels[$i]['href'] ?: 'bridal.html') . '" data-reveal="mask">
            <img src="' . e($panels[$i]['image']) . '" alt="" loading="lazy" style="object-position: ' . e($panels[$i]['pos'] ?: '50% 50%') . '">
            <span class="panel__num">' . str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) . '</span>
            <span class="panel__label">' . e($panels[$i]['label']) . '</span>
            <span class="panel__body"><span class="panel__title">' . e($panels[$i]['title']) . '</span><span class="panel__text">' . e($panels[$i]['text']) . '</span><span class="panel__cta">' . e($panels[$i]['cta']) . ' ' . site_icon('arrow-right') . '</span></span>
          </a>', array_keys($panels)));

    return [
        'heroHotspots' => implode("\n          ", $pins), 'sparkles' => $sparkles, 'categoryArches' => $categoryArches, 'bento' => $bento,
        'testimonialSlides' => $testimonialSlides, 'testimonialDots' => $testimonialDots, 'journalMag' => $journalMag,
        'instaTiles' => $instaTiles, 'tickerItems' => $tickerItems,
        'closerName' => $closer ? e($closer['name']) : '', 'closerPrice' => $closer ? product_price_text($closer) : '', 'closerSlug' => $closer ? e($closer['slug']) : '',
        'heroImage' => e($heroImage), 'heroAlt' => e(media_alt($heroImage)),
        'heroEyebrow' => e(setting('hero_eyebrow', '')), 'heroLine1' => star((string) setting('hero_line1', ''), 'text-metal'),
        'heroLine2' => star((string) setting('hero_line2', ''), 'text-metal'), 'heroLead' => e(setting('hero_lead', '')),
        'heroStats' => $heroStats, 'bridalPanels' => $bridalPanels,
    ];
}

/** The homepage template with its sections in the chosen order, leaving out the hidden ones */
function home_template(): string
{
    $parts = preg_split('/^<!--section:([a-z]+)-->\n/m', read_view('site', 'pages/home.html'), -1, PREG_SPLIT_DELIM_CAPTURE);
    $sections = [];
    for ($i = 1; $i < count($parts); $i += 2) $sections[$parts[$i]] = $parts[$i + 1];
    $out = $parts[0];
    $seen = [];
    foreach ((array) setting('home_sections', []) as $s) {
        if (!isset($sections[$s['key']])) continue;
        $seen[$s['key']] = true;
        if (!empty($s['visible']) || $s['key'] === 'hero') $out .= $sections[$s['key']];
    }
    foreach ($sections as $key => $html) if (empty($seen[$key])) $out .= $html; // sections added to the template later
    return $out;
}

function media_alt(string $path): string
{
    return (string) (val('SELECT alt FROM media WHERE path = ?', [$path]) ?? '');
}

/** "₹ 1,23,000", or "Price on request" when the price is hidden */
function product_price_text(array $p): string
{
    if (!(int) $p['show_price']) return 'Price on request';
    return inr($p['offer_price'] !== null ? $p['offer_price'] : $p['price']);
}

/* ---------- Other pages ---------- */

function collection_cards(): string
{
    $list = collections(false);
    return implode('', array_map(fn ($i) => '
      <a class="coll-card" href="' . e($list[$i]['href']) . '" data-reveal>
        <img src="' . e($list[$i]['image']) . '" alt="" loading="lazy" style="object-position:' . e($list[$i]['image_pos']) . '">
        <span class="coll-card__body"><span class="coll-card__num">' . str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) . ' · ' . $list[$i]['n'] . ' pieces</span><span class="coll-card__title">' . e($list[$i]['title']) . '</span><span class="coll-card__text">' . e($list[$i]['kicker']) . '</span><span class="coll-card__cta">Explore ' . site_icon('arrow-right') . '</span></span>
      </a>', array_keys($list)));
}

function journal_fragments(): array
{
    $stories = array_map('article_public', articles(true));
    if (!$stories) return ['journalFeature' => '', 'journalChips' => '', 'journalGrid' => '<p class="section-lead">New stories are on their way.</p>'];
    $lead = $stories[0];
    $cats = array_values(array_unique(array_column($stories, 'category')));
    return [
        'journalFeature' => '
      <a class="jcard feature-post" href="article.html?slug=' . e($lead['slug']) . '" data-reveal>
        <div class="jcard__media"><img src="' . e($lead['image']) . '" alt="" style="object-position:' . e($lead['imagePosition']) . '"><span class="jcard__cat">Featured story</span></div>
        <div>
          <p class="jcard__meta">' . e($lead['category']) . ' · ' . e($lead['date']) . ' · ' . e($lead['readTime']) . '</p>
          <h2 class="jcard__title">' . e($lead['title']) . '</h2>
          <p class="jcard__excerpt">' . e($lead['excerpt']) . '</p>
          <span class="link-arrow">Read the story ' . site_icon('arrow-right') . '</span>
        </div>
      </a>',
        'journalChips' => '<button type="button" class="chip chip--text is-active" data-journal-filter="all" aria-pressed="true">All stories</button>'
            . implode('', array_map(fn ($c) => '<button type="button" class="chip chip--text" data-journal-filter="' . e($c) . '" aria-pressed="false">' . e($c) . '</button>', $cats)),
        'journalGrid' => implode('', array_map(fn ($a) => '
      <a class="jcard" href="article.html?slug=' . e($a['slug']) . '" data-cat="' . e($a['category']) . '" data-reveal>
        <div class="jcard__media"><img src="' . e($a['image']) . '" alt="" loading="lazy" style="object-position:' . e($a['imagePosition']) . '"><span class="jcard__cat">' . e($a['category']) . '</span></div>
        <p class="jcard__meta">' . e($a['date']) . ' · ' . e($a['readTime']) . '</p>
        <h2 class="jcard__title">' . e($a['title']) . '</h2>
        <p class="jcard__excerpt">' . e($a['excerpt']) . '</p>
      </a>', array_slice($stories, 1))),
    ];
}

function catalog_chips(string $active): string
{
    $all = '<a class="chip chip--text" href="jewellery.html"' . ($active === '' ? ' aria-current="page"' : '') . '>All jewellery</a>';
    return $all . implode('', array_map(fn ($c) =>
        '<a class="chip" href="' . e($c['slug']) . '.html"' . ($active === $c['slug'] ? ' aria-current="page"' : '') . '><img src="' . e($c['image']) . '" alt="" width="34" height="34">' . e($c['name']) . '</a>', nav_categories()));
}

/** The heading, introduction and banner of one of the website's own pages */
function page_vars(string $key): array
{
    $pg = page_row($key);
    return ['pageHeading' => star((string) $pg['heading']), 'pageLead' => e($pg['lead'] ?? ''), 'pageBanner' => e($pg['banner'])];
}

/**
 * Works out which page an address asks for.
 * @return array|null the page, or null when there is no such page
 */
function site_page(string $route): ?array
{
    $route = $route === '' ? 'index' : $route;
    $seo = function (string $key) { $pg = page_row($key); return ['title' => $pg['seo_title'], 'description' => $pg['seo_desc']]; };
    switch ($route) {
        case 'index':
            return ['page' => 'home', 'nav' => 'home', 'main' => 'home', 'vars' => home_fragments()] + $seo('index');
        case 'collections':
            return ['page' => 'collections', 'nav' => 'collections', 'main' => 'collections', 'vars' => ['collectionCards' => collection_cards()] + page_vars('collections')] + $seo('collections');
        case 'jewellery':
            $pg = page_row('jewellery');
            return ['page' => 'catalog', 'nav' => 'jewellery', 'main' => 'catalog', 'modals' => ['modal-filters'], 'vars' => [
                'category' => '', 'crumb' => 'All jewellery', 'heading' => star((string) $pg['heading']), 'lead' => e($pg['lead']),
                'heroImage' => e($pg['banner']), 'total' => (string) count(products(true)), 'chips' => catalog_chips(''),
            ]] + $seo('jewellery');
        case 'product':
            $p = product_by_slug((string) ($_GET['slug'] ?? ''));
            $meta = $seo('product');
            if ($p) $meta = ['title' => $p['seo_title'] ?: $p['name'] . ' — Mangalam Jewellers', 'description' => $p['seo_desc'] ?: ($p['summary'] ?: $meta['description'])];
            return ['page' => 'product', 'nav' => 'jewellery', 'main' => 'product', 'solid' => true, 'modals' => ['modal-enquire'], 'vars' => []] + $meta;
        case 'about':
        case 'craftsmanship':
        case 'contact':
            return ['page' => $route, 'nav' => $route, 'main' => $route, 'vars' => page_vars($route)] + $seo($route);
        case 'journal':
            return ['page' => 'journal', 'nav' => 'journal', 'main' => 'journal', 'vars' => journal_fragments() + page_vars('journal')] + $seo('journal');
        case 'article':
            $a = null;
            foreach (articles(true) as $x) if ($x['slug'] === ($_GET['slug'] ?? '')) $a = $x;
            $meta = $seo('article');
            if ($a) $meta = ['title' => $a['seo_title'] ?: $a['title'] . ' — Mangalam Journal', 'description' => $a['seo_desc'] ?: $a['excerpt']];
            return ['page' => 'article', 'nav' => 'journal', 'main' => 'article', 'vars' => []] + $meta;
        case '404':
            return ['page' => 'not-found', 'nav' => '', 'main' => '404', 'vars' => page_vars('404')] + $seo('404');
    }
    $c = category_by_slug($route);
    if ($c) {
        return [
            'page' => 'catalog', 'nav' => $c['slug'] === 'bridal' ? 'bridal' : 'jewellery', 'main' => 'catalog', 'category' => $c['slug'], 'modals' => ['modal-filters'],
            'title' => $c['seo_title'] ?: $c['name'] . ' — Mangalam Jewellers', 'description' => $c['seo_desc'] ?: (string) $c['intro'],
            'vars' => [
                'category' => e($c['slug']), 'crumb' => e($c['name']), 'heading' => star($c['heading'] ?: $c['name']), 'lead' => e($c['intro']),
                'heroImage' => e($c['banner']), 'total' => (string) count_live(fn ($p) => $p['category'] === $c['slug']), 'chips' => catalog_chips($c['slug']),
            ],
        ];
    }
    return null;
}

function render_site_page(array $p): string
{
    $icon = fn ($name, $cls) => site_icon($name, $cls);
    $vars = site_vars() + site_fragments() + ($p['vars'] ?? []);
    $tpl = $p['main'] === 'home' ? home_template() : read_view('site', 'pages/' . $p['main'] . '.html');
    $main = render_template($tpl, $vars, 'site', $icon);
    $out = render_template(read_view('site', 'partials/layout.html'), $vars + [
        'title' => e($p['title']),
        'description' => e($p['description']),
        'page' => $p['page'],
        'bodyAttrs' => !empty($p['category']) ? ' data-category="' . e($p['category']) . '"' : '',
        'headerClass' => !empty($p['solid']) ? ' is-solid' : '',
        'main' => $main,
        'extraModals' => implode("\n", array_map(fn ($m) => render_template(read_view('site', 'partials/' . $m . '.html'), $vars, 'site', $icon), $p['modals'] ?? [])),
        'robots' => setting('indexable', true) ? '' : "\n  <meta name=\"robots\" content=\"noindex, nofollow\">",
        'loader' => setting('loader', true) ? render_template(read_view('site', 'partials/loader.html'), [], 'site', $icon) : '',
        'offer' => offer_is_live() ? render_template(read_view('site', 'partials/offer.html'), $vars + offer_vars(), 'site', $icon) : '',
        'dataVersion' => content_version(),
        'cssVersion' => asset_version('assets/css/mangalam.css'), 'jsVersion' => asset_version('assets/js/main.js'), 'uiVersion' => asset_version('assets/js/ui.js'),
    ], 'site', $icon);
    if (!empty($p['nav'])) $out = str_replace('data-nav="' . $p['nav'] . '"', 'data-nav="' . $p['nav'] . '" aria-current="page"', $out);
    return $out;
}

function offer_is_live(): bool
{
    if (!setting('offer_on', true)) return false;
    $today = date('Y-m-d');
    $start = (string) setting('offer_start', '');
    $end = (string) setting('offer_end', '');
    return ($start === '' || $start <= $today) && ($end === '' || $end >= $today);
}

function offer_vars(): array
{
    return [
        'offerEyebrow' => e(setting('offer_eyebrow', '')), 'offerTitle1' => e(setting('offer_title1', '')), 'offerTitle2' => e(setting('offer_title2', '')),
        'offerText' => e(setting('offer_text', '')), 'offerCta' => e(setting('offer_cta', '')), 'offerNote' => e(setting('offer_note', '')),
        'offerDelay' => (string) (int) round((float) setting('offer_delay', 1.5) * 1000), 'offerSeconds' => (string) max(1, (int) setting('offer_seconds', 7)),
        'offerOnce' => setting('offer_once', true) ? '1' : '0', 'offerTab' => setting('offer_tab', true) ? '1' : '0',
    ];
}

function render_maintenance(): string
{
    return render_template(read_view('site', 'pages/maintenance.html'), [
        'message' => nl2br(e(setting('maintenance_message', ''))), 'storeName' => e(setting('store_name', 'Mangalam Jewellers')),
    ] + site_vars(), 'site', fn ($n, $c) => site_icon($n, $c));
}

/* ---------- window.MJ: the catalogue, journal and testimonials for the website's scripts ---------- */

function site_data_js(): string
{
    $img = ['hero' => (string) setting('hero_image', 'assets/images/campaign/hero.jpg'), 'craft' => 'assets/images/mangalam-craft.jpg'];
    $names = [];
    $copy = [];
    foreach (categories() as $c) { $img[$c['slug']] = $c['image']; $names[$c['slug']] = $c['name']; $copy[$c['slug']] = (string) $c['intro']; }
    $data = [
        'IMG' => $img,
        'categories' => array_column(nav_categories(), 'slug'),
        'categoryNames' => $names,
        'categoryCopy' => $copy,
        'products' => array_map('product_public', products(true)),
        'featuredSlugs' => array_column(featured_products(true), 'slug'),
        'articles' => array_map('article_public', articles(true)),
        'testimonials' => array_map(fn ($t) => ['quote' => $t['quote'], 'who' => $t['who'], 'occasion' => $t['occasion']], rows('SELECT * FROM testimonials WHERE on_home = 1 ORDER BY sort_order, id')),
        'settings' => ['smoothScroll' => (bool) setting('smooth_scroll', true), 'calmMotion' => (bool) setting('calm_motion', false)],
    ];
    return "/* Mangalam Jewellers — the catalogue, journal and testimonials, read from the database. */\n"
        . "(function (root) {\n  \"use strict\";\n  var MJ = " . script_json($data) . ";\n"
        . "  MJ.small = function (src) { return src.replace(/\\.(jpe?g|png|webp|gif)$/i, \"-sm.\$1\"); };\n"
        . "  MJ.capitalize = function (v) { return v.charAt(0).toUpperCase() + v.slice(1); };\n"
        . "  MJ.categoryName = function (slug) { return MJ.categoryNames[slug] || MJ.capitalize(slug || \"\"); };\n"
        . "  MJ.formatPrice = function (price) { return \"₹ \" + Number(price).toLocaleString(\"en-IN\"); };\n"
        . "  root.MJ = MJ;\n})(typeof window !== \"undefined\" ? window : globalThis);\n";
}
