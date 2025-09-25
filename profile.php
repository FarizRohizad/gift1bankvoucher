<?php
session_start();
include('connect.php');

// Ensure user is logged in
if (!isset($_SESSION['UserID'])) {
    header("Location: landingpage.php");
    exit();
}

$userID = $_SESSION['UserID'];
$error = '';
$message = '';

// Process profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($name) || empty($email)) {
        $error = "Please enter your name and email.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif ($new_password !== '' && $new_password !== $confirm_password) {
        $error = "New password and confirmation do not match.";
    } else {
        $stmt = $conn->prepare("SELECT User_ID FROM users WHERE User_Email = ? AND User_ID != ?");
        $stmt->bind_param("si", $email, $userID);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $error = "This email is already used by another user.";
        } else {
            if ($new_password !== '') {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $update = $conn->prepare("UPDATE users SET User_Name = ?, User_Email = ?, User_Password = ? WHERE User_ID = ?");
                $update->bind_param("sssi", $name, $email, $hashed_password, $userID);
            } else {
                $update = $conn->prepare("UPDATE users SET User_Name = ?, User_Email = ? WHERE User_ID = ?");
                $update->bind_param("ssi", $name, $email, $userID);
            }

            if ($update->execute()) {
                $message = "Your profile has been updated successfully.";
                $_SESSION['User Name'] = $name;
                $_SESSION['User Email'] = $email;
            } else {
                $error = "Failed to update profile. Please try again.";
            }
        }
    }
}

// Fetch user details
$stmt = $conn->prepare("SELECT User_Name, User_Email FROM users WHERE User_ID = ?");
$stmt->bind_param("i", $userID);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: logout.php");
    exit();
}

$user = $result->fetch_assoc();

