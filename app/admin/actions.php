<?php
/* Mangalam Jewellers — everything the admin changes: admin.js posts here (via admin/api.php) with an action
 * name, and each function checks, saves and answers with what the page should do next.
 *
 * Answers: message (the toast), text (its second line), reload / redirect, link (a link to share). */

/** Which permission each action needs ('' = anyone signed in) */
const ACTION_PERMISSIONS = [
    'profile.save' => '', 'notices.read' => '', 'upload.image' => '',
    'product.save' => 'products.edit', 'upload.model' => 'products.edit', 'product.duplicate' => 'products.edit', 'product.status' => 'products.edit', 'product.move' => 'products.edit', 'product.delete' => 'products.delete',
    'category.save' => 'products.edit', 'category.delete' => 'products.edit', 'category.reorder' => 'products.edit', 'category.menu' => 'products.edit',
    'collection.save' => 'products.edit', 'collection.delete' => 'products.edit', 'collection.reorder' => 'products.edit', 'collection.home' => 'products.edit',
    'home.save' => 'content.edit', 'page.save' => 'content.edit',
    'article.save' => 'content.edit', 'article.duplicate' => 'content.edit', 'article.status' => 'content.edit', 'article.delete' => 'content.edit',
    'testimonial.save' => 'content.edit', 'testimonial.delete' => 'content.edit', 'testimonial.reorder' => 'content.edit', 'testimonial.home' => 'content.edit',
    'media.upload' => 'media.edit', 'media.save' => 'media.edit', 'media.delete' => 'media.edit', 'media.optimise' => 'media.edit',
    'enquiry.status' => 'enquiries.reply', 'enquiry.message' => 'enquiries.reply', 'enquiry.preview' => 'enquiries.reply', 'enquiry.start' => 'enquiries.reply',
    'enquiry.contact' => 'enquiries.reply', 'enquiry.link' => 'enquiries.reply', 'enquiry.delete' => 'enquiries.reply', 'mail.fetch' => 'enquiries.reply',
    'template.save' => 'templates.edit', 'template.delete' => 'templates.edit', 'template.reorder' => 'templates.edit', 'template.toggle' => 'templates.edit',
    'template.preview' => 'templates.edit', 'template.test' => 'templates.edit',
    'appointment.save' => 'appointments.edit', 'appointment.delete' => 'appointments.edit',
    'subscriber.add' => 'subscribers.export', 'subscriber.status' => 'subscribers.export', 'subscriber.delete' => 'subscribers.export',
    'offer.save' => 'offers.edit', 'settings.save' => 'settings.edit', 'settings.mailtest' => 'settings.edit', 'settings.mailboxtest' => 'settings.edit',
    'catoffer.save' => 'offers.edit', 'catoffer.delete' => 'offers.edit', 'catoffer.reorder' => 'offers.edit', 'catoffer.live' => 'offers.edit',
    'offerspage.save' => 'offers.edit',
    'view3d.save' => 'products.edit', 'view3d.toggle' => 'products.edit', 'view3d.remove' => 'products.edit',
    'spin.frame' => 'products.edit', 'spin.save' => 'products.edit', 'spin.toggle' => 'products.edit', 'spin.remove' => 'products.edit',
    'user.invite' => 'users.manage', 'user.link' => 'users.manage', 'user.role' => 'users.manage', 'user.delete' => 'users.manage', 'user.permissions' => 'users.manage',
];

/** Page addresses that can't be used for a category (they are the website's own pages) */
const RESERVED_SLUGS = ['index', 'collections', 'jewellery', 'offers', 'product', 'about', 'craftsmanship', 'journal', 'article', 'contact', '404', 'api', 'admin', 'install', 'data', 'maintenance', 'assets', 'app', 'database'];

function run_action(string $action, array $me): array
{
    if (!array_key_exists($action, ACTION_PERMISSIONS)) fail('The admin does not know how to do that.');
    $perm = ACTION_PERMISSIONS[$action];
    if ($action === 'upload.image') {
        if (!can('products.edit', $me) && !can('content.edit', $me) && !can('media.edit', $me) && !can('offers.edit', $me)) fail('Your role cannot upload photographs.');
    } elseif ($perm !== '' && !can($perm, $me)) {
        fail('Your role does not allow this. The owner can change what each role may do in Users & roles.');
    }
    $fn = 'act_' . str_replace('.', '_', $action);
    return $fn($me);
}

/* ---------- Small checks ---------- */

function text_in(string $key, int $max, bool $required = false, string $label = ''): string
{
    $v = trim((string) input($key));
    if ($required && $v === '') fail('Please fill in ' . ($label ?: $key) . '.', [$key]);
    if (mb_strlen($v) > $max) fail(ucfirst($label ?: $key) . ' is too long (at most ' . $max . ' characters).', [$key]);
    return $v;
}

function date_in(string $key): ?string
{
    $v = trim((string) input($key));
    if ($v === '') return null;
    $d = DateTimeImmutable::createFromFormat('Y-m-d', $v);
    if (!$d || $d->format('Y-m-d') !== $v) fail('Please choose a valid date.', [$key]);
    return $v;
}

function url_in(string $key): string
{
    $v = trim((string) input($key));
    if ($v !== '' && !preg_match('#^https?://#i', $v)) fail('Web addresses start with https://', [$key]);
    return $v;
}

/** A web address that is free in $table (adds -2, -3… when the name is taken) */
function unique_slug(string $table, string $wanted, int $exceptId, bool $explicit, string $what): string
{
    $slug = slugify($wanted);
    if ($slug === '') fail('Please give it a name, so it gets a web address.', ['name']);
    $taken = fn ($s) => (bool) val("SELECT COUNT(*) FROM `$table` WHERE slug = ? AND id <> ?", [$s, $exceptId]);
    if ($table === 'categories' && in_array($slug, RESERVED_SLUGS, true)) {
        if ($explicit) fail('“' . $slug . '” is the address of one of the website\'s own pages. Choose another web address.', ['slug']);
        $slug .= '-jewellery';
    }
    if (!$taken($slug)) return $slug;
    if ($explicit) fail('The web address “' . $slug . '” is already used by another ' . $what . '.', ['slug']);
    for ($i = 2; $taken("$slug-$i"); $i++);
    return "$slug-$i";
}

function ids_in(): array
{
    $ids = int_ids(input_array('ids'));
    if (!$ids && input_int('id')) $ids = [input_int('id')];
    if (!$ids) fail('Choose at least one first.');
    return $ids;
}

/** Stored HTML uses the website's own image addresses (the editor shows them from ../) */
function editor_html_in(string $key): string
{
    $html = clean_html((string) ($_POST[$key] ?? ''));
    return preg_replace('/(<img[^>]+src=")(\.\.\/)+(assets\/)/i', '$1$3', $html);
}

function image_in(string $key): string
{
    $path = clean_image_path($_POST[$key] ?? '');
    if ($path !== '' && !is_file(ROOT_DIR . '/' . $path)) fail('One of the photographs could not be found. Please choose it again.', [$key]);
    return $path;
}

function reorder(string $table): array
{
    $ids = int_ids(input_array('ids'));
    $stmt = db()->prepare("UPDATE `$table` SET sort_order = ? WHERE id = ?");
    foreach ($ids as $i => $id) $stmt->execute([$i + 1, $id]);
    return [];
}

/* ---------- Your profile and notifications ---------- */

