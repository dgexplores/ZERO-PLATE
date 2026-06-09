<?php
/**
 * Database connection for Food Waste Management System
 *
 * Configuration (highest priority last):
 * 1) Defaults (XAMPP-style local)
 * 2) connection.local.php — return an array with any of: host, user, password, database, port
 * 3) Environment variables: MARK16_DB_HOST, MARK16_DB_USER, MARK16_DB_PASSWORD, MARK16_DB_NAME, MARK16_DB_PORT
 */
require_once __DIR__ . '/config.php';

$cfg = [
    'host' => 'localhost',
    'user' => 'root',
    'password' => '',
    'database' => 'demo',
    'port' => null,
];

$localFile = __DIR__ . DIRECTORY_SEPARATOR . 'connection.local.php';
if (is_readable($localFile)) {
    $local = include $localFile;
    if (is_array($local)) {
        foreach (['host', 'user', 'password', 'database', 'port'] as $key) {
            if (array_key_exists($key, $local) && $local[$key] !== null && $local[$key] !== '') {
                $cfg[$key] = $local[$key];
            }
        }
        // Allow explicit empty password from local file
        if (array_key_exists('password', $local)) {
            $cfg['password'] = (string) $local['password'];
        }
    }
}

if (($v = getenv('MARK16_DB_HOST')) !== false && $v !== '') {
    $cfg['host'] = $v;
}
if (($v = getenv('MARK16_DB_USER')) !== false && $v !== '') {
    $cfg['user'] = $v;
}
if (getenv('MARK16_DB_PASSWORD') !== false) {
    $cfg['password'] = (string) getenv('MARK16_DB_PASSWORD');
}
if (($v = getenv('MARK16_DB_NAME')) !== false && $v !== '') {
    $cfg['database'] = $v;
}
if (($v = getenv('MARK16_DB_PORT')) !== false && $v !== '') {
    $cfg['port'] = (int) $v;
}

$port = $cfg['port'];
if ($port === null || $port === '' || (int) $port === 0) {
    $port = null;
} else {
    $port = (int) $port;
}

$connection = mysqli_connect(
    $cfg['host'],
    $cfg['user'],
    $cfg['password'],
    $cfg['database'],
    $port
);

if (!$connection) {
    $msg = 'Database connection failed: ' . mysqli_connect_error() . ' — check MySQL is running, database exists, and connection.local.php / MARK16_DB_* settings.';
    if (defined('MARK16_SKIP_CONNECTION_DIE') && MARK16_SKIP_CONNECTION_DIE) {
        // deploy_check.php and similar tools can inspect $connection === false
    } else {
        mark16_log_runtime_error('db_connect_failed', ['error' => mysqli_connect_error(), 'host' => $cfg['host'], 'db' => $cfg['database']]);
        mark16_send_safe_error('Unable to connect to database right now. Please try again shortly.', 503);
        exit();
    }
} else {
    mysqli_set_charset($connection, 'utf8mb4');
}
