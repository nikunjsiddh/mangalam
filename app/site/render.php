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
            '<li><a href="' . e($c['slug']) . '.html"><span class="mega__thumb"><img src="' . e(small_or_full($c['image'])) . '" alt="" loading="lazy" width="900" height="1200"></span>' . e($c['name']) . '</a></li>', $cats)),
        // The mobile menu and the footer list the jewellery categories; Bridal has its own link beside them
        'menuCats' => implode('', array_map(fn ($c) => '<li><a href="' . e($c['slug']) . '.html">' . e($c['name']) . '</a></li>', array_filter($cats, fn ($c) => $c['slug'] !== 'bridal'))),
        'footerCats' => implode("\n", array_map(fn ($c) => '            <li><a href="' . e($c['slug']) . '.html">' . e($c['name']) . '</a></li>', array_filter($cats, fn ($c) => $c['slug'] !== 'bridal'))),
        // Shown twice over so the marquee never runs dry
        'topbarItems' => implode('', array_map(fn ($t) => '<li>' . e($t) . '</li>', array_merge($announcements, $announcements))),
        // The Offers page is in the menus while at least one category offer is live
        'navOffers' => category_offers(true) ? '<li><a class="nav__link nav__link--offers" data-nav="offers" href="offers.html">Offers</a></li>' : '',
        'menuOffers' => category_offers(true) ? '<li style="--i:3.5"><a data-nav="offers" href="offers.html">Offers <span class="menu__badge">Up to ' . max(array_map(fn ($o) => (int) $o['percent'], category_offers(true))) . '%</span></a></li>' : '',
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
        <span class="cat-arch__frame"><span class="cat-arch__img"><img src="' . e(small_or_full($c['image'])) . '" alt="" loading="lazy" width="900" height="1200"></span></span>
        <span class="cat-arch__name">' . e($c['name']) . '</span>
        <span class="cat-arch__count">' . plural(count_live(fn ($p) => $p['category'] === $c['slug']), 'design') . '</span>
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
        <div class="jcard__media"><img src="' . e($lead['image']) . '"' . srcset($lead['image'], '(max-width: 1023px) 100vw, 55vw') . ' alt="" loading="lazy" style="object-position:' . e($lead['imagePosition']) . '"><span class="jcard__cat">' . e($lead['category']) . '</span></div>
        <p class="jcard__meta">' . e($lead['date']) . ' · ' . e($lead['readTime']) . '</p>
        <h3 class="jcard__title">' . e($lead['title']) . '</h3>
        <p class="jcard__excerpt">' . e($lead['excerpt']) . '</p>
        <span class="link-arrow">Read the story ' . site_icon('arrow-right') . '</span>
      </a>
      <div class="journal-mag__side" data-stagger>' . implode('', array_map(fn ($a) => '
        <a class="jcard jcard--row" href="article.html?slug=' . e($a['slug']) . '" data-reveal="right">
          <div class="jcard__media"><img src="' . e(small_or_full($a['image'])) . '" alt="" loading="lazy" style="object-position:' . e($a['imagePosition']) . '"></div>
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
    $closer = product_by_slug('emerald-kundan-bridal-choker') ?? product_by_slug('polki-bridal-choker') ?? ($live[0] ?? null);

    $heroImage = (string) setting('hero_image', 'assets/images/campaign/hero.jpg');
    $heroStats = implode("\n", array_map(fn ($s) => '            <li><strong>' . e($s[0]) . '</strong><span>' . e($s[1]) . '</span></li>', (array) setting('hero_stats', [])));
    $panels = (array) setting('bridal_panels', []);
    $bridalPanels = implode("\n", array_map(fn ($i) => '          <a class="panel' . ($i === 0 ? ' is-active' : '') . '" href="' . e($panels[$i]['href'] ?: 'bridal.html') . '" data-reveal="mask">
            <img src="' . e($panels[$i]['image']) . '" alt="" loading="lazy" style="object-position: ' . e($panels[$i]['pos'] ?: '50% 50%') . '">
            <span class="panel__num">' . str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) . '</span>
            <span class="panel__label">' . e($panels[$i]['label']) . '</span>
            <span class="panel__body"><span class="panel__title">' . e($panels[$i]['title']) . '</span><span class="panel__text">' . e($panels[$i]['text']) . '</span><span class="panel__cta">' . e($panels[$i]['cta']) . ' ' . site_icon('arrow-right') . '</span></span>
          </a>', array_keys($panels)));

    // The signature showcase's tabs: every piece (signature pieces first), each category that has pieces, and what's new.
    // main.js draws the cards for the chosen tab from data.js.
    $tabs = [['all', 'Signature', count($live)]];
    foreach (nav_categories() as $c) {
        $n = count_live(fn ($p) => $p['category'] === $c['slug']);
        if ($n) $tabs[] = [$c['slug'], $c['name'], $n];
    }
    $new = count_live(fn ($p) => (int) $p['is_new'] === 1);
    if ($new) $tabs[] = ['new', 'New in', $new];
    $showcaseTabs = implode('', array_map(fn ($i, $t) => '
          <button type="button" class="tabs__btn" role="tab" id="showcase-tab-' . e($t[0]) . '" aria-selected="' . ($i === 0 ? 'true' : 'false') . '" aria-controls="featured-grid"' . ($i === 0 ? '' : ' tabindex="-1"')
            . ' data-tab="' . e($t[0]) . '" data-href="' . ($t[0] === 'all' || $t[0] === 'new' ? 'jewellery.html' : e($t[0]) . '.html') . '" data-label="' . e($t[0] === 'all' || $t[0] === 'new' ? 'jewellery' : mb_strtolower($t[1])) . '">'
            . ($i === 0 ? site_icon('sparkles') : '') . e($t[1]) . '<span class="tabs__count">' . $t[2] . '</span></button>', array_keys($tabs), $tabs));

    return [
        'showcaseTabs' => $showcaseTabs, 'showcaseTotal' => (string) count($live),
        'heroHotspots' => implode("\n          ", $pins), 'sparkles' => $sparkles, 'categoryArches' => $categoryArches, 'categoryCount' => (string) count(nav_categories()), 'bento' => $bento,
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
        <div class="jcard__media"><img src="' . e($lead['image']) . '"' . srcset($lead['image'], '(max-width: 900px) 100vw, 60vw') . ' alt="" style="object-position:' . e($lead['imagePosition']) . '"><span class="jcard__cat">Featured story</span></div>
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
        <div class="jcard__media"><img src="' . e($a['image']) . '"' . srcset($a['image'], '(max-width: 767px) 100vw, (max-width: 1023px) 50vw, 33vw') . ' alt="" loading="lazy" style="object-position:' . e($a['imagePosition']) . '"><span class="jcard__cat">' . e($a['category']) . '</span></div>
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
        '<a class="chip" href="' . e($c['slug']) . '.html"' . ($active === $c['slug'] ? ' aria-current="page"' : '') . '><img src="' . e(small_or_full($c['image'])) . '" alt="" width="34" height="34">' . e($c['name']) . '</a>', nav_categories()));
}

