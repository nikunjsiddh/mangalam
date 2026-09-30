<?php
/* Mangalam Jewellers — the admin's requests: saving, deleting, reordering, uploading (POST, JSON answers)
 * and CSV exports (GET ?action=export&what=products|enquiries|subscribers). */
require dirname(__DIR__) . '/app/bootstrap.php';
require_installed();
require APP_DIR . '/admin/render.php';
require APP_DIR . '/admin/actions.php';

start_session();
header('X-Frame-Options: SAMEORIGIN');
$action = (string) ($_POST['action'] ?? $_GET['action'] ?? '');
$me = current_user();

try {
    if ($action === 'export' && !is_post()) {
        if (!$me) redirect('login.html');
        export_csv((string) ($_GET['what'] ?? ''), $me);
    }
    if (!is_post()) json_response(['ok' => false, 'error' => 'Send changes to this address with POST.'], 405);
    if (!$_POST && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) fail('That upload is larger than the server accepts (' . ini_get('post_max_size') . ' at a time). Try fewer or smaller photographs.');
    $signingIn = in_array($action, ['auth.login', 'auth.welcome'], true);
    if (!$me && !$signingIn) json_response(['ok' => false, 'error' => 'You have been signed out. Sign in again to carry on.', 'redirect' => 'login.html'], 401);
    if (!csrf_valid()) json_response(['ok' => false, 'error' => 'This page has been open a long time. Reload it and try again.'], 419);

    if ($action === 'auth.login') json_response(['ok' => true] + act_auth_login());
    if ($action === 'auth.welcome') json_response(['ok' => true] + act_auth_welcome());

    json_response(['ok' => true] + run_action($action, $me));
} catch (UserError $e) {
    if (db_in_transaction()) db()->rollBack();
    if ($action === 'export') { http_response_code(403); echo e($e->getMessage()); exit; }
    json_response(['ok' => false, 'error' => $e->getMessage(), 'fields' => $e->fields], 422);
} catch (Throwable $e) {
    if (db_in_transaction()) db()->rollBack();
    error_log('Mangalam admin API: ' . $e);
    json_response(['ok' => false, 'error' => !empty(config()['debug']) ? $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')' : 'Something went wrong on the server. Please try again.'], 500);
}

function db_in_transaction(): bool
{
    try { return db()->inTransaction(); } catch (Throwable $e) { return false; }
}
