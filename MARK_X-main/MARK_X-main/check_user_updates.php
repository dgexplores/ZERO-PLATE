<?php
session_start();
include("connection.php");

header('Content-Type: application/json');

if(!isset($_SESSION['email']) || $_SESSION['email'] == '') {
    echo json_encode(['hasUpdates' => false, 'notificationCount' => 0]);
    exit();
}

$email = $_SESSION['email'];
$hasUpdates = false;
$notificationCount = 0;

// Ensure delivery_status column exists
$checkColumn = "SHOW COLUMNS FROM food_donations LIKE 'delivery_status'";
$colResult = mysqli_query($connection, $checkColumn);
if(mysqli_num_rows($colResult) == 0) {
    mysqli_query($connection, "ALTER TABLE food_donations ADD COLUMN delivery_status VARCHAR(50) DEFAULT 'pending'");
}

// Check for status changes in last 30 seconds
$recentQuery = "SELECT COUNT(*) as count FROM food_donations 
                WHERE email='$email' 
                AND (
                    (delivery_by IS NOT NULL AND date >= DATE_SUB(NOW(), INTERVAL 30 SECOND))
                    OR (delivery_status IS NOT NULL AND delivery_status != 'pending' AND date >= DATE_SUB(NOW(), INTERVAL 30 SECOND))
                )";
$recentResult = mysqli_query($connection, $recentQuery);
$recentRow = mysqli_fetch_assoc($recentResult);

if($recentRow && $recentRow['count'] > 0) {
    $hasUpdates = true;
}

// Count new notifications (donations assigned or status changed in last 24 hours)
$notificationQuery = "SELECT COUNT(*) as count FROM food_donations 
                      WHERE email='$email' 
                      AND (
                          (delivery_by IS NOT NULL AND date >= DATE_SUB(NOW(), INTERVAL 24 HOUR))
                          OR (delivery_status IS NOT NULL AND delivery_status != 'pending' AND date >= DATE_SUB(NOW(), INTERVAL 24 HOUR))
                      )";
$notificationResult = mysqli_query($connection, $notificationQuery);
$notificationRow = mysqli_fetch_assoc($notificationResult);
$notificationCount = $notificationRow ? $notificationRow['count'] : 0;

echo json_encode([
    'hasUpdates' => $hasUpdates,
    'notificationCount' => $notificationCount
]);
?>

