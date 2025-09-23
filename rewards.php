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

// Get search and filter parameters
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$category_filter = isset($_GET['category']) ? $_GET['category'] : '';

// Build SQL query with filters
$sql = "SELECT voucherID, name, expiredDate, cost, categoryName FROM Voucher WHERE expiredDate >= CURDATE()";

// Add search filter if provided
if (!empty($search_query)) {
    $sql .= " AND name LIKE ?";
}

// Add category filter if provided
if (!empty($category_filter) && $category_filter !== 'all') {
    $sql .= " AND categoryName = ?";
}

$sql .= " ORDER BY categoryName, cost";

// Prepare and execute the query
$stmt = $conn->prepare($sql);

if (!empty($search_query) && !empty($category_filter) && $category_filter !== 'all') {
    $search_param = "%" . $search_query . "%";
    $stmt->bind_param("ss", $search_param, $category_filter);
} elseif (!empty($search_query)) {
    $search_param = "%" . $search_query . "%";
    $stmt->bind_param("s", $search_param);
} elseif (!empty($category_filter) && $category_filter !== 'all') {
    $stmt->bind_param("s", $category_filter);
}

$stmt->execute();
$result = $stmt->get_result();

// Get all available categories for the filter dropdown
$categories_sql = "SELECT DISTINCT categoryName FROM Voucher WHERE expiredDate >= CURDATE() ORDER BY categoryName";
$categories_result = $conn->query($categories_sql);
$available_categories = [];
if ($categories_result && $categories_result->num_rows > 0) {
    while ($row = $categories_result->fetch_assoc()) {
        $available_categories[] = $row['categoryName'];
    }
}

$vouchers = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $category = $row['categoryName'];
        if (!isset($vouchers[$category])) {
            $vouchers[$category] = [];
        }
        
        $vouchers[$category][] = [
            'id' => $row['voucherID'],
            'name' => $row['name'],
            'points' => $row['cost'],
            'expiredDate' => $row['expiredDate'],
            'description' => 'Valid until ' . date('M j, Y', strtotime($row['expiredDate']))
        ];
    }
}

