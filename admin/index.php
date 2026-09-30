<?php
/* Mangalam Jewellers — every screen of the admin (admin/*.html). admin/.htaccess sends the addresses here. */
require dirname(__DIR__) . '/app/bootstrap.php';
require_installed();
require APP_DIR . '/admin/render.php';

start_session();
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: same-origin');

$route = strtolower((string) ($_GET['route'] ?? 'index'));
$me = current_user();

try {
    switch ($route) {
        case 'logout':
            sign_out();
            redirect('login.html');
        case 'login':
            if ($me) redirect(home_screen($me));
            echo render_auth_page('login', 'Sign in', ['next' => e(safe_next((string) ($_GET['next'] ?? '')))]);
            exit;
        case 'welcome':
            // An invitation or password-reset link: choose a password
            $user = user_for_token((string) ($_GET['token'] ?? ''));
            echo render_auth_page('welcome', $user ? 'Choose a password' : 'Link expired', [
                'token' => e((string) ($_GET['token'] ?? '')), 'name' => $user ? e(explode(' ', $user['name'])[0]) : '',
                'email' => $user ? e($user['email']) : '', 'formHidden' => $user ? '' : ' hidden', 'expiredHidden' => $user ? ' hidden' : '',
            ]);
            exit;
    }
    if (!$me) {
        $query = $_GET;
        unset($query['route']);
        redirect('login.html' . ($route !== 'index' ? '?next=' . rawurlencode($route . '.html' . ($query ? '?' . http_build_query($query) : '')) : ''));
    }
    if (!isset(ADMIN_PAGES[$route])) {
        http_response_code(404);
        redirect(home_screen($me));
    }
    echo render_admin_page($route);
} catch (Throwable $e) {
    http_response_code(500);
    error_log('Mangalam admin: ' . $e);
    echo !empty(config()['debug'])
        ? '<pre style="white-space:pre-wrap;font:14px/1.5 monospace;padding:24px">' . e($e) . '</pre>'
        : '<p style="font:16px system-ui;margin:20vh auto;max-width:520px">Something went wrong. Please go back and try again.</p>';
}

/** Only addresses inside the admin are followed after signing in */
function safe_next(string $next): string
{
    return preg_match('#^[a-z0-9-]+\.html(\?[^\s<>"]*)?$#i', $next) ? $next : '';
}
