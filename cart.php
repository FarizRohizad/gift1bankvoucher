<?php
session_start();
include('connect.php'); // make sure this points to your DB connection

// Redirect to login if not authenticated
if (!isset($_SESSION['UserID'])) {
    header("Location: /../landingpage.php");
    exit();
}

$userID = $_SESSION['UserID'];

// Fetch latest info from DB with error handling
$sql = "SELECT User_Name, User_Role, User_Points FROM users WHERE User_ID = ?";
$stmt = $conn->prepare($sql);

if (!$stmt) {
    // Log error and use session data
    error_log("Database error: " . $conn->error);
    $userName   = $_SESSION['UserName'];
    $userRole   = $_SESSION['UserRole'];
    $userPoints = isset($_SESSION['UserPoints']) ? $_SESSION['UserPoints'] : 0;
} else {
    $stmt->bind_param("i", $userID);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $row = $result->fetch_assoc()) {
        $userName   = $row['User_Name'];
        $userRole   = $row['User_Role'];
        $userPoints = $row['User_Points'];
        $_SESSION['UserPoints'] = $userPoints;
        $_SESSION['UserName'] = $userName;
    } else {
        $userName   = $_SESSION['UserName'];
        $userRole   = $_SESSION['UserRole'];
        $userPoints = isset($_SESSION['UserPoints']) ? $_SESSION['UserPoints'] : 0;
    }
}

// Get cart items from session
$cartItems = isset($_SESSION['cart']) ? $_SESSION['cart'] : [];
$totalPoints = 0;
$cartItemCount = 0;
foreach ($cartItems as $item) {
    $totalPoints += $item['points'] * $item['quantity'];
    $cartItemCount += $item['quantity'];
}

// Handle update item quantity with plus/minus
if (isset($_POST['update_item_id']) && isset($_POST['action'])) {
    $updateId = filter_input(INPUT_POST, 'update_item_id', FILTER_VALIDATE_INT);
    $action   = $_POST['action'];

    if ($updateId !== false && $updateId !== null && isset($_SESSION['cart'][$updateId])) {
        if ($action === "increase") {
            $_SESSION['cart'][$updateId]['quantity']++;
            $_SESSION['toastr_success'] = "Increased quantity of " . htmlspecialchars($_SESSION['cart'][$updateId]['name']) . ".";
        } elseif ($action === "decrease") {
            if ($_SESSION['cart'][$updateId]['quantity'] > 1) {
                $_SESSION['cart'][$updateId]['quantity']--;
                $_SESSION['toastr_success'] = "Decreased quantity of " . htmlspecialchars($_SESSION['cart'][$updateId]['name']) . ".";
            } else {
                $removedItemName = $_SESSION['cart'][$updateId]['name'];
                unset($_SESSION['cart'][$updateId]);
                $_SESSION['toastr_success'] = htmlspecialchars($removedItemName) . " removed from cart.";
            }
        }
    } else {
        $_SESSION['toastr_error'] = "Failed to update item quantity.";
    }
    header("Location: /../cart.php");
    exit();
}

// Handle removing item from cart
if (isset($_POST['remove_item_id'])) {
    $removeId = filter_input(INPUT_POST, 'remove_item_id', FILTER_VALIDATE_INT);
    if ($removeId !== false && $removeId !== null && isset($_SESSION['cart'][$removeId])) {
        if ($_SESSION['cart'][$removeId]['quantity'] > 1) {
            $_SESSION['cart'][$removeId]['quantity']--;
            $_SESSION['toastr_success'] = "Quantity of " . htmlspecialchars($_SESSION['cart'][$removeId]['name']) . " decreased.";
        } else {
            $removedItemName = $_SESSION['cart'][$removeId]['name'];
            unset($_SESSION['cart'][$removeId]);
            $_SESSION['toastr_success'] = htmlspecialchars($removedItemName) . " removed from cart.";
        }
    } else {
        $_SESSION['toastr_error'] = "Failed to remove item.";
    }
    header("Location: /../cart.php");
    exit();
}

