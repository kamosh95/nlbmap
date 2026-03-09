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
            
            $subject = "Password Reset Request - NLB Map Portal";
            
            // Professional HTML Email Template
            $message = '
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="UTF-8">
                <style>
                    body { font-family: Arial, sans-serif; background-color: #f4f7f6; margin: 0; padding: 0; }
                    .email-wrapper { max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 10px rgba(0,0,0,0.05); }
                    .header { background: linear-gradient(135deg, #0072ff, #00d4ff); padding: 20px; text-align: center; color: #ffffff; }
                    .header h1 { margin: 0; font-size: 24px; font-weight: bold; }
                    .content { padding: 30px; color: #333333; line-height: 1.6; }
                    .content h2 { color: #333333; font-size: 20px; margin-top: 0; }
                    .btn-container { text-align: center; margin: 30px 0; }
                    .btn { display: inline-block; padding: 12px 25px; background-color: #0072ff; color: #ffffff; text-decoration: none; border-radius: 5px; font-weight: bold; font-size: 16px; }
                    .footer { background-color: #f9f9f9; padding: 20px; text-align: center; color: #777777; font-size: 12px; border-top: 1px solid #eeeeee; }
                </style>
            </head>
            <body>
                <div class="email-wrapper">
                    <div class="header">
                        <h1>NLB Portal Security</h1>
                    </div>
                    <div class="content">
                        <h2>Password Reset Request</h2>
                        <p>Hello <strong>' . htmlspecialchars($user['full_name']) . '</strong>,</p>
                        <p>We received a request to reset your password for the NLB Seller Map Portal. If you made this request, please click the button below to set a new password. This link is valid for <strong>1 hour</strong>.</p>
                        <div class="btn-container">
                            <a href="' . $resetLink . '" class="btn">Reset Password</a>
                        </div>
                        <p>If the button doesn\'t work, copy and paste the following link into your browser:</p>
                        <p style="word-break: break-all; font-size: 14px; color: #0072ff;"><a href="' . $resetLink . '">' . $resetLink . '</a></p>
                        <p>If you did not request a password reset, you can safely ignore this email. Your account remains secure.</p>
                    </div>
                    <div class="footer">
                        <p>This is an automated message from the National Lotteries Board Information Technology Division. Please do not reply to this email.</p>
                        <p>&copy; ' . date('Y') . ' National Lotteries Board. All rights reserved.</p>
                    </div>
                </div>
            </body>
            </html>
            ';
            
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
            if (savedTheme === "dark" || !savedTheme) {
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
                <h3 style="color: var(--text-main); margin-bottom: 0.5rem;">Email Sent!</h3>
                <p style="color: var(--text-muted); line-height: 1.5;"><?php echo $success; ?></p>
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
