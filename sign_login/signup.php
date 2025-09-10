<?php
session_start();

include(__DIR__ . '/../connect.php');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Collect and sanitize inputs
    $userName = htmlspecialchars($_POST['Name']);
    $userEmail = htmlspecialchars($_POST['Email']);
    $userPassword = htmlspecialchars($_POST['Password']);
    $confirmPassword = htmlspecialchars($_POST['ConfirmPassword']);
    $userRole = isset($_POST['userRole']) ? htmlspecialchars($_POST['userRole']) : 'customer';

    // OptimaBank roles: customer, admin, staff
    if (!in_array($userRole, ['customer', 'admin', 'staff'])) {
        $userRole = 'customer';
    }

    // Validate password match
    if ($userPassword !== $confirmPassword) {
        echo "<script>
        sessionStorage.setItem('message', 'Passwords do not match. Please try again.');
        window.location.href = '../landingpage.php';
        </script>";
        $conn->close();
        exit();
    }

    // Validate password length
    if (strlen($userPassword) < 6) {
        echo "<script>
        sessionStorage.setItem('message', 'Password must be at least 6 characters long.');
        window.location.href = '../landingpage.php';
        </script>";
        $conn->close();
        exit();
    }

    $hashedPassword = password_hash($userPassword, PASSWORD_BCRYPT);

    // Check if username exists
    $checkNameQuery = "SELECT User_Name FROM users WHERE User_Name = ?";
    $checkNameStmt = $conn->prepare($checkNameQuery);
    $checkNameStmt->bind_param("s", $userName);
    $checkNameStmt->execute();
    $checkNameStmt->store_result();

    if ($checkNameStmt->num_rows > 0) {
        echo "<script>
        sessionStorage.setItem('message', 'The username already exists. Please use a different username.');
        window.location.href = '../landingpage.php';
        </script>";
        $checkNameStmt->close();
        $conn->close();
        exit();
    }
    $checkNameStmt->close();

    // Check if email exists
    $checkEmailQuery = "SELECT User_Email FROM users WHERE User_Email = ?";
    $checkEmailStmt = $conn->prepare($checkEmailQuery);
    $checkEmailStmt->bind_param("s", $userEmail);
    $checkEmailStmt->execute();
    $checkEmailStmt->store_result();

    if ($checkEmailStmt->num_rows > 0) {
        echo "<script>
        sessionStorage.setItem('message', 'The email already exists. Please use a different email.');
        window.location.href = '../landingpage.php';
        </script>";
        $checkEmailStmt->close();
        $conn->close();
        exit();
    }
    $checkEmailStmt->close();

    // Insert new user for OptimaBank Loyalty
    $insertStmt = $conn->prepare("INSERT INTO users (User_Name, User_Email, User_Password, User_Role, User_Points) VALUES (?, ?, ?, ?, 0)");
    $insertStmt->bind_param("ssss", $userName, $userEmail, $hashedPassword, $userRole);

    if ($insertStmt->execute()) {
        $user_id = $insertStmt->insert_id;

        $_SESSION['UserID'] = $user_id;
        $_SESSION['UserName'] = $userName;
        $_SESSION['UserEmail'] = $userEmail;
        $_SESSION['UserRole'] = $userRole;
        $_SESSION['UserPoints'] = 0;

        echo "<script>
            sessionStorage.setItem('name', 'Welcome " . htmlspecialchars($_SESSION['UserName']) . "!');
        </script>";

        // Redirect based on OptimaBank role
        if ($userRole === 'admin') {
            header("Location: /optima_loyalty/admin.php");
        } elseif ($userRole === 'staff') {
            header("Location: /optima_loyalty/staff.php");
        } else { // customer
            header("Location: /optima_loyalty/home.php");
        }
        exit();
    } else {
        echo "Error: " . $conn->error;
    }

    $insertStmt->close();
}

$conn->close();
?>