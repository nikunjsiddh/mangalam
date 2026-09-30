<?php
/* Mangalam Jewellers — loaded first by every entry point (index.php, api.php, admin/index.php, admin/api.php). */
declare(strict_types=1);

const APP_DIR = __DIR__;
define('ROOT_DIR', dirname(__DIR__));

date_default_timezone_set('Asia/Kolkata');
mb_internal_encoding('UTF-8');

require APP_DIR . '/lib/helpers.php';
require APP_DIR . '/lib/db.php';
require APP_DIR . '/lib/template.php';
require APP_DIR . '/lib/content.php';
require APP_DIR . '/lib/auth.php';
require APP_DIR . '/lib/images.php';

/** The settings in app/config.php, or null before the site is installed */
function config(bool $reload = false): ?array
{
    static $config = false;
    if ($config === false || $reload) {
        $file = APP_DIR . '/config.php';
        $config = is_file($file) ? require $file : null;
    }
    return $config;
}

$config = config();
$debug = $config === null || !empty($config['debug']);
ini_set('display_errors', $debug ? '1' : '0');
error_reporting(E_ALL);

/** Before installation every page points to the installer */
function require_installed(): void
{
    if (config() !== null) return;
    http_response_code(503);
    $installer = base_url() . 'install.php';
    echo '<!doctype html><meta charset="utf-8"><title>Mangalam Jewellers — not installed</title>'
        . '<body style="font:16px/1.6 system-ui;margin:12vh auto;max-width:560px;padding:0 20px">'
        . '<h1 style="font-weight:500">The website is not installed yet</h1>'
        . '<p>Create the database and import the catalogue with the installer: <a href="' . e($installer) . '">' . e($installer) . '</a></p>';
    exit;
}
