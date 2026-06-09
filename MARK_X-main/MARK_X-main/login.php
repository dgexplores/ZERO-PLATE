<?php
require_once __DIR__ . '/config.php';
ensureSessionStarted();
require_once __DIR__ . '/includes/rate_limit.php';
include 'connection.php';
// $connection = mysqli_connect("localhost:3307", "root", "");
// $db = mysqli_select_db($connection, 'demo');
if (isset($_POST['sign'])) {
  if (!verifyCsrfToken($_POST['csrf'] ?? '')) {
    exit('Security check failed');
  }
  if (mark16_rate_limit_hit('legacy_login_' . mark16_client_bucket_prefix(), 15, 600)) {
    exit('Too many attempts');
  }
  $email = $_POST['email'];
  $password = $_POST['password'];
  $st = mysqli_prepare($connection, 'SELECT name, gender, password FROM login WHERE email = ? LIMIT 1');
  mysqli_stmt_bind_param($st, 's', $email);
  mysqli_stmt_execute($st);
  $result = mysqli_stmt_get_result($st);
  $num = $result ? mysqli_num_rows($result) : 0;
  if ($num == 1) {
    while ($row = mysqli_fetch_assoc($result)) {
      if (password_verify($password, $row['password'])) {
        $_SESSION['email'] = $email;
        $_SESSION['name'] = $row['name'];
        $_SESSION['gender'] = $row['gender'];
        header("location:home.html");
        exit();
      } else {
        // echo "<h1><center> Login Failed incorrect password</center></h1>";
      }
    }
  } else {
    echo "<h1><center>Account does not exists </center></h1>";
  }
  mysqli_stmt_close($st);


}
?>