// Handle checkout
if (isset($_POST['checkout'])) {
    // Check if cart is empty before proceeding
    if (empty($cartItems)) {
        $_SESSION['toastr_error'] = "Your cart is empty. Please add items before checking out.";
        header("Location: /../cart.php");
        exit();
    }

    // Check if user has enough points
    if ($userPoints >= $totalPoints) {
        // 1. Deduct points from user in DB.
        $newPoints = $userPoints - $totalPoints;
        $updateSql = "UPDATE users SET User_Points = ? WHERE User_ID = ?";
        $updateStmt = $conn->prepare($updateSql);
        
        if (!$updateStmt) {
            $_SESSION['toastr_error'] = "Database error: " . $conn->error;
            header("Location: /../cart.php");
            exit();
        }
        
        $updateStmt->bind_param("ii", $newPoints, $userID);
        
        if (!$updateStmt->execute()) {
            $_SESSION['toastr_error'] = "An error occurred during point deduction. Please try again.";
            header("Location: /../cart.php");
            exit();
        }

        // 2. Record the redemption in the 'History' table.
        $conn->begin_transaction();
        $historyIds = [];
        
        try {
            foreach ($cartItems as $item) {
                // Fetch voucher details from Voucher table
                $voucherDetailSql = "SELECT voucherID, name FROM Voucher WHERE voucherID = ?";
                $voucherDetailStmt = $conn->prepare($voucherDetailSql);
                
                if (!$voucherDetailStmt) {
                    throw new Exception("Voucher query preparation failed: " . $conn->error);
                }
                
                $voucherDetailStmt->bind_param("i", $item['id']);
                $voucherDetailStmt->execute();
                $voucherDetailResult = $voucherDetailStmt->get_result();
                $voucherDetails = $voucherDetailResult->fetch_assoc();

                if (!$voucherDetails) {
                    throw new Exception("Voucher details not found for ID: " . $item['id']);
                }

                // Fixed SQL query - using dateBuy instead of transactionDate
                $insertHistorySql = "INSERT INTO History (userId, voucherID, quantity, pointCost, dateBuy) VALUES (?, ?, ?, ?, NOW())";
                $insertHistoryStmt = $conn->prepare($insertHistorySql);
                
                if (!$insertHistoryStmt) {
                    throw new Exception("History query preparation failed: " . $conn->error);
                }
                
                $itemTotalCost = $item['points'] * $item['quantity'];
                $insertHistoryStmt->bind_param("iiii", $userID, $item['id'], $item['quantity'], $itemTotalCost);
                
                if (!$insertHistoryStmt->execute()) {
                    throw new Exception("Error inserting into history: " . $insertHistoryStmt->error);
                }
                
                $historyIds[] = $conn->insert_id;
            }
            
            $conn->commit();
            $_SESSION['cart'] = [];
            $_SESSION['UserPoints'] = $newPoints; // Update session points
            $_SESSION['toastr_success'] = "Checkout successful! Your rewards have been processed.";
            $_SESSION['last_checkout_history_ids'] = $historyIds;

            header("Location: /../generate_receipt_pdf.php");
            exit();

        } catch (Exception $e) {
            $conn->rollback();
            error_log("Checkout failed: " . $e->getMessage());
            $_SESSION['toastr_error'] = "An error occurred while recording your redemption. Please try again.";
            header("Location: /../cart.php");
            exit();
        }
    } else {
        $_SESSION['toastr_error'] = "Not enough points to complete this checkout! You need " . number_format($totalPoints) . " points, but only have " . number_format($userPoints) . " points.";
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
        .quantity-controls {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .quantity-btn {
            background: #31c6f6;
            color: white;
            border: none;
            padding: 5px 12px;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            transition: background 0.3s;
        }
        .quantity-btn:hover {
            background: #0e499f;
        }
        .quantity-display {
            min-width: 30px;
            text-align: center;
            font-weight: bold;
            color: #0e499f;
        }
        @media(max-width: 800px) {
            .container { 
                max-width: 98vw; 
                padding: 1.5rem;
                margin: 1rem auto;
            }
            .header { 
                padding: 1rem 1rem; 
                flex-direction: column;
                gap: 1rem;
            }
            .nav-links { 
                flex-wrap: wrap;
                justify-content: center;
                gap: 1rem;
            }
            .nav-links a { 
                margin-left: 0; 
            }
            .logo { 
                font-size: 1.5rem; 
            }
            .logo::before { 
                font-size: 1.5rem; 
            }
            .cart-summary { 
                flex-direction: column; 
                align-items: flex-start; 
                gap: 1rem;
            }
            .checkout-btn { 
                margin-top: 1rem; 
                width: 100%;
                text-align: center;
            }
            .cart-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            .quantity-controls {
                align-self: flex-end;
            }
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
                            <div class="item-points"><?php echo number_format($item['points']); ?> Points each</div>
                            <div class="item-quantity">Quantity: <?php echo $item['quantity']; ?></div>
                            <div class="item-points">Subtotal: <?php echo number_format($item['points'] * $item['quantity']); ?> Points</div>
                        </div>

                        <div class="quantity-controls">
                            <!-- Decrease button -->
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="update_item_id" value="<?php echo $itemId; ?>">
                                <input type="hidden" name="action" value="decrease">
                                <button type="submit" class="quantity-btn">-</button>
                            </form>

                            <!-- Quantity display -->
                            <span class="quantity-display">
                                <?php echo $item['quantity']; ?>
                            </span>

                            <!-- Increase button -->
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="update_item_id" value="<?php echo $itemId; ?>">
                                <input type="hidden" name="action" value="increase">
                                <button type="submit" class="quantity-btn">+</button>
                            </form>

                            <!-- Remove button -->
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="remove_item_id" value="<?php echo $itemId; ?>">
                                <button type="submit" class="remove-btn">Remove</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="cart-summary">
                <div>
                    <div>Total Points Required: <span class="total-points"><?php echo number_format($totalPoints); ?></span></div>
                    <div style="margin-top: 0.5rem; font-size: 1rem; color: #555;">
                        Your available points: <strong><?php echo number_format($userPoints); ?></strong>
                    </div>
                </div>
                <form method="post">
                    <button type="submit" name="checkout" class="checkout-btn">Checkout Now</button>
                </form>
            </div>
            
            <?php if ($userPoints < $totalPoints): ?>
                <div style="text-align: center; margin-top: 1rem; padding: 1rem; background: #fff3cd; border-radius: 5px; color: #856404;">
                    <strong>Notice:</strong> You don't have enough points for this purchase. 
                    <a href="/../rewards.php" style="color: #0e499f; text-decoration: underline;">Earn more points</a>
                </div>
            <?php endif; ?>
            
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

            // Update cart count dynamically
            function updateCartCount() {
                let total = 0;
                <?php 
                $cartItems = isset($_SESSION['cart']) ? $_SESSION['cart'] : [];
                foreach ($cartItems as $item) {
                    echo "total += " . $item['quantity'] . ";\n";
                }
                ?>
                $('#cart-item-count').text(total);
            }

            // Update cart count when page loads
            updateCartCount();
        });
    </script>
</body>
</html>
