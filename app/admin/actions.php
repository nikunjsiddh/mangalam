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
    'media.upload' => 'media.edit', 'media.save' => 'media.edit', 'media.delete' => 'media.edit',
    'enquiry.status' => 'enquiries.reply', 'enquiry.reply' => 'enquiries.reply', 'enquiry.delete' => 'enquiries.reply',
    'appointment.save' => 'appointments.edit', 'appointment.delete' => 'appointments.edit',
    'subscriber.add' => 'subscribers.export', 'subscriber.status' => 'subscribers.export', 'subscriber.delete' => 'subscribers.export',
    'offer.save' => 'offers.edit', 'settings.save' => 'settings.edit', 'settings.mailtest' => 'settings.edit',
    'user.invite' => 'users.manage', 'user.link' => 'users.manage', 'user.role' => 'users.manage', 'user.delete' => 'users.manage', 'user.permissions' => 'users.manage',
];

/** Page addresses that can't be used for a category (they are the website's own pages) */
const RESERVED_SLUGS = ['index', 'collections', 'jewellery', 'product', 'about', 'craftsmanship', 'journal', 'article', 'contact', '404', 'api', 'admin', 'install', 'data', 'maintenance', 'assets', 'app', 'database'];

function run_action(string $action, array $me): array
{
    if (!array_key_exists($action, ACTION_PERMISSIONS)) fail('The admin does not know how to do that.');
    $perm = ACTION_PERMISSIONS[$action];
    if ($action === 'upload.image') {
        if (!can('products.edit', $me) && !can('content.edit', $me) && !can('media.edit', $me)) fail('Your role cannot upload photographs.');
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
    $m = store_upload($files[0], $folder);
    $thumb = $m['folder'] === 'products' ? small_image($m['path']) : $m['path'];
    return ['message' => 'Photo uploaded', 'text' => $m['name'] . ' is in the media library.', 'path' => $m['path'], 'url' => asset($m['path']), 'thumb' => asset($thumb)];
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
    foreach ($files as $f) $names[] = store_upload($f, $folder)['name'];
    return ['message' => count($names) === 1 ? 'Image uploaded' : count($names) . ' images uploaded', 'text' => implode(', ', array_slice($names, 0, 3)) . (count($names) > 3 ? '…' : ''), 'redirect' => 'media.html?folder=' . rawurlencode($folder)];
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
    foreach (array_unique([$m['path'], small_image($m['path'])]) as $p) {
        if (is_file(ROOT_DIR . '/' . $p)) @unlink(ROOT_DIR . '/' . $p);
    }
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
    q('DELETE FROM products WHERE id IN (' . placeholders($ids) . ')', $ids);
    $then = (string) input('then');
    return ['message' => count($ids) === 1 ? 'Product deleted' : count($ids) . ' products deleted'] + ($then === 'products.html' ? ['redirect' => 'products.html'] : ['reload' => true]);
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

function act_enquiry_reply(array $me): array
{
    $en = row('SELECT * FROM enquiries WHERE id = ?', [input_int('id')]);
    if (!$en) fail('That enquiry no longer exists.');
    $message = text_in('message', 5000, true, 'your reply');
    $sent = send_mail($en['email'], 'Your enquiry to ' . setting('store_name', 'Mangalam Jewellers'), $message . "\n\n" . $me['name'] . "\n" . setting('store_name', 'Mangalam Jewellers') . ' · ' . setting('phone', ''), (string) setting('email', ''));
    insert('enquiry_replies', ['enquiry_id' => $en['id'], 'author' => $me['name'], 'message' => $message, 'emailed' => $sent ? 1 : 0]);
    update('enquiries', ['status' => 'replied'], ['id' => $en['id']]);
    $first = explode(' ', trim($en['name']))[0];
    return ['message' => $sent ? 'Reply sent' : 'Reply saved', 'text' => $sent ? $first . ' will receive it by email.' : rtrim(mail_error(), '.') . ' — use “Open in email” to send it to ' . $first . '.', 'redirect' => 'enquiries.html?open=' . $en['id']];
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
    if ($id) update('appointments', $data, ['id' => $id]);
    else insert('appointments', $data + ['source' => 'admin']);
    return ['message' => $status === 'cancelled' ? 'Appointment cancelled' : 'Appointment saved', 'text' => (new DateTimeImmutable($date))->format('l j F') . ($time ? ', ' . $time : ''), 'redirect' => 'appointments.html?month=' . substr($date, 0, 7)];
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
    ] + mail_settings_in() + [
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

function act_settings_mailtest(array $me): array
{
    $c = mail_settings_in();
    $to = trim((string) input('notify_email')) ?: $me['email'];
    $override = ['host' => $c['smtp_host'], 'port' => $c['smtp_port'], 'secure' => $c['smtp_secure'], 'user' => $c['smtp_user'], 'pass' => $c['smtp_pass'], 'from' => $c['mail_from']];
    $sent = send_mail($to, 'Test email from ' . setting('store_name', 'Mangalam Jewellers'),
        "This is a test from the website's Settings › Notifications.\n\nIf you can read this, enquiries, appointment requests and the morning summary will reach this inbox.\n\nSent by " . $me['name'] . ' on ' . date('j F Y, g:i A') . '.',
        '', $override, true);
    if (!$sent) fail(mail_error());
    return ['message' => 'Test email sent', 'text' => 'Check the inbox of ' . $to . (setting('mail_enabled', false) ? '.' : ' — then switch on “Send emails from the website” and save.')];
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
    $sent = send_mail($email, $me['name'] . ' invited you to the ' . setting('store_name', 'Mangalam') . ' admin', ($note ? $note . "\n\n" : '') . "Open this link to choose your password (it works for 7 days):\n$link");
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
            $head = ['Received', 'Name', 'Email', 'Phone', 'From', 'About', 'Message', 'Status'];
            $data = array_map(fn ($e) => [$e['created_at'], $e['name'], $e['email'], $e['phone'], $e['source'] === 'product' ? 'Product page' : 'Contact form', $e['product_name'] ?: $e['topic'], $e['message'], $e['status']],
                rows('SELECT e.*, p.name AS product_name FROM enquiries e LEFT JOIN products p ON p.id = e.product_id' . sprintf($in, 'e') . ' ORDER BY e.created_at DESC'));
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
