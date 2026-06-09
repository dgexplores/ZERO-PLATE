<?php
session_start();
include __DIR__ . '/login.php';
include __DIR__ . '/connection.php';

if (!isset($_SESSION['name']) || $_SESSION['name'] === '') {
    header('location: signup.php');
    exit();
}

$donationId = isset($_SESSION['last_donation_id']) ? $_SESSION['last_donation_id'] : null;
$donationInfo = null;

if ($donationId) {
    $did = (int) $donationId;
    $em = mysqli_real_escape_string($connection, $_SESSION['email']);
    $query = "SELECT * FROM food_donations WHERE Fid=$did AND email='$em'";
    $result = mysqli_query($connection, $query);
    if ($result && mysqli_num_rows($result) > 0) {
        $donationInfo = mysqli_fetch_assoc($result);
    }
    unset($_SESSION['last_donation_id']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Donation Successful - ZeroPLATE</title>
    <link rel="stylesheet" href="home.css">
    <link rel="stylesheet" href="https://unicons.iconscout.com/release/v4.0.0/css/line.css">
    <style>
        .success-container { background-color: white; display: grid; place-items: center; min-height: 80vh; padding: 40px 20px; }
        .success-box { text-align: center; max-width: 600px; padding: 40px; background: linear-gradient(135deg, #06C167 0%, #05a85a 100%); border-radius: 20px; color: white; box-shadow: 0 10px 40px rgba(6, 193, 103, 0.3); }
        .success-icon { font-size: 80px; margin-bottom: 20px; animation: bounce 1s ease-in-out; }
        @keyframes bounce { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-20px); } }
        .success-title { font-size: 36px; margin-bottom: 15px; font-weight: bold; }
        .success-message { font-size: 20px; margin-bottom: 30px; line-height: 1.6; }
        .donation-details { background: rgba(255, 255, 255, 0.2); padding: 20px; border-radius: 10px; margin: 20px 0; text-align: left; }
        .donation-details h3 { margin-bottom: 15px; font-size: 24px; }
        .detail-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid rgba(255, 255, 255, 0.2); }
        .detail-row:last-child { border-bottom: none; }
        .action-buttons { display: flex; gap: 15px; justify-content: center; margin-top: 30px; flex-wrap: wrap; }
        .btn { padding: 15px 30px; border-radius: 10px; text-decoration: none; font-weight: bold; transition: transform 0.3s; display: inline-block; }
        .btn:hover { transform: translateY(-3px); }
        .btn-primary { background: white; color: #06C167; }
        .btn-secondary { background: rgba(255, 255, 255, 0.2); color: white; border: 2px solid white; }
        .tracking-link { margin-top: 20px; padding: 15px; background: rgba(255, 255, 255, 0.2); border-radius: 10px; }
        .tracking-link a { color: white; text-decoration: underline; font-weight: bold; }
        @media (max-width: 767px) {
            .success-box { padding: 30px 20px; }
            .success-title { font-size: 28px; }
            .action-buttons { flex-direction: column; }
            .btn { width: 100%; }
        }
    </style>
</head>
<body>
    <header>
        <div class="logo"><b style="color: #06C167;">ZeroPLATE</b></div>
        <div class="hamburger" aria-label="Open menu" role="button" tabindex="0">
            <div class="line"></div>
            <div class="line"></div>
            <div class="line"></div>
        </div>
        <nav class="nav-bar" aria-label="Main navigation">
            <ul>
                <li><a href="home.html">Home</a></li>
                <li><a href="about.html">About</a></li>
                <li><a href="contact.html">Contact</a></li>
                <li><a href="profile.php">Profile</a></li>
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

    <div class="success-container">
        <div class="success-box">
            <div class="success-icon">
                <i class="uil uil-check-circle"></i>
            </div>
            <h1 class="success-title">Donation successful</h1>
            <p class="success-message">
                Thank you for your generous donation. Your food will be coordinated for collection and delivery to those in need.
            </p>

            <?php if ($donationInfo): ?>
            <div class="donation-details">
                <h3><i class="uil uil-info-circle"></i> Donation details</h3>
                <div class="detail-row">
                    <span><strong>Food:</strong></span>
                    <span><?php echo htmlspecialchars($donationInfo['food']); ?></span>
                </div>
                <div class="detail-row">
                    <span><strong>Category:</strong></span>
                    <span><?php echo htmlspecialchars(ucfirst(str_replace('-', ' ', $donationInfo['category']))); ?></span>
                </div>
                <div class="detail-row">
                    <span><strong>Quantity:</strong></span>
                    <span><?php echo htmlspecialchars($donationInfo['quantity']); ?></span>
                </div>
                <div class="detail-row">
                    <span><strong>Location:</strong></span>
                    <span><?php echo htmlspecialchars(ucfirst($donationInfo['location'])); ?></span>
                </div>
                <div class="detail-row">
                    <span><strong>Date:</strong></span>
                    <span><?php echo date('d M Y, h:i A', strtotime($donationInfo['date'])); ?></span>
                </div>
            </div>
            <?php endif; ?>

            <div class="tracking-link">
                <p><i class="uil uil-location-arrow"></i> Track your donation status in real time.</p>
                <a href="profile.php">View tracking and notifications</a>
            </div>

            <div class="action-buttons">
                <a href="profile.php" class="btn btn-primary">
                    <i class="uil uil-user"></i> View profile
                </a>
                <a href="home.html" class="btn btn-secondary">
                    <i class="uil uil-home"></i> Return home
                </a>
            </div>
        </div>
    </div>

    <script src="https://unicons.iconscout.com/release/v4.0.0/script/monochrome/bundle.js"></script>
</body>
</html>
