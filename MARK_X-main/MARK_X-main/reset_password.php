<?php
require_once __DIR__ . '/config.php';
ensureSessionStarted();
require_once __DIR__ . '/connection.php';
require_once __DIR__ . '/includes/password_reset_lib.php';

$token = isset($_GET['token']) ? (string) $_GET['token'] : '';
$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf'] ?? '')) {
        $err = 'Invalid session. Please open the reset link again.';
    } else {
        $token = (string) ($_POST['token'] ?? '');
        $p1 = (string) ($_POST['password'] ?? '');
        $p2 = (string) ($_POST['password2'] ?? '');
        if ($p1 !== $p2) {
            $err = 'Passwords do not match.';
        } else {
            $r = mark16_complete_password_reset($connection, $token, $p1);
            if ($r['ok']) {
                header('Location: signin.php?reset=1');
                exit();
            }
            if (($r['error'] ?? '') === 'weak_password') {
                $err = 'Password must be at least 8 characters.';
            } else {
                $err = 'This reset link is invalid or has expired. Request a new one.';
            }
        }
    }
}

$validPreview = $token !== '' && mark16_validate_reset_token($connection, $token)['ok'];
$csrf = getCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset password — ZeroPLATE</title>
    <style>body{font-family:Poppins,sans-serif;padding:24px;max-width:480px;margin:0 auto;}</style>
</head>
<body>
    <h1>Set new password</h1>
    <?php if ($err): ?><p style="color:#c00;"><?php echo htmlspecialchars($err, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
    <?php if ($msg): ?><p style="color:green;"><?php echo htmlspecialchars($msg, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>

    <?php if ($validPreview || ($_SERVER['REQUEST_METHOD'] === 'POST' && $token)): ?>
    <form method="post">
        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="token" value="<?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>">
        <label>New password (min 8 characters)<br>
            <input type="password" name="password" required minlength="8" style="width:100%;padding:8px;margin-top:4px;">
        </label>
        <p><label>Confirm password<br>
            <input type="password" name="password2" required minlength="8" style="width:100%;padding:8px;margin-top:4px;">
        </label></p>
        <p><button type="submit" style="padding:10px 20px;background:#06C167;color:#fff;border:none;border-radius:6px;cursor:pointer;">Update password</button></p>
    </form>
    <?php else: ?>
    <p>This reset link is invalid or expired.</p>
    <p><a href="forgot_password.php">Request a new link</a></p>
    <?php endif; ?>
</body>
</html>
