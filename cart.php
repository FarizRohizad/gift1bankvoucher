<?php
session_start();
include('connect.php'); // make sure this points to your DB connection

// Redirect to login if not authenticated
if (!isset($_SESSION['UserID'])) {
    header("Location: /../landingpage.php");
    exit();
}

$userID = $_SESSION['UserID'];

// Fetch latest info from DB (for header display, points, etc.)
$sql = "SELECT User_Name, User_Role, User_Points FROM users WHERE User_ID = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userID);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $row = $result->fetch_assoc()) {
    $userName   = $row['User_Name'];
    $userRole   = $row['User_Role'];
    $userPoints = $row['User_Points'];
    // Update session points from DB to ensure it's current
    $_SESSION['UserPoints'] = $userPoints;
} else {
    $userName   = $_SESSION['UserName'];
    $userRole   = $_SESSION['UserRole'];
    $userPoints = isset($_SESSION['UserPoints']) ? $_SESSION['UserPoints'] : 0;
}

// Get cart items from session
$cartItems = isset($_SESSION['cart']) ? $_SESSION['cart'] : [];
$totalPoints = 0;
foreach ($cartItems as $item) {
    $totalPoints += $item['points'] * $item['quantity'];
}
$cartItemCount = count($cartItems);

// Handle removing item from cart
if (isset($_POST['remove_item_id'])) {
    $removeId = filter_input(INPUT_POST, 'remove_item_id', FILTER_VALIDATE_INT);
    if ($removeId !== false && $removeId !== null && isset($_SESSION['cart'][$removeId])) {
        unset($_SESSION['cart'][$removeId]);
        $_SESSION['toastr_success'] = "Item removed from cart.";
    } else {
        $_SESSION['toastr_error'] = "Failed to remove item.";
    }
    header("Location: /../cart.php"); // Redirect to refresh cart page
    exit();
}

