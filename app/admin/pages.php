<?php
/* Mangalam Jewellers — the admin's screens, built from the database on each request.
 * Each function returns the variables its template in app/views/admin/pages/ needs. */

/* ---------- Shared facts ---------- */

function need_photos(): array
{
    return array_values(array_filter(products(false), fn ($p) => !$p['gallery']));
}

function new_enquiry_count(): int
{
    return (int) val("SELECT COUNT(*) FROM enquiries WHERE status = 'new'");
}

/** Which collections a piece appears in (by their rules) */
function collections_of(array $p): array
{
    return array_column(array_filter(rows('SELECT * FROM collections ORDER BY sort_order'), fn ($c) => collection_matches($c, $p)), 'title');
}

/** The team members who look after appointments */
function consultants(): array
{
    return rows("SELECT name FROM users WHERE status = 'active' AND role <> 'Editor' ORDER BY FIELD(role, 'Owner', 'Manager', 'Sales'), name");
}

function time_slots(): array
{
    $slots = [];
    for ($m = 10 * 60 + 30; $m <= 20 * 60; $m += 30) $slots[] = date('g:i A', mktime(intdiv($m, 60), $m % 60));
    return $slots;
}

/** How each website page scores for its search listing */
function seo_score(string $title, string $desc): array
{
    if (mb_strlen($title) > 60) return ['warn', 'Title too long'];
    if (mb_strlen($desc) < 70) return ['warn', 'Description short'];
    if (mb_strlen($desc) > 160) return ['warn', 'Description long'];
    return ['ok', 'Good'];
}

/** Every page of the website: its own pages, then the category pages */
function site_page_list(): array
{
    $out = [];
    foreach (rows('SELECT * FROM pages ORDER BY sort_order, id') as $p) {
        $out[] = ['key' => 'page:' . $p['page_key'], 'file' => $p['page_key'] . '.html', 'name' => $p['name'], 'kind' => $p['kind'],
            'heading' => $p['page_key'] === 'index' ? (string) setting('hero_line1', '') . ' ' . (string) setting('hero_line2', '') : (string) $p['heading'],
            'lead' => $p['page_key'] === 'index' ? (string) setting('hero_lead', '') : (string) $p['lead'],
            'banner' => $p['page_key'] === 'index' ? (string) setting('hero_image', '') : (string) $p['banner'],
            'seoTitle' => $p['seo_title'], 'seoDesc' => $p['seo_desc'], 'updated' => $p['updated_at'], 'pageKey' => $p['page_key']];
    }
    // The category pages sit after "All jewellery"
    $cats = [];
    foreach (categories() as $c) {
        $cats[] = ['key' => 'category:' . $c['id'], 'file' => $c['slug'] . '.html', 'name' => $c['name'], 'kind' => 'Category', 'heading' => $c['heading'] ?: $c['name'],
            'lead' => (string) $c['intro'], 'banner' => $c['banner'], 'seoTitle' => $c['seo_title'] ?: $c['name'] . ' — Mangalam Jewellers',
            'seoDesc' => $c['seo_desc'] ?: (string) $c['intro'], 'updated' => $c['updated_at'], 'pageKey' => ''];
    }
    $at = array_search('jewellery.html', array_column($out, 'file'), true);
    array_splice($out, $at === false ? count($out) : $at + 1, 0, $cats);
    foreach ($out as &$p) $p['seo'] = seo_score($p['seoTitle'], $p['seoDesc']);
    return $out;
}

/* ---------- Notifications in the bell ---------- */

function notifications(array $me): array
{
    $seen = $me['notices_seen_at'] ?? '1970-01-01';
    $list = [];
    foreach (rows("SELECT e.*, p.name AS product_name FROM enquiries e LEFT JOIN products p ON p.id = e.product_id WHERE e.status = 'new' ORDER BY e.created_at DESC LIMIT 4") as $en) {
        $list[] = ['icon' => 'inbox', 'text' => 'New enquiry from ' . $en['name'] . ($en['product_name'] ? ' about the ' . $en['product_name'] : ($en['topic'] ? ' — ' . $en['topic'] : '')),
            'when' => $en['created_at'], 'href' => 'enquiries.html?open=' . $en['id'], 'unread' => $en['created_at'] > $seen];
    }
    foreach (rows("SELECT * FROM appointments WHERE status = 'pending' AND date >= CURDATE() ORDER BY created_at DESC LIMIT 3") as $ap) {
        $list[] = ['icon' => 'calendar-check', 'text' => $ap['name'] . ' would like an appointment on ' . (new DateTimeImmutable($ap['date']))->format('D j M') . ' — waiting to be confirmed',
            'when' => $ap['created_at'], 'href' => 'appointments.html?month=' . substr($ap['date'], 0, 7), 'unread' => $ap['created_at'] > $seen];
    }
    usort($list, fn ($a, $b) => strcmp($b['when'], $a['when']));
    $missing = count(need_photos());
    if ($missing) $list[] = ['icon' => 'image', 'text' => plural($missing, 'piece') . ' ' . ($missing === 1 ? 'is' : 'are') . ' still waiting for photographs', 'when' => '', 'href' => 'products.html?flags=photos', 'unread' => false];
    $month = (int) val("SELECT COUNT(*) FROM subscribers WHERE created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')");
    if ($month) $list[] = ['icon' => 'mail', 'text' => plural($month, 'person', 'people') . ' subscribed to the newsletter this month', 'when' => '', 'href' => 'subscribers.html', 'unread' => false];
    return $list;
}

/* ---------- Dashboard ---------- */

