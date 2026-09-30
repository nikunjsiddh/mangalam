<?php
/* Mangalam Jewellers — the admin panel's pages (admin/*.html), made from the database on each request.
 * Templates: app/views/admin/ (layout.html, layout-auth.html, partials/, pages/). Behaviour: assets/js/admin.js. */

require APP_DIR . '/site/render.php';
require APP_DIR . '/admin/ui.php';
require APP_DIR . '/admin/pages.php';

/** Every screen: [template, sidebar item, title, permission needed] */
const ADMIN_PAGES = [
    'index' => ['dashboard', 'dashboard', 'Dashboard', 'dashboard'],
    'products' => ['products', 'products', 'Products', 'products.edit'],
    'product-new' => ['product-form', 'products', 'Add product', 'products.edit'],
    'product-edit' => ['product-form', 'products', 'Edit product', 'products.edit'],
    'categories' => ['categories', 'categories', 'Categories', 'products.edit'],
    'collections' => ['collections', 'collections', 'Collections', 'products.edit'],
    'homepage' => ['homepage', 'homepage', 'Homepage', 'content.edit'],
    'pages' => ['pages', 'pages', 'Pages & banners', 'content.edit'],
    'journal' => ['journal', 'journal', 'Journal', 'content.edit'],
    'article-new' => ['article-form', 'journal', 'New story', 'content.edit'],
    'article-edit' => ['article-form', 'journal', 'Edit story', 'content.edit'],
    'testimonials' => ['testimonials', 'testimonials', 'Testimonials', 'content.edit'],
    'media' => ['media', 'media', 'Media library', 'media.edit'],
    'enquiries' => ['enquiries', 'enquiries', 'Enquiries', 'enquiries.reply'],
    'appointments' => ['appointments', 'appointments', 'Appointments', 'appointments.edit'],
    'subscribers' => ['subscribers', 'subscribers', 'Subscribers', 'subscribers.export'],
    'offers' => ['offers', 'offers', 'Offers & announcements', 'offers.edit'],
    'settings' => ['settings', 'settings', 'Settings', 'settings.edit'],
    'users' => ['users', 'users', 'Users & roles', 'users.manage'],
];

/** The first screen someone may open (the dashboard, unless their role cannot see it) */
function home_screen(array $user): string
{
    foreach (ADMIN_PAGES as $route => $page) if (can($page[3], $user)) return $route . '.html';
    return 'login.html';
}

function render_admin_page(string $route): string
{
    $me = current_user();
    [$main, $nav, $title, $perm] = ADMIN_PAGES[$route];
    if (!can($perm, $me)) {
        http_response_code(403);
        return admin_layout('forbidden', '', 'No access', [
            'what' => e($title), 'homeHref' => home_screen($me),
        ]);
    }
    $vars = match ($route) {
        'index' => page_dashboard(),
        'products' => page_products(),
        'product-new' => page_product_form(null),
        'product-edit' => page_product_form(find_product_for_edit()),
        'categories' => page_categories(),
        'collections' => page_collections(),
        'homepage' => page_homepage(),
        'pages' => page_pages(),
        'journal' => page_journal(),
        'article-new' => page_article_form(null),
        'article-edit' => page_article_form(find_article_for_edit()),
        'testimonials' => page_testimonials(),
        'media' => page_media(),
        'enquiries' => page_enquiries(),
        'appointments' => page_appointments(),
        'subscribers' => page_subscribers(),
        'offers' => page_offers(),
        'settings' => page_settings(),
        'users' => page_users(),
    };
    if ($route === 'product-edit') $title = html_entity_decode($vars['formTitle']);
    if ($route === 'article-edit') $title = html_entity_decode($vars['formTitle']);
    return admin_layout($main, $nav, $title, $vars);
}

function find_product_for_edit(): array
{
    $id = (int) ($_GET['id'] ?? 0);
    foreach (products(false) as $p) {
        if (($id && (int) $p['id'] === $id) || (!$id && $p['slug'] === ($_GET['slug'] ?? ''))) return $p;
    }
    redirect('products.html');
}

function find_article_for_edit(): array
{
    $id = (int) ($_GET['id'] ?? 0);
    $a = $id ? row('SELECT * FROM articles WHERE id = ?', [$id]) : row('SELECT * FROM articles WHERE slug = ?', [(string) ($_GET['slug'] ?? '')]);
    if (!$a) redirect('journal.html');
    return $a;
}

