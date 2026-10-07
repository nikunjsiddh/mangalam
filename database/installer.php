<?php
/* Mangalam Jewellers — installation: creates the database and its tables, imports the website's existing
 * content from database/seed.json (products and their photographs, categories, collections, journal,
 * testimonials, pages, homepage, offer and settings), fills the media library from assets/images/, and
 * creates the owner's account. Used by install.php (in the browser) and database/install-cli.php. */

/**
 * @param array $opts db (host, port, name, user, pass), owner (name, email, password), samples (bool), debug (bool)
 * @return array a summary of what was imported
 */
function mj_install(array $opts): array
{
    $c = $opts['db'];
    if (!preg_match('/^[A-Za-z0-9_]+$/', $c['name'])) throw new UserError('The database name may use only letters, numbers and underscores.');

    // 1. The database itself
    $server = db_connect($c, false);
    $server->exec('CREATE DATABASE IF NOT EXISTS `' . $c['name'] . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

    // 2. app/config.php, so db() connects to it from here on
    $config = ['db' => ['host' => $c['host'], 'port' => (int) $c['port'], 'name' => $c['name'], 'user' => $c['user'], 'pass' => $c['pass']], 'debug' => (bool) ($opts['debug'] ?? true)];
    $php = "<?php\n/* Written by the installer. See app/config.sample.php. Not committed to git. */\nreturn " . var_export($config, true) . ";\n";
    if (file_put_contents(APP_DIR . '/config.php', $php) === false) throw new UserError('app/config.php could not be written. Check that the app folder can be written to.');
    if (function_exists('opcache_invalidate')) opcache_invalidate(APP_DIR . '/config.php', true);
    config(true);

    // A fresh connection: checking for an earlier installation may already have connected to the old database
    $pdo = db(true);
    // 3. The tables
    $schema = file_get_contents(__DIR__ . '/schema.sql');
    foreach (array_filter(array_map('trim', preg_split('/;\s*$/m', preg_replace('/--\s.*$/m', '', $schema)))) as $statement) {
        $pdo->exec($statement);
    }

    $seed = json_decode(file_get_contents(__DIR__ . '/seed.json'), true, 512, JSON_THROW_ON_ERROR);
    $ins = function (string $table, array $data) use ($pdo): int {
        $cols = array_keys($data);
        $stmt = $pdo->prepare('INSERT INTO `' . $table . '` (`' . implode('`,`', $cols) . '`) VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')');
        $stmt->execute(array_map(fn ($v) => is_bool($v) ? (int) $v : $v, array_values($data)));
        return (int) $pdo->lastInsertId();
    };
    $pdo->beginTransaction();
    try {
        // 4. Catalogue
        $catIds = [];
        foreach ($seed['categories'] as $cat) {
            $catIds[$cat['slug']] = $ins('categories', [
                'slug' => $cat['slug'], 'name' => $cat['name'], 'heading' => $cat['heading'], 'intro' => $cat['intro'],
                'image' => $cat['image'], 'banner' => $cat['banner'], 'seo_title' => $cat['seoTitle'], 'seo_desc' => $cat['seoDesc'],
                'in_menu' => $cat['inMenu'], 'sort_order' => $cat['sort'],
            ]);
        }
        $productIds = [];
        $photoCount = 0;
        foreach ($seed['products'] as $p) {
            $id = $ins('products', [
                'slug' => $p['slug'], 'name' => $p['name'], 'category_id' => $catIds[$p['category']] ?? null, 'price' => $p['price'],
                'metal' => $p['metal'], 'purity' => $p['purity'], 'stone' => $p['stone'], 'style' => $p['style'], 'line' => $p['line'],
                'summary' => $p['summary'], 'details' => $p['details'], 'image_position' => $p['imagePosition'], 'is_new' => $p['isNew'],
                'featured_order' => $p['featuredOrder'], 'status' => $p['status'] ?? 'published', 'sort_order' => $p['sort'],
                'show_price' => $p['showPrice'] ?? true, 'seo_desc' => $p['seoDesc'] ?? '',
            ]);
            $productIds[$p['slug']] = $id;
            foreach ($p['gallery'] as $i => $path) {
                $ins('product_images', ['product_id' => $id, 'path' => $path, 'sort_order' => $i + 1]);
                $photoCount++;
            }
        }
        foreach ($seed['hotspots'] as $i => $h) {
            if (isset($productIds[$h['slug']])) $ins('hotspots', ['product_id' => $productIds[$h['slug']], 'x' => $h['x'], 'y' => $h['y'], 'flip' => !empty($h['flip']), 'sort_order' => $i + 1]);
        }
        foreach ($seed['collections'] as $col) {
            $ins('collections', ['title' => $col['title'], 'kicker' => $col['kicker'], 'image' => $col['image'], 'image_pos' => $col['pos'],
                'rule_field' => $col['field'], 'rule_value' => $col['value'], 'on_home' => $col['onHome'], 'sort_order' => $col['sort']]);
        }

        // 5. Website content
        foreach ($seed['articles'] as $a) {
            $ins('articles', ['slug' => $a['slug'], 'title' => $a['title'], 'category' => $a['category'], 'excerpt' => $a['excerpt'], 'body' => $a['body'],
                'image' => $a['image'], 'image_position' => $a['imagePosition'], 'read_time' => $a['readTime'], 'author' => $a['author'],
                'status' => 'published', 'published_on' => $a['date']]);
        }
        foreach ($seed['testimonials'] as $t) {
            $ins('testimonials', ['quote' => $t['quote'], 'who' => $t['who'], 'occasion' => $t['occasion'], 'sort_order' => $t['sort']]);
        }
        foreach ($seed['pages'] as $pg) {
            $ins('pages', ['page_key' => $pg['key'], 'name' => $pg['name'], 'kind' => $pg['kind'], 'heading' => $pg['heading'], 'lead' => $pg['lead'],
                'banner' => $pg['banner'], 'seo_title' => $pg['seoTitle'], 'seo_desc' => $pg['seoDesc'], 'sort_order' => $pg['sort']]);
        }
        foreach ($seed['settings'] as $name => $value) {
            $ins('settings', ['name' => $name, 'value' => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
        }

        // 6. Who may do what (the Owner can always do everything)
        $roles = ['Manager', 'Editor', 'Sales'];
        $keys = array_keys(PERMISSIONS);
        foreach ($seed['samples']['permissions'] as $i => $perm) {
            foreach ($roles as $j => $role) $ins('role_permissions', ['role' => $role, 'permission' => $keys[$i], 'allowed' => (bool) $perm[$j + 2]]);
        }

        // 7. The owner's account
        $owner = $opts['owner'];
        $ins('users', ['name' => $owner['name'], 'email' => mb_strtolower($owner['email']), 'password_hash' => password_hash($owner['password'], PASSWORD_DEFAULT),
            'role' => 'Owner', 'status' => 'active']);

        // 8. The sample customers, bookings, subscribers and colleagues from the admin design
        $samples = ['enquiries' => 0, 'appointments' => 0, 'subscribers' => 0, 'team' => 0];
        if (!empty($opts['samples'])) {
            $s = $seed['samples'];
            foreach ($s['team'] as $t) {
                if (strcasecmp($t['email'], $owner['email']) === 0) continue;
                $ins('users', ['name' => $t['name'], 'email' => $t['email'], 'role' => $t['role'], 'status' => $t['status'],
                    'last_login_at' => $t['status'] === 'active' ? date('Y-m-d H:i:s', strtotime('-' . (1 + $samples['team'] * 2) . ' days')) : null]);
                $samples['team']++;
            }
            foreach ($s['enquiries'] as $en) {
                $at = time() - $en['minutesAgo'] * 60;
                $id = $ins('enquiries', ['name' => $en['name'], 'email' => $en['email'], 'phone' => $en['phone'], 'source' => $en['source'],
                    'product_id' => $en['product'] ? ($productIds[$en['product']] ?? null) : null, 'topic' => $en['topic'] ?? '',
                    'message' => $en['message'], 'status' => $en['status'], 'created_at' => date('Y-m-d H:i:s', $at), 'updated_at' => date('Y-m-d H:i:s', $at)]);
                if ($en['reply']) {
                    $ins('enquiry_replies', ['enquiry_id' => $id, 'author' => $en['reply']['by'], 'message' => $en['reply']['text'],
                        'created_at' => date('Y-m-d H:i:s', $at + $en['reply']['minutesAfter'] * 60)]);
                }
                $samples['enquiries']++;
            }
            foreach ($s['appointments'] as $ap) {
                $ins('appointments', ['name' => $ap['name'], 'phone' => $ap['phone'], 'date' => date('Y-m-d', strtotime(($ap['day'] >= 0 ? '+' : '') . $ap['day'] . ' days')),
                    'time' => $ap['time'], 'interest' => $ap['interest'], 'consultant' => $ap['consultant'], 'status' => $ap['status'], 'notes' => $ap['notes']]);
                $samples['appointments']++;
            }
            foreach ($s['subscribers'] as $sub) {
                $at = date('Y-m-d H:i:s', strtotime('-' . $sub['daysAgo'] . ' days 09:00'));
                $ins('subscribers', ['email' => $sub['email'], 'source' => $sub['source'], 'status' => $sub['status'], 'created_at' => $at,
                    'unsubscribed_at' => $sub['status'] === 'unsubscribed' ? $at : null]);
                $samples['subscribers']++;
            }
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    // 9. The media library: every photograph and logo the website uses
    $alts = [];
    foreach ($seed['products'] as $p) foreach ($p['gallery'] as $path) $alts[$path] ??= $p['name'];
    $media = 0;
    $scan = [['assets/images/campaign', 'campaign', null], ['assets/images/products', 'products', fn ($n) => !preg_match('/-sm\.\w+$/', $n)],
        ['assets/images/brand', 'brand', null], ['assets/images', 'other', fn ($n) => (bool) preg_match('/\.jpe?g$/i', $n)], ['assets/images/uploads', 'other', null]];
    foreach ($scan as [$dir, $folder, $filter]) {
        if (!is_dir(ROOT_DIR . '/' . $dir)) continue;
        $files = scandir(ROOT_DIR . '/' . $dir);
        sort($files, SORT_STRING);
        foreach ($files as $name) {
            if (!is_file(ROOT_DIR . "/$dir/$name") || !preg_match('/\.(jpe?g|png|svg|webp|gif)$/i', $name) || ($filter && !$filter($name))) continue;
            record_media("$dir/$name", $folder, $alts["$dir/$name"] ?? '');
            $media++;
        }
    }

    return [
        'categories' => count($seed['categories']), 'products' => count($seed['products']), 'photos' => $photoCount,
        'collections' => count($seed['collections']), 'articles' => count($seed['articles']), 'testimonials' => count($seed['testimonials']),
        'pages' => count($seed['pages']), 'media' => $media, 'samples' => $samples,
    ];
}

/** Is there already a working installation? */
function mj_installed(): bool
{
    if (config() === null) return false;
    try {
        return (int) val('SELECT COUNT(*) FROM users') > 0;
    } catch (Throwable $e) {
        return false;
    }
}
