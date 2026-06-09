<?php
/**
 * Configuration file for Food Waste Management System
 * This file should be included at the beginning of all PHP files
 */

if (!function_exists('mark16_load_local_env')) {
    function mark16_load_local_env($path) {
        if (!is_readable($path)) {
            return;
        }
        $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($lines)) {
            return;
        }
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            $eqPos = strpos($line, '=');
            if ($eqPos === false) {
                continue;
            }
            $key = trim(substr($line, 0, $eqPos));
            $value = trim(substr($line, $eqPos + 1));
            if ($key === '') {
                continue;
            }
            if ((str_starts_with($value, '"') && str_ends_with($value, '"')) || (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
                $value = substr($value, 1, -1);
            }
            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}

mark16_load_local_env(__DIR__ . '/.env.local');

// Error reporting configuration
// For deployment: avoid leaking errors to users
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/error_log.txt');
mysqli_report(MYSQLI_REPORT_OFF);

if (!function_exists('mark16_send_safe_error')) {
    function mark16_send_safe_error($message = 'Something went wrong. Please try again shortly.', $statusCode = 500) {
        if (!headers_sent()) {
            http_response_code((int) $statusCode);
            header('Content-Type: text/plain; charset=utf-8');
        }
        echo $message;
    }
}

if (!function_exists('mark16_log_runtime_error')) {
    function mark16_log_runtime_error($label, $details = []) {
        $payload = is_array($details) ? json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : (string) $details;
        error_log('[MARK16][' . $label . '] ' . $payload);
    }
}

set_exception_handler(function ($e) {
    mark16_log_runtime_error('uncaught_exception', [
        'type' => get_class($e),
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
    ]);
    mark16_send_safe_error();
});

set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    mark16_log_runtime_error('php_error', [
        'severity' => $severity,
        'message' => $message,
        'file' => $file,
        'line' => $line,
    ]);
    return true;
});

register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array((int) $err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        mark16_log_runtime_error('fatal_shutdown', $err);
        mark16_send_safe_error();
    }
});

// Session configuration (call this file before session_start())
ini_set('session.cookie_httponly', '1');
ini_set('session.use_only_cookies', '1');
$mark16HttpsEnv = getenv('MARK16_HTTPS');
$mark16HttpsOn = ($mark16HttpsEnv === '1' || strtolower((string) $mark16HttpsEnv) === 'true')
    || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (isset($_SERVER['SERVER_PORT']) && (string) $_SERVER['SERVER_PORT'] === '443');
ini_set('session.cookie_secure', $mark16HttpsOn ? '1' : '0');
ini_set('session.use_strict_mode', '1');

// Timezone setting
date_default_timezone_set('Asia/Kolkata');

// Default admin test credentials (for demo/deployment with no signup)
$DEFAULT_ADMIN_EMAIL = getenv('DEFAULT_ADMIN_EMAIL') ?: 'admin@test.com';
$DEFAULT_ADMIN_PASSWORD = getenv('DEFAULT_ADMIN_PASSWORD') ?: 'admin123';

// Security headers (if not already set in .htaccess)
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('X-XSS-Protection: 1; mode=block');
}

// Database connection check function
function checkDatabaseConnection($connection) {
    if (!$connection) {
        mark16_log_runtime_error('db_connection_check_failed', ['error' => mysqli_connect_error()]);
        mark16_send_safe_error('Temporary database issue. Please try again shortly.', 503);
        exit();
    }
}

// Input sanitization helper function
function sanitizeInput($data, $connection) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    $data = mysqli_real_escape_string($connection, $data);
    return $data;
}

// Validate email function
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Validate phone number function
function validatePhone($phone) {
    return preg_match("/^[0-9]{10}$/", $phone);
}

// Default admin credentials for testing/deployment (no signup)
define('DEFAULT_ADMIN_EMAIL', 'admin@test.com');
// Hash generated with password_hash('admin123', PASSWORD_DEFAULT)
define('DEFAULT_ADMIN_PASSWORD_HASH', '$2y$10$tCMaXbv4KySo4NHHad2yPu3xMXAAgxZzdLi0vmFYZ.y.VybUrtVCC');
define('DEFAULT_ADMIN_NAME', 'Admin');
define('DEFAULT_ADMIN_LOCATION', 'bareilly');

// Ensure session is started once
function ensureSessionStarted() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

// CSRF helpers
function getCsrfToken() {
    ensureSessionStarted();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken($token) {
    ensureSessionStarted();
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], (string)$token);
}
