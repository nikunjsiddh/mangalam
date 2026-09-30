<?php
/* Install from the command line (the same as opening install.php in the browser):
 *
 *   php database/install-cli.php --owner-email=you@example.com --owner-password=… [--owner-name="Your Name"]
 *                                [--db-host=127.0.0.1] [--db-port=3306] [--db-name=mangalam] [--db-user=root] [--db-pass=]
 *                                [--no-samples] [--force]
 *
 * Leave out --owner-password to have one made up; it is written to database/owner-credentials.txt
 * (not committed to git, and not served by the web server). --force replaces an existing installation. */
if (PHP_SAPI !== 'cli') exit("Run this from the command line.\n");

require dirname(__DIR__) . '/app/bootstrap.php';
require __DIR__ . '/installer.php';

$o = getopt('', ['owner-email:', 'owner-password::', 'owner-name::', 'db-host::', 'db-port::', 'db-name::', 'db-user::', 'db-pass::', 'no-samples', 'force']);
$seed = json_decode(file_get_contents(__DIR__ . '/seed.json'), true);

if (mj_installed() && !isset($o['force'])) {
    fwrite(STDERR, "The website is already installed. Add --force to replace the database with a fresh import.\n");
    exit(1);
}
$email = $o['owner-email'] ?? $seed['owner']['email'];
$password = $o['owner-password'] ?? '';
$generated = $password === '';
if ($generated) $password = rtrim(strtr(base64_encode(random_bytes(12)), '+/', 'Kq'), '=');
if (!valid_email($email)) { fwrite(STDERR, "--owner-email is not an email address.\n"); exit(1); }
if ($problem = password_problem($password)) { fwrite(STDERR, $problem . "\n"); exit(1); }

try {
    $summary = mj_install([
        'db' => ['host' => $o['db-host'] ?? '127.0.0.1', 'port' => (int) ($o['db-port'] ?? 3306), 'name' => $o['db-name'] ?? 'mangalam', 'user' => $o['db-user'] ?? 'root', 'pass' => $o['db-pass'] ?? ''],
        'owner' => ['name' => $o['owner-name'] ?? $seed['owner']['name'], 'email' => $email, 'password' => $password],
        'samples' => !isset($o['no-samples']),
    ]);
} catch (Throwable $e) {
    fwrite(STDERR, 'Installation failed: ' . $e->getMessage() . "\n");
    exit(1);
}

if ($generated) {
    file_put_contents(__DIR__ . '/owner-credentials.txt', "Mangalam admin — the owner's sign-in (made by install-cli.php on " . date('j M Y, g:i A') . ")\n\nAddress:  admin/login.html\nEmail:    $email\nPassword: $password\n\nChange the password from “Your profile” in the admin, then delete this file.\n");
}
echo "Installed.\n";
foreach ($summary as $k => $v) echo str_pad($k, 14) . (is_array($v) ? json_encode($v) : $v) . "\n";
echo $generated ? "The owner's password is in database/owner-credentials.txt\n" : '';
