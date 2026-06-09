<?php
include("login.php"); 
include("connection.php");
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['name']) || $_SESSION['name'] === '') {
	header("location: signup.php");
	exit();
}

$email = $_SESSION['email'];
$name = $_SESSION['name'];

require_once __DIR__ . '/includes/site_config.php';
mark16_ensure_login_email_notifications_column($connection);

$emailMsg = '';
$emailErr = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_email_notif'])) {
	if (!verifyCsrfToken($_POST['csrf'] ?? '')) {
		$emailErr = 'Invalid session. Please refresh and try again.';
	} else {
		$on = isset($_POST['donation_emails']) ? 1 : 0;
		$stE = mysqli_prepare($connection, 'UPDATE login SET email_notifications = ? WHERE email = ?');
		mysqli_stmt_bind_param($stE, 'is', $on, $email);
		if (mysqli_stmt_execute($stE)) {
			$emailMsg = 'Email preferences saved.';
		} else {
			$emailErr = 'Could not save preferences.';
		}
		mysqli_stmt_close($stE);
	}
}

$emailNotifOn = 1;
$stN = mysqli_prepare($connection, 'SELECT email_notifications FROM login WHERE email = ? LIMIT 1');
if ($stN) {
	mysqli_stmt_bind_param($stN, 's', $email);
	mysqli_stmt_execute($stN);
	$rn = mysqli_stmt_get_result($stN);
	$rw = mysqli_fetch_assoc($rn);
	mysqli_stmt_close($stN);
	if ($rw) {
		$emailNotifOn = (int) $rw['email_notifications'] === 1 ? 1 : 0;
	}
}

$pwdMsg = '';
$pwdErr = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
	if (!verifyCsrfToken($_POST['csrf'] ?? '')) {
		$pwdErr = 'Invalid session. Please refresh and try again.';
	} else {
		$cur = (string) ($_POST['current_password'] ?? '');
		$n1 = (string) ($_POST['new_password'] ?? '');
		$n2 = (string) ($_POST['new_password2'] ?? '');
		if ($n1 !== $n2) {
			$pwdErr = 'New passwords do not match.';
		} elseif (strlen($n1) < 8) {
			$pwdErr = 'New password must be at least 8 characters.';
		} else {
			$st = mysqli_prepare($connection, 'SELECT password FROM login WHERE email = ?');
			mysqli_stmt_bind_param($st, 's', $email);
			mysqli_stmt_execute($st);
			$res = mysqli_stmt_get_result($st);
			$row = mysqli_fetch_assoc($res);
			mysqli_stmt_close($st);
			if (!$row || !password_verify($cur, $row['password'])) {
				$pwdErr = 'Current password is incorrect.';
			} else {
				$hash = password_hash($n1, PASSWORD_DEFAULT);
				$st2 = mysqli_prepare($connection, 'UPDATE login SET password = ? WHERE email = ?');
				mysqli_stmt_bind_param($st2, 'ss', $hash, $email);
				if (mysqli_stmt_execute($st2)) {
					$pwdMsg = 'Password updated successfully.';
				} else {
					$pwdErr = 'Could not update password.';
				}
				mysqli_stmt_close($st2);
			}
		}
	}
}
$pageCsrf = getCsrfToken();

// Ensure delivery_status column exists
$checkColumn = "SHOW COLUMNS FROM food_donations LIKE 'delivery_status'";
$colResult = mysqli_query($connection, $checkColumn);
if(mysqli_num_rows($colResult) == 0) {
    mysqli_query($connection, "ALTER TABLE food_donations ADD COLUMN delivery_status VARCHAR(50) DEFAULT 'pending'");
}

// Get user statistics
$totalDonationsQuery = "SELECT COUNT(*) as total FROM food_donations WHERE email='$email'";
$totalResult = mysqli_query($connection, $totalDonationsQuery);
$totalRow = mysqli_fetch_assoc($totalResult);
$totalDonations = $totalRow ? $totalRow['total'] : 0;

$pendingQuery = "SELECT COUNT(*) as pending FROM food_donations WHERE email='$email' AND (delivery_status IS NULL OR delivery_status='pending')";
$pendingResult = mysqli_query($connection, $pendingQuery);
$pendingRow = mysqli_fetch_assoc($pendingResult);
$pendingDonations = $pendingRow ? $pendingRow['pending'] : 0;

