<?php
/* Reads customers' email replies into Enquiries from the command line — the same as Check for replies in the
 * admin. The admin already does this every few minutes while it is open; run this from a cron job (Hostinger ›
 * Advanced › Cron Jobs, every 5 minutes) so replies arrive even when nobody has the admin open:
 *
 *   php database/fetch-mail.php
 *
 * Needs "Read customers' replies into Enquiries" on in Settings › Notifications. Nothing in the mailbox is changed. */
if (PHP_SAPI !== 'cli') exit("Run this from the command line.\n");

require dirname(__DIR__) . '/app/bootstrap.php';
require_installed();
upgrade_schema();

if (!setting('imap_enabled', false)) {
    echo "Reading replies is off — switch on \"Read customers' replies into Enquiries\" in Settings › Notifications.\n";
    exit(0);
}
try {
    $r = fetch_replies(50);
    printf("%d new email%s read: %d repl%s added to conversations, %d new conversation%s.\n",
        $r['read'], $r['read'] === 1 ? '' : 's', $r['added'], $r['added'] === 1 ? 'y' : 'ies', $r['started'], $r['started'] === 1 ? '' : 's');
} catch (Throwable $e) {
    fwrite(STDERR, 'Could not read the mailbox: ' . $e->getMessage() . "\n");
    exit(1);
}
