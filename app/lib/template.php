<?php
/* Templates in app/views/: {{name}} inserts a variable, {{icon:name}} (or {{icon:name:class}}) an icon, and
 * {{> partial}} another template from the partials folder. Values are inserted once and never re-read,
 * so text typed in the admin can never be mistaken for template syntax. */

function view_path(string $area, string $file): string
{
    return APP_DIR . '/views/' . $area . '/' . $file;
}

function read_view(string $area, string $file): string
{
    static $cache = [];
    $path = view_path($area, $file);
    if (!isset($cache[$path])) {
        if (!is_file($path)) throw new RuntimeException("Template not found: $area/$file");
        $cache[$path] = file_get_contents($path);
    }
    return $cache[$path];
}

/**
 * @param string   $area  'site' or 'admin' (whose partials folder {{> name}} reads)
 * @param callable $icon  fn(string $name, ?string $class): string
 */
function render_template(string $tpl, array $vars, string $area, callable $icon): string
{
    return preg_replace_callback(
        '/\{\{(?:>\s*([\w-]+)\s*|icon:([a-z0-9-]+)(?::([^}]+))?|(\w+))\}\}/',
        function ($m) use ($vars, $area, $icon) {
            if ($m[1] !== '') return render_template(read_view($area, 'partials/' . $m[1] . '.html'), $vars, $area, $icon);
            if ($m[2] !== '') return $icon($m[2], ($m[3] ?? '') !== '' ? $m[3] : null);
            $key = $m[4];
            if (!array_key_exists($key, $vars)) throw new RuntimeException("Template variable \"$key\" is not defined");
            return (string) $vars[$key];
        },
        $tpl
    );
}

/** Changes whenever the file changes, so browsers load the new version (assets/…?v=…) */
function asset_version(string $path): string
{
    return (string) @filemtime(ROOT_DIR . '/' . $path);
}

/* ---------- Icons ---------- */

/** The website's icon set, read from assets/js/ui.js so the browser and the server share one list */
function site_icons(): array
{
    static $icons = null;
    if ($icons === null) {
        $icons = [];
        $js = file_get_contents(ROOT_DIR . '/assets/js/ui.js');
        preg_match_all('/^\s*"?([a-z0-9-]+)"?:\s*\'(.*)\',\s*$/m', $js, $m, PREG_SET_ORDER);
        foreach ($m as $match) $icons[$match[1]] = $match[2];
    }
    return $icons;
}

/** An inline icon, exactly as MJUI.icon() draws it in the browser */
function site_icon(string $name, ?string $class = null): string
{
    $icons = site_icons();
    if (!isset($icons[$name])) throw new RuntimeException('Unknown icon: ' . $name);
    $arrow = str_starts_with($name, 'arrow-') ? ' icon--arrow' : '';
    return '<svg class="icon icon--' . $name . $arrow . ($class ? ' ' . $class : '') . '" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $icons[$name] . '</svg>';
}

/** The admin draws the website's icons plus its own */
function admin_icons(): array
{
    static $icons = null;
    if ($icons === null) $icons = site_icons() + require APP_DIR . '/views/admin/icons.php';
    return $icons;
}

/** Admin icons point into a sprite at the top of each page, which holds only the icons that page uses */
function admin_icon(string $name, ?string $class = null): string
{
    if (!isset(admin_icons()[$name])) throw new RuntimeException('Unknown icon: ' . $name);
    return '<svg class="icon' . ($class ? ' ' . $class : '') . '" viewBox="0 0 24 24" aria-hidden="true"><use href="#i-' . $name . '"/></svg>';
}

function admin_sprite(string $html, array $always = []): string
{
    preg_match_all('/href="#i-([a-z0-9-]+)"/', $html, $m);
    $names = array_unique(array_merge($m[1], $always));
    sort($names, SORT_STRING);
    $icons = admin_icons();
    $out = '';
    foreach ($names as $n) if (isset($icons[$n])) $out .= '<symbol id="i-' . $n . '" viewBox="0 0 24 24">' . $icons[$n] . '</symbol>';
    return '<svg class="icon-sprite" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">' . $out . '</svg>';
}