// Fetch voucher redemption history
$history_query = $conn->prepare("
    SELECT h.*, v.name as voucher_name, v.categoryName 
    FROM History h 
    JOIN Voucher v ON h.voucherID = v.voucherID 
    WHERE h.userId = ? 
    ORDER BY h.dateBuy DESC
");
$history_query->bind_param("i", $userID);
$history_query->execute();
$history_result = $history_query->get_result();
$redemption_history = $history_result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>My Profile - OptimaBank</title>
    <link rel="stylesheet" href="/../toastr.min.css" />
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script src="toastr.min.js"></script>
    <style>
    :root {
        --primary-blue: #0e499f;
        --secondary-blue: #31c6f6;
        --light-blue: #e6f4ff;
        --dark-blue: #143c6b;
        --accent-yellow: #ffe165;
        --text-dark: #333;
        --text-light: #6c757d;
        --white: #ffffff;
        --light-gray: #f8f9fa;
        --border-radius: 12px;
        --box-shadow: 0 8px 28px rgba(14,73,159,0.07);
        --transition: all 0.3s ease;
    }
    
    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background: linear-gradient(135deg, #f4f7fc 0%, #e6f4ff 100%);
        margin: 0;
        padding: 0;
        min-height: 100vh;
        display: flex;
        flex-direction: column;
        color: var(--text-dark);
    }

    /* ===== HEADER ===== */
    .header {
        background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
        color: var(--white);
        padding: 1rem 2rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        position: sticky;
        top: 0;
        z-index: 100;
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
        gap: 1.5rem;
    }
    .nav-links a {
        color: var(--white);
        text-decoration: none;
        font-weight: 500;
        transition: var(--transition);
        padding: 0.5rem 1rem;
        border-radius: 50px;
    }
    .nav-links a:hover {
        color: var(--accent-yellow);
        background: rgba(255, 255, 255, 0.1);
    }

    /* ===== LOGOUT BUTTON ===== */
    header .logout-btn {
        background: var(--accent-yellow) !important;
        color: var(--primary-blue) !important;
        border: none !important;
        padding: 0.5rem 1.5rem !important;
        border-radius: 50px !important;
        font-weight: 600 !important;
        cursor: pointer !important;
        transition: var(--transition) !important;
        box-shadow: none !important;
    }
    header .logout-btn:hover {
        background: #ffd633 !important;
        transform: scale(1.05);
        box-shadow: 0 4px 12px rgba(255, 225, 101, 0.3);
    }

    /* ===== MAIN CONTENT ===== */
    .main-container {
        flex: 1;
        max-width: 1200px;
        margin: 3rem auto;
        padding: 0 1.5rem;
        display: flex;
        gap: 2rem;
    }

    /* ===== PROFILE SECTION ===== */
    .profile-section {
        flex: 1;
        background: var(--white);
        padding: 2.5rem;
        border-radius: var(--border-radius);
        box-shadow: var(--box-shadow);
        position: relative;
        overflow: hidden;
    }
    
    .profile-section::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 5px;
        background: linear-gradient(90deg, var(--primary-blue), var(--secondary-blue));
    }

    /* ===== HISTORY SECTION ===== */
    .history-section {
        flex: 1;
        background: var(--white);
        padding: 2.5rem;
        border-radius: var(--border-radius);
        box-shadow: var(--box-shadow);
        max-height: 700px;
        overflow-y: auto;
        position: relative;
    }
    
    .history-section::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 5px;
        background: linear-gradient(90deg, var(--secondary-blue), var(--primary-blue));
    }

    h2 {
        color: var(--primary-blue);
        text-align: center;
        margin-bottom: 2rem;
        font-size: 2.2rem;
        position: relative;
        padding-bottom: 1rem;
    }
    
    h2::after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 50%;
        transform: translateX(-50%);
        width: 80px;
        height: 4px;
        background: linear-gradient(90deg, var(--primary-blue), var(--secondary-blue));
        border-radius: 2px;
    }

    /* ===== FORM ===== */
    .form-group {
        margin-bottom: 1.5rem;
    }
    
    form label {
        display: block;
        margin-bottom: 0.5rem;
        font-weight: 600;
        color: var(--primary-blue);
        font-size: 1rem;
    }
    
    form input[type="text"],
    form input[type="email"],
    form input[type="password"] {
        width: 100%;
        padding: 1rem 1.2rem;
        border: 2px solid #e1e5eb;
        border-radius: var(--border-radius);
        font-size: 1rem;
        background: var(--light-gray);
        transition: var(--transition);
        box-sizing: border-box;
    }
    
    form input[type="text"]:focus,
    form input[type="email"]:focus,
    form input[type="password"]:focus {
        outline: none;
        border-color: var(--secondary-blue);
        background: var(--white);
        box-shadow: 0 0 0 3px rgba(49,198,246,0.1);
    }

    /* ===== SUBMIT BUTTON ===== */
    button[type="submit"] {
        margin-top: 1.5rem;
        width: 100%;
        background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
        color: var(--white);
        border: none;
        padding: 1rem;
        font-size: 1.1rem;
        border-radius: 50px;
        cursor: pointer;
        transition: var(--transition);
        box-shadow: 0 4px 15px rgba(14,73,159,0.2);
        font-weight: 600;
        letter-spacing: 0.5px;
    }
    
    button[type="submit"]:hover {
        background: linear-gradient(135deg, var(--secondary-blue), var(--primary-blue));
        box-shadow: 0 6px 20px rgba(14,73,159,0.3);
        transform: translateY(-2px);
    }

    /* ===== MESSAGE BOX ===== */
    .message {
        margin-top: 1rem;
        padding: 1rem;
        border-radius: var(--border-radius);
        text-align: center;
        font-weight: 600;
        border-left: 4px solid;
    }
    
    .error {
        background: #f8d7da;
        color: #842029;
        border-left-color: #dc3545;
    }
    
    .success {
        background: #d1e7dd;
        color: #0f5132;
        border-left-color: #198754;
    }

    /* ===== BACK LINK ===== */
    .back-link {
        display: inline-flex;
        align-items: center;
        margin-top: 1.5rem;
        color: var(--primary-blue);
        text-decoration: none;
        font-weight: 600;
        transition: var(--transition);
        padding: 0.5rem 1rem;
        border-radius: 50px;
    }
    
    .back-link:hover {
        color: var(--secondary-blue);
        background: var(--light-blue);
        text-decoration: none;
        transform: translateX(-5px);
    }
    
    .back-link::before {
        content: '←';
        margin-right: 8px;
        transition: var(--transition);
    }

    /* ===== FOOTER ===== */
    footer.footer {
        background: var(--dark-blue);
        color: var(--white);
        padding: 2rem 1rem 1rem;
        text-align: center;
        margin-top: auto;
    }
    
    footer.footer p {
        margin: 0;
        color: #ccc;
    }

    /* ===== VOUCHER HISTORY ===== */
    .history-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.5rem;
        padding-bottom: 1rem;
        border-bottom: 2px solid #f0f0f0;
    }
    
    .history-header h3 {
        color: var(--primary-blue);
        margin: 0;
        font-size: 1.8rem;
        position: relative;
        padding-bottom: 0.5rem;
    }
    
    .history-header h3::after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 0;
        width: 50px;
        height: 3px;
        background: var(--secondary-blue);
        border-radius: 2px;
    }
    
    .history-item {
        margin-bottom: 1.5rem;
        padding: 1.5rem;
        border-radius: var(--border-radius);
        background: var(--light-gray);
        border-left: 4px solid var(--primary-blue);
        transition: var(--transition);
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    }
    
    .history-item:hover {
        transform: translateY(-3px);
        box-shadow: 0 5px 15px rgba(0,0,0,0.08);
    }
    
    .history-item-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 0.8rem;
    }
    
    .history-item-name {
        font-weight: 600;
        color: var(--primary-blue);
        font-size: 1.2rem;
    }
    
    .history-item-date {
        color: var(--text-light);
        font-size: 0.9rem;
        background: var(--white);
        padding: 0.3rem 0.8rem;
        border-radius: 50px;
        font-weight: 500;
    }
    
    .history-item-details {
        display: flex;
        justify-content: space-between;
        color: var(--text-dark);
        align-items: center;
    }
    
    .category-badge {
        display: inline-block;
        padding: 0.4rem 0.8rem;
        border-radius: 50px;
        font-size: 0.8rem;
        font-weight: 600;
        background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
        color: var(--white);
        box-shadow: 0 2px 5px rgba(14,73,159,0.2);
    }
    
    .cost-cell {
        color: #dc3545;
        font-weight: 600;
        font-size: 1.1rem;
    }
    
    .no-history {
        text-align: center;
        padding: 3rem 2rem;
        color: var(--text-light);
        font-style: italic;
    }
    
    .no-history p {
        margin-bottom: 1rem;
        font-size: 1.1rem;
    }
    
    .no-history a {
        color: var(--primary-blue);
        text-decoration: none;
        font-weight: 600;
        transition: var(--transition);
        display: inline-block;
        padding: 0.5rem 1rem;
        border-radius: 50px;
        background: var(--light-blue);
    }
    
    .no-history a:hover {
        color: var(--secondary-blue);
        background: var(--white);
        box-shadow: 0 2px 8px rgba(14,73,159,0.1);
    }

    /* ===== RESPONSIVE ===== */
    @media(max-width: 992px) {
        .main-container {
            flex-direction: column;
        }
        
        .profile-section, .history-section {
            max-height: none;
        }
    }
    
    @media(max-width: 768px) {
        .header {
            padding: 1rem;
            flex-direction: column;
            gap: 1rem;
        }
        
        .nav-links {
            gap: 1rem;
            flex-wrap: wrap;
            justify-content: center;
        }
        
        .logo {
            font-size: 1.5rem;
        }
        
        .main-container {
            margin: 1.5rem auto;
            padding: 0 1rem;
        }
        
        .profile-section, .history-section {
            padding: 1.5rem;
        }
        
        .history-item-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 0.5rem;
        }
        
        .history-item-details {
            flex-direction: column;
            gap: 0.8rem;
            align-items: flex-start;
        }
        
        h2 {
            font-size: 1.8rem;
        }
        
        .history-header h3 {
            font-size: 1.5rem;
        }
    }
    
    @media(max-width: 480px) {
        .nav-links {
            flex-direction: column;
            width: 100%;
        }
        
        .nav-links a {
            width: 100%;
            text-align: center;
        }
        
        .profile-section, .history-section {
            padding: 1rem;
        }
        
        h2 {
            font-size: 1.5rem;
        }
    }
    </style>
