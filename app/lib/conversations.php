<?php
/* Conversations with customers. Every enquiry is a conversation (enquiries, then enquiry_replies) that the team
 * carries on from Enquiries — by email, WhatsApp, phone or with notes for each other — and the customer from their
 * private page (conversation.html?t=…) or simply by replying to an email (read back by fetch_replies()).
 * Also the email templates, and the emails the website sends by itself. */

/** How a message travelled: [name, icon] */
const CHANNELS = [
    'email' => ['Email', 'mail'], 'whatsapp' => ['WhatsApp', 'whatsapp'], 'website' => ['Conversation page', 'globe'],
    'phone' => ['Phone call', 'phone'], 'note' => ['Note for the team', 'sticky-note'],
];

/** What can be put in a template ({first_name} …), with what it stands for */
const TEMPLATE_FIELDS = [
    'first_name' => 'Their first name', 'name' => 'Their full name', 'product' => 'The piece they asked about', 'topic' => 'What they wrote about',
    'ref' => 'The conversation’s reference', 'conversation' => 'The link to their conversation page', 'my_name' => 'Your name',
    'store' => 'The store’s name', 'store_phone' => 'The store’s phone', 'store_whatsapp' => 'The store’s WhatsApp', 'store_email' => 'The store’s email',
    'address' => 'The store’s address', 'hours' => 'Opening hours',
    'date' => 'Appointment: the day', 'time' => 'Appointment: the time', 'interest' => 'Appointment: what it is for', 'consultant' => 'Appointment: the consultant',
];

/** Where a template's button can go */
const TEMPLATE_LINKS = [
    'conversation' => 'Their conversation page', 'product' => 'The piece’s page', 'whatsapp' => 'A WhatsApp chat with the store',
    'maps' => 'Directions to the store', 'contact' => 'The Contact page', 'offers' => 'The Offers page', 'jewellery' => 'All jewellery',
];

/** The emails the website sends by itself: key => [name, when] */
const AUTO_TEMPLATES = [
    'enquiry_received' => ['Enquiry received', 'To the customer, as soon as they send an enquiry from a product page or the contact form'],
    'appointment_received' => ['Appointment requested', 'To the customer, when they ask for an appointment on the website'],
    'appointment_confirmed' => ['Appointment confirmed', 'To the customer, when an appointment is set to Confirmed in Appointments'],
];