function act_profile_save(array $me): array
{
    $name = text_in('name', 120, true, 'your name');
    $email = mb_strtolower(text_in('email', 190, true, 'your email'));
    if (!valid_email($email)) fail('Please enter a valid email address.', ['email']);
    $password = (string) ($_POST['password'] ?? '');
    $changing = $email !== $me['email'] || $password !== '';
    if ($changing && !password_verify((string) ($_POST['current'] ?? ''), (string) $me['password_hash'])) fail('Your current password is needed to change your email or password — and it did not match.', ['current']);
    if ($email !== $me['email'] && val('SELECT COUNT(*) FROM users WHERE email = ? AND id <> ?', [$email, $me['id']])) fail('Someone on the team already uses that email.', ['email']);
    $data = ['name' => $name, 'email' => $email];
    if ($password !== '') {
        if ($p = password_problem($password)) fail($p, ['password']);
        $data['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
    }
    update('users', $data, ['id' => $me['id']]);
    return ['message' => 'Profile saved', 'text' => $password !== '' ? 'Use your new password next time you sign in.' : '', 'reload' => true];
}

function act_notices_read(array $me): array
{
    q('UPDATE users SET notices_seen_at = NOW() WHERE id = ?', [$me['id']]);
    return ['message' => 'All marked as read', 'reload' => true];
}

/* ---------- Photographs ---------- */

function act_upload_image(array $me): array
{
    $files = uploaded_files('file');
    if (!$files) fail('Choose a photograph to upload.');
    $folder = (string) input('folder', 'campaign');
    // The admin scales big photographs down before sending them; original_bytes is the size the photo was chosen at
    $before = max((int) input('original_bytes', 0), (int) $files[0]['size']);
    $m = store_upload($files[0], $folder);
    $thumb = $m['folder'] === 'products' ? small_image($m['path']) : $m['path'];
    return ['message' => 'Photo uploaded', 'text' => $m['name'] . ' is in the media library' . compression_note($m['path'], $before) . '.', 'path' => $m['path'], 'url' => asset($m['path']), 'thumb' => asset($thumb),
        'before' => $before, 'after' => served_bytes($m['path'])];
}

/** A .glb model for a product's 3D view: kept in assets/models and saved with the product */
function act_upload_model(array $me): array
{
    $files = uploaded_files('file');
    if (!$files) fail('Choose a .glb 3D model to upload.');
    $path = store_model($files[0]);
    return ['message' => '3D model uploaded', 'text' => 'Save the product to keep it.', 'path' => $path, 'url' => asset($path), 'name' => basename($path), 'size' => file_size_label((int) filesize(ROOT_DIR . '/' . $path))];
}

function act_media_upload(array $me): array
{
    $files = uploaded_files('files');
    if (!$files) fail('Choose or drop the images to upload first.');
    $folder = (string) input('folder', 'campaign');
    $names = [];
    $paths = [];
    foreach ($files as $f) { $m = store_upload($f, $folder); $names[] = $m['name']; $paths[] = $m['path']; }
    $before = max((int) input('original_bytes', 0), array_sum(array_column($files, 'size')));
    return ['message' => count($names) === 1 ? 'Image uploaded' : count($names) . ' images uploaded', 'text' => implode(', ', array_slice($names, 0, 3)) . (count($names) > 3 ? '…' : '') . compression_note($paths, $before), 'redirect' => 'media.html?folder=' . rawurlencode($folder)];
}

/** Light copies for photographs that have none yet (uploaded before, or added by hand); a batch at a time so no request runs long */
function act_media_optimise(array $me): array
{
    if (!gd_available()) fail('Turn on PHP\'s GD extension (in php.ini, extension=gd) so the website can make light copies of its photographs.');
    $r = optimise_images(20);
    return ['message' => $r['left'] ? 'Optimising photographs…' : 'Photographs optimised', 'done' => $r['done'], 'left' => $r['left'], 'saved' => $r['saved']];
}

function act_media_save(array $me): array
{
    $id = input_int('id');
    if (!row('SELECT id FROM media WHERE id = ?', [$id])) fail('That image is no longer in the library.');
    update('media', ['alt' => text_in('alt', 255)], ['id' => $id]);
    return ['message' => 'Alt text saved', 'reload' => true];
}

function act_media_delete(array $me): array
{
    $m = row('SELECT * FROM media WHERE id = ?', [input_int('id')]);
    if (!$m) fail('That image is no longer in the library.');
    if ($m['folder'] === 'brand') fail('The logo files are part of the design, so they can\'t be deleted here.');
    $used = media_usage()[$m['path']] ?? [];
    if ($used) fail('It is still used on: ' . implode(', ', array_slice($used, 0, 4)) . (count($used) > 4 ? '…' : '') . '. Choose another photograph there first.');
    // The photograph, its small copy and their AVIF / WebP copies
    foreach (image_files($m['path']) as $p) @unlink(ROOT_DIR . '/' . $p);
    q('DELETE FROM media WHERE id = ?', [$m['id']]);
    return ['message' => 'Image deleted', 'text' => $m['name'], 'reload' => true];
}

/* ---------- Products ---------- */

function act_product_save(array $me): array
{
    $id = input_int('id');
    $old = $id ? row('SELECT * FROM products WHERE id = ?', [$id]) : null;
    if ($id && !$old) fail('That product no longer exists.');
    $name = text_in('name', 160, true, 'the name');
    $slug = unique_slug('products', input('slug') !== '' ? (string) input('slug') : $name, $id, input('slug') !== '' && (!$old || input('slug') !== $old['slug']), 'product');
    $price = input_money('price');
    if ($price === null) fail('Please give the price.', ['price']);
    $offer = input_money('offerPrice');
    if ($offer !== null && $offer >= $price) fail('The offer price should be lower than the price.', ['offerPrice']);
    $category = input('category') !== '' ? val('SELECT id FROM categories WHERE slug = ?', [input('category')]) : null;
    $intent = (string) input('intent');
    $status = $intent === 'draft' ? 'draft' : strtolower((string) input('status', 'published'));
    if (!in_array($status, ['published', 'draft', 'hidden'], true)) $status = 'draft';
    $huid = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) input('huid')));
    if ($huid !== '' && strlen($huid) !== 6) fail('A HUID is 6 letters and numbers.', ['huid']);
    // The 3D view & customiser (assets/js/admin-3d.js keeps its settings in one JSON field)
    $view3dIn = json_decode((string) ($_POST['view3d'] ?? ''), true);
    $view3d = view3d_config($view3dIn);
    if ($view3d && $view3d['enabled'] && ($view3dIn['source'] ?? '') === 'model' && $view3d['source'] !== 'model') {
        fail('The 3D model could not be found. Upload it again, or choose one of the house designs.');
    }
    // The 360° view (assets/js/admin-spin.js); kept as it was when the form did not send it
    $spin = array_key_exists('spin', $_POST) ? spin_in() : spin_config($old['spin'] ?? null);
    $gallery = [];
    foreach (input_array('gallery') as $path) {
        $path = clean_image_path($path);
        if ($path !== '' && is_file(ROOT_DIR . '/' . $path)) $gallery[] = $path;
    }
    $data = [
        'name' => $name, 'slug' => $slug, 'category_id' => $category ? (int) $category : null, 'price' => $price, 'offer_price' => $offer,
        'metal' => in_array(input('metal'), METALS, true) ? input('metal') : 'Gold',
        'purity' => in_array(input('purity'), PURITIES, true) ? input('purity') : '22K',
        'stone' => in_array(input('stone'), STONES, true) ? input('stone') : 'None',
        'style' => in_array(input('style'), STYLES, true) ? input('style') : 'Classic',
        'line' => text_in('collection', 80) ?: LINES[0],
        'summary' => text_in('summary', 400), 'details' => editor_html_in('details'),
        'gross_weight' => input_decimal('grossWeight'), 'gold_weight' => input_decimal('goldWeight'), 'stone_weight' => input_decimal('stoneWeight'),
        'making_charges' => input_decimal('making'), 'huid' => $huid ?: null,
        'is_new' => input_bool('isNew'), 'show_price' => input_bool('showPrice'), 'allow_enquiry' => input_bool('enquiries'),
        'status' => $status, 'publish_on' => date_in('publishDate'),
        'seo_title' => text_in('seoTitle', 160), 'seo_desc' => text_in('seoDesc', 255),
        'view3d' => $view3d ? json_encode($view3d, JSON_UNESCAPED_SLASHES) : null,
        'spin' => $spin ? json_encode($spin, JSON_UNESCAPED_SLASHES) : null,
    ];
    $featured = input_bool('featured');
    if (!$old || ($featured !== ((int) $old['featured_order'] > 0))) {
        $data['featured_order'] = $featured ? (int) val('SELECT COALESCE(MAX(featured_order), 0) + 1 FROM products') : 0;
    }
    db()->beginTransaction();
    if ($old) {
        update('products', $data, ['id' => $id]);
    } else {
        $data['sort_order'] = (int) val('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM products');
        $id = insert('products', $data);
    }
    q('DELETE FROM product_images WHERE product_id = ?', [$id]);
    foreach ($gallery as $i => $path) insert('product_images', ['product_id' => $id, 'path' => $path, 'sort_order' => $i + 1]);
    q('UPDATE products SET updated_at = NOW() WHERE id = ?', [$id]);
    db()->commit();
    if ($old) spin_cleanup(spin_frames_of($old)); // frames taken out of its 360° view
    $message = $status === 'draft' ? 'Draft saved' : ($status === 'hidden' ? 'Saved — hidden from the website' : ($old ? 'Product updated' : 'Product published'));
    $text = $status === 'published' && !$gallery ? 'It has no photographs yet, so the category image stands in.' : ($status === 'draft' ? 'Only the team can see a draft until it is published.' : '');
    return ['message' => $message, 'text' => $text, 'redirect' => 'product-edit.html?id=' . $id];
}

function act_product_duplicate(array $me): array
{
    $p = row('SELECT * FROM products WHERE id = ?', [input_int('id')]);
    if (!$p) fail('That product no longer exists.');
    $copy = $p;
    unset($copy['id'], $copy['created_at'], $copy['updated_at']);
    $copy['name'] = $p['name'] . ' (copy)';
    $copy['slug'] = unique_slug('products', $p['slug'] . '-copy', 0, false, 'product');
    $copy['status'] = 'draft';
    $copy['featured_order'] = 0;
    $copy['sort_order'] = (int) val('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM products');
    $newId = insert('products', $copy);
    q('INSERT INTO product_images (product_id, path, sort_order) SELECT ?, path, sort_order FROM product_images WHERE product_id = ? ORDER BY sort_order', [$newId, $p['id']]);
    return ['message' => 'Product duplicated', 'text' => '“' . $copy['name'] . '” was added as a draft.', 'reload' => true];
}

function act_product_status(array $me): array
{
    $ids = ids_in();
    $status = (string) input('status');
    if (!in_array($status, ['published', 'draft', 'hidden'], true)) fail('Unknown status.');
    q('UPDATE products SET status = ? WHERE id IN (' . placeholders($ids) . ')', array_merge([$status], $ids));
    $n = count($ids);
    return ['message' => $status === 'published' ? ($n === 1 ? 'Product published' : "$n products published") : ($n === 1 ? 'Product hidden' : "$n products hidden"),
        'text' => $status === 'hidden' ? 'Hidden pieces stay in the catalogue but are not shown on the website.' : '', 'reload' => true];
}

function act_product_move(array $me): array
{
    $ids = ids_in();
    $cat = row('SELECT * FROM categories WHERE id = ?', [input_int('category')]);
    if (!$cat) fail('Choose the category to move them to.');
    q('UPDATE products SET category_id = ? WHERE id IN (' . placeholders($ids) . ')', array_merge([(int) $cat['id']], $ids));
    return ['message' => plural(count($ids), 'product') . ' moved', 'text' => 'They are now in ' . $cat['name'] . '.', 'reload' => true];
}

function act_product_delete(array $me): array
{
    $ids = ids_in();
    $frames = array_merge([], ...array_map('spin_frames_of', rows('SELECT spin FROM products WHERE id IN (' . placeholders($ids) . ')', $ids)));
    q('DELETE FROM products WHERE id IN (' . placeholders($ids) . ')', $ids);
    spin_cleanup($frames);
    $then = (string) input('then');
    return ['message' => count($ids) === 1 ? 'Product deleted' : count($ids) . ' products deleted'] + ($then === 'products.html' ? ['redirect' => 'products.html'] : ['reload' => true]);
}

/* ---------- 3D jewellery (a product's 3D view & customiser, edited on its own) ---------- */

function product_for_3d(int $id): array
{
    $p = row('SELECT id, name, view3d FROM products WHERE id = ?', [$id]);
    if (!$p) fail('Choose the product this 3D view is for.', ['pick']);
    return $p;
}

function act_view3d_save(array $me): array
{
    $p = product_for_3d(input_int('id') ?: input_int('pick'));
    $in = json_decode((string) ($_POST['view3d'] ?? ''), true);
    $v = view3d_config($in);
    if (!$v) fail('The 3D settings did not arrive. Please try again.');
    if (($in['source'] ?? '') === 'model' && $v['source'] !== 'model') fail('The 3D model could not be found. Upload it again, or choose one of the house designs.');
    q('UPDATE products SET view3d = ?, updated_at = NOW() WHERE id = ?', [json_encode($v, JSON_UNESCAPED_SLASHES), $p['id']]);
    return ['message' => $v['enabled'] ? '3D view saved' : 'Saved — 3D view switched off', 'text' => $v['enabled'] ? $p['name'] . ' shows “View in 3D & customise” on its page.' : 'Switch it on when it is ready for visitors.', 'reload' => true];
}

