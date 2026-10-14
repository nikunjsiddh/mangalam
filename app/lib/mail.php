<?php
/* Email: sending (SMTP, or PHP mail()), the branded layout every email goes out in, and reading the customers'
 * replies from the mailbox (IMAP), so they join their conversation in Enquiries.
 *
 * Emails are sent only when "Send emails from the website" is on in Settings › Notifications, and replies are read
 * only when "Read customers' replies" is on there too. Neither needs a PHP extension: both talk to the mail server
 * over a plain (encrypted) socket. */

/* ---------- Sending ---------- */

/** The mail server settings; $override (the Settings form, for a test email) wins over what is saved */
function mail_config(array $override = []): array
{
    $c = [
        'host' => trim((string) setting('smtp_host', '')), 'port' => (int) setting('smtp_port', 587),
        'secure' => (string) setting('smtp_secure', 'tls'), 'user' => trim((string) setting('smtp_user', '')),
        'pass' => (string) setting('smtp_pass', ''), 'from' => trim((string) setting('mail_from', '')),
    ];
    foreach ($override as $k => $v) if ($v !== null && $v !== '') $c[$k] = $v;
    // Gmail and most hosts only send "From" the signed-in address
    if ($c['from'] === '') $c['from'] = valid_email($c['user']) ? $c['user'] : (string) setting('email', 'care@mangalamjewellers.in');
    return $c;
}

/** Why the last email was not sent ('' when it was) */
function mail_error(?string $set = null): string
{
    static $error = '';
    if ($set !== null) $error = $set;
    return $error;
}

/** A new Message-ID, on the sending address's domain */
function new_message_id(): string
{
    $domain = explode('@', mail_config()['from'])[1] ?? 'localhost';
    return '<' . bin2hex(random_bytes(12)) . '.' . time() . '@' . preg_replace('/[^a-z0-9.-]/i', '', $domain) . '>';
}

/**
 * Sends an email as plain text and HTML. Without $options['html'], the text is set in the house layout.
 * $options: html, message_id (from new_message_id(), to remember it), in_reply_to and references (to keep a
 * conversation together in the customer's mailbox), from_name (instead of the store's name).
 */
function send_mail(string $to, string $subject, string $body, string $replyTo = '', array $override = [], bool $force = false, array $options = []): bool
{
    mail_error('');
    if (!$force && !setting('mail_enabled', false)) { mail_error('Sending emails is off in Settings › Notifications.'); return false; }
    $recipients = array_values(array_filter(array_map('trim', explode(',', $to)), 'valid_email'));
    if (!$recipients) { mail_error('There is no email address to send to.'); return false; }
    $c = mail_config($override);
    $name = (string) ($options['from_name'] ?? setting('store_name', 'Mangalam Jewellers'));
    $body = str_replace(["\r\n", "\r"], "\n", $body);
    $html = (string) ($options['html'] ?? email_html(['subject' => $subject, 'body' => $body]));
    [$contentType, $mime] = email_mime($body, $html);
    $headers = [
        'Date' => date('r'),
        'From' => mb_encode_mimeheader($name, 'UTF-8', 'Q') . ' <' . $c['from'] . '>',
        'Reply-To' => $replyTo !== '' && valid_email($replyTo) ? $replyTo : '',
        'Message-ID' => (string) ($options['message_id'] ?? '') ?: new_message_id(),
        'In-Reply-To' => (string) ($options['in_reply_to'] ?? ''),
        'References' => (string) ($options['references'] ?? ''),
        'MIME-Version' => '1.0',
        'Content-Type' => $contentType,
    ];
    try {
        if ($c['host'] !== '') {
            smtp_send($c, $recipients, mb_encode_mimeheader($subject, 'UTF-8', 'Q'), $mime, $headers);
            return true;
        }
        // No mail server set: PHP's own mail() (works on most hosting; on XAMPP it needs an SMTP server)
        $lines = [];
        foreach ($headers as $k => $v) if ($v !== '' && $k !== 'Date') $lines[] = "$k: $v";
        $sent = @mail(implode(', ', $recipients), mb_encode_mimeheader($subject, 'UTF-8', 'Q'), str_replace("\n", "\r\n", $mime), implode("\r\n", $lines));
        if (!$sent) throw new RuntimeException('The server could not send it with PHP mail(). Add your mail server (for example Gmail) under Settings › Notifications.');
        return true;
    } catch (Throwable $e) {
        mail_error($e->getMessage());
        error_log('Mangalam email to ' . implode(', ', $recipients) . ' not sent: ' . $e->getMessage());
        return false;
    }
}

/**
 * The body of an email: the plain text and the HTML, with the pictures the HTML shows from the website travelling
 * inside it (so the logo appears even while the website is on localhost).
 * @return array [its Content-Type header, the body]
 */
function email_mime(string $text, string $html): array
{
    [$html, $pictures] = email_inline_images($html);
    $boundary = '=_mj' . bin2hex(random_bytes(8));
    $related = '=_mr' . bin2hex(random_bytes(8));
    // Every part is encoded, so no line is ever too long for a mail server
    $b64 = fn (string $data) => rtrim(chunk_split(base64_encode($data), 76, "\n")) . "\n";
    $htmlPart = "Content-Type: text/html; charset=UTF-8\nContent-Transfer-Encoding: base64\n\n" . $b64($html);
    if ($pictures) {
        // multipart/related: the HTML, then each picture it shows (src="cid:…")
        $inner = "--$related\n" . $htmlPart;
        foreach ($pictures as $p) {
            $inner .= "--$related\nContent-Type: {$p['type']}; name=\"{$p['name']}\"\nContent-Transfer-Encoding: base64\n"
                . "Content-ID: <{$p['cid']}>\nContent-Disposition: inline; filename=\"{$p['name']}\"\n\n" . $b64($p['data']);
        }
        $htmlPart = "Content-Type: multipart/related; type=\"text/html\"; boundary=\"$related\"\n\n" . $inner . "--$related--\n";
    }
    $body = "This is a message in HTML and plain text.\n\n"
        . "--$boundary\nContent-Type: text/plain; charset=UTF-8\nContent-Transfer-Encoding: quoted-printable\n\n" . str_replace("\r\n", "\n", quoted_printable_encode(str_replace("\n", "\r\n", $text))) . "\n"
        . "--$boundary\n" . $htmlPart
        . "--$boundary--\n";
    return ['multipart/alternative; boundary="' . $boundary . '"', $body];
}

