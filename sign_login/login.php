<?php
session_start();

include(__DIR__ . '/../connect.php');

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $userEmail = htmlspecialchars($_POST['Email']);
    $userPassword = htmlspecialchars($_POST['Password']);

    $query = "SELECT User_ID, User_Name, User_Email, User_Password, User_Role, User_Points FROM users WHERE User_Email = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $userEmail);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        if (password_verify($userPassword, $user['User_Password'])) {
            $_SESSION['UserID'] = $user['User_ID'];
            $_SESSION['UserName'] = $user['User_Name'];
            $_SESSION['UserEmail'] = $user['User_Email'];
            $_SESSION['UserRole'] = $user['User_Role'];
            $_SESSION['UserPoints'] = $user['User_Points'];

            // OptimaBank roles: customer, admin, staff
            if ($user['User_Role'] === 'customer') {
                $_SESSION['welcome'] = "Welcome " . htmlspecialchars($_SESSION['UserName']) . "!";
                header("Location: ../home.php");
                exit();
            } elseif ($user['User_Role'] === 'admin') {
                $_SESSION['welcome'] = "Welcome Admin " . htmlspecialchars($_SESSION['UserName']) . "!";
                header("Location: /optima_loyalty/admin.php");
                exit();
            } elseif ($user['User_Role'] === 'staff') {
                $_SESSION['welcome'] = "Welcome Staff " . htmlspecialchars($_SESSION['UserName']) . "!";
                header("Location: /optima_loyalty/staff.php");
                exit();
            } else {
                $_SESSION['welcome'] = "Welcome " . htmlspecialchars($_SESSION['UserName']) . "!";
                header("Location: /GROUP1GIFT/home.php");
                exit();
            }
        } else {
            echo "<script>
                sessionStorage.setItem('message', 'Invalid password.');
                window.location.href = '../landingpage.php';
            </script>";
        }
    } else {
        echo "<script>
            sessionStorage.setItem('message', 'Invalid email address.');
            window.location.href = '../landingpage.php';
        </script>";
    }

    $stmt->close();
}

$conn->close();
?>