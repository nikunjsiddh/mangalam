<?php
/* Mangalam Jewellers — configuration.
 *
 * install.php writes app/config.php from this file. To set it up by hand, copy this file to
 * app/config.php and fill in the database details (XAMPP's MySQL user is "root" with no password).
 * app/config.php is not committed to git.
 */
return [
    'db' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'mangalam',
        'user' => 'root',
        'pass' => '',
    ],
    // Show PHP errors in the browser. Turn off on the live server.
    'debug' => true,
];
