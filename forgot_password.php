<?php
session_start();
require 'connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';

    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = "Please enter a valid email address";
        header("Location: landingpage.php#forgotPasswordModal");
        exit();
    }

    // Check if email exists
    $stmt = $conn->prepare("SELECT User_ID FROM users WHERE User_Email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $_SESSION['error'] = "No account found with that email address";
        header("Location: landingpage.php#forgotPasswordModal");
        exit();
    }

    $user = $result->fetch_assoc();
    $_SESSION['reset_user_id'] = $user['User_ID']; // store user ID temporarily

    $_SESSION['show_reset_modal'] = true;
    header("Location: landingpage.php#resetPasswordModal");
    exit();
}
?>