/** The templates a new installation starts with: [name, auto_key, subject, heading, body, button, button_link] */
const DEFAULT_TEMPLATES = [
    ['Enquiry received', 'enquiry_received', '', 'Thank you, *{first_name}*.',
        "Your message has reached our concierge at Mangalam House, and one of our consultants will write back to you within one business day.\n\nEverything we write to each other is kept on your private conversation page — you can read our reply there and write to us again at any time. Or simply reply to this email.\n\nIf it is urgent, call us on {store_phone}.",
        'View your conversation', 'conversation'],
    ['Appointment requested', 'appointment_received', 'Your visit to {store}', 'We look forward to *welcoming you*.',
        "Thank you, {first_name}. We have your request for a private consultation — {interest} — on {date}.\n\nOur concierge will call you shortly to confirm the time and to ask what you would like to see, so the pieces are ready before you arrive.\n\n{store}, {address}\n{hours}",
        'Get directions', 'maps'],
    ['Appointment confirmed', 'appointment_confirmed', 'Your visit is confirmed — {date}', 'Your visit is *confirmed*.',
        "Dear {first_name}, we are delighted to confirm your private consultation:\n\n{date}, {time}\nWith {consultant}\n\nWe will have pieces ready for you. If your plans change, simply reply to this email or call us on {store_phone}.\n\n{store}, {address}",
        'Get directions', 'maps'],
    ['Invite to visit', null, '', 'An invitation to *Mangalam House*',
        "Thank you for writing to Mangalam, {first_name}. We would love to show you the {product} in person — visit us at {address}, open {hours}.\n\nShall I book a private viewing for you? Tell me a day and time that suits you, and the piece will be waiting.",
        'Reply to book a viewing', 'conversation'],
    ['Bridal consultation', null, '', 'Congratulations, *{first_name}*!',
        "Our bridal consultations are private and unhurried — about ninety minutes with one of our designers, and your family is most welcome.\n\nTell us two or three dates that suit you, and a little about the outfits and the occasions, and we will prepare pieces before you arrive.",
        '', 'conversation'],
    ['Price & availability', null, '', 'About the *{product}*',
        "Thank you for asking about the {product}, {first_name}. It is available to view at Mangalam House.\n\nOur gold is priced at the day's rate, so the final price depends on the weight and the rate on the day you choose it — I will be happy to send you an exact quote today. Would you like it in a particular size, or with any changes?",
        'View the piece', 'product'],
    ['Bespoke commission', null, '', 'Let us make it *yours*.',
        "What a lovely idea, {first_name}. Our karigars make every bespoke piece by hand in our Surat atelier, usually in four to six weeks.\n\nIf you have a photograph, a sketch or a family piece you would like to reimagine, simply reply with it. We will suggest designs and an estimate, and then meet you to settle every detail.",
        '', 'conversation'],
    ['Care & repairs', null, '', 'We will *take care* of it.',
        "Hello {first_name}, cleaning, polishing and small repairs are free for the life of every Mangalam piece. Bring it to Mangalam House any day — {hours} — and our karigars will look at it while you wait.\n\nFor anything bigger, we will tell you exactly what it needs and how long it will take before we begin.",
        'Get directions', 'maps'],
    ['Follow-up', null, '', 'Just checking in, *{first_name}*',
        "I wanted to follow up on your enquiry about the {product}. Do you have any questions I can answer, or would you like to see it in person?\n\nI am happy to send more photographs, a video or an exact quote — just reply to this email.",
        '', 'conversation'],
    ['Thank you for visiting', null, '', 'Thank you for *visiting us*.',
        "It was a pleasure to welcome you to Mangalam House, {first_name}. If you would like to see any of the pieces again, or think it over with photographs, simply reply and I will send them.\n\nWe hope to see you again soon.",
        'Explore the collection', 'jewellery'],
];

/* ---------- Conversations ---------- */

/** "MJ-0012": in every email subject, so replies find their way back */
function enquiry_ref(int $id): string
{
    return 'MJ-' . str_pad((string) $id, 4, '0', STR_PAD_LEFT);
}

function new_token(): string
{
    return bin2hex(random_bytes(16));
}

/** The customer's private link (made the first time it is needed) */
function enquiry_token(array $en): string
{
    if (!empty($en['token'])) return (string) $en['token'];
    $token = new_token();
    update('enquiries', ['token' => $token], ['id' => $en['id']]);
    return $token;
}

function conversation_url(array $en): string
{
    return site_url('conversation.html?t=' . enquiry_token($en));
}

const ENQUIRY_SELECT = 'SELECT e.*, p.name AS product_name, p.slug AS product_slug FROM enquiries e LEFT JOIN products p ON p.id = e.product_id';

function enquiry_by_id(int $id): ?array
{
    return row(ENQUIRY_SELECT . ' WHERE e.id = ?', [$id]);
}

function enquiry_by_token(string $token): ?array
{
    return preg_match('/^[a-f0-9]{32}$/', $token) ? row(ENQUIRY_SELECT . ' WHERE e.token = ?', [$token]) : null;
}

/** What it is about: the piece, the email's subject or the topic */
function enquiry_about(array $en): string
{
    return (string) (($en['product_name'] ?? '') ?: (($en['subject'] ?? '') ?: ($en['topic'] ?: 'General enquiry')));
}

/** The subject the conversation's emails carry, ending in its reference */
function enquiry_subject(array $en): string
{
    $subject = trim(preg_replace('/^\s*((re|fwd?|aw|sv)\s*:\s*)+/i', '', (string) ($en['subject'] ?? '')));
    if ($subject === '') $subject = !empty($en['product_name']) ? 'Your enquiry about the ' . $en['product_name'] : 'Your enquiry to ' . setting('store_name', 'Mangalam Jewellers');
    return with_ref($subject, (int) $en['id']);
}

