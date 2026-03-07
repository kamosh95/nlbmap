<?php
require_once 'includes/security.php';
require_once 'includes/db_config.php';

$error = '';
$success = '';
$token = $_GET['token'] ?? ($_POST['token'] ?? '');

if (!$token) {
    header("Location: login.php");
    exit;
}

// Validate Token
$stmt = $pdo->prepare("SELECT email, expires_at FROM password_resets WHERE token = ?");
$stmt->execute([$token]);
$reset = $stmt->fetch();

if ($reset) {
    // Check if expired (MySQL DATETIME is string, compare with current time)
    if (strtotime($reset['expires_at']) < time()) {
        $error = "This reset link has expired. Please request a new one.";
        $reset = false;
    }
} else {
    $error = "This reset link is invalid. Please request a new one.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $reset) {
    verify_csrf_token($_POST['csrf_token'] ?? '');
    
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (strlen($password) < 6) {
        $error = "Password must be at least 6 characters long.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        // Update user password
        $hashed_pass = password_hash($password, PASSWORD_DEFAULT);
        try {
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
            if ($stmt->execute([$hashed_pass, $reset['email']])) {
                // Delete token
                $stmt = $pdo->prepare("DELETE FROM password_resets WHERE email = ?");
                $stmt->execute([$reset['email']]);
                
                $success = "Password reset successfully! You can now log in with your new password.";
                log_activity($pdo, "Password Reset Success", "Email: " . $reset['email'], "auth");
            } else {
                $error = "Failed to update password. Please contact support.";
            }
        } catch (PDOException $e) {
            $error = "An error occurred. Please try again later.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set New Password - NLB Portal</title>
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
            background: linear-gradient(135deg, #10b981, #059669);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            margin: 0 auto 1.5rem;
            box-shadow: 0 15px 30px rgba(16, 185, 129, 0.3);
        }
    </style>
</head>
<body class="auth-page">
    <div class="container auth-container">
        <div style="text-align: center; margin-bottom: 2rem;">
            <div class="icon-box">🛡️</div>
            <h1 style="font-size: 1.8rem; font-weight: 800; margin-bottom: 0.5rem;">New Password</h1>
            <p class="subtitle">Set a strong password to protect your account</p>
        </div>

        <?php if ($error): ?>
            <div class="message error" style="display: block; margin-bottom: 1.5rem;"><?php echo $error; ?></div>
            <?php if (!$reset): ?>
                <div style="text-align: center;">
                    <a href="forgot_password.php" class="btn-submit" style="display: inline-block; text-decoration: none; padding: 12px 30px;">Request New Link</a>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="message success" style="display: block; padding: 2rem; text-align: center;">
                <div style="font-size: 3rem; margin-bottom: 1rem;">✅</div>
                <h3 style="color: #fff; margin-bottom: 0.5rem;">All Set!</h3>
                <p style="color: rgba(255,255,255,0.8); line-height: 1.5;"><?php echo $success; ?></p>
                <div style="margin-top: 2rem;">
                    <a href="login.php" class="btn-submit" style="display: inline-block; text-decoration: none; padding: 12px 30px;">Log In Now</a>
                </div>
            </div>
        <?php elseif ($reset): ?>
            <form method="POST">
                <?php csrf_input(); ?>
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                
                <div class="form-group">
                    <label for="password">New Password</label>
                    <input type="password" id="password" name="password" placeholder="Create new password" required autofocus autocomplete="new-password">
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm New Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" placeholder="Repeat new password" required autocomplete="new-password">
                </div>

                <button type="submit" class="btn-submit" style="margin-top: 1rem;">Update Password</button>
            </form>
        <?php endif; ?>
    </div>
    <?php include 'includes/footer.php'; ?>
</body>
</html>