function page_dashboard(): array
{
    $period = in_array((int) ($_GET['period'] ?? 7), [7, 30, 90, 365], true) ? (int) ($_GET['period'] ?? 7) : 7;
    $today = today();
    $weekStart = $today->modify('-' . (((int) $today->format('N')) - 1) . ' days');
    $weeks = [];
    for ($i = 11; $i >= 0; $i--) {
        $from = $weekStart->modify('-' . ($i * 7) . ' days');
        $n = (int) val('SELECT COUNT(*) FROM enquiries WHERE created_at >= ? AND created_at < ?', [$from->format('Y-m-d'), $from->modify('+7 days')->format('Y-m-d')]);
        $weeks[] = ['label' => $from->format('j M'), 'value' => $n];
    }
    $inPeriod = (int) val('SELECT COUNT(*) FROM enquiries WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)', [$period]);
    $before = (int) val('SELECT COUNT(*) FROM enquiries WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY) AND created_at < DATE_SUB(NOW(), INTERVAL ? DAY)', [$period * 2, $period]);
    $delta = $before ? (int) round(($inPeriod - $before) / $before * 100) : null;
    $periodName = [7 => 'this week', 30 => 'last 30 days', 90 => 'last 90 days', 365 => 'last 12 months'][$period];
    $vs = [7 => 'last week', 30 => 'the 30 days before', 90 => 'the 90 days before', 365 => 'the year before'][$period];

    $upcoming = rows("SELECT * FROM appointments WHERE date >= CURDATE() AND date < DATE_ADD(CURDATE(), INTERVAL 14 DAY) AND status <> 'cancelled' ORDER BY date, STR_TO_DATE(time, '%l:%i %p')");
    $todayCount = count(array_filter($upcoming, fn ($a) => $a['date'] === $today->format('Y-m-d')));
    $live = products(true);
    $missing = need_photos();
    $subsTotal = (int) val("SELECT COUNT(*) FROM subscribers WHERE status = 'subscribed'");
    $subsNew = (int) val("SELECT COUNT(*) FROM subscribers WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)", [$period]);
    $growth = [];
    for ($i = 11; $i >= 0; $i--) {
        $end = $today->modify('first day of this month')->modify('-' . ($i - 1) . ' months')->format('Y-m-d');
        $growth[] = (int) val("SELECT COUNT(*) FROM subscribers WHERE created_at < ? AND (unsubscribed_at IS NULL OR unsubscribed_at >= ?)", [$end, $end]);
    }

    $kpi = fn ($o) => '
        <a class="card kpi" href="' . $o['href'] . '">
          <span class="kpi__top"><span class="kpi__label">' . $o['label'] . '</span><span class="kpi__icon">' . ai($o['icon']) . '</span></span>
          <span class="kpi__value">' . $o['value'] . '</span>
          <span class="kpi__foot"><span class="kpi__note">' . $o['note'] . '</span>' . ($o['spark'] ?? '') . '</span>
        </a>';
    $deltaNote = $delta === null ? 'none in ' . $vs : '<span class="delta delta--' . ($delta >= 0 ? 'up' : 'down') . '">' . ai($delta >= 0 ? 'trending-up' : 'trending-down') . ($delta >= 0 ? '+' : '') . $delta . '%</span> vs ' . $vs;
    $kpiTiles = implode('', [
        $kpi(['href' => 'enquiries.html', 'label' => 'Enquiries ' . $periodName, 'icon' => 'inbox', 'value' => $inPeriod, 'note' => $deltaNote, 'spark' => sparkline(array_column($weeks, 'value'))]),
        $kpi(['href' => 'appointments.html', 'label' => 'Upcoming appointments', 'icon' => 'calendar-check', 'value' => count($upcoming), 'note' => '<strong>' . $todayCount . '</strong> today · next 2 weeks']),
        $kpi(['href' => 'products.html', 'label' => 'Pieces on the website', 'icon' => 'gem', 'value' => count($live), 'note' => $missing ? '<span class="kpi__warn">' . ai('triangle-alert') . count($missing) . ' need photos</span>' : 'Every piece has photographs']),
        $kpi(['href' => 'subscribers.html', 'label' => 'Newsletter subscribers', 'icon' => 'mail', 'value' => indian_number($subsTotal), 'note' => '<span class="delta delta--up">' . ai('trending-up') . '+' . $subsNew . '</span> ' . $periodName, 'spark' => sparkline($growth)]),
    ]);

    $all = products(false);
    $catCounts = [];
    foreach (categories() as $c) $catCounts[] = ['c' => $c, 'n' => count(array_filter($all, fn ($p) => $p['category'] === $c['slug']))];
    usort($catCounts, fn ($a, $b) => $b['n'] <=> $a['n']);
    $max = max(1, ...array_column($catCounts, 'n'));
    $categoryBars = implode('', array_map(fn ($x) => '
            <li><a class="hbar" href="products.html?category=' . e($x['c']['slug']) . '" data-chart-tip="' . e($x['c']['name'] . '|' . plural($x['n'], 'piece') . ' · ' . ($all ? round($x['n'] / count($all) * 100) : 0) . '% of the catalogue') . '">
              <span class="hbar__label">' . e($x['c']['name']) . '</span>
              <span class="hbar__track"><span class="hbar__fill" style="width:' . number_format($x['n'] / $max * 100, 1, '.', '') . '%"></span></span>
              <span class="hbar__value">' . $x['n'] . '</span>
            </a></li>', $catCounts));

    $recent = rows("SELECT e.*, p.name AS product_name FROM enquiries e LEFT JOIN products p ON p.id = e.product_id WHERE e.status <> 'archived' ORDER BY e.created_at DESC LIMIT 5");
    $recentEnquiries = implode('', array_map(fn ($en) => '
              <tr>
                <td><a class="cell-main" href="enquiries.html?open=' . $en['id'] . '">' . avatar($en['name']) . '<span><span class="cell-title">' . e($en['name']) . '</span><span class="cell-sub">' . e($en['email']) . '</span></span></a></td>
                <td>' . e($en['product_name'] ?: ($en['topic'] ?: 'General enquiry')) . '</td>
                <td class="cell-muted">' . ($en['source'] === 'product' ? 'Product page' : 'Contact form') . '</td>
                <td class="cell-muted nowrap">' . e(time_ago($en['created_at'])) . (preg_match('/min|hr/', time_ago($en['created_at'])) ? ' ago' : '') . '</td>
                <td>' . badge($en['status']) . '</td>
              </tr>', $recent)) ?: '<tr><td colspan="5" class="cell-muted">No enquiries yet — they arrive from product pages and the contact form.</td></tr>';

    $upcomingList = implode('', array_map(fn ($a) => '
            <li><button type="button" class="agenda__item" data-dialog-open="appointment-dialog" data-fill="' . attr_json(appointment_fill($a)) . '">
              <span class="agenda__date"><strong>' . (int) substr($a['date'], 8, 2) . '</strong>' . (new DateTimeImmutable($a['date']))->format('M') . '</span>
              <span class="agenda__body"><span class="cell-title">' . e($a['name']) . '</span><span class="cell-sub">' . e(day_label($a['date'])) . ($a['time'] ? ', ' . e($a['time']) : '') . ' · ' . e($a['interest']) . '</span></span>
              ' . badge($a['status']) . '
            </button></li>', array_slice($upcoming, 0, 5))) ?: '<li class="agenda__none cell-muted">Nothing booked for the next two weeks.</li>';

    $newEnq = new_enquiry_count();
    $pending = (int) val("SELECT COUNT(*) FROM appointments WHERE status = 'pending' AND date >= CURDATE()");
    $drafts = (int) val("SELECT COUNT(*) FROM products WHERE status = 'draft'");
    $seoIssues = count(array_filter(site_page_list(), fn ($p) => $p['seo'][0] !== 'ok' && $p['kind'] !== 'Template'));
    $offerLive = function_exists('offer_is_live') ? offer_is_live() : (bool) setting('offer_on', true);
    $todo = array_filter([
        $missing ? ['warn', 'image', plural(count($missing), 'piece') . ' ' . (count($missing) === 1 ? 'has' : 'have') . ' no photographs yet', 'products.html?flags=photos', 'View'] : null,
        $newEnq ? ['info', 'inbox', plural($newEnq, 'new enquiry', 'new enquiries') . ' waiting for a reply', 'enquiries.html?status=new', 'Reply'] : null,
        $pending ? ['info', 'calendar-check', plural($pending, 'appointment request') . ' waiting to be confirmed', 'appointments.html', 'Confirm'] : null,
        $drafts ? ['info', 'gem', plural($drafts, 'draft piece') . ' not yet on the website', 'products.html?flags=draft', 'Review'] : null,
        $seoIssues ? ['info', 'globe', plural($seoIssues, 'page') . ' could use a better search listing', 'pages.html', 'Review'] : null,
        ['ok', 'megaphone', $offerLive ? 'The offer popup (up to ' . setting('offer_percent') . '% off) is live on every page' : 'The offer popup is switched off', 'offers.html', 'Edit'],
    ]);
    $attention = implode('', array_map(fn ($a) => '
            <li class="todo todo--' . $a[0] . '"><span class="todo__icon">' . ai($a[1]) . '</span><span class="todo__text">' . e($a[2]) . '</span><a class="btn btn--ghost btn--sm" href="' . $a[3] . '">' . $a[4] . ai('chevron-right') . '</a></li>', $todo));

    $periodRadios = implode("\n            ", array_map(fn ($d) => '<label><input type="radio" name="period" value="' . $d . '"' . checked($d === $period) . ' data-period><span>' . [7 => '7 days', 30 => '30 days', 90 => '90 days', 365 => '12 months'][$d] . '</span></label>', [7, 30, 90, 365]));

    return [
        'kpiTiles' => $kpiTiles, 'weeksJSON' => attr_json($weeks),
        'weeksTable' => implode('', array_map(fn ($w) => '<tr><td>Week of ' . $w['label'] . '</td><td class="num">' . $w['value'] . '</td></tr>', $weeks)),
        'weeksTotal' => array_sum(array_column($weeks, 'value')), 'categoryBars' => $categoryBars, 'recentEnquiries' => $recentEnquiries,
        'upcomingList' => $upcomingList, 'attention' => $attention, 'periodRadios' => $periodRadios,
        'offerPercent' => e(setting('offer_percent', '')), 'offerTitle1' => e(setting('offer_title1', '')), 'offerTitle2' => e(setting('offer_title2', '')),
        'offerState' => $offerLive ? 'Live now' : 'Switched off', 'categoryCount' => count(categories()),
    ];
}

function day_label(string $date): string
{
    $d = new DateTimeImmutable($date);
    $diff = (int) today()->diff($d)->format('%r%a');
    return $diff === 0 ? 'Today' : ($diff === 1 ? 'Tomorrow' : $d->format('D j M'));
}

/** What the appointment dialog is filled with */
function appointment_fill(?array $a, array $extra = []): array
{
    if (!$a) return $extra + ['title' => 'New appointment', 'status' => 'confirmed'];
    return ['title' => $a['name'], 'id' => (int) $a['id'], 'name' => $a['name'], 'phone' => $a['phone'], 'email' => $a['email'], 'date' => $a['date'], 'time' => $a['time'],
        'interest' => $a['interest'], 'consultant' => $a['consultant'], 'status' => $a['status'], 'notes' => (string) $a['notes']] + $extra;
}

/* ---------- Products ---------- */

function product_flags(array $p): array
{
    return array_values(array_filter([
        (int) $p['featured_order'] > 0 ? 'featured' : null, $p['is_new'] ? 'new' : null, !$p['gallery'] ? 'photos' : null,
        !$p['is_live'] ? 'draft' : null,
    ]));
}

function product_status_key(array $p): string
{
    if ($p['status'] === 'published' && !$p['is_live']) return 'scheduled';
    return $p['status'];
}

function page_products(): array
{
    $list = products(false);
    $canDelete = can('products.delete');
    $rows = implode('', array_map(function ($p) use ($canDelete) {
        $flags = product_flags($p);
        $tags = ((int) $p['featured_order'] > 0 ? '<span class="tag tag--gold">Featured</span>' : '') . ($p['is_new'] ? '<span class="tag">New</span>' : '') . ($p['gallery'] ? '' : '<span class="tag tag--warn">Needs photos</span>');
        $name = e($p['name']);
        $live = $p['is_live'];
        return '
              <tr data-item data-id="' . $p['id'] . '" data-search="' . e(mb_strtolower(implode(' ', [$p['name'], $p['category'] ?? '', $p['metal'], $p['style'], $p['line'], $p['purity'], $p['stone']]))) . '" data-category="' . e($p['category'] ?? '') . '" data-metal="' . e($p['metal']) . '" data-style="' . e($p['style']) . '" data-flags="' . implode(' ', $flags) . '" data-sort-price="' . (int) $p['price'] . '" data-sort-name="' . $name . '" data-sort-updated="' . strtotime($p['updated_at']) . '">
                <td class="col-check"><input class="check" type="checkbox" data-check-row aria-label="Select ' . $name . '"></td>
                <td><a class="cell-main" href="product-edit.html?id=' . $p['id'] . '"><img class="thumb" src="' . e(asset($p['thumb'])) . '" alt="" loading="lazy" width="48" height="48"><span><span class="cell-title">' . $name . '</span><span class="cell-sub">' . e($p['line']) . '</span>' . ($tags ? '<span class="tags">' . $tags . '</span>' : '') . '</span></a></td>
                <td>' . e(category_name($p['category'])) . '</td>
                <td class="nowrap">' . e($p['purity'] . ' ' . $p['metal']) . '</td>
                <td class="num">' . ($p['offer_price'] !== null ? inr($p['offer_price']) . ' <s class="cell-muted">' . inr($p['price']) . '</s>' : inr($p['price'])) . '</td>
                <td>' . badge(product_status_key($p)) . '</td>
                <td class="cell-muted nowrap">' . long_date($p['updated_at']) . '</td>
                <td class="col-actions"><div class="row-actions">
                  <a class="icon-btn icon-btn--sm" href="product-edit.html?id=' . $p['id'] . '" aria-label="Edit ' . $name . '" data-tip="Edit">' . ai('pencil') . '</a>
                  ' . more_menu($p['name'], [
                    $live ? menu_link('../product.html?slug=' . $p['slug'], 'external-link', 'View on website') : null,
                    menu_post('copy', 'Duplicate', 'product.duplicate', ['id' => (int) $p['id']]),
                    $p['status'] === 'published' ? menu_post('eye-off', 'Hide from website', 'product.status', ['ids' => [(int) $p['id']], 'status' => 'hidden'])
                        : menu_post('eye', 'Publish on website', 'product.status', ['ids' => [(int) $p['id']], 'status' => 'published']),
                    $canDelete ? menu_delete('“' . $p['name'] . '”', 'It will be removed from the website and the catalogue. This can\'t be undone.', 'product.delete', ['ids' => [(int) $p['id']]]) : null,
                  ]) . '
                </div></td>
              </tr>';
    }, $list));
    $count = fn ($flag) => count(array_filter($list, fn ($p) => in_array($flag, product_flags($p), true)));
    $chips = implode('', array_map(fn ($c) => '<button type="button" class="chip' . ($c[0] === '' ? ' is-active' : '') . '" data-list-chip="flags" data-value="' . $c[0] . '" aria-pressed="' . ($c[0] === '' ? 'true' : 'false') . '">' . $c[1] . '<span class="chip__count">' . $c[2] . '</span></button>',
        [['', 'All pieces', count($list)], ['featured', 'Featured', $count('featured')], ['new', 'New', $count('new')], ['photos', 'Needs photos', $count('photos')], ['draft', 'Not on the website', $count('draft')]]));
    return [
        'productRows' => $rows ?: '', 'productChips' => $chips, 'categoryTotal' => count(categories()),
        'liveCount' => count(products(true)),
        'bulkDelete' => $canDelete ? '<button type="button" class="btn btn--ghost btn--sm" data-bulk-post="product.delete"' . params_attr([]) . ' data-confirm="Delete the selected products?" data-confirm-text="They will be removed from the website and the catalogue. This can\'t be undone." data-confirm-action="Delete">' . ai('trash-2') . ' Delete</button>' : '',
        'moveOptions' => implode('', array_map(fn ($c) => option((string) $c['id'], $c['name']), categories())),
    ];
}

function gallery_thumb(string $src, int $i): string
{
    return '
                <li class="gthumb" data-sort-item draggable="true">
                  <img src="' . e(asset(small_image($src))) . '" alt="" loading="lazy" draggable="false">
                  <input type="hidden" name="gallery[]" value="' . e($src) . '">
                  <span class="gthumb__tag" data-gthumb-tag>' . ($i === 0 ? 'Main' : ($i === 1 ? 'On hover' : '')) . '</span>
                  <button type="button" class="gthumb__remove" data-remove-closest=".gthumb" aria-label="Remove photo">' . ai('x') . '</button>
                </li>';
}

/** The editor's HTML shows images from the admin folder, one level down */
function html_for_editor(?string $html): string
{
    return preg_replace('/(<img[^>]+src=")(assets\/)/i', '$1../$2', (string) $html);
}

function page_product_form(?array $p): array
{
    $edit = $p !== null;
    $val = fn ($k) => $edit ? e($p[$k] ?? '') : '';
    $status = $edit ? $p['status'] : 'draft';
    $cats = categories();
    $defaultTitle = $edit ? $p['name'] . ' — Mangalam Jewellers' : 'Product name — Mangalam Jewellers';
    $num = fn ($k) => $edit && $p[$k] !== null ? rtrim(rtrim((string) $p[$k], '0'), '.') : '';
    return [
        'formTitle' => $edit ? e($p['name']) : 'Add a product',
        'formCrumb' => $edit ? e($p['name']) : 'Add product',
        'formDesc' => $edit ? e(category_name($p['category'])) . ' · last edited ' . long_date($p['updated_at']) : 'Fill in the details, add photos, then publish it to the website.',
        'formBadge' => badge($edit ? product_status_key($p) : 'draft'),
        'submitLabel' => $edit ? 'Update product' : 'Publish product',
        'savedToast' => $edit ? 'Product updated' : 'Product published',
        'statusRadios' => radios('status', ['Published', 'Draft', 'Hidden'], ucfirst($status)),
        'publishDate' => $edit && $p['publish_on'] ? $p['publish_on'] : '',
        'viewHref' => $edit ? '../product.html?slug=' . e($p['slug']) : '../jewellery.html',
        'viewHidden' => $edit && $p['is_live'] ? '' : ' hidden',
        'fId' => $edit ? (int) $p['id'] : '',
        'fName' => $val('name'), 'fSlug' => $val('slug'), 'fPrice' => $edit ? (int) $p['price'] : '',
        'fOfferPrice' => $edit && $p['offer_price'] !== null ? (int) $p['offer_price'] : '',
        'fGross' => $num('gross_weight'), 'fGold' => $num('gold_weight'), 'fStoneWeight' => $num('stone_weight'), 'fMaking' => $num('making_charges'), 'fHuid' => $val('huid'),
        'fSummary' => $val('summary'),
        'fDescription' => $edit ? html_for_editor($p['details']) : '',
        'fSeoTitle' => $val('seo_title'), 'fSeoDesc' => $val('seo_desc'),
        'seoTitlePlaceholder' => e($defaultTitle), 'seoDescPlaceholder' => $edit ? e($p['summary']) : 'A short description of the piece for search results.',
        'seoPreviewTitle' => e($edit && $p['seo_title'] ? $p['seo_title'] : $defaultTitle),
        'seoPreviewUrl' => $edit ? 'product.html?slug=' . e($p['slug']) : 'product.html?slug=…',
        'seoPreviewDesc' => $edit ? e($p['seo_desc'] ?: $p['summary']) : 'A short description of the piece for search results.',
        'metalOptions' => options(METALS, $edit ? $p['metal'] : 'Gold'),
        'purityRadios' => radios('purity', PURITIES, $edit ? $p['purity'] : '22K'),
        'stoneOptions' => options(STONES, $edit ? $p['stone'] : 'None'),
        'categorySelect' => implode('', array_map(fn ($c) => option($c['slug'], $c['name'], $edit && $p['category'] === $c['slug']), $cats)),
        'styleRadios' => radios('style', STYLES, $edit ? $p['style'] : 'Classic'),
        'lineOptions' => options(array_values(array_unique(array_merge(LINES, $edit ? [$p['line']] : []))), $edit ? $p['line'] : LINES[0]),
        'inCollections' => $edit ? e(implode(', ', collections_of($p)) ?: 'None yet') : 'Worked out from the details once saved',
        'isNewChecked' => checked($edit && $p['is_new']),
        'featuredChecked' => checked($edit && (int) $p['featured_order'] > 0),
        'showPriceChecked' => checked(!$edit || $p['show_price']),
        'enquiriesChecked' => checked(!$edit || $p['allow_enquiry']),
        'galleryThumbs' => $edit ? implode('', array_map('gallery_thumb', $p['gallery'], array_keys($p['gallery']))) : '',
        'deleteCard' => $edit && can('products.delete') ? '
          <section class="card card--danger">
            <div class="card__body">
              <h2 class="card__title">Delete this product</h2>
              <p class="hint mt-6">It is removed from the website, search and every collection.</p>
              <button type="button" class="btn btn--danger-ghost btn--sm mt-12"' . confirm_attrs('Delete “' . $p['name'] . '”?', 'It will be removed from the website and the catalogue. This can\'t be undone.', 'Delete', 'product.delete', ['ids' => [(int) $p['id']], 'then' => 'products.html']) . '>' . ai('trash-2') . ' Delete product</button>
            </div>
          </section>' : '',
    ];
}

/* ---------- Categories ---------- */

function page_categories(): array
{
    $all = products(false);
    $rows = implode('', array_map(function ($c) use ($all) {
        $n = count(array_filter($all, fn ($p) => $p['category'] === $c['slug']));
        $fill = ['title' => 'Edit ' . $c['name'], 'id' => (int) $c['id'], 'name' => $c['name'], 'slug' => $c['slug'], 'heading' => $c['heading'], 'intro' => (string) $c['intro'],
            'seoTitle' => $c['seo_title'], 'seoDesc' => $c['seo_desc'], 'image' => $c['image'], 'banner' => $c['banner'], 'inMenu' => (bool) $c['in_menu']];
        return '
              <tr data-sort-item data-id="' . $c['id'] . '" draggable="true">
                <td class="col-grip"><span class="grip" aria-hidden="true">' . ai('grip-vertical') . '</span></td>
                <td><div class="cell-main"><img class="thumb thumb--arch" src="' . e(asset($c['image'])) . '" alt="" loading="lazy"><span><span class="cell-title">' . e($c['name']) . '</span><span class="cell-sub">/' . e($c['slug']) . '.html</span></span></div></td>
                <td class="cell-desc">' . e($c['intro']) . '</td>
                <td class="num"><a href="products.html?category=' . e($c['slug']) . '">' . $n . '</a></td>
                <td>' . switch_post('Show ' . $c['name'] . ' in the menu', (bool) $c['in_menu'], 'category.menu', ['id' => (int) $c['id']]) . '</td>
                <td class="col-actions"><div class="row-actions">
                  <button type="button" class="btn btn--outline btn--sm" data-dialog-open="category-drawer" data-fill="' . attr_json($fill) . '">' . ai('pencil') . ' Edit</button>
                  ' . more_menu($c['name'], [menu_link('../' . $c['slug'] . '.html', 'external-link', 'View on website'),
                    menu_delete('the ' . $c['name'] . ' category', 'Its ' . plural($n, 'piece') . ' stay in the catalogue without a category.', 'category.delete', ['id' => (int) $c['id']])]) . '
                </div></td>
              </tr>';
    }, categories()));
    return ['categoryRows' => $rows, 'categoryTotal' => count(categories()), 'starHint' => STAR_HINT];
}

/* ---------- Collections ---------- */

function collection_value_options(): string
{
    return '<optgroup label="Categories">' . implode('', array_map(fn ($c) => option($c['slug'], $c['name']), categories())) . '</optgroup>'
        . '<optgroup label="Metals">' . options(METALS) . '</optgroup><optgroup label="Styles">' . options(STYLES) . '</optgroup>';
}

function page_collections(): array
{
    $cards = implode('', array_map(function ($c) {
        $fill = ['title' => 'Edit ' . $c['title'], 'id' => (int) $c['id'], 'name' => $c['title'], 'kicker' => $c['kicker'], 'image' => $c['image'], 'field' => $c['rule_field'], 'value' => $c['rule_value'], 'onHome' => (bool) $c['on_home']];
        return '
          <article class="coll" data-sort-item data-id="' . $c['id'] . '" draggable="true">
            <div class="coll__media">
              <img src="' . e(asset($c['image'])) . '" alt="" loading="lazy" draggable="false" style="object-position:' . e($c['image_pos']) . '">
              <span class="coll__count">' . plural((int) $c['n'], 'piece') . '</span>
              <span class="grip coll__grip" aria-hidden="true">' . ai('grip-vertical') . '</span>
            </div>
            <div class="coll__body">
              <p class="coll__kicker">' . e($c['kicker']) . '</p>
              <h2 class="coll__title">' . e($c['title']) . '</h2>
              <p class="coll__rule">' . ai('list-filter') . e(collection_rule_text($c)) . '</p>
            </div>
            <div class="coll__foot">
              <label class="switch switch--text"><input type="checkbox"' . checked((bool) $c['on_home']) . ' data-change-post="collection.home"' . params_attr(['id' => (int) $c['id']]) . '><span class="switch__track" aria-hidden="true"></span><span>On homepage</span></label>
              <div class="row-actions">
                <button type="button" class="btn btn--outline btn--sm" data-dialog-open="collection-drawer" data-fill="' . attr_json($fill) . '">' . ai('pencil') . ' Edit</button>
                ' . more_menu($c['title'], [menu_link('../' . $c['href'], 'external-link', 'View on website'),
                    menu_delete('the ' . $c['title'] . ' collection', 'The pieces stay in the catalogue; only this grouping is removed.', 'collection.delete', ['id' => (int) $c['id']])]) . '
              </div>
            </div>
          </article>';
    }, collections(false)));
    return ['collectionCards' => $cards, 'collectionTotal' => (int) val('SELECT COUNT(*) FROM collections'), 'valueOptions' => collection_value_options()];
}

/* ---------- Homepage ---------- */

function page_homepage(): array
{
    $sections = (array) setting('home_sections', []);
    $keys = array_column($sections, 'key');
    foreach (array_keys(HOME_SECTIONS) as $k) if (!in_array($k, $keys, true)) $sections[] = ['key' => $k, 'visible' => true];
    $counts = ['categories' => plural(count(nav_categories()), 'category arch', 'category arches'), 'collections' => plural((int) val('SELECT COUNT(*) FROM collections WHERE on_home = 1'), 'collection tile'),
        'testimonials' => plural((int) val('SELECT COUNT(*) FROM testimonials WHERE on_home = 1'), 'customer story', 'customer stories')];
    $sectionRows = implode('', array_map(function ($s) use ($counts) {
        if (!isset(HOME_SECTIONS[$s['key']])) return '';
        [$name, $desc, $icon, $href] = HOME_SECTIONS[$s['key']];
        $desc = $counts[$s['key']] ?? $desc;
        return '
            <li class="srow" data-sort-item draggable="true">
              <span class="grip" aria-hidden="true">' . ai('grip-vertical') . '</span>
              <input type="hidden" name="sections[]" value="' . $s['key'] . '">
              <span class="srow__icon">' . ai($icon) . '</span>
              <span class="srow__text"><span class="srow__name">' . $name . '</span><span class="srow__desc">' . e($desc) . '</span></span>
              ' . ($s['key'] === 'hero' ? '<input type="hidden" name="visible[]" value="hero"><span class="tag tag--soft">Always shown</span>'
                : '<label class="switch"><input type="checkbox" name="visible[]" value="' . $s['key'] . '"' . checked(!empty($s['visible'])) . ' aria-label="Show ' . e($name) . ' on the homepage"><span class="switch__track" aria-hidden="true"></span></label>') . '
              ' . ($href ? '<a class="icon-btn icon-btn--sm" href="' . $href . '" aria-label="Edit ' . e($name) . '" data-tip="Edit">' . ai('pencil') . '</a>' : '<span class="icon-btn icon-btn--sm is-placeholder" aria-hidden="true"></span>') . '
            </li>';
    }, $sections));

    $stats = (array) setting('hero_stats', []);
    while (count($stats) < 3) $stats[] = ['', ''];
    $heroStats = implode('', array_map(fn ($i) => '
                <div class="stat-edit"><input class="input" name="stat_value[]" value="' . e($stats[$i][0]) . '" aria-label="Figure ' . ($i + 1) . '"><input class="input" name="stat_label[]" value="' . e($stats[$i][1]) . '" aria-label="Figure ' . ($i + 1) . ' caption"></div>', [0, 1, 2]));

    $pins = rows('SELECT h.*, p.name, p.slug FROM hotspots h JOIN products p ON p.id = h.product_id ORDER BY h.sort_order, h.id');
    $all = products(false);
    $byId = array_column($all, null, 'id');
    $heroPins = implode('', array_map(fn ($i) => '<button type="button" class="pin" style="--x:' . pct($pins[$i]['x']) . '%;--y:' . pct($pins[$i]['y']) . '%" data-pin="' . ($i + 1) . '" aria-label="Pin ' . ($i + 1) . ', ' . e($pins[$i]['name']) . '. Drag to move.">' . ($i + 1) . '</button>', array_keys($pins)));
    $pinRow = fn ($i, $h) => '
                <li class="pinrow" data-pin-row="' . ($i + 1) . '">
                  <span class="pinrow__num">' . ($i + 1) . '</span>
                  <img class="thumb thumb--sm" src="' . e(asset($byId[$h['product_id']]['thumb'] ?? '')) . '" alt="" loading="lazy" data-pin-thumb>
                  <span class="pinrow__body">
                    <select class="select select--sm" name="pin_product[]" aria-label="Piece shown by pin ' . ($i + 1) . '" data-pin-product>' . product_options((int) $h['product_id']) . '</select>
                    <span class="pinrow__pos" data-pin-pos>' . pct($h['x']) . '% across · ' . pct($h['y']) . '% down</span>
                  </span>
                  <input type="hidden" name="pin_x[]" value="' . pct($h['x']) . '" data-pin-x><input type="hidden" name="pin_y[]" value="' . pct($h['y']) . '" data-pin-y><input type="hidden" name="pin_flip[]" value="' . (int) $h['flip'] . '" data-pin-flip>
                  <button type="button" class="icon-btn icon-btn--sm" data-confirm="Remove pin ' . ($i + 1) . '?" data-confirm-text="The pin disappears from the hero when you publish; the piece stays in the catalogue." data-confirm-action="Remove" data-done="Pin removed" data-remove-pin="' . ($i + 1) . '" aria-label="Remove pin ' . ($i + 1) . '">' . ai('trash-2') . '</button>
                </li>';
    $pinRows = implode('', array_map($pinRow, array_keys($pins), $pins));
    // A blank row the page copies when a pin is added
    $pinTemplate = $pinRow(0, ['product_id' => $all[0]['id'] ?? 0, 'x' => 50, 'y' => 50, 'flip' => 0]);

    $featuredPicks = implode('', array_map(fn ($i, $p) => '
              <li class="pick" data-sort-item draggable="true">
                <span class="pick__num" data-position>' . ($i + 1) . '</span>
                <input type="hidden" name="featured[]" value="' . $p['id'] . '">
                <img src="' . e(asset($p['thumb'])) . '" alt="" loading="lazy" draggable="false">
                <span class="pick__text"><span class="pick__name">' . e($p['name']) . '</span><span class="pick__meta">' . e(category_name($p['category'])) . ' · ' . inr($p['price']) . '</span></span>
                <button type="button" class="icon-btn icon-btn--sm" data-remove-closest=".pick" aria-label="Remove ' . e($p['name']) . ' from featured pieces">' . ai('x') . '</button>
              </li>', array_keys(featured_products(false)), featured_products(false)));

    $panels = (array) setting('bridal_panels', []);
    $bridalPanels = implode('', array_map(fn ($i, $pn) => '
              <div class="panel-edit">
                <div class="panel-edit__media"><img src="' . e(asset($pn['image'])) . '" alt="" loading="lazy" data-image-preview><input type="hidden" name="panel_image[]" value="' . e($pn['image']) . '" data-image-value><label class="panel-edit__replace">' . ai('upload') . '<span>Replace</span><input type="file" accept="image/*" data-image-input data-upload-folder="campaign"></label></div>
                <div class="panel-edit__fields">
                  <input class="input" name="panel_title[]" value="' . e($pn['title']) . '" aria-label="Panel ' . ($i + 1) . ' title">
                  <textarea class="textarea" name="panel_text[]" rows="3" aria-label="Panel ' . ($i + 1) . ' text">' . e($pn['text']) . '</textarea>
                  <input type="hidden" name="panel_label[]" value="' . e($pn['label']) . '"><input type="hidden" name="panel_cta[]" value="' . e($pn['cta']) . '"><input type="hidden" name="panel_href[]" value="' . e($pn['href']) . '"><input type="hidden" name="panel_pos[]" value="' . e($pn['pos']) . '">
                </div>
              </div>', array_keys($panels), $panels));

    $heroImage = (string) setting('hero_image', '');
    return [
        'sectionRows' => $sectionRows, 'sectionTotal' => count($sections), 'heroImage' => e(asset($heroImage)), 'heroImagePath' => e($heroImage),
        'heroEyebrow' => e(setting('hero_eyebrow', '')), 'heroLine1' => e(setting('hero_line1', '')), 'heroLine2' => e(setting('hero_line2', '')),
        'heroLead' => e(setting('hero_lead', '')), 'heroStats' => $heroStats, 'heroPins' => $heroPins, 'pinRows' => $pinRows,
        'pinTemplate' => e($pinTemplate), 'featuredPicks' => $featuredPicks, 'productOptions' => product_options(null, true), 'bridalPanels' => $bridalPanels,
        'starHint' => STAR_HINT,
    ];
}

/* ---------- Pages & banners ---------- */

function page_pages(): array
{
    $list = site_page_list();
    $rows = implode('', array_map(function ($p) {
        $fill = ['title' => 'Edit ' . $p['name'], 'key' => $p['key'], 'heading' => $p['heading'], 'intro' => $p['lead'], 'seoTitle' => $p['seoTitle'], 'seoDesc' => $p['seoDesc'], 'banner' => $p['banner'], 'url' => $p['file']];
        $edit = match (true) {
            $p['pageKey'] === 'product' => '<a class="btn btn--outline btn--sm" href="products.html">' . ai('pencil') . ' Edit products</a>',
            $p['pageKey'] === 'article' => '<a class="btn btn--outline btn--sm" href="journal.html">' . ai('pencil') . ' Edit stories</a>',
            $p['pageKey'] === 'index' => '<a class="btn btn--outline btn--sm" href="homepage.html">' . ai('pencil') . ' Edit homepage</a>',
            default => '<button type="button" class="btn btn--outline btn--sm" data-dialog-open="page-drawer" data-fill="' . attr_json($fill) . '">' . ai('pencil') . ' Edit</button>',
        };
        return '
              <tr data-item data-search="' . e(mb_strtolower($p['name'] . ' ' . $p['file'] . ' ' . star_plain($p['heading']))) . '" data-kind="' . $p['kind'] . '">
                <td><div class="cell-main">' . ($p['banner'] ? '<img class="thumb thumb--wide" src="' . e(asset($p['banner'])) . '" alt="" loading="lazy">' : '<span class="thumb thumb--wide thumb--icon">' . ai('file-text') . '</span>') . '<span><span class="cell-title">' . e($p['name']) . '</span><span class="cell-sub">/' . e($p['file']) . '</span></span></div></td>
                <td><span class="tag tag--soft">' . $p['kind'] . '</span></td>
                <td class="cell-desc">' . e(star_plain($p['heading']) ?: ($p['kind'] === 'Template' ? ($p['pageKey'] === 'product' ? 'Each product\'s name' : 'Each story\'s title') : '')) . '</td>
                <td><span class="badge badge--' . $p['seo'][0] . '">' . $p['seo'][1] . '</span></td>
                <td class="cell-muted nowrap">' . long_date($p['updated']) . '</td>
                <td class="col-actions"><div class="row-actions">
                  ' . $edit . '
                  <a class="icon-btn icon-btn--sm" href="../' . e($p['file']) . '" target="_blank" rel="noopener" aria-label="View ' . e($p['name']) . ' on the website" data-tip="View">' . ai('external-link') . '</a>
                </div></td>
              </tr>';
    }, $list));
    return ['pageRows' => $rows, 'pageTotal' => count($list), 'seoIssues' => count(array_filter($list, fn ($p) => $p['seo'][0] !== 'ok')), 'starHint' => STAR_HINT];
}

/* ---------- Journal ---------- */

function article_status_key(array $a): string
{
    if ($a['status'] === 'draft') return 'draft';
    return ($a['published_on'] && $a['published_on'] <= date('Y-m-d')) ? 'published' : 'scheduled';
}

function page_journal(): array
{
    $list = articles(false);
    $cards = implode('', array_map(function ($a) {
        $st = article_status_key($a);
        $pub = article_public($a);
        return '
          <article class="post card" data-item data-category="' . e($a['category']) . '" data-status="' . $st . '" data-search="' . e(mb_strtolower($a['title'] . ' ' . $a['category'] . ' ' . $a['excerpt'])) . '">
            <a class="post__media" href="article-edit.html?id=' . $a['id'] . '">' . ($a['image'] ? '<img src="' . e(asset($a['image'])) . '" alt="" loading="lazy" style="object-position:' . e($a['image_position']) . '">' : '') . '<span class="tag tag--light post__cat">' . e($a['category']) . '</span></a>
            <div class="post__body">
              <p class="post__meta">' . e($pub['date'] ?: 'Not dated yet') . ' · ' . e($pub['readTime']) . '</p>
              <h2 class="post__title"><a href="article-edit.html?id=' . $a['id'] . '">' . e($a['title']) . '</a></h2>
              <p class="post__excerpt">' . e($a['excerpt']) . '</p>
            </div>
            <div class="post__foot">
              ' . badge($st) . '
              <div class="row-actions">
                <a class="btn btn--outline btn--sm" href="article-edit.html?id=' . $a['id'] . '">' . ai('pencil') . ' Edit</a>
                ' . more_menu($a['title'], [
                    $st === 'published' ? menu_link('../article.html?slug=' . $a['slug'], 'external-link', 'View on website') : null,
                    menu_post('copy', 'Duplicate', 'article.duplicate', ['id' => (int) $a['id']]),
                    $a['status'] === 'draft' ? menu_post('eye', 'Publish', 'article.status', ['id' => (int) $a['id'], 'status' => 'published'])
                        : menu_post('eye-off', 'Unpublish', 'article.status', ['id' => (int) $a['id'], 'status' => 'draft']),
                    menu_delete('“' . $a['title'] . '”', 'The story will be removed from the journal. This can\'t be undone.', 'article.delete', ['id' => (int) $a['id']]),
                  ]) . '
              </div>
            </div>
          </article>';
    }, $list));
    $cats = array_values(array_unique(array_merge(TOPICS, array_column($list, 'category'))));
    $chips = implode('', array_map(fn ($c) => '<button type="button" class="chip' . ($c[0] === '' ? ' is-active' : '') . '" data-list-chip="' . $c[3] . '" data-value="' . e($c[0]) . '" aria-pressed="' . ($c[0] === '' ? 'true' : 'false') . '">' . e($c[1]) . '<span class="chip__count">' . $c[2] . '</span></button>',
        array_merge([['', 'All stories', count($list), 'category']], array_map(fn ($c) => [$c, $c, count(array_filter($list, fn ($a) => $a['category'] === $c)), 'category'], array_values(array_filter($cats, fn ($c) => count(array_filter($list, fn ($a) => $a['category'] === $c))))))));
    $drafts = count(array_filter($list, fn ($a) => article_status_key($a) !== 'published'));
    return ['articleCards' => $cards, 'journalChips' => $chips, 'articleTotal' => count(articles(true)), 'draftNote' => $drafts ? ' ' . plural($drafts, 'more is', 'more are') . ' drafts or scheduled.' : ''];
}

function page_article_form(?array $a): array
{
    $edit = $a !== null;
    $val = fn ($k) => $edit ? e($a[$k] ?? '') : '';
    $authors = rows("SELECT name FROM users WHERE status = 'active' ORDER BY FIELD(role, 'Editor', 'Manager', 'Owner', 'Sales'), name");
    $authorNames = array_column($authors, 'name');
    if ($edit && $a['author'] && !in_array($a['author'], $authorNames, true)) $authorNames[] = $a['author'];
    $me = current_user();
    $st = $edit ? article_status_key($a) : 'draft';
    $defaultTitle = $edit ? $a['title'] . ' — Mangalam Journal' : 'Story title — Mangalam Journal';
    return [
        'formTitle' => $edit ? e($a['title']) : 'Write a story',
        'formCrumb' => $edit ? e($a['title']) : 'New story',
        'formDesc' => $edit ? e($a['category']) . ($st === 'published' ? ' · published ' . e(article_public($a)['date']) : ($st === 'scheduled' ? ' · scheduled for ' . long_date($a['published_on']) : ' · draft')) : 'Write it, add a cover photograph, then publish it to the journal.',
        'formBadge' => badge($st),
        'submitLabel' => $edit ? 'Update story' : 'Publish story',
        'savedToast' => $edit ? 'Story updated' : 'Story published',
        'statusRadios' => radios('status', ['Published', 'Draft', 'Scheduled'], ucfirst($edit ? $a['status'] : 'draft')),
        'viewHref' => $edit ? '../article.html?slug=' . e($a['slug']) : '../journal.html',
        'viewHidden' => $st === 'published' ? '' : ' hidden',
        'fId' => $edit ? (int) $a['id'] : '',
        'fTitle' => $val('title'), 'fSlug' => $val('slug'), 'fExcerpt' => $val('excerpt'), 'fReadTime' => $edit ? (int) $a['read_time'] : '',
        'fBody' => $edit ? html_for_editor($a['body']) : '',
        'fSeoTitle' => $val('seo_title'), 'fSeoDesc' => $val('seo_desc'),
        'seoTitlePlaceholder' => e($defaultTitle), 'seoDescPlaceholder' => $edit ? e($a['excerpt']) : 'A one-line summary for search results.',
        'seoPreviewTitle' => e($edit && $a['seo_title'] ? $a['seo_title'] : $defaultTitle),
        'seoPreviewUrl' => $edit ? 'article.html?slug=' . e($a['slug']) : 'article.html?slug=…',
        'seoPreviewDesc' => $edit ? e($a['seo_desc'] ?: $a['excerpt']) : 'A one-line summary for search results.',
        'categoryRadios' => radios('category', array_values(array_unique(array_merge(TOPICS, $edit ? [$a['category']] : []))), $edit ? $a['category'] : 'Bridal'),
        'authorOptions' => implode('', array_map(fn ($n) => option($n, $n, $edit ? $n === $a['author'] : $n === ($me['name'] ?? '')), $authorNames)),
        'onHomeChecked' => checked(!$edit || $a['on_home']),
        'coverImage' => $edit && $a['image'] ? '<img src="' . e(asset($a['image'])) . '" alt="" data-image-preview>' : '<img src="" alt="" data-image-preview hidden>',
        'coverPath' => $edit ? e($a['image']) : '',
        'coverEmpty' => $edit && $a['image'] ? ' hidden' : '',
        'publishDate' => $edit && $a['published_on'] ? $a['published_on'] : date('Y-m-d'),
        'deleteCard' => $edit ? '
          <section class="card card--danger">
            <div class="card__body">
              <h2 class="card__title">Delete this story</h2>
              <p class="hint mt-6">It is removed from the journal and the homepage.</p>
              <button type="button" class="btn btn--danger-ghost btn--sm mt-12"' . confirm_attrs('Delete “' . $a['title'] . '”?', 'The story will be removed from the journal. This can\'t be undone.', 'Delete', 'article.delete', ['id' => (int) $a['id'], 'then' => 'journal.html']) . '>' . ai('trash-2') . ' Delete story</button>
            </div>
          </section>' : '',
    ];
}

