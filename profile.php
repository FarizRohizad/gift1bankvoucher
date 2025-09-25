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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
    :root {
        --primary-blue: #0e499f;
        --secondary-blue: #31c6f6;
        --accent-yellow: #ffd93d;
        --white: #ffffff;
        --light-gray: #f5f7fa;
        --dark-text: #1a1a1a;
        --muted-text: #6c757d;
        --border-radius: 16px;
        --transition: all 0.3s ease;
    }

    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background: linear-gradient(135deg, #f0f4ff, #e6faff);
        margin: 0;
        padding: 0;
        min-height: 100vh;
        display: flex;
        flex-direction: column;
        color: var(--dark-text);
    }

    /* HEADER */
    .header {
        background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
        color: var(--white);
        padding: 1rem 2rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        position: sticky;
        top: 0;
        z-index: 100;
    }
    .logo {
        font-size: 1.8rem;
        font-weight: bold;
        display: flex;
        align-items: center;
    }
    .logo i {
        margin-right: 10px;
    }
    .nav-links {
        display: flex;
        align-items: center;
        gap: 1rem;
    }
    .nav-links a {
        color: var(--white);
        text-decoration: none;
        font-weight: 500;
        padding: 0.5rem 1rem;
        border-radius: 30px;
        transition: var(--transition);
    }
    .nav-links a:hover {
        background: rgba(255, 255, 255, 0.15);
        color: var(--accent-yellow);
    }
    .logout-btn {
        background: var(--accent-yellow);
        color: var(--primary-blue);
        border: none;
        padding: 0.5rem 1.5rem;
        border-radius: 30px;
        font-weight: 600;
        cursor: pointer;
        transition: var(--transition);
    }
    .logout-btn:hover {
        background: #ffcd00;
        transform: scale(1.05);
        box-shadow: 0 4px 12px rgba(255, 217, 61, 0.4);
    }

    /* MAIN */
    .main-container {
        flex: 1;
        max-width: 1200px;
        margin: 3rem auto;
        padding: 0 1.5rem;
        display: flex;
        gap: 2rem;
    }

    .profile-section, .history-section {
        flex: 1;
        background: var(--white);
        padding: 2.5rem;
        border-radius: var(--border-radius);
        box-shadow: 0 6px 20px rgba(0,0,0,0.05);
        position: relative;
    }

    h2, h3 {
        color: var(--primary-blue);
        margin-bottom: 1.5rem;
        text-align: center;
        font-size: 2rem;
        position: relative;
    }
    h2::after, h3::after {
        content: '';
        display: block;
        width: 80px;
        height: 4px;
        margin: 0.5rem auto 0;
        background: linear-gradient(90deg, var(--primary-blue), var(--secondary-blue));
        border-radius: 2px;
    }

    /* FORM */
    .form-group {
        margin-bottom: 1.5rem;
    }
    .form-group label {
        font-weight: 600;
        display: block;
        margin-bottom: 0.5rem;
        color: var(--primary-blue);
    }
    .form-group i {
        margin-right: 6px;
        color: var(--secondary-blue);
    }
    form input {
        width: 100%;
        padding: 1rem;
        border-radius: var(--border-radius);
        border: 2px solid #e1e5eb;
        background: var(--light-gray);
        font-size: 1rem;
        transition: var(--transition);
    }
    form input:focus {
        border-color: var(--secondary-blue);
        background: #fff;
        box-shadow: 0 0 0 4px rgba(49,198,246,0.15);
        outline: none;
    }
    button[type="submit"] {
        width: 100%;
        background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
        color: var(--white);
        border: none;
        padding: 1rem;
        font-size: 1.1rem;
        border-radius: 50px;
        font-weight: 600;
        cursor: pointer;
        transition: var(--transition);
    }
    button[type="submit"]:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 18px rgba(14,73,159,0.25);
    }

    /* HISTORY */
    .history-item {
        padding: 1.5rem;
        border-radius: var(--border-radius);
        margin-bottom: 1.2rem;
        background: #f9fbff;
        border-left: 5px solid var(--secondary-blue);
        transition: var(--transition);
    }
    .history-item:hover {
        transform: translateY(-3px);
        box-shadow: 0 6px 15px rgba(0,0,0,0.1);
    }
    .history-item-header {
        display: flex;
        justify-content: space-between;
        margin-bottom: 0.8rem;
    }
    .history-item-name {
        font-size: 1.2rem;
        font-weight: 600;
        color: var(--primary-blue);
    }
    .history-item-date {
        font-size: 0.9rem;
        background: var(--light-gray);
        padding: 0.3rem 0.8rem;
        border-radius: 20px;
        color: var(--muted-text);
    }
    .category-badge {
        display: inline-block;
        padding: 0.4rem 0.8rem;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 600;
        color: #fff;
        background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
    }
    .cost-cell {
        font-weight: 600;
        color: #dc3545;
    }
    .no-history {
        text-align: center;
        color: var(--muted-text);
        font-style: italic;
        padding: 2rem;
    }
    .no-history a {
        display: inline-block;
        margin-top: 1rem;
        padding: 0.6rem 1.2rem;
        border-radius: 30px;
        background: var(--secondary-blue);
        color: #fff;
        text-decoration: none;
        transition: var(--transition);
    }
    .no-history a:hover {
        background: var(--primary-blue);
    }

    /* FOOTER */
    .footer {
        background: var(--primary-blue);
        color: #fff;
        padding: 1.2rem;
        text-align: center;
        margin-top: auto;
    }

    /* RESPONSIVE */
    @media(max-width: 992px) {
        .main-container { flex-direction: column; }
    }
    </style>
