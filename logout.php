<?php
session_start();

// For OptimaBank Loyalty - customized logout
if (isset($_SESSION['UserName'])) { 
    // Clear all session data
    $_SESSION = array(); 
    session_destroy();
    echo "<script>
          localStorage.removeItem('scrollPosition');
          sessionStorage.setItem('message', 'You have successfully logged out from OptimaBank Loyalty.');
          window.location.href = '/group1GIFT/landingpage.php'; 
          </script>";
} else {
    echo "<script>
          sessionStorage.setItem('message', 'Please log in to use OptimaBank Loyalty.');
          window.location.href = '/group1GIFT/landingpage.php';
          </script>";
}
exit();
?>