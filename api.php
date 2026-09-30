<?php
/* Mangalam Jewellers — the website's forms: product enquiries and the contact form (→ Enquiries),
 * appointment requests (→ Appointments) and the newsletter (→ Subscribers). Answers in JSON. */
require __DIR__ . '/app/bootstrap.php';
require_installed();

if (!is_post()) json_response(['ok' => false, 'error' => 'Send the form to this address.'], 405);

// Only the website's own pages may send forms here
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '' && parse_url($origin, PHP_URL_HOST) !== parse_url('http://' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST)) {
    json_response(['ok' => false, 'error' => 'This form can only be sent from the Mangalam website.'], 403);
}

try {
    switch (input('form')) {
        case 'enquiry':
            [$name, $email, $phone] = visitor_details();
            $message = (string) input('message');
            if ($message === '') fail('Please write your message.');
            if (mb_strlen($message) > 4000) fail('Please keep your message under 4,000 characters.');
            $product = input('product') !== '' ? row('SELECT id, name FROM products WHERE slug = ?', [input('product')]) : null;
            $topic = in_array(input('topic'), ENQUIRY_TOPICS, true) ? input('topic') : 'General enquiry';
            insert('enquiries', [
                'name' => $name, 'email' => $email, 'phone' => $phone, 'message' => $message,
                'source' => $product ? 'product' : 'contact', 'product_id' => $product['id'] ?? null, 'topic' => $product ? '' : $topic,
            ]);
            if (setting('notify_enquiry', true)) notify_team('New enquiry from ' . $name, ($product ? 'About: ' . $product['name'] : 'Topic: ' . $topic) . "\n\n$message\n\n$name · $email · $phone");
            json_response(['ok' => true]);

        case 'appointment':
            [$name, $email, $phone] = visitor_details(true);
            $date = to_date(input('date'));
            if (!$date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) input('date'))) fail('Please choose the day you would like to visit.');
            if ($date < today()) fail('Please choose a day from today onwards.');
            $interest = in_array(input('interest'), INTERESTS, true) ? input('interest') : INTERESTS[0];
            insert('appointments', ['name' => $name, 'email' => $email, 'phone' => $phone, 'date' => $date->format('Y-m-d'), 'interest' => $interest, 'status' => 'pending', 'source' => 'website']);
            if (setting('notify_appointment', true)) notify_team('Appointment request from ' . $name, "$interest on " . $date->format('l j F Y') . "\n\n$name · $phone · $email");
            json_response(['ok' => true]);

        case 'newsletter':
            $email = mb_strtolower((string) input('email'));
            if (!valid_email($email)) fail('Please enter a valid email address.');
            $existing = row('SELECT id, status FROM subscribers WHERE email = ?', [$email]);
            if (!$existing) {
                insert('subscribers', ['email' => $email, 'source' => in_array(input('source'), SUBSCRIBER_SOURCES, true) ? input('source') : 'Website footer']);
                if (setting('notify_subscriber', false)) notify_team('New newsletter subscriber', $email);
            } elseif ($existing['status'] === 'unsubscribed') {
                update('subscribers', ['status' => 'subscribed', 'unsubscribed_at' => null], ['id' => $existing['id']]);
            }
            json_response(['ok' => true]);
    }
    fail('This form is not one the website knows.');
} catch (UserError $e) {
    json_response(['ok' => false, 'error' => $e->getMessage()], 422);
} catch (Throwable $e) {
    error_log('Mangalam form: ' . $e);
    json_response(['ok' => false, 'error' => 'Something went wrong on our side. Please try again, or call us.'], 500);
}

/** Name, email and phone from a form, checked */
function visitor_details(bool $phoneRequired = false): array
{
    $name = (string) input('name');
    $email = mb_strtolower((string) input('email'));
    $phone = (string) input('phone');
    if ($name === '') fail('Please tell us your name.');
    if (mb_strlen($name) > 120) fail('Please shorten your name.');
    if (!valid_email($email)) fail('Please enter a valid email address.');
    if ($phoneRequired && $phone === '') fail('Please give a phone number so our concierge can confirm.');
    if ($phone !== '' && !preg_match('/^[\d\s+()\-.]{6,40}$/', $phone)) fail('Please check the phone number.');
    return [$name, $email, $phone];
}