function act_view3d_toggle(array $me): array
{
    $p = product_for_3d(input_int('id'));
    $v = view3d_config($p['view3d']);
    if (!$v) fail('This piece has no 3D view yet.');
    $v['enabled'] = input_bool('value');
    q('UPDATE products SET view3d = ?, updated_at = NOW() WHERE id = ?', [json_encode($v, JSON_UNESCAPED_SLASHES), $p['id']]);
    return ['message' => $v['enabled'] ? '3D view on the website' : '3D view switched off', 'text' => $p['name']];
}

function act_view3d_remove(array $me): array
{
    $p = product_for_3d(input_int('id'));
    q('UPDATE products SET view3d = NULL, updated_at = NOW() WHERE id = ?', [$p['id']]);
    return ['message' => '3D view removed', 'text' => $p['name'] . ' is otherwise unchanged.', 'reload' => true];
}

/* ---------- 360° views (the piece photographed as it turns; edited here or in the product editor) ---------- */

/** The frames a product's 360° view had, as stored */
function spin_frames_of(array $p): array
{
    $s = json_decode((string) ($p['spin'] ?? ''), true);
    return array_values(array_filter(is_array($s['frames'] ?? null) ? $s['frames'] : [], 'is_string'));
}

/** The 360° view sent by the editor (assets/js/admin-spin.js keeps it in one JSON field), checked — or null for none */
function spin_in(): ?array
{
    $in = json_decode((string) ($_POST['spin'] ?? ''), true);
    if (!is_array($in)) return null;
    $s = spin_config($in);
    $sent = count(array_unique(array_filter(is_array($in['frames'] ?? null) ? $in['frames'] : [], 'is_string')));
    if ($s && count($s['frames']) < min($sent, SPIN_MAX_FRAMES)) {
        fail(plural(min($sent, SPIN_MAX_FRAMES) - count($s['frames']), 'frame') . ' of the 360° view could not be found on the server. Make the frames again.');
    }
    if ($s && $s['enabled'] && count($s['frames']) < SPIN_MIN_FRAMES) {
        fail('A 360° view needs at least ' . SPIN_MIN_FRAMES . ' frames to turn. Add more, or switch it off for now.');
    }
    return $s;
}

function product_for_spin(int $id): array
{
    $p = row('SELECT id, name, spin FROM products WHERE id = ?', [$id]);
    if (!$p) fail('Choose the product this 360° view is for.', ['pick']);
    return $p;
}

/** Saves a product's 360° view (null removes it) and deletes the frames it no longer uses */
function save_spin(array $p, ?array $s): void
{
    q('UPDATE products SET spin = ?, updated_at = NOW() WHERE id = ?', [$s ? json_encode($s, JSON_UNESCAPED_SLASHES) : null, $p['id']]);
    spin_cleanup(spin_frames_of($p));
}

/** One frame, taken from a turntable video or a photo and cropped square by the admin */
function act_spin_frame(array $me): array
{
    $files = uploaded_files('file');
    if (!$files) fail('Choose the frames to upload.');
    $r = store_spin_frame($files[0], (string) input('set'), (string) input('named'), input_int('index'));
    return $r + ['url' => asset($r['path'])];
}

function act_spin_save(array $me): array
{
    $p = product_for_spin(input_int('id') ?: input_int('pick'));
    $s = spin_in();
    if (!$s) fail('Make the frames first — from a turntable video or a set of photos.');
    save_spin($p, $s);
    return ['message' => $s['enabled'] ? '360° view saved' : 'Saved — 360° view switched off',
        'text' => $s['enabled'] ? $p['name'] . ' turns in 360° on its page.' : 'Switch it on when it is ready for visitors.', 'reload' => true];
}

function act_spin_toggle(array $me): array
{
    $p = product_for_spin(input_int('id'));
    $s = spin_config($p['spin']);
    if (!$s) fail('This piece has no 360° view yet.');
    $s['enabled'] = input_bool('value');
    if ($s['enabled'] && count($s['frames']) < SPIN_MIN_FRAMES) fail('A 360° view needs at least ' . SPIN_MIN_FRAMES . ' frames to turn. Edit it and add more first.');
    q('UPDATE products SET spin = ?, updated_at = NOW() WHERE id = ?', [json_encode($s, JSON_UNESCAPED_SLASHES), $p['id']]);
    return ['message' => $s['enabled'] ? '360° view on the website' : '360° view switched off', 'text' => $p['name']];
}

function act_spin_remove(array $me): array
{
    $p = product_for_spin(input_int('id'));
    save_spin($p, null);
    return ['message' => '360° view removed', 'text' => $p['name'] . ' keeps its photographs; only the turning frames were deleted.', 'reload' => true];
}

/* ---------- Categories and collections ---------- */

function act_category_save(array $me): array
{
    $id = input_int('id');
    $old = $id ? row('SELECT * FROM categories WHERE id = ?', [$id]) : null;
    if ($id && !$old) fail('That category no longer exists.');
    $name = text_in('name', 80, true, 'the name');
    $explicit = input('slug') !== '' && (!$old || input('slug') !== $old['slug']);
    $slug = unique_slug('categories', input('slug') !== '' ? (string) input('slug') : $name, $id, $explicit, 'category');
    $data = [
        'name' => $name, 'slug' => $slug, 'heading' => text_in('heading', 160), 'intro' => text_in('intro', 600),
        'image' => image_in('image'), 'banner' => image_in('banner'),
        'seo_title' => text_in('seoTitle', 160), 'seo_desc' => text_in('seoDesc', 255), 'in_menu' => input_bool('inMenu'),
    ];
    if ($old) {
        update('categories', $data, ['id' => $id]);
        if ($old['slug'] !== $slug) q("UPDATE collections SET rule_value = ? WHERE rule_field = 'category' AND rule_value = ?", [$slug, $old['slug']]);
    } else {
        $data['sort_order'] = (int) val('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM categories');
        insert('categories', $data);
    }
    return ['message' => 'Category saved', 'text' => 'Its page is /' . $slug . '.html', 'reload' => true];
}

function act_category_delete(array $me): array
{
    $c = row('SELECT * FROM categories WHERE id = ?', [input_int('id')]);
    if (!$c) fail('That category no longer exists.');
    q('DELETE FROM categories WHERE id = ?', [$c['id']]);
    return ['message' => 'Category deleted', 'text' => 'Its pieces are in the catalogue without a category.', 'reload' => true];
}

function act_category_reorder(array $me): array
{
    reorder('categories');
    return ['message' => 'Category order updated'];
}

function act_category_menu(array $me): array
{
    $on = input_bool('value');
    q('UPDATE categories SET in_menu = ? WHERE id = ?', [$on ? 1 : 0, input_int('id')]);
    return ['message' => $on ? 'Shown in the menu' : 'Hidden from the menu'];
}

function act_collection_save(array $me): array
{
    $id = input_int('id');
    if ($id && !row('SELECT id FROM collections WHERE id = ?', [$id])) fail('That collection no longer exists.');
    $field = (string) input('field');
    $value = (string) input('value');
    if (!in_array($field, ['category', 'metal', 'style'], true)) fail('Choose which detail the pieces share.', ['field']);
    $valid = match ($field) {
        'category' => (bool) category_by_slug($value),
        'metal' => in_array($value, METALS, true),
        'style' => in_array($value, STYLES, true),
    };
    if (!$valid) fail('That value does not go with “' . $field . '” — for a ' . $field . ', choose one of the ' . ($field === 'category' ? 'categories' : $field . 's') . '.', ['value']);
    $data = ['title' => text_in('name', 80, true, 'the title'), 'kicker' => text_in('kicker', 160), 'image' => image_in('image'),
        'rule_field' => $field, 'rule_value' => $value, 'on_home' => input_bool('onHome')];
    if ($id) update('collections', $data, ['id' => $id]);
    else { $data['sort_order'] = (int) val('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM collections'); insert('collections', $data); }
    return ['message' => 'Collection saved', 'reload' => true];
}

function act_collection_delete(array $me): array
{
    q('DELETE FROM collections WHERE id = ?', [input_int('id')]);
    return ['message' => 'Collection deleted', 'text' => 'Its pieces stay in the catalogue.', 'reload' => true];
}

function act_collection_reorder(array $me): array
{
    reorder('collections');
    return ['message' => 'Collection order updated'];
}

function act_collection_home(array $me): array
{
    $on = input_bool('value');
    q('UPDATE collections SET on_home = ? WHERE id = ?', [$on ? 1 : 0, input_int('id')]);
    return ['message' => $on ? 'Shown on the homepage' : 'Taken off the homepage'];
}

/* ---------- Homepage and pages ---------- */

