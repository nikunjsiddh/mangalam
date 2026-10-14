<?php
/* Uploading photographs: checks, safe file names, web sizes, light copies and the media library record.
 *
 * With PHP's GD extension (php.ini: extension=gd) large photographs are resized for the web, get a small
 * "-sm" copy for cards, menus and thumbnails (products 600 px, campaign and other photos 800 px), and get
 * AVIF and WebP copies beside them ("ring.jpg" → "ring.jpg.avif", "ring.jpg.webp"). .htaccess hands a
 * browser the lightest copy it can read in place of the JPEG or PNG, so no page has to name them.
 * Without GD, files are kept as uploaded and a product's "-sm" copy is the same file. */

const MEDIA_FOLDERS = [
    // folder => [label, directory]
    'campaign' => ['Campaign', 'assets/images/campaign'],
    'products' => ['Products', 'assets/images/products'],
    'brand' => ['Brand', 'assets/images/brand'],
    'other' => ['Other', 'assets/images/uploads'],
];
const MAX_UPLOAD_BYTES = 10 * 1024 * 1024;

/** Longest side of each folder's photographs, and of their small copies (0: none). The admin scales photos to these before uploading too. */
const WEB_SIZES = ['products' => [1200, 600], 'campaign' => [2400, 800], 'brand' => [1600, 0], 'other' => [2000, 800]];

/** The light copies made beside a JPEG or PNG, best first, with their quality (AVIF: GD's 0–100 scale, at encoder speed 8) */
const WEB_COPIES = ['avif' => 40, 'webp' => 76];

function gd_available(): bool
{
    return function_exists('imagecreatetruecolor') && function_exists('imagecreatefromjpeg');
}

/** Can this server write that format of light copy? */
function can_encode(string $format): bool
{
    return gd_available() && function_exists($format === 'avif' ? 'imageavif' : 'imagewebp');
}

/** A small copy, or an AVIF / WebP copy made beside a photograph — not a photograph of its own */
function is_derived_image(string $name): bool
{
    return (bool) preg_match('/-sm\.\w+$|\.(jpe?g|png)\.(avif|webp)$/i', $name);
}

/** The small copy of a photograph when it has one ("cat-rings.jpg" → "cat-rings-sm.jpg"), for places that show it small */
function small_or_full(string $src): string
{
    $small = small_image($src);
    return $small !== $src && is_file(ROOT_DIR . '/' . $small) ? $small : $src;
}

/**
 * Lets the browser choose between a photograph and its small copy for the room it has on screen:
 * ' srcset="a-sm.jpg 800w, a.jpg 1600w" sizes="…"', or '' when the photograph has no small copy.
 * @param string $sizes how wide the photograph shows, e.g. "(max-width: 767px) 100vw, 50vw"
 */
function srcset(string $src, string $sizes): string
{
    $small = small_or_full($src);
    if ($small === $src) return '';
    $ws = (image_dimensions(ROOT_DIR . '/' . $small) ?? [0])[0];
    $wf = (image_dimensions(ROOT_DIR . '/' . $src) ?? [0])[0];
    if (!$ws || !$wf) return '';
    return ' srcset="' . e($small) . ' ' . $ws . 'w, ' . e($src) . ' ' . $wf . 'w" sizes="' . e($sizes) . '"';
}

/** A photograph's files on disk: itself, its small copy and the light copies of both */
function image_files(string $rel): array
{
    $files = [];
    foreach (array_unique([$rel, small_image($rel)]) as $p) {
        $files[] = $p;
        foreach (array_keys(WEB_COPIES) as $fmt) $files[] = "$p.$fmt";
    }
    return array_values(array_filter($files, fn ($p) => is_file(ROOT_DIR . '/' . $p)));
}

/** Normalises $_FILES['name'] (single or multiple) into a list of files */
function uploaded_files(string $field): array
{
    $f = $_FILES[$field] ?? null;
    if (!$f) return [];
    if (!is_array($f['name'])) return [$f];
    $out = [];
    foreach ($f['name'] as $i => $name) {
        $out[] = ['name' => $name, 'type' => $f['type'][$i], 'tmp_name' => $f['tmp_name'][$i], 'error' => $f['error'][$i], 'size' => $f['size'][$i]];
    }
    return $out;
}