/** Shared by every signed-in screen: the sidebar, the top bar and the dialogs */
function admin_shared(array $me): array
{
    $notices = notifications($me);
    $unread = count(array_filter($notices, fn ($n) => $n['unread']));
    return [
        'productCount' => count(products(false)), 'newEnquiries' => new_enquiry_count(),
        'notificationItems' => implode('', array_map(fn ($n) => '
            <a class="notice' . ($n['unread'] ? ' is-unread' : '') . '" href="' . e($n['href']) . '"><span class="notice__icon">' . ai($n['icon']) . '</span><span><span class="notice__text">' . e($n['text']) . '</span><span class="notice__when">' . e($n['when'] ? time_ago($n['when']) . (preg_match('/min|hr/', time_ago($n['when'])) ? ' ago' : '') : '') . '</span></span></a>', $notices))
            ?: '<p class="notice notice--none">Nothing new.</p>',
        'notificationCount' => $unread, 'notificationDot' => $unread ? '<span class="top__dot" aria-hidden="true"></span>' : '',
        'meName' => e($me['name']), 'meFirst' => e(explode(' ', trim($me['name']))[0]), 'meRole' => e($me['role']), 'meInitials' => e(initials($me['name'])), 'meTint' => tint($me['name']),
        'meEmail' => e($me['email']),
        'todayLabel' => date('l, j F Y'),
        'siteName' => e(setting('store_name', 'Mangalam Jewellers')),
        'categoryOptions' => implode('', array_map(fn ($c) => option($c['slug'], $c['name']), categories())),
        'timeSlotOptions' => '<option value="">To be arranged</option>' . options(time_slots()),
        'consultantOptions' => '<option value="">Not chosen yet</option>' . options(array_column(consultants(), 'name')),
        'interestOptions' => options(INTERESTS),
        'csrf' => csrf_token(),
        'extraScripts' => '',
    ] + admin_asset_versions();
}

function admin_asset_versions(): array
{
    return ['cssVersion' => asset_version('assets/css/admin.css'), 'jsVersion' => asset_version('assets/js/admin.js')];
}

function admin_layout(string $main, string $nav, string $title, array $vars): string
{
    $me = current_user();
    $icon = fn ($n, $c) => admin_icon($n, $c);
    $vars = admin_shared($me) + ['page' => $main] + $vars + ['title' => e($title)];
    $content = render_template(read_view('admin', 'pages/' . $main . '.html'), $vars, 'admin', $icon);
    $out = render_template(read_view('admin', 'layout.html'), $vars + ['main' => $content], 'admin', $icon);
    $out = hide_forbidden_links($out, $me);
    $out = preg_replace('/(<body[^>]*>)/', "$1\n  " . admin_sprite($out, ['chevron-left', 'chevron-right', 'x', 'check', 'copy']), $out, 1);
    if ($nav) $out = str_replace('data-nav="' . $nav . '"', 'data-nav="' . $nav . '" aria-current="page"', $out);
    return $out;
}

/** The sidebar only lists the screens this person's role may open */
function hide_forbidden_links(string $html, array $me): string
{
    $lines = explode("\n", $html);
    $out = [];
    foreach ($lines as $line) {
        if (preg_match('/class="side__link" data-nav="([a-z]+)"/', $line, $m)) {
            $route = $m[1] === 'dashboard' ? 'index' : $m[1];
            if (isset(ADMIN_PAGES[$route]) && !can(ADMIN_PAGES[$route][3], $me)) continue;
        }
        $out[] = $line;
    }
    $html = implode("\n", $out);
    // Drop group headings left with nothing under them
    $html = preg_replace('#\s*<p class="side__group">[^<]*</p>(?=\s*(<p class="side__group">|</nav>))#', '', $html);
    return $html;
}

/** Sign-in and choose-a-password screens (no sidebar) */
function render_auth_page(string $main, string $title, array $vars = []): string
{
    $icon = fn ($n, $c) => admin_icon($n, $c);
    $vars += ['page' => $main, 'title' => e($title), 'csrf' => csrf_token(), 'storeName' => e(setting('store_name', 'Mangalam Jewellers'))] + admin_asset_versions();
    $content = render_template(read_view('admin', 'pages/' . $main . '.html'), $vars, 'admin', $icon);
    $out = render_template(read_view('admin', 'layout-auth.html'), $vars + ['main' => $content], 'admin', $icon);
    return preg_replace('/(<body[^>]*>)/', "$1\n  " . admin_sprite($out, ['x', 'check']), $out, 1);
}