function act_home_save(array $me): array
{
    $visible = array_flip(array_map('strval', input_array('visible')));
    $sections = [];
    foreach (input_array('sections') as $key) {
        if (isset(HOME_SECTIONS[$key])) $sections[] = ['key' => $key, 'visible' => $key === 'hero' || isset($visible[$key])];
    }
    $stats = [];
    $values = input_array('stat_value');
    $labels = input_array('stat_label');
    foreach ($values as $i => $v) {
        $v = trim((string) $v);
        $l = trim((string) ($labels[$i] ?? ''));
        if ($v !== '' || $l !== '') $stats[] = [mb_substr($v, 0, 12), mb_substr($l, 0, 40)];
    }
    $panels = [];
    foreach (input_array('panel_image') as $i => $img) {
        $path = clean_image_path($img);
        $panels[] = ['href' => preg_match('/^[a-z0-9-]+\.html(\?[\w=&%-]*)?$/i', (string) ($_POST['panel_href'][$i] ?? '')) ? $_POST['panel_href'][$i] : 'bridal.html',
            'image' => $path, 'pos' => preg_match('/^[\d.% ]+$/', (string) ($_POST['panel_pos'][$i] ?? '')) ? $_POST['panel_pos'][$i] : '50% 50%',
            'label' => mb_substr(trim((string) ($_POST['panel_label'][$i] ?? '')), 0, 40), 'title' => mb_substr(trim((string) ($_POST['panel_title'][$i] ?? '')), 0, 60),
            'text' => mb_substr(trim((string) ($_POST['panel_text'][$i] ?? '')), 0, 200), 'cta' => mb_substr(trim((string) ($_POST['panel_cta'][$i] ?? 'Explore')), 0, 30)];
    }
    $heroImage = image_in('hero_image');
    if ($heroImage === '') fail('The hero needs a photograph.');
    db()->beginTransaction();
    save_settings([
        'home_sections' => $sections, 'hero_image' => $heroImage,
        'hero_eyebrow' => text_in('hero_eyebrow', 80), 'hero_line1' => text_in('hero_line1', 60), 'hero_line2' => text_in('hero_line2', 60),
        'hero_lead' => text_in('hero_lead', 240), 'hero_stats' => $stats, 'bridal_panels' => $panels,
    ]);
    // Shop-the-look pins
    q('DELETE FROM hotspots');
    $xs = input_array('pin_x');
    $ys = input_array('pin_y');
    $flips = input_array('pin_flip');
    foreach (input_array('pin_product') as $i => $pid) {
        if (!row('SELECT id FROM products WHERE id = ?', [(int) $pid])) continue;
        insert('hotspots', ['product_id' => (int) $pid, 'x' => max(0, min(100, (float) ($xs[$i] ?? 50))), 'y' => max(0, min(100, (float) ($ys[$i] ?? 50))), 'flip' => !empty($flips[$i]) ? 1 : 0, 'sort_order' => $i + 1]);
    }
    // Featured pieces (the Signature tab), in order
    q('UPDATE products SET featured_order = 0 WHERE featured_order <> 0');
    foreach (int_ids(input_array('featured')) as $i => $pid) q('UPDATE products SET featured_order = ? WHERE id = ?', [$i + 1, $pid]);
    db()->commit();
    return ['message' => 'Homepage published', 'text' => 'Visitors see the new homepage straight away.', 'reload' => true];
}

function act_page_save(array $me): array
{
    $key = (string) input('key');
    $data = ['heading' => text_in('heading', 200), 'banner' => image_in('banner'), 'seo_title' => text_in('seoTitle', 160), 'seo_desc' => text_in('seoDesc', 255)];
    if (preg_match('/^page:([a-z0-9-]+)$/', $key, $m)) {
        if (!row('SELECT id FROM pages WHERE page_key = ?', [$m[1]])) fail('That page no longer exists.');
        update('pages', $data + ['lead' => text_in('intro', 600)], ['page_key' => $m[1]]);
    } elseif (preg_match('/^category:(\d+)$/', $key, $m)) {
        if (!row('SELECT id FROM categories WHERE id = ?', [(int) $m[1]])) fail('That category no longer exists.');
        update('categories', $data + ['intro' => text_in('intro', 600)], ['id' => (int) $m[1]]);
    } else {
        fail('That page could not be found.');
    }
    return ['message' => 'Page saved', 'reload' => true];
}

/* ---------- Journal ---------- */

function act_article_save(array $me): array
{
    $id = input_int('id');
    $old = $id ? row('SELECT * FROM articles WHERE id = ?', [$id]) : null;
    if ($id && !$old) fail('That story no longer exists.');
    $title = text_in('title', 200, true, 'the title');
    $slug = unique_slug('articles', input('slug') !== '' ? (string) input('slug') : $title, $id, input('slug') !== '' && (!$old || input('slug') !== $old['slug']), 'story');
    $status = input('intent') === 'draft' ? 'draft' : strtolower((string) input('status', 'published'));
    if (!in_array($status, ['published', 'draft', 'scheduled'], true)) $status = 'draft';
    $date = date_in('date');
    if ($status !== 'draft' && !$date) $date = date('Y-m-d');
    if ($status === 'scheduled' && $date <= date('Y-m-d')) $status = 'published';
    $body = editor_html_in('body');
    $words = str_word_count(html_text($body));
    $read = input_int('readTime', 0) ?: max(1, (int) ceil($words / 200));
    $data = [
        'title' => $title, 'slug' => $slug, 'excerpt' => text_in('excerpt', 255), 'body' => $body,
        'category' => mb_substr(trim((string) input('category', 'Guide')), 0, 40) ?: 'Guide',
        'image' => image_in('image'), 'read_time' => max(1, min(120, $read)), 'author' => text_in('author', 80),
        'status' => $status, 'published_on' => $date, 'on_home' => input_bool('onHome'),
        'seo_title' => text_in('seoTitle', 160), 'seo_desc' => text_in('seoDesc', 255),
    ];
    if ($old) update('articles', $data, ['id' => $id]);
    else $id = insert('articles', $data);
    $live = $status !== 'draft' && $date <= date('Y-m-d');
    return ['message' => $status === 'draft' ? 'Draft saved' : ($live ? ($old ? 'Story updated' : 'Story published') : 'Story scheduled'),
        'text' => $status === 'draft' ? 'Only the team can see a draft until it is published.' : ($live ? '' : 'It appears in the journal on ' . long_date($date) . '.'),
        'redirect' => 'article-edit.html?id=' . $id];
}

function act_article_duplicate(array $me): array
{
    $a = row('SELECT * FROM articles WHERE id = ?', [input_int('id')]);
    if (!$a) fail('That story no longer exists.');
    $copy = $a;
    unset($copy['id'], $copy['created_at'], $copy['updated_at']);
    $copy['title'] = $a['title'] . ' (copy)';
    $copy['slug'] = unique_slug('articles', $a['slug'] . '-copy', 0, false, 'story');
    $copy['status'] = 'draft';
    insert('articles', $copy);
    return ['message' => 'Story duplicated', 'text' => '“' . $copy['title'] . '” was saved as a draft.', 'reload' => true];
}

function act_article_status(array $me): array
{
    $a = row('SELECT * FROM articles WHERE id = ?', [input_int('id')]);
    if (!$a) fail('That story no longer exists.');
    if (input('status') === 'published') {
        // "Publish" means make it live now, so a missing or future date becomes today
        $date = ($a['published_on'] && $a['published_on'] <= date('Y-m-d')) ? $a['published_on'] : date('Y-m-d');
        update('articles', ['status' => 'published', 'published_on' => $date], ['id' => $a['id']]);
        return ['message' => 'Story published', 'reload' => true];
    }
    update('articles', ['status' => 'draft'], ['id' => $a['id']]);
    return ['message' => 'Story unpublished', 'text' => 'It is now a draft and hidden from the website.', 'reload' => true];
}

function act_article_delete(array $me): array
{
    q('DELETE FROM articles WHERE id = ?', [input_int('id')]);
    return ['message' => 'Story deleted'] + (input('then') === 'journal.html' ? ['redirect' => 'journal.html'] : ['reload' => true]);
}

/* ---------- Testimonials ---------- */

function act_testimonial_save(array $me): array
{
    $id = input_int('id');
    if (!input_bool('consent')) fail('Please confirm they agreed to appear on the website.', ['consent']);
    $data = ['quote' => text_in('quote', 400, true, 'their words'), 'who' => text_in('who', 80, true, 'their name'), 'occasion' => text_in('occasion', 120), 'consent' => 1];
    if ($id) update('testimonials', $data, ['id' => $id]);
    else { $data['sort_order'] = (int) val('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM testimonials'); insert('testimonials', $data); }
    return ['message' => 'Testimonial saved', 'reload' => true];
}

function act_testimonial_delete(array $me): array
{
    q('DELETE FROM testimonials WHERE id = ?', [input_int('id')]);
    return ['message' => 'Testimonial deleted', 'reload' => true];
}

function act_testimonial_reorder(array $me): array
{
    reorder('testimonials');
    return ['message' => 'Order updated'];
}

function act_testimonial_home(array $me): array
{
    $on = input_bool('value');
    q('UPDATE testimonials SET on_home = ? WHERE id = ?', [$on ? 1 : 0, input_int('id')]);
    return ['message' => $on ? 'Shown on the homepage' : 'Taken off the homepage'];
}

/* ---------- Enquiries and appointments ---------- */

function act_enquiry_status(array $me): array
{
    $status = (string) input('value');
    if (!in_array($status, ['new', 'replied', 'closed', 'archived'], true)) fail('Unknown status.');
    update('enquiries', ['status' => $status], ['id' => input_int('id')]);
    return ['message' => $status === 'archived' ? 'Enquiry archived' : 'Status updated', 'text' => $status === 'archived' ? 'Find it again under the Archived filter.' : '', 'reload' => $status === 'archived'];
}

/**
 * A message in a conversation, as the composer sends it: an email in the house style, a WhatsApp message (the page
 * has opened WhatsApp with it; this keeps the copy), or a note — the team's own, or what the customer said on WhatsApp or the phone.
 */
function act_enquiry_message(array $me): array
{
    $en = enquiry_by_id(input_int('id'));
    if (!$en) fail('That conversation no longer exists.');
    $vars = template_vars($en, $me);
    $message = fill_template(text_in('message', 10000, true, 'your message'), $vars);
    $first = explode(' ', trim($en['name']))[0];
    $back = 'enquiries.html?open=' . $en['id'];
    switch ((string) input('channel', 'email')) {
        case 'note':
            $kind = (string) input('kind', 'note');
            if ($kind === 'whatsapp_in' || $kind === 'phone_in') {
                add_message($en, ['direction' => 'in', 'channel' => $kind === 'phone_in' ? 'phone' : 'whatsapp', 'author' => $en['name'], 'message' => $message]);
                return ['message' => 'Added to the conversation', 'text' => 'It is marked New until someone answers.', 'redirect' => $back];
            }
            add_message($en, ['direction' => 'out', 'channel' => 'note', 'author' => $me['name'], 'message' => $message]);
            return ['message' => 'Note added', 'text' => 'Only the team can see it.', 'redirect' => $back];
        case 'whatsapp':
            if (whatsapp_number((string) $en['phone']) === '') fail('Add ' . $first . '’s mobile number first (⋯ › Edit contact details).');
            add_message($en, ['direction' => 'out', 'channel' => 'whatsapp', 'author' => $me['name'], 'message' => $message]);
            return ['message' => 'Kept in the conversation', 'text' => 'Press send in WhatsApp, in the window that opened.', 'redirect' => $back];
        case 'email':
            if (!valid_email((string) $en['email'])) fail($first . ' has no email address — add one (⋯ › Edit contact details), or send it on WhatsApp.');
            [$subject, $heading, $opts] = composer_email_in($en, $me, $vars);
            [$sent, $messageId] = send_conversation_email($en, ['subject' => $subject, 'heading' => $heading, 'body' => $message], $opts);
            add_message($en, ['direction' => 'out', 'channel' => 'email', 'author' => $me['name'], 'subject' => $subject, 'message' => $message, 'emailed' => $sent ? 1 : 0, 'message_id' => $messageId]);
            return ['message' => $sent ? 'Email sent' : 'Reply saved', 'redirect' => $back,
                'text' => $sent ? $first . ' will receive it in the house style.' : rtrim(mail_error(), '.') . ' — it is on ' . $first . '’s conversation page: send them the link (⋯ › Copy their conversation link).'];
    }
    fail('Choose Email, WhatsApp or Note.');
}

