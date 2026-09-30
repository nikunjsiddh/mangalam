<?php
/* Small helpers shared by the website and the admin. */

/** Escape text for HTML (the same characters the old build escaped: & < > ") */
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_COMPAT | ENT_SUBSTITUTE, 'UTF-8');
}

/** JSON for a data-* attribute */
function attr_json($value): string
{
    return e(json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

/** JSON written inside a <script> element */
function script_json($value): string
{
    return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP);
}

/** The web path of the website's folder, e.g. "/mangalam/" */
function base_url(): string
{
    static $base = null;
    if ($base === null) {
        $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/index.php');
        $dir = rtrim(dirname($script), '/');
        if (preg_match('#/admin$#', $dir)) $dir = substr($dir, 0, -6);
        $base = $dir . '/';
    }
    return $base;
}

function slugify(string $value): string
{
    $value = mb_strtolower(trim($value));
    if (function_exists('iconv')) {
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if ($ascii !== false) $value = $ascii;
    }
    return trim(preg_replace('/[^a-z0-9]+/', '-', $value), '-');
}

/** 123456 → "₹ 1,23,456" (Indian digit grouping, as toLocaleString("en-IN") gives) */
function inr($amount): string
{
    return '₹ ' . indian_number((int) $amount);
}

function indian_number(int $n): string
{
    $s = (string) abs($n);
    if (strlen($s) > 3) {
        $last = substr($s, -3);
        $rest = substr($s, 0, -3);
        $rest = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest);
        $s = $rest . ',' . $last;
    }
    return ($n < 0 ? '-' : '') . $s;
}

/** Headings keep their styling in a small mark-up: *words* become gold italics and | starts a new line */
function star(string $text, string $emClass = ''): string
{
    $html = e($text);
    $html = preg_replace('/\*(.+?)\*/u', '<em' . ($emClass ? ' class="' . $emClass . '"' : '') . '>$1</em>', $html);
    return preg_replace('/\s*\|\s*/', ' <br>', $html);
}

/** The heading as plain words (for lists and titles) */
function star_plain(string $text): string
{
    return trim(preg_replace('/\s+/', ' ', str_replace(['*', '|'], ['', ' '], $text)));
}

function plural(int $n, string $one, ?string $many = null): string
{
    return $n . ' ' . ($n === 1 ? $one : ($many ?? $one . 's'));
}

function capitalize(string $v): string
{
    return mb_strtoupper(mb_substr($v, 0, 1)) . mb_substr($v, 1);
}

function initials(string $name): string
{
    $words = preg_split('/\s+/', trim($name)) ?: [];
    $out = '';
    foreach ($words as $w) if ($w !== '') $out .= mb_substr($w, 0, 1);
    return mb_strtoupper(mb_substr($out, 0, 2));
}

/* ---------- Dates ---------- */

function today(): DateTimeImmutable
{
    return new DateTimeImmutable('today');
}

function to_date($value): ?DateTimeImmutable
{
    if ($value instanceof DateTimeImmutable) return $value;
    if ($value === null || $value === '') return null;
    try { return new DateTimeImmutable((string) $value); } catch (Exception $e) { return null; }
}

function short_date($d): string { $d = to_date($d); return $d ? $d->format('j M') : ''; }
function long_date($d): string { $d = to_date($d); return $d ? $d->format('j M Y') : ''; }
function iso_date($d): string { $d = to_date($d); return $d ? $d->format('Y-m-d') : ''; }

/** "12 min", "3 hrs", "Yesterday", "3 days", "2 weeks", "4 Aug" */
function time_ago($when): string
{
    $d = to_date($when);
    if (!$d) return '';
    $mins = (int) floor((time() - $d->getTimestamp()) / 60);
    if ($mins < 1) return 'Just now';
    if ($mins < 60) return $mins . ' min';
    if ($mins < 1440 && $d->format('Y-m-d') === date('Y-m-d')) return plural((int) floor($mins / 60), 'hr', 'hrs');
    $days = (int) today()->diff($d->setTime(0, 0))->days;
    if ($days <= 1) return 'Yesterday';
    if ($days < 7) return $days . ' days';
    if ($days < 28) return plural((int) floor($days / 7), 'week');
    return $d->format('j M');
}