$deliveredQuery = "SELECT COUNT(*) as delivered FROM food_donations WHERE email='$email' AND delivery_status='delivered'";
$deliveredResult = mysqli_query($connection, $deliveredQuery);
$deliveredRow = mysqli_fetch_assoc($deliveredResult);
$deliveredDonations = $deliveredRow ? $deliveredRow['delivered'] : 0;

$assignedQuery = "SELECT COUNT(*) as assigned FROM food_donations WHERE email='$email' AND delivery_by IS NOT NULL";
$assignedResult = mysqli_query($connection, $assignedQuery);
$assignedRow = mysqli_fetch_assoc($assignedResult);
$assignedDonations = $assignedRow ? $assignedRow['assigned'] : 0;

// Ensure delivery_method column exists
$checkColumn = "SHOW COLUMNS FROM food_donations LIKE 'delivery_method'";
$colResult = mysqli_query($connection, $checkColumn);
if(mysqli_num_rows($colResult) == 0) {
    mysqli_query($connection, "ALTER TABLE food_donations ADD COLUMN delivery_method VARCHAR(20) DEFAULT 'partner'");
}

// Get all donations with status
$query = "SELECT fd.*, 
          dp.name as delivery_name, 
          dp.email as delivery_email,
          ad.name as admin_name,
          COALESCE(fd.delivery_status, 'pending') as status,
          COALESCE(fd.delivery_method, 'partner') as delivery_method
          FROM food_donations fd
          LEFT JOIN delivery_persons dp ON fd.delivery_by = dp.Did
          LEFT JOIN admin ad ON fd.assigned_to = ad.Aid
          WHERE fd.email='$email'
          ORDER BY fd.date DESC";