/** The composer's subject, heading and email options (the piece, a template's button) */
function composer_email_in(array $en, array $me, array $vars): array
{
    $subject = with_ref(fill_template(text_in('subject', 200), $vars) ?: enquiry_subject($en), (int) $en['id']);
    $heading = fill_template(text_in('heading', 160), $vars);
    $opts = ['author' => $me['name'], 'product' => input_bool('show_product'), 'quote' => empty($en['sample'])];
    $t = input_int('template') ? row('SELECT * FROM message_templates WHERE id = ?', [input_int('template')]) : null;
    if ($t && ($button = template_button($t, $en, $vars))) $opts['button'] = $button;
    return [$subject, $heading, $opts];
}

/** The composer's email as the customer will see it (also for a conversation not started yet) */
function act_enquiry_preview(array $me): array
{
    $en = input_int('id') ? enquiry_by_id(input_int('id')) : draft_enquiry_in();
    if (!$en) fail('That conversation no longer exists.');
    $vars = template_vars($en, $me);
    [$subject, $heading, $opts] = composer_email_in($en, $me, $vars);
    $body = fill_template((string) input('message'), $vars);
    if (trim($body) === '') fail('Write the message first.');
    $m = conversation_email($en, ['subject' => $subject, 'heading' => $heading, 'body' => $body], $opts);
    return ['message' => 'Preview', 'html' => email_html($m), 'subject' => $subject, 'to' => $en['name'] . ($en['email'] ? ' <' . $en['email'] . '>' : '')];
}

/** The New message form as a conversation-to-be, for its preview */
function draft_enquiry_in(): array
{
    $product = input_int('product') ? row('SELECT name, slug FROM products WHERE id = ?', [input_int('product')]) : null;
    $next = (int) val("SELECT AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'enquiries'") ?: 1;
    return ['id' => $next, 'sample' => true, 'token' => str_repeat('0', 32), 'name' => text_in('name', 120) ?: 'the customer', 'email' => text_in('email', 190), 'phone' => text_in('phone', 40),
        'source' => 'admin', 'topic' => '', 'subject' => text_in('subject', 200), 'message' => '', 'message_id' => '', 'created_at' => date('Y-m-d H:i:s'),
        'product_name' => $product['name'] ?? '', 'product_slug' => $product['slug'] ?? ''];
}

/** Writes to a customer first (New message): a conversation started by the team */
function act_enquiry_start(array $me): array
{
    $name = text_in('name', 120, true, 'their name');
    $email = mb_strtolower(text_in('email', 190));
    $phone = text_in('phone', 40);
    $channel = input('channel') === 'whatsapp' ? 'whatsapp' : 'email';
    if ($email !== '' && !valid_email($email)) fail('Please check the email address.', ['email']);
    if ($phone !== '' && !preg_match('/^[\d\s+()\-.]{6,40}$/', $phone)) fail('Please check the phone number.', ['phone']);
    if ($channel === 'email' && $email === '') fail('Add their email address — or choose WhatsApp.', ['email']);
    if ($channel === 'whatsapp' && whatsapp_number($phone) === '') fail('Add their mobile number for WhatsApp (with the country code if it is not Indian).', ['phone']);
    text_in('message', 10000, true, 'your message');
    $product = input_int('product') ? row('SELECT id, name FROM products WHERE id = ?', [input_int('product')]) : null;
    $subject = trim(preg_replace('/\s*\[MJ-\d+\]/i', '', text_in('subject', 200)));
    $id = insert('enquiries', [
        'token' => new_token(), 'name' => $name, 'email' => $email, 'phone' => $phone, 'source' => 'admin', 'product_id' => $product['id'] ?? null,
        'subject' => $subject ?: ($product ? 'The ' . $product['name'] : 'A note from ' . setting('store_name', 'Mangalam Jewellers')),
        'message' => '', 'status' => 'replied', 'last_at' => date('Y-m-d H:i:s'),
    ]);
    // The first message goes as any other (the subject is now the conversation's own)
    $_POST['id'] = (string) $id;
    $_POST['subject'] = '';
    return act_enquiry_message($me);
}

function act_enquiry_contact(array $me): array
{
    $en = enquiry_by_id(input_int('id'));
    if (!$en) fail('That conversation no longer exists.');
    $email = mb_strtolower(text_in('email', 190));
    $phone = text_in('phone', 40);
    if ($email !== '' && !valid_email($email)) fail('Please check the email address.', ['email']);
    if ($phone !== '' && !preg_match('/^[\d\s+()\-.]{6,40}$/', $phone)) fail('Please check the phone number.', ['phone']);
    update('enquiries', ['name' => text_in('name', 120, true, 'their name'), 'email' => $email, 'phone' => $phone], ['id' => $en['id']]);
    return ['message' => 'Contact details saved', 'redirect' => 'enquiries.html?open=' . $en['id']];
}

/** The customer's private link, to send by WhatsApp or SMS when an email cannot go */
function act_enquiry_link(array $me): array
{
    $en = enquiry_by_id(input_int('id'));
    if (!$en) fail('That conversation no longer exists.');
    $first = explode(' ', trim($en['name']))[0];
    return ['message' => 'Conversation link', 'link' => conversation_url($en), 'linkTitle' => $first . '’s conversation page',
        'linkText' => 'Everything written in this conversation (except notes) is on this page, and ' . $first . ' can reply there. Anyone with the link can open it — send it only to ' . $first . '.'];
}

/** Reads new replies from the mailbox now (Check for replies, and every few minutes while Enquiries is open) */
function act_mail_fetch(array $me): array
{
    if (!setting('imap_enabled', false)) fail('Reading replies is off — switch it on in Settings › Notifications.');
    try {
        $r = fetch_replies();
    } catch (Throwable $e) {
        fail($e->getMessage());
    }
    $n = $r['added'] + $r['started'];
    return ['message' => $n ? plural($n, 'new message') . ' arrived' : 'No new replies', 'added' => $n,
        'text' => $n ? '' : ($r['read'] ? plural($r['read'], 'new email') . ' in the mailbox, none of them a reply to a conversation.' : 'Nothing new in the mailbox.'),
        'reload' => $n > 0 && !input_bool('quiet')];
}

function act_enquiry_delete(array $me): array
{
    q('DELETE FROM enquiries WHERE id = ?', [input_int('id')]);
    return ['message' => 'Enquiry deleted', 'redirect' => 'enquiries.html'];
}

function act_appointment_save(array $me): array
{
    $id = input_int('id');
    if ($id && !row('SELECT id FROM appointments WHERE id = ?', [$id])) fail('That appointment no longer exists.');
    $email = mb_strtolower(text_in('email', 190));
    if ($email !== '' && !valid_email($email)) fail('Please check the email address.', ['email']);
    $date = date_in('date');
    if (!$date) fail('Please choose the day.', ['date']);
    $time = (string) input('time');
    if ($time !== '' && !in_array($time, time_slots(), true)) fail('Please choose one of the times.', ['time']);
    $status = (string) input('status', 'pending');
    if (!in_array($status, ['pending', 'confirmed', 'completed', 'cancelled'], true)) $status = 'pending';
    $data = ['name' => text_in('name', 120, true, 'the customer\'s name'), 'phone' => text_in('phone', 40), 'email' => $email, 'date' => $date, 'time' => $time,
        'interest' => in_array(input('interest'), INTERESTS, true) ? input('interest') : INTERESTS[0],
        'consultant' => in_array(input('consultant'), array_column(consultants(), 'name'), true) ? input('consultant') : '',
        'status' => $status, 'notes' => text_in('notes', 2000)];
    $was = $id ? (string) val('SELECT status FROM appointments WHERE id = ?', [$id]) : '';
    if ($id) update('appointments', $data, ['id' => $id]);
    else insert('appointments', $data + ['source' => 'admin']);
    // Confirming a visit emails the customer (Email templates › Appointment confirmed)
    $emailed = $status === 'confirmed' && $was !== 'confirmed' && send_appointment_email('appointment_confirmed', $data);
    return ['message' => $status === 'cancelled' ? 'Appointment cancelled' : 'Appointment saved', 'redirect' => 'appointments.html?month=' . substr($date, 0, 7),
        'text' => (new DateTimeImmutable($date))->format('l j F') . ($time ? ', ' . $time : '') . ($emailed ? ' — the confirmation was emailed to ' . $email . '.' : '')];
}

function act_appointment_delete(array $me): array
{
    q('DELETE FROM appointments WHERE id = ?', [input_int('id')]);
    return ['message' => 'Appointment deleted', 'reload' => true];
}

/* ---------- Subscribers ---------- */

function act_subscriber_add(array $me): array
{
    $email = mb_strtolower(text_in('email', 190, true, 'the email address'));
    if (!valid_email($email)) fail('Please enter a valid email address.', ['email']);
    if (!input_bool('consent')) fail('Please confirm they asked to receive the newsletter.', ['consent']);
    $source = in_array(input('source'), SUBSCRIBER_SOURCES, true) ? input('source') : 'In store';
    $existing = row('SELECT * FROM subscribers WHERE email = ?', [$email]);
    if ($existing && $existing['status'] === 'subscribed') fail($email . ' is already on the list.', ['email']);
    if ($existing) update('subscribers', ['status' => 'subscribed', 'unsubscribed_at' => null, 'source' => $source], ['id' => $existing['id']]);
    else insert('subscribers', ['email' => $email, 'source' => $source]);
    return ['message' => 'Subscriber added', 'text' => $email, 'reload' => true];
}

function act_subscriber_status(array $me): array
{
    $ids = ids_in();
    $on = input('status') === 'subscribed';
    q('UPDATE subscribers SET status = ?, unsubscribed_at = ' . ($on ? 'NULL' : 'NOW()') . ' WHERE id IN (' . placeholders($ids) . ')', array_merge([$on ? 'subscribed' : 'unsubscribed'], $ids));
    return ['message' => $on ? 'Subscribed again' : (count($ids) === 1 ? 'Unsubscribed' : count($ids) . ' unsubscribed'), 'reload' => true];
}