/* ---------- Testimonials ---------- */

function page_testimonials(): array
{
    $list = rows('SELECT * FROM testimonials ORDER BY sort_order, id');
    $cards = implode('', array_map(function ($t) {
        $fill = ['title' => 'Edit ' . $t['who'] . '\'s story', 'id' => (int) $t['id'], 'quote' => $t['quote'], 'who' => $t['who'], 'occasion' => $t['occasion'], 'consent' => (bool) $t['consent']];
        return '
          <article class="card qcard" data-sort-item data-id="' . $t['id'] . '" draggable="true">
            <span class="grip qcard__grip" aria-hidden="true">' . ai('grip-vertical') . '</span>
            <blockquote class="qcard__quote">' . e($t['quote']) . '</blockquote>
            <div class="qcard__who">' . avatar($t['who']) . '<span><span class="cell-title">' . e($t['who']) . '</span><span class="cell-sub">' . e($t['occasion']) . '</span></span></div>
            <div class="qcard__foot">
              <label class="switch switch--text"><input type="checkbox"' . checked((bool) $t['on_home']) . ' data-change-post="testimonial.home"' . params_attr(['id' => (int) $t['id']]) . '><span class="switch__track" aria-hidden="true"></span><span>On homepage</span></label>
              <div class="row-actions">
                <button type="button" class="btn btn--outline btn--sm" data-dialog-open="testimonial-dialog" data-fill="' . attr_json($fill) . '">' . ai('pencil') . ' Edit</button>
                <button type="button" class="icon-btn icon-btn--sm"' . confirm_attrs('Delete ' . $t['who'] . '\'s story?', 'It will no longer appear on the homepage.', 'Delete', 'testimonial.delete', ['id' => (int) $t['id']]) . ' aria-label="Delete ' . e($t['who']) . '\'s story">' . ai('trash-2') . '</button>
              </div>
            </div>
          </article>';
    }, $list));
    return ['testimonialCards' => $cards, 'testimonialTotal' => count($list)];
}

