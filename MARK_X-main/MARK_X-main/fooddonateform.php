<?php
include("login.php"); 
if (!isset($_SESSION['name']) || $_SESSION['name'] === '') {
	header("location: signin.php");
	exit();
}
$emailid = $_SESSION['email'];
include("connection.php");
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/site_config.php';
require_once __DIR__ . '/includes/automation.php';
require_once __DIR__ . '/includes/org_flow.php';

if (isset($_POST['submit'])) {
    if (!verifyCsrfToken($_POST['csrf'] ?? '')) {
        echo '<script type="text/javascript">alert("Invalid session. Please refresh the page and try again.")</script>';
    } else {
    $foodname = mysqli_real_escape_string($connection, $_POST['foodname']);
    $meal = mysqli_real_escape_string($connection, $_POST['meal']);
    
    // Validate and sanitize category
    $allowed_categories = array('raw-food', 'cooked-food', 'packed-food');
    $category = isset($_POST['image-choice']) ? $_POST['image-choice'] : 'cooked-food';
    if(!in_array($category, $allowed_categories)) {
        $category = 'cooked-food';
    }
    $category = mysqli_real_escape_string($connection, $category);
    
    $quantity = mysqli_real_escape_string($connection, $_POST['quantity']);
    $phoneno = mysqli_real_escape_string($connection, $_POST['phoneno']);
    $district = mysqli_real_escape_string($connection, $_POST['district']);
    $address = mysqli_real_escape_string($connection, $_POST['address']);
    $name = mysqli_real_escape_string($connection, $_POST['name']);
    
    // Delivery method: 'self' or 'partner'
    $delivery_method = isset($_POST['delivery_method']) ? mysqli_real_escape_string($connection, $_POST['delivery_method']) : 'partner';
    if (!in_array($delivery_method, ['self', 'partner'], true)) {
        $delivery_method = 'partner';
    }
    $food_condition = isset($_POST['food_condition']) && $_POST['food_condition'] === 'non_edible' ? 'non_edible' : 'edible';
    $prepared_at_sql = 'NULL';
    $best_before_sql = 'NULL';
    $preparedAtRaw = str_replace('T', ' ', trim((string)($_POST['prepared_at'] ?? '')));
    $bestBeforeRaw = str_replace('T', ' ', trim((string)($_POST['best_before_at'] ?? '')));
    if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $preparedAtRaw)) {
        $prepared_at_sql = "'" . mysqli_real_escape_string($connection, $preparedAtRaw . ':00') . "'";
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $bestBeforeRaw)) {
        $best_before_sql = "'" . mysqli_real_escape_string($connection, $bestBeforeRaw . ':00') . "'";
    }
    
    // Additional fields for self-delivery
    $delivery_address = '';
    $delivery_instructions = '';
    if($delivery_method == 'self') {
        $delivery_address = mysqli_real_escape_string($connection, $_POST['delivery_address'] ?? '');
        $delivery_instructions = mysqli_real_escape_string($connection, $_POST['delivery_instructions'] ?? '');
    }

    // Validate required fields
    if(empty($foodname) || empty($meal) || empty($quantity) || empty($phoneno) || empty($district) || empty($address) || empty($name)) {
        echo '<script type="text/javascript">alert("Please fill all required fields")</script>';
    } else {
        if ($food_condition === 'edible' && $best_before_sql === 'NULL') {
            echo '<script type="text/javascript">alert("Best Before is required for edible donations.")</script>';
        } elseif ($food_condition === 'edible' && $bestBeforeRaw !== '' && strtotime($bestBeforeRaw) !== false && strtotime($bestBeforeRaw) <= time()) {
            echo '<script type="text/javascript">alert("Best Before must be a future date/time for edible donations.")</script>';
        } elseif ($preparedAtRaw !== '' && $bestBeforeRaw !== '' && strtotime($preparedAtRaw) !== false && strtotime($bestBeforeRaw) !== false && strtotime($preparedAtRaw) > strtotime($bestBeforeRaw)) {
            echo '<script type="text/javascript">alert("Prepared time cannot be later than Best Before time.")</script>';
        } else {
        // Validate phone number format
        if(!preg_match("/^[0-9]{10}$/", $phoneno)) {
            echo '<script type="text/javascript">alert("Please enter a valid 10-digit phone number")</script>';
        } else {
            // Ensure delivery_method column exists
            $checkColumn = "SHOW COLUMNS FROM food_donations LIKE 'delivery_method'";
            $colResult = mysqli_query($connection, $checkColumn);
            if(mysqli_num_rows($colResult) == 0) {
                mysqli_query($connection, "ALTER TABLE food_donations ADD COLUMN delivery_method VARCHAR(20) DEFAULT 'partner'");
            }

            mark16_ensure_food_donation_cold_chain_columns($connection);
            $colCold = mysqli_query($connection, "SHOW COLUMNS FROM food_donations LIKE 'requires_cold_chain'");
            $hasCold = $colCold && mysqli_num_rows($colCold) > 0;
            $reqCold = isset($_POST['requires_cold_chain']) ? 1 : 0;
            $pickupDeadlineSql = 'NULL';
            if ($hasCold && !empty($_POST['pickup_deadline'])) {
                $rawDl = str_replace('T', ' ', trim((string) $_POST['pickup_deadline']));
                if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}(:\d{2})?$/', $rawDl)) {
                    if (strlen($rawDl) === 16) {
                        $rawDl .= ':00';
                    }
                    $pickupDeadlineSql = "'" . mysqli_real_escape_string($connection, $rawDl) . "'";
                }
            }
            
            // Insert donation
            $foodConditionCol = mysqli_query($connection, "SHOW COLUMNS FROM food_donations LIKE 'food_condition'");
            if ($foodConditionCol && mysqli_num_rows($foodConditionCol) == 0) {
                mysqli_query($connection, "ALTER TABLE food_donations ADD COLUMN food_condition ENUM('edible','non_edible') NOT NULL DEFAULT 'edible'");
            }
            $preparedCol = mysqli_query($connection, "SHOW COLUMNS FROM food_donations LIKE 'prepared_at'");
            if ($preparedCol && mysqli_num_rows($preparedCol) == 0) {
                mysqli_query($connection, "ALTER TABLE food_donations ADD COLUMN prepared_at DATETIME NULL DEFAULT NULL");
            }
            $bestBeforeCol = mysqli_query($connection, "SHOW COLUMNS FROM food_donations LIKE 'best_before_at'");
            if ($bestBeforeCol && mysqli_num_rows($bestBeforeCol) == 0) {
                mysqli_query($connection, "ALTER TABLE food_donations ADD COLUMN best_before_at DATETIME NULL DEFAULT NULL");
            }

            if ($hasCold) {
                $query = "INSERT INTO food_donations(email, food, type, category, phoneno, location, address, name, quantity, delivery_method, requires_cold_chain, pickup_deadline, food_condition, prepared_at, best_before_at) 
                     VALUES('$emailid', '$foodname', '$meal', '$category', '$phoneno', '$district', '$address', '$name', '$quantity', '$delivery_method', $reqCold, $pickupDeadlineSql, '$food_condition', $prepared_at_sql, $best_before_sql)";
            } else {
                $query = "INSERT INTO food_donations(email, food, type, category, phoneno, location, address, name, quantity, delivery_method, food_condition, prepared_at, best_before_at) 
                     VALUES('$emailid', '$foodname', '$meal', '$category', '$phoneno', '$district', '$address', '$name', '$quantity', '$delivery_method', '$food_condition', $prepared_at_sql, $best_before_sql)";
            }
            
            $query_run = mysqli_query($connection, $query);
            if($query_run)
            {
                $donationId = mysqli_insert_id($connection);
                $_SESSION['last_donation_id'] = $donationId;
                $_SESSION['delivery_method'] = $delivery_method;
                
                // If self-delivery, mark as self-delivered immediately
                if($delivery_method == 'self') {
                    mysqli_query($connection, "UPDATE food_donations SET delivery_status='delivered', delivery_method='self' WHERE Fid=$donationId");
                } else {
                    // Autonomous business routing: assign admin + delivery automatically.
                    mark16_route_donation_for_org($connection, (int) $donationId);
                    mark16_auto_assign_donation($connection, (int) $donationId);
                }
                
                header("location:delivery_success.php");
                exit();
            }
            else{
                echo '<script type="text/javascript">alert("Error: Data not saved. Please try again.")</script>';
            }
        }
        }
    }
    }
}
$donateCsrf = getCsrfToken();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Food Donation Form - ZeroPLATE</title>
    <link rel="stylesheet" href="loginstyle.css">
    <link rel="stylesheet" href="https://unicons.iconscout.com/release/v4.0.0/css/line.css">
    <style>
        .form-section {
            background: white;
            padding: 20px;
            margin: 15px 0;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .section-title {
            font-size: 18px;
            font-weight: bold;
            color: #06C167;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #06C167;
        }
        .delivery-method-group {
            display: flex;
            gap: 20px;
            margin: 15px 0;
            flex-wrap: wrap;
        }
        .delivery-option {
            flex: 1;
            min-width: 200px;
            padding: 20px;
            border: 2px solid #ddd;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s;
            text-align: center;
        }
        .delivery-option:hover {
            border-color: #06C167;
            background: #f0fdf4;
        }
        .delivery-option input[type="radio"] {
            display: none;
        }
        .delivery-option input[type="radio"]:checked + label {
            color: #06C167;
            font-weight: bold;
        }
        .delivery-option.selected {
            border-color: #06C167;
            background: #e8f5e9;
        }
        .delivery-option i {
            font-size: 48px;
            color: #06C167;
            margin-bottom: 10px;
        }
        .conditional-field {
            display: none;
            margin-top: 15px;
            padding: 15px;
            background: #f9f9f9;
            border-radius: 5px;
            border-left: 4px solid #06C167;
        }
        .conditional-field.active {
            display: block;
        }
        .info-box {
            background: #e3f2fd;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
            border-left: 4px solid #2196F3;
        }
        .info-box i {
            color: #2196F3;
            margin-right: 10px;
        }
        .required-field {
            color: #e74c3c;
        }
    </style>
</head>
<body style="background-color: #06C167;">
    <div class="container">
        <div class="regformf">
            <form action="" method="post" id="donationForm">
                <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($donateCsrf, ENT_QUOTES, 'UTF-8'); ?>">
                <p class="logo"><b style="color: #06C167;">ZeroPLATE</b></p>
                
                <!-- Food Details Section -->
                <div class="form-section">
                    <div class="section-title">
                        <i class="uil uil-utensils"></i> Food Details
                    </div>
                    
                    <div class="input">
                        <label for="foodname">Food Name <span class="required-field">*</span></label>
                        <input type="text" id="foodname" name="foodname" placeholder="e.g., Rice, Roti, Curry" required/>
                    </div>
                    
                    <div class="radio">
                        <label for="meal">Meal Type <span class="required-field">*</span></label>
                        <br><br>
                        <input type="radio" name="meal" id="veg" value="veg" required/>
                        <label for="veg" style="padding-right: 40px;">Vegetarian</label>
                        <input type="radio" name="meal" id="Non-veg" value="Non-veg">
                        <label for="Non-veg">Non-Vegetarian</label>
                    </div>
                    <br>
                    
                    <div class="input">
                        <label for="food">Food Category <span class="required-field">*</span></label>
                        <br><br>
                        <div class="image-radio-group">
                            <input type="radio" id="raw-food" name="image-choice" value="raw-food">
                            <label for="raw-food">
                                <img src="img/raw-food.png" alt="raw-food">
                                <div style="text-align: center; margin-top: 5px;">Raw Food</div>
                            </label>
                            <input type="radio" id="cooked-food" name="image-choice" value="cooked-food" checked>
                            <label for="cooked-food">
                                <img src="img/cooked-food.png" alt="cooked-food">
                                <div style="text-align: center; margin-top: 5px;">Cooked Food</div>
                            </label>
                            <input type="radio" id="packed-food" name="image-choice" value="packed-food">
                            <label for="packed-food">
                                <img src="img/packed-food.png" alt="packed-food">
                                <div style="text-align: center; margin-top: 5px;">Packed Food</div>
                            </label>
                        </div>
                    </div>
                    
                    <div class="input">
                        <label for="quantity">Quantity <span class="required-field">*</span></label>
                        <input type="text" id="quantity" name="quantity" placeholder="e.g., 5kg, 10 plates, 20 packets" required/>
                        <small style="color: #666;">Specify quantity (kg/plates/packets)</small>
                    </div>
                    <div class="input" style="margin-top:16px;">
                        <label for="food_condition">Food Usability <span class="required-field">*</span></label>
                        <select id="food_condition" name="food_condition" required style="padding:10px; width:100%; border-radius:5px; border:1px solid #ddd;">
                            <option value="edible" selected>Edible (human consumption)</option>
                            <option value="non_edible">Yale / Non-edible (for processing/fertilizer)</option>
                        </select>
                        <small id="foodConditionHelp" style="color:#666;">Edible donations are routed to NGOs first. Yale/non-edible donations are routed to processors/fertilizer flow.</small>
                    </div>
                    <div class="input">
                        <label for="prepared_at">Prepared/Cooked At (optional)</label>
                        <input type="datetime-local" id="prepared_at" name="prepared_at" style="width:100%;max-width:320px;padding:10px;border-radius:5px;border:1px solid #ddd;">
                    </div>
                    <div class="input">
                        <label for="best_before_at">Best Before (recommended)</label>
                        <input type="datetime-local" id="best_before_at" name="best_before_at" style="width:100%;max-width:320px;padding:10px;border-radius:5px;border:1px solid #ddd;">
                        <small id="bestBeforeHint" style="color:#666;">Required for edible donations. Used for auto-routing to NGO vs processor.</small>
                    </div>
                    <div class="input" style="margin-top:16px;">
                        <label style="display:flex;align-items:center;gap:10px;cursor:pointer;">
                            <input type="checkbox" name="requires_cold_chain" value="1">
                            This donation needs cold chain (refrigeration) until pickup
                        </label>
                        <label for="pickup_deadline" style="display:block;margin-top:12px;">Preferred pickup by (optional)</label>
                        <input type="datetime-local" id="pickup_deadline" name="pickup_deadline" style="width:100%;max-width:280px;padding:10px;border-radius:5px;border:1px solid #ddd;">
                        <small style="color:#666;">Helps partners plan time-sensitive pickups.</small>
                    </div>
                </div>

                <!-- Contact Details Section -->
                <div class="form-section">
                    <div class="section-title">
                        <i class="uil uil-user"></i> Contact Details
                    </div>
                    
                    <div class="input">
                        <div>
                            <label for="name">Your Name <span class="required-field">*</span></label>
                            <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($_SESSION['name']); ?>" required/>
                        </div>
                        <div>
                            <label for="phoneno">Phone Number <span class="required-field">*</span></label>
                            <input type="text" id="phoneno" name="phoneno" maxlength="10" pattern="[0-9]{10}" placeholder="10-digit mobile number" required/>
                        </div>
                    </div>
                </div>

                <!-- Location Details Section -->
                <div class="form-section">
                    <div class="section-title">
                        <i class="uil uil-map-marker"></i> Location Details
                    </div>
                    
                    <div class="input">
                        <label for="district">District <span class="required-field">*</span></label>
                        <select id="district" name="district" style="padding:10px; width: 100%; border-radius: 5px; border: 1px solid #ddd;" required>
                            <option value="bareilly" selected>Bareilly</option>
                            <option value="lucknow">Lucknow</option>
                            <option value="kanpur">Kanpur</option>
                            <option value="agra">Agra</option>
                            <option value="varanasi">Varanasi</option>
                            <option value="prayagraj">Prayagraj</option>
                            <option value="meerut">Meerut</option>
                            <option value="ghaziabad">Ghaziabad</option>
                            <option value="noida">Noida</option>
                            <option value="gorakhpur">Gorakhpur</option>
                            <option value="aligarh">Aligarh</option>
                            <option value="moradabad">Moradabad</option>
                            <option value="saharanpur">Saharanpur</option>
                            <option value="jhansi">Jhansi</option>
                            <option value="mathura">Mathura</option>
                            <option value="ayodhya">Ayodhya</option>
                            <option value="shahjahanpur">Shahjahanpur</option>
                            <option value="firozabad">Firozabad</option>
                            <option value="muzaffarnagar">Muzaffarnagar</option>
                            <option value="sultanpur">Sultanpur</option>
                            <option value="raebareli">Raebareli</option>
                            <option value="sitapur">Sitapur</option>
                            <option value="hardoi">Hardoi</option>
                            <option value="unnao">Unnao</option>
                            <option value="lakhimpur">Lakhimpur</option>
                            <option value="etawah">Etawah</option>
                            <option value="mainpuri">Mainpuri</option>
                            <option value="budaun">Budaun</option>
                            <option value="pilibhit">Pilibhit</option>
                            <option value="shamli">Shamli</option>
                        </select>
                    </div>
                    
                    <div class="input">
                        <label for="address">Pickup Address <span class="required-field">*</span></label>
                        <textarea id="address" name="address" rows="3" placeholder="Enter complete address where food can be picked up" required style="width: 100%; padding: 10px; border-radius: 5px; border: 1px solid #ddd;"></textarea>
                    </div>
                </div>

                <!-- Delivery Method Section -->
                <div class="form-section">
                    <div class="section-title">
                        <i class="uil uil-truck"></i> Delivery Method
                    </div>
                    
                    <div class="info-box">
                        <i class="uil uil-info-circle"></i>
                        <strong>Choose how you want to deliver:</strong>
                        <ul style="margin: 10px 0 0 20px;">
                            <li><strong>Self Delivery:</strong> You will deliver directly to the needy</li>
                            <li><strong>Delivery Partner:</strong> Our delivery partner will collect and deliver</li>
                        </ul>
                    </div>
                    
                    <div class="delivery-method-group">
                        <div class="delivery-option" onclick="selectDeliveryMethod('self')">
                            <input type="radio" name="delivery_method" id="delivery_self" value="self">
                            <label for="delivery_self">
                                <i class="uil uil-user-circle"></i>
                                <div style="font-weight: bold; margin-top: 10px;">Self Delivery</div>
                                <div style="font-size: 12px; color: #666; margin-top: 5px;">I will deliver directly</div>
                            </label>
                        </div>
                        
                        <div class="delivery-option selected" onclick="selectDeliveryMethod('partner')">
                            <input type="radio" name="delivery_method" id="delivery_partner" value="partner" checked>
                            <label for="delivery_partner">
                                <i class="uil uil-truck"></i>
                                <div style="font-weight: bold; margin-top: 10px;">Delivery Partner</div>
                                <div style="font-size: 12px; color: #666; margin-top: 5px;">Partner will collect & deliver</div>
                            </label>
                        </div>
                    </div>
                    
                    <!-- Self Delivery Additional Fields -->
                    <div class="conditional-field" id="selfDeliveryFields">
                        <h4 style="margin-top: 0; color: #06C167;">
                            <i class="uil uil-map-marker-alt"></i> Self Delivery Details
                        </h4>
                        <div class="input">
                            <label for="delivery_address">Delivery Address <span class="required-field">*</span></label>
                            <textarea id="delivery_address" name="delivery_address" rows="3" placeholder="Where will you deliver the food?" style="width: 100%; padding: 10px; border-radius: 5px; border: 1px solid #ddd;"></textarea>
                        </div>
                        <div class="input">
                            <label for="delivery_instructions">Delivery Instructions</label>
                            <textarea id="delivery_instructions" name="delivery_instructions" rows="2" placeholder="Any special instructions for delivery?" style="width: 100%; padding: 10px; border-radius: 5px; border: 1px solid #ddd;"></textarea>
                        </div>
                        <div class="info-box">
                            <i class="uil uil-clock"></i>
                            <strong>Note:</strong> Please ensure food is delivered within 2 hours of preparation for safety.
                        </div>
                    </div>
                    
                    <!-- Delivery Partner Info -->
                    <div class="conditional-field active" id="partnerDeliveryInfo">
                        <div class="info-box">
                            <i class="uil uil-check-circle"></i>
                            <strong>Delivery Partner Process:</strong>
                            <ol style="margin: 10px 0 0 20px;">
                                <li>Admin will review your donation</li>
                                <li>Admin will assign a delivery partner</li>
                                <li>Delivery partner will collect from your address</li>
                                <li>Food will be delivered to needy people</li>
                                <li>You'll receive real-time updates on status</li>
                            </ol>
                        </div>
                    </div>
                </div>

                <div class="btn">
                    <button type="submit" name="submit" style="width: 100%; padding: 15px; font-size: 16px;">
                        <i class="uil uil-heart"></i> Submit Donation
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script src="https://unicons.iconscout.com/release/v4.0.0/script/monochrome/bundle.js"></script>
    <script>
        function selectDeliveryMethod(method) {
            // Update radio button
            document.getElementById('delivery_' + method).checked = true;
            
            // Update visual selection
            document.querySelectorAll('.delivery-option').forEach(opt => {
                opt.classList.remove('selected');
            });
            event.currentTarget.classList.add('selected');
            
            // Show/hide conditional fields
            if(method === 'self') {
                document.getElementById('selfDeliveryFields').classList.add('active');
                document.getElementById('partnerDeliveryInfo').classList.remove('active');
                document.getElementById('delivery_address').required = true;
            } else {
                document.getElementById('selfDeliveryFields').classList.remove('active');
                document.getElementById('partnerDeliveryInfo').classList.add('active');
                document.getElementById('delivery_address').required = false;
            }
        }
        
        // Form validation
        document.getElementById('donationForm').addEventListener('submit', function(e) {
            const deliveryMethod = document.querySelector('input[name="delivery_method"]:checked').value;
            const foodCondition = document.getElementById('food_condition').value;
            const bestBefore = document.getElementById('best_before_at').value;
            const preparedAt = document.getElementById('prepared_at').value;
            
            if(deliveryMethod === 'self') {
                const deliveryAddress = document.getElementById('delivery_address').value.trim();
                if(!deliveryAddress) {
                    e.preventDefault();
                    alert('Please provide delivery address for self-delivery');
                    return false;
                }
            }
            if (foodCondition === 'edible' && !bestBefore) {
                e.preventDefault();
                alert('Best Before is required for edible donations.');
                return false;
            }
            if (bestBefore && new Date(bestBefore).getTime() <= Date.now()) {
                e.preventDefault();
                alert('Best Before must be in the future.');
                return false;
            }
            if (preparedAt && bestBefore && new Date(preparedAt).getTime() > new Date(bestBefore).getTime()) {
                e.preventDefault();
                alert('Prepared time cannot be later than Best Before.');
                return false;
            }
        });

        function updateFoodConditionUI() {
            const condition = document.getElementById('food_condition').value;
            const bestBeforeInput = document.getElementById('best_before_at');
            const bestBeforeHint = document.getElementById('bestBeforeHint');
            if (condition === 'edible') {
                bestBeforeInput.required = true;
                bestBeforeHint.textContent = 'Required for edible donations. Used for auto-routing to NGO vs processor.';
            } else {
                bestBeforeInput.required = false;
                bestBeforeHint.textContent = 'Optional for Yale/non-edible donations (processor/fertilizer route).';
            }
        }
        document.getElementById('food_condition').addEventListener('change', updateFoodConditionUI);
        updateFoodConditionUI();
    </script>
</body>
</html>