function with_ref(string $subject, int $id): string
{
    $subject = trim(preg_replace('/\s*\[MJ-\d+\]/i', '', $subject));
    return $id ? $subject . ' [' . enquiry_ref($id) . ']' : $subject;
}

/** Every message after the first, oldest first */
function conversation_messages(int $enquiryId): array
{
    return rows('SELECT * FROM enquiry_replies WHERE enquiry_id = ? ORDER BY created_at, id', [$enquiryId]);
}

/** Adds a message, and moves the conversation along: the customer writing makes it New, the team answering Replied */
function add_message(array $en, array $data): int
{
    $id = insert('enquiry_replies', $data + ['enquiry_id' => (int) $en['id']]);
    $channel = $data['channel'] ?? 'email';
    if ($channel !== 'note') {
        $patch = ['last_at' => $data['created_at'] ?? date('Y-m-d H:i:s')];
        if (empty($data['is_auto'])) $patch['status'] = ($data['direction'] ?? 'out') === 'in' ? 'new' : 'replied';
        update('enquiries', $patch, ['id' => (int) $en['id']]);
    }
    return $id;
}

/** A phone number as WhatsApp needs it: country code and digits ("098250 12345" → 919825012345) */
function whatsapp_number(string $phone): string
{
    $digits = preg_replace('/\D/', '', $phone);
    if (strlen($digits) === 11 && $digits[0] === '0') $digits = substr($digits, 1);
    if (strlen($digits) === 10) $digits = '91' . $digits;
    return strlen($digits) >= 11 ? $digits : '';
}

function whatsapp_url(string $phone, string $text = ''): string
{
    $number = whatsapp_number($phone);
    return $number === '' ? '' : 'https://wa.me/' . $number . ($text !== '' ? '?text=' . rawurlencode($text) : '');
}

/* ---------- Templates ---------- */

/** Reply templates in their order (or the automatic emails) */
function message_templates(bool $auto = false): array
{
    return rows('SELECT * FROM message_templates WHERE auto_key IS ' . ($auto ? 'NOT ' : '') . 'NULL ORDER BY sort_order, id');
}

function auto_template(string $key): ?array
{
    return row('SELECT * FROM message_templates WHERE auto_key = ?', [$key]);
}

/** Puts the templates a new installation starts with into an empty table (once) */
function seed_templates(): void
{
    if (setting('templates_seeded', false)) return;
    if (!(int) val('SELECT COUNT(*) FROM message_templates')) {
        foreach (DEFAULT_TEMPLATES as $i => [$name, $key, $subject, $heading, $body, $button, $link]) {
            insert('message_templates', ['name' => $name, 'auto_key' => $key, 'subject' => $subject, 'heading' => $heading, 'body' => $body,
                'button' => $button, 'button_link' => $link, 'sort_order' => $i + 1]);
        }
    }
    save_settings(['templates_seeded' => true]);
}

/** The values for {first_name} and the rest; $en may be an enquiry, an appointment or a sample */
function template_vars(?array $en, ?array $me = null, array $extra = []): array
{
    $name = trim((string) ($en['name'] ?? ''));
    $store = (string) setting('store_name', 'Mangalam Jewellers');
    return $extra + [
        'first_name' => $name !== '' ? explode(' ', $name)[0] : 'there',
        'name' => $name,
        'product' => ($en['product_name'] ?? '') ?: 'piece',
        'topic' => ($en['topic'] ?? '') ?: (($en['subject'] ?? '') ?: 'your enquiry'),
        'ref' => !empty($en['id']) ? enquiry_ref((int) $en['id']) : '',
        'conversation' => !empty($en['id']) ? conversation_url($en) : site_url(),
        'my_name' => $me['name'] ?? 'The concierge',
        'store' => $store,
        'store_phone' => (string) setting('phone', ''),
        'store_whatsapp' => (string) (setting('whatsapp', '') ?: setting('phone', '')),
        'store_email' => (string) setting('email', ''),
        'address' => store_address(),
        'hours' => hours_short(),
    ];
}