</head>
<body>

    <header class="header">
        <div class="logo">OptimaBank Loyalty</div>
        <div class="nav-links">
            <a href="home.php">Home</a>
            <a href="rewards.php">Voucher</a>
            <a href="profile.php">Profile</a>
            <form method="post" action="logout.php" style="display:inline;">
                <button type="submit" class="logout-btn">Log Out</button>
            </form>
        </div>
    </header>

    <div class="main-container">
        <!-- Profile Section -->
        <section class="profile-section">
            <h2>My Profile</h2>

            <?php if (isset($error) && $error): ?>
                <div class="message error"><?php echo htmlspecialchars($error); ?></div>
            <?php elseif (isset($message) && $message): ?>
                <div class="message success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="name">Username</label>
                    <input type="text" id="name" name="name" required value="<?php echo isset($user['User_Name']) ? htmlspecialchars($user['User_Name']) : ''; ?>">
                </div>

                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required value="<?php echo isset($user['User_Email']) ? htmlspecialchars($user['User_Email']) : ''; ?>">
                </div>

                <div class="form-group">
                    <label for="new_password">New Password (leave blank if you don't want to change)</label>
                    <input type="password" id="new_password" name="new_password">
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm New Password</label>
                    <input type="password" id="confirm_password" name="confirm_password">
                </div>

                <button type="submit">Update Profile</button>
            </form>

            <a href="home.php" class="back-link">Back to Home</a>
        </section>
        
        <!-- Voucher Redemption History Section -->
        <section class="history-section">
            <div class="history-header">
                <h3>Voucher Redemption History</h3>
            </div>
            
            <?php if (!empty($redemption_history)): ?>
                <?php foreach ($redemption_history as $history): ?>
                    <div class="history-item">
                        <div class="history-item-header">
                            <span class="history-item-name"><?php echo htmlspecialchars($history['voucher_name']); ?></span>
                            <span class="history-item-date"><?php echo date('M j, Y', strtotime($history['dateBuy'])); ?></span>
                        </div>
                        <div class="history-item-details">
                            <span class="category-badge"><?php echo htmlspecialchars($history['categoryName']); ?></span>
                            <span>Qty: <?php echo htmlspecialchars($history['quantity']); ?></span>
                            <span class="cost-cell"><?php echo htmlspecialchars($history['pointCost']); ?> points</span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-history">
                    <p>You haven't redeemed any vouchers yet.</p>
                    <p><a href="rewards.php">Browse available rewards</a></p>
                </div>
            <?php endif; ?>
        </section>
    </div>

    <footer class="footer">
        <p>&copy; <?php echo date("Y"); ?> OptimaBank Loyalty. All rights reserved.</p>
    </footer>
</body>
</html>