/**
 * Saves an uploaded image into a media folder and records it in the media library.
 * @return array the media row
 */
function store_upload(array $file, string $folder): array
{
    if (!isset(MEDIA_FOLDERS[$folder])) $folder = 'other';
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $tooBig = in_array($file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true);
        fail($tooBig ? '“' . $file['name'] . '” is larger than the server accepts.' : 'The upload of “' . $file['name'] . '” did not arrive. Please try again.');
    }
    if (!is_uploaded_file($file['tmp_name'])) fail('That upload could not be read.');
    if ($file['size'] > MAX_UPLOAD_BYTES) fail('“' . $file['name'] . '” is larger than 10 MB.');

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif', 'image/svg+xml' => 'svg'][$mime] ?? null;
    if (!$ext) fail('“' . $file['name'] . '” is not a JPEG, PNG, WebP or SVG image.');
    if ($ext === 'svg') {
        $svg = file_get_contents($file['tmp_name']);
        if (preg_match('/<script|\son[a-z]+\s*=|javascript:|<foreignObject/i', $svg)) fail('That SVG contains scripts, so it was not uploaded.');
    }

    $dir = MEDIA_FOLDERS[$folder][1];
    if (!is_dir(ROOT_DIR . '/' . $dir)) mkdir(ROOT_DIR . '/' . $dir, 0775, true);
    $resize = gd_available() && in_array($ext, ['jpg', 'png', 'webp'], true);
    // Photographs for products are stored as JPEG so they match the rest of the catalogue
    $outExt = ($resize && $folder === 'products') ? 'jpg' : $ext;
    $base = slugify(pathinfo($file['name'], PATHINFO_FILENAME)) ?: 'image';
    $name = $base . '.' . $outExt;
    for ($i = 2; file_exists(ROOT_DIR . "/$dir/$name") || file_exists(ROOT_DIR . '/' . small_image("$dir/$name")); $i++) {
        $name = "$base-$i.$outExt";
    }
    $rel = "$dir/$name";
    $abs = ROOT_DIR . '/' . $rel;

    if ($resize) {
        resize_image($file['tmp_name'], $abs, WEB_SIZES[$folder][0], $outExt);
    } elseif (!move_uploaded_file($file['tmp_name'], $abs)) {
        fail('The file could not be saved. Check that ' . $dir . ' can be written to.');
    }
    if ($folder === 'products' && !$resize) copy($abs, ROOT_DIR . '/' . small_image($rel)); // the cards ask for it
    if ($resize) optimise_image($rel, $folder);
    return record_media($rel, $folder);
}

/**
 * Gives a stored photograph its small copy (when its folder has one and the photo is larger) and the light
 * AVIF / WebP copies of both. Copies already made since the photograph last changed are kept.
 * @return int bytes saved for a browser that takes the lightest copies
 */
function optimise_image(string $rel, string $folder): int
{
    $abs = ROOT_DIR . '/' . $rel;
    if (!gd_available() || !preg_match('/\.(jpe?g|png|webp)$/i', $rel) || !is_file($abs)) return 0;
    $smallSide = WEB_SIZES[$folder][1] ?? 0;
    $small = small_image($rel);
    $smallAbs = ROOT_DIR . '/' . $small;
    if ($smallSide && !is_file($smallAbs)) {
        $dims = image_dimensions($abs);
        $ext = str_replace('jpeg', 'jpg', strtolower(pathinfo($rel, PATHINFO_EXTENSION)));
        // Products always have one (the cards ask for it); other photos only when they are larger than it
        if ($folder === 'products' || ($dims && max($dims) > $smallSide)) resize_image($abs, $smallAbs, $smallSide, $ext);
    }
    $saved = make_web_copies($rel);
    if ($smallSide && is_file($smallAbs)) $saved += make_web_copies($small);
    return $saved;
}