/** A plain SMTP conversation (SSL on 465, STARTTLS on 587), with AUTH LOGIN when a user is set */
function smtp_send(array $c, array $to, string $subject, string $body, array $headers): void
{
    $secure = in_array($c['secure'], ['ssl', 'tls', 'none'], true) ? $c['secure'] : 'tls';
    $port = (int) $c['port'] ?: ($secure === 'ssl' ? 465 : 587);
    // Port 465 is encrypted from the first byte (SSL); 587 starts plain and switches with STARTTLS
    if ($port === 465) $secure = 'ssl';
    elseif ($port === 587 && $secure === 'ssl') $secure = 'tls';
    $context = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'SNI_enabled' => true, 'peer_name' => $c['host']]]);
    $fp = @stream_socket_client(($secure === 'ssl' ? 'ssl://' : 'tcp://') . $c['host'] . ':' . $port, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $context);
    if (!$fp) throw new RuntimeException("Could not connect to {$c['host']}:$port ($errstr). Check the server name and port.");
    stream_set_timeout($fp, 20);
    $read = function () use ($fp): array {
        $text = '';
        while (($line = fgets($fp, 1024)) !== false) {
            $text .= $line;
            if (strlen($line) < 4 || $line[3] === ' ') break;
        }
        // One line for messages: "530-5.7.0 Authentication…\r\n530 5.7.0 …" → "5.7.0 Authentication… 5.7.0 …"
        return [(int) substr($text, 0, 3), trim(preg_replace(['/(^|\n)\d{3}[ -]/', '/\s+/'], [' ', ' '], $text))];
    };
    $cmd = function (string $line, array $ok, string $what = '') use ($fp, $read): string {
        if ($line !== '') fwrite($fp, $line . "\r\n");
        [$code, $text] = $read();
        if (!in_array($code, $ok, true)) throw new RuntimeException(($what ?: 'The mail server refused the email') . ': ' . ($text ?: 'no answer'));
        return $text;
    };
    try {
        $cmd('', [220], 'The mail server did not greet us');
        $me = preg_replace('/[^a-z0-9.-]/i', '', (string) ($_SERVER['SERVER_NAME'] ?? '')) ?: 'localhost';
        $ehlo = $cmd("EHLO $me", [250]);
        if ($secure === 'tls') {
            if (stripos($ehlo, 'STARTTLS') === false) throw new RuntimeException('The mail server does not offer TLS on this port — try port 465 with SSL.');
            $cmd('STARTTLS', [220]);
            if (!@stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT)) throw new RuntimeException('Could not start a secure connection with the mail server.');
            $ehlo = $cmd("EHLO $me", [250]);
        }
        if ($c['user'] !== '') {
            $refused = 'The mail server refused the username or password' . (stripos($c['host'], 'gmail') !== false ? ' — Gmail only accepts a current App Password made on this same account' : '');
            // AUTH PLAIN when offered (one step), otherwise AUTH LOGIN
            if (preg_match('/\bAUTH[ =].*?(?<![\w-])PLAIN(?![\w-])/i', $ehlo)) {
                $cmd('AUTH PLAIN ' . base64_encode("\0" . $c['user'] . "\0" . $c['pass']), [235], $refused);
            } else {
                $cmd('AUTH LOGIN', [334], 'The mail server does not accept a sign-in');
                $cmd(base64_encode($c['user']), [334], 'The mail server refused the username');
                $cmd(base64_encode($c['pass']), [235], $refused);
            }
        }
        $cmd('MAIL FROM:<' . $c['from'] . '>', [250], 'The mail server refused the sender ' . $c['from']);
        foreach ($to as $rcpt) $cmd("RCPT TO:<$rcpt>", [250, 251], 'The mail server refused ' . $rcpt);
        $cmd('DATA', [354]);
        $head = 'To: ' . implode(', ', $to) . "\r\nSubject: $subject\r\n";
        foreach ($headers as $k => $v) if ($v !== '') $head .= "$k: $v\r\n";
        // Lines starting with a dot are doubled so they don't end the message early
        $data = preg_replace('/^\./m', '..', str_replace("\n", "\r\n", $body));
        $cmd($head . "\r\n" . $data . "\r\n.", [250], 'The mail server did not accept the email');
        fwrite($fp, "QUIT\r\n");
    } finally {
        fclose($fp);
    }
}

/**
 * Tells the team about something that arrived from the website; replies go to the visitor. $button: [label, admin page].
 * $extra: anything email_html() takes — heading, details, message, links…
 */
function notify_team(string $subject, string $body, string $replyTo = '', ?array $button = null, array $extra = []): bool
{
    $to = trim((string) setting('notify_email', '')) ?: (string) setting('email', '');
    $subject = 'Mangalam website: ' . $subject;
    $m = $extra + [
        'subject' => $subject, 'eyebrow' => 'For the team', 'body' => $body,
        'button' => $button ? [$button[0], admin_link($button[1])] : null, 'team' => true,
    ];
    return send_mail($to, $subject, email_text($m), $replyTo, [], false, ['html' => email_html($m)]);
}

/** The visitor's details for the team's emails: [label, value, link] rows */
function visitor_rows(string $name, string $email, string $phone): array
{
    $rows = [['Name', $name]];
    if ($email !== '') $rows[] = ['Email', $email, 'mailto:' . $email];
    if ($phone !== '') $rows[] = ['Phone', $phone, 'tel:' . preg_replace('/[^\d+]/', '', $phone)];
    return $rows;
}

/** Quick ways for the team to answer a visitor: reply by email, WhatsApp, call */
function visitor_links(string $email, string $phone, string $about = ''): array
{
    $links = [];
    if ($email !== '') $links[] = ['Reply by email', 'mailto:' . $email . ($about !== '' ? '?subject=' . rawurlencode($about) : '')];
    if (($wa = whatsapp_url($phone)) !== '') $links[] = ['WhatsApp', $wa];
    if ($phone !== '') $links[] = ['Call', 'tel:' . preg_replace('/[^\d+]/', '', $phone)];
    return $links;
}

/** Once a day, from 7 AM: today's appointments to the team (sent by the first page someone opens after that) */
function maybe_send_daily_summary(): void
{
    try {
        $today = date('Y-m-d');
        if ((int) date('G') < 7 || !setting('mail_enabled', false) || !setting('notify_summary', true) || setting('summary_sent_on', '') === $today) return;
        save_settings(['summary_sent_on' => $today]);
        $list = rows("SELECT * FROM appointments WHERE date = ? AND status <> 'cancelled' ORDER BY time = '', STR_TO_DATE(time, '%h:%i %p'), id", [$today]);
        $rows = array_map(fn ($a) => [$a['time'] ?: 'Time to confirm', $a['name'] . ' · ' . $a['interest']
            . ($a['phone'] ? ' · ' . $a['phone'] : '') . ($a['consultant'] ? ' · with ' . $a['consultant'] : '') . ($a['status'] === 'pending' ? ' (not yet confirmed)' : '')], $list);
        $count = plural(count($list), 'appointment');
        notify_team($count . ' today, ' . date('l j F'), $list ? 'Good morning. Here is who is visiting the store today.' : 'Good morning. There are no appointments today.', '',
            ['Open the calendar', 'appointments.html'], [
                'eyebrow' => 'For the team · Morning summary', 'heading' => $list ? '*' . $count . '* today' : 'A *quiet* day',
                'details' => $list ? ['title' => date('l j F Y'), 'rows' => $rows] : null,
            ]);
    } catch (Throwable $e) {
        error_log('Mangalam daily summary: ' . $e->getMessage());
    }
}

/* ---------- Addresses for links in emails ---------- */