/* ---------- Media library ---------- */

/** Where each image is used: pages, products, stories, categories, collections and the homepage */
function media_usage(): array
{
    $use = [];
    $add = function (string $path, string $where) use (&$use) { if ($path !== '') $use[preg_replace('/-sm(\.\w+)$/', '$1', $path)][] = $where; };
    foreach (products(false) as $p) foreach ($p['gallery'] as $g) $add($g, $p['name']);
    foreach (rows('SELECT title, image FROM articles') as $a) $add($a['image'], 'Journal: ' . $a['title']);
    foreach (categories() as $c) { $add($c['image'], 'Category: ' . $c['name']); $add($c['banner'], $c['name'] . ' page banner'); }
    foreach (rows('SELECT title, image FROM collections') as $c) $add($c['image'], 'Collection: ' . $c['title']);
    foreach (rows('SELECT name, banner FROM pages') as $p) $add($p['banner'], $p['name'] . ' banner');
    $add((string) setting('hero_image', ''), 'Home: hero');
    foreach ((array) setting('bridal_panels', []) as $pn) $add($pn['image'], 'Home: bridal edit');
    // Photographs placed in the page templates themselves
    $templates = [];
    foreach (glob(APP_DIR . '/views/site/pages/*.html') as $f) $templates[basename($f, '.html')] = file_get_contents($f);
    $templates['all pages'] = file_get_contents(APP_DIR . '/views/site/partials/header.html') . file_get_contents(APP_DIR . '/views/site/partials/footer.html');
    $names = ['home' => 'Home', 'about' => 'About us', 'craftsmanship' => 'Craftsmanship', 'contact' => 'Contact', 'collections' => 'Collections', 'all pages' => 'Menu on every page'];
    foreach ($templates as $key => $html) {
        preg_match_all('#assets/images/[A-Za-z0-9_./-]+#', $html, $m);
        foreach (array_unique($m[0]) as $path) $add($path, $names[$key] ?? capitalize($key));
    }
    if (setting('instagram')) foreach ([1, 2, 3, 4, 5, 6] as $n) $add("assets/images/campaign/insta-$n.jpg", 'Home: Instagram');
    foreach ($use as &$u) $u = array_values(array_unique($u));
    return $use;
}