/** "Today, 10:42 AM", "Yesterday, 6:15 PM", "Wed, 11:05 AM", "19 Sep, 5:25 PM" */
function when_label($when): string
{
    $d = to_date($when);
    if (!$d) return '';
    $days = (int) today()->diff($d->setTime(0, 0))->days;
    $time = $d->format('g:i A');
    if ($days === 0) return 'Today, ' . $time;
    if ($days === 1) return 'Yesterday, ' . $time;
    if ($days < 7) return $d->format('D') . ', ' . $time;
    return $d->format('j M') . ', ' . $time;
}

/** "20:30" → "8:30 PM" */
function clock_label(string $hhmm): string
{
    $d = DateTimeImmutable::createFromFormat('H:i', $hhmm);
    return $d ? $d->format('g:i A') : $hhmm;
}

/* ---------- Requests and responses ---------- */

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function input(string $key, $default = '')
{
    $v = $_POST[$key] ?? $_GET[$key] ?? $default;
    return is_string($v) ? trim($v) : $v;
}

function input_int(string $key, int $default = 0): int
{
    $v = input($key, '');
    return is_numeric($v) ? (int) $v : $default;
}

function input_bool(string $key): bool
{
    $v = $_POST[$key] ?? null;
    return $v !== null && $v !== '' && $v !== '0' && $v !== 'false' && $v !== 'off';
}

function input_array(string $key): array
{
    $v = $_POST[$key] ?? [];
    return is_array($v) ? $v : ($v === '' ? [] : [$v]);
}

/** Whole numbers from a price-like field: "1,25,000" → 125000 */
function input_money(string $key): ?int
{
    $v = preg_replace('/[^\d.]/', '', (string) input($key, ''));
    return $v === '' ? null : (int) round((float) $v);
}

function input_decimal(string $key): ?float
{
    $v = preg_replace('/[^\d.]/', '', (string) input($key, ''));
    return $v === '' ? null : (float) $v;
}

function json_response(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** A problem the person can fix; the API turns it into a message */
class UserError extends RuntimeException
{
    public array $fields;
    public function __construct(string $message, array $fields = [])
    {
        parent::__construct($message);
        $this->fields = $fields;
    }
}

function fail(string $message, array $fields = []): void
{
    throw new UserError($message, $fields);
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function valid_email(string $email): bool
{
    return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
}

/** Only the safe parts of editor HTML: formatting, lists, headings, quotes, links and images */
function clean_html(?string $html): string
{
    $html = (string) $html;
    if (trim(strip_tags($html, '<img>')) === '') return '';
    $html = preg_replace('#<(script|style|iframe|object|embed|form|input|button|textarea|select|meta|link)\b[^>]*>.*?</\1>#is', '', $html);
    $html = strip_tags($html, '<p><br><b><strong><i><em><u><h2><h3><h4><ul><ol><li><blockquote><a><img><div><span>');
    // No event handlers, inline scripts or styles
    $html = preg_replace('/\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html);
    $html = preg_replace('/\s+style\s*=\s*("[^"]*"|\'[^\']*\')/i', '', $html);
    $html = preg_replace('/(href|src)\s*=\s*(["\'])\s*(javascript|data|vbscript):[^"\']*\2/i', '$1="#"', $html);
    // The editor wraps lines in <div>; the website expects paragraphs
    $html = preg_replace('#<div\b[^>]*>#i', '<p>', $html);
    $html = str_ireplace('</div>', '</p>', $html);
    $html = preg_replace('#<span\b[^>]*>|</span>#i', '', $html);
    return trim($html);
}

/** Paragraph text from editor HTML (for search results and summaries) */
function html_text(string $html): string
{
    $text = preg_replace('#</(p|h\d|li|blockquote)>#i', "\n", $html);
    return trim(html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
}