/** "{first_name}" → "Priya" (names it does not know stay as they are) */
function fill_template(string $text, array $vars): string
{
    return preg_replace_callback('/\{([a-z_]+)\}/', fn ($m) => array_key_exists($m[1], $vars) ? (string) $vars[$m[1]] : $m[0], $text);
}

/** Where a template's button goes, for this conversation ('' when it cannot go there) */
function template_link(string $kind, ?array $en): string
{
    switch ($kind) {
        case 'conversation': return !empty($en['id']) ? conversation_url($en) : '';
        case 'product': return !empty($en['product_slug']) ? site_url('product.html?slug=' . rawurlencode($en['product_slug'])) : '';
        case 'whatsapp': return whatsapp_url((string) (setting('whatsapp', '') ?: setting('phone', '')), !empty($en['id']) ? 'Hello Mangalam — about ' . enquiry_ref((int) $en['id']) : 'Hello Mangalam');
        case 'maps': return (string) setting('maps_url', '') ?: site_url('contact.html');
        case 'contact': return site_url('contact.html');
        case 'offers': return site_url('offers.html');
        case 'jewellery': return site_url('jewellery.html');
    }
    return '';
}

/** A template's button for this conversation: [label, url], or null */
function template_button(array $t, ?array $en, array $vars): ?array
{
    $url = trim((string) $t['button']) !== '' ? template_link((string) $t['button_link'], $en) : '';
    return $url !== '' ? [fill_template((string) $t['button'], $vars), $url] : null;
}

/* ---------- Emails in a conversation ---------- */

/** The piece, for the card in an email */
function email_product(array $en): ?array
{
    $p = !empty($en['product_slug']) ? product_by_slug((string) $en['product_slug'], false) : null;
    if (!$p) return null;
    return [
        'name' => $p['name'], 'meta' => category_name($p['category']) . ' · ' . $p['purity'] . ' ' . mb_strtolower($p['metal']),
        'price' => product_price_text($p), 'image' => site_url($p['thumb']), 'url' => site_url('product.html?slug=' . rawurlencode($p['slug'])),
    ];
}

/** The enquiry at a glance, for the card in the email that confirms it arrived */
function enquiry_details(array $en): array
{
    $sent = to_date($en['created_at'] ?? 'now');
    return ['title' => 'Your enquiry', 'rows' => [
        ['Reference', enquiry_ref((int) $en['id'])],
        ['Received', $sent ? $sent->format('l j F Y, g:i A') : ''],
        ['About', enquiry_about($en)],
        ['We will reply to', implode("\n", array_filter([(string) ($en['email'] ?? ''), (string) ($en['phone'] ?? '')]))],
    ]];
}

/**
 * The email for a message in the conversation, as email_html() and email_text() take it.
 * $msg: subject, heading, body. $opts: author (the team member: the signature), product (show the piece),
 * button ([label, url]; false = none; missing = "View our conversation"), quote (the customer's last message below), eyebrow,
 * details (true: the card with the enquiry's reference, date and subject).
 */
