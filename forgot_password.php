<?php
include('connect.php');
session_start();

$error = '';
$message = '';
$show_password_form = false;
$email = '';
$password_reset_success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['check_email'])) {
        // Step 1: check email
        $email = trim($_POST['email'] ?? '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid email address.";
        } else {
            $stmt = $conn->prepare("SELECT User_ID FROM users WHERE User_Email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 0) {
                $error = "Email not found.";
            } else {
                $show_password_form = true; // show new password form
            }
        }
    } elseif (isset($_POST['reset_password'])) {
        // Step 2: accept new password
        $email = trim($_POST['email'] ?? '');
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Invalid email address.";
        } elseif (empty($new_password) || empty($confirm_password)) {
            $error = "Please enter and confirm your new password.";
            $show_password_form = true;
        } elseif ($new_password !== $confirm_password) {
            $error = "Password and confirmation do not match.";
            $show_password_form = true;
        } else {
            // Update password
            $stmt = $conn->prepare("SELECT User_ID FROM users WHERE User_Email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 0) {
                $error = "Email not found.";
            } else {
                $user = $result->fetch_assoc();
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

                $update = $conn->prepare("UPDATE users SET User_Password = ? WHERE User_ID = ?");
                $update->bind_param("si", $hashed_password, $user['User_ID']);
                if ($update->execute()) {
                    // Instead of PHP header redirect, set JS flag to show popup and redirect after OK
                    $password_reset_success = true;
                } else {
                    $error = "Failed to update password. Please try again.";
                    $show_password_form = true;
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Forgot Password - OptimaBank</title>
    <link rel="stylesheet" href="toastr.min.css" />
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script src="toastr.min.js"></script>
    <style>
        /* Base styles from landingpage.php */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            background: #f4f7fc;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
         .header {
            position: fixed;
            top: 0; width: 100%;
            background: rgba(14, 73, 159, 0.95);
            backdrop-filter: blur(10px);
            z-index: 1000; padding: 1rem 0;
            transition: all 0.3s ease;
        }



        .nav {
            display: flex; justify-content: space-between; align-items: center;
            max-width: 1200px; margin: 0 auto; padding: 0 2rem;
        }
        .logo {
            font-size: 2rem; font-weight: bold;
            background: linear-gradient(135deg, #0e499f, #31c6f6);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            display: flex; align-items: center; gap: 0.5rem;
        }
        .logo::before {
            content: '🏦';
            font-size: 1.8rem;
        }
        .nav-links {
            display: flex; list-style: none; gap: 2rem;
        }
        .nav-links a {
            text-decoration: none; color: #fff; font-weight: 500; transition: color 0.3s;
        }
        .nav-links a:hover { color: #31c6f6; }
        .auth-buttons {
            display: flex; gap: 1rem;
        }
        .btn {
            padding: 0.75rem 1.5rem; border: none; border-radius: 50px;
            font-weight: 600; cursor: pointer; transition: all 0.3s;
            text-decoration: none; display: inline-block; text-align: center;
        }
        .btn-outline {
            background: transparent; border: 2px solid #31c6f6; color: #31c6f6;
        }
        .btn-outline:hover { background: #31c6f6; color: white; }
        .btn-primary {
            background: linear-gradient(135deg, #0e499f, #31c6f6); color: white; border: none;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(14,73,159,0.2);
        }

        main {
            flex: 1;
            max-width: 450px;
            margin: 8rem auto 4rem;
            background: white;
            padding: 2.5rem 2rem 3rem;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(14,73,159,0.1);
        }
        h2 {
            color: #0e499f;
            text-align: center;
            margin-bottom: 1.5rem;
            font-size: 2rem;
        }
        form label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #0e499f;
        }
        form input[type="email"],
        form input[type="password"] {
            width: 100%;
            padding: 0.75rem 1rem;
            margin-bottom: 1.5rem;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            font-size: 1rem;
            background: #f8f9fa;
            transition: all 0.3s;
        }
        form input[type="email"]:focus,
        form input[type="password"]:focus {
            outline: none;
            border-color: #31c6f6;
            background: white;
            box-shadow: 0 0 0 3px rgba(49,198,246,0.1);
        }
        button[type="submit"] {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, #0e499f, #31c6f6);
            color: white;
            border: none;
            border-radius: 50px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        button[type="submit"]:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(49,198,246,0.18);
        }
        .message {
            text-align: center;
            margin-bottom: 1rem;
            padding: 0.8rem;
            border-radius: 8px;
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
        .back-link {
            display: block;
            text-align: center;
            margin-top: 1.5rem;
            color: #0e499f;
            font-weight: 600;
            text-decoration: none;
        }
        .back-link:hover {
            text-decoration: underline;
        }
        footer.footer {
            background: #143c6b;
            color: white;
            padding: 3rem 2rem 1rem;
            text-align: center;
            margin-top: auto;
        }
        footer.footer p {
            margin: 0;
            color: #ccc;
        }
        @media (max-width: 480px) {
            main {
                margin: 6rem 1rem 3rem;
                padding: 2rem 1.5rem 2.5rem;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <nav class="nav">
            <div class="logo">OptimaBank Loyalty</div>
            <ul class="nav-links">
                <li><a href="landingpage.php#offers">Offers</a></li>
                <li><a href="landingpage.php#points">My Points</a></li>
                <li><a href="landingpage.php#stats">Stats</a></li>
            </ul>
            <div class="auth-buttons">
                <a href="landingpage.php#login" class="btn btn-outline">Log In</a>
                <a href="landingpage.php#signup" class="btn btn-primary">Sign Up</a>
            </div>
        </nav>
    </header>

    <main>
        <h2>Forgot Password</h2>

        <?php if ($error): ?>
            <div class="message error"><?php echo htmlspecialchars($error); ?></div>
        <?php elseif ($message): ?>
            <div class="message success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <?php if (!$show_password_form && !$message && !$password_reset_success): ?>
            <!-- Email check form -->
            <form method="POST" action="">
                <label for="email">Enter your registered email address:</label>
                <input type="email" id="email" name="email" required value="<?php echo htmlspecialchars($email); ?>" />
                <button type="submit" name="check_email">Submit</button>
            </form>
        <?php elseif ($show_password_form && !$message && !$password_reset_success): ?>
            <!-- Reset password form -->
            <form method="POST" action="">
                <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>" />
                <label for="new_password">New Password:</label>
                <input type="password" id="new_password" name="new_password" required />
                <label for="confirm_password">Confirm New Password:</label>
                <input type="password" id="confirm_password" name="confirm_password" required />
                <button type="submit" name="reset_password">Update Password</button>
            </form>
        <?php endif; ?>

        <a href="landingpage.php" class="back-link">&larr; Back to Home</a>
    </main>

    <!-- Footer -->
    <footer class="footer">
        <p>&copy; <?php echo date("Y"); ?> OptimaBank. All rights reserved.</p>
    </footer>

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

            <?php if ($error): ?>
                toastr.error("<?php echo addslashes($error); ?>");
            <?php elseif ($message): ?>
                toastr.success("<?php echo addslashes($message); ?>");
            <?php endif; ?>

            <?php if ($password_reset_success): ?>
                // Show browser confirm popup instead of toastr
                if (confirm("Your password has been updated successfully. Please login with your new password.")) {
                    window.location.href = "landingpage.php";
                }
            <?php endif; ?>

            <?php
            // Remove this block because now handled with confirm dialog
            /*
            if (isset($_SESSION['password_reset_success'])): ?>
                toastr.success("<?php echo addslashes($_SESSION['password_reset_success']); ?>");
                <?php unset($_SESSION['password_reset_success']); ?>
            <?php endif; ?>
            */
            ?>
        });
    </script>
</body>
</html>