<?php
/* Gives every photograph the website uses its light AVIF / WebP copies and its small copy, from the command
 * line (the same as Media library › Optimise images in the admin, without the time limit of a web request):
 *
 *   php database/optimise-images.php
 *
 * Needs PHP's GD extension (php.ini: extension=gd). Photographs that already have up-to-date copies are skipped,
 * so it is safe to run again after adding photographs by hand. */
if (PHP_SAPI !== 'cli') exit("Run this from the command line.\n");

require dirname(__DIR__) . '/app/bootstrap.php';

if (!gd_available()) {
    fwrite(STDERR, "PHP's GD extension is off. In php.ini remove the ; in front of extension=gd and run this again.\n");
    exit(1);
}
if (!can_encode('avif')) echo "This PHP can't write AVIF, so only WebP copies will be made.\n";

$before = array_sum(array_map(fn ($f) => (int) filesize(ROOT_DIR . '/' . $f[0]), optimisable_images()));
$done = 0;
$saved = 0;
do {
    $r = optimise_images(20);
    $done += $r['done'];
    $saved += $r['saved'];
    echo "  $done done, {$r['left']} to go\n";
} while ($r['left'] > 0 && $r['done'] > 0);

printf("Finished: %d photographs (%.1f MB as JPEG / PNG), %.1f MB lighter for browsers that read AVIF or WebP.\n", $done, $before / 1048576, $saved / 1048576);