/** The website's public address, for links in emails ("https://mangalamjewellers.in/"): Settings › General, or the address it is being used at */
function site_url(string $path = ''): string
{
    $base = trim((string) setting('site_url', ''));
    if ($base === '' && PHP_SAPI !== 'cli' && isset($_SERVER['HTTP_HOST'])) {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $base = $scheme . '://' . $_SERVER['HTTP_HOST'] . base_url();
    }
    // From the command line there is no request: the address the admin was last opened at
    if ($base === '') $base = (string) setting('site_url_seen', 'http://localhost/');
    return rtrim($base, '/') . '/' . ltrim($path, '/');
}

/** A page of the admin, for buttons in the team's emails */
function admin_link(string $page): string
{
    return site_url('admin/' . $page);
}

/**
 * The website's own pictures in an email (the logo, a piece's photograph) as attachments shown in place: each
 * <img src="https://…/assets/…"> becomes src="cid:…", so the customer's mailbox needs no link back to the website.
 * @return array [the HTML, [[cid, type, name, data], …]]
 */
function email_inline_images(string $html): array
{
    $base = site_url();
    $assets = realpath(ROOT_DIR . '/assets');
    $types = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif'];
    $pictures = [];
    $html = preg_replace_callback('/(<img\b[^>]*?\bsrc=")([^"]+)(")/i', function ($m) use ($base, $assets, $types, &$pictures) {
        $url = html_entity_decode($m[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (!$assets || !str_starts_with($url, $base)) return $m[0];
        $file = realpath(ROOT_DIR . '/' . ltrim((string) strtok(substr($url, strlen($base)), '?#'), '/'));
        $ext = strtolower(pathinfo((string) $file, PATHINFO_EXTENSION));
        // Only files under assets/, of a kind every mailbox shows, and small enough not to weigh the email down
        if (!$file || !str_starts_with($file, $assets . DIRECTORY_SEPARATOR) || !isset($types[$ext]) || filesize($file) > 400 * 1024) return $m[0];
        if (!isset($pictures[$file])) {
            $pictures[$file] = ['cid' => 'mj' . count($pictures) . '.' . bin2hex(random_bytes(6)) . '@mangalam', 'type' => $types[$ext],
                'name' => preg_replace('/[^\w.-]/', '', basename($file)), 'data' => (string) file_get_contents($file)];
        }
        return $m[1] . 'cid:' . $pictures[$file]['cid'] . $m[3];
    }, $html);
    return [$html, array_values($pictures)];
}

/* ---------- The house layout ---------- */

/** Plain text as email paragraphs: blank lines start a paragraph, web addresses become links */
function email_paragraphs(string $text, string $style): string
{
    $out = '';
    foreach (preg_split('/\n\s*\n/', trim(str_replace(["\r\n", "\r"], "\n", $text))) as $para) {
        if (trim($para) === '') continue;
        $html = nl2br(e(trim($para)), false);
        $html = preg_replace('#\bhttps?://[^\s<]+[^\s<.,;:!?)\'"]#i', '<a href="$0" style="color:#7A5C00;text-decoration:underline;word-break:break-all;">$0</a>', $html);
        $out .= '<p style="' . $style . '">' . $html . '</p>';
    }
    return $out;
}

/**
 * An email in the house style: the gold logo on midnight wine, then an ivory card with an eyebrow, a heading
 * (*words* in gold italics), the text, and optionally the visitor's message, a card of details, the piece, a button,
 * the earlier message and a signature; the store's promises and contact details below.
 * $m: subject, preheader, eyebrow, heading, body, message [label, text] (shown in a frame, for the team),
 *     details [title, rows => [[label, value, url?], …]], product [name, meta, price, image, url], button [label, url],
 *     links [[label, url], …] (small links under the button), quote [who, when, text], signature [name, role],
 *     team (true for the team's own emails: no promises, a shorter footer), marker (true: "reply above this line"),
 *     footnote (the small print at the very end)
 */
function email_html(array $m): string
{
    $store = (string) setting('store_name', 'Mangalam Jewellers');
    $team = !empty($m['team']);
    $serif = "'Cormorant Garamond',Georgia,'Times New Roman',serif";
    $sans = "Jost,'Helvetica Neue',Helvetica,Arial,sans-serif";
    $text = 'margin:0 0 16px;font-family:' . $sans . ';font-size:15.5px;line-height:1.75;font-weight:300;color:#4A3F39;';
    $label = 'margin:0;font-family:' . $sans . ';font-size:10px;line-height:1.4;font-weight:500;letter-spacing:3px;text-transform:uppercase;color:#7A5C00;';
    $founded = (string) setting('founded', '1962');
    $tel = fn (string $phone) => 'tel:' . preg_replace('/[^\d+]/', '', $phone);

    $preheader = trim((string) ($m['preheader'] ?? mb_substr(preg_replace('/\s+/', ' ', (string) ($m['body'] ?? '')), 0, 110)));
    $heading = (string) ($m['heading'] ?? '');
    $headingHtml = $heading !== '' ? '<h1 class="mj-h1" style="margin:0 0 20px;font-family:' . $serif . ';font-size:36px;line-height:1.15;font-weight:500;color:#241A17;">'
        . str_replace('<em>', '<em style="font-style:italic;color:#7A5C00;">', star($heading)) . '</h1>' : '';
    $rule = '<td width="44" style="width:44px;vertical-align:middle;"><div style="height:1px;line-height:1px;font-size:1px;background:#C9A23A;">&nbsp;</div></td>';
    $ornament = '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 26px;"><tr>'
        . $rule . '<td style="padding:0 9px;font-size:9px;line-height:1;color:#A57E00;vertical-align:middle;">&#9670;</td>' . $rule . '</tr></table>';

    // What the visitor wrote, framed (the team's emails)
    $message = '';
    if (!empty($m['message'][1]) && trim((string) $m['message'][1]) !== '') {
        $message = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:4px 0 26px;"><tr>'
            . '<td style="background:#FFFFFF;border:1px solid #E4D6BC;border-left:3px solid #C9A23A;padding:18px 22px 6px;">'
            . '<p style="' . $label . 'margin-bottom:10px;">' . e($m['message'][0] ?: 'Their message') . '</p>'
            . email_paragraphs(mb_strimwidth((string) $m['message'][1], 0, 4000, '…'), 'margin:0 0 12px;font-family:' . $sans . ';font-size:15px;line-height:1.7;font-weight:400;color:#241A17;')
            . '</td></tr></table>';
    }

    // A card of details: the appointment, the enquiry, who wrote…
    $details = '';
    $rows = array_values(array_filter((array) ($m['details']['rows'] ?? []), fn ($r) => isset($r[1]) && trim((string) $r[1]) !== ''));
    if ($rows) {
        $cells = '';
        foreach ($rows as $i => $r) {
            $line = $i < count($rows) - 1 ? 'border-bottom:1px solid #EFE6D5;' : '';
            $value = nl2br(e((string) $r[1]), false);
            if (!empty($r[2])) $value = '<a href="' . e($r[2]) . '" style="color:#241A17;text-decoration:none;border-bottom:1px solid #E3C77A;">' . $value . '</a>';
            $cells .= '<tr>'
                . '<td class="mj-dt" width="34%" style="width:34%;padding:12px 14px 12px 0;' . $line . 'vertical-align:top;font-family:' . $sans . ';font-size:10.5px;line-height:1.9;font-weight:500;letter-spacing:2px;text-transform:uppercase;color:#8E7F72;">' . e((string) $r[0]) . '</td>'
                . '<td class="mj-dd" style="padding:12px 0;' . $line . 'vertical-align:top;font-family:' . $sans . ';font-size:15px;line-height:1.55;font-weight:400;color:#241A17;">' . $value . '</td>'
                . '</tr>';
        }
        $details = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:6px 0 28px;border:1px solid #E4D6BC;background:#FFFFFF;">'
            . (!empty($m['details']['title']) ? '<tr><td style="background:#F5EEE2;border-bottom:1px solid #E4D6BC;padding:13px 22px;"><p style="' . $label . '"><span style="color:#A57E00;">&#9670;</span>&nbsp;&nbsp;' . e((string) $m['details']['title']) . '</p></td></tr>' : '')
            . '<tr><td style="padding:4px 22px;"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">' . $cells . '</table></td></tr></table>';
    }

    $product = '';
    if (!empty($m['product'])) {
        $p = $m['product'];
        $img = !empty($p['image'])
            ? '<td width="104" style="width:104px;padding:0 20px 0 0;vertical-align:middle;"><a href="' . e($p['url']) . '"><img src="' . e($p['image']) . '" width="104" height="104" alt="' . e($p['name']) . '" style="display:block;width:104px;height:104px;object-fit:cover;border:0;background:#F5EEE2;"></a></td>'
            : '<td width="56" style="width:56px;padding:0 16px 0 0;vertical-align:middle;"><div style="width:56px;height:56px;line-height:56px;text-align:center;background:#2E0A16;color:#E3C77A;font-family:' . $serif . ';font-size:26px;">&#10022;</div></td>';
        $product = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:6px 0 28px;border:1px solid #E4D6BC;background:#FFFFFF;"><tr><td style="padding:14px;">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>' . $img
            . '<td style="vertical-align:middle;">'
            . '<p style="' . $label . 'margin-bottom:6px;">' . e($p['meta'] ?? 'The piece') . '</p>'
            . '<p style="margin:0 0 4px;font-family:' . $serif . ';font-size:23px;line-height:1.2;font-weight:500;color:#241A17;">' . e($p['name']) . '</p>'
            . (!empty($p['price']) ? '<p style="margin:0 0 8px;font-family:' . $sans . ';font-size:14px;color:#4A3F39;">' . e($p['price']) . '</p>' : '')
            . '<a href="' . e($p['url']) . '" style="font-family:' . $sans . ';font-size:11px;letter-spacing:2px;text-transform:uppercase;color:#7A5C00;text-decoration:none;font-weight:500;">View the piece &rarr;</a>'
            . '</td></tr></table></td></tr></table>';
    }

    $button = '';
    if (!empty($m['button'][0]) && !empty($m['button'][1])) {
        $button = '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:10px 0 8px;"><tr>'
            . '<td style="background:#2E0A16;border:1px solid #A57E00;">'
            . '<a href="' . e($m['button'][1]) . '" style="display:inline-block;padding:16px 30px;font-family:' . $sans . ';font-size:11.5px;line-height:1;font-weight:500;letter-spacing:3px;text-transform:uppercase;color:#F3E6C2;text-decoration:none;">'
            . e($m['button'][0]) . ' &nbsp;&rarr;</a></td></tr></table>';
    }
    $links = '';
    if (!empty($m['links'])) {
        $links = '<p style="margin:16px 0 6px;font-family:' . $sans . ';font-size:13px;line-height:1.7;color:#776A60;">'
            . ($team ? '' : 'Need anything? &nbsp;')
            . implode(' &nbsp;&middot;&nbsp; ', array_map(fn ($l) => '<a href="' . e($l[1]) . '" style="color:#7A5C00;text-decoration:none;font-weight:500;white-space:nowrap;">' . e($l[0]) . '</a>', $m['links'])) . '</p>';
    }

    $signature = '';
    if (!empty($m['signature'])) {
        $signature = '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:30px 0 0;"><tr>'
            . '<td style="border-top:1px solid #E4D6BC;padding:22px 0 0;">'
            . '<p style="margin:0;font-family:' . $sans . ';font-size:14px;font-weight:300;color:#776A60;">With warm regards,</p>'
            . '<p style="margin:4px 0 0;font-family:' . $serif . ';font-size:25px;font-style:italic;font-weight:500;color:#241A17;">' . e($m['signature'][0]) . '</p>'
            . '<p style="margin:3px 0 0;font-family:' . $sans . ';font-size:10.5px;letter-spacing:2.5px;text-transform:uppercase;color:#7A5C00;">' . e($m['signature'][1] ?? $store) . '</p>'
            . '</td></tr></table>';
    }

    $quote = '';
    if (!empty($m['quote'][2])) {
        $quote = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:34px 0 0;"><tr>'
            . '<td style="border-left:2px solid #E3C77A;background:#F5EEE2;padding:16px 20px;">'
            . '<p style="margin:0 0 8px;font-family:' . $sans . ';font-size:10px;letter-spacing:2.5px;text-transform:uppercase;color:#776A60;">' . e($m['quote'][0]) . (!empty($m['quote'][1]) ? ' &middot; ' . e($m['quote'][1]) : '') . '</p>'
            . email_paragraphs(mb_strimwidth((string) $m['quote'][2], 0, 900, '…'), 'margin:0 0 8px;font-family:' . $sans . ';font-size:13.5px;line-height:1.65;font-weight:300;color:#4A3F39;')
            . '</td></tr></table>';
    }

    // The store's promises, under the card (customers' emails)
    $promises = '';
    if (!$team) {
        $cols = '';
        foreach ([['BIS hallmarked', 'Certified 22K &amp; 18K gold'], ['Handmade in Surat', 'By three generations of karigars'], ['Lifetime care', 'Free cleaning &amp; polishing']] as [$title, $sub]) {
            $cols .= '<td class="mj-col" width="33%" valign="top" style="width:33%;padding:0 8px;text-align:center;vertical-align:top;">'
                . '<p style="margin:0 0 7px;font-size:10px;line-height:1;color:#A57E00;">&#9670;</p>'
                . '<p style="margin:0 0 3px;font-family:' . $serif . ';font-size:17px;line-height:1.25;font-weight:500;color:#241A17;">' . $title . '</p>'
                . '<p class="mj-col-sub" style="margin:0;font-family:' . $sans . ';font-size:11.5px;line-height:1.5;color:#776A60;">' . $sub . '</p></td>';
        }
        $promises = '<tr><td class="mj-promises" style="background:#F5EEE2;border-top:1px solid #E4D6BC;padding:26px 26px 24px;">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>' . $cols . '</tr></table></td></tr>';
    }

    $address = store_address();
    $phone = (string) setting('phone', '');
    $email = (string) setting('email', '');
    $foot = 'margin:0 0 6px;font-family:' . $sans . ';font-size:12.5px;line-height:1.7;color:#CDBFA8;';
    $footLink = 'color:#E3C77A;text-decoration:none;';
    $social = array_filter(['Instagram' => (string) setting('instagram', ''), 'Facebook' => (string) setting('facebook', ''), 'YouTube' => (string) setting('youtube', '')],
        fn ($u) => $u !== '' && !preg_match('#^https?://(www\.)?(instagram|facebook|youtube)\.com/?$#', $u));
    $contact = array_filter([
        $phone !== '' ? '<a href="' . e($tel($phone)) . '" style="' . $footLink . '">' . e($phone) . '</a>' : '',
        $email !== '' ? '<a href="mailto:' . e($email) . '" style="' . $footLink . '">' . e($email) . '</a>' : '',
    ]);
    $emblem = '<img src="' . e(site_url('assets/images/brand/email-emblem.png')) . '" width="48" height="31" alt="" style="display:block;margin:0 auto 14px;width:48px;height:auto;border:0;">';
    $footer = $emblem
        . '<p style="margin:0 0 12px;font-family:' . $serif . ';font-size:17px;letter-spacing:5px;text-transform:uppercase;color:#E3C77A;">' . e($store) . '</p>'
        . (!$team
            ? ($address ? '<p style="' . $foot . '">' . e($address) . '</p>' : '')
                . ($contact ? '<p style="' . $foot . '">' . implode(' &nbsp;&middot;&nbsp; ', $contact) . '</p>' : '')
                . '<p style="' . $foot . 'margin-bottom:14px;">' . e(hours_short()) . '</p>'
                . ($social ? '<p style="margin:0 0 18px;font-family:' . $sans . ';font-size:11px;letter-spacing:2px;text-transform:uppercase;">' . implode(' &nbsp;&middot;&nbsp; ', array_map(fn ($k, $u) => '<a href="' . e($u) . '" style="' . $footLink . '">' . $k . '</a>', array_keys($social), $social)) . '</p>' : '')
            : '')
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td align="center" style="border-top:1px solid #3E1220;padding:16px 0 0;text-align:center;">'
        . '<p style="margin:0 0 6px;font-family:' . $sans . ';font-size:11px;line-height:1.6;color:#8E7F72;">'
        . e($m['footnote'] ?? ($team ? 'Sent by the ' . $store . ' website to the team, from Settings › Notifications.' : 'You are receiving this because you wrote to ' . $store . '. Simply reply to this email to write to us.')) . '</p>'
        . '<p style="margin:0;font-family:' . $sans . ';font-size:11px;line-height:1.6;color:#6E5F55;">&copy; ' . date('Y') . ' ' . e($store) . ' &middot; Since ' . e($founded) . '</p>'
        . '</td></tr></table>';

    $logoWidth = $team ? 132 : 188;
    $logo = '<a href="' . e(site_url()) . '" style="text-decoration:none;"><img src="' . e(site_url('assets/images/brand/email-logo.png')) . '" width="' . $logoWidth . '" alt="' . e($store) . '"'
        . ' style="display:block;margin:0 auto;width:' . $logoWidth . 'px;max-width:100%;height:auto;border:0;font-family:' . $serif . ';font-size:26px;letter-spacing:6px;color:#E3C77A;"></a>';
    $topline = $team ? 'Website notification' : 'Fine jewellery &nbsp;&middot;&nbsp; Since ' . e($founded);

    return '<!doctype html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="x-apple-disable-message-reformatting">
<meta name="color-scheme" content="light">
<meta name="supported-color-schemes" content="light">
<title>' . e($m['subject'] ?? $store) . '</title>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,500;1,500&amp;family=Jost:wght@300;400;500&amp;display=swap" rel="stylesheet">
<style>
  body { margin: 0; padding: 0; background: #EFE7DA; -webkit-text-size-adjust: 100%; }
  a { color: #7A5C00; }
  @media (max-width: 620px) {
    .mj-outer { padding: 0 !important; }
    .mj-pad { padding-left: 24px !important; padding-right: 24px !important; padding-top: 36px !important; }
    .mj-h1 { font-size: 30px !important; }
    .mj-head { padding: 26px 16px 22px !important; }
    .mj-promises { padding: 22px 16px 8px !important; }
    .mj-col { display: block !important; width: 100% !important; padding: 0 0 16px !important; }
    .mj-dt { display: block !important; width: 100% !important; padding: 12px 0 0 !important; border-bottom: 0 !important; }
    .mj-dd { display: block !important; width: 100% !important; padding: 2px 0 12px !important; }
  }
</style>
</head>
<body style="margin:0;padding:0;background:#EFE7DA;">
' . (!empty($m['marker']) ? '<div style="display:none;max-height:0;overflow:hidden;font-size:1px;line-height:1px;color:#EFE7DA;">##- Please type your reply above this line -##</div>' : '') . '
<div style="display:none;max-height:0;overflow:hidden;opacity:0;font-size:1px;line-height:1px;color:#EFE7DA;">' . e($preheader) . str_repeat('&#8199;&#65279;&#847; ', 30) . '</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#EFE7DA;">
  <tr><td class="mj-outer" align="center" style="padding:28px 14px 36px;">
    <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:600px;">
      <tr><td align="center" style="padding:0 0 14px;font-family:' . $sans . ';font-size:10px;line-height:1.4;letter-spacing:3px;text-transform:uppercase;color:#8E7F72;">' . $topline . '</td></tr>
      <tr><td style="background:#2E0A16;padding:12px;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border:1px solid #5C3A24;"><tr>
          <td class="mj-head" align="center" style="padding:' . ($team ? '24px 24px 20px' : '32px 24px 28px') . ';">' . $logo . '</td>
        </tr></table>
      </td></tr>
      <tr><td style="height:3px;line-height:3px;font-size:0;background-color:#A57E00;background-image:linear-gradient(90deg,#8C6A00,#C9A23A,#F4E2A8,#D4AF37,#A57E00,#E9CF83,#8C6A00);">&nbsp;</td></tr>
      <tr><td class="mj-pad" style="background:#FBF8F2;padding:46px 52px 44px;">
        ' . (!empty($m['eyebrow']) ? '<p style="margin:0 0 14px;font-family:' . $sans . ';font-size:10.5px;line-height:1.4;font-weight:500;letter-spacing:4px;text-transform:uppercase;color:#7A5C00;">' . e($m['eyebrow']) . '</p>' : '') . '
        ' . $headingHtml . ($headingHtml ? $ornament : '') . '
        ' . email_paragraphs((string) ($m['body'] ?? ''), $text) . '
        ' . $message . $details . $product . $button . $links . $signature . $quote . '
      </td></tr>
      ' . $promises . '
      <tr><td align="center" style="background:#1D060E;padding:32px 40px 30px;border-top:1px solid #4A1224;">' . $footer . '</td></tr>
    </table>
  </td></tr>
</table>
</body>
</html>';
}

/** The plain-text part that goes with email_html() */
function email_text(array $m): string
{
    $store = (string) setting('store_name', 'Mangalam Jewellers');
    $out = [];
    if (!empty($m['marker'])) $out[] = '##- Please type your reply above this line -##';
    if (!empty($m['eyebrow'])) $out[] = mb_strtoupper((string) $m['eyebrow']);
    if (!empty($m['heading'])) $out[] = star_plain((string) $m['heading']);
    $out[] = trim((string) ($m['body'] ?? ''));
    if (!empty($m['message'][1])) $out[] = mb_strtoupper((string) ($m['message'][0] ?: 'Their message')) . ":\n" . trim((string) $m['message'][1]);
    $rows = array_filter((array) ($m['details']['rows'] ?? []), fn ($r) => isset($r[1]) && trim((string) $r[1]) !== '');
    if ($rows) {
        $out[] = (!empty($m['details']['title']) ? mb_strtoupper((string) $m['details']['title']) . "\n" : '')
            . implode("\n", array_map(fn ($r) => $r[0] . ': ' . str_replace("\n", ', ', (string) $r[1]), $rows));
    }
    if (!empty($m['product'])) $out[] = $m['product']['name'] . (!empty($m['product']['price']) ? ' — ' . $m['product']['price'] : '') . "\n" . $m['product']['url'];
    if (!empty($m['button'][0]) && !empty($m['button'][1])) $out[] = $m['button'][0] . ': ' . $m['button'][1];
    foreach ($m['links'] ?? [] as $l) $out[] = str_starts_with($l[1], 'tel:') && preg_match('/\d/', $l[0]) ? $l[0] : $l[0] . ': ' . preg_replace('/^(?:tel|mailto):([^?]*).*$/', '$1', $l[1]);
    if (!empty($m['signature'])) $out[] = "With warm regards,\n" . $m['signature'][0] . "\n" . ($m['signature'][1] ?? $store);
    if (!empty($m['quote'][2])) $out[] = $m['quote'][0] . (!empty($m['quote'][1]) ? ', ' . $m['quote'][1] : '') . ":\n> " . str_replace("\n", "\n> ", trim((string) $m['quote'][2]));
    $out[] = !empty($m['team'])
        ? "—\n" . ($m['footnote'] ?? 'Sent by the ' . $store . ' website to the team.')
        : "—\n" . $store . ' · ' . store_address() . "\n" . implode(' · ', array_filter([(string) setting('phone', ''), (string) setting('email', '')])) . "\n" . hours_short();
    return implode("\n\n", array_filter($out, fn ($s) => trim((string) $s) !== ''));
}

/* ---------- Reading replies (IMAP) ---------- */

/** The mailbox replies are read from; blank fields take the mail server's (smtp.gmail.com → imap.gmail.com, same sign-in) */
function mailbox_config(array $override = []): array
{
    $smtp = mail_config();
    $c = [
        'host' => trim((string) setting('imap_host', '')), 'port' => (int) setting('imap_port', 993),
        'secure' => (string) setting('imap_secure', 'ssl'), 'user' => trim((string) setting('imap_user', '')),
        'pass' => (string) setting('imap_pass', ''), 'folder' => trim((string) setting('imap_folder', '')) ?: 'INBOX',
    ];
    foreach ($override as $k => $v) if ($v !== null && $v !== '') $c[$k] = $v;
    if ($c['host'] === '' && $smtp['host'] !== '') $c['host'] = preg_replace('/^(smtp|smtpout|mail)\./i', 'imap.', $smtp['host']);
    if ($c['user'] === '') $c['user'] = $smtp['user'];
    if ($c['pass'] === '' && $c['user'] === $smtp['user']) $c['pass'] = $smtp['pass'];
    return $c;
}

/** The address customers reply to: the mailbox replies are read from, or else the store's email */
function reply_address(): string
{
    if (setting('imap_enabled', false)) {
        $user = mailbox_config()['user'];
        if (valid_email($user)) return $user;
        return mail_config()['from'];
    }
    return (string) setting('email', '');
}

function mailbox_quote(string $s): string
{
    return '"' . addcslashes($s, "\"\\") . '"';
}

/** Connects and signs in (SSL on 993, STARTTLS on 143) */
function mailbox_connect(array $c)
{
    $secure = in_array($c['secure'], ['ssl', 'tls', 'none'], true) ? $c['secure'] : 'ssl';
    $port = (int) $c['port'] ?: ($secure === 'ssl' ? 993 : 143);
    if ($port === 993) $secure = 'ssl';
    elseif ($port === 143 && $secure === 'ssl') $secure = 'tls';
    $context = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'SNI_enabled' => true, 'peer_name' => $c['host']]]);
    $fp = @stream_socket_client(($secure === 'ssl' ? 'ssl://' : 'tcp://') . $c['host'] . ':' . $port, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $context);
    if (!$fp) throw new RuntimeException("Could not connect to the mailbox at {$c['host']}:$port ($errstr). Check the server name and port.");
    stream_set_timeout($fp, 30);
    $greeting = fgets($fp, 4096);
    if ($greeting === false || !preg_match('/^\* (OK|PREAUTH)/i', $greeting)) {
        fclose($fp);
        throw new RuntimeException('The mailbox did not greet us: ' . trim((string) $greeting));
    }
    try {
        if ($secure === 'tls') {
            mailbox_command($fp, 'STARTTLS', 'The mailbox does not offer TLS on this port — try port 993 with SSL');
            if (!@stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT)) throw new RuntimeException('Could not start a secure connection with the mailbox.');
        }
        mailbox_command($fp, 'LOGIN ' . mailbox_quote($c['user']) . ' ' . mailbox_quote($c['pass']),
            'The mailbox refused the username or password' . (stripos($c['host'], 'gmail') !== false ? ' — Gmail needs IMAP switched on and an App Password' : ''));
    } catch (Throwable $e) {
        fclose($fp);
        throw $e;
    }
    return $fp;
}