/**
 * Makes the AVIF and WebP copies of a JPEG or PNG beside it. A copy is kept only when it is clearly lighter
 * (grainy photographs often come out no smaller as WebP, and then the JPEG is served as before).
 * @return int bytes saved by the lightest copy
 */
function make_web_copies(string $rel): int
{
    $abs = ROOT_DIR . '/' . $rel;
    if (!gd_available() || !preg_match('/\.(jpe?g|png)$/i', $rel) || !is_file($abs)) return 0;
    $size = (int) filesize($abs);
    $best = $size;
    $im = null;
    foreach (WEB_COPIES as $fmt => $quality) {
        $copy = "$abs.$fmt";
        if (is_file($copy) && filemtime($copy) >= filemtime($abs)) { $best = min($best, (int) filesize($copy)); continue; }
        @unlink($copy);
        if (!can_encode($fmt)) continue;
        $im ??= open_image($abs);
        if (!$im) return 0;
        $ok = $fmt === 'avif' ? @imageavif($im, $copy, $quality, 8) : @imagewebp($im, $copy, $quality);
        clearstatcache(true, $copy);
        if (!$ok || !is_file($copy) || filesize($copy) > $size * 0.92) { @unlink($copy); continue; }
        $best = min($best, (int) filesize($copy));
    }
    if ($im) imagedestroy($im);
    return $size - $best;
}

/** The file a modern browser downloads for a photograph: its lightest copy (AVIF or WebP), or the photograph itself */
function light_copy(string $rel): string
{
    $best = $rel;
    $size = is_file(ROOT_DIR . '/' . $rel) ? (int) filesize(ROOT_DIR . '/' . $rel) : PHP_INT_MAX;
    foreach (array_keys(WEB_COPIES) as $fmt) {
        $copy = "$rel.$fmt";
        if (is_file(ROOT_DIR . '/' . $copy) && filesize(ROOT_DIR . '/' . $copy) < $size) [$best, $size] = [$copy, (int) filesize(ROOT_DIR . '/' . $copy)];
    }
    return $best;
}

/** How many bytes that is */
function served_bytes(string $rel): int
{
    $p = ROOT_DIR . '/' . light_copy($rel);
    return is_file($p) ? (int) filesize($p) : 0;
}

/** " (6.2 MB → 240 KB)": how much lighter uploaded photographs became for the website, or '' when they barely changed */
function compression_note(string|array $paths, int $before): string
{
    $after = array_sum(array_map('served_bytes', (array) $paths));
    return $after && $before > $after * 1.1 ? ' (' . file_size_label($before) . ' → ' . file_size_label($after) . ')' : '';
}

/** Does a photograph still need its light copies (or its small copy)? */
function needs_optimising(string $rel, string $folder): bool
{
    $abs = ROOT_DIR . '/' . $rel;
    if (!preg_match('/\.(jpe?g|png)$/i', $rel) || !is_file($abs)) return false;
    $fresh = fn ($p) => is_file(ROOT_DIR . "/$p.avif") && filemtime(ROOT_DIR . "/$p.avif") >= filemtime(ROOT_DIR . "/$p");
    if (!can_encode('avif')) $fresh = fn ($p) => true; // nothing more this server can make
    if (!$fresh($rel)) return true;
    $small = small_image($rel);
    return ($folder === 'products' && !is_file(ROOT_DIR . '/' . $small)) || (is_file(ROOT_DIR . '/' . $small) && !$fresh($small));
}

/**
 * The website's photographs that can have light copies: [path, folder], in a fixed order.
 * Logos (Brand) stay as they are; they are mostly SVG.
 */
