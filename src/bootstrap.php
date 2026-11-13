<?php
declare(strict_types=1);

$CONFIG = require dirname(__DIR__) . '/config.php';
define('APP_TZ', $CONFIG['timezone']);
define('ZERO_NATIONAL', '0000000000');
date_default_timezone_set(APP_TZ);
mb_internal_encoding('UTF-8');

require_once __DIR__ . '/jalali.php';
require_once __DIR__ . '/xlsx.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/payroll.php';
require_once __DIR__ . '/advance.php';
require_once __DIR__ . '/leave.php';
require_once __DIR__ . '/promotion.php';
require_once __DIR__ . '/penalty.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/managers.php';
require_once __DIR__ . '/notify.php';
require_once __DIR__ . '/employee_history.php';
require_once __DIR__ . '/employee_profile.php';
require_once __DIR__ . '/employee_dossier.php';

function cfg(string $key, $default = null)
{
    global $CONFIG;
    return $CONFIG[$key] ?? $default;
}

function start_session_secure(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_name('ELZPAY');
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'httponly' => true,
            'secure'   => !empty($_SERVER['HTTPS']),
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

function e(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function json_out(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function request_data(): array
{
    if (isset($_SERVER['CONTENT_TYPE']) && str_contains($_SERVER['CONTENT_TYPE'], 'application/json')) {
        $raw = file_get_contents('php://input');
        $d = json_decode($raw ?: '[]', true);
        return is_array($d) ? $d : [];
    }
    return $_POST;
}

function csrf_token(): string
{
    start_session_secure();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(24));
    }
    return $_SESSION['csrf'];
}

function require_csrf(): void
{
    $sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf'] ?? '');
    if (!is_string($sent) || $sent === '' || !hash_equals(csrf_token(), $sent)) {
        json_out(['ok' => false, 'message' => 'نشست شما منقضی شده است. صفحه را تازه کنید.'], 419);
    }
}

function money(float|int|null $v): string
{
    if ($v === null || abs((float)$v) < 0.0001) {
        return '-';
    }
    return number_format((float)round((float)$v), 0, '.', ',');
}

