<?php
header("Cross-Origin-Opener-Policy: same-origin-allow-popups");
header("Cross-Origin-Embedder-Policy: credentialless");
header("Cross-Origin-Resource-Policy: cross-origin");

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OptimaBank Loyalty Rewards</title>
    <link rel="stylesheet" href="toastr.min.css">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script src="toastr.min.js"></script>
    <script src="google-signin.js"></script>
    <script src="https://accounts.google.com/gsi/client" async defer></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            overflow-x: hidden;
            background: #f4f7fc;
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
        .hero {
            min-height: 100vh;
            background: linear-gradient(135deg, rgba(14,73,159,0.94) 0%, rgba(49,198,246,0.88) 100%);
            display: flex; align-items: center; justify-content: center; text-align: center; color: white;
            position: relative; overflow: hidden;
        }
        .hero-content { max-width: 800px; z-index: 2; position: relative; }
        .hero h1 {
            font-size: 3rem; margin-bottom: 1rem; opacity: 0; animation: fadeInUp 1s ease forwards;
        }
        .hero p {
            font-size: 1.25rem; margin-bottom: 2rem; opacity: 0; animation: fadeInUp 1s ease 0.3s forwards;
        }
        .hero-buttons {
            opacity: 0; animation: fadeInUp 1s ease 0.6s forwards;
        }
        .hero-buttons .btn { margin: 0 0.5rem; padding: 1rem 2rem; font-size: 1.1rem; }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(50px);}
            to { opacity: 1; transform: translateY(0);}
        }
        .points-section {
            background: #fff; padding: 4rem 2rem; text-align: center;
        }
        .points-title { font-size: 2rem; margin-bottom: 1rem; color: #0e499f; }
        .points-balance {
            font-size: 2.5rem; font-weight: bold; color: #31c6f6; margin-bottom: 0.5rem;
        }
        .points-desc { color: #666; margin-bottom: 2rem; }
        .offers-section {
            padding: 4rem 2rem; background: #f8f9fa;
        }
        .offers-title { font-size: 2rem; margin-bottom: 2rem; color: #0e499f; text-align: center; }
        .offers-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem;
            max-width: 1200px; margin: 0 auto;
        }
        .offer-card {
            background: white;
            padding: 2rem; border-radius: 20px; box-shadow: 0 10px 30px rgba(14,73,159,0.05);
            text-align: center; position: relative; transition: transform 0.3s, box-shadow 0.3s;
        }
        .offer-card:hover {
            transform: translateY(-10px); box-shadow: 0 20px 50px rgba(49,198,246,0.10);
        }
        .offer-icon {
            width: 70px; height: 70px; background: #31c6f6;
            border-radius: 50%; display: flex; align-items: center; justify-content: center;
            margin: 0 auto 1rem; font-size: 2rem; color: white;
        }
        .offer-title { font-size: 1.5rem; margin-bottom: 1rem; color: #0e499f; }
        .offer-desc { color: #666; margin-bottom: 1rem; }
        .offer-points {
            font-weight: bold; color: #0e499f; margin-bottom: 1rem;
        }
        .offer-card .btn {
            background: linear-gradient(135deg, #0e499f, #31c6f6); color: white;
            border: none; width: 100%; margin-top: 1rem;
        }
        .offer-card .btn:hover { background: #31c6f6; color: #fff; }
        .stats {
            padding: 3rem 2rem; background: #0e499f; color: white;
        }
        .stats-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 2rem;
            text-align: center; position: relative; z-index: 2;
        }
        .stat-item {
            background: rgba(49,198,246,0.15); padding: 2rem; border-radius: 15px;
            backdrop-filter: blur(10px);
        }
        .stat-item h3 { font-size: 2rem; margin-bottom: 0.5rem; color: #31c6f6; }
        .stat-item p { font-size: 1.1rem; opacity: 0.9; }
        .cta {
            padding: 5rem 2rem;
            background: linear-gradient(135deg, #0e499f, #31c6f6);
            color: white; text-align: center; position: relative;
        }
        .cta h2 { font-size: 2.5rem; margin-bottom: 1rem; }
        .cta p { font-size: 1.2rem; margin-bottom: 2rem; opacity: 0.9; }
        .cta .btn { position: relative; z-index: 2; background: rgba(255,255,255,0.2); border: 2px solid rgba(255,255,255,0.3);}
        .footer {
            background: #143c6b; color: white; padding: 3rem 2rem 1rem;
        }
        .footer-content {
            max-width: 1200px; margin: 0 auto;
            display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 2rem;
        }
        .footer-section h3 { margin-bottom: 1rem; color: #31c6f6; display: flex; align-items: center; gap: 0.5rem; }
        .footer-section h3::before { content: '🏦'; font-size: 1.2rem;}
        .footer-section ul { list-style: none; }
        .footer-section ul li { margin-bottom: 0.5rem;}
        .footer-section a { color: #ccc; text-decoration: none; transition: color 0.3s;}
        .footer-section a:hover { color: #31c6f6;}
        .footer-bottom {
            text-align: center; padding-top: 2rem; margin-top: 2rem; border-top: 1px solid #555; color: #999;
        }
        @media (max-width: 768px) {
            .nav-links { display: none; }
            .hero h1 { font-size: 2rem;}
            .hero-buttons .btn { display: block; margin: 0.5rem 0;}
            .auth-buttons { flex-direction: column; gap: 0.5rem;}
        }
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(5px);
            animation: fadeIn 0.3s ease;
        }
        .modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        .modal-content {
            background: white;
            margin: 2rem;
            padding: 0;
            border-radius: 20px;
            width: 100%;
            max-width: 450px;
            position: relative;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: slideIn 0.3s ease;
            overflow: hidden;
        }
        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-50px);}
            to { opacity: 1; transform: translateY(0);}
        }
        .modal-header {
            background: linear-gradient(135deg, #0e499f, #31c6f6);
            color: white;
            padding: 2rem;
            text-align: center;
            position: relative;
        }
        .modal-header h2 { margin: 0; font-size: 2rem; position: relative; z-index: 1;}
        .modal-header p { margin: 0.5rem 0 0 0; opacity: 0.9; position: relative; z-index: 1;}
        .close {
            position: absolute;
            right: 1rem;
            top: 1rem;
            color: white;
            font-size: 2rem;
            font-weight: bold;
            cursor: pointer;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: rgba(255,255,255,0.15);
            transition: all 0.3s;
            z-index: 2;
        }
        .close:hover { background: rgba(255,255,255,0.3); transform: rotate(90deg);}
        .modal-body { padding: 2rem;}
        .form-group { margin-bottom: 1.5rem;}
        .form-group label { display: block; margin-bottom: 0.5rem; color: #333; font-weight: 600;}
        .form-control {
            width: 100%; padding: 1rem; border: 2px solid #e9ecef;
            border-radius: 10px; font-size: 1rem; transition: all 0.3s;
            background: #f8f9fa;
        }
        .form-control:focus {
            outline: none;
            border-color: #31c6f6;
            background: white;
            box-shadow: 0 0 0 3px rgba(49,198,246,0.1);
        }
        .btn-modal {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, #0e499f, #31c6f6);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-bottom: 1rem;
        }
        .btn-modal:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(49,198,246,0.18);
        }
        .modal-footer {
            text-align: center; padding: 1rem 2rem 2rem; color: #666;
        }
        .modal-footer a {
            color: #0e499f;
            text-decoration: none;
            font-weight: 600;
        }
        .modal-footer a:hover {
            text-decoration: underline;
        }

        .toggle-password {
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    cursor: pointer;
    font-size: 1.2rem;
    color: #666;
    transition: color 0.3s;
}
.toggle-password:hover {
    color: #0e499f;
}

        @media (max-width: 768px) {
            .modal-content { margin: 1rem; max-width: none;}
            .modal-header { padding: 1.5rem;}
            .modal-body { padding: 1.5rem;}
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <nav class="nav">
            <div class="logo">OptimaBank Loyalty</div>
            <ul class="nav-links">
                <li><a href="#offers">Offers</a></li>
                <li><a href="#points">My Points</a></li>
                <li><a href="#stats">Stats</a></li>
            </ul>
            <div class="auth-buttons">
                <a href="#login" class="btn btn-outline">Log In</a>
                <a href="#signup" class="btn btn-primary">Sign Up</a>
            </div>
        </nav>
    </header>
    <!-- Hero Section -->
    <section class="hero" id="home">
        <div class="hero-content">
            <h1>Welcome to OptimaBank Loyalty</h1>
            <p>
                Redeem exclusive rewards, offers, and vouchers with your points. Boost your banking experience and enjoy more with Malaysia’s leading customer-centric bank.
            </p>
            <div class="hero-buttons">
                <a href="#signup" class="btn btn-primary">Join & Start Earning</a>
                <a href="#offers" class="btn btn-outline">View Rewards</a>
            </div>
        </div>
    </section>
    <!-- Loyalty Points Overview -->
    <section class="points-section" id="points">
        <div>
            <div class="points-title">Your Loyalty Points</div>
            <div class="points-balance" id="points-balance">1,500</div>
            <div class="points-desc">Earn points with every transaction, referral, and engagement. Redeem points for competitive offers and vouchers below!</div>
        </div>
    </section>
    <!-- Offers/Vouchers Section -->
    <section class="offers-section" id="offers">
        <h2 class="offers-title">Redeem Exclusive Rewards</h2>
        <div class="offers-grid">
            <div class="offer-card">
                <div class="offer-icon">🎁</div>
                <div class="offer-title">RM50 Shopping Voucher</div>
                <div class="offer-desc">Spend at selected retailers and partners.</div>
                <div class="offer-points">1,000 Points</div>
                <button class="btn" onclick="redeemOffer('RM50 Shopping Voucher', 1000)">Redeem</button>
            </div>
            <div class="offer-card">
                <div class="offer-icon">🍽️</div>
                <div class="offer-title">Dining Discount</div>
                <div class="offer-desc">Enjoy 20% off at top restaurants in Malaysia.</div>
                <div class="offer-points">700 Points</div>
                <button class="btn" onclick="redeemOffer('Dining Discount', 700)">Redeem</button>
            </div>
            <div class="offer-card">
                <div class="offer-icon">🚗</div>
                <div class="offer-title">Petrol Cashback</div>
                <div class="offer-desc">Get RM30 cashback on your next petrol refill.</div>
                <div class="offer-points">900 Points</div>
                <button class="btn" onclick="redeemOffer('Petrol Cashback', 900)">Redeem</button>
            </div>
            <div class="offer-card">
                <div class="offer-icon">🛍️</div>
                <div class="offer-title">Online Store Voucher</div>
                <div class="offer-desc">RM25 voucher for popular online shops.</div>
                <div class="offer-points">500 Points</div>
                <button class="btn" onclick="redeemOffer('Online Store Voucher', 500)">Redeem</button>
            </div>
        </div>
    </section>
    <!-- Engagement Stats -->
    <section class="stats" id="stats">
        <div class="stats-grid">
            <div class="stat-item">
                <h3>10,000+</h3>
                <p>Active Members</p>
            </div>
            <div class="stat-item">
                <h3>RM500,000+</h3>
                <p>Total Rewards Redeemed</p>
            </div>
            <div class="stat-item">
                <h3>95%</h3>
                <p>User Satisfaction</p>
            </div>
            <div class="stat-item">
                <h3>80%</h3>
                <p>Retention Rate</p>
            </div>
        </div>
    </section>
    <!-- CTA Section -->
    <section class="cta">
        <div>
            <h2>Stay Loyal, Get Rewarded</h2>
            <p>
                Be part of Malaysia's most rewarding banking experience. Earn more, redeem faster, and enjoy exclusive benefits!
            </p>
            <a href="#signup" class="btn btn-primary">Sign Up Free</a>
        </div>
    </section>
    <!-- Footer -->
    <footer class="footer">
        <div class="footer-content">
            <div class="footer-section">
                <h3>OptimaBank Loyalty</h3>
                <p>Serving Malaysians with innovative banking and rewarding experiences. Your loyalty matters.</p>
            </div>
            <div class="footer-section">
                <h3>Quick Links</h3>
                <ul>
                    <li><a href="#offers">Offers</a></li>
                    <li><a href="#points">My Points</a></li>
                    <li><a href="#stats">Stats</a></li>
                </ul>
            </div>
            <div class="footer-section">
                <h3>Account</h3>
                <ul>
                    <li><a href="#login">Log In</a></li>
                    <li><a href="#signup">Sign Up</a></li>
                </ul>
            </div>
            <div class="footer-section">
                <h3>Support</h3>
                <ul>
                    <li><a href="#help">Help Center</a></li>
                    <li><a href="#contact">Contact Us</a></li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; 2025 OptimaBank. All rights reserved.</p>
        </div>
    </footer>
    <!-- Login Modal -->
   <div id="loginModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <span class="close" onclick="closeModal('loginModal')">&times;</span>
            <h2>Welcome Back!</h2>
            <p>Sign in to access your loyalty account</p>
        </div>
        <div class="modal-body">
            <!-- Change the action to match your PHP login script path -->
            <form id="loginForm" action="/group1GIFT/sign_login/login.php" method="POST">
                <div class="form-group">
                    <label for="loginEmail">Email Address</label>
                    <input type="email" id="loginEmail" name="Email" class="form-control" placeholder="Enter your email" required>
                </div>
                <div class="form-group">
    <label for="loginPassword">Password</label>
    <div style="position: relative;">
        <input type="password" id="loginPassword" name="Password" class="form-control" placeholder="Enter your password" required>
        <button type="button" class="toggle-password" data-target="loginPassword">👁</button>
    </div>
</div>

                <button type="submit" class="btn-modal">Sign In</button>
            </form>

            <!-- <div class="google-signup">
                <button class="btn-google form-control" onclick="signInWithGoogle()"  id="g_id_signin">
                    <img src="https://developers.google.com/identity/images/g-logo.png" alt="Google Logo"  style="width:20px; height:20px; vertical-align:middle; margin-right:10px;">
                    Sign In with Google
                    </button>
            </div> -->
            <div id="g_id_signin">
                <div 
                    id="g_id_onload"
                    data-context="signin"
                    data-ux_mode="popup"
                    data-login_uri="gift1bankvoucher/sign_login/google_login.php"
                    data-auto_prompt="false">
                </div>

                <div 
                    class="g_id_signin"
                    data-type="standard"
                    data-shape="rectangular"
                    data-theme="outline"
                    data-text="continue_with"
                    data-size="large"
                    data-logo_alignment="left">
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <p>Don't have an account? <a href="#" onclick="switchModal('loginModal', 'signupModal')">Sign up here</a></p>
            <p><a href="/group1GIFT/forgot_password.php">Forgot your password?</a></p>
        </div>
    </div>
</div>
    <!-- Signup Modal -->
    <div id="signupModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <span class="close" onclick="closeModal('signupModal')">&times;</span>
                <h2>Join OptimaBank Loyalty</h2>
                <p>Register now and start earning points instantly!</p>
            </div>
            <div class="modal-body">
                 <form id="signupForm" action="sign_login/signup.php" method="POST">
                    <div class="form-group">
                        <label for="signupName">Username</label>
                        <input type="text" id="signupName" name="Name" class="form-control" placeholder="Enter your user name" required>
                    </div>
                    <div class="form-group">
                        <label for="signupEmail">Email Address</label>
                        <input type="email" id="signupEmail" name="Email" class="form-control" placeholder="Enter your email" required>
                    </div>
                   <div class="form-group">
    <label for="signupPassword">Password</label>
    <div style="position: relative;">
        <input type="password" id="signupPassword" name="Password" class="form-control" placeholder="Create a password" required>
        <button type="button" class="toggle-password" data-target="signupPassword">👁</button>
    </div>
</div>

<div class="form-group">
    <label for="confirmPassword">Confirm Password</label>
    <div style="position: relative;">
        <input type="password" id="confirmPassword" name="ConfirmPassword" class="form-control" placeholder="Confirm your password" required>
        <button type="button" class="toggle-password" data-target="confirmPassword">👁</button>
    </div>
</div>

                    <button type="submit" class="btn-modal">Create Account</button>
                </form>
            </div>
            <div class="modal-footer">
                <p>Already have an account? <a href="#" onclick="switchModal('signupModal', 'loginModal')">Sign in here</a></p>
            </div>
        </div>
    </div>
    <script>
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                if (this.getAttribute('href') === "#") return;
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
        // function signUpWithGoogle() {
        //     alert("Google Sign Up clicked! Implement Google OAuth here.");
        // }
        // function signInWithGoogle() {
        //     alert("Google Sign In clicked! Implement Google OAuth here.");
        // }
        function openModal(modalId) {
            document.getElementById(modalId).classList.add('show');
            document.body.style.overflow = 'hidden';
        }
        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('show');
            document.body.style.overflow = 'auto';
        }
        function switchModal(currentModal, targetModal) {
            closeModal(currentModal);
            setTimeout(() => openModal(targetModal), 300);
        }
        document.querySelectorAll('a[href="#login"]').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                openModal('loginModal');
            });
        });
        document.querySelectorAll('a[href="#signup"]').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                openModal('signupModal');
            });
        });
        window.addEventListener('click', function(e) {
            if (e.target.classList.contains('modal')) {
                closeModal(e.target.id);
            }
        });
        function redeemOffer(offerName, offerCost) {
            let balanceElem = document.getElementById('points-balance');
            let currentPoints = parseInt(balanceElem.innerText.replace(/,/g, ''));
            if (currentPoints >= offerCost) {
                currentPoints -= offerCost;
                balanceElem.innerText = currentPoints.toLocaleString();
                toastr.success(`Successfully redeemed "${offerName}"!`);
            } else {
                toastr.error('Not enough points to redeem this offer.');
            }
        }
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
            toastr.success('Welcome to OptimaBank Loyalty Rewards!');
        });

    // Toggle show/hide password
document.querySelectorAll('.toggle-password').forEach(button => {
    button.addEventListener('click', function () {
        const input = document.getElementById(this.dataset.target);
        if (input.type === "password") {
            input.type = "text";
            this.textContent = "🙈"; // change icon
        } else {
            input.type = "password";
            this.textContent = "👁"; // back to eye
        }
    });
});

    </script>

</body>
</html>