// Handle checkout
if (isset($_POST['checkout'])) {
    if ($userPoints >= $totalPoints) {
        // --- REAL APPLICATION LOGIC HERE ---
        // 1. Deduct points from user in DB.
        //    Example: $newPoints = $userPoints - $totalPoints;
        //    $updateSql = "UPDATE users SET User_Points = ? WHERE User_ID = ?";
        //    $updateStmt = $conn->prepare($updateSql);
        //    $updateStmt->bind_param("ii", $newPoints, $userID);
        //    $updateStmt->execute();

        // 2. Record the redemption in a 'transactions' or 'orders' table.
        //    Store $cartItems, $userID, $totalPoints, timestamp, etc.

        // 3. Generate vouchers/codes for the redeemed items if applicable.

        // --- SIMULATION FOR DEMONSTRATION ---
        $_SESSION['cart'] = []; // Clear the cart
        // Simulate point deduction in session (for immediate display)
        $_SESSION['UserPoints'] -= $totalPoints; 
        $_SESSION['toastr_success'] = "Checkout successful! Your items will be processed and points deducted.";
        
        // Redirect to home or a confirmation page
        header("Location: /../home.php");
        exit();
    } else {
        $_SESSION['toastr_error'] = "Not enough points to complete this checkout!";
        header("Location: /../cart.php");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OptimaBank Loyalty - My Cart</title>
    <link rel="stylesheet" href="/../toastr.min.css">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script src="/../toastr.min.js"></script>
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
            position: relative;
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
        .container {
            max-width: 800px;
            margin: 2rem auto;
            background: #fff;
            padding: 2.5rem;
            border-radius: 20px;
            box-shadow: 0 8px 28px rgba(14,73,159,0.07);
        }
        h1 {
            font-size: 2.5rem;
            color: #0e499f;
            text-align: center;
            margin-bottom: 2rem;
        }
        .cart-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid #eee;
            padding: 1rem 0;
        }
        .cart-item:last-child {
            border-bottom: none;
        }
        .item-details {
            flex-grow: 1;
        }
        .item-name {
            font-weight: bold;
            font-size: 1.1rem;
            color: #0e499f;
        }
        .item-points {
            color: #31c6f6;
            font-weight: 600;
            margin-top: 5px;
        }
        .item-quantity {
            margin-left: 1rem;
            color: #555;
        }
        .remove-btn {
            background: #ff6b6b;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            cursor: pointer;
            transition: background 0.3s;
        }
        .remove-btn:hover {
            background: #e04e4e;
        }
        .cart-summary {
            margin-top: 2rem;
            border-top: 2px solid #0e499f;
            padding-top: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 1.2rem;
            font-weight: bold;
            color: #0e499f;
        }
        .total-points {
            font-size: 1.5rem;
            color: #31c6f6;
        }
        .checkout-btn {
            background: linear-gradient(135deg, #0e499f, #31c6f6);
            color: white;
            padding: 0.8rem 2.5rem;
            border: none;
            border-radius: 50px;
            font-weight: 600;
            text-decoration: none;
            transition: background 0.3s, box-shadow 0.3s;
            cursor: pointer;
            box-shadow: 0 2px 8px rgba(49,198,246,0.13);
            font-size: 1.1rem;
        }
        .checkout-btn:hover {
            background: #31c6f6;
            color: #0e499f;
            box-shadow: 0 6px 18px rgba(14,73,159,0.13);
        }
        .empty-cart {
            text-align: center;
            padding: 3rem 0;
            color: #777;
            font-size: 1.2rem;
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
            .container { max-width: 98vw; }
            .header { padding: 1rem 1rem; }
            .nav-links a { margin-left: 1rem; }
            .logo { font-size: 1.5rem; }
            .logo::before { font-size: 1.5rem; }
            .cart-summary { flex-direction: column; align-items: flex-start; }
            .checkout-btn { margin-top: 1rem; }
        }
    </style>
</head>
<body>
    
    <div class="header">
        <div class="logo">OptimaBank Loyalty</div>
        <div class="nav-links">
            <a href="/../home.php">Home</a>
            <a href="/../rewards.php">Voucher</a>
            <a href="/../profile.php">Profile</a>
            <a href="/../cart.php">Cart <span class="cart-count" id="cart-item-count"><?php echo $cartItemCount; ?></span></a>
            <form style="display:inline;" method="post" action="/../logout.php">
                <button type="submit" class="logout-btn">Log Out</button>
            </form>
        </div>
    </div>

    <div class="container">
        <h1>Your Shopping Cart</h1>

        <?php if (empty($cartItems)): ?>
            <div class="empty-cart">Your cart is empty. Start adding some exciting rewards!</div>
            <div style="text-align: center; margin-top: 2rem;">
                <a href="/../home.php" class="checkout-btn">Continue Shopping</a>
            </div>
        <?php else: ?>
            <div class="cart-list">
                <?php foreach ($cartItems as $itemId => $item): ?>
                    <div class="cart-item">
                        <div class="item-details">
                            <div class="item-name"><?php echo htmlspecialchars($item['name']); ?></div>
                            <div class="item-points"><?php echo number_format($item['points']); ?> Points x <?php echo $item['quantity']; ?></div>
                        </div>
                        <form method="post" style="display:inline;">
                            <input type="hidden" name="remove_item_id" value="<?php echo $itemId; ?>">
                            <button type="submit" class="remove-btn">Remove</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="cart-summary">
                <div>Total Points Required: <span class="total-points"><?php echo number_format($totalPoints); ?></span></div>
                <form method="post">
                    <button type="submit" name="checkout" class="checkout-btn">Checkout Now</button>
                </form>
            </div>
            <p style="text-align: center; margin-top: 1rem; color: #555;">
                Your available points: <strong><?php echo number_format($userPoints); ?></strong>
            </p>
        <?php endif; ?>
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

            // Display Toastr messages if set in session
            <?php if (isset($_SESSION['toastr_success'])): ?>
                toastr.success("<?php echo addslashes($_SESSION['toastr_success']); ?>");
                <?php unset($_SESSION['toastr_success']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['toastr_error'])): ?>
                toastr.error("<?php echo addslashes($_SESSION['toastr_error']); ?>");
                <?php unset($_SESSION['toastr_error']); ?>
            <?php endif; ?>
        });
    </script>
</body>
</html>