function optimisable_images(): array
{
    $list = [];
    $dirs = [['assets/images', 'other'], [MEDIA_FOLDERS['campaign'][1], 'campaign'], [MEDIA_FOLDERS['products'][1], 'products'], [MEDIA_FOLDERS['other'][1], 'other']];
    foreach ($dirs as [$dir, $folder]) {
        foreach (glob(ROOT_DIR . "/$dir/*.{jpg,jpeg,png,JPG,JPEG,PNG}", GLOB_BRACE) ?: [] as $abs) {
            $name = basename($abs);
            if (!is_derived_image($name)) $list[] = ["$dir/$name", $folder];
        }
    }
    return $list;
}

/**
 * Works through the photographs that need light copies, for at most $seconds (so a request never times out).
 * @return array ['done' => photos finished, 'saved' => bytes saved, 'left' => photos still to do]
 */
function optimise_images(float $seconds): array
{
    @set_time_limit((int) $seconds + 60);
    $start = microtime(true);
    $done = 0;
    $saved = 0;
    $todo = array_values(array_filter(optimisable_images(), fn ($f) => needs_optimising($f[0], $f[1])));
    foreach ($todo as [$rel, $folder]) {
        if (microtime(true) - $start > $seconds) break;
        try {
            $saved += optimise_image($rel, $folder);
        } catch (UserError $e) {
            error_log("Mangalam: could not optimise $rel: " . $e->getMessage()); // an unreadable file; the rest carry on
        }
        $done++;
    }
    return ['done' => $done, 'saved' => $saved, 'left' => count($todo) - $done];
}

/** Opens a JPEG, PNG or WebP the right way up, with its transparency; null when it can't be read */
function open_image(string $path): ?GdImage
{
    $type = (@getimagesize($path) ?: [])[2] ?? null;
    $im = match ($type) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
        IMAGETYPE_PNG => @imagecreatefrompng($path),
        IMAGETYPE_WEBP => @imagecreatefromwebp($path),
        default => false,
    };
    if (!$im) return null;
    if ($type === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
        // Phone photographs are often stored sideways with a note to turn them
        $orientation = (@exif_read_data($path) ?: [])['Orientation'] ?? 1;
        $angle = [3 => 180, 6 => -90, 8 => 90][$orientation] ?? 0;
        if ($angle) $im = imagerotate($im, $angle, 0);
    }
    if (!imageistruecolor($im)) imagepalettetotruecolor($im);
    imagealphablending($im, false);
    imagesavealpha($im, true);
    return $im;
}

/** Scales an image down so its longer side is at most $maxSide (never up) */
function resize_image(string $from, string $to, int $maxSide, string $ext): void
{
    $src = open_image($from);
    if (!$src) fail('That image could not be read.');
    [$w, $h] = [imagesx($src), imagesy($src)];
    $scale = min(1, $maxSide / max($w, $h));
    $nw = max(1, (int) round($w * $scale));
    $nh = max(1, (int) round($h * $scale));
    $dst = imagecreatetruecolor($nw, $nh);
    if ($ext !== 'jpg') { imagealphablending($dst, false); imagesavealpha($dst, true); }
    else imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
    if ($ext === 'jpg') imageinterlace($dst, true); // progressive: the photo appears at once and sharpens as it loads
    $ok = match ($ext) {
        'jpg' => imagejpeg($dst, $to, 82),
        'png' => imagepng($dst, $to, 9),
        'webp' => imagewebp($dst, $to, 82),
    };
    imagedestroy($src);
    imagedestroy($dst);
    if (!$ok) fail('The resized image could not be saved.');
}

/** Width and height of a JPEG, PNG, WebP, GIF or SVG */
function image_dimensions(string $abs): ?array
{
    if (str_ends_with(strtolower($abs), '.svg')) {
        $svg = @file_get_contents($abs, false, null, 0, 4096) ?: '';
        return preg_match('/viewBox="[\d.-]+\s+[\d.-]+\s+([\d.]+)\s+([\d.]+)"/', $svg, $m) ? [(int) round((float) $m[1]), (int) round((float) $m[2])] : null;
    }
    $info = @getimagesize($abs);
    return $info ? [$info[0], $info[1]] : null;
}