function act_subscriber_delete(array $me): array
{
    $ids = ids_in();
    q('DELETE FROM subscribers WHERE id IN (' . placeholders($ids) . ')', $ids);
    return ['message' => count($ids) === 1 ? 'Subscriber removed' : count($ids) . ' subscribers removed', 'reload' => true];
}

/* ---------- Category offers (the Offers page) ---------- */

function act_catoffer_save(array $me): array
{
    $id = input_int('id');
    if ($id && !row('SELECT id FROM offers WHERE id = ?', [$id])) fail('That offer no longer exists.');
    $slug = (string) input('category');
    $cat = $slug !== '' ? category_by_slug($slug) : null;
    if ($slug !== '' && !$cat) fail('Choose one of the categories, or All jewellery.', ['category']);
    $percent = input_int('percent', -1);
    if ($percent < 1 || $percent > 99) fail('The discount is a number from 1 to 99.', ['percent']);
    $start = date_in('start');
    $end = date_in('end');
    if ($start && $end && $end < $start) fail('The offer ends before it starts.', ['end']);
    $images = [];
    foreach (input_array('gallery') as $path) {
        $path = clean_image_path($path);
        if ($path !== '' && is_file(ROOT_DIR . '/' . $path) && !in_array($path, $images, true)) $images[] = $path;
    }
    $data = [
        'category_id' => $cat ? (int) $cat['id'] : null, 'kicker' => text_in('kicker', 80), 'title' => text_in('name', 120, true, 'the title'),
        'deal_type' => input('dealType') === 'flat' ? 'flat' : 'upto', 'percent' => $percent, 'deal_on' => text_in('dealOn', 60),
        'text' => text_in('text', 400), 'terms' => text_in('terms', 200), 'cta' => text_in('cta', 40),
        'images' => json_encode($images, JSON_UNESCAPED_SLASHES), 'with_products' => input_bool('withProducts'),
        'starts_on' => $start, 'ends_on' => $end, 'is_live' => input_bool('isLive'),
    ];
    if ($id) update('offers', $data, ['id' => $id]);
    else { $data['sort_order'] = (int) val('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM offers'); $id = insert('offers', $data); }
    $text = match (offer_state(row('SELECT * FROM offers WHERE id = ?', [$id]))) {
        'live' => 'It is on the Offers page' . ($cat ? ' and the ' . $cat['name'] . ' page' : '') . '.',
        'scheduled' => 'Visitors see it from ' . long_date($start) . '.',
        'ended' => 'Its last day has passed, so visitors no longer see it.',
        default => 'It is switched off, so visitors do not see it.',
    };
    return ['message' => 'Offer saved', 'text' => $text, 'reload' => true];
}

function act_catoffer_delete(array $me): array
{
    q('DELETE FROM offers WHERE id = ?', [input_int('id')]);
    return ['message' => 'Offer deleted', 'text' => 'Its photographs are still in the media library.', 'reload' => true];
}

function act_catoffer_reorder(array $me): array
{
    reorder('offers');
    return ['message' => 'Offer order updated', 'text' => 'The first offer is the feature on the Offers page.'];
}

function act_catoffer_live(array $me): array
{
    $on = input_bool('value');
    q('UPDATE offers SET is_live = ? WHERE id = ?', [$on ? 1 : 0, input_int('id')]);
    return ['message' => $on ? 'Offer switched on' : 'Offer switched off', 'text' => $on ? 'Visitors see it between its dates.' : 'It is off the Offers page and its category\'s page.'];
}

/** The Offers page's own content: its heading, banner and search listing (pages row) and everything around the offers */
function act_offerspage_save(array $me): array
{
    $href = (string) input('cta_href');
    if (!preg_match('/^[a-z0-9-]+\.html(\?[\w=&%-]*)?$/i', $href)) fail('Choose where the second button goes.', ['cta_href']);
    $icons = input_array('step_icon');
    $titles = input_array('step_title');
    $texts = input_array('step_text');
    $steps = [];
    for ($i = 0; $i < 4; $i++) {
        $title = trim((string) ($titles[$i] ?? ''));
        $text = trim((string) ($texts[$i] ?? ''));
        if (mb_strlen($title) > 40 || mb_strlen($text) > 160) fail('Step ' . ($i + 1) . ' is too long (a title of 40 and a text of 160 characters at most).');
        $icon = (string) ($icons[$i] ?? '');
        $steps[] = ['icon' => isset(OFFER_STEP_ICONS[$icon]) ? $icon : 'gem', 'title' => $title, 'text' => $text];
    }
    $pieces = input_int('pieces', 4);
    $page = ['heading' => text_in('heading', 200, true, 'the heading'), 'lead' => text_in('lead', 600), 'banner' => image_in('banner'),
        'seo_title' => text_in('seo_title', 160), 'seo_desc' => text_in('seo_desc', 255)];
    $settings = [
        'stats' => input_bool('stats'), 'chips' => input_bool('chips'), 'chips_all' => text_in('chips_all', 30) ?: OFFERS_PAGE_DEFAULTS['chips_all'],
        'timer' => input_bool('timer'), 'pieces' => in_array($pieces, [0, 2, 3, 4], true) ? $pieces : 4, 'viewing' => input_bool('viewing'), 'feature' => input_bool('feature'),
        'steps_on' => input_bool('steps_on'), 'steps_eyebrow' => text_in('steps_eyebrow', 60), 'steps_title' => text_in('steps_title', 80), 'steps' => $steps,
        'cta_on' => input_bool('cta_on'), 'cta_title' => text_in('cta_title', 80), 'cta_text' => text_in('cta_text', 240),
        'cta_primary' => text_in('cta_primary', 40), 'cta_secondary' => text_in('cta_secondary', 40), 'cta_href' => $href,
        'empty_title' => text_in('empty_title', 80, true, 'the heading for when no offer is live'), 'empty_text' => text_in('empty_text', 240),
    ];
    if ($settings['steps_on'] && !array_filter($steps, fn ($s) => $s['title'] !== '')) fail('Give at least one step a title, or switch the section off.', ['step_title[]']);
    if ($settings['cta_on'] && $settings['cta_title'] === '') fail('Please give the closing band a heading, or switch it off.', ['cta_title']);
    db()->beginTransaction();
    if (row("SELECT id FROM pages WHERE page_key = 'offers'")) update('pages', $page, ['page_key' => 'offers']);
    else insert('pages', $page + ['page_key' => 'offers', 'name' => 'Offers', 'kind' => 'Page', 'sort_order' => (int) val('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM pages')]);
    save_settings(['offers_page' => $settings]);
    db()->commit();
    return ['message' => 'Offers page saved', 'text' => 'Visitors see the changes on their next visit to the page.'];
}

/* ---------- Offer, announcements and settings ---------- */

function act_offer_save(array $me): array
{
    $percent = input_int('offer_percent', -1);
    if ($percent < 1 || $percent > 99) fail('The discount is a number from 1 to 99.', ['offer_percent']);
    $href = (string) input('offer_href');
    if (!preg_match('/^[a-z0-9-]+\.html(\?[\w=&%-]*)?$/i', $href)) fail('Choose where the button goes.', ['offer_href']);
    $start = date_in('offer_start');
    $end = date_in('offer_end');
    if ($start && $end && $end < $start) fail('The offer ends before it starts.', ['offer_end']);
    $announcements = array_values(array_filter(array_map(fn ($a) => mb_substr(trim((string) $a), 0, 100), input_array('announcements')), 'strlen'));
    save_settings([
        'offer_on' => input_bool('offer_on'), 'offer_eyebrow' => text_in('offer_eyebrow', 60), 'offer_title1' => text_in('offer_title1', 40),
        'offer_title2' => text_in('offer_title2', 40), 'offer_percent' => (string) $percent, 'offer_cta' => text_in('offer_cta', 30) ?: 'Explore offer',
        'offer_text' => text_in('offer_text', 120), 'offer_href' => $href, 'offer_note' => text_in('offer_note', 60),
        'offer_start' => $start ?? '', 'offer_end' => $end ?? '',
        'offer_delay' => (string) max(0, min(60, round((float) str_replace(',', '.', (string) input('offer_delay', '1.5')), 1))),
        'offer_seconds' => (string) max(1, min(120, input_int('offer_seconds', 7))),
        'offer_once' => input_bool('offer_once'), 'offer_tab' => input_bool('offer_tab'),
        'announcements' => $announcements,
    ]);
    $live = input_bool('offer_on');
    return ['message' => $live ? 'Offer published' : 'Offer switched off', 'text' => $live ? ($start && $start > date('Y-m-d') ? 'It starts on ' . long_date($start) . '.' : 'Visitors see it on their next page.') : 'Visitors no longer see the popup or its tab.'];
}