function page_media(): array
{
    $media = rows('SELECT * FROM media ORDER BY FIELD(folder, "campaign", "products", "brand", "other"), name');
    $usage = media_usage();
    $bytes = array_sum(array_column($media, 'bytes'));
    $tiles = implode('', array_map(function ($m) use ($usage) {
        $used = $usage[$m['path']] ?? [];
        $label = MEDIA_FOLDERS[$m['folder']][0];
        $dims = $m['width'] ? $m['width'] . ' × ' . $m['height'] : '';
        $thumb = $m['folder'] === 'products' && is_file(ROOT_DIR . '/' . small_image($m['path'])) ? small_image($m['path']) : $m['path'];
        $info = ['id' => (int) $m['id'], 'src' => asset($m['path']), 'name' => $m['name'], 'folder' => $label, 'dims' => $dims, 'size' => file_size_label((int) $m['bytes']), 'url' => $m['path'], 'alt' => $m['alt'], 'used' => $used];
        return '
            <li data-item data-folder="' . $m['folder'] . '" data-search="' . e(mb_strtolower($m['name'])) . '">
              <button type="button" class="tile' . ($m['folder'] === 'brand' ? ' tile--brand' : '') . '" data-media="' . attr_json($info) . '" aria-label="' . e($m['name']) . '">
                <span class="tile__img"><img src="' . e(asset($thumb)) . '" alt="" loading="lazy" draggable="false"></span>
                <span class="tile__name">' . e($m['name']) . '</span>
                <span class="tile__meta">' . ($dims ? $dims . ' · ' : '') . file_size_label((int) $m['bytes']) . '</span>
              </button>
            </li>';
    }, $media));
    $chips = implode('', array_map(fn ($c) => '<button type="button" class="chip' . ($c[0] === '' ? ' is-active' : '') . '" data-list-chip="folder" data-value="' . $c[0] . '" aria-pressed="' . ($c[0] === '' ? 'true' : 'false') . '">' . $c[1] . '<span class="chip__count">' . $c[2] . '</span></button>',
        array_merge([['', 'All', count($media)]], array_map(fn ($k) => [$k, MEDIA_FOLDERS[$k][0], count(array_filter($media, fn ($m) => $m['folder'] === $k))], array_keys(MEDIA_FOLDERS)))));
    return ['mediaTiles' => $tiles, 'mediaChips' => $chips, 'mediaTotal' => count($media), 'mediaSize' => file_size_label($bytes),
        'folderOptions' => implode('', array_map(fn ($k) => option($k, MEDIA_FOLDERS[$k][0], $k === ($_GET['folder'] ?? 'campaign')), array_keys(MEDIA_FOLDERS))),
        'resizeNote' => gd_available() ? 'Large photographs are resized for the web automatically; product photos get their small copy.' : 'Upload photographs at web size (products 1200 × 1200 px). Turn on PHP\'s GD extension to have them resized automatically.'];
}

