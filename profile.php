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

$stmt = $conn->prepare("SELECT User_Name, User_Email FROM users WHERE User_ID = ?");
$stmt->bind_param("i", $userID);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: logout.php");
    exit();
}

$user = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>My Profile - OptimaBank</title>
    <link rel="stylesheet" href="/../toastr.min.css" />
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script src="/../toastr.min.js"></script>
    <style>
    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background: #f4f7fc;
        margin: 0;
        padding: 0;
        min-height: 100vh;
        display: flex;
        flex-direction: column;
    }

    /* ===== HEADER ===== */
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
        gap: 1.5rem;
    }
    .nav-links a {
        color: #fff;
        text-decoration: none;
        font-weight: 500;
        transition: color 0.3s;
    }
    .nav-links a:hover {
        color: #ffe165;
    }

    /* ===== LOGOUT BUTTON ===== */
   header .logout-btn {
    background: #ffe165 !important;
    color: #0e499f !important;
    border: none !important;
    padding: 0.2rem 1rem !important;
    border-radius: 50px !important;
    font-weight: 600 !important;
    cursor: pointer !important;
    transition: all 0.3s !important;
    box-shadow: none !important;
}
header .logout-btn:hover {
    background: #ffd633 !important;
    color: #0e499f !important;
    transform: scale(1.05);
}


    /* ===== MAIN CARD ===== */
    main {
        flex: 1;
        max-width: 700px;
        margin: 6rem auto 3rem;
        background: white;
        padding: 3rem 3rem 3.5rem;
        border-radius: 20px;
        box-shadow: 0 8px 28px rgba(14,73,159,0.07);
    }
    h2 {
        color: #0e499f;
        text-align: center;
        margin-bottom: 2rem;
        font-size: 2rem;
    }

    /* ===== FORM ===== */
    form label {
        display: block;
        margin-top: 1rem;
        font-weight: 600;
        color: #0e499f;
    }
    form input[type="text"],
    form input[type="email"],
    form input[type="password"] {
        width: 100%;
        padding: 1rem 1.2rem;
        margin-top: 0.3rem;
        border: 1px solid #ccc;
        border-radius: 12px;
        font-size: 1rem;
        background: #f8f9fa;
        transition: all 0.3s;
    }
    form input[type="text"]:focus,
    form input[type="email"]:focus,
    form input[type="password"]:focus {
        outline: none;
        border-color: #31c6f6;
        background: white;
        box-shadow: 0 0 0 3px rgba(49,198,246,0.1);
    }

    /* ===== SUBMIT BUTTON ===== */
    button[type="submit"] {
        margin-top: 2rem;
        width: 100%;
        background: linear-gradient(135deg, #0e499f, #31c6f6);
        color: white;
        border: none;
        padding: 0.9rem;
        font-size: 1.1rem;
        border-radius: 50px;
        cursor: pointer;
        transition: all 0.3s;
        box-shadow: 0 2px 8px rgba(49,198,246,0.13);
    }
    button[type="submit"]:hover {
        background: #31c6f6;
        color: #0e499f;
        box-shadow: 0 6px 18px rgba(14,73,159,0.13);
    }

    /* ===== MESSAGE BOX ===== */
    .message {
        margin-top: 1rem;
        padding: 0.8rem;
        border-radius: 8px;
        text-align: center;
        font-weight: 600;
    }
    .error {
        background: #f8d7da;
        color: #842029;
    }
    .success {
        background: #d1e7dd;
        color: #0f5132;
    }

    /* ===== BACK LINK ===== */
    .back-link {
        display: block;
        margin-top: 1.5rem;
        text-align: center;
        color: #0e499f;
        text-decoration: none;
        font-weight: 600;
    }
    .back-link:hover {
        text-decoration: underline;
    }

    /* ===== FOOTER ===== */
    footer.footer {
        background: #143c6b;
        color: white;
        padding: 2rem 1rem 1rem;
        text-align: center;
        margin-top: auto;
    }
    footer.footer p {
        margin: 0;
        color: #ccc;
    }

    /* ===== RESPONSIVE ===== */
    @media(max-width: 640px) {
        main {
            margin: 6rem 1rem 3rem;
            padding: 2rem 1.5rem 2.5rem;
        }
        .nav-links {
            gap: 1rem;
        }
        .logo {
            font-size: 1.5rem;
        }
        .logout-btn {
            padding: 0.4rem 1rem;
            font-size: 0.9rem;
        }
    }
    </style>
</head>
<body>




    <header class="header">
        <div class="logo">OptimaBank Loyalty</div>
        <div class="nav-links">
            <a href="/../home.php">Home</a>
            <a href="/../rewards.php">Offers</a>
            <a href="/../profile.php">Profile</a>
            <form method="post" action="/../logout.php" style="display:inline;">
                <button type="submit" class="logout-btn">Log Out</button>
            </form>
        </div>
    </header>

    <main>
        <h2>My Profile</h2>

        <?php if ($error): ?>
            <div class="message error"><?php echo htmlspecialchars($error); ?></div>
        <?php elseif ($message): ?>
            <div class="message success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <label for="name">Username</label>
            <input type="text" id="name" name="name" required value="<?php echo htmlspecialchars($user['User_Name']); ?>">

            <label for="email">Email</label>
            <input type="email" id="email" name="email" required value="<?php echo htmlspecialchars($user['User_Email']); ?>">

            <label for="new_password">New Password (leave blank if you don't want to change)</label>
            <input type="password" id="new_password" name="new_password">

            <label for="confirm_password">Confirm New Password</label>
            <input type="password" id="confirm_password" name="confirm_password">

            <button type="submit">Update Profile</button>
        </form>

        <a href="/../home.php" class="back-link">&larr; Back to Home</a>
    </main>

    <footer class="footer">
        <p>&copy; <?php echo date("Y"); ?> OptimaBank Loyalty. All rights reserved.</p>
    </footer>
</body>
</html>