/** Adds (or refreshes) a file in the media library */
function record_media(string $rel, string $folder, string $alt = ''): array
{
    $abs = ROOT_DIR . '/' . $rel;
    $dims = image_dimensions($abs);
    q('INSERT INTO media (path, folder, name, alt, width, height, bytes) VALUES (?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE folder = VALUES(folder), width = VALUES(width), height = VALUES(height), bytes = VALUES(bytes)',
        [$rel, $folder, basename($rel), $alt, $dims[0] ?? null, $dims[1] ?? null, (int) @filesize($abs)]);
    return row('SELECT * FROM media WHERE path = ?', [$rel]);
}

function file_size_label(int $bytes): string
{
    return $bytes >= 1048576 ? number_format($bytes / 1048576, 1) . ' MB' : round($bytes / 1024) . ' KB';
}

const MODEL_DIR = 'assets/models';
const MAX_MODEL_BYTES = 30 * 1024 * 1024;

/**
 * Saves an uploaded 3D model (binary glTF, .glb) for a product's 3D view.
 * @return string its address, e.g. assets/models/heritage-ring.glb
 */
function store_model(array $file): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $tooBig = in_array($file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true);
        fail($tooBig ? '“' . $file['name'] . '” is larger than the server accepts (' . ini_get('upload_max_filesize') . ').' : 'The upload of “' . $file['name'] . '” did not arrive. Please try again.');
    }
    if (!is_uploaded_file($file['tmp_name'])) fail('That upload could not be read.');
    if ($file['size'] > MAX_MODEL_BYTES) fail('“' . $file['name'] . '” is larger than 30 MB. Ask your 3D designer for a lighter export (Draco or fewer polygons).');
    // A .glb starts with "glTF" and its version (2)
    $head = (string) file_get_contents($file['tmp_name'], false, null, 0, 8);
    if (strlen($head) < 8 || substr($head, 0, 4) !== 'glTF' || unpack('V', substr($head, 4, 4))[1] !== 2) {
        fail('“' . $file['name'] . '” is not a .glb 3D model. Export the piece as binary glTF 2.0 (.glb) and upload that.');
    }
    if (!is_dir(ROOT_DIR . '/' . MODEL_DIR)) mkdir(ROOT_DIR . '/' . MODEL_DIR, 0775, true);
    $base = slugify(pathinfo($file['name'], PATHINFO_FILENAME)) ?: 'model';
    $name = "$base.glb";
    for ($i = 2; file_exists(ROOT_DIR . '/' . MODEL_DIR . "/$name"); $i++) $name = "$base-$i.glb";
    $rel = MODEL_DIR . "/$name";
    if (!move_uploaded_file($file['tmp_name'], ROOT_DIR . '/' . $rel)) fail('The model could not be saved. Check that ' . MODEL_DIR . ' can be written to.');
    return $rel;
}

const SPIN_DIR = 'assets/images/spins';
/** Longest side of a 360° frame: as sharp as the product photographs, for the zoom */
const SPIN_SIDE = 1200;

/**
 * Saves one frame of a product's 360° view. The admin takes the frames from a turntable video or a set of photos,
 * crops them square and sends them one by one; each set of frames gets a folder of its own
 * (assets/images/spins/heritage-kundan-kadas-3fa9c1/), so a new set never overwrites one a browser has kept.
 * @param string $set   the folder of the frames sent before, or '' to start a new one
 * @param string $named what the new folder is named after (the product)
 * @param int    $index the frame's place in the turn, for its file name
 * @return array ['set' => folder, 'path' => assets/images/spins/<set>/f001.jpg]
 */
