<?php
/* The database connection and a few query helpers (PDO, prepared statements everywhere). */

/** The connection to the configured database; $reconnect drops it and connects again (after app/config.php changes) */
function db(bool $reconnect = false): PDO
{
    static $pdo = null;
    if ($pdo === null || $reconnect) {
        $c = config()['db'] ?? null;
        if (!$c) throw new RuntimeException('The database is not configured (app/config.php is missing).');
        $pdo = db_connect($c);
    }
    return $pdo;
}

function db_connect(array $c, bool $withDatabase = true): PDO
{
    $dsn = 'mysql:host=' . $c['host'] . ';port=' . (int) ($c['port'] ?? 3306) . ($withDatabase ? ';dbname=' . $c['name'] : '') . ';charset=utf8mb4';
    $pdo = new PDO($dsn, $c['user'], $c['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    // Keep the database clock on Indian time, like PHP
    $pdo->exec("SET time_zone = '+05:30'");
    return $pdo;
}

function q(string $sql, array $params = []): PDOStatement
{
    $stmt = db()->prepare($sql);
    foreach ($params as $key => $value) {
        $name = is_int($key) ? $key + 1 : ':' . ltrim($key, ':');
        $type = is_int($value) ? PDO::PARAM_INT : (is_bool($value) ? PDO::PARAM_INT : ($value === null ? PDO::PARAM_NULL : PDO::PARAM_STR));
        $stmt->bindValue($name, is_bool($value) ? (int) $value : $value, $type);
    }
    $stmt->execute();
    return $stmt;
}

function rows(string $sql, array $params = []): array
{
    return q($sql, $params)->fetchAll();
}

function row(string $sql, array $params = []): ?array
{
    $r = q($sql, $params)->fetch();
    return $r === false ? null : $r;
}

function val(string $sql, array $params = [])
{
    $v = q($sql, $params)->fetchColumn();
    return $v === false ? null : $v;
}

function insert(string $table, array $data): int
{
    $cols = array_keys($data);
    $sql = 'INSERT INTO `' . $table . '` (`' . implode('`, `', $cols) . '`) VALUES (' . implode(', ', array_map(fn ($c) => ':' . $c, $cols)) . ')';
    q($sql, $data);
    return (int) db()->lastInsertId();
}

function update(string $table, array $data, array $where): int
{
    $params = [];
    $set = [];
    foreach ($data as $col => $v) { $set[] = "`$col` = :s_$col"; $params["s_$col"] = $v; }
    $cond = [];
    foreach ($where as $col => $v) { $cond[] = "`$col` = :w_$col"; $params["w_$col"] = $v; }
    return q('UPDATE `' . $table . '` SET ' . implode(', ', $set) . ' WHERE ' . implode(' AND ', $cond), $params)->rowCount();
}

/** Brings a database installed from an older schema.sql up to date (run by the admin; each step checks first) */
function upgrade_schema(): void
{
    // Products › 3D view & customiser
    if (!val("SHOW COLUMNS FROM products LIKE 'view3d'")) q('ALTER TABLE products ADD COLUMN view3d TEXT NULL AFTER image_position');
    // Products › 360° view (October 2026)
    if (!val("SHOW COLUMNS FROM products LIKE 'spin'")) q('ALTER TABLE products ADD COLUMN spin TEXT NULL AFTER view3d');
    // Category offers and the Offers page (the table is created as database/schema.sql describes it)
    if (!val("SHOW TABLES LIKE 'offers'")) {
        $schema = preg_replace('/--\s.*$/m', '', (string) file_get_contents(ROOT_DIR . '/database/schema.sql'));
        if (preg_match('/CREATE TABLE offers \(.*?\) ENGINE=[^;]+/s', $schema, $m)) db()->exec($m[0]);
    }
    // Conversations: messages either way and on any channel, the customer's page, email templates (October 2026)
    if (!val("SHOW COLUMNS FROM enquiry_replies LIKE 'direction'")) {
        q("ALTER TABLE enquiries
            ADD COLUMN token CHAR(32) NULL UNIQUE AFTER id,
            MODIFY source ENUM('product','contact','email','admin') NOT NULL DEFAULT 'contact',
            ADD COLUMN subject VARCHAR(200) NOT NULL DEFAULT '' AFTER topic,
            ADD COLUMN message_id VARCHAR(255) NOT NULL DEFAULT '' AFTER message,
            ADD COLUMN last_at DATETIME NULL AFTER status,
            ADD KEY idx_enquiries_email (email)");
        q("ALTER TABLE enquiry_replies
            ADD COLUMN direction ENUM('out','in') NOT NULL DEFAULT 'out' AFTER enquiry_id,
            ADD COLUMN channel ENUM('email','whatsapp','website','phone','note') NOT NULL DEFAULT 'email' AFTER direction,
            ADD COLUMN is_auto TINYINT(1) NOT NULL DEFAULT 0 AFTER channel,
            ADD COLUMN subject VARCHAR(200) NOT NULL DEFAULT '' AFTER author,
            ADD COLUMN message_id VARCHAR(255) NOT NULL DEFAULT '' AFTER emailed,
            ADD COLUMN seen_at DATETIME NULL AFTER message_id,
            ADD KEY idx_replies_enquiry (enquiry_id, created_at),
            ADD KEY idx_replies_message_id (message_id(64))");
        q('UPDATE enquiries e SET last_at = GREATEST(e.created_at, COALESCE((SELECT MAX(r.created_at) FROM enquiry_replies r WHERE r.enquiry_id = e.id), e.created_at))');
    }
    if (!val("SHOW TABLES LIKE 'message_templates'")) {
        $schema = preg_replace('/--\s.*$/m', '', (string) file_get_contents(ROOT_DIR . '/database/schema.sql'));
        if (preg_match('/CREATE TABLE message_templates \(.*?\) ENGINE=[^;]+/s', $schema, $m)) db()->exec($m[0]);
        // Managers may edit the templates, as they may edit the offers
        q("INSERT IGNORE INTO role_permissions (role, permission, allowed) VALUES ('Manager', 'templates.edit', 1)");
    }
    seed_templates();
    if (!val("SELECT COUNT(*) FROM pages WHERE page_key = 'offers'")) {
        // Its heading, introduction and banner as database/seed.json gives them for a new installation
        $seed = json_decode((string) file_get_contents(ROOT_DIR . '/database/seed.json'), true);
        foreach ($seed['pages'] ?? [] as $pg) {
            if ($pg['key'] !== 'offers') continue;
            insert('pages', ['page_key' => 'offers', 'name' => $pg['name'], 'kind' => $pg['kind'], 'heading' => $pg['heading'], 'lead' => $pg['lead'],
                'banner' => $pg['banner'], 'seo_title' => $pg['seoTitle'], 'seo_desc' => $pg['seoDesc'],
                'sort_order' => (int) val('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM pages')]);
        }
    }
}

/** "?, ?, ?" for an IN (…) list */
function placeholders(array $values): string
{
    return implode(', ', array_fill(0, max(1, count($values)), '?'));
}

/** Whole-number ids from a list, without duplicates */
function int_ids(array $values): array
{
    return array_values(array_unique(array_filter(array_map('intval', $values), fn ($v) => $v > 0)));
}
