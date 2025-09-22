<?php
session_start();
include('connect.php'); // make sure this points to your DB connection

// Redirect to login if not authenticated
if (!isset($_SESSION['UserID'])) {
    header("Location: landingpage.php");
    exit();
}

$userID = $_SESSION['UserID'];

// Fetch latest info from DB
$sql = "SELECT User_Name, User_Role, User_Points FROM users WHERE User_ID = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userID);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $row = $result->fetch_assoc()) {
    $userName   = $row['User_Name'];
    $userRole   = $row['User_Role'];
    $userPoints = $row['User_Points'];

    // Optional: update session for consistency
    $_SESSION['UserName']   = $userName;
    $_SESSION['UserRole']   = $userRole;
    $_SESSION['UserPoints'] = $userPoints;
} else {
    // fallback kalau query failed
    $userName   = $_SESSION['UserName'];
    $userRole   = $_SESSION['UserRole'];
    $userPoints = isset($_SESSION['UserPoints']) ? $_SESSION['UserPoints'] : 0;
}

// Initialize cart if not already set
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}
// Calculate cart item count (sum of quantities)
$cartItemCount = 0;
foreach ($_SESSION['cart'] as $item) {
    $cartItemCount += $item['quantity'];
}


// --- Fetch vouchers from the database ---
$vouchers = [];
$sql_vouchers = "SELECT voucherID, name, cost, categoryName FROM Voucher ORDER BY cost ASC LIMIT 4"; // Fetching up to 4 featured vouchers
$result_vouchers = $conn->query($sql_vouchers);

if ($result_vouchers) {
    while ($voucher_row = $result_vouchers->fetch_assoc()) {
        $vouchers[] = $voucher_row;
    }
} else {
    // Handle error if voucher query fails
    error_log("Error fetching vouchers: " . $conn->error);
}