function store_spin_frame(array $file, string $set, string $named, int $index): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $tooBig = in_array($file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true);
        fail($tooBig ? 'A frame is larger than the server accepts (' . ini_get('upload_max_filesize') . ').' : 'A frame did not arrive. Please try again.');
    }
    if (!is_uploaded_file($file['tmp_name'])) fail('That upload could not be read.');
    if ($file['size'] > MAX_UPLOAD_BYTES) fail('A frame is larger than 10 MB.');
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) fail('A frame is not a JPEG, PNG or WebP image.');
    if (!gd_available() && $mime !== 'image/jpeg') fail('Without PHP\'s GD extension, frames can only be JPEG images.');

    if ($set === '') {
        $base = substr(slugify($named), 0, 50) ?: 'spin';
        do { $set = rtrim($base, '-') . '-' . bin2hex(random_bytes(3)); } while (is_dir(ROOT_DIR . '/' . SPIN_DIR . "/$set"));
    } elseif (!preg_match('/^[a-z0-9][a-z0-9-]{0,80}$/', $set)) {
        fail('The frames were sent to an unknown folder. Please make them again.');
    }
    $dir = SPIN_DIR . "/$set";
    if (!is_dir(ROOT_DIR . '/' . $dir) && !mkdir(ROOT_DIR . '/' . $dir, 0775, true)) fail('The frames could not be saved. Check that ' . SPIN_DIR . ' can be written to.');
    $name = sprintf('f%03d', max(0, min(999, $index)));
    for ($i = 2; file_exists(ROOT_DIR . "/$dir/$name.jpg"); $i++) $name = sprintf('f%03d-%d', $index, $i);
    $rel = "$dir/$name.jpg";

    if (gd_available()) {
        resize_image($file['tmp_name'], ROOT_DIR . '/' . $rel, SPIN_SIDE, 'jpg');
        make_web_copies($rel);
    } elseif (!move_uploaded_file($file['tmp_name'], ROOT_DIR . '/' . $rel)) {
        fail('The frame could not be saved. Check that ' . SPIN_DIR . ' can be written to.');
    }
    return ['set' => $set, 'path' => $rel];
}

/** Every 360° frame a product uses (a duplicated product shares its frames with the original) */
function spin_frames_in_use(): array
{
    $used = [];
    foreach (rows('SELECT spin FROM products WHERE spin IS NOT NULL') as $r) {
        $s = json_decode((string) $r['spin'], true);
        foreach (is_array($s['frames'] ?? null) ? $s['frames'] : [] as $f) $used[(string) $f] = true;
    }
    return $used;
}

/**
 * Deletes 360° frames no product uses any more — the ones named, and those of sets made over a day ago and never
 * saved (the drawer closed without saving) — with their light copies, and the folders left empty.
 */
function spin_cleanup(array $paths = []): void
{
    $used = spin_frames_in_use();
    $root = ROOT_DIR . '/' . SPIN_DIR;
    foreach (glob("$root/*", GLOB_ONLYDIR) ?: [] as $abs) {
        if (filemtime($abs) < time() - 86400) foreach (glob("$abs/*.jpg") ?: [] as $f) $paths[] = SPIN_DIR . '/' . basename($abs) . '/' . basename($f);
    }
    $dirs = [];
    foreach (array_unique($paths) as $rel) {
        $rel = preg_replace('#^(\.\./)+#', '', (string) $rel);
        if (isset($used[$rel]) || !preg_match('#^' . SPIN_DIR . '/([a-z0-9][a-z0-9-]*)/[a-z0-9-]+\.jpg$#', $rel, $m)) continue;
        foreach ([$rel, "$rel.avif", "$rel.webp"] as $f) if (is_file(ROOT_DIR . "/$f")) @unlink(ROOT_DIR . "/$f");
        $dirs[$m[1]] = true;
    }
    foreach (array_keys($dirs) as $d) {
        if (is_dir("$root/$d") && !(glob("$root/$d/*") ?: [])) @rmdir("$root/$d");
    }
}

/** Only paths inside the website's image folders are accepted from forms */
function clean_image_path($path): string
{
    $path = trim((string) $path);
    $path = preg_replace('#^(\.\./)+#', '', $path);
    if ($path === '') return '';
    if (!preg_match('#^assets/images/[A-Za-z0-9_./-]+\.(jpe?g|png|webp|gif|svg)$#i', $path) || str_contains($path, '..')) {
        fail('That image address is not one of the website\'s images.');
    }
    return $path;
}
