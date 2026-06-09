<?php
require_once __DIR__ . '/config.php';
ensureSessionStarted();
require_once __DIR__ . '/connection.php';
require_once __DIR__ . '/includes/password_reset_lib.php';
require_once __DIR__ . '/includes/mail_helper.php';
require_once __DIR__ . '/includes/site_config.php';

$allowedRoles = ['user' => 'Donor account', 'admin' => 'Admin account', 'delivery' => 'Delivery partner'];
$role = isset($_GET['role']) ? (string) $_GET['role'] : 'user';
if (!isset($allowedRoles[$role])) {
    $role = 'user';
}

$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf'] ?? '')) {
        $err = 'Invalid session. Please refresh the page.';
    } else {
        $email = trim((string) ($_POST['email'] ?? ''));
        $r = mark16_create_password_reset($connection, $email, $role);
        if (!$r['ok']) {
            $err = 'Could not process request.';
        } else {
            if (!empty($r['token'])) {
                $link = mark16_base_url() . '/reset_password.php?token=' . urlencode($r['token']);
                $html = '<p>You requested a password reset for your ZeroPLATE ' . htmlspecialchars($allowedRoles[$role], ENT_QUOTES, 'UTF-8') . '.</p>';
                $html .= '<p><a href="' . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '">Set a new password</a></p>';
                $html .= '<p>This link expires in one hour. If you did not request this, ignore this email.</p>';
                mark16_send_mail($email, 'ZeroPLATE — Password reset', $html);
            }
            $msg = 'If an account exists for that email, we sent reset instructions.';
        }
    }
}
$csrf = getCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot password — ZeroPLATE</title>
    <link rel="stylesheet" href="loginstyle.css">
    <style>body{font-family:Poppins,sans-serif;padding:24px;max-width:480px;margin:0 auto;}</style>
</head>
<body>
    <h1>Forgot password</h1>
    <p><?php echo htmlspecialchars($allowedRoles[$role], ENT_QUOTES, 'UTF-8'); ?></p>
    <?php if ($msg): ?><p style="color:green;"><?php echo htmlspecialchars($msg, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
    <?php if ($err): ?><p style="color:#c00;"><?php echo htmlspecialchars($err, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
    <form method="post">
        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
        <label>Email<br><input type="email" name="email" required style="width:100%;padding:8px;margin-top:4px;"></label>
        <p><button type="submit" style="margin-top:16px;padding:10px 20px;background:#06C167;color:#fff;border:none;border-radius:6px;cursor:pointer;">Send reset link</button></p>
    </form>
    <p><a href="signin.php">Back to sign in</a> · <a href="index.html">Home</a></p>
</body>
</html>