// Handle voucher redemption
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['voucher_id']) && isset($_POST['action'])) {
    $voucher_id = intval($_POST['voucher_id']);
    $action = $_POST['action'];
    
    // Get voucher details
    $voucher_sql = "SELECT cost, name FROM Voucher WHERE voucherID = ?";
    $voucher_stmt = $conn->prepare($voucher_sql);
    $voucher_stmt->bind_param("i", $voucher_id);
    $voucher_stmt->execute();
    $voucher_result = $voucher_stmt->get_result();
    
    if ($voucher_result && $voucher_row = $voucher_result->fetch_assoc()) {
        $voucher_cost = $voucher_row['cost'];
        $voucher_name = $voucher_row['name'];
        
        if ($action === 'redeem') {
            // Handle direct redemption
            // Check if user has enough points
            if ($userPoints >= $voucher_cost) {
                // Start transaction
                $conn->begin_transaction();
                
                try {
                    // 1. Add to history
                    $history_sql = "INSERT INTO History (userId, voucherID, quantity, pointCost) VALUES (?, ?, 1, ?)";
                    $history_stmt = $conn->prepare($history_sql);
                    $history_stmt->bind_param("iii", $userID, $voucher_id, $voucher_cost);
                    $history_stmt->execute();
                    
                    // 2. Update user points
                    $update_sql = "UPDATE users SET User_Points = User_Points - ? WHERE User_ID = ?";
                    $update_stmt = $conn->prepare($update_sql);
                    $update_stmt->bind_param("ii", $voucher_cost, $userID);
                    $update_stmt->execute();
                    
                    // 3. Add to points table (debit)
                    $points_sql = "INSERT INTO Points (userId, debit) VALUES (?, ?)";
                    $points_stmt = $conn->prepare($points_sql);
                    $points_stmt->bind_param("ii", $userID, $voucher_cost);
                    $points_stmt->execute();
                    
                    // Commit transaction
                    $conn->commit();
                    
                    // Update session and local variables
                    $userPoints -= $voucher_cost;
                    $_SESSION['UserPoints'] = $userPoints;
                    
                    // Store redeemed voucher info for PDF download
                    $_SESSION['success_message_redeem'] = "Successfully redeemed '$voucher_name'!";
                    $_SESSION['redeemed_voucher_id'] = $voucher_id;
                    $_SESSION['redeemed_voucher_name'] = $voucher_name;

                } catch (Exception $e) {
                    // Rollback transaction on error
                    $conn->rollback();
                    $_SESSION['error_message'] = "Error redeeming voucher: " . $e->getMessage();
                }
            } else {
                $_SESSION['error_message'] = "You don't have enough points to redeem this voucher.";
            }
        } elseif ($action === 'add_to_cart') {
            // Handle add to cart
            $found = false;
            foreach ($_SESSION['cart'] as &$item) {
                if ($item['id'] == $voucher_id) {
                    $item['quantity'] += 1;
                    $found = true;
                    break;
                }
            }
            
            if (!$found) {
                $_SESSION['cart'][] = [
                    'id' => $voucher_id,
                    'name' => $voucher_name,
                    'points' => $voucher_cost,
                    'quantity' => 1
                ];
            }
            
            // Update cart count
            $cartItemCount = 0;
            foreach ($_SESSION['cart'] as $item) {
                $cartItemCount += $item['quantity'];
            }
            
            $_SESSION['success_message'] = "'$voucher_name' added to cart!";
        }
    } else {
        $_SESSION['error_message'] = "Voucher not found.";
    }
    
    // Redirect to avoid form resubmission
    header("Location: " . $_SERVER['PHP_SELF'] . (!empty($_GET) ? '?' . http_build_query($_GET) : ''));
    exit();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OptimaBank Loyalty - Rewards</title>
    <link rel="stylesheet" href="toastr.min.css">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script src="toastr.min.js"></script>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f4f7fc;
            margin: 0;
            padding: 0;
            color: #333;
        }
        .header {
            background: linear-gradient(135deg, #0e499f, #31c6f6);
            color: white;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 15px rgba(14,73,159,0.1);
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
        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 2rem;
        }
        .page-title {
            font-size: 2.5rem;
            color: #0e499f;
            text-align: center;
            margin-bottom: 1.5rem;
            font-weight: 700;
        }
        .points-summary {
            background: linear-gradient(135deg, #31c6f6 0%, #0e499f 100%);
            color: white;
            border-radius: 20px;
            padding: 1.5rem 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 8px 25px rgba(49,198,246,0.2);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .points-summary h2 {
            margin: 0;
            font-size: 1.6rem;
        }
        .points-balance {
            font-size: 2.8rem;
            font-weight: bold;
            display: flex;
            align-items: center;
        }
        .points-balance::after {
            content: '✨'; /* Sparkle icon */
            margin-left: 10px;
            font-size: 2rem;
        }
        
        /* Search and Filter Section - Stacked Layout */
        .search-filter-section {
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 8px 28px rgba(14,73,159,0.07);
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .search-filter-row {
            display: flex;
            gap: 1.5rem;
            align-items: end;
            margin-bottom: 1.5rem;
        }

        .search-box {
            flex: 2;
            position: relative;
        }

        .filter-dropdown {
            flex: 1;
        }

        .search-box input,
        .filter-dropdown select {
            width: 100%;
            padding: 0.8rem 1rem;
            border: 2px solid #e1e5eb;
            border-radius: 50px;
            font-size: 1rem;
            background: #f8f9fa;
            transition: all 0.3s;
        }

        .search-box input:focus,
        .filter-dropdown select:focus {
            outline: none;
            border-color: #31c6f6;
            background: white;
            box-shadow: 0 0 0 3px rgba(49,198,246,0.1);
        }

        .search-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
        }

        .filter-actions {
            display: flex;
            gap: 0.8rem;
            justify-content: center;
        }
        
        .btn {
            display: inline-block;
            background: linear-gradient(135deg, #0e499f, #31c6f6);
            color: white;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 50px;
            font-weight: 600;
            text-decoration: none;
            transition: background 0.3s, box-shadow 0.3s;
            cursor: pointer;
            box-shadow: 0 2px 8px rgba(49,198,246,0.13);
        }
        .btn:hover {
            background: #31c6f6;
            color: #0e499f;
            box-shadow: 0 6px 18px rgba(14,73,159,0.13);
        }
        .btn-clear {
            background: linear-gradient(135deg, #6c757d, #adb5bd);
        }
        .btn-clear:hover {
            background: #adb5bd;
            color: white;
        }
        
        .category-section {
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 8px 28px rgba(14,73,159,0.07);
            padding: 2rem;
            margin-bottom: 2rem;
        }
        .category-title {
            font-size: 1.8rem;
            color: #0e499f;
            margin-bottom: 1.5rem;
            border-bottom: 2px solid #eaf6ff;
            padding-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .voucher-list {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
        }
        .voucher-card {
            background: linear-gradient(135deg, #eaf6ff 80%, #f4f7fc 100%);
            border-radius: 15px;
            padding: 1.5rem;
            text-align: center;
            box-shadow: 0 2px 8px rgba(49,198,246,0.08);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .voucher-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 15px rgba(49,198,246,0.15);
        }
        .voucher-name {
            font-weight: 700;
            color: #0e499f;
            font-size: 1.3rem;
            margin-bottom: 0.5rem;
        }
        .voucher-points {
            color: #31c6f6;
            font-weight: bold;
            font-size: 1.1rem;
            margin-bottom: 1rem;
        }
        .voucher-desc {
            color: #555;
            font-size: 0.95rem;
            flex-grow: 1; /* Allows description to take up available space */
            margin-bottom: 1rem;
        }
        .btn-redeem {
            padding: 0.6rem 1.5rem;
            font-size: 1rem;
            width: fit-content;
            margin: 0 auto 0.5rem auto;
        }
        .btn-cart {
            padding: 0.6rem 1.5rem;
            font-size: 1rem;
            width: fit-content;
            margin: 0 auto;
            background: linear-gradient(135deg, #4CAF50, #2E7D32);
        }
        .btn-cart:hover {
            background: #2E7D32;
            color: white;
        }
        .btn.disabled {
            background: #ccc;
            cursor: not-allowed;
            box-shadow: none;
        }
        .btn.disabled:hover {
            background: #ccc;
            color: white;
        }
        .button-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
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
        .no-results {
            text-align: center;
            padding: 3rem;
            color: #6c757d;
            font-style: italic;
        }
        /* Styles for the Toastr Download Button */
        .toastr-download-btn {
            background-color: #ffe165;
            color: #0e499f;
            border: 1px solid #ffe165;
            padding: 8px 15px;
            border-radius: 5px;
            font-weight: 600;
            text-decoration: none;
            margin-top: 10px;
            display: inline-block;
            transition: background-color 0.3s ease, color 0.3s ease;
        }
        .toastr-download-btn:hover {
            background-color: #ffbc00;
            color: #fff;
            border-color: #ffbc00;
        }
        
        @media(max-width: 768px) {
            .header {
                flex-direction: column;
                padding: 1rem;
            }
            .nav-links {
                margin-top: 1rem;
                display: flex;
                flex-wrap: wrap;
                justify-content: center;
                gap: 1rem;
            }
            .nav-links a, .logout-btn {
                margin: 0;
            }
            .points-summary {
                flex-direction: column;
                text-align: center;
            }
            .points-summary h2 {
                margin-bottom: 1rem;
            }
            .container {
                padding: 0 1rem;
            }
            .search-filter-row {
                flex-direction: column;
                gap: 1rem;
            }
            .search-box, .filter-dropdown {
                width: 100%;
            }
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

    <div class="container">
        <h1 class="page-title">Available Vouchers & Rewards</h1>

        <div class="points-summary">
            <h2>Your Current Loyalty Points:</h2>
            <div class="points-balance" id="points-balance"><?php echo number_format($userPoints); ?></div>
        </div>

        <!-- Search and Filter Section -->
        <div class="search-filter-section">
            <form method="GET" action="" class="search-filter-form">
                <div class="search-filter-row">
                    <div class="search-box">
                        <span class="search-icon">🔍</span>
                        <input type="text" name="search" placeholder="Search vouchers by name..." value="<?php echo htmlspecialchars($search_query); ?>">
                    </div>
                    <div class="filter-dropdown">
                        <select name="category">
                            <option value="all" <?php echo ($category_filter === 'all' || empty($category_filter)) ? 'selected' : ''; ?>>All Categories</option>
                            <?php foreach ($available_categories as $category): ?>
                                <option value="<?php echo htmlspecialchars($category); ?>" <?php echo ($category_filter === $category) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($category); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="filter-actions">
                    <button type="submit" class="btn">Apply Filters</button>
                    <a href="rewards.php" class="btn btn-clear">Clear</a>
                </div>
            </form>
        </div>

        <?php if (!empty($vouchers)): ?>
            <?php foreach ($vouchers as $category => $items): ?>
                <div class="category-section">
                    <h3 class="category-title">
                        <?php 
                            // Assign an emoji icon based on category
                            $icon = '';
                            switch ($category) {
                                case 'Shopping': $icon = '🛍️'; break;
                                case 'Dining': $icon = '🍽️'; break;
                                case 'Travel': $icon = '✈️'; break;
                                case 'Entertainment': $icon = '🎬'; break;
                                case 'Cashback': $icon = '💰'; break;
                                default: $icon = '⭐';
                            }
                            echo $icon . ' ' . htmlspecialchars($category); 
                        ?>
                    </h3>
                    <div class="voucher-list">
                        <?php foreach ($items as $voucher): ?>
                            <div class="voucher-card">
                                <div>
                                    <div class="voucher-name"><?php echo htmlspecialchars($voucher['name']); ?></div>
                                    <div class="voucher-points"><?php echo number_format($voucher['points']); ?> Points</div>
                                    <div class="voucher-desc"><?php echo htmlspecialchars($voucher['description']); ?></div>
                                </div>
                                <div class="button-group">
                                    <!-- Redeem Button -->
                                    <form method="POST" action="" style="margin: 0;">
                                        <input type="hidden" name="voucher_id" value="<?php echo $voucher['id']; ?>">
                                        <input type="hidden" name="action" value="redeem">
                                        <button 
                                            type="submit"
                                            class="btn btn-redeem" 
                                            <?php echo ($userPoints < $voucher['points']) ? 'disabled' : ''; ?>
                                        >
                                            <?php echo ($userPoints < $voucher['points']) ? 'Not Enough Points' : 'Redeem Now'; ?>
                                        </button>
                                    </form>
                                    
                                    <!-- Add to Cart Button -->
                                    <form method="POST" action="" style="margin: 0;">
                                        <input type="hidden" name="voucher_id" value="<?php echo $voucher['id']; ?>">
                                        <input type="hidden" name="action" value="add_to_cart">
                                        <button 
                                            type="submit"
                                            class="btn btn-cart" 
                                        >
                                            Add to Cart
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="category-section">
                <div class="no-results">
                    <h3>No Vouchers Found</h3>
                    <p>No vouchers match your search criteria. Try adjusting your filters or search term.</p>
                    <a href="rewards.php" class="btn">View All Vouchers</a>
                </div>
            </div>
        <?php endif; ?>

    </div>

    <div class="footer">
        &copy; <?php echo date("Y"); ?> OptimaBank Loyalty. All rights reserved.
    </div>

    <script>
        $(document).ready(function() {
            toastr.options = {
                "closeButton": true, // Changed to true for direct messages
                "debug": false,
                "newestOnTop": false,
                "progressBar": false,
                "positionClass": "toast-top-center",
                "preventDuplicates": false,
                "onclick": null,
                "showDuration": "300",
                "hideDuration": "1000",
                "timeOut": "4000", // Default timeout for general messages
                "extendedTimeOut": "1000",
                "showEasing": "swing",
                "hideEasing": "linear",
                "showMethod": "fadeIn",
                "hideMethod": "fadeOut"
            };

            // Options for redemption success message (sticky with download button)
            var redemptionToastrOptions = {
                "closeButton": true,
                "tapToDismiss": false, // Keep toastr open until user clicks button or closes
                "timeOut": "0",        // Keep toastr open indefinitely
                "extendedTimeOut": "0",
            };

            <?php if (isset($_SESSION['success_message_redeem'])): ?>
                var voucherId = <?php echo isset($_SESSION['redeemed_voucher_id']) ? intval($_SESSION['redeemed_voucher_id']) : 'null'; ?>;
                var voucherName = "<?php echo addslashes(isset($_SESSION['redeemed_voucher_name']) ? $_SESSION['redeemed_voucher_name'] : 'Your Voucher'); ?>";

                if (voucherId) {
                    var downloadLink = 'generate_voucher_pdf.php?voucher_id=' + voucherId;
                    var htmlMessage = '<div>' +
                                      '<strong>Voucher Redeemed!</strong><br>' +
                                      'You have successfully redeemed ' + voucherName + '.' +
                                      '<br><a href="' + downloadLink + '" target="_blank" class="toastr-download-btn">Download PDF Voucher</a>' +
                                      '</div>';
                    toastr.success(htmlMessage, 'Redemption Successful', redemptionToastrOptions);
                } else {
                    toastr.success("<?php echo addslashes($_SESSION['success_message_redeem']); ?>");
                }
                <?php
                    unset($_SESSION['success_message_redeem']);
                    unset($_SESSION['redeemed_voucher_id']);
                    unset($_SESSION['redeemed_voucher_name']);
                ?>
            <?php elseif (isset($_SESSION['success_message'])): ?>
                toastr.success("<?php echo addslashes($_SESSION['success_message']); ?>");
                <?php unset($_SESSION['success_message']); ?>
            <?php endif; ?>
            <?php if (isset($_SESSION['error_message'])): ?>
                toastr.error("<?php echo addslashes($_SESSION['error_message']); ?>");
                <?php unset($_SESSION['error_message']); ?>
            <?php endif; ?>
        });
    </script>
</body>
</html>