/* ---------- Offers ---------- */

/** The pieces an offer covers — its category's, or the featured pieces for an all-jewellery offer — that have photographs */
function offer_pieces(array $o, int $max): array
{
    $list = $o['category'] !== '' ? array_filter(products(true), fn ($p) => $p['category'] === $o['category']) : featured_products(true);
    return array_slice(array_values(array_filter($list, fn ($p) => (bool) $p['gallery'])), 0, $max);
}

/** What an offer's gallery shows: its own photographs, then (when chosen) its pieces, each with a caption */
function offer_gallery(array $o): array
{
    $title = star_plain($o['title']);
    $shot = fn ($src, $thumb, $caption, $sub, $href = '') => ['src' => $src, 'thumb' => $thumb, 'caption' => $caption, 'sub' => $sub, 'href' => $href];
    $shots = array_map(fn ($src) => $shot($src, $src, $title, $o['category_name']), $o['images']);
    if ((int) $o['with_products']) {
        foreach (offer_pieces($o, 12) as $p) $shots[] = $shot($p['image'], $p['thumb'], $p['name'], product_price_text($p), 'product.html?slug=' . $p['slug']);
    }
    // Nothing uploaded and no pieces yet: the category's own photographs stand in
    if (!$shots) foreach (array_unique(array_filter([$o['category_banner'], $o['category_image']])) as $src) $shots[] = $shot($src, $src, $title, $o['category_name']);
    return $shots;
}

/** "Ends 15 Dec", or how long it runs when it has no last day */
function offer_until(array $o): string
{
    if (!$o['ends_on']) return 'Limited period';
    $days = (int) (new DateTimeImmutable(date('Y-m-d')))->diff(new DateTimeImmutable($o['ends_on']))->days;
    return $days === 0 ? 'Ends today' : ($days === 1 ? 'Ends tomorrow' : 'Ends ' . to_date($o['ends_on'])->format('j M'));
}