function conversation_email(array $en, array $msg, array $opts = []): array
{
    $store = (string) setting('store_name', 'Mangalam Jewellers');
    $url = conversation_url($en);
    $quote = null;
    if (!empty($opts['quote'])) {
        $last = empty($en['sample']) ? row("SELECT message, created_at FROM enquiry_replies WHERE enquiry_id = ? AND direction = 'in' AND channel <> 'note' ORDER BY created_at DESC, id DESC LIMIT 1", [$en['id']]) : null;
        if ($last) $quote = ['Your message', long_date($last['created_at']), $last['message']];
        elseif (trim((string) $en['message']) !== '') $quote = ['Your message', long_date($en['created_at']), $en['message']];
    }
    $button = $opts['button'] ?? ['View our conversation', $url];
    $links = [];
    $wa = whatsapp_url((string) setting('whatsapp', ''), 'Hello Mangalam — about ' . enquiry_ref((int) $en['id']));
    if ($wa !== '' && (!$button || $button[1] !== $wa)) $links[] = ['WhatsApp us', $wa];
    if (setting('phone', '') !== '') $links[] = ['Call ' . setting('phone', ''), 'tel:' . preg_replace('/[^\d+]/', '', (string) setting('phone', ''))];
    if ($button && $button[1] !== $url) $links[] = ['Your conversation', $url];
    return [
        'subject' => $msg['subject'],
        'eyebrow' => ($opts['eyebrow'] ?? (($en['source'] ?? '') === 'admin' ? 'Your conversation' : 'Your enquiry')) . ' · ' . enquiry_ref((int) $en['id']),
        'heading' => (string) ($msg['heading'] ?? ''),
        'body' => (string) $msg['body'],
        'details' => !empty($opts['details']) ? enquiry_details($en) : null,
        'product' => !empty($opts['product']) ? email_product($en) : null,
        'button' => $button ?: null,
        'links' => $links,
        'signature' => [!empty($opts['author']) ? $opts['author'] : 'The concierge', $store],
        'quote' => $quote,
        'marker' => true,
        'footnote' => 'This is your private conversation with ' . $store . ' (' . enquiry_ref((int) $en['id']) . '). Reply to this email, or open your conversation page, to write to us.',
    ];
}

/**
 * Emails a message of the conversation to the customer, threaded with the earlier ones in their mailbox.
 * @return array [sent, the email's Message-ID]
 */
function send_conversation_email(array $en, array $msg, array $opts = []): array
{
    if (!valid_email((string) $en['email'])) { mail_error('There is no email address for ' . $en['name'] . '.'); return [false, '']; }
    $m = conversation_email($en, $msg, $opts);
    $ids = array_values(array_filter(array_merge([(string) ($en['message_id'] ?? '')],
        array_column(rows("SELECT message_id FROM enquiry_replies WHERE enquiry_id = ? AND message_id <> '' ORDER BY created_at, id", [$en['id']]), 'message_id'))));
    $messageId = new_message_id();
    $store = (string) setting('store_name', 'Mangalam Jewellers');
    $sent = send_mail((string) $en['email'], $msg['subject'], email_text($m), reply_address(), [], false, [
        'html' => email_html($m), 'message_id' => $messageId,
        'in_reply_to' => $ids ? end($ids) : '', 'references' => implode(' ', array_slice($ids, -10)),
        // "Nikunj · Mangalam Jewellers": the customer sees who is writing
        'from_name' => !empty($opts['author']) && empty($opts['auto']) ? explode(' ', trim($opts['author']))[0] . ' · ' . $store : $store,
    ]);
    return [$sent, $sent ? $messageId : ''];
}

/** To the customer, as soon as their enquiry arrives (Email templates › Enquiry received) */
function send_enquiry_received(array $en): void
{
    $t = auto_template('enquiry_received');
    if (!$t || !$t['is_on'] || !setting('mail_enabled', false) || !valid_email((string) $en['email'])) return;
    $vars = template_vars($en);
    $subject = with_ref(fill_template((string) $t['subject'], $vars) ?: enquiry_subject($en), (int) $en['id']);
    $msg = ['subject' => $subject, 'heading' => fill_template((string) $t['heading'], $vars), 'body' => fill_template((string) $t['body'], $vars)];
    [$sent, $messageId] = send_conversation_email($en, $msg, ['auto' => true, 'details' => true, 'product' => true, 'quote' => true, 'button' => template_button($t, $en, $vars) ?: false]);
    if ($sent) add_message($en, ['direction' => 'out', 'channel' => 'email', 'is_auto' => 1, 'author' => 'Automatic email', 'subject' => $subject,
        'message' => $msg['body'], 'emailed' => 1, 'message_id' => $messageId]);
}