/**
 * Sends one IMAP command and reads the answer: [untagged lines, literals]. A {n} literal (a whole email) is read
 * byte for byte, and stands as {#i} in its line.
 */
function mailbox_command($fp, string $command, string $what = ''): array
{
    static $n = 0;
    $tag = 'MJ' . (++$n);
    fwrite($fp, $tag . ' ' . $command . "\r\n");
    $lines = [];
    $literals = [];
    $lost = function () use ($fp) {
        $meta = stream_get_meta_data($fp);
        return new RuntimeException(!empty($meta['timed_out']) ? 'The mailbox took too long to answer.' : 'The mailbox closed the connection.');
    };
    while (true) {
        $line = fgets($fp);
        if ($line === false) throw $lost();
        while (preg_match('/\{(\d+)\}\r?\n$/', $line, $m)) {
            $size = (int) $m[1];
            $data = '';
            while (strlen($data) < $size) {
                $chunk = fread($fp, min(65536, $size - strlen($data)));
                if ($chunk === false || $chunk === '') throw $lost();
                $data .= $chunk;
            }
            $literals[] = $data;
            $rest = fgets($fp);
            if ($rest === false) throw $lost();
            $line = substr($line, 0, -strlen($m[0])) . '{#' . (count($literals) - 1) . '}' . $rest;
        }
        $line = rtrim($line, "\r\n");
        if (str_starts_with($line, $tag . ' ')) {
            if (!preg_match('/^' . $tag . ' OK\b/i', $line)) {
                throw new RuntimeException(($what ?: 'The mailbox refused ' . strtok($command, ' ')) . ': ' . trim(preg_replace('/^' . $tag . ' (NO|BAD)\s*/i', '', $line)));
            }
            return [$lines, $literals];
        }
        $lines[] = $line;
    }
}

