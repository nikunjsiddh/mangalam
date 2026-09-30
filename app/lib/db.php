<?php
/* The database connection and a few query helpers (PDO, prepared statements everywhere). */

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
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
