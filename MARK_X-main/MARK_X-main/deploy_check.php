<?php
/**
 * Deployment verification: database connection + required tables.
 *
 * Usage (recommended):
 *   php deploy_check.php
 *
 * Optional browser check (only if MARK16_DEPLOY_CHECK_KEY is set in the environment):
 *   deploy_check.php?key=YOUR_SECRET
 */
$cli = (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg');
$key = getenv('MARK16_DEPLOY_CHECK_KEY');
if (!$cli) {
    if ($key === false || $key === '') {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        echo "Forbidden. Set MARK16_DEPLOY_CHECK_KEY in the server environment, or run: php deploy_check.php\n";
        exit(1);
    }
    if (!isset($_GET['key']) || !hash_equals($key, (string) $_GET['key'])) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        echo "Forbidden.\n";
        exit(1);
    }
}

header('Content-Type: text/plain; charset=utf-8');

$requiredTables = [
    'admin',
    'delivery_persons',
    'food_donations',
    'login',
    'user_feedback',
];

$errors = [];

if (!is_readable(__DIR__ . '/connection.php')) {
    $errors[] = 'connection.php is missing';
} else {
    define('MARK16_SKIP_CONNECTION_DIE', true);
    require_once __DIR__ . '/connection.php';
    if (!$connection || !($connection instanceof mysqli)) {
        $errors[] = 'Database connection failed: ' . mysqli_connect_error();
    } elseif (!mysqli_ping($connection)) {
        $errors[] = 'mysqli_ping failed';
    } else {
        echo "OK: database connection\n";
        require_once __DIR__ . '/includes/site_config.php';
        if (!mark16_ensure_password_resets_table($connection)) {
            $errors[] = 'Could not ensure password_resets table: ' . mysqli_error($connection);
        } else {
            echo "OK: password_resets table\n";
        }
        if (!mark16_ensure_login_email_notifications_column($connection)) {
            $errors[] = 'Could not ensure login.email_notifications column: ' . mysqli_error($connection);
        } else {
            echo "OK: login.email_notifications column\n";
        }
        mark16_ensure_food_donation_cold_chain_columns($connection);
        echo "OK: food_donations cold-chain columns (if permitted)\n";
        require_once __DIR__ . '/includes/org_flow.php';
        mark16_ensure_org_tables($connection);
        echo "OK: organizations / claims tables\n";
        if (!is_readable(__DIR__ . '/api/automation_tick.php')) {
            $errors[] = 'Missing automation endpoint: api/automation_tick.php';
        } else {
            echo "OK: automation tick endpoint present\n";
        }
    }
}

if (empty($errors) && isset($connection) && $connection instanceof mysqli) {
    $existing = [];
    $r = mysqli_query($connection, 'SHOW TABLES');
    if ($r) {
        while ($row = mysqli_fetch_row($r)) {
            $existing[] = $row[0];
        }
    } else {
        $errors[] = 'SHOW TABLES failed: ' . mysqli_error($connection);
    }

    foreach ($requiredTables as $t) {
        if (!in_array($t, $existing, true)) {
            $errors[] = "Missing table: {$t} (import database/demo.sql)";
        }
    }

    if (!$errors) {
        echo 'OK: tables present: ' . implode(', ', $requiredTables) . "\n";
    }
}

$keyFiles = [
    'login.php',
    'connection.php',
    'forgot_password.php',
    'reset_password.php',
    'admin/admin.php',
    'delivery/delivery.php',
];
foreach ($keyFiles as $f) {
    $path = __DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $f);
    $out = [];
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($path) . ' 2>&1', $out, $code);
    if ($code !== 0) {
        $errors[] = 'PHP syntax ' . $f . ': ' . implode(' ', $out);
    }
}
if (empty(array_filter($errors, static function ($e) {
    return strpos($e, 'syntax') !== false;
}))) {
    echo "OK: PHP syntax on key entry files\n";
}

if ($errors) {
    echo "\nIssues:\n";
    foreach ($errors as $e) {
        echo "  - {$e}\n";
    }
    exit(1);
}

echo "\nDeploy check passed.\n";
exit(0);