// Map categoryName to an icon (optional, you can expand this)
function getVoucherIcon($categoryName) {
    switch ($categoryName) {
        case 'Shopping': return '🎁';
        case 'Food & Beverage': return '🍽️';
        case 'Automotive': return '🚗';
        case 'E-commerce': return '🛍️';
        default: return '🌟'; // Default icon
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OptimaBank Loyalty - Home</title>
    <link rel="stylesheet" href="toastr.min.css">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script src="toastr.min.js"></script>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f4f7fc;
            margin: 0;
            padding: 0;
        }
        .header {
            background: linear-gradient(135deg, #0e499f, #31c6f6);
            color: white;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .logo {
            font-size: 2rem;
            font-weight: bold;
            display: flex;
            align-items: center;
        }
        .logo::before {
            content: '🏦';
            font-size: 1.8rem;
            margin-right: 8px;
        }
        .nav-links {
            display: flex;
            align-items: center;
        }
        .nav-links a {
            color: #fff;
            text-decoration: none;
            font-weight: 500;
            margin-left: 2rem;
            transition: color 0.3s;
            position: relative; /* For cart item count */
        }
        .nav-links a:hover {
            color: #ffe165;
        }
        .cart-count {
            background-color: #ffe165;
            color: #0e499f;
            border-radius: 50%;
            padding: 2px 7px;
            font-size: 0.75rem;
            font-weight: bold;
            position: absolute;
            top: -8px;
            right: -12px;
            line-height: 1;
        }
        .welcome-section {
            background: #fff;
            margin: 2rem auto;
            max-width: 700px;
            padding: 2rem 2rem 1rem 2rem;
            border-radius: 20px;
            box-shadow: 0 8px 28px rgba(14,73,159,0.07);
            text-align: center;
        }
        .welcome-section h1 {
            margin-bottom: 1rem;
            font-size: 2.2rem;
            color: #0e499f;
        }
        .welcome-section p {
            font-size: 1.2rem;
            color: #555;
        }
        .points-card {
            background: linear-gradient(135deg, #31c6f6 0%, #0e499f 100%);
            color: white;
            border-radius: 15px;
            padding: 1.5rem;
            max-width: 350px;
            margin: 2rem auto 1rem auto;
            box-shadow: 0 4px 18px rgba(49,198,246,0.12);
            text-align: center;
        }
        .points-card h2 {
            font-size: 1.3rem;
            margin-bottom: 0.6rem;
        }
        .points-balance {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }
        .btn {
            display: inline-block;
            background: linear-gradient(135deg, #0e499f, #31c6f6);
            color: white;
            padding: 0.75rem 2rem;
            border: none;
            border-radius: 50px;
            font-weight: 600;
            text-decoration: none;
            margin: 1rem 0.5rem 0 0.5rem;
            transition: background 0.3s, box-shadow 0.3s;
            cursor: pointer;
            box-shadow: 0 2px 8px rgba(49,198,246,0.13);
        }
        .btn:hover {
            background: #31c6f6;
            color: #0e499f;
            box-shadow: 0 6px 18px rgba(14,73,159,0.13);
        }
        .offers-section {
            background: #fff;
            margin: 2rem auto;
            border-radius: 20px;
            max-width: 700px;
            box-shadow: 0 8px 28px rgba(14,73,159,0.07);
            padding: 2rem;
        }
        .offers-title {
            font-size: 1.4rem;
            color: #0e499f;
            margin-bottom: 1.5rem;
            text-align: center;
        }
        .offer-list {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.3rem;
        }
        .offer-card {
            background: linear-gradient(135deg, #eaf6ff 80%, #f4f7fc 100%);
            border-radius: 15px;
            padding: 1rem;
            text-align: center;
            box-shadow: 0 2px 8px rgba(49,198,246,0.08);
        }
        .offer-icon {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
        }
        .offer-name {
            font-weight: 600;
            color: #0e499f;
            margin-bottom: 0.3rem;
        }
        .offer-points {
            color: #31c6f6;
            font-weight: bold;
            margin-bottom: 0.2rem;
        }
        .offer-desc {
            color: #555;
            font-size: 0.97rem;
        }
        .offer-card .btn {
            margin-top: 0.7rem;
            padding: 0.5rem 1.2rem;
            font-size: 1rem;
        }
        .footer {
            background: #143c6b; color: white;
            padding: 2rem 1rem 1rem;
            text-align: center;
            margin-top: 3rem;
        }
        .logout-btn {
            background: #ffe165;
            color: #0e499f;
            border: none;
            padding: 0.5rem 1.2rem;
            border-radius: 50px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
        }
        .logout-btn:hover {
            background: #ffbc00;
            color: #fff;
        }
        @media(max-width: 800px) {
            .welcome-section, .offers-section { max-width: 98vw; }
        }
    </style>
</head>
<body>
    
    <div class="header">
        <div class="logo">OptimaBank Loyalty</div>
        <div class="nav-links">
            <a href="home.php">Home</a>
            <a href="rewards.php">Voucher</a>
            <a href="profile.php">Profile</a>
            <a href="cart.php">Cart <span class="cart-count" id="cart-item-count"><?php echo $cartItemCount; ?></span></a>
            <form style="display:inline;" method="post" action="logout.php">
                <button type="submit" class="logout-btn">Log Out</button>
            </form>
        </div>
    </div>
    <div class="welcome-section">
        <h1>Hello, <?php echo htmlspecialchars($userName); ?>!</h1>
        <p>
            Welcome to OptimaBank's Loyalty Program.<br>
            Here, your everyday banking earns you points and rewards.<br>
            <strong>Role:</strong> <?php echo ucfirst(htmlspecialchars($userRole)); ?>
        </p>
        <div class="points-card">
            <h2>Your Loyalty Points</h2>
            <div class="points-balance" id="points-balance"><?php echo number_format($userPoints); ?></div>
            <div>Earn more points by making transactions, referrals, or redeeming special offers.</div>
        </div>
        <a href="rewards.php" class="btn">Redeem Rewards</a>
        <a href="profile.php" class="btn">My Profile</a>
    </div>
    <div class="offers-section">
        <div class="offers-title">Featured Offers</div>
        <div class="offer-list">
            <?php foreach ($vouchers as $voucher): ?>
            <div class="offer-card">
                <div class="offer-icon"><?php echo getVoucherIcon($voucher['categoryName']); ?></div>
                <div class="offer-name"><?php echo htmlspecialchars($voucher['name']); ?></div>
                <div class="offer-points"><?php echo number_format($voucher['cost']); ?> Points</div>
                <div class="offer-desc">
                    <?php 
                        // You'll likely need a 'description' column in your Voucher table
                        // For now, I'll use a generic description based on the name.
                        switch($voucher['name']) {
                            case 'RM50 Shopping Voucher': echo 'Spend at selected retailers and partners.'; break;
                            case 'Dining Discount': echo 'Enjoy 20% off at top restaurants in Malaysia.'; break;
                            case 'Petrol Cashback': echo 'Get RM30 cashback on your next petrol refill.'; break;
                            case 'Online Store Voucher': echo 'RM25 voucher for popular online shops.'; break;
                            default: echo 'A special offer just for you!'; break;
                        }
                    ?>
                </div>
                <!-- Changed back to button with data attributes for AJAX -->
                <button class="btn add-to-cart-btn" 
                        data-id="<?php echo htmlspecialchars($voucher['voucherID']); ?>" 
                        data-name="<?php echo htmlspecialchars($voucher['name']); ?>" 
                        data-points="<?php echo htmlspecialchars($voucher['cost']); ?>">Add to Cart</button>
            </div>
            <?php endforeach; ?>
            <?php if (empty($vouchers)): ?>
                <p style="grid-column: 1 / -1; text-align: center; color: #777;">No featured offers available at the moment.</p>
            <?php endif; ?>
        </div>
    </div>
    <div class="footer">
        &copy; <?php echo date("Y"); ?> OptimaBank Loyalty. All rights reserved.
    </div>
    <script>
        $(document).ready(function() {
            toastr.options = {
                "closeButton": false,
                "debug": false,
                "newestOnTop": false,
                "progressBar": false,
                "positionClass": "toast-top-center",
                "preventDuplicates": false,
                "onclick": null,
                "showDuration": "300",
                "hideDuration": "1000",
                "timeOut": "4000",
                "extendedTimeOut": "1000",
                "showEasing": "swing",
                "hideEasing": "linear",
                "showMethod": "fadeIn",
                "hideMethod": "fadeOut"
            };
            <?php if (isset($_SESSION['welcome'])): ?>
                toastr.success("<?php echo addslashes($_SESSION['welcome']); ?>");
                <?php unset($_SESSION['welcome']); ?>
            <?php endif; ?>

            // Display Toastr messages if set in session by other pages (like cart.php)
            <?php if (isset($_SESSION['toastr_success'])): ?>
                toastr.success("<?php echo addslashes($_SESSION['toastr_success']); ?>");
                <?php unset($_SESSION['toastr_success']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['toastr_error'])): ?>
                toastr.error("<?php echo addslashes($_SESSION['toastr_error']); ?>");
                <?php unset($_SESSION['toastr_error']); ?>
            <?php endif; ?>

            // Handle Add to Cart button clicks
            $('.add-to-cart-btn').on('click', function() {
                const itemId = $(this).data('id');
                const itemName = $(this).data('name');
                const itemPoints = $(this).data('points');

                $.ajax({
                    url: 'add_to_cart.php', // Path to your new AJAX script
                    type: 'POST',
                    data: {
                        id: itemId,
                        name: itemName,
                        points: itemPoints
                    },
                    dataType: 'json', // Expect JSON response
                    success: function(res) {
                        if (res.status === 'success') {
                            toastr.success(res.message);
                            // Update cart count from the response
                            $('#cart-item-count').text(res.cartTotalQuantity);
                        } else {
                            toastr.error(res.message);
                        }
                    },
                    error: function() {
                        toastr.error("Error adding item to cart.");
                    }
                });
            });
        });
    </script>
</body>
</html>