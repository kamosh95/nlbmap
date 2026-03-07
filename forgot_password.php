<?php
require_once 'includes/security.php';
require_once 'includes/db_config.php';
require_once 'includes/mail_helper.php';

// Ensure reset table exists
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS password_resets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(100) NOT NULL,
        token VARCHAR(100) NOT NULL,
        expires_at DATETIME NOT NULL,
        INDEX (email),
        INDEX (token)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
} catch (PDOException $e) {
    // Table already exists or error
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token($_POST['csrf_token'] ?? '');
    
    $email = trim($_POST['email']);
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {
        // Check if user exists
        $stmt = $pdo->prepare("SELECT full_name FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user) {
            // Generate unique token
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            // Delete old tokens for this email
            $stmt = $pdo->prepare("DELETE FROM password_resets WHERE email = ?");
            $stmt->execute([$email]);
            
            // Save new token
            $stmt = $pdo->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
            $stmt->execute([$email, $token, $expires]);
            
            // Send Email
            $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]" . dirname($_SERVER['PHP_SELF']);
            $resetLink = $baseUrl . "/reset_password.php?token=" . $token;
            
            $subject = "Password Reset Request - NLB Portal";
            $message = "Hello " . $user['full_name'] . ",\n\n";
            $message .= "We received a request to reset your password for the NLB Seller Map Portal.\n";
            $message .= "Click the link below to set a new password. This link will expire in 1 hour.\n\n";
            $message .= $resetLink . "\n\n";
            $message .= "If you did not request this, please ignore this email.\n\n";
            $message .= "Best Regards,\nNLB Administration Team";
            
            if (send_notification_email($email, $user['full_name'], $subject, $message)) {
                $success = "Email sent! Please check your inbox for the reset link.";
                log_activity($pdo, "Password Reset Requested", "Email: $email", "auth");
            } else {
                $error = "Failed to send email. Please try again later.";
            }
        } else {
            // Don't reveal if email exists for security, but user wants it to be helpful
            $error = "No account found with that email address.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - NLB Portal</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="assets/img/logo1.png">
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
        .auth-container {
            max-width: 450px;
            margin: 5rem auto;
            padding: 2rem;
            background: var(--card-bg);
            border: 1px solid var(--glass-border);
            border-radius: 24px;
            box-shadow: 0 40px 100px -20px rgba(0,0,0,0.5);
            backdrop-filter: blur(20px);
            animation: slideUp 0.6s cubic-bezier(0.16, 1, 0.3, 1);
        }
        @keyframes slideUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        
        .icon-box {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            margin: 0 auto 1.5rem;
            box-shadow: 0 15px 30px rgba(0, 114, 255, 0.3);
        }
    </style>
</head>
<body class="auth-page">
    <div class="container auth-container">
        <div style="text-align: center; margin-bottom: 2rem;">
            <div class="icon-box">🔑</div>
            <h1 style="font-size: 1.8rem; font-weight: 800; margin-bottom: 0.5rem;">Reset Password</h1>
            <p class="subtitle">Enter your email to receive a secure reset link</p>
        </div>

        <?php if ($error): ?>
            <div class="message error" style="display: block; margin-bottom: 1.5rem;"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="message success" style="display: block; padding: 2rem; text-align: center;">
                <div style="font-size: 3rem; margin-bottom: 1rem;">✉️</div>
                <h3 style="color: #fff; margin-bottom: 0.5rem;">Email Sent!</h3>
                <p style="color: rgba(255,255,255,0.8); line-height: 1.5;"><?php echo $success; ?></p>
                <div style="margin-top: 2rem;">
                    <a href="login.php" class="btn-submit" style="display: inline-block; text-decoration: none; padding: 12px 30px;">Return to Login</a>
                </div>
            </div>
        <?php else: ?>
            <form method="POST">
                <?php csrf_input(); ?>
                <div class="form-group">
                    <label for="email">Official Email Address</label>
                    <input type="email" id="email" name="email" placeholder="official@nlb.lk" required autofocus>
                </div>

                <button type="submit" class="btn-submit" style="margin-top: 1rem;">Send Reset Link</button>
                
                <div style="text-align: center; margin-top: 2rem;">
                    <a href="login.php" style="color: var(--text-muted); text-decoration: none; font-size: 0.9rem; font-weight: 500;">
                        Wait, I remembered it! <span style="color: var(--secondary-color); font-weight: 700;">Sign In</span>
                    </a>
                </div>
            </form>
        <?php endif; ?>
    </div>
    <?php include 'includes/footer.php'; ?>
</body>
</html>