/** The email about an appointment, from its template (as email_html() takes it) */
function appointment_email(array $t, array $a): array
{
    $vars = template_vars(['name' => $a['name']], null, [
        'date' => (new DateTimeImmutable($a['date']))->format('l j F Y'), 'time' => $a['time'] ?: 'at a time we will confirm with you',
        'interest' => mb_strtolower($a['interest']), 'consultant' => $a['consultant'] ?: 'one of our consultants',
    ]);
    $store = (string) setting('store_name', 'Mangalam Jewellers');
    $wa = whatsapp_url((string) setting('whatsapp', ''), 'Hello Mangalam — about my visit on ' . $vars['date']);
    $confirmed = $t['auto_key'] === 'appointment_confirmed';
    $maps = template_link('maps', null);
    return [
        'subject' => fill_template((string) $t['subject'], $vars), 'eyebrow' => 'Your visit · ' . (new DateTimeImmutable($a['date']))->format('j M Y'),
        'heading' => fill_template((string) $t['heading'], $vars), 'body' => fill_template((string) $t['body'], $vars),
        'details' => ['title' => $confirmed ? 'Your visit is confirmed' : 'Your appointment request', 'rows' => [
            ['Date', $vars['date']],
            ['Time', $a['time'] ?: 'Our concierge will call to confirm'],
            ['For', $a['interest']],
            ['With', $a['consultant'] ?: ($confirmed ? 'One of our consultants' : '')],
            ['Where', $store . "\n" . store_address(), $maps],
            ['Status', $confirmed ? 'Confirmed' : 'Requested — awaiting confirmation'],
        ]],
        'button' => template_button($t, null, $vars),
        'links' => array_values(array_filter([$wa !== '' ? ['WhatsApp us', $wa] : null, setting('phone', '') !== '' ? ['Call ' . setting('phone', ''), 'tel:' . preg_replace('/[^\d+]/', '', (string) setting('phone', ''))] : null])),
        'signature' => ['The concierge', $store],
        'footnote' => 'You are receiving this because you asked ' . $store . ' for an appointment. Reply to this email to change it.',
    ];
}

/** To the customer about their appointment ($key: appointment_received or appointment_confirmed) */
function send_appointment_email(string $key, array $a): bool
{
    $t = auto_template($key);
    if (!$t || !$t['is_on'] || !setting('mail_enabled', false) || !valid_email((string) $a['email'])) return false;
    $m = appointment_email($t, $a);
    return send_mail((string) $a['email'], $m['subject'], email_text($m), (string) setting('email', ''), [], false, ['html' => email_html($m)]);
}

/* ---------- Previews (Email templates, and the composer's Preview) ---------- */

/** An invented customer for previews and test emails, asking about a real piece */
function sample_enquiry(): array
{
    $p = featured_products(false)[0] ?? (products(false)[0] ?? null);
    return ['id' => 128, 'sample' => true, 'token' => str_repeat('0', 32), 'name' => 'Priya Shah', 'email' => 'priya@example.com', 'phone' => '+91 98765 43210',
        'source' => 'product', 'topic' => '', 'subject' => '', 'message_id' => '', 'created_at' => date('Y-m-d H:i:s', strtotime('-1 day')),
        'message' => 'Hello, I love this piece. Could I see it this weekend, and is it available in a slightly shorter length?',
        'product_name' => $p['name'] ?? '', 'product_slug' => $p['slug'] ?? ''];
}

function sample_appointment(): array
{
    return ['name' => 'Priya Shah', 'email' => 'priya@example.com', 'date' => date('Y-m-d', strtotime('+3 days')), 'time' => '11:30 AM',
        'interest' => 'Bridal jewellery', 'consultant' => (string) val("SELECT name FROM users WHERE status = 'active' AND role <> 'Editor' ORDER BY FIELD(role, 'Owner', 'Manager', 'Sales'), name LIMIT 1")];
}

