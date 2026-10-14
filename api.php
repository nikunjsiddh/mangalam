<?php
/* Mangalam Jewellers — the website's forms: product enquiries and the contact form (→ Enquiries),
 * a customer writing on their conversation page (→ the same enquiry), appointment requests (→ Appointments)
 * and the newsletter (→ Subscribers). Answers in JSON. */
require __DIR__ . '/app/bootstrap.php';
require_installed();
upgrade_schema();

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
            $id = insert('enquiries', [
                'token' => new_token(), 'name' => $name, 'email' => $email, 'phone' => $phone, 'message' => $message,
                'source' => $product ? 'product' : 'contact', 'product_id' => $product['id'] ?? null, 'topic' => $product ? '' : $topic, 'last_at' => date('Y-m-d H:i:s'),
            ]);
            $en = enquiry_by_id($id);
            if (setting('notify_enquiry', true)) notify_team('New enquiry from ' . $name, $product ? 'A visitor wrote from the page of the ' . $product['name'] . '.' : 'A visitor wrote from the contact form.', $email, ['Open the enquiry', 'enquiries.html?open=' . $id], [
                'eyebrow' => 'For the team · New enquiry', 'heading' => 'New enquiry from *' . $name . '*', 'message' => ['Their message', $message],
                'details' => ['title' => 'Enquiry ' . enquiry_ref($id), 'rows' => array_merge(visitor_rows($name, $email, $phone), [
                    ['About', $product ? $product['name'] : $topic], ['Received', date('l j F Y, g:i A')],
                ])],
                'links' => visitor_links($email, $phone, enquiry_subject($en)),
            ]);
            send_enquiry_received($en);
            // The visitor's own conversation page: where our reply appears, and where they can write again
            json_response(['ok' => true, 'conversation' => 'conversation.html?t=' . $en['token']]);

        case 'conversation':
            $en = enquiry_by_token((string) input('t'));
            if (!$en) fail('This conversation could not be found. Please write to us from the Contact page.');
            $message = (string) input('message');
            if ($message === '') fail('Please write your message.');
            if (mb_strlen($message) > 4000) fail('Please keep your message under 4,000 characters.');
            // A second click on Send, or the page sent twice, does not add it again
            if (val("SELECT 1 FROM enquiry_replies WHERE enquiry_id = ? AND direction = 'in' AND message = ? AND created_at > DATE_SUB(NOW(), INTERVAL 10 MINUTE)", [$en['id'], $message])) json_response(['ok' => true]);
            add_message($en, ['direction' => 'in', 'channel' => 'website', 'author' => $en['name'], 'message' => $message]);
            if ($en['status'] === 'archived') update('enquiries', ['status' => 'new'], ['id' => $en['id']]);
            if (setting('notify_enquiry', true)) notify_team($en['name'] . ' wrote again', 'A new message on their conversation page.', (string) $en['email'], ['Open the conversation', 'enquiries.html?open=' . $en['id']], [
                'eyebrow' => 'For the team · ' . enquiry_ref((int) $en['id']), 'heading' => '*' . $en['name'] . '* wrote again', 'message' => ['Their message', $message],
                'details' => ['title' => 'Conversation ' . enquiry_ref((int) $en['id']), 'rows' => array_merge(visitor_rows((string) $en['name'], (string) $en['email'], (string) $en['phone']), [
                    ['About', enquiry_about($en)], ['Received', date('l j F Y, g:i A')],
                ])],
                'links' => visitor_links((string) $en['email'], (string) $en['phone'], enquiry_subject($en)),
            ]);
            json_response(['ok' => true]);

        case 'appointment':
            [$name, $email, $phone] = visitor_details(true);
            $date = to_date(input('date'));
            if (!$date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) input('date'))) fail('Please choose the day you would like to visit.');
            if ($date < today()) fail('Please choose a day from today onwards.');
            $interest = in_array(input('interest'), INTERESTS, true) ? input('interest') : INTERESTS[0];
            $appointment = ['name' => $name, 'email' => $email, 'phone' => $phone, 'date' => $date->format('Y-m-d'), 'time' => '', 'interest' => $interest, 'consultant' => '', 'status' => 'pending', 'source' => 'website'];
            insert('appointments', $appointment);
            if (setting('notify_appointment', true)) notify_team('Appointment request from ' . $name, 'Call them to confirm the time, then set the appointment to Confirmed — they will receive the confirmation email.', $email, ['Open the calendar', 'appointments.html?month=' . $date->format('Y-m')], [
                'eyebrow' => 'For the team · Appointment request', 'heading' => '*' . $name . '* would like to visit',
                'details' => ['title' => 'Requested visit', 'rows' => array_merge([['Date', $date->format('l j F Y')], ['For', $interest]], visitor_rows($name, $email, $phone), [
                    ['Status', 'Waiting to be confirmed'],
                ])],
                'links' => visitor_links($email, $phone, 'Your visit to ' . setting('store_name', 'Mangalam Jewellers')),
            ]);
            send_appointment_email('appointment_received', $appointment);
            json_response(['ok' => true]);

        case 'newsletter':
            $email = mb_strtolower((string) input('email'));
            if (!valid_email($email)) fail('Please enter a valid email address.');
            $existing = row('SELECT id, status FROM subscribers WHERE email = ?', [$email]);
            if (!$existing) {
                insert('subscribers', ['email' => $email, 'source' => in_array(input('source'), SUBSCRIBER_SOURCES, true) ? input('source') : 'Website footer']);
                if (setting('notify_subscriber', false)) notify_team('New newsletter subscriber', 'Someone joined the newsletter.', '', ['Open Subscribers', 'subscribers.html'], [
                    'eyebrow' => 'For the team · Newsletter', 'heading' => 'A new *subscriber*',
                    'details' => ['title' => 'Subscriber', 'rows' => [['Email', $email, 'mailto:' . $email], ['Joined from', in_array(input('source'), SUBSCRIBER_SOURCES, true) ? input('source') : 'Website footer'], ['Joined', date('l j F Y, g:i A')]]],
                ]);
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