/** The deal in three parts for the big type: ["Up to", "25%", "off making charges"] */
function offer_deal_parts(array $o): array
{
    return [$o['deal_type'] === 'flat' ? 'Flat' : 'Up to', (int) $o['percent'] . '%', 'off' . ($o['deal_on'] !== '' ? ' ' . $o['deal_on'] : '')];
}

/**
 * One offer on the Offers page: the photo mosaic with its seal, and the offer beside it.
 * @param array $s the page's settings (offers_page_settings): the feature, countdown, pieces and viewing button
 */
function offer_block(array $o, int $i, int $count, array $shots, array $s): string
{
    [$pre, $pct, $post] = offer_deal_parts($o);
    $title = star_plain($o['title']);
    $feature = $i === 0 && $s['feature'];
    $shown = array_slice($shots, 0, 5);
    $more = count($shots) - count($shown);
    $tiles = implode('', array_map(fn ($k) => '
            <button type="button" class="offer-mosaic__tile" data-gallery-open="' . $i . '" data-gallery-at="' . $k . '" aria-label="' . e($title) . ': open photo ' . ($k + 1) . ' of ' . count($shots) . '">
              <img src="' . e($k === 0 ? $shown[$k]['src'] : $shown[$k]['thumb']) . '" alt="" loading="lazy">'
            . ($k === 0 ? '<span class="offer-mosaic__label" aria-hidden="true">' . site_icon('images') . 'View gallery <span>' . count($shots) . '</span></span>' : '')
            . ($k === count($shown) - 1 && $more > 0 ? '<span class="offer-mosaic__more">+' . $more . '<small>photos</small></span>' : '') . '
            </button>', array_keys($shown)));
    $pieces = $s['pieces'] > 0 ? offer_pieces($o, (int) $s['pieces']) : [];
    $total = $o['category'] !== '' ? count_live(fn ($p) => $p['category'] === $o['category']) : count(products(true));
    $piecesHtml = $pieces ? '
          <div class="offer-show__pieces">
            <p class="offer-show__label">In this offer · ' . plural($total, 'design') . '</p>
            <ul>' . implode('', array_map(fn ($p) => '
              <li><a href="product.html?slug=' . e($p['slug']) . '"><img src="' . e($p['thumb']) . '" alt="" loading="lazy" style="object-position:' . e($p['image_position']) . '"><span class="offer-show__piece">' . e($p['name']) . '</span><span class="offer-show__price">' . product_price_text($p) . '</span></a></li>', $pieces)) . '
            </ul>
          </div>' : '';
    $timer = !$s['timer']
        ? ($o['ends_on'] ? '<div class="offer-timer offer-timer--open"><span class="offer-timer__label">' . site_icon('clock') . 'Limited period</span><span class="offer-timer__date">Until ' . long_date($o['ends_on']) . '</span></div>' : '')
        : ($o['ends_on']
        ? '<div class="offer-timer" data-ends="' . e($o['ends_on']) . 'T23:59:59+05:30" role="timer" aria-label="The offer runs until ' . long_date($o['ends_on']) . '">
            <span class="offer-timer__label">' . site_icon('clock') . 'Ends in</span>
            <span class="offer-timer__units" aria-hidden="true"><span><strong data-unit="d">–</strong>days</span><span><strong data-unit="h">–</strong>hrs</span><span><strong data-unit="m">–</strong>min</span><span><strong data-unit="s">–</strong>sec</span></span>
            <span class="offer-timer__date">Until ' . long_date($o['ends_on']) . '</span>
          </div>'
        : '<div class="offer-timer offer-timer--open"><span class="offer-timer__label">' . site_icon('sparkles') . 'Limited period offer</span><span class="offer-timer__date">While the season lasts</span></div>');
    $cta = $o['cta'] !== '' ? $o['cta'] : ($o['category'] !== '' ? 'Shop ' . mb_strtolower($o['category_name']) : 'Shop all jewellery');
    return '
      <article class="offer-show' . ($feature ? ' offer-show--feature' : '') . ($i % 2 ? ' offer-show--flip' : '') . '" id="' . e($o['anchor']) . '" data-offer-item data-cat="' . e($o['category'] !== '' ? $o['category'] : 'jewellery') . '">
        <div class="offer-show__media" data-reveal="' . ($i % 2 ? 'right' : 'left') . '">
          <div class="offer-mosaic offer-mosaic--' . count($shown) . '">' . $tiles . '
          </div>
          <span class="offer-seal" aria-hidden="true">
            <svg class="offer-seal__ring" viewBox="0 0 120 120"><defs><path id="seal-path-' . (int) $o['id'] . '" d="M60,60 m-47,0 a47,47 0 1,1 94,0 a47,47 0 1,1 -94,0"/></defs><text><textPath href="#seal-path-' . (int) $o['id'] . '" textLength="292">MANGALAM ✦ ' . e(mb_strtoupper($o['category_name'])) . ' OFFER ✦ </textPath></text></svg>
            <span class="offer-seal__core"><small>' . e($pre) . '</small><strong>' . e($pct) . '</strong><small>off</small></span>
          </span>
        </div>
        <div class="offer-show__body" data-reveal="' . ($i % 2 ? 'left' : 'right') . '">
          <p class="offer-show__count"><span>' . str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) . '</span> / ' . str_pad((string) $count, 2, '0', STR_PAD_LEFT) . '</p>
          <a class="offer-show__cat" href="' . e($o['href']) . '">' . ($o['category_image'] ? '<img src="' . e($o['category_image']) . '" alt="" width="34" height="34">' : '<span class="offer-show__cat-icon">' . site_icon('gem') . '</span>') . e($o['category_name']) . '</a>
          ' . ($o['kicker'] !== '' ? '<p class="eyebrow' . ($feature ? ' eyebrow--light' : '') . '">' . e($o['kicker']) . '</p>' : '') . '
          <h2 class="offer-show__title">' . star($o['title']) . '</h2>
          <p class="offer-show__deal"><span class="offer-show__pre">' . e($pre) . '</span><strong class="text-metal">' . e($pct) . '</strong><span class="offer-show__post">' . e($post) . '</span></p>
          ' . ($o['text'] !== '' ? '<p class="offer-show__text">' . e($o['text']) . '</p>' : '') . '
          ' . $timer . '
          <div class="offer-show__actions">
            <a class="btn ' . ($feature ? 'btn--gold' : 'btn--primary') . '" href="' . e($o['href']) . '">' . e($cta) . ' ' . site_icon('arrow-right') . '</a>'
            . ($s['viewing'] ? '
            <button type="button" class="btn ' . ($feature ? 'btn--light' : 'btn--outline') . '" data-open="appointment">' . site_icon('calendar') . ' Book a viewing</button>' : '') . '
          </div>' . $piecesHtml . '
          ' . ($o['terms'] !== '' ? '<p class="offer-show__terms">* ' . e($o['terms']) . '</p>' : '') . '
        </div>
      </article>';
}

function offers_fragments(): array
{
    $s = offers_page_settings();
    $offers = category_offers(true);
    $galleries = [];
    $blocks = '';
    foreach ($offers as $i => $o) {
        $shots = offer_gallery($o);
        $galleries[] = ['title' => star_plain($o['title']), 'category' => $o['category_name'], 'deal' => offer_deal_text($o), 'href' => $o['href'], 'shots' => $shots];
        $blocks .= offer_block($o, $i, count($offers), $shots, $s);
    }
    // One chip per category (with its best saving), in the order the offers come
    $byCategory = [];
    foreach ($offers as $o) {
        $key = $o['category'] !== '' ? $o['category'] : 'jewellery';
        if (!isset($byCategory[$key])) $byCategory[$key] = $o;
        else $byCategory[$key]['percent'] = max((int) $byCategory[$key]['percent'], (int) $o['percent']);
    }
    $chips = $s['chips'] && count($byCategory) > 1
        ? '<button type="button" class="chip chip--text is-active" data-offer-filter="all" aria-pressed="true">' . e($s['chips_all']) . ' <span class="chip__count">' . count($offers) . '</span></button>'
            . implode('', array_map(fn ($key, $o) => '<button type="button" class="chip" data-offer-filter="' . e($key) . '" aria-pressed="false">'
                . ($o['category_image'] ? '<img src="' . e($o['category_image']) . '" alt="" width="34" height="34">' : '') . e($o['category_name']) . '<span class="chip__deal">' . (int) $o['percent'] . '%</span></button>', array_keys($byCategory), $byCategory))
        : '';
    $best = $offers ? max(array_map(fn ($o) => (int) $o['percent'], $offers)) : 0;
    $soonest = array_filter(array_column($offers, 'ends_on'));
    sort($soonest);
    $steps = array_values(array_filter($s['steps'], fn ($st) => trim((string) ($st['title'] ?? '')) !== ''));
    $stepsHtml = $s['steps_on'] && $steps ? '
    <section class="section section--pearl section--tight offer-steps" aria-labelledby="offer-steps-title">
      <div class="container">
        <header class="section-head">
          ' . ($s['steps_eyebrow'] !== '' ? '<p class="eyebrow eyebrow--center">' . e($s['steps_eyebrow']) . '</p>' : '') . '
          <h2 class="section-title" id="offer-steps-title">' . star($s['steps_title']) . '</h2>
        </header>
        <ol class="offer-steps__list offer-steps__list--' . count($steps) . '" data-stagger="0.12">' . implode('', array_map(fn ($k) => '
          <li data-reveal><span class="offer-steps__icon">' . site_icon(isset(OFFER_STEP_ICONS[$steps[$k]['icon'] ?? '']) ? $steps[$k]['icon'] : 'gem') . '</span><span class="offer-steps__num">' . str_pad((string) ($k + 1), 2, '0', STR_PAD_LEFT) . '</span><h3>' . e($steps[$k]['title']) . '</h3>'
            . (($steps[$k]['text'] ?? '') !== '' ? '<p>' . e($steps[$k]['text']) . '</p>' : '') . '</li>', array_keys($steps))) . '
        </ol>
      </div>
    </section>' : '';
    $ctaHtml = $s['cta_on'] ? '
    <section class="cta-final" aria-labelledby="cta-title" data-expand>
      <div class="container" data-reveal>
        <span class="brand-emblem" aria-hidden="true"></span>
        <h2 class="cta-final__title" id="cta-title">' . star($s['cta_title'], 'text-metal') . '</h2>
        ' . ($s['cta_text'] !== '' ? '<p class="section-lead section-lead--light" style="margin-inline:auto">' . e($s['cta_text']) . '</p>' : '') . '
        <div class="cta-final__actions">'
            . ($s['cta_primary'] !== '' ? '
          <button type="button" class="btn btn--gold" data-open="appointment">' . site_icon('calendar') . ' ' . e($s['cta_primary']) . '</button>' : '')
            . ($s['cta_secondary'] !== '' ? '
          <a class="btn btn--light" href="' . e($s['cta_href']) . '">' . e($s['cta_secondary']) . ' ' . site_icon('arrow-right') . '</a>' : '') . '
        </div>
      </div>
    </section>' : '';
    return [
        'offerBlocks' => $blocks, 'offerChips' => $chips, 'offerChipsHidden' => $chips === '' ? ' hidden' : '',
        'offerData' => script_json($galleries), 'offerEmptyHidden' => $offers ? ' hidden' : '',
        'offerEmptyTitle' => e($s['empty_title']), 'offerEmptyText' => e($s['empty_text']),
        'offerSteps' => $stepsHtml, 'offerClosing' => $ctaHtml,
        'offerStats' => $offers && $s['stats'] ? '
          <ul class="offer-stats">
            <li><strong>' . count($offers) . '</strong><span>' . (count($offers) === 1 ? 'Offer' : 'Category offers') . ' live</span></li>
            <li><strong>' . $best . '%</strong><span>Highest saving</span></li>
            <li><strong>' . ($soonest ? e(to_date($soonest[0])->format('j M')) : 'BIS') . '</strong><span>' . ($soonest ? 'Next offer ends' : 'Hallmarked') . '</span></li>
          </ul>' : '',
    ];
}

/** The strip above a catalogue page: its category's offer, or (on All jewellery) the way to every offer */
function offer_ribbon(string $category): string
{
    $o = offer_for_category($category);
    if ($o) {
        [$pre, $pct, $post] = offer_deal_parts($o);
        return '
    <div class="container">
      <a class="offer-ribbon" href="offers.html?category=' . e($o['category'] !== '' ? $o['category'] : 'jewellery') . '" data-reveal="fade">
        <span class="offer-ribbon__seal"><small>' . e($pre) . '</small>' . e($pct) . '</span>
        <span class="offer-ribbon__body">
          <span class="offer-ribbon__kicker">' . site_icon('sparkles') . e($o['kicker'] !== '' ? $o['kicker'] : $o['category_name'] . ' offer') . '</span>
          <span class="offer-ribbon__title">' . e($pre . ' ' . $pct . ' ' . $post) . ($o['category'] !== '' ? ' on ' . e(mb_strtolower($o['category_name'])) : '') . '</span>
          <span class="offer-ribbon__until">' . site_icon('clock') . e(offer_until($o)) . ($o['terms'] !== '' ? ' · ' . e($o['terms']) : '') . '</span>
        </span>
        <span class="offer-ribbon__cta">View offer &amp; gallery ' . site_icon('arrow-right') . '</span>
      </a>
    </div>';
    }
    $all = category_offers(true);
    if ($category !== '' || !$all) return '';
    $best = max(array_map(fn ($x) => (int) $x['percent'], $all));
    return '
    <div class="container">
      <a class="offer-ribbon offer-ribbon--all" href="offers.html" data-reveal="fade">
        <span class="offer-ribbon__seal"><small>Up to</small>' . $best . '%</span>
        <span class="offer-ribbon__body">
          <span class="offer-ribbon__kicker">' . site_icon('sparkles') . 'Offers of the season</span>
          <span class="offer-ribbon__title">' . plural(count($all), 'category offer') . ' on ' . e(implode(', ', array_map(fn ($x) => mb_strtolower($x['category_name']), array_slice($all, 0, 4)))) . (count($all) > 4 ? ' and more' : '') . '</span>
        </span>
        <span class="offer-ribbon__cta">See every offer ' . site_icon('arrow-right') . '</span>
      </a>
    </div>';
}

/* ---------- A customer's conversation (conversation.html?t=…) ---------- */

/** One message on the conversation page: the customer's on the right, the house's on the left */
function convo_message(bool $mine, string $who, string $when, string $text, string $via = '', string $initials = ''): string
{
    // *words* (bold in WhatsApp) show as bold here too
    $paras = implode('', array_map(fn ($p) => '<p>' . preg_replace('/\*([^*\n]+)\*/u', '<strong>$1</strong>', nl2br(e(trim($p)), false)) . '</p>', array_filter(preg_split('/\n\s*\n/', trim($text)), fn ($p) => trim($p) !== '')));
    return '
            <li class="convo-msg convo-msg--' . ($mine ? 'mine' : 'house') . '" data-reveal="' . ($mine ? 'right' : 'left') . '">
              <span class="convo-msg__avatar" aria-hidden="true">' . ($mine ? e($initials) : '<span class="brand-emblem"></span>') . '</span>
              <div class="convo-msg__card">
                <p class="convo-msg__meta"><strong>' . e($who) . '</strong><time datetime="' . e(date('c', strtotime($when))) . '">' . e(when_label($when)) . '</time>' . ($via !== '' ? '<span class="convo-msg__via">' . e($via) . '</span>' : '') . '</p>
                <div class="convo-msg__text">' . $paras . '</div>
              </div>
            </li>';
}

/** The page's heading, the messages, the reply form and the panel beside them */
function conversation_fragments(?array $en): array
{
    $store = (string) setting('store_name', 'Mangalam Jewellers');
    $phone = (string) setting('phone', '');
    $phoneHref = 'tel:' . preg_replace('/[^\d+]/', '', $phone);
    $wa = whatsapp_url((string) (setting('whatsapp', '') ?: $phone), $en ? 'Hello Mangalam — about my enquiry ' . enquiry_ref((int) $en['id']) : 'Hello Mangalam');
    $contactButtons = ($wa !== '' ? '<a class="btn btn--gold btn--sm" href="' . e($wa) . '" target="_blank" rel="noopener noreferrer">' . site_icon('whatsapp') . ' WhatsApp us</a>' : '')
        . '<a class="btn btn--light btn--sm" href="' . e($phoneHref) . '">' . site_icon('phone') . ' ' . e($phone) . '</a>';
    if (!$en) {
        return [
            'convoEyebrow' => 'Private conversation', 'convoTitle' => 'This link is <em>not complete</em>',
            'convoLead' => 'Part of the address may have been lost when it was copied. Open the link in our latest email again — or write to us, and we will send you a new one.',
            'convoBody' => '
        <div class="convo-missing" data-reveal>
          <span class="form-success__icon">' . site_icon('message-circle') . '</span>
          <div class="convo-missing__actions"><a class="btn btn--primary btn--sm" href="contact.html">Write to us ' . site_icon('arrow-right') . '</a>' . str_replace('btn--light', 'btn--outline', $contactButtons) . '</div>
        </div>',
        ];
    }
    // The customer has now seen what the team sent (the team's own visits don't count)
    if (!team_member_signed_in()) {
        q("UPDATE enquiry_replies SET seen_at = NOW() WHERE enquiry_id = ? AND direction = 'out' AND channel IN ('email', 'whatsapp', 'website') AND is_auto = 0 AND seen_at IS NULL", [$en['id']]);
    }
    $first = explode(' ', trim((string) $en['name']))[0];
    $initials = initials((string) $en['name']);
    $about = !empty($en['product_name']) ? 'About the ' . $en['product_name'] . '.'
        : ($en['subject'] !== '' ? 'About “' . preg_replace('/^\s*((re|fwd?)\s*:\s*)+/i', '', $en['subject']) . '”.' : 'Your ' . mb_strtolower($en['topic'] ?: 'general enquiry') . (str_ends_with(mb_strtolower($en['topic'] ?: 'enquiry'), 'enquiry') ? '' : ' enquiry') . '.');
    $items = [];
    $answered = false;
    if (trim((string) $en['message']) !== '') $items[] = convo_message(true, 'You', $en['created_at'], $en['message'], $en['source'] === 'email' ? 'By email' : '', $initials);
    foreach (conversation_messages((int) $en['id']) as $m) {
        // Notes, call notes and the automatic emails stay with the team
        if ($m['is_auto'] || in_array($m['channel'], ['note', 'phone'], true)) continue;
        $mine = $m['direction'] === 'in';
        if (!$mine) $answered = true;
        $via = $m['channel'] === 'whatsapp' ? 'On WhatsApp' : ($m['channel'] === 'email' ? ($mine ? 'By email' : ($m['emailed'] ? 'Also emailed to you' : '')) : '');
        $who = $mine ? 'You' : preg_replace('/\s*<[^>]*>$/', '', explode(' ', trim($m['author']))[0]) . ' · ' . $store;
        $items[] = convo_message($mine, $who, $m['created_at'], $m['message'], $via, $initials);
    }
    $waiting = $answered ? '' : '
            <li class="convo-wait" data-reveal>' . site_icon('clock') . '<p>Our concierge replies within one business day. The answer will appear here' . (valid_email((string) $en['email']) ? ', and reach you by email at <strong>' . e($en['email']) . '</strong>' : '') . '.</p></li>';

    $piece = '';
    $p = !empty($en['product_slug']) ? product_by_slug((string) $en['product_slug'], false) : null;
    if ($p) {
        $piece = '
          <a class="convo-piece" href="product.html?slug=' . e($p['slug']) . '" data-reveal>
            <span class="convo-piece__media"><img src="' . e($p['thumb']) . '" alt="' . e($p['name']) . '" loading="lazy"></span>
            <span class="convo-piece__body"><span class="eyebrow">The piece</span><strong>' . e($p['name']) . '</strong><span>' . e(product_price_text($p)) . '</span><span class="convo-piece__link">View the piece ' . site_icon('arrow-right') . '</span></span>
          </a>';
    }
    return [
        'convoEyebrow' => 'Private conversation · ' . enquiry_ref((int) $en['id']),
        'convoTitle' => 'Hello, <em>' . e($first) . '</em>.',
        'convoLead' => e($about) . ' Everything you and our concierge write to each other is kept here — read our replies and write to us again at any time.',
        'convoBody' => '
        <div class="convo__grid">
          <div class="convo__main">
            <ol class="convo__thread" aria-label="Messages">' . implode('', $items) . $waiting . '
            </ol>
            <form class="convo-reply" data-conversation id="reply" data-reveal>
              <input type="hidden" name="t" value="' . e($en['token']) . '">
              <label class="field"><textarea class="field__input" name="message" rows="4" required maxlength="4000" placeholder=" "></textarea><span class="field__label">Write to our concierge…</span></label>
              <div class="convo-reply__foot">
                <p>' . site_icon('mail') . (valid_email((string) $en['email']) ? 'You can also simply reply to any of our emails.' : 'We usually reply within one business day.') . '</p>
                <button type="submit" class="btn btn--primary">Send message ' . site_icon('arrow-right') . '</button>
              </div>
            </form>
          </div>
          <aside class="convo__side">' . $piece . '
            <div class="convo-help" data-reveal>
              <p class="eyebrow eyebrow--light">Sooner?</p>
              <h2>Talk to our <em>concierge</em></h2>
              <p>' . e(hours_short()) . '</p>
              <div class="convo-help__actions">' . $contactButtons . '<button type="button" class="btn btn--light btn--sm" data-open="appointment">' . site_icon('calendar') . ' Book a visit</button></div>
            </div>
            <p class="convo-private">' . site_icon('shield-check') . '<span>Only people with this link can open this conversation — please keep it to yourself.</span></p>
          </aside>
        </div>',
    ];
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
            // Shop by metal (jewellery.html?metal=Silver): the same page, headed for that metal
            $metal = null;
            foreach (METALS as $m) if (strcasecmp($m, trim((string) ($_GET['metal'] ?? ''))) === 0) $metal = $m;
            if ($metal) {
                $label = $metal . ' jewellery';
                return ['page' => 'catalog', 'nav' => 'jewellery', 'main' => 'catalog', 'modals' => ['modal-filters'], 'vars' => [
                    'category' => '', 'crumb' => e($label), 'heading' => star($metal . ' *jewellery*'), 'lead' => e(METAL_LEADS[$metal] ?? $pg['lead']),
                    'heroImage' => e($pg['banner']), 'total' => (string) count_live(fn ($p) => $p['metal'] === $metal), 'chips' => catalog_chips(''), 'offerRibbon' => offer_ribbon(''),
                ], 'title' => $metal . ' Jewellery — Mangalam Jewellers', 'description' => METAL_LEADS[$metal] ?? (string) $pg['lead']];
            }
            return ['page' => 'catalog', 'nav' => 'jewellery', 'main' => 'catalog', 'modals' => ['modal-filters'], 'vars' => [
                'category' => '', 'crumb' => 'All jewellery', 'heading' => star((string) $pg['heading']), 'lead' => e($pg['lead']),
                'heroImage' => e($pg['banner']), 'total' => (string) count(products(true)), 'chips' => catalog_chips(''), 'offerRibbon' => offer_ribbon(''),
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
        case 'offers':
            return ['page' => 'offers', 'nav' => 'offers', 'main' => 'offers', 'vars' => offers_fragments() + page_vars('offers')] + $seo('offers');
        case 'article':
            $a = null;
            foreach (articles(true) as $x) if ($x['slug'] === ($_GET['slug'] ?? '')) $a = $x;
            $meta = $seo('article');
            if ($a) $meta = ['title' => $a['seo_title'] ?: $a['title'] . ' — Mangalam Journal', 'description' => $a['seo_desc'] ?: $a['excerpt']];
            return ['page' => 'article', 'nav' => 'journal', 'main' => 'article', 'vars' => []] + $meta;
        case 'conversation':
            // A customer's private conversation (the link is in every email we send them, and shown after they enquire)
            $en = enquiry_by_token((string) ($_GET['t'] ?? ''));
            if (!$en) http_response_code(404);
            return ['page' => 'conversation', 'nav' => '', 'main' => 'conversation', 'private' => true, 'vars' => conversation_fragments($en),
                'title' => 'Your conversation — ' . setting('store_name', 'Mangalam Jewellers'), 'description' => 'Your private conversation with our concierge.'];
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
                'heroImage' => e($c['banner']), 'total' => (string) count_live(fn ($p) => $p['category'] === $c['slug']), 'chips' => catalog_chips($c['slug']), 'offerRibbon' => offer_ribbon($c['slug']),
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
    // The first photograph a visitor sees (the hero, or the page's banner) starts loading with the styles
    $lead = $vars['heroImage'] ?? $vars['pageBanner'] ?? '';
    $out = render_template(read_view('site', 'partials/layout.html'), $vars + [
        'preload' => $lead !== '' ? "\n  <link rel=\"preload\" as=\"image\" href=\"$lead\" fetchpriority=\"high\">" : '',
        'title' => e($p['title']),
        'description' => e($p['description']),
        'page' => $p['page'],
        'bodyAttrs' => !empty($p['category']) ? ' data-category="' . e($p['category']) . '"' : '',
        'headerClass' => !empty($p['solid']) ? ' is-solid' : '',
        'main' => $main,
        'extraModals' => implode("\n", array_map(fn ($m) => render_template(read_view('site', 'partials/' . $m . '.html'), $vars, 'site', $icon), $p['modals'] ?? [])),
        // Private pages are never listed, and their address (which holds the key) is not passed on to other sites
        'robots' => !empty($p['private']) ? "\n  <meta name=\"robots\" content=\"noindex, nofollow\">\n  <meta name=\"referrer\" content=\"no-referrer\">"
            : (setting('indexable', true) ? '' : "\n  <meta name=\"robots\" content=\"noindex, nofollow\">"),
        'loader' => setting('loader', true) ? render_template(read_view('site', 'partials/loader.html'), [], 'site', $icon) : '',
        'offer' => offer_is_live() ? render_template(read_view('site', 'partials/offer.html'), $vars + offer_vars(), 'site', $icon) : '',
        'dataVersion' => content_version(),
        'cssVersion' => asset_version('assets/css/mangalam.css'), 'jsVersion' => asset_version('assets/js/main.js'), 'uiVersion' => asset_version('assets/js/ui.js'),
        'viewerVersion' => asset_version('assets/js/viewer3d.js'), 'product3dVersion' => asset_version('assets/js/product3d.js'),
        'spinVersion' => asset_version('assets/js/spin360.js'),
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