function act_settings_save(array $me): array
{
    $email = mb_strtolower(text_in('email', 190, true, 'the email'));
    if (!valid_email($email)) fail('Please check the email address.', ['email']);
    $founded = text_in('founded', 4);
    if ($founded !== '' && (!ctype_digit($founded) || (int) $founded < 1800 || (int) $founded > (int) date('Y'))) fail('Founded is a year, like 1962.', ['founded']);
    $notify = array_filter(array_map('trim', explode(',', (string) input('notify_email'))));
    foreach ($notify as $n) if (!valid_email($n)) fail('“' . $n . '” is not an email address.', ['notify_email']);
    $hours = [];
    $old = [];
    foreach ((array) setting('hours', []) as $h) $old[$h['day']] = $h;
    foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $d) {
        $from = (string) ($_POST['hours_from'][$d] ?? ($old[$d]['from'] ?? '10:30'));
        $to = (string) ($_POST['hours_to'][$d] ?? ($old[$d]['to'] ?? '20:30'));
        $open = !empty($_POST['hours_open'][$d]);
        if (!preg_match('/^\d{2}:\d{2}$/', $from) || !preg_match('/^\d{2}:\d{2}$/', $to)) fail("Please check the opening hours for $d.");
        if ($open && $to <= $from) fail("On $d the closing time is before the opening time.");
        $hours[] = ['day' => $d, 'open' => $open, 'from' => $from, 'to' => $to];
    }
    save_settings([
        'store_name' => text_in('store_name', 80, true, 'the store name'), 'founded' => $founded ?: '1962', 'tagline' => text_in('tagline', 120),
        'currency' => 'INR', 'timezone' => 'Asia/Kolkata',
        'phone' => text_in('phone', 40, true, 'the phone number'), 'whatsapp' => text_in('whatsapp', 40), 'email' => $email,
        'address_line1' => text_in('address_line1', 120), 'address_line2' => text_in('address_line2', 120), 'maps_url' => url_in('maps_url'),
        'hours' => $hours, 'instagram' => url_in('instagram'), 'facebook' => url_in('facebook'), 'youtube' => url_in('youtube'),
        'indexable' => input_bool('indexable'), 'notify_email' => implode(', ', $notify),
        'notify_enquiry' => input_bool('notify_enquiry'), 'notify_appointment' => input_bool('notify_appointment'),
        'notify_subscriber' => input_bool('notify_subscriber'), 'notify_summary' => input_bool('notify_summary'), 'mail_enabled' => input_bool('mail_enabled'),
    ] + mail_settings_in() + mailbox_settings_in() + ['site_url' => site_url_in()] + [
        'loader' => input_bool('loader'), 'smooth_scroll' => input_bool('smooth_scroll'), 'calm_motion' => input_bool('calm_motion'),
        'maintenance' => input_bool('maintenance'), 'maintenance_message' => text_in('maintenance_message', 400),
    ]);
    update('pages', ['seo_title' => text_in('home_title', 160), 'seo_desc' => text_in('home_desc', 255)], ['page_key' => 'index']);
    return ['message' => 'Settings saved', 'text' => input_bool('maintenance') ? 'The maintenance page is on — visitors see it instead of the website.' : 'The website shows the new details straight away.'];
}

/** The mail server fields of Settings › Notifications, checked (a blank password keeps the saved one) */
function mail_settings_in(): array
{
    $host = strtolower(text_in('smtp_host', 190));
    if ($host !== '' && !preg_match('/^[a-z0-9.-]+$/', $host)) fail('The mail server is a name like smtp.gmail.com.', ['smtp_host']);
    $port = input_int('smtp_port', 0);
    if ($host !== '' && ($port < 1 || $port > 65535)) fail('The port is a number, usually 587 or 465.', ['smtp_port']);
    $from = mb_strtolower(text_in('mail_from', 190));
    if ($from !== '' && !valid_email($from)) fail('Please check the “send from” address.', ['mail_from']);
    $pass = (string) ($_POST['smtp_pass'] ?? '');
    // Google shows App Passwords as "abcd efgh ijkl mnop"; the spaces are not part of it
    if (preg_match('/^([a-z]{4} ){3}[a-z]{4}$/i', trim($pass))) $pass = str_replace(' ', '', trim($pass));
    $secure = in_array(input('smtp_secure'), ['tls', 'ssl', 'none'], true) ? input('smtp_secure') : 'tls';
    if ($port === 465) $secure = 'ssl';
    elseif ($port === 587 && $secure === 'ssl') $secure = 'tls';
    return [
        'smtp_host' => $host, 'smtp_port' => $port ?: 587, 'smtp_secure' => $secure,
        'smtp_user' => text_in('smtp_user', 190), 'smtp_pass' => $pass !== '' ? $pass : (string) setting('smtp_pass', ''), 'mail_from' => $from,
    ];
}

/** Settings › Notifications › Replies by email, checked (a blank password keeps the saved one) */
function mailbox_settings_in(): array
{
    $host = strtolower(text_in('imap_host', 190));
    if ($host !== '' && !preg_match('/^[a-z0-9.-]+$/', $host)) fail('The mailbox server is a name like imap.gmail.com.', ['imap_host']);
    $port = input_int('imap_port', 0) ?: 993;
    if ($port < 1 || $port > 65535) fail('The port is a number, usually 993.', ['imap_port']);
    $secure = in_array(input('imap_secure'), ['ssl', 'tls', 'none'], true) ? input('imap_secure') : 'ssl';
    if ($port === 993) $secure = 'ssl';
    elseif ($port === 143 && $secure === 'ssl') $secure = 'tls';
    $pass = (string) ($_POST['imap_pass'] ?? '');
    if (preg_match('/^([a-z]{4} ){3}[a-z]{4}$/i', trim($pass))) $pass = str_replace(' ', '', trim($pass));
    $values = [
        'imap_enabled' => input_bool('imap_enabled'), 'imap_new_threads' => input_bool('imap_new_threads'),
        'imap_host' => $host, 'imap_port' => $port, 'imap_secure' => $secure, 'imap_user' => text_in('imap_user', 190),
        'imap_pass' => $pass !== '' ? $pass : (string) setting('imap_pass', ''), 'imap_folder' => text_in('imap_folder', 120),
    ];
    // Another mailbox (or folder) is read from its last two days, not from where the old one had got to
    foreach (['imap_host' => $host, 'imap_user' => $values['imap_user'], 'imap_folder' => $values['imap_folder'], 'smtp_host' => strtolower(text_in('smtp_host', 190)), 'smtp_user' => text_in('smtp_user', 190)] as $k => $v) {
        if ((string) setting($k, '') !== $v) $values['imap_state'] = [];
    }
    return $values;
}

/** Settings › General › Website address (for links in emails); empty = the address the website is open at */
function site_url_in(): string
{
    $url = url_in('site_url');
    return $url === '' ? '' : rtrim($url, '/') . '/';
}

/** Signs in to the mailbox as typed (before saving) and opens the folder */
function act_settings_mailboxtest(array $me): array
{
    $override = ['host' => strtolower(text_in('imap_host', 190)), 'port' => input_int('imap_port', 0) ?: null, 'secure' => (string) input('imap_secure'),
        'user' => text_in('imap_user', 190), 'pass' => (string) ($_POST['imap_pass'] ?? '') ?: (string) setting('imap_pass', ''), 'folder' => text_in('imap_folder', 120)];
    try {
        $count = mailbox_test($override);
    } catch (Throwable $e) {
        fail($e->getMessage());
    }
    $c = mailbox_config($override);
    return ['message' => 'Connected to the mailbox', 'text' => $c['user'] . ' · ' . $c['folder'] . ' holds ' . plural($count, 'email') . '. Switch on “Read customers’ replies” and save.'];
}
function act_settings_mailtest(array $me): array
{
    $c = mail_settings_in();
    $to = trim((string) input('notify_email')) ?: $me['email'];
    $override = ['host' => $c['smtp_host'], 'port' => $c['smtp_port'], 'secure' => $c['smtp_secure'], 'user' => $c['smtp_user'], 'pass' => $c['smtp_pass'], 'from' => $c['mail_from']];
    $subject = 'Test email from ' . setting('store_name', 'Mangalam Jewellers');
    $server = mail_config($override);
    $m = [
        'subject' => $subject, 'team' => true, 'eyebrow' => 'Settings · Notifications', 'heading' => 'Your emails are *working*.',
        'body' => "This is a test from the website's Settings › Notifications.\n\nIf you can read this, enquiries, appointment requests and the morning summary will reach this inbox — in the same house style as this email.",
        'details' => ['title' => 'This test', 'rows' => [
            ['Sent to', $to], ['Sent from', $server['from']],
            ['Mail server', $server['host'] !== '' ? $server['host'] . ':' . ((int) $server['port'] ?: 587) . ' · ' . strtoupper($server['secure']) : 'PHP mail() — no server set'],
            ['Sent by', $me['name']], ['Sent on', date('l j F Y, g:i A')],
        ]],
        'button' => ['Open Settings', admin_link('settings.html')],
    ];
    $sent = send_mail($to, $subject, email_text($m), '', $override, true, ['html' => email_html($m)]);
    if (!$sent) fail(mail_error());
    return ['message' => 'Test email sent', 'text' => 'Check the inbox of ' . $to . (setting('mail_enabled', false) ? '.' : ' — then switch on “Send emails from the website” and save.')];
}

/* ---------- Email templates ---------- */

/** The template editor's fields, checked */
function template_in(?array $t): array
{
    $auto = !empty($t['auto_key']);
    $link = (string) input('button_link');
    if ($link !== '' && !array_key_exists($link, TEMPLATE_LINKS)) $link = '';
    $button = $link === '' ? '' : text_in('button', 40);
    if ($link !== '' && $button === '') fail('Write the button’s words, or choose No button.', ['button']);
    return [
        'name' => $auto ? $t['name'] : text_in('name', 80, true, 'the template’s name'),
        'subject' => text_in('subject', 200, str_starts_with((string) ($t['auto_key'] ?? ''), 'appointment'), 'the subject'), 'heading' => text_in('heading', 160), 'body' => text_in('body', 5000, true, 'the text'),
        'button' => $button, 'button_link' => $link ?: 'conversation',
    ];
}

function act_template_save(array $me): array
{
    $id = input_int('id');
    $t = $id ? row('SELECT * FROM message_templates WHERE id = ?', [$id]) : null;
    if ($id && !$t) fail('That template no longer exists.');
    $data = template_in($t);
    if ($t) update('message_templates', $data, ['id' => $id]);
    else $id = insert('message_templates', $data + ['sort_order' => (int) val('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM message_templates')]);
    return ['message' => 'Template saved', 'text' => $t && $t['auto_key'] ? 'The next email goes out with these words.' : 'It is in the Templates menu in Enquiries.', 'redirect' => 'templates.html?id=' . $id];
}

function act_template_delete(array $me): array
{
    q('DELETE FROM message_templates WHERE id = ? AND auto_key IS NULL', [input_int('id')]);
    return ['message' => 'Template deleted', 'redirect' => 'templates.html'];
}

function act_template_reorder(array $me): array
{
    return reorder('message_templates');
}

function act_template_toggle(array $me): array
{
    $on = input_bool('value');
    update('message_templates', ['is_on' => $on ? 1 : 0], ['id' => input_int('id')]);
    return ['message' => $on ? 'Sent automatically again' : 'Switched off', 'text' => $on ? (setting('mail_enabled', false) ? '' : 'Emails are off in Settings › Notifications, so nothing is sent yet.') : 'Customers will not receive this email.'];
}

/** The editor's words in the email, for the invented customer (as they are typed, before saving) */
function act_template_preview(array $me): array
{
    $t = input_int('id') ? row('SELECT * FROM message_templates WHERE id = ?', [input_int('id')]) : null;
    $link = (string) input('button_link');
    $draft = ['id' => (int) ($t['id'] ?? 0), 'auto_key' => $t['auto_key'] ?? null, 'name' => (string) input('name'), 'subject' => (string) input('subject'), 'heading' => (string) input('heading'),
        'body' => (string) input('body') ?: '…', 'button' => array_key_exists($link, TEMPLATE_LINKS) ? (string) input('button') : '', 'button_link' => $link ?: 'conversation'];
    [$subject, $m] = template_sample($draft, $me);
    return ['message' => 'Preview', 'html' => email_html($m), 'subject' => $subject];
}

