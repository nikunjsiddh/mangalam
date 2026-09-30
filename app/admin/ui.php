<?php
/* Small pieces of admin markup shared by the screens: badges, avatars, menus, switches and options.
 *
 * Buttons that change something carry data-post="<action>" and data-params='{…}'; admin.js sends them to
 * admin/api.php (after a confirmation when they also carry data-confirm). Forms carry data-save="<action>". */

const STATUS = [
    'published' => ['ok', 'Published'], 'draft' => ['neutral', 'Draft'], 'scheduled' => ['info', 'Scheduled'], 'hidden' => ['neutral', 'Hidden'],
    'new' => ['info', 'New'], 'replied' => ['ok', 'Replied'], 'closed' => ['neutral', 'Closed'], 'archived' => ['neutral', 'Archived'],
    'confirmed' => ['ok', 'Confirmed'], 'pending' => ['warn', 'Pending'], 'completed' => ['neutral', 'Completed'], 'cancelled' => ['danger', 'Cancelled'],
    'subscribed' => ['ok', 'Subscribed'], 'unsubscribed' => ['neutral', 'Unsubscribed'],
    'active' => ['ok', 'Active'], 'invited' => ['warn', 'Invited'],
];

function ai(string $name, ?string $class = null): string
{
    return admin_icon($name, $class);
}

function badge(string $key): string
{
    [$tone, $text] = STATUS[$key] ?? ['neutral', capitalize($key)];
    return '<span class="badge badge--' . $tone . '">' . $text . '</span>';
}

function asset(string $path): string
{
    return $path === '' ? '' : '../' . $path;
}

function tint(string $name): int
{
    $sum = 0;
    foreach (mb_str_split($name) as $ch) $sum += mb_ord($ch);
    return $sum % 4;
}

function avatar(string $name, string $size = ''): string
{
    return '<span class="avatar avatar--t' . tint($name) . ($size ? ' avatar--' . $size : '') . '" aria-hidden="true">' . e(initials($name)) . '</span>';
}

function params_attr(array $params): string
{
    return " data-params='" . str_replace("'", '&#39;', e(json_encode($params, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))) . "'";
}

function switch_only(string $label, bool $checked = true, string $attrs = ''): string
{
    return '<label class="switch"' . $attrs . '><input type="checkbox"' . ($checked ? ' checked' : '') . ' aria-label="' . e($label) . '"><span class="switch__track" aria-hidden="true"></span></label>';
}

/** A switch that saves itself when flipped */
function switch_post(string $label, bool $checked, string $action, array $params): string
{
    return '<label class="switch"><input type="checkbox"' . ($checked ? ' checked' : '') . ' aria-label="' . e($label) . '" data-change-post="' . $action . '"' . params_attr($params) . '><span class="switch__track" aria-hidden="true"></span></label>';
}

function more_menu(string $name, array $items): string
{
    return '<div class="dropdown"><button type="button" class="icon-btn icon-btn--sm" data-menu-trigger aria-haspopup="menu" aria-expanded="false" aria-label="More actions for ' . e($name) . '">' . ai('ellipsis') . '</button><div class="menu" data-menu role="menu" hidden>' . implode('', array_filter($items)) . '</div></div>';
}

function menu_link(string $href, string $icon, string $text): string
{
    return '<a role="menuitem" href="' . e($href) . '"' . (str_starts_with($href, '../') ? ' target="_blank" rel="noopener"' : '') . '>' . ai($icon) . $text . '</a>';
}

function menu_post(string $icon, string $text, string $action, array $params): string
{
    return '<button type="button" role="menuitem" data-post="' . $action . '"' . params_attr($params) . '>' . ai($icon) . $text . '</button>';
}

function confirm_attrs(string $question, string $detail, string $verb, string $action, array $params): string
{
    return ' data-confirm="' . e($question) . '" data-confirm-text="' . e($detail) . '" data-confirm-action="' . e($verb) . '" data-post="' . $action . '"' . params_attr($params);
}

function menu_delete(string $what, string $detail, string $action, array $params, string $verb = 'Delete'): string
{
    return '<hr><button type="button" role="menuitem" class="is-danger"' . confirm_attrs("$verb $what?", $detail, $verb, $action, $params) . '>' . ai('trash-2') . $verb . '</button>';
}

function option(string $value, string $text, bool $selected = false): string
{
    return '<option value="' . e($value) . '"' . ($selected ? ' selected' : '') . '>' . e($text) . '</option>';
}

function options(array $values, ?string $selected = null): string
{
    return implode('', array_map(fn ($v) => option($v, $v, $v === $selected), $values));
}

function radios(string $name, array $values, ?string $checked): string
{
    return implode('', array_map(fn ($v) => '<label><input type="radio" name="' . $name . '" value="' . e($v) . '"' . ($v === $checked ? ' checked' : '') . '><span>' . e($v) . '</span></label>', $values));
}

function checked(bool $on): string
{
    return $on ? ' checked' : '';
}

/** A 12-point sparkline: the trend in a quiet ink, the current value in gold */
function sparkline(array $values, int $w = 104, int $h = 34): string
{
    $values = array_values($values);
    if (count($values) < 2) return '';
    $min = min($values);
    $max = max($values);
    $pad = 4;
    $x = fn ($i) => number_format($pad + ($i * ($w - $pad * 2)) / (count($values) - 1), 1, '.', '');
    $y = fn ($v) => number_format($h - $pad - (($v - $min) / (($max - $min) ?: 1)) * ($h - $pad * 2), 1, '.', '');
    $points = implode(' ', array_map(fn ($i) => $x($i) . ',' . $y($values[$i]), array_keys($values)));
    $last = count($values) - 1;
    return '<svg class="spark" viewBox="0 0 ' . $w . ' ' . $h . '" width="' . $w . '" height="' . $h . '" aria-hidden="true"><polyline points="' . $points . '"/><circle cx="' . $x($last) . '" cy="' . $y($values[$last]) . '" r="3.5"/></svg>';
}

/** A drop-down of every piece, for pins and featured picks */
function product_options(?int $selected = null, bool $blank = false): string
{
    $out = $blank ? '<option value="">Choose a piece to add…</option>' : '';
    foreach (products(false) as $p) {
        $out .= '<option value="' . $p['id'] . '" data-thumb="' . e(asset($p['thumb'])) . '" data-meta="' . e(category_name($p['category']) . ' · ' . inr($p['price'])) . '"'
            . ((int) $p['id'] === $selected ? ' selected' : '') . '>' . e($p['name'] . ($p['is_live'] ? '' : ' (not on the website)')) . '</option>';
    }
    return $out;
}

/** An image field: preview, a Replace button that uploads straight away, and the hidden value that is saved */
function image_input(string $name, string $path, string $folder): string
{
    return '<input type="hidden" name="' . $name . '" value="' . e($path) . '" data-image-value>'
        . '<input type="file" accept="image/*" data-image-input data-upload-folder="' . $folder . '">';
}

/** The heading mark-up hint shown beside heading fields */
const STAR_HINT = 'Put *stars* around words for gold italics, and | where a new line starts.';
