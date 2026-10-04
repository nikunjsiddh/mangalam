<?php
/* Mangalam Jewellers — every page of the website (/, /rings.html, /product.html?slug=…) and /data.js,
 * made from the database. .htaccess sends the .html addresses here. */
require __DIR__ . '/app/bootstrap.php';
require_installed();
require APP_DIR . '/site/render.php';

$route = strtolower(trim((string) ($_GET['route'] ?? ''), '/'));

try {
    if ($route === 'data.js') {
        header('Content-Type: application/javascript; charset=utf-8');
        // The pages ask for data.js?v=<version>; that exact version never changes, so browsers may keep it
        $fresh = ($_GET['v'] ?? '') === content_version();
        header('Cache-Control: ' . ($fresh ? 'public, max-age=31536000, immutable' : 'no-cache'));
        echo site_data_js();
        exit;
    }
    if (!preg_match('/^[a-z0-9-]*$/', $route)) $route = '404';

    if (setting('maintenance', false) && !team_member_signed_in()) {
        http_response_code(503);
        header('Retry-After: 3600');
        echo render_maintenance();
        exit;
    }

    $page = site_page($route);
    if (!$page) {
        http_response_code(404);
        $page = site_page('404');
    }
    header('Content-Type: text/html; charset=utf-8');
    echo render_site_page($page);
    flush();
    maybe_send_daily_summary();
} catch (Throwable $e) {
    http_response_code(500);
    error_log('Mangalam: ' . $e);
    echo !empty(config()['debug'])
        ? '<pre style="white-space:pre-wrap;font:14px/1.5 monospace;padding:24px">' . e($e) . '</pre>'
        : '<!doctype html><meta charset="utf-8"><title>Mangalam Jewellers</title><p style="font:16px system-ui;margin:20vh auto;max-width:520px;padding:0 20px">Something went wrong on our side. Please try again in a moment.</p>';
}
