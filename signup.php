<?php
require_once 'includes/security.php';
if (!file_exists('includes/db_config.php')) {
    header("Location: installer.php");
    exit;
}
require_once 'includes/db_config.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF
    verify_csrf_token($_POST['csrf_token'] ?? '');

    $username = trim($_POST['username']);
    $full_name = trim($_POST['full_name']);
    $emp_no = trim($_POST['emp_no']);
    $mobile_no = trim($_POST['mobile_no']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role = $_POST['role'] ?? 'user';

    // Basic Validation
    if (strlen($username) < 3) {
        $error = "Username must be at least 3 characters long.";
    } elseif (empty($full_name)) {
        $error = "Full Name is required.";
    } elseif (empty($emp_no)) {
        $error = "Employee Number is required.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters long.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        // Check if username exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            $error = "Username already taken. Please choose another.";
        } else {
            // Hash password and insert
            $hashed_pass = password_hash($password, PASSWORD_DEFAULT);
            try {
                $stmt = $pdo->prepare("INSERT INTO users (username, full_name, emp_no, mobile_no, email, password, role, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')");
                $stmt->execute([$username, $full_name, $emp_no, $mobile_no, $email, $hashed_pass, $role]);
                
                log_activity($pdo, "User Signup", "New registration request: $username ($full_name)", "auth");
                $success = "Registration request sent! Please wait for Admin approval before logging in.";
            } catch (PDOException $e) {
                $error = "Registration failed. Please try again later. " . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account - NLB Portal</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <script>
        (function() {
            const savedTheme = localStorage.getItem("theme");
            if (savedTheme === "dark") {
                document.documentElement.classList.add("dark-mode");
            }
        })();
    </script>
    <style>
        .signup-container {
            max-width: 550px;
            margin: 2rem auto;
            animation: fadeIn 0.5s ease-out;
        }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        
        .role-selector {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 1.5rem;
        }
        .role-option {
            padding: 12px;
            border-radius: 12px;
            border: 1px solid var(--glass-border);
            text-align: center;
            cursor: pointer;
            transition: 0.3s;
            background: rgba(255, 255, 255, 0.02);
            font-size: 0.85rem;
            color: var(--text-muted);
            font-weight: 500;
        }
        .role-option:hover { background: rgba(255, 255, 255, 0.05); }
        .role-option.active {
            background: rgba(0, 114, 255, 0.1);
            border-color: var(--secondary-color);
            color: var(--secondary-color);
            box-shadow: 0 0 15px rgba(0, 114, 255, 0.2);
        }
        input[type="radio"] { display: none; }
        
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        @media (max-width: 600px) {
            .form-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body class="auth-page">
    <div class="container signup-container">
        <div style="text-align: center; margin-bottom: 2rem;">
            <img src="assets/img/Logo.png" alt="NLB Logo" style="max-width: 100px; height: auto;">
            <h1 style="margin-top: 1rem; font-size: 1.8rem; font-weight: 800;">Join NLB Portal</h1>
            <p class="subtitle">Enter your official details to register</p>
        </div>

        <?php if ($error): ?>
            <div class="message error" style="display: block;"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="message success" style="display: block; padding: 2rem; text-align: center;">
                <div style="font-size: 3rem; margin-bottom: 1rem;">⏳</div>
                <h3 style="color: #fff; margin-bottom: 0.5rem;">Registration Received!</h3>
                <p style="color: rgba(255,255,255,0.8); line-height: 1.5;"><?php echo $success; ?></p>
                <div style="margin-top: 2rem;">
                    <a href="login.php" class="btn-submit" style="display: inline-block; text-decoration: none; padding: 12px 30px;">Back to Login</a>
                </div>
            </div>
        <?php else: ?>
            <form action="signup.php" method="POST" id="signupForm">
                <?php csrf_input(); ?>
                
                <div class="form-group">
                    <label>System Role Request</label>
                    <div class="role-selector">
                        <label class="role-option active" onclick="updateRoles(this)">
                            <input type="radio" name="role" value="tm" checked>
                            Field Agent (TM)
                        </label>
                        <label class="role-option" onclick="updateRoles(this)">
                            <input type="radio" name="role" value="user">
                            Regular User
                        </label>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label for="full_name">Full Name</label>
                        <input type="text" id="full_name" name="full_name" placeholder="As per NIC" required>
                    </div>
                    <div class="form-group">
                        <label for="emp_no">Employee Number</label>
                        <input type="text" id="emp_no" name="emp_no" placeholder="Official ID" required>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label for="mobile_no">Mobile Number</label>
                        <div class="phone-input-wrap">
                            <div class="phone-prefix">
                                <span class="flag">🇱🇰</span>
                                <span class="phone-prefix-code">+94</span>
                            </div>
                            <input type="tel" id="mobile_no" name="mobile_no" placeholder="7x xxxxxxx" pattern="[0-9]{9}" title="Please enter 9 digits" style="border:none; border-radius:0;">
                        </div>
                        <p style="font-size: 0.72rem; color: var(--text-muted); margin-top: 5px;">(Without leading 0. Ex: 712345678)</p>
                    </div>
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <div class="phone-input-wrap">
                            <div class="phone-prefix" style="min-width: 60px;">
                                <span style="font-size: 1.2rem;">✉️</span>
                            </div>
                            <input type="email" id="email" name="email" placeholder="official@nlb.lk" style="border:none; border-radius:0;">
                        </div>
                    </div>
                </div>

                <div class="form-group" style="margin-top: 10px; border-top: 1px solid rgba(255,255,255,0.05); padding-top: 20px;">
                    <label for="username">Login Username</label>
                    <input type="text" id="username" name="username" placeholder="Choose a login ID" required autocomplete="username">
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" placeholder="Create password" required autocomplete="new-password">
                    </div>
                    <div class="form-group">
                        <label for="confirm_password">Confirm Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" placeholder="Repeat password" required autocomplete="new-password">
                    </div>
                </div>

                <button type="submit" class="btn-submit" style="margin-top: 1rem;">Submit Registration</button>
                
                <div style="text-align: center; margin-top: 1.5rem;">
                    <p style="color: var(--text-muted); font-size: 0.9rem;">
                        Already registered? <a href="login.php" style="color: var(--secondary-color); text-decoration: none; font-weight: 700;">Sign In</a>
                    </p>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <script>
        function updateRoles(labelElement) {
            // Remove active class from all options
            document.querySelectorAll('.role-option').forEach(opt => opt.classList.remove('active'));
            
            // Add active class to the clicked label
            labelElement.classList.add('active');
            
            // Ensure the radio input inside is checked
            const radio = labelElement.querySelector('input[type="radio"]');
            if (radio) {
                radio.checked = true;
            }
        }
    </script>
    <?php include 'includes/footer.php'; ?>
</body>
</html>