/* ---------- Enquiries ---------- */

function page_enquiries(): array
{
    $list = rows("SELECT e.*, p.name AS product_name, p.slug AS product_slug, p.price AS product_price, p.category_id FROM enquiries e LEFT JOIN products p ON p.id = e.product_id ORDER BY e.created_at DESC");
    $replies = [];
    foreach (rows('SELECT * FROM enquiry_replies ORDER BY created_at') as $r) $replies[$r['enquiry_id']][] = $r;
    $open = (int) ($_GET['open'] ?? 0);
    if (!$open || !in_array($open, array_map('intval', array_column($list, 'id')), true)) $open = (int) ($list[0]['id'] ?? 0);
    $subject = fn ($en) => $en['product_name'] ?: ($en['topic'] ?: 'General enquiry');
    $items = implode('', array_map(fn ($en) => '
            <li data-item data-status="' . $en['status'] . '" data-source="' . $en['source'] . '" data-search="' . e(mb_strtolower($en['name'] . ' ' . $subject($en) . ' ' . $en['message'])) . '">
              <button type="button" class="inbox__item' . ($en['status'] === 'new' ? ' is-unread' : '') . '" data-thread-open="' . $en['id'] . '"' . ((int) $en['id'] === $open ? ' aria-current="true"' : '') . '>
                ' . avatar($en['name']) . '
                <span class="inbox__body">
                  <span class="inbox__top"><span class="inbox__name">' . e($en['name']) . '</span><span class="inbox__time">' . e(time_ago($en['created_at'])) . '</span></span>
                  <span class="inbox__subject">' . e($subject($en)) . '</span>
                  <span class="inbox__snippet">' . e($en['message']) . '</span>
                  <span class="inbox__meta">' . badge($en['status']) . '<span class="inbox__source">' . ($en['source'] === 'product' ? 'Product page' : 'Contact form') . '</span></span>
                </span>
              </button>
            </li>', $list));
    $threads = implode('', array_map(function ($en) use ($replies, $open, $subject) {
        $first = explode(' ', trim($en['name']))[0];
        $thumb = '';
        if ($en['product_slug']) { $p = product_by_slug($en['product_slug'], false); $thumb = $p ? $p['thumb'] : ''; }
        $msgs = implode('', array_map(fn ($r) => '<div class="msg msg--out"><p>' . nl2br(e($r['message'])) . '</p><span class="msg__meta">' . e($r['author']) . ' · ' . e(when_label($r['created_at'])) . ($r['emailed'] ? ' · emailed' : '') . '</span></div>', $replies[$en['id']] ?? []));
        $id = (int) $en['id'];
        return '
          <article class="thread" data-thread="' . $id . '"' . ($id === $open ? '' : ' hidden') . ' aria-label="Enquiry from ' . e($en['name']) . '">
            <header class="thread__head">
              <button type="button" class="icon-btn thread__back" data-thread-back aria-label="Back to all enquiries">' . ai('arrow-left') . '</button>
              ' . avatar($en['name'], 'lg') . '
              <div class="thread__who">
                <h2 class="thread__name">' . e($en['name']) . '</h2>
                <p class="thread__contact"><a href="mailto:' . e($en['email']) . '">' . ai('mail') . e($en['email']) . '</a>' . ($en['phone'] ? '<a href="tel:' . e(preg_replace('/\s/', '', $en['phone'])) . '">' . ai('phone') . e($en['phone']) . '</a>' : '') . '</p>
              </div>
              <div class="thread__tools">
                <select class="select select--sm" aria-label="Status of this enquiry" data-change-post="enquiry.status"' . params_attr(['id' => $id]) . '>' . implode('', array_map(fn ($s) => option($s, STATUS[$s][1], $s === $en['status']), ['new', 'replied', 'closed', 'archived'])) . '</select>
                ' . more_menu($en['name'], [
                    can('appointments.edit') ? '<button type="button" role="menuitem" data-dialog-open="appointment-dialog" data-fill="' . attr_json(appointment_fill(null, ['title' => 'New appointment', 'name' => $en['name'], 'phone' => $en['phone'], 'email' => $en['email'], 'date' => today()->modify('+3 days')->format('Y-m-d'), 'status' => 'pending', 'notes' => 'From enquiry: ' . $subject($en)])) . '">' . ai('calendar-check') . 'Book an appointment</button>' : null,
                    $en['status'] !== 'archived' ? menu_post('archive', 'Archive', 'enquiry.status', ['id' => $id, 'value' => 'archived']) : null,
                    menu_delete('this enquiry', 'The message and any replies will be removed. This can\'t be undone.', 'enquiry.delete', ['id' => $id]),
                  ]) . '
              </div>
            </header>
            <dl class="facts">
              <div><dt>Received</dt><dd>' . e(when_label($en['created_at'])) . '</dd></div>
              <div><dt>From</dt><dd>' . ($en['source'] === 'product' ? 'Product page' : 'Contact form') . '</dd></div>
              <div><dt>Topic</dt><dd>' . e($en['product_name'] ? 'Product enquiry' : ($en['topic'] ?: 'General enquiry')) . '</dd></div>
            </dl>
            ' . ($en['product_slug'] ? '<a class="thread__product" href="../product.html?slug=' . e($en['product_slug']) . '" target="_blank" rel="noopener"><img class="thumb" src="' . e(asset($thumb)) . '" alt="" loading="lazy"><span><span class="cell-title">' . e($en['product_name']) . '</span><span class="cell-sub">' . e(category_name(val('SELECT slug FROM categories WHERE id = ?', [$en['category_id']]))) . ' · ' . inr($en['product_price']) . '</span></span>' . ai('external-link') . '</a>' : '') . '
            <div class="msg msg--in"><p>' . nl2br(e($en['message'])) . '</p><span class="msg__meta">' . e($first) . ' · ' . e(when_label($en['created_at'])) . '</span></div>
            ' . $msgs . '
            <form class="composer" data-save="enquiry.reply" data-toast="Reply saved">
              <input type="hidden" name="id" value="' . $id . '">
              <label class="sr-only" for="reply-' . $id . '">Reply to ' . e($en['name']) . '</label>
              <textarea class="composer__text" id="reply-' . $id . '" name="message" rows="4" required placeholder="Write a reply to ' . e($first) . '…" data-reply-for="' . e($first) . '"></textarea>
              <div class="composer__foot">
                <select class="select select--sm" aria-label="Insert a saved reply" data-saved-reply>
                  <option value="">Saved replies…</option>
                  <option value="visit">Invite to visit</option>
                  <option value="bridal">Bridal consultation</option>
                  <option value="care">Care &amp; repairs</option>
                </select>
                <a class="btn btn--ghost btn--sm" href="mailto:' . e($en['email']) . '?subject=' . rawurlencode('Your enquiry to Mangalam Jewellers') . '">' . ai('mail') . ' Open in email</a>
                <button type="submit" class="btn btn--primary btn--sm">' . ai('send') . ' ' . (setting('mail_enabled', false) ? 'Send reply' : 'Save reply') . '</button>
              </div>
            </form>
          </article>';
    }, $list));
    $counts = ['' => count(array_filter($list, fn ($e) => $e['status'] !== 'archived'))];
    foreach (['new', 'replied', 'closed', 'archived'] as $s) $counts[$s] = count(array_filter($list, fn ($e) => $e['status'] === $s));
    $status = (string) ($_GET['status'] ?? '');
    $chips = implode('', array_map(fn ($s) => '<button type="button" class="chip chip--sm' . ($s === '' ? ' is-active' : '') . '" data-list-chip="status" data-value="' . $s . '" aria-pressed="' . ($s === '' ? 'true' : 'false') . '">' . ($s === '' ? 'All' : STATUS[$s][1]) . '<span class="chip__count">' . $counts[$s] . '</span></button>', array_keys($counts)));
    return ['enquiryItems' => $items, 'enquiryThreads' => $threads ?: '<div class="empty"><span class="empty__icon">' . ai('inbox') . '</span><p class="empty__title">No enquiries yet</p><p>Messages from product pages and the contact form arrive here.</p></div>',
        'enquiryChips' => $chips, 'enquiryTotal' => count($list), 'mailNote' => setting('mail_enabled', false) ? '' : ' Replies are saved here; to email them, use “Open in email” or turn on sending in Settings › Notifications.'];
}