/** The template, filled in for the invented customer, to the signed-in person's own inbox */
function act_template_test(array $me): array
{
    $t = row('SELECT * FROM message_templates WHERE id = ?', [input_int('id')]);
    if (!$t) fail('Save the template first.');
    [$subject, $m] = template_sample($t, $me);
    $m['footnote'] = 'A test of the “' . $t['name'] . '” template, sent from Email templates to ' . $me['email'] . '. Customers see their own details.';
    if (!send_mail($me['email'], '[Test] ' . $subject, email_text($m), '', [], true, ['html' => email_html($m)])) fail(mail_error());
    return ['message' => 'Test email sent', 'text' => 'Check the inbox of ' . $me['email'] . '.'];
}
/* ---------- Team ---------- */

function act_user_invite(array $me): array
{
    $name = text_in('name', 120, true, 'their name');
    $email = mb_strtolower(text_in('email', 190, true, 'their email'));
    if (!valid_email($email)) fail('Please enter a valid email address.', ['email']);
    if (row('SELECT id FROM users WHERE email = ?', [$email])) fail('Someone on the team already uses that email.', ['email']);
    $role = in_array(input('role'), ['Manager', 'Editor', 'Sales'], true) ? input('role') : 'Editor';
    $id = insert('users', ['name' => $name, 'email' => $email, 'role' => $role, 'status' => 'invited']);
    $link = admin_url('welcome.html?token=' . issue_password_token($id));
    $note = text_in('note', 1000);
    $store = (string) setting('store_name', 'Mangalam Jewellers');
    $subject = $me['name'] . ' invited you to the ' . setting('store_name', 'Mangalam') . ' admin';
    $m = [
        'subject' => $subject, 'team' => true, 'eyebrow' => 'Invitation · ' . $store . ' admin', 'heading' => 'Welcome to the team, *' . explode(' ', $name)[0] . '*.',
        'body' => ($note ? $note . "\n\n" : '') . $me['name'] . ' has invited you to the ' . $store . " admin, where the team looks after the website, the enquiries and the appointments.\n\nChoose your password to sign in. The link works for 7 days.",
        'details' => ['title' => 'Your invitation', 'rows' => [
            ['Your sign-in', $email], ['Your role', $role], ['Invited by', $me['name']], ['Link valid until', date('l j F Y', strtotime('+7 days'))],
        ]],
        'button' => ['Choose your password', $link],
        'footnote' => 'You are receiving this because ' . $me['name'] . ' invited you to the ' . $store . ' admin. If you were not expecting it, you can ignore this email.',
    ];
    $sent = send_mail($email, $subject, email_text($m), '', [], false, ['html' => email_html($m)]);
    return ['message' => 'Invitation ready', 'link' => $link, 'linkTitle' => 'Send this link to ' . explode(' ', $name)[0],
        'linkText' => ($sent ? 'It was emailed to ' . $email . '. ' : '') . 'They open it to choose a password and sign in as ' . $role . '. It works for 7 days.', 'reload' => true];
}

function act_user_link(array $me): array
{
    $u = row('SELECT * FROM users WHERE id = ?', [input_int('id')]);
    if (!$u) fail('That person is no longer on the team.');
    if ($u['role'] === 'Owner' && (int) $u['id'] !== (int) $me['id']) fail('The owner changes their own password from Your profile.');
    $link = admin_url('welcome.html?token=' . issue_password_token((int) $u['id']));
    $first = explode(' ', $u['name'])[0];
    return ['message' => 'Link ready', 'link' => $link, 'linkTitle' => 'Send this link to ' . $first,
        'linkText' => $u['status'] === 'invited' ? 'A new invitation link; the old one no longer works. It works for 7 days.' : 'It lets ' . $first . ' choose a new password. Their current password keeps working until then. It works for 7 days.', 'reload' => true];
}

function act_user_role(array $me): array
{
    $u = row('SELECT * FROM users WHERE id = ?', [input_int('id')]);
    if (!$u || $u['role'] === 'Owner' || (int) $u['id'] === (int) $me['id']) fail('That role cannot be changed here.');
    $role = (string) input('value');
    if (!in_array($role, ['Manager', 'Editor', 'Sales'], true)) fail('Unknown role.');
    update('users', ['role' => $role], ['id' => $u['id']]);
    return ['message' => 'Role updated', 'text' => $u['name'] . ' is now ' . ($role === 'Editor' ? 'an ' : 'a ') . $role . '.'];
}

function act_user_delete(array $me): array
{
    $u = row('SELECT * FROM users WHERE id = ?', [input_int('id')]);
    if (!$u) fail('That person is no longer on the team.');
    if ($u['role'] === 'Owner' || (int) $u['id'] === (int) $me['id']) fail('The owner cannot be removed.');
    q('DELETE FROM users WHERE id = ?', [$u['id']]);
    return ['message' => $u['status'] === 'invited' ? 'Invitation cancelled' : 'Team member removed', 'text' => $u['email'], 'reload' => true];
}

function act_user_permissions(array $me): array
{
    $given = $_POST['perm'] ?? [];
    $stmt = db()->prepare('INSERT INTO role_permissions (role, permission, allowed) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE allowed = VALUES(allowed)');
    foreach (['Manager', 'Editor', 'Sales'] as $role) {
        foreach (array_keys(PERMISSIONS) as $key) $stmt->execute([$role, $key, !empty($given[$role][$key]) ? 1 : 0]);
    }
    return ['message' => 'Permissions saved', 'text' => 'They apply the next time each person opens a page.'];
}

/* ---------- Signing in ---------- */

function act_auth_login(): array
{
    $email = (string) input('email');
    $password = (string) ($_POST['password'] ?? '');
    if ($email === '' || $password === '') fail('Please enter your email and password.');
    if (!attempt_login($email, $password, input_bool('remember'))) fail('That email and password don\'t match. Please try again.');
    $next = (string) input('next');
    return ['redirect' => preg_match('#^[a-z0-9-]+\.html(\?[^\s<>"]*)?$#i', $next) ? $next : home_screen(current_user_fresh())];
}

function act_auth_welcome(): array
{
    $u = user_for_token((string) input('token'));
    if (!$u) fail('This link has expired. Ask the owner for a new one.');
    $password = (string) ($_POST['password'] ?? '');
    if ($p = password_problem($password)) fail($p);
    if ($password !== (string) ($_POST['confirm'] ?? '')) fail('The two passwords are different. Please type them again.');
    update('users', ['password_hash' => password_hash($password, PASSWORD_DEFAULT), 'status' => 'active', 'token_hash' => null, 'token_expires' => null], ['id' => $u['id']]);
    attempt_login($u['email'], $password, false);
    return ['redirect' => home_screen(current_user_fresh())];
}

function current_user_fresh(): array
{
    return row('SELECT * FROM users WHERE id = ?', [(int) $_SESSION['uid']]);
}

/* ---------- Exports (CSV, opened by Excel) ---------- */

function export_csv(string $what, array $me): void
{
    $ids = int_ids(explode(',', (string) ($_GET['ids'] ?? '')));
    $in = $ids ? ' WHERE %s.id IN (' . implode(',', $ids) . ')' : '';
    switch ($what) {
        case 'products':
            if (!can('products.edit', $me)) fail('Your role cannot export products.');
            $head = ['Name', 'Web address', 'Category', 'Price', 'Offer price', 'Metal', 'Purity', 'Stone', 'Style', 'Collection line', 'Status', 'Featured', 'New', 'Photos', 'Gross weight (g)', 'Gold weight (g)', 'HUID', 'Last edited'];
            $list = products(false);
            if ($ids) $list = array_filter($list, fn ($p) => in_array((int) $p['id'], $ids, true));
            $data = array_map(fn ($p) => [$p['name'], $p['slug'], category_name($p['category']), $p['price'], $p['offer_price'], $p['metal'], $p['purity'], $p['stone'], $p['style'], $p['line'],
                product_status_key($p), (int) $p['featured_order'] > 0 ? 'Yes' : '', $p['is_new'] ? 'Yes' : '', count($p['gallery']), $p['gross_weight'], $p['gold_weight'], $p['huid'], $p['updated_at']], $list);
            break;
        case 'enquiries':
            if (!can('enquiries.reply', $me)) fail('Your role cannot export enquiries.');
            $head = ['Reference', 'Received', 'Name', 'Email', 'Phone', 'From', 'About', 'Message', 'Messages since', 'Last message', 'Status'];
            $data = array_map(fn ($e) => [enquiry_ref((int) $e['id']), $e['created_at'], $e['name'], $e['email'], $e['phone'], ENQUIRY_SOURCES[$e['source']] ?? $e['source'], enquiry_about($e), $e['message'], (int) $e['messages'], $e['last_at'] ?: $e['created_at'], $e['status']],
                rows("SELECT e.*, p.name AS product_name, (SELECT COUNT(*) FROM enquiry_replies r WHERE r.enquiry_id = e.id AND r.channel <> 'note' AND r.is_auto = 0) AS messages
                    FROM enquiries e LEFT JOIN products p ON p.id = e.product_id" . sprintf($in, 'e') . ' ORDER BY e.created_at DESC'));
            break;
        case 'subscribers':
            if (!can('subscribers.export', $me)) fail('Your role cannot export subscribers.');
            $head = ['Email', 'Signed up in', 'Date', 'Status'];
            $data = array_map(fn ($s) => [$s['email'], $s['source'], $s['created_at'], $s['status']], rows('SELECT * FROM subscribers' . sprintf($in, 'subscribers') . ' ORDER BY created_at DESC'));
            break;
        default:
            fail('Unknown export.');
    }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="mangalam-' . $what . '-' . date('Y-m-d') . '.csv"');
    header('Cache-Control: no-store');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // so Excel reads ₹ and names correctly
    fputcsv($out, $head);
    foreach ($data as $r) fputcsv($out, array_map(fn ($v) => is_string($v) && preg_match('/^[=+\-@]/', $v) ? "'" . $v : $v, $r)); // no formulas from visitors' text
    fclose($out);
    exit;
}