</head>
<body>
    <header class="header">
        <div class="logo"><i class="fas fa-university"></i> OptimaBank Loyalty</div>
        <div class="nav-links">
            <a href="home.php"><i class="fas fa-home"></i> Home</a>
            <a href="rewards.php"><i class="fas fa-gift"></i> Voucher</a>
            <a href="profile.php"><i class="fas fa-user"></i> Profile</a>
            <form method="post" action="logout.php" style="display:inline;">
                <button type="submit" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Log Out</button>
            </form>
        </div>
    </header>

    <div class="main-container">
        <!-- Profile -->
        <section class="profile-section">
            <h2>My Profile</h2>
            <?php if ($error): ?>
                <div class="message error"><?= htmlspecialchars($error) ?></div>
            <?php elseif ($message): ?>
                <div class="message success"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="name"><i class="fas fa-user"></i> Username</label>
                    <input type="text" id="name" name="name" required value="<?= htmlspecialchars($user['User_Name']) ?>">
                </div>
                <div class="form-group">
                    <label for="email"><i class="fas fa-envelope"></i> Email</label>
                    <input type="email" id="email" name="email" required value="<?= htmlspecialchars($user['User_Email']) ?>">
                </div>
                <div class="form-group">
                    <label for="new_password"><i class="fas fa-lock"></i> New Password</label>
                    <input type="password" id="new_password" name="new_password">
                </div>
                <div class="form-group">
                    <label for="confirm_password"><i class="fas fa-lock"></i> Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password">
                </div>
                <button type="submit"><i class="fas fa-save"></i> Update Profile</button>
            </form>
        </section>

        <!-- History -->
        <section class="history-section">
            <h3>Voucher Redemption History</h3>
            <?php if (!empty($redemption_history)): ?>
                <?php foreach ($redemption_history as $history): ?>
                    <div class="history-item">
                        <div class="history-item-header">
                            <span class="history-item-name"><?= htmlspecialchars($history['voucher_name']); ?></span>
                            <span class="history-item-date"><?= date('M j, Y', strtotime($history['dateBuy'])); ?></span>
                        </div>
                        <div class="history-item-details">
                            <span class="category-badge"><?= htmlspecialchars($history['categoryName']); ?></span>
                            <span>Qty: <?= htmlspecialchars($history['quantity']); ?></span>
                            <span class="cost-cell"><?= htmlspecialchars($history['pointCost']); ?> points</span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-history">
                    <p>You haven’t redeemed any vouchers yet.</p>
                    <a href="rewards.php"><i class="fas fa-gift"></i> Browse Rewards</a>
                </div>
            <?php endif; ?>
        </section>
    </div>

    <footer class="footer">
        <p>&copy; <?= date("Y") ?> OptimaBank Loyalty. All rights reserved.</p>
    </footer>
</body>
</html>