/* ---------- Appointments ---------- */

function page_appointments(): array
{
    $month = preg_match('/^\d{4}-\d{2}$/', (string) ($_GET['month'] ?? '')) ? $_GET['month'] : date('Y-m');
    $monthStart = new DateTimeImmutable($month . '-01');
    $monthEnd = $monthStart->modify('last day of this month');
    $gridStart = $monthStart->modify('-' . (((int) $monthStart->format('N')) - 1) . ' days');
    $gridEnd = $monthEnd->modify('+' . (7 - (int) $monthEnd->format('N')) . ' days');
    $list = rows('SELECT * FROM appointments WHERE date BETWEEN ? AND ? ORDER BY date, STR_TO_DATE(time, "%l:%i %p")', [$gridStart->format('Y-m-d'), $gridEnd->format('Y-m-d')]);
    $icons = ['confirmed' => 'check', 'pending' => 'clock', 'completed' => 'check-check', 'cancelled' => 'x'];
    $days = '';
    for ($d = $gridStart; $d <= $gridEnd; $d = $d->modify('+1 day')) {
        $iso = $d->format('Y-m-d');
        $items = array_filter($list, fn ($a) => $a['date'] === $iso);
        $cls = ($d->format('m') !== $monthStart->format('m') ? ' is-out' : '') . ($iso === date('Y-m-d') ? ' is-today' : '');
        $days .= '
              <div class="cal__day' . $cls . '"><span class="cal__num">' . $d->format('j') . '</span>' . implode('', array_map(fn ($a) => '<button type="button" class="appt appt--' . $a['status'] . '" data-dialog-open="appointment-dialog" data-fill="' . attr_json(appointment_fill($a)) . '" aria-label="' . e(($a['time'] ?: 'Time to arrange') . ', ' . $a['name'] . ', ' . STATUS[$a['status']][1]) . '">' . ai($icons[$a['status']]) . '<span class="appt__time">' . e(strtolower(str_replace([':00', ' '], '', $a['time'])) ?: '—') . '</span><span class="appt__name">' . e($a['name']) . '</span></button>', $items)) . '</div>';
    }
    $upcoming = rows("SELECT * FROM appointments WHERE date >= CURDATE() AND date < DATE_ADD(CURDATE(), INTERVAL 14 DAY) AND status <> 'cancelled' ORDER BY date, STR_TO_DATE(time, '%l:%i %p')");
    $groups = [];
    foreach ($upcoming as $a) $groups[$a['date']][] = $a;
    $agenda = implode('', array_map(function ($date, $items) {
        $key = day_label($date);
        $d = new DateTimeImmutable($date);
        return '
            <li class="agenda__group">
              <p class="agenda__day">' . $key . (in_array($key, ['Today', 'Tomorrow'], true) ? ' · ' . $d->format('D j M') : '') . '</p>
              <ul>' . implode('', array_map(fn ($a) => '
                <li><button type="button" class="agenda__item" data-dialog-open="appointment-dialog" data-fill="' . attr_json(appointment_fill($a)) . '">
                  <span class="agenda__time">' . ($a['time'] ? e(explode(' ', $a['time'])[0]) . '<small>' . e(explode(' ', $a['time'])[1] ?? '') . '</small>' : '—<small>time</small>') . '</span>
                  <span class="agenda__body"><span class="cell-title">' . e($a['name']) . '</span><span class="cell-sub">' . e($a['interest']) . ($a['consultant'] ? ' · with ' . e(explode(' ', $a['consultant'])[0]) : ' · no consultant yet') . '</span></span>
                  ' . badge($a['status']) . '
                </button></li>', $items)) . '
              </ul>
            </li>';
    }, array_keys($groups), $groups)) ?: '<li class="agenda__none cell-muted">Nothing booked for the next two weeks.</li>';
    return [
        'calendarDays' => $days, 'agenda' => $agenda, 'monthLabel' => $monthStart->format('F Y'),
        'prevMonth' => $monthStart->modify('-1 month')->format('Y-m'), 'nextMonth' => $monthStart->modify('+1 month')->format('Y-m'),
        'upcomingTotal' => count($upcoming), 'todayTotal' => count(array_filter($upcoming, fn ($a) => $a['date'] === date('Y-m-d'))),
        'pendingTotal' => (int) val("SELECT COUNT(*) FROM appointments WHERE status = 'pending' AND date >= CURDATE()"),
    ];
}

/* ---------- Subscribers ---------- */

