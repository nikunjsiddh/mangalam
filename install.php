<?php
/* Mangalam Jewellers — installer. Open /install.php once: it creates the database and its tables, imports the
 * website's existing content (products and photographs, categories, collections, journal, pages, homepage,
 * offer, settings), fills the media library, and creates the owner's sign-in. It switches itself off once
 * the website is installed (delete app/config.php to run it again — that replaces everything). */
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/database/installer.php';

session_name('mjinstall');
session_start();
if (empty($_SESSION['install_token'])) $_SESSION['install_token'] = bin2hex(random_bytes(16));
header('X-Frame-Options: DENY');

$installed = mj_installed();
$seed = json_decode(file_get_contents(__DIR__ . '/database/seed.json'), true);
$errors = [];
$summary = null;
$v = [
    'db_host' => '127.0.0.1', 'db_port' => '3306', 'db_name' => 'mangalam', 'db_user' => 'root', 'db_pass' => '',
    'owner_name' => $seed['owner']['name'], 'owner_email' => $seed['owner']['email'], 'samples' => '1',
];

if (!$installed && is_post()) {
    foreach ($v as $k => $default) $v[$k] = $k === 'samples' ? (isset($_POST['samples']) ? '1' : '') : trim((string) ($_POST[$k] ?? ''));
    $password = (string) ($_POST['owner_password'] ?? '');
    if (!hash_equals($_SESSION['install_token'], (string) ($_POST['token'] ?? ''))) $errors[] = 'The form expired. Please send it again.';
    if ($v['owner_name'] === '') $errors[] = 'Please give the owner\'s name.';
    if (!valid_email($v['owner_email'])) $errors[] = 'Please give the owner\'s email address.';
    if ($p = password_problem($password)) $errors[] = $p;
    if ($password !== (string) ($_POST['owner_password2'] ?? '')) $errors[] = 'The two passwords are different.';
    if (!$errors) {
        try {
            $summary = mj_install([
                'db' => ['host' => $v['db_host'], 'port' => (int) $v['db_port'], 'name' => $v['db_name'], 'user' => $v['db_user'], 'pass' => $v['db_pass']],
                'owner' => ['name' => $v['owner_name'], 'email' => $v['owner_email'], 'password' => $password],
                'samples' => $v['samples'] === '1',
                'debug' => in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1'], true),
            ]);
            $installed = true;
        } catch (PDOException $e) {
            $errors[] = 'The database could not be reached or created: ' . $e->getMessage();
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }
    }
}
$field = fn ($name, $label, $type = 'text', $hint = '') => '<label class="field"><span class="field__label">' . $label . '</span><input class="input" type="' . $type . '" name="' . $name . '" value="' . ($type === 'password' ? '' : e($v[$name] ?? '')) . '"' . ($type === 'password' ? ' autocomplete="new-password"' : '') . '>' . ($hint ? '<span class="hint">' . $hint . '</span>' : '') . '</label>';
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title>Install — Mangalam Jewellers</title>
  <link rel="icon" href="favicon.ico" sizes="32x32">
  <link rel="icon" href="assets/images/favicon.svg" type="image/svg+xml">
  <link rel="apple-touch-icon" href="assets/images/brand/apple-touch-icon.png">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;1,400&amp;family=Jost:wght@300;400;500;600&amp;display=swap">
  <link rel="stylesheet" href="assets/css/admin.css">
  <style>.install { max-width: 720px; margin: 0 auto; padding: 48px 20px 80px; } .install .card { margin-top: 22px; } .install ul { margin: 10px 0 0 18px; line-height: 1.8; } .install .err { padding: 12px 14px; border-radius: 10px; background: #FBEAEA; color: #8A2323; }</style>
</head>
<body class="adm-install">
  <main class="install">
    <p class="ph__eyebrow">Mangalam Jewellers</p>
    <h1 class="ph__title">Install the website</h1>
<?php if ($summary): ?>
    <section class="card"><div class="card__body stack">
      <h2 class="card__title">Installed</h2>
      <p>The database is ready and the website's content has been imported:</p>
      <ul>
        <li><?= $summary['products'] ?> products with <?= $summary['photos'] ?> photographs, in <?= $summary['categories'] ?> categories</li>
        <li><?= $summary['collections'] ?> collections, <?= $summary['articles'] ?> journal stories, <?= $summary['testimonials'] ?> testimonials, <?= $summary['pages'] ?> pages</li>
        <li><?= $summary['media'] ?> images in the media library</li>
        <?php if ($summary['samples']['enquiries']): ?><li>Sample data from the design: <?= $summary['samples']['enquiries'] ?> enquiries, <?= $summary['samples']['appointments'] ?> appointments, <?= $summary['samples']['subscribers'] ?> subscribers, <?= $summary['samples']['team'] ?> colleagues</li><?php endif; ?>
      </ul>
      <p><a class="btn btn--gold" href="admin/login.html">Sign in to the admin</a> <a class="btn btn--outline" href="index.html">View the website</a></p>
    </div></section>
<?php elseif ($installed): ?>
    <section class="card"><div class="card__body stack">
      <h2 class="card__title">Already installed</h2>
      <p>The website is installed, so the installer is switched off. To start again from the original content, delete <code>app/config.php</code> and open this page again — that replaces everything in the database.</p>
      <p><a class="btn btn--gold" href="admin/login.html">Sign in to the admin</a></p>
    </div></section>
<?php else: ?>
    <p class="ph__desc">Creates the database, imports the current catalogue, journal, pages and settings, and makes the owner's sign-in.</p>
    <?php if ($errors): ?><div class="card"><div class="card__body"><p class="err"><?= implode('<br>', array_map('e', $errors)) ?></p></div></div><?php endif; ?>
    <form method="post" autocomplete="off">
      <input type="hidden" name="token" value="<?= e($_SESSION['install_token']) ?>">
      <section class="card"><div class="card__body stack">
        <h2 class="card__title">Database</h2>
        <p class="hint">On XAMPP: user <strong>root</strong> with no password. On hosting, create a MySQL database in the control panel first and use its details.</p>
        <div class="form-grid"><?= $field('db_host', 'Host') ?><?= $field('db_port', 'Port') ?><?= $field('db_name', 'Database name', 'text', 'Created if it does not exist.') ?><?= $field('db_user', 'User') ?><?= $field('db_pass', 'Password', 'password') ?></div>
      </div></section>
      <section class="card"><div class="card__body stack">
        <h2 class="card__title">The owner's sign-in</h2>
        <div class="form-grid"><?= $field('owner_name', 'Name') ?><?= $field('owner_email', 'Email', 'email') ?><?= $field('owner_password', 'Password', 'password', 'At least 8 characters.') ?><?= $field('owner_password2', 'Password again', 'password') ?></div>
        <label class="check-row"><input class="check" type="checkbox" name="samples"<?= $v['samples'] ? ' checked' : '' ?>><span>Also add the sample enquiries, appointments, subscribers and colleagues from the admin design (to see the screens full; delete them later)</span></label>
      </div></section>
      <p style="margin-top:22px"><button type="submit" class="btn btn--gold btn--lg">Install</button></p>
    </form>
<?php endif; ?>
  </main>
</body>
</html>