/** A template filled in for the invented customer: [subject, the email as email_html() takes it] */
function template_sample(array $t, ?array $me): array
{
    if (str_starts_with((string) $t['auto_key'], 'appointment')) {
        $m = appointment_email($t, sample_appointment());
        return [$m['subject'], $m];
    }
    $en = sample_enquiry();
    $vars = template_vars($en, $me);
    $subject = with_ref(fill_template((string) $t['subject'], $vars) ?: enquiry_subject($en), (int) $en['id']);
    $auto = !empty($t['auto_key']);
    $m = conversation_email($en, ['subject' => $subject, 'heading' => fill_template((string) $t['heading'], $vars), 'body' => fill_template((string) $t['body'], $vars)],
        ['author' => $auto ? '' : ($me['name'] ?? ''), 'details' => $auto, 'product' => true, 'quote' => true, 'button' => template_button($t, $en, $vars) ?: ($auto ? false : null)]);
    return [$subject, $m];
}

/* ---------- Replies read from the mailbox ---------- */

/** The store's own addresses: emails from them are not customers' */
function our_addresses(): array
{
    $c = mail_config();
    return array_values(array_filter(array_map('mb_strtolower', [$c['from'], $c['user'], mailbox_config()['user'], (string) setting('email', '')]), 'valid_email'));
}

/**
 * An email read from the mailbox: a reply joins its conversation (found by the email it answers, or by the [MJ-12]
 * in its subject when it comes from the customer's address); other customers' emails start a conversation when
 * "Also add new emails" is on. Automatic emails (out of office, newsletters, bounces) are left out.
 * @return string 'added', 'started' or '' (left out)
 */
function take_email(array $mail): string
{
    if ($mail['from'] === '' || $mail['auto'] || in_array($mail['from'], our_addresses(), true)) return '';
    if ($mail['message_id'] !== '' && (val('SELECT 1 FROM enquiry_replies WHERE message_id = ? LIMIT 1', [$mail['message_id']]) || val('SELECT 1 FROM enquiries WHERE message_id = ? LIMIT 1', [$mail['message_id']]))) return '';
    $en = null;
    $answers = array_values(array_unique(array_filter(array_merge([$mail['in_reply_to']], $mail['references']))));
    if ($answers) {
        $id = val('SELECT enquiry_id FROM enquiry_replies WHERE message_id IN (' . placeholders($answers) . ') ORDER BY id DESC LIMIT 1', $answers)
            ?: val('SELECT id FROM enquiries WHERE message_id IN (' . placeholders($answers) . ') ORDER BY id DESC LIMIT 1', $answers);
        if ($id) $en = enquiry_by_id((int) $id);
    }
    // Only the customer's own address may use the reference in the subject (anyone could type it)
    if (!$en && preg_match('/\[MJ-(\d+)\]/i', $mail['subject'], $m)) {
        $found = enquiry_by_id((int) $m[1]);
        if ($found && mb_strtolower((string) $found['email']) === $mail['from']) $en = $found;
    }
    $text = strip_quoted_reply($mail['text']) ?: trim($mail['text']);
    if ($mail['attachments']) $text .= "\n\n[" . plural(count($mail['attachments']), 'attachment') . ' in the mailbox: ' . implode(', ', $mail['attachments']) . ']';
    if ($text === '') $text = '(An email with no text.)';
    $text = mb_substr($text, 0, 20000);
    if ($en) {
        $author = $mail['from_name'] ?: (string) $en['name'];
        if ($mail['from'] !== mb_strtolower((string) $en['email'])) $author .= ' <' . $mail['from'] . '>';
        add_message($en, ['direction' => 'in', 'channel' => 'email', 'author' => mb_substr($author, 0, 120), 'subject' => $mail['subject'],
            'message' => $text, 'message_id' => $mail['message_id'], 'created_at' => $mail['date']]);
        return 'added';
    }
    if (!setting('imap_new_threads', false)) return '';
    insert('enquiries', [
        'token' => new_token(), 'name' => $mail['from_name'] ?: ucfirst(explode('@', $mail['from'])[0]), 'email' => $mail['from'], 'source' => 'email',
        'subject' => $mail['subject'] ?: '(No subject)', 'message' => $text, 'message_id' => $mail['message_id'], 'status' => 'new',
        'created_at' => $mail['date'], 'last_at' => $mail['date'],
    ]);
    return 'started';
}