/** Signs in and opens the folder, to check the settings; returns how many emails it holds */
function mailbox_test(array $override = []): int
{
    $c = mailbox_config($override);
    if ($c['host'] === '' || $c['user'] === '') throw new RuntimeException('Fill in the mailbox server and username first.');
    $fp = mailbox_connect($c);
    try {
        [$lines] = mailbox_command($fp, 'EXAMINE ' . mailbox_quote($c['folder']), 'The mailbox has no folder called ' . $c['folder']);
        $count = 0;
        foreach ($lines as $l) if (preg_match('/^\* (\d+) EXISTS/i', $l, $m)) $count = (int) $m[1];
        try { mailbox_command($fp, 'LOGOUT'); } catch (Throwable $e) { /* already closing */ }
        return $count;
    } finally {
        fclose($fp);
    }
}

/**
 * Reads the emails that arrived since the last time (never marking or moving anything in the mailbox): replies join
 * their conversation, and — when "Also add new emails" is on — other customers' emails start one.
 * @return array ['read' => emails looked at, 'added' => replies added, 'started' => new conversations]
 */
function fetch_replies(int $limit = 25): array
{
    $result = ['read' => 0, 'added' => 0, 'started' => 0];
    // One reader at a time (two admin pages opening together would read the same emails)
    if (!(int) val('SELECT GET_LOCK(?, 0)', ['mj_fetch_replies'])) return $result;
    $state = (array) setting('imap_state', []);
    $c = mailbox_config();
    try {
        if ($c['host'] === '' || $c['user'] === '') throw new RuntimeException('Fill in the mailbox to read replies from in Settings › Notifications.');
        $fp = mailbox_connect($c);
        try {
            [$lines] = mailbox_command($fp, 'SELECT ' . mailbox_quote($c['folder']), 'The mailbox has no folder called ' . $c['folder']);
            $validity = 0;
            $next = 0;
            foreach ($lines as $l) {
                if (preg_match('/UIDVALIDITY (\d+)/i', $l, $m)) $validity = (int) $m[1];
                if (preg_match('/UIDNEXT (\d+)/i', $l, $m)) $next = (int) $m[1];
            }
            $last = (int) ($state['validity'] ?? 0) === $validity ? (int) ($state['uid'] ?? 0) : 0;
            // The first time (or after the mailbox was rebuilt) only the last two days are read, not the whole mailbox
            [$lines] = mailbox_command($fp, $last ? 'UID SEARCH UID ' . ($last + 1) . ':*' : 'UID SEARCH SINCE ' . date('j-M-Y', strtotime('-2 days')));
            $uids = [];
            foreach ($lines as $l) if (preg_match('/^\* SEARCH\b(.*)$/i', $l, $m)) array_push($uids, ...array_map('intval', preg_split('/\s+/', trim($m[1]), -1, PREG_SPLIT_NO_EMPTY)));
            $uids = array_values(array_filter(array_unique($uids), fn ($u) => $u > $last));
            sort($uids);
            foreach (array_slice($uids, 0, $limit) as $uid) {
                [, $literals] = mailbox_command($fp, 'UID FETCH ' . $uid . ' (BODY.PEEK[])');
                if ($literals) {
                    try {
                        $taken = take_email(parse_email($literals[0]));
                        if ($taken) $result[$taken]++;
                    } catch (Throwable $e) {
                        error_log('Mangalam mailbox: email ' . $uid . ' skipped: ' . $e->getMessage());
                    }
                }
                $result['read']++;
                $last = $uid;
            }
            if (!$uids && !$last && $next > 1) $last = $next - 1;
            try { mailbox_command($fp, 'LOGOUT'); } catch (Throwable $e) { /* already closing */ }
        } finally {
            fclose($fp);
            if (isset($validity)) $state = ['validity' => $validity, 'uid' => $last ?? 0] + $state;
        }
        $state['error'] = '';
        return $result;
    } catch (Throwable $e) {
        $state['error'] = $e->getMessage();
        throw $e;
    } finally {
        $state['checked_at'] = date('Y-m-d H:i:s');
        save_settings(['imap_state' => $state]);
        q('SELECT RELEASE_LOCK(?)', ['mj_fetch_replies']);
    }
}