$result = mysqli_query($connection, $query);
$donations = array();
while($row = mysqli_fetch_assoc($result)){
    $donations[] = $row;
}
?> 

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - ZeroPLATE</title>
    <link rel="stylesheet" href="home.css">
    <link rel="stylesheet" href="profile.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="https://unicons.iconscout.com/release/v4.0.0/css/line.css">
    <style>
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 20px 0;
            padding: 20px;
        }
        .stat-card {
            background: linear-gradient(135deg, #06C167 0%, #05a85a 100%);
            color: white;
            padding: 20px;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 4px 15px rgba(6, 193, 103, 0.3);
            transition: transform 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .stat-card h3 {
            margin: 0;
            font-size: 32px;
            font-weight: bold;
        }
        .stat-card p {
            margin: 10px 0 0 0;
            font-size: 14px;
            opacity: 0.9;
        }
        .stat-card.blue {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            box-shadow: 0 4px 15px rgba(52, 152, 219, 0.3);
        }
        .stat-card.orange {
            background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);
            box-shadow: 0 4px 15px rgba(243, 156, 18, 0.3);
        }
        .stat-card.purple {
            background: linear-gradient(135deg, #9b59b6 0%, #8e44ad 100%);
            box-shadow: 0 4px 15px rgba(155, 89, 182, 0.3);
        }
        .notification-bell {
            position: relative;
            display: inline-block;
            cursor: pointer;
            margin-left: 20px;
        }
        .notification-bell i {
            font-size: 24px;
            color: #06C167;
        }
        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #e74c3c;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: bold;
        }
        .notification-panel {
            position: fixed;
            top: 80px;
            right: 20px;
            width: 350px;
            max-height: 500px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 25px rgba(0,0,0,0.2);
            z-index: 1000;
            display: none;
            overflow-y: auto;
        }
        .notification-panel.active {
            display: block;
        }
        .notification-header {
            padding: 15px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .notification-item {
            padding: 15px;
            border-bottom: 1px solid #eee;
            cursor: pointer;
            transition: background 0.3s;
        }
        .notification-item:hover {
            background: #f5f5f5;
        }
        .notification-item.new {
            background: #e8f5e9;
        }
        .donation-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin: 15px 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        .donation-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.15);
        }
        .donation-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        .donation-title {
            font-size: 20px;
            font-weight: bold;
            color: #333;
        }
        .status-badge {
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        .status-assigned {
            background: #cfe2ff;
            color: #084298;
        }
        .status-picked {
            background: #fff3cd;
            color: #856404;
        }
        .status-delivered {
            background: #d1e7dd;
            color: #0f5132;
        }
        .donation-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 10px;
            margin-top: 15px;
        }
        .detail-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .detail-item i {
            color: #06C167;
        }
        .tracking-timeline {
            margin-top: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
        }
        .timeline-item {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
            position: relative;
        }
        .timeline-item:not(:last-child)::after {
            content: '';
            position: absolute;
            left: 12px;
            top: 30px;
            width: 2px;
            height: 30px;
            background: #ddd;
        }
        .timeline-icon {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #06C167;
            color: white;
            font-size: 14px;
            z-index: 1;
        }
        .timeline-icon.completed {
            background: #06C167;
        }
        .timeline-icon.pending {
            background: #ccc;
        }
        .timeline-content {
            flex: 1;
        }
        .timeline-title {
            font-weight: bold;
            margin-bottom: 5px;
        }
        .timeline-date {
            font-size: 12px;
            color: #666;
        }
        .search-filter {
            margin: 20px 0;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .search-filter input,
        .search-filter select {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            flex: 1;
            min-width: 200px;
        }
        .no-donations {
            text-align: center;
            padding: 40px;
            color: #666;
        }
        .no-donations i {
            font-size: 64px;
            color: #ddd;
            margin-bottom: 20px;
        }
        @media (max-width: 767px) {
            .stats-container {
                grid-template-columns: repeat(2, 1fr);
                gap: 10px;
            }
            .stat-card h3 {
                font-size: 24px;
            }
            .notification-panel {
                width: calc(100% - 40px);
                right: 20px;
                left: 20px;
            }
            .donation-details {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
<header>
        <div class="logo"><b style="color: #06C167;">ZeroPLATE</b></div>
        <div class="hamburger">
            <div class="line"></div>
            <div class="line"></div>
            <div class="line"></div>
        </div>
        <nav class="nav-bar">
            <ul>
                <li><a href="home.html">Home</a></li>
                <li><a href="about.html">About</a></li>
                <li><a href="contact.html">Contact</a></li>
                <li><a href="profile.php" class="active">Profile</a></li>
                <li>
                    <div class="notification-bell" id="notificationBell" role="button" tabindex="0" aria-label="Notifications" aria-expanded="false" aria-controls="notificationPanel" onclick="toggleNotifications()">
                        <i class="uil uil-bell" aria-hidden="true"></i>
                        <span class="notification-badge" id="notificationBadge" style="display: none;">0</span>
                    </div>
                </li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </nav>
    </header>
    <script>
        hamburger=document.querySelector(".hamburger");
        hamburger.onclick =function(){
            navBar=document.querySelector(".nav-bar");
            navBar.classList.toggle("active");
        }
    </script>

    <!-- Notification Panel -->
    <div class="notification-panel" id="notificationPanel" role="region" aria-label="Notifications list">
        <div class="notification-header">
            <h3>Notifications</h3>
            <button type="button" onclick="toggleNotifications()" aria-label="Close notifications" style="cursor: pointer; border: none; background: none; font-size: 1.2rem;">✕</button>
        </div>
        <div id="notificationList">
            <!-- Notifications will be loaded here -->
        </div>
    </div>

    <div class="profile">
        <div class="profilebox" style="max-width: 1200px; width: 95%; height: auto; min-height: 600px;">
            <p class="headingline" style="text-align: left;font-size:30px;">
                <i class="uil uil-user" style="color: #06C167;"></i> Profile Dashboard
            </p>
            
            <div class="info" style="padding-left:10px;">
                <p style="font-size: 18px; margin-bottom: 10px;"><strong>Name:</strong> <?php echo htmlspecialchars($name); ?></p>
                <p style="font-size: 18px; margin-bottom: 10px;"><strong>Email:</strong> <?php echo htmlspecialchars($email); ?></p>
                <p style="font-size: 18px; margin-bottom: 10px;"><strong>Gender:</strong> <?php echo htmlspecialchars($_SESSION['gender'] ?? ''); ?></p>
                <p style="font-size: 16px; margin-bottom: 10px;"><a href="marketplace_create.php" style="color:#06C167;">Create discounted surplus listing (restaurants/donors)</a></p>
            </div>

            <div style="max-width:480px;margin:24px 0;padding:20px;border:1px solid #ddd;border-radius:12px;background:#fafafa;">
                <p class="heading" style="margin-top:0;font-size:20px;">Change password</p>
                <?php if ($pwdMsg): ?><p style="color:#06C167;"><?php echo htmlspecialchars($pwdMsg); ?></p><?php endif; ?>
                <?php if ($pwdErr): ?><p style="color:#c00;"><?php echo htmlspecialchars($pwdErr); ?></p><?php endif; ?>
                <form method="post" style="display:grid;gap:12px;">
                    <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($pageCsrf, ENT_QUOTES, 'UTF-8'); ?>">
                    <label>Current password<br><input type="password" name="current_password" required autocomplete="current-password" style="width:100%;padding:8px;margin-top:4px;border-radius:6px;border:1px solid #ccc;"></label>
                    <label>New password (min 8 characters)<br><input type="password" name="new_password" required minlength="8" autocomplete="new-password" style="width:100%;padding:8px;margin-top:4px;border-radius:6px;border:1px solid #ccc;"></label>
                    <label>Confirm new password<br><input type="password" name="new_password2" required minlength="8" autocomplete="new-password" style="width:100%;padding:8px;margin-top:4px;border-radius:6px;border:1px solid #ccc;"></label>
                    <button type="submit" name="change_password" value="1" style="padding:10px 16px;background:#06C167;color:#fff;border:none;border-radius:8px;cursor:pointer;width:fit-content;">Update password</button>
                </form>
            </div>

            <div style="max-width:480px;margin:24px 0;padding:20px;border:1px solid #ddd;border-radius:12px;background:#fafafa;">
                <p class="heading" style="margin-top:0;font-size:20px;">Donation status emails</p>
                <p style="color:#666;font-size:14px;margin-top:0;">When a partner updates your donation (assigned, picked up, delivered), we can email you at <?php echo htmlspecialchars($email); ?>.</p>
                <?php if ($emailMsg): ?><p style="color:#06C167;"><?php echo htmlspecialchars($emailMsg); ?></p><?php endif; ?>
                <?php if ($emailErr): ?><p style="color:#c00;"><?php echo htmlspecialchars($emailErr); ?></p><?php endif; ?>
                <form method="post" style="display:grid;gap:12px;margin-top:12px;">
                    <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($pageCsrf, ENT_QUOTES, 'UTF-8'); ?>">
                    <label style="display:flex;align-items:center;gap:10px;cursor:pointer;">
                        <input type="checkbox" name="donation_emails" value="1" <?php echo $emailNotifOn ? 'checked' : ''; ?>>
                        Send me email when donation status changes
                    </label>
                    <button type="submit" name="update_email_notif" value="1" style="padding:10px 16px;background:#06C167;color:#fff;border:none;border-radius:8px;cursor:pointer;width:fit-content;">Save email preference</button>
                </form>
            </div>

            <!-- Statistics Cards -->
            <div class="stats-container">
                <div class="stat-card">
                    <h3><?php echo $totalDonations; ?></h3>
                    <p><i class="uil uil-heart"></i> Total Donations</p>
                </div>
                <div class="stat-card blue">
                    <h3><?php echo $assignedDonations; ?></h3>
                    <p><i class="uil uil-truck"></i> Assigned</p>
                </div>
                <div class="stat-card orange">
                    <h3><?php echo $pendingDonations; ?></h3>
                    <p><i class="uil uil-clock"></i> Pending</p>
                </div>
                <div class="stat-card purple">
                    <h3><?php echo $deliveredDonations; ?></h3>
                    <p><i class="uil uil-check-circle"></i> Delivered</p>
                </div>
            </div>

            <hr style="margin: 30px 0;">
            
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <p class="heading" style="margin: 0;">Your Donations</p>
                <button onclick="refreshDonations()" style="padding: 10px 20px; background: #06C167; color: white; border: none; border-radius: 5px; cursor: pointer;">
                    <i class="uil uil-sync"></i> Refresh
                </button>
            </div>

            <!-- Search and Filter -->
            <div class="search-filter">
                <input type="text" id="searchInput" placeholder="Search donations..." onkeyup="filterDonations()">
                <select id="statusFilter" onchange="filterDonations()">
                    <option value="">All Status</option>
                    <option value="pending">Pending</option>
                    <option value="assigned">Assigned</option>
                    <option value="picked">Picked Up</option>
                    <option value="delivered">Delivered</option>
                </select>
            </div>

            <!-- Donations List -->
            <div id="donationsContainer">
                <?php if(empty($donations)): ?>
                    <div class="no-donations">
                        <i class="uil uil-inbox"></i>
                        <h3>No Donations Yet</h3>
                        <p>Start making a difference by donating food!</p>
                        <a href="fooddonateform.php" style="display: inline-block; margin-top: 20px; padding: 10px 20px; background: #06C167; color: white; text-decoration: none; border-radius: 5px;">Donate Now</a>
                    </div>
                <?php else: ?>
                    <?php foreach($donations as $donation): 
                        $status = $donation['status'];
                        $statusText = ucfirst($status);
                        if($donation['delivery_by'] != null && $status == 'pending') {
                            $status = 'assigned';
                            $statusText = 'Assigned';
                        }
                    ?>
                        <div class="donation-card" data-status="<?php echo $status; ?>" data-food="<?php echo strtolower($donation['food']); ?>">
                            <div class="donation-header">
                                <div class="donation-title">
                                    <i class="uil uil-utensils"></i> <?php echo htmlspecialchars($donation['food']); ?>
                                </div>
                                <span class="status-badge status-<?php echo $status; ?>">
                                    <?php echo $statusText; ?>
                                </span>
                            </div>
                            
                            <div class="donation-details">
                                <div class="detail-item">
                                    <i class="uil uil-calendar-alt"></i>
                                    <span><strong>Date:</strong> <?php echo date('d M Y, h:i A', strtotime($donation['date'])); ?></span>
                                </div>
                                <div class="detail-item">
                                    <i class="uil uil-layer-group"></i>
                                    <span><strong>Category:</strong> <?php echo ucfirst(str_replace('-', ' ', $donation['category'])); ?></span>
                                </div>
                                <div class="detail-item">
                                    <i class="uil uil-weight"></i>
                                    <span><strong>Quantity:</strong> <?php echo htmlspecialchars($donation['quantity']); ?></span>
                                </div>
                                <div class="detail-item">
                                    <i class="uil uil-map-marker"></i>
                                    <span><strong>Location:</strong> <?php echo ucfirst($donation['location']); ?></span>
                                </div>
                                <div class="detail-item">
                                    <i class="uil uil-<?php echo (isset($donation['delivery_method']) && $donation['delivery_method'] == 'self') ? 'user' : 'truck'; ?>"></i>
                                    <span><strong>Delivery:</strong> 
                                        <?php if(isset($donation['delivery_method']) && $donation['delivery_method'] == 'self'): ?>
                                            <span style="color: #2196F3;">Self Delivery</span>
                                        <?php else: ?>
                                            <span style="color: #06C167;">Delivery Partner</span>
                                        <?php endif; ?>
                                    </span>
                                </div>
                            </div>

                            <?php if($donation['delivery_by'] != null || $donation['assigned_to'] != null || (isset($donation['delivery_method']) && $donation['delivery_method'] == 'self')): ?>
                                <div class="tracking-timeline">
                                    <h4 style="margin-bottom: 15px;"><i class="uil uil-location-arrow"></i> Tracking</h4>
                                    
                                    <div class="timeline-item">
                                        <div class="timeline-icon completed">
                                            <i class="uil uil-check"></i>
                                        </div>
                                        <div class="timeline-content">
                                            <div class="timeline-title">Donation Submitted</div>
                                            <div class="timeline-date"><?php echo date('d M Y, h:i A', strtotime($donation['date'])); ?></div>
                                        </div>
                                    </div>

                                    <?php if($donation['assigned_to'] != null): ?>
                                        <div class="timeline-item">
                                            <div class="timeline-icon completed">
                                                <i class="uil uil-user-check"></i>
                                            </div>
                                            <div class="timeline-content">
                                                <div class="timeline-title">Assigned to Admin</div>
                                                <div class="timeline-date">Admin: <?php echo htmlspecialchars($donation['admin_name']); ?></div>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <?php if($donation['delivery_by'] != null): ?>
                                        <div class="timeline-item">
                                            <div class="timeline-icon <?php echo ($status == 'delivered' || $status == 'picked') ? 'completed' : 'pending'; ?>">
                                                <i class="uil uil-truck"></i>
                                            </div>
                                            <div class="timeline-content">
                                                <div class="timeline-title">Delivery Partner Assigned</div>
                                                <div class="timeline-date">Partner: <?php echo htmlspecialchars($donation['delivery_name']); ?></div>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <?php if($status == 'picked' || $status == 'delivered'): ?>
                                        <div class="timeline-item">
                                            <div class="timeline-icon completed">
                                                <i class="uil uil-box"></i>
                                            </div>
                                            <div class="timeline-content">
                                                <div class="timeline-title">Picked Up</div>
                                                <div class="timeline-date">Food collected by delivery partner</div>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <?php if($status == 'delivered'): ?>
                                        <div class="timeline-item">
                                            <div class="timeline-icon completed">
                                                <i class="uil uil-check-circle"></i>
                                            </div>
                                            <div class="timeline-content">
                                                <div class="timeline-title">Delivered</div>
                                                <div class="timeline-date">Successfully delivered to needy</div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://unicons.iconscout.com/release/v4.0.0/script/monochrome/bundle.js"></script>
    <script src="js/realtime-updates.js"></script>
    <script>
        // Initialize real-time updates
        const realtime = new RealtimeUpdates('user');
        
        // Handle updates
        realtime.onUpdate((data) => {
            if(data.updates && data.updates.length > 0) {
                // Update notification badge
                const badge = document.getElementById('notificationBadge');
                if(badge) {
                    badge.textContent = data.updates.length;
                    badge.style.display = 'flex';
                }
                
                // Refresh donations list without full page reload
                updateDonationsList(data.updates);
            }
        });
        
        // Start real-time updates
        realtime.start();
        
        // Legacy update check (fallback)
        function checkForUpdates() {
            fetch('check_user_updates.php')
                .then(response => response.json())
                .then(data => {
                    if(data.hasUpdates) {
                        // Only reload if major changes
                        if(data.notificationCount > 0) {
                            location.reload();
                        }
                    }
                    if(data.notificationCount > 0) {
                        const badge = document.getElementById('notificationBadge');
                        if(badge) {
                            badge.textContent = data.notificationCount;
                            badge.style.display = 'flex';
                            loadNotifications();
                        }
                    }
                })
                .catch(error => console.error('Error:', error));
        }
        
        // Update donations list dynamically
        function updateDonationsList(updates) {
            updates.forEach(update => {
                const orderCard = document.querySelector(`[data-order-id="${update.order_id}"]`);
                if(orderCard) {
                    // Update status badge
                    const statusBadge = orderCard.querySelector('.status-badge');
                    if(statusBadge) {
                        statusBadge.className = `status-badge status-${update.status}`;
                        statusBadge.textContent = update.status.charAt(0).toUpperCase() + update.status.slice(1);
                    }
                    
                    // Update timeline if delivery partner assigned
                    if(update.delivery_name) {
                        const timeline = orderCard.querySelector('.tracking-timeline');
                        if(timeline && !timeline.querySelector('.delivery-assigned')) {
                            const timelineItem = document.createElement('div');
                            timelineItem.className = 'timeline-item delivery-assigned';
                            timelineItem.innerHTML = `
                                <div class="timeline-icon completed">
                                    <i class="uil uil-truck"></i>
                                </div>
                                <div class="timeline-content">
                                    <div class="timeline-title">Delivery Partner Assigned</div>
                                    <div class="timeline-date">Partner: ${update.delivery_name}</div>
                                </div>
                            `;
                            timeline.appendChild(timelineItem);
                        }
                    }
                }
            });
        }
        
        // Check every 30 seconds as fallback
        setInterval(checkForUpdates, 30000);

        function loadNotifications() {
            fetch('get_user_notifications.php')
                .then(response => response.json())
                .then(data => {
                    const notificationList = document.getElementById('notificationList');
                    notificationList.innerHTML = '';
                    if(data.notifications.length === 0) {
                        notificationList.innerHTML = '<div style="padding: 20px; text-align: center; color: #666;">No new notifications</div>';
                    } else {
                        data.notifications.forEach(notif => {
                            const item = document.createElement('div');
                            item.className = 'notification-item' + (notif.isNew ? ' new' : '');
                            item.innerHTML = `
                                <div style="font-weight: bold; margin-bottom: 5px;">${notif.title}</div>
                                <div style="font-size: 12px; color: #666;">${notif.message}</div>
                                <div style="font-size: 11px; color: #999; margin-top: 5px;">${notif.time}</div>
                            `;
                            notificationList.appendChild(item);
                        });
                    }
                });
        }

        function toggleNotifications() {
            const panel = document.getElementById('notificationPanel');
            const bell = document.getElementById('notificationBell');
            panel.classList.toggle('active');
            const open = panel.classList.contains('active');
            if (bell) bell.setAttribute('aria-expanded', open ? 'true' : 'false');
            if(open) {
                loadNotifications();
            }
        }

        document.getElementById('notificationBell')?.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                toggleNotifications();
            }
        });

        function filterDonations() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const statusFilter = document.getElementById('statusFilter').value;
            const cards = document.querySelectorAll('.donation-card');

            cards.forEach(card => {
                const food = card.getAttribute('data-food');
                const status = card.getAttribute('data-status');
                const matchesSearch = food.includes(searchTerm);
                const matchesStatus = !statusFilter || status === statusFilter;

                if(matchesSearch && matchesStatus) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        }

        function refreshDonations() {
            location.reload();
        }

        // Load notifications on page load
        checkForUpdates();
    </script>
</body>
</html>
