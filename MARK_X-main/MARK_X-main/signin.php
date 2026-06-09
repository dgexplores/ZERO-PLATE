<?php
require_once __DIR__ . '/config.php';
ensureSessionStarted();
require_once __DIR__ . '/includes/rate_limit.php';
include 'connection.php';

$msg = 0;
$err = 0;

if (isset($_POST['sign'])) {
    if (!verifyCsrfToken($_POST['csrf'] ?? '')) {
        $err = 2;
    } elseif (mark16_rate_limit_hit('user_login_' . mark16_client_bucket_prefix(), 12, 600)) {
        $err = 3;
    } else {
        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $st = mysqli_prepare($connection, 'SELECT name, gender, password FROM login WHERE email = ? LIMIT 1');
        if ($st) {
            mysqli_stmt_bind_param($st, 's', $email);
            mysqli_stmt_execute($st);
            $result = mysqli_stmt_get_result($st);
            $row = mysqli_fetch_assoc($result);
            mysqli_stmt_close($st);
            if ($row && password_verify($password, $row['password'])) {
                $_SESSION['email'] = $email;
                $_SESSION['name'] = $row['name'];
                $_SESSION['gender'] = $row['gender'];
                header('Location: home.html');
                exit();
            }
            if ($row) {
                $msg = 1;
            } else {
                $err = 1;
            }
        }
    }
}
$csrf = getCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign in — ZeroPLATE</title>
    <link rel="stylesheet" href="loginstyle.css">
    <link rel="stylesheet" href="path/to/font-awesome/css/font-awesome.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.3.0/font/bootstrap-icons.css" />

    <link rel="stylesheet" href="https://unicons.iconscout.com/release/v4.0.0/css/line.css">

</head>

<body>
    <style>
    .uil {

        top: 42%;
    }
    </style>
    <div class="container">
        <div class="regform">

            <form action="" method="post" autocomplete="on">
                <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
                <p class="logo" style=""><b style="color:#06C167;">ZeroPLATE</b></p>
                <p id="heading" style="padding-left: 1px;"> Welcome back ! <img src="" alt=""> </p>

                <?php if ($err === 1): ?>
                <p class="error" style="text-align:center;">No account found for this email.</p>
                <?php elseif ($err === 2): ?>
                <p class="error" style="text-align:center;">Security check failed. Refresh the page.</p>
                <?php elseif ($err === 3): ?>
                <p class="error" style="text-align:center;">Too many attempts. Try again in a few minutes.</p>
                <?php endif; ?>

                <div class="input">
                    <input type="email" placeholder="Email address" name="email" value="" required autocomplete="username" />
                </div>
                <div class="password">
                    <input type="password" placeholder="Password" name="password" id="password" required autocomplete="current-password" />

                  
                    <i class="uil uil-eye-slash showHidePw"></i>
                  
                    <?php
                    if ($msg == 1) {
                        echo ' <i class="bx bx-error-circle error-icon"></i>';
                        echo '<p class="error">Incorrect password.</p>';
                    }
                    ?>
                
                </div>


                <div class="btn">
                    <button type="submit" name="sign"> Sign in</button>
                </div>
                <p style="text-align:center;margin-top:12px;"><a href="forgot_password.php?role=user">Forgot password?</a></p>
                <?php if (!empty($_GET['reset'])): ?>
                <p style="text-align:center;color:green;">Password updated. You can sign in.</p>
                <?php endif; ?>
                <div class="signin-up">
                    <p id="signin-up">Don't have an account? <a href="signup.php">Register</a></p>
                </div>
            </form>
        </div>


    </div>
    <script src="login.js"></script>
    <script src="admin/login.js"></script>
</body>

</html>
