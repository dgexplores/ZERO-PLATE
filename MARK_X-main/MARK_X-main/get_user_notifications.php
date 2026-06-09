<?php
session_start();
include("connection.php");

header('Content-Type: application/json');

if(!isset($_SESSION['email']) || $_SESSION['email'] == '') {
    echo json_encode(['notifications' => []]);
    exit();
}

$email = $_SESSION['email'];
$notifications = array();

// Ensure delivery_status column exists
$checkColumn = "SHOW COLUMNS FROM food_donations LIKE 'delivery_status'";
$colResult = mysqli_query($connection, $checkColumn);
if(mysqli_num_rows($colResult) == 0) {
    mysqli_query($connection, "ALTER TABLE food_donations ADD COLUMN delivery_status VARCHAR(50) DEFAULT 'pending'");
}

// Get recent donation updates
$query = "SELECT fd.*, 
          dp.name as delivery_name,
          ad.name as admin_name,
          COALESCE(fd.delivery_status, 'pending') as status
          FROM food_donations fd
          LEFT JOIN delivery_persons dp ON fd.delivery_by = dp.Did
          LEFT JOIN admin ad ON fd.assigned_to = ad.Aid
          WHERE fd.email='$email'
          AND (
              (fd.delivery_by IS NOT NULL AND fd.date >= DATE_SUB(NOW(), INTERVAL 24 HOUR))
              OR (fd.delivery_status IS NOT NULL AND fd.delivery_status != 'pending' AND fd.date >= DATE_SUB(NOW(), INTERVAL 24 HOUR))
          )
          ORDER BY fd.date DESC
          LIMIT 10";

$result = mysqli_query($connection, $query);

while($row = mysqli_fetch_assoc($result)) {
    $status = $row['status'];
    if($row['delivery_by'] != null && $status == 'pending') {
        $status = 'assigned';
    }

    $title = '';
    $message = '';
    $isNew = strtotime($row['date']) > (time() - 300); // New if within 5 minutes

    if($row['delivery_by'] != null && $status == 'assigned') {
        $title = 'Delivery Partner Assigned';
        $message = "Your donation of {$row['food']} has been assigned to delivery partner: {$row['delivery_name']}";
    } elseif($status == 'picked') {
        $title = 'Food Picked Up';
        $message = "Your donation of {$row['food']} has been picked up by the delivery partner";
    } elseif($status == 'delivered') {
        $title = 'Successfully Delivered';
        $message = "Your donation of {$row['food']} has been successfully delivered to the needy!";
    } elseif($row['assigned_to'] != null) {
        $title = 'Admin Assigned';
        $message = "Your donation of {$row['food']} has been assigned to admin: {$row['admin_name']}";
    }

    if($title != '') {
        $notifications[] = [
            'title' => $title,
            'message' => $message,
            'time' => date('d M Y, h:i A', strtotime($row['date'])),
            'isNew' => $isNew
        ];
    }
}

echo json_encode(['notifications' => $notifications]);
?>

