<?php
/* Uploading photographs: checks, safe file names, web sizes and the media library record.
 *
 * With PHP's GD extension (php.ini: extension=gd) large photographs are resized for the web and product
 * photos get their 600 px "-sm" copy made at that size. Without it, files are kept as uploaded and the
 * "-sm" copy is the same file. */

const MEDIA_FOLDERS = [
    // folder => [label, directory]
    'campaign' => ['Campaign', 'assets/images/campaign'],
    'products' => ['Products', 'assets/images/products'],
    'brand' => ['Brand', 'assets/images/brand'],
    'other' => ['Other', 'assets/images/uploads'],
];
const MAX_UPLOAD_BYTES = 10 * 1024 * 1024;

function gd_available(): bool
{
    return function_exists('imagecreatetruecolor') && function_exists('imagecreatefromjpeg');
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
    for ($i = 2; file_exists(ROOT_DIR . "/$dir/$name") || ($folder === 'products' && file_exists(ROOT_DIR . '/' . small_image("$dir/$name"))); $i++) {
        $name = "$base-$i.$outExt";
    }
    $rel = "$dir/$name";
    $abs = ROOT_DIR . '/' . $rel;

    $maxSide = ['products' => 1200, 'campaign' => 2400, 'brand' => 1600, 'other' => 2000][$folder];
    if ($resize) {
        resize_image($file['tmp_name'], $abs, $maxSide, $outExt);
    } elseif (!move_uploaded_file($file['tmp_name'], $abs)) {
        fail('The file could not be saved. Check that ' . $dir . ' can be written to.');
    }
    if ($folder === 'products') {
        $smallAbs = ROOT_DIR . '/' . small_image($rel);
        if ($resize) resize_image($abs, $smallAbs, 600, $outExt);
        else copy($abs, $smallAbs);
    }
    return record_media($rel, $folder);
}

/** Scales an image down so its longer side is at most $maxSide (never up) */
function resize_image(string $from, string $to, int $maxSide, string $ext): void
{
    $info = @getimagesize($from);
    if (!$info) fail('That image could not be read.');
    [$w, $h, $type] = $info;
    $src = match ($type) {
        IMAGETYPE_JPEG => imagecreatefromjpeg($from),
        IMAGETYPE_PNG => imagecreatefrompng($from),
        IMAGETYPE_WEBP => imagecreatefromwebp($from),
        default => null,
    };
    if (!$src) fail('That image could not be read.');
    if ($type === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
        // Phone photographs are often stored sideways with a note to turn them
        $orientation = (@exif_read_data($from) ?: [])['Orientation'] ?? 1;
        $angle = [3 => 180, 6 => -90, 8 => 90][$orientation] ?? 0;
        if ($angle) { $src = imagerotate($src, $angle, 0); [$w, $h] = [imagesx($src), imagesy($src)]; }
    }
    $scale = min(1, $maxSide / max($w, $h));
    $nw = max(1, (int) round($w * $scale));
    $nh = max(1, (int) round($h * $scale));
    $dst = imagecreatetruecolor($nw, $nh);
    if ($ext !== 'jpg') { imagealphablending($dst, false); imagesavealpha($dst, true); }
    else imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
    $ok = match ($ext) {
        'jpg' => imagejpeg($dst, $to, 86),
        'png' => imagepng($dst, $to, 7),
        'webp' => imagewebp($dst, $to, 86),
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
