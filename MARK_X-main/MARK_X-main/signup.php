<?php
require_once __DIR__ . '/config.php';
ensureSessionStarted();
require_once __DIR__ . '/includes/rate_limit.php';
include 'connection.php';
require_once __DIR__ . '/includes/site_config.php';
mark16_ensure_login_email_notifications_column($connection);

$signupErr = 0;

if (isset($_POST['sign'])) {
    if (!verifyCsrfToken($_POST['csrf'] ?? '')) {
        $signupErr = 3;
    } elseif (mark16_rate_limit_hit('user_signup_' . mark16_client_bucket_prefix(), 8, 3600)) {
        $signupErr = 4;
    } else {
        $username = trim((string) ($_POST['name'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $gender = trim((string) ($_POST['gender'] ?? ''));

        if ($username === '' || $email === '' || $password === '' || $gender === '') {
            $signupErr = 1;
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $signupErr = 2;
        } elseif (strlen($password) < 6) {
            $signupErr = 5;
        } else {
            $st = mysqli_prepare($connection, 'SELECT 1 FROM login WHERE email = ? LIMIT 1');
            mysqli_stmt_bind_param($st, 's', $email);
            mysqli_stmt_execute($st);
            $exists = mysqli_stmt_get_result($st)->num_rows > 0;
            mysqli_stmt_close($st);
            if ($exists) {
                $signupErr = 6;
            } else {
                $pass = password_hash($password, PASSWORD_DEFAULT);
                $ins = mysqli_prepare($connection, 'INSERT INTO login (name, email, password, gender) VALUES (?, ?, ?, ?)');
                mysqli_stmt_bind_param($ins, 'ssss', $username, $email, $pass, $gender);
                if (mysqli_stmt_execute($ins)) {
                    mysqli_stmt_close($ins);
                    header('Location: signin.php');
                    exit();
                }
                mysqli_stmt_close($ins);
                $signupErr = 7;
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
    <title>Register — ZeroPLATE</title>
    <link rel="stylesheet" href="loginstyle.css">
    <link rel="stylesheet" href="path/to/font-awesome/css/font-awesome.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.3.0/font/bootstrap-icons.css" />
    <link rel="stylesheet" href="https://unicons.iconscout.com/release/v4.0.0/css/line.css">
</head>
<body>

    <div class="container">
    <div class="regform">
       
        <form action="" method="post">
            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
            <p class="logo"><b style="color: #06C167;">ZeroPLATE</b></p>
            
            <p id="heading">Create your account</p>

            <?php if ($signupErr === 1): ?><p style="color:#c00;text-align:center;">Please fill all required fields.</p><?php endif; ?>
            <?php if ($signupErr === 2): ?><p style="color:#c00;text-align:center;">Invalid email.</p><?php endif; ?>
            <?php if ($signupErr === 3): ?><p style="color:#c00;text-align:center;">Security check failed. Refresh the page.</p><?php endif; ?>
            <?php if ($signupErr === 4): ?><p style="color:#c00;text-align:center;">Too many signup attempts. Try later.</p><?php endif; ?>
            <?php if ($signupErr === 5): ?><p style="color:#c00;text-align:center;">Password must be at least 6 characters.</p><?php endif; ?>
            <?php if ($signupErr === 6): ?><p style="color:#c00;text-align:center;">Account already exists.</p><?php endif; ?>
            <?php if ($signupErr === 7): ?><p style="color:#c00;text-align:center;">Could not save. Try again.</p><?php endif; ?>
            
            <div class="input">
                <label class="textlabel" for="name">User name</label><br>
                
                <input type="text" id="name" name="name" required/>
             </div>
             <div class="input">
                <label class="textlabel" for="email">Email</label>
                <input type="email" id="email" name="email" required/>
             </div>
             <label class="textlabel" for="password">Password</label>
             <div class="password">
              
                <input type="password" name="password" id="password" required/>
                <i class="uil uil-eye-slash showHidePw" id="showpassword"></i>                
			
             </div>
    
             <div class="radio">
                
                <input type="radio" name="gender" id="male" value="male" required/>
                <label for="male" >Male</label>
                <input type="radio" name="gender" id="female" value="female">
                <label for="female" >Female</label>

             </div>
             <div class="btn">
                <button type="submit" name="sign">Continue</button>
             </div>
                
            <div class="signin-up">
                 <p style="font-size: 20px; text-align: center;">Already have an account? <a href="signin.php"> Sign in</a></p>
             </div>
         

        </form>
        </div>
       
    </div>

    <script src="admin/login.js"></script>
       
</body>
</html>