/** Every few minutes while the admin is open (after the page has been sent): read new replies */
function maybe_fetch_replies(): void
{
    try {
        if (!setting('imap_enabled', false)) return;
        $at = (string) (setting('imap_state', [])['checked_at'] ?? '');
        if ($at !== '' && strtotime($at) > time() - 180) return;
        fetch_replies();
    } catch (Throwable $e) {
        error_log('Mangalam mailbox: ' . $e->getMessage());
    }
}

/* ---------- Reading an email (MIME) ---------- */

/** Headers ([name => [values]], names in lower case) and body of an email or of one of its parts */
function mime_split(string $raw): array
{
    $raw = str_replace("\r\n", "\n", $raw);
    $pos = strpos($raw, "\n\n");
    $head = $pos === false ? $raw : substr($raw, 0, $pos);
    $body = $pos === false ? '' : substr($raw, $pos + 2);
    $headers = [];
    foreach (explode("\n", preg_replace('/\n[ \t]+/', ' ', $head)) as $line) {
        $at = strpos($line, ':');
        if ($at === false) continue;
        $headers[strtolower(trim(substr($line, 0, $at)))][] = trim(substr($line, $at + 1));
    }
    return [$headers, $body];
}

/** "=?UTF-8?B?…?=" words in a header, as text */
function mime_words(string $value): string
{
    if (!str_contains($value, '=?')) return $value;
    $decoded = @iconv_mime_decode($value, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8');
    return $decoded !== false ? $decoded : mb_decode_mimeheader($value);
}

/** 'text/plain; charset="utf-8"' → ['' => 'text/plain', 'charset' => 'utf-8'] (and RFC 2231 names) */
function mime_params(string $value): array
{
    $parts = preg_split('/;(?=(?:[^"]*"[^"]*")*[^"]*$)/', $value);
    $out = ['' => strtolower(trim((string) array_shift($parts)))];
    foreach ($parts as $p) {
        if (!str_contains($p, '=')) continue;
        [$k, $v] = array_map('trim', explode('=', $p, 2));
        $k = strtolower($k);
        $v = trim($v, '"');
        if (str_ends_with($k, '*')) {
            $k = rtrim($k, '*');
            if (preg_match("/^([^']*)'[^']*'(.*)$/", $v, $m)) $v = to_utf8(rawurldecode($m[2]), $m[1]);
        }
        $out[$k] = mime_words($v);
    }
    return $out;
}

function to_utf8(string $s, string $charset): string
{
    $charset = strtolower(trim($charset));
    if ($charset !== '' && !in_array($charset, ['utf-8', 'utf8', 'us-ascii', 'ascii'], true)) {
        try {
            $s = mb_convert_encoding($s, 'UTF-8', $charset);
        } catch (Throwable $e) {
            $converted = @iconv($charset, 'UTF-8//IGNORE', $s);
            if ($converted !== false) $s = $converted;
        }
    }
    return mb_check_encoding($s, 'UTF-8') ? $s : mb_convert_encoding($s, 'UTF-8', 'Windows-1252');
}

/** Walks the parts of an email: the first plain text and HTML, and the names of attached files */
function mime_walk(array $headers, string $body, array &$out, int $depth = 0): void
{
    if ($depth > 8) return;
    $type = mime_params($headers['content-type'][0] ?? 'text/plain');
    $mime = $type[''] ?: 'text/plain';
    if (str_starts_with($mime, 'multipart/') && !empty($type['boundary'])) {
        $chunks = explode("\n--" . $type['boundary'], "\n" . $body);
        array_shift($chunks); // the preamble
        foreach ($chunks as $chunk) {
            if (str_starts_with($chunk, '--')) break; // the closing boundary
            [$h, $b] = mime_split(ltrim(preg_replace('/^[ \t]*\n/', '', $chunk), "\n"));
            mime_walk($h, $b, $out, $depth + 1);
        }
        return;
    }
    $disposition = mime_params($headers['content-disposition'][0] ?? '');
    $name = $disposition['filename'] ?? $type['name'] ?? '';
    $encoding = strtolower(trim($headers['content-transfer-encoding'][0] ?? ''));
    $data = $encoding === 'base64' ? (string) base64_decode(preg_replace('/\s+/', '', $body)) : ($encoding === 'quoted-printable' ? quoted_printable_decode($body) : $body);
    if ($mime === 'message/rfc822' || $disposition[''] === 'attachment' || ($name !== '' && !str_starts_with($mime, 'text/'))) {
        // Pictures inside a signature (with a Content-ID) are not attachments the team needs to know about
        if ($name !== '' && !($disposition[''] !== 'attachment' && !empty($headers['content-id']))) $out['attachments'][] = $name;
        elseif ($mime === 'message/rfc822') $out['attachments'][] = 'a forwarded email';
        return;
    }
    if ($mime === 'text/plain' && !isset($out['text'])) $out['text'] = to_utf8($data, $type['charset'] ?? '');
    elseif ($mime === 'text/html' && !isset($out['html'])) $out['html'] = to_utf8($data, $type['charset'] ?? '');
}

/** The words of an HTML email, with its paragraphs, lines and quotes kept apart */
function html_to_text(string $html): string
{
    $html = preg_replace('#<(head|style|script|title)\b[^>]*>.*?</\1>#is', '', $html);
    $html = preg_replace('#<blockquote\b[^>]*>#i', "\n> ", $html);
    $html = preg_replace('#<br\s*/?>#i', "\n", $html);
    $html = preg_replace('#</(p|div|tr|li|h[1-6]|blockquote|table)>#i', "\n", $html);
    $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace('/[ \t\x{00A0}]+/u', ' ', $text);
    $text = preg_replace('/ *\n */', "\n", $text);
    return trim(preg_replace("/\n{3,}/", "\n\n", $text));
}

/** An email as the conversation needs it */
function parse_email(string $raw): array
{
    [$headers, $body] = mime_split($raw);
    $parts = [];
    mime_walk($headers, $body, $parts);
    $h = fn (string $name) => trim(mime_words($headers[$name][0] ?? ''));
    $from = $h('from');
    $address = preg_match('/<([^>]+)>/', $from, $m) ? $m[1] : (preg_match('/[^\s"<>]+@[^\s"<>]+/', $from, $m) ? $m[0] : '');
    $name = trim(preg_replace('/<[^>]*>/', '', $from), " \t\"'");
    if ($name === $address || str_contains($name, '@')) $name = '';
    $ids = fn (string $v) => preg_match_all('/<[^<>\s]+>/', $v, $m) ? $m[0] : [];
    $auto = (strtolower($h('auto-submitted')) !== '' && strtolower($h('auto-submitted')) !== 'no')
        || preg_match('/^(bulk|junk|list|auto_reply)$/i', $h('precedence'))
        || isset($headers['list-unsubscribe']) || isset($headers['list-id']) || isset($headers['x-autoreply']) || isset($headers['x-autorespond'])
        || preg_match('/^(mailer-daemon|postmaster|no-?reply|do-?not-?reply|bounces?)([+.@-]|$)/i', $address);
    $date = strtotime($h('date'));
    return [
        'from' => mb_strtolower(trim($address)),
        'from_name' => mb_substr($name, 0, 120),
        'subject' => mb_substr(preg_replace('/\s+/', ' ', $h('subject')), 0, 200),
        'message_id' => mb_substr($ids($h('message-id'))[0] ?? '', 0, 255),
        'in_reply_to' => $ids($h('in-reply-to'))[0] ?? '',
        'references' => array_slice($ids($h('references')), -20),
        'text' => trim(str_replace(["\r\n", "\r"], "\n", $parts['text'] ?? (isset($parts['html']) ? html_to_text($parts['html']) : ''))),
        'attachments' => $parts['attachments'] ?? [],
        'auto' => (bool) $auto,
        'date' => $date && $date <= time() + 3600 && $date > time() - 90 * 86400 ? date('Y-m-d H:i:s', $date) : date('Y-m-d H:i:s'),
    ];
}

/** What the customer wrote, without the earlier messages their mail app quoted below it, or their signature */
function strip_quoted_reply(string $text): string
{
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    $cuts = [
        '/^[^\n]*Please type your reply above this line[^\n]*$/mi',
        '/^[ \t>]*On\b(?:(?!\n\s*\n).){0,400}?\bwrote:[ \t]*$/msu',         // Gmail, Apple Mail (the line may wrap)
        '/^[ \t]*-{2,}[ \t]*Original Message[ \t]*-{2,}/mi',
        '/^[ \t]*_{8,}[ \t]*$/m',                                           // Outlook's line above "From:"
        '/^[ \t]*\*?From:\*?[ \t].*\n[ \t]*\*?(Sent|Date):\*?/mi',
        '/^[ \t>]*Le\b(?:(?!\n\s*\n).){0,400}?a écrit[ \t]*:[ \t]*$/msu',
        '/^[ \t]*Sent from my (iPhone|iPad|Android|Samsung|mobile)[^\n]*$/mi',
        '/^[ \t]*Get Outlook for (iOS|Android)[^\n]*$/mi',
    ];
    $end = strlen($text);
    foreach ($cuts as $re) {
        if (preg_match($re, $text, $m, PREG_OFFSET_CAPTURE) && $m[0][1] < $end) $end = $m[0][1];
    }
    $text = substr($text, 0, $end);
    $text = preg_replace('/\n-- ?\n.*$/s', '', "\n" . $text); // a signature after "-- "
    $lines = explode("\n", rtrim($text));
    while ($lines && (trim(end($lines)) === '' || str_starts_with(ltrim(end($lines)), '>'))) array_pop($lines);
    return trim(preg_replace("/\n{3,}/", "\n\n", implode("\n", $lines)));
}