function page_subscribers(): array
{
    $list = rows('SELECT * FROM subscribers ORDER BY created_at DESC, id DESC');
    $rows = implode('', array_map(fn ($s) => '
              <tr data-item data-id="' . $s['id'] . '" data-status="' . $s['status'] . '" data-source="' . e($s['source']) . '" data-search="' . e($s['email']) . '" data-sort-date="' . strtotime($s['created_at']) . '">
                <td class="col-check"><input class="check" type="checkbox" data-check-row aria-label="Select ' . e($s['email']) . '"></td>
                <td><span class="cell-main">' . avatar(preg_replace('/[._]/', ' ', explode('@', $s['email'])[0]), 'sm') . '<span class="cell-title">' . e($s['email']) . '</span></span></td>
                <td class="cell-muted">' . e($s['source']) . '</td>
                <td class="cell-muted nowrap">' . long_date($s['created_at']) . '</td>
                <td>' . badge($s['status']) . '</td>
                <td class="col-actions"><div class="row-actions">' . ($s['status'] === 'subscribed'
                    ? '<button type="button" class="icon-btn icon-btn--sm" data-post="subscriber.status"' . params_attr(['ids' => [(int) $s['id']], 'status' => 'unsubscribed']) . ' aria-label="Unsubscribe ' . e($s['email']) . '" data-tip="Unsubscribe">' . ai('log-out') . '</button>'
                    : '<button type="button" class="icon-btn icon-btn--sm" data-post="subscriber.status"' . params_attr(['ids' => [(int) $s['id']], 'status' => 'subscribed']) . ' aria-label="Subscribe ' . e($s['email']) . ' again" data-tip="Subscribe again">' . ai('refresh-cw') . '</button>')
                . '<button type="button" class="icon-btn icon-btn--sm"' . confirm_attrs('Remove ' . $s['email'] . '?', 'They will stop receiving the newsletter and be removed from this list.', 'Remove', 'subscriber.delete', ['ids' => [(int) $s['id']]]) . ' aria-label="Remove ' . e($s['email']) . '">' . ai('trash-2') . '</button></div></td>
              </tr>', $list));
    $growth = [];
    for ($i = 11; $i >= 0; $i--) {
        $end = today()->modify('first day of this month')->modify('-' . ($i - 1) . ' months')->format('Y-m-d');
        $growth[] = (int) val("SELECT COUNT(*) FROM subscribers WHERE created_at < ? AND (unsubscribed_at IS NULL OR unsubscribed_at >= ?)", [$end, $end]);
    }
    $sources = array_values(array_unique(array_merge(SUBSCRIBER_SOURCES, array_column($list, 'source'))));
    $monthBy = rows("SELECT source, COUNT(*) n FROM subscribers WHERE created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01') GROUP BY source ORDER BY n DESC LIMIT 1");
    return [
        'subscriberRows' => $rows, 'subscriberTotal' => indian_number((int) val("SELECT COUNT(*) FROM subscribers WHERE status = 'subscribed'")),
        'subscribersMonth' => (int) val("SELECT COUNT(*) FROM subscribers WHERE created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')"),
        'monthNote' => $monthBy ? 'Mostly from ' . mb_strtolower($monthBy[0]['source']) : 'None yet this month',
        'unsubscribed' => (int) val("SELECT COUNT(*) FROM subscribers WHERE status = 'unsubscribed' AND unsubscribed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"),
        'subscriberSpark' => sparkline($growth), 'listed' => count($list),
        'sourceOptions' => options($sources), 'sourceFilter' => options($sources),
    ];
}

/* ---------- Offers & announcements ---------- */

function page_offers(): array
{
    $announcements = (array) setting('announcements', []);
    $rows = implode('', array_map(fn ($i, $a) => '
              <li class="arow" data-sort-item draggable="true">
                <span class="grip" aria-hidden="true">' . ai('grip-vertical') . '</span>
                <input class="input" name="announcements[]" value="' . e($a) . '" aria-label="Message ' . ($i + 1) . '" data-announcement>
                <button type="button" class="icon-btn icon-btn--sm" data-remove-closest=".arow" aria-label="Remove message ' . ($i + 1) . '">' . ai('x') . '</button>
              </li>', array_keys($announcements), $announcements));
    $links = array_merge([['jewellery.html', 'All jewellery'], ['collections.html', 'Collections'], ['bridal.html', 'The bridal edit']],
        array_map(fn ($c) => [$c['slug'] . '.html', $c['name']], array_filter(categories(), fn ($c) => $c['slug'] !== 'bridal')));
    $href = (string) setting('offer_href', 'jewellery.html');
    if (!in_array($href, array_column($links, 0), true)) $links[] = [$href, $href];
    return [
        'offerOnChecked' => checked((bool) setting('offer_on', true)),
        'offerEyebrow' => e(setting('offer_eyebrow', '')), 'offerTitle1' => e(setting('offer_title1', '')), 'offerTitle2' => e(setting('offer_title2', '')),
        'offerText' => e(setting('offer_text', '')), 'offerCta' => e(setting('offer_cta', '')), 'offerNote' => e(setting('offer_note', '')),
        'offerPercent' => e(setting('offer_percent', '')), 'offerDelay' => e(setting('offer_delay', '1.5')), 'offerSeconds' => e(setting('offer_seconds', '7')),
        'offerLinks' => implode('', array_map(fn ($l) => option($l[0], $l[1], $l[0] === $href), $links)),
        'offerStart' => e(setting('offer_start', '')), 'offerEnd' => e(setting('offer_end', '')),
        'offerOnceChecked' => checked((bool) setting('offer_once', true)), 'offerTabChecked' => checked((bool) setting('offer_tab', true)),
        'offerOff' => setting('offer_on', true) ? '' : ' is-off',
        'announcementRows' => $rows,
        'announcementPreview' => implode('', array_map(fn ($a) => '<li>' . e($a) . '</li>', array_merge($announcements, $announcements))),
    ];
}

/* ---------- Settings ---------- */

function page_settings(): array
{
    $hours = implode('', array_map(function ($h) {
        $d = $h['day'];
        $open = !empty($h['open']);
        return '
                <div class="hours__row">
                  <span class="hours__day">' . $d . '</span>
                  <label class="switch switch--text"><input type="checkbox" name="hours_open[' . $d . ']"' . checked($open) . ' data-hours-toggle><span class="switch__track" aria-hidden="true"></span><span>' . ($open ? 'Open' : 'Closed') . '</span></label>
                  <input class="input input--time" type="time" name="hours_from[' . $d . ']" value="' . e($h['from']) . '" aria-label="' . $d . ' opens"' . ($open ? '' : ' disabled') . '>
                  <span class="hours__to">to</span>
                  <input class="input input--time" type="time" name="hours_to[' . $d . ']" value="' . e($h['to']) . '" aria-label="' . $d . ' closes"' . ($open ? '' : ' disabled') . '>
                </div>';
    }, (array) setting('hours', [])));
    $home = page_row('index');
    $s = fn ($k, $d = '') => e(setting($k, $d));
    $b = fn ($k, $d = false) => checked((bool) setting($k, $d));
    $placeholderSocial = in_array(setting('instagram'), ['https://instagram.com', ''], true) || in_array(setting('facebook'), ['https://facebook.com', ''], true);
    return [
        'storeName' => $s('store_name'), 'founded' => $s('founded'), 'tagline' => $s('tagline'),
        'sitePhone' => $s('phone'), 'siteWhatsapp' => $s('whatsapp'), 'siteEmail' => $s('email'), 'addrLine1' => $s('address_line1'), 'addrLine2' => $s('address_line2'),
        'siteMaps' => $s('maps_url'), 'hours' => $hours, 'siteInstagram' => $s('instagram'), 'siteFacebook' => $s('facebook'), 'siteYoutube' => $s('youtube'),
        'socialNote' => $placeholderSocial ? '<p class="note">' . ai('info') . '<span>These are still the placeholder addresses from the template — add the house\'s real pages here.</span></p>' : '',
        'homeTitle' => e($home['seo_title']), 'homeDesc' => e($home['seo_desc']), 'indexableChecked' => $b('indexable', true),
        'notifyEmail' => $s('notify_email'), 'notifyEnquiry' => $b('notify_enquiry', true), 'notifyAppointment' => $b('notify_appointment', true),
        'notifySubscriber' => $b('notify_subscriber'), 'notifySummary' => $b('notify_summary', true), 'mailEnabled' => $b('mail_enabled'),
        'loaderChecked' => $b('loader', true), 'smoothChecked' => $b('smooth_scroll', true), 'calmChecked' => $b('calm_motion'),
        'maintenanceChecked' => $b('maintenance'), 'maintenanceMessage' => $s('maintenance_message'),
    ];
}

/* ---------- Users & roles ---------- */

function page_users(): array
{
    $me = current_user();
    $team = rows("SELECT * FROM users ORDER BY FIELD(role, 'Owner', 'Manager', 'Editor', 'Sales'), status, name");
    $rows = implode('', array_map(function ($u) use ($me) {
        $self = (int) $u['id'] === (int) $me['id'];
        $owner = $u['role'] === 'Owner';
        $last = $u['status'] === 'invited' ? 'Invitation sent ' . mb_strtolower(time_ago($u['token_expires'] ? date('Y-m-d H:i:s', strtotime($u['token_expires']) - 7 * 86400) : $u['created_at'])) . (preg_match('/min|hr|day|week/', time_ago($u['created_at'])) ? ' ago' : '')
            : ($self ? 'Online now' : ($u['last_login_at'] ? time_ago($u['last_login_at']) . (preg_match('/min|hr|day|week/', time_ago($u['last_login_at'])) ? ' ago' : '') : 'Has not signed in yet'));
        $noPassword = $u['status'] === 'active' && !$u['password_hash'];
        $id = (int) $u['id'];
        return '
              <tr>
                <td><div class="cell-main">' . avatar($u['name']) . '<span><span class="cell-title">' . e($u['name']) . ($self ? ' <span class="tag tag--soft">You</span>' : '') . '</span><span class="cell-sub">' . e($u['email']) . '</span></span></div></td>
                <td>' . ($owner || $self ? '<span class="badge badge--gold">' . $u['role'] . '</span>' : '<select class="select select--sm select--inline" aria-label="Role of ' . e($u['name']) . '" data-change-post="user.role"' . params_attr(['id' => $id]) . '>' . implode('', array_map(fn ($r) => option($r, $r, $r === $u['role']), array_slice(array_keys(ROLES), 1))) . '</select>') . '</td>
                <td>' . badge($u['status']) . ($noPassword ? ' <span class="tag tag--warn">No password yet</span>' : '') . '</td>
                <td class="cell-muted">' . e($last) . '</td>
                <td class="col-actions"><div class="row-actions">' . ($self || $owner ? '' : ($u['status'] === 'invited'
                    ? '<button type="button" class="btn btn--ghost btn--sm" data-post="user.link"' . params_attr(['id' => $id]) . '>' . ai('refresh-cw') . ' New link</button>' . more_menu($u['name'], [menu_delete('the invitation for ' . $u['email'], 'The link they were sent will stop working.', 'user.delete', ['id' => $id], 'Cancel')])
                    : more_menu($u['name'], [menu_post('lock', $noPassword ? 'Make a sign-in link' : 'Reset password', 'user.link', ['id' => $id]), menu_delete($u['name'] . ' from the team', 'They will lose access to the admin straight away.', 'user.delete', ['id' => $id], 'Remove')]))) . '</div></td>
              </tr>';
    }, $team));
    $roleNames = array_keys(ROLES);
    $matrix = permission_matrix();
    $roleHeads = implode('', array_map(fn ($r) => '<th scope="col" class="center"><span class="role-head">' . $r . '<small>' . ROLES[$r] . '</small></span></th>', $roleNames));
    $permissionRows = implode('', array_map(fn ($key) => '
              <tr><th scope="row">' . PERMISSIONS[$key] . '</th>' . implode('', array_map(fn ($r) => '<td class="center"><input class="check" type="checkbox"' . ($r === 'Owner' ? ' checked disabled' : ' name="perm[' . $r . '][' . $key . ']"' . checked($matrix[$key][$r])) . ' aria-label="' . e($r . ': ' . PERMISSIONS[$key]) . '"></td>', $roleNames)) . '</tr>', array_keys(PERMISSIONS)));
    return ['userRows' => $rows, 'roleHeads' => $roleHeads, 'permissionRows' => $permissionRows, 'teamTotal' => count($team),
        'roleOptions' => implode('', array_map(fn ($r) => option($r, $r . ' — ' . ROLES[$r], $r === 'Editor'), array_slice($roleNames, 1)))];
}
