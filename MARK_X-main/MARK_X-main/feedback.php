<?php
require_once __DIR__ . '/config.php';
ensureSessionStarted();
require_once __DIR__ . '/includes/rate_limit.php';
include 'connection.php';

if (isset($_POST['feedback'])) {
  if (mark16_rate_limit_hit('feedback_submit_' . mark16_client_bucket_prefix(), 8, 3600)) {
    echo '<script type="text/javascript">alert("Too many feedback attempts. Please try again later.")</script>';
    exit();
  }
  // Sanitize all inputs
  $email = mysqli_real_escape_string($connection, $_POST['email']);
  $name = mysqli_real_escape_string($connection, $_POST['name']);
  $msg = mysqli_real_escape_string($connection, $_POST['message']);
  $category = isset($_POST['category']) ? mysqli_real_escape_string($connection, $_POST['category']) : 'general';
  $rating = isset($_POST['rating']) ? intval($_POST['rating']) : 0;
  
  // Validate inputs
  if(empty($email) || empty($name) || empty($msg)) {
    echo '<script type="text/javascript">alert("Please fill all required fields")</script>';
  } elseif(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo '<script type="text/javascript">alert("Please enter a valid email address")</script>';
  } else {
    // Check if category and rating columns exist, if not use basic insert
    $checkColumns = "SHOW COLUMNS FROM user_feedback LIKE 'category'";
    $result = mysqli_query($connection, $checkColumns);
    
    if(mysqli_num_rows($result) > 0) {
      $st = mysqli_prepare($connection, 'INSERT INTO user_feedback(name,email,message,category,rating) VALUES(?,?,?,?,?)');
      mysqli_stmt_bind_param($st, 'ssssi', $name, $email, $msg, $category, $rating);
    } else {
      // Add columns if they don't exist
      mysqli_query($connection, "ALTER TABLE user_feedback ADD COLUMN category VARCHAR(50) DEFAULT 'general'");
      mysqli_query($connection, "ALTER TABLE user_feedback ADD COLUMN rating INT DEFAULT 0");
      mysqli_query($connection, "ALTER TABLE user_feedback ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
      $st = mysqli_prepare($connection, 'INSERT INTO user_feedback(name,email,message,category,rating) VALUES(?,?,?,?,?)');
      mysqli_stmt_bind_param($st, 'ssssi', $name, $email, $msg, $category, $rating);
    }
    
    $query_run = $st ? mysqli_stmt_execute($st) : false;
    if ($st) {
      mysqli_stmt_close($st);
    }
    if($query_run)
    {
      header("location:contact.html?feedback=success");
      exit();
    }
    else{
      echo '<script type="text/javascript">alert("Error: Feedback not saved. Please try again.")</script>'; 
    }
  }
}
?>
