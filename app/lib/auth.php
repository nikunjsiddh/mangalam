<?php
/* Signing in to the admin, sessions, roles and permissions, and protection against forged requests. */

const ROLES = [
    'Owner' => 'Everything, including settings and the team',
    'Manager' => 'Catalogue, website content, customers and offers',
    'Editor' => 'Catalogue and website content',
    'Sales' => 'Enquiries and appointments',
];

const PERMISSIONS = [
    'dashboard' => 'See the dashboard',
    'products.edit' => 'Add and edit products',
    'products.delete' => 'Delete products',
    'content.edit' => 'Edit the homepage, pages and journal',
    'media.edit' => 'Upload and delete media',
    'enquiries.reply' => 'Reply to enquiries',
    'appointments.edit' => 'Book and change appointments',
    'subscribers.export' => 'Export subscribers',
    'offers.edit' => 'Edit the offer and announcements',
    'settings.edit' => 'Change settings',
    'users.manage' => 'Invite and remove team members',
];

function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    session_name('mjadmin');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => base_url(),
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_start();
    // "Keep me signed in" keeps the session for 30 days; otherwise it ends when the browser closes
    if (!empty($_SESSION['remember']) && empty($_SESSION['cookie_extended'])) {
        setcookie(session_name(), session_id(), ['expires' => time() + 30 * 86400, 'path' => base_url(), 'httponly' => true, 'samesite' => 'Lax']);
        $_SESSION['cookie_extended'] = true;
    }
}

/** Is someone from the team signed in? (Only looks when a session cookie exists, so visitors get no session.) */
function team_member_signed_in(): bool
{
    if (empty($_COOKIE['mjadmin'])) return false;
    start_session();
    return current_user() !== null;
}

function current_user(): ?array
{
    static $user = false;
    if ($user === false) {
        $user = null;
        if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['uid'])) {
            $user = row("SELECT * FROM users WHERE id = ? AND status = 'active' AND password_hash IS NOT NULL", [(int) $_SESSION['uid']]);
        }
    }
    return $user;
}

function attempt_login(string $email, string $password, bool $remember): bool
{
    $user = row('SELECT * FROM users WHERE email = ?', [mb_strtolower(trim($email))]);
    if (!$user || $user['status'] !== 'active' || !$user['password_hash'] || !password_verify($password, $user['password_hash'])) {
        usleep(400000); // slow down guessing
        return false;
    }
    if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
        update('users', ['password_hash' => password_hash($password, PASSWORD_DEFAULT)], ['id' => $user['id']]);
    }
    session_regenerate_id(true);
    $_SESSION['uid'] = (int) $user['id'];
    $_SESSION['remember'] = $remember;
    unset($_SESSION['cookie_extended']);
    q('UPDATE users SET last_login_at = NOW() WHERE id = ?', [$user['id']]);
    return true;
}

function sign_out(): void
{
    start_session();
    $_SESSION = [];
    setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => base_url()]);
    session_destroy();
}

/** What each role may do: [permission => [role => bool]] */
function permission_matrix(): array
{
    static $matrix = null;
    if ($matrix === null) {
        $matrix = [];
        foreach (PERMISSIONS as $key => $label) $matrix[$key] = ['Owner' => true, 'Manager' => false, 'Editor' => false, 'Sales' => false];
        foreach (rows('SELECT role, permission, allowed FROM role_permissions') as $r) {
            if (isset($matrix[$r['permission']])) $matrix[$r['permission']][$r['role']] = (bool) $r['allowed'];
        }
    }
    return $matrix;
}

function can(string $permission, ?array $user = null): bool
{
    $user = $user ?? current_user();
    if (!$user) return false;
    if ($user['role'] === 'Owner') return true;
    return permission_matrix()[$permission][$user['role']] ?? false;
}

/* ---------- Forged-request protection for the admin's own requests ---------- */

function csrf_token(): string
{
    start_session();
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}

function csrf_valid(): bool
{
    $sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['_csrf'] ?? '');
    return !empty($_SESSION['csrf']) && is_string($sent) && hash_equals($_SESSION['csrf'], $sent);
}

/* ---------- Invitation and password-reset links ---------- */

/** Makes a one-time link for choosing a password; returns the token (only its hash is stored) */
function issue_password_token(int $userId, int $days = 7): string
{
    $token = bin2hex(random_bytes(24));
    update('users', ['token_hash' => hash('sha256', $token), 'token_expires' => date('Y-m-d H:i:s', time() + $days * 86400)], ['id' => $userId]);
    return $token;
}

function user_for_token(string $token): ?array
{
    if (!preg_match('/^[a-f0-9]{48}$/', $token)) return null;
    return row('SELECT * FROM users WHERE token_hash = ? AND token_expires > NOW()', [hash('sha256', $token)]);
}

/** The full address of a page in the admin, for links that are copied and shared */
function admin_url(string $page): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    return $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . base_url() . 'admin/' . $page;
}

function password_problem(string $password): ?string
{
    if (mb_strlen($password) < 8) return 'Choose a password of at least 8 characters.';
    return null;
}
