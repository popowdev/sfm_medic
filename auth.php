<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

const SESSION_LIFETIME = 60 * 60 * 24 * 7;

function start_session(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;

    $httpsOn =
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (!empty($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

    session_name(defined('SESSION_NAME') ? SESSION_NAME : 'samr_session');
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $httpsOn,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    ini_set('session.gc_maxlifetime', (string)SESSION_LIFETIME);

    $samrSessions = '/var/lib/samr-sessions';
    if (is_dir($samrSessions) && is_writable($samrSessions)) {
        ini_set('session.save_path', $samrSessions);
        ini_set('session.gc_probability', '1');
        ini_set('session.gc_divisor', '100');
    }

    session_start();
}

function is_logged_in(): bool {
    start_session();
    return isset($_SESSION['user']['id']) && (int)$_SESSION['user']['id'] > 0;
}

function current_user(): ?array {
    start_session();
    return $_SESSION['user'] ?? null;
}

function require_login(): void {
    if (!is_logged_in()) {
        $back = urlencode($_SERVER['REQUEST_URI'] ?? '/');
        header('Location: /login.php?redirect=' . $back);
        exit;
    }
}

function require_admin(): void {
    require_login();
    $u = current_user();
    if (!$u || ($u['role'] ?? '') !== 'admin') {
        http_response_code(403);
        echo 'Accès interdit.';
        exit;
    }
}

function login_user(array $userRow): void {
    start_session();

    $_SESSION['user'] = [
        'id'             => (int)$userRow['id'],
        'username'       => (string)$userRow['username'],
        'role'           => (string)$userRow['role'],
        'calendar_color' => (string)($userRow['calendar_color'] ?? ''),
        'discord_id'     => (string)($userRow['discord_id']     ?? ''),
    ];

    session_write_close();
}

function logout_user(): void {
    start_session();
    $_SESSION = [];
    session_destroy();

    setcookie(
        session_name(), '',
        time() - 3600,
        '/',
        '',
        !empty($_SERVER['HTTPS']),
        true
    );
}
