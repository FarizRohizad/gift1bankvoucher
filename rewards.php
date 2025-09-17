<?php
session_start();
include('connect.php'); // make sure this points to your DB connection

// Redirect to login if not authenticated
if (!isset($_SESSION['UserID'])) {
    header("Location: /../landingpage.php");
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

// Sample Voucher Data (you would typically fetch this from a database)
$vouchers = [
    'Shopping' => [
        ['id' => 1, 'name' => 'RM50 AEON Voucher', 'points' => 1000, 'description' => 'Valid at all AEON outlets nationwide.'],
        ['id' => 2, 'name' => 'RM25 Zalora Voucher', 'points' => 800, 'description' => 'For your next fashion haul on Zalora.'],
        ['id' => 3, 'name' => 'RM100 Harvey Norman Voucher', 'points' => 1800, 'description' => 'Discount on electronics and home appliances.'],
    ],
    'Dining' => [
        ['id' => 4, 'name' => 'RM30 GrabFood Credit', 'points' => 700, 'description' => 'Enjoy delicious meals delivered to your door.'],
        ['id' => 5, 'name' => 'Starbucks Buy 1 Get 1 Free', 'points' => 500, 'description' => 'Share a coffee with a friend.'],
        ['id' => 6, 'name' => 'RM50 Dining Voucher (TGIF)', 'points' => 1200, 'description' => 'Treat yourself at TGI Fridays.'],
    ],
    'Travel & Entertainment' => [
        ['id' => 7, 'name' => 'RM50 KLOOK Voucher', 'points' => 1100, 'description' => 'Discount on attractions and activities.'],
        ['id' => 8, 'name' => 'GSC Cinema Ticket (2 pax)', 'points' => 950, 'description' => 'Catch the latest blockbuster with a loved one.'],
        ['id' => 9, 'name' => 'RM100 AirAsia Flight Voucher', 'points' => 2500, 'description' => 'Towards your next domestic adventure.'],
    ],
    'Cashback & Others' => [
        ['id' => 10, 'name' => 'RM30 Petrol Cashback', 'points' => 900, 'description' => 'Get cashback on your next petrol refill.'],
        ['id' => 11, 'name' => 'RM20 Phone Bill Rebate', 'points' => 600, 'description' => 'Get a rebate on your next mobile bill.'],
        ['id' => 12, 'name' => 'Fitness Class Pass', 'points' => 750, 'description' => 'One-time pass for a fitness class.'],
    ]
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OptimaBank Loyalty - Rewards</title>
    <link rel="stylesheet" href="/../toastr.min.css">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script src="/../toastr.min.js"></script>
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
        .nav-links a {
            color: #fff;
            text-decoration: none;
            font-weight: 500;
            margin-left: 2rem;
            transition: color 0.3s;
        }
        .nav-links a:hover {
            color: #ffe165;
        }
        .container {
            max-width: 1000px;
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
        .btn {
            display: inline-block;
            background: linear-gradient(135deg, #0e499f, #31c6f6);
            color: white;
            padding: 0.75rem 2rem;
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
        .btn-redeem {
            padding: 0.6rem 1.5rem;
            font-size: 1rem;
            width: fit-content;
            margin: 0 auto;
        }
        .btn-redeem.disabled {
            background: #ccc;
            cursor: not-allowed;
            box-shadow: none;
        }
        .btn-redeem.disabled:hover {
            background: #ccc;
            color: white;
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
            <form style="display:inline;" method="post" action="/../logout.php">
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

        <?php foreach ($vouchers as $category => $items): ?>
            <div class="category-section">
                <h3 class="category-title">
                    <?php 
                        // Assign an emoji icon based on category
                        $icon = '';
                        switch ($category) {
                            case 'Shopping': $icon = '🛍️'; break;
                            case 'Dining': $icon = '🍽️'; break;
                            case 'Travel & Entertainment': $icon = '✈️'; break;
                            case 'Cashback & Others': $icon = '💰'; break;
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
                            <button 
                                class="btn btn-redeem" 
                                data-voucher-id="<?php echo $voucher['id']; ?>"
                                data-voucher-name="<?php echo htmlspecialchars($voucher['name']); ?>"
                                data-voucher-points="<?php echo $voucher['points']; ?>"
                                onclick="redeemVoucher(this)"
                                <?php echo ($userPoints < $voucher['points']) ? 'disabled' : ''; ?>
                            >
                                Redeem
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>

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

            // Initial check for disabled buttons based on current points
            updateRedeemButtonStates();

            <?php if (isset($_SESSION['success_message'])): ?>
                toastr.success("<?php echo addslashes($_SESSION['success_message']); ?>");
                <?php unset($_SESSION['success_message']); ?>
            <?php endif; ?>
            <?php if (isset($_SESSION['error_message'])): ?>
                toastr.error("<?php echo addslashes($_SESSION['error_message']); ?>");
                <?php unset($_SESSION['error_message']); ?>
            <?php endif; ?>
        });

        function updateRedeemButtonStates() {
            let currentPoints = parseInt($('#points-balance').text().replace(/,/g, ''));
            $('.btn-redeem').each(function() {
                let voucherPoints = parseInt($(this).data('voucher-points'));
                if (currentPoints < voucherPoints) {
                    $(this).prop('disabled', true).addClass('disabled').text('Not Enough Points');
                } else {
                    $(this).prop('disabled', false).removeClass('disabled').text('Redeem');
                }
            });
        }

        function redeemVoucher(button) {
            let voucherId = $(button).data('voucher-id');
            let voucherName = $(button).data('voucher-name');
            let voucherPoints = parseInt($(button).data('voucher-points'));
            let currentPointsElement = $('#points-balance');
            let currentPoints = parseInt(currentPointsElement.text().replace(/,/g, ''));

            if (currentPoints >= voucherPoints) {
                // In a real application, you would send an AJAX request to a PHP script here
                // For example:
                /*
                $.ajax({
                    url: 'redeem_voucher.php', // A new PHP script to handle redemption
                    method: 'POST',
                    data: { voucher_id: voucherId, points_cost: voucherPoints },
                    success: function(response) {
                        if (response.success) {
                            currentPoints -= voucherPoints;
                            currentPointsElement.text(currentPoints.toLocaleString());
                            toastr.success(`Successfully redeemed "${voucherName}"!`);
                            updateRedeemButtonStates(); // Re-evaluate button states
                        } else {
                            toastr.error(response.message || 'Error redeeming voucher.');
                        }
                    },
                    error: function() {
                        toastr.error('An error occurred during redemption.');
                    }
                });
                */

                // For demonstration purposes, we'll update points directly on the client side
                currentPoints -= voucherPoints;
                currentPointsElement.text(currentPoints.toLocaleString());
                toastr.success(`Successfully redeemed "${voucherName}"! Check your profile for details.`);
                updateRedeemButtonStates(); // Re-evaluate button states after points change
                
            } else {
                toastr.error('You do not have enough points to redeem this voucher.');
            }
        }
    </script>
</body>
</html>
