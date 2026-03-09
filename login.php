<?php
require_once 'includes/security.php';
if (!file_exists('includes/db_config.php')) {
    header("Location: installer.php");
    exit;
}
require_once 'includes/db_config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF
    verify_csrf_token($_POST['csrf_token'] ?? '');

    // Rate Limit Login Attempts
    if (!check_rate_limit('login', 5, 300)) {
        $error = "Too many login attempts. Please try again in 5 minutes.";
    } else {
        $username = $_POST['username'];
        $password = $_POST['password'];

        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            // Check status
            if ($user['status'] === 'pending') {
                $error = "⌛ Your account is pending approval. Please contact the administrator.";
            } elseif ($user['status'] === 'suspended') {
                $error = "🚫 Your account has been suspended. Please contact the administrator.";
            } else {
                // Regenerate session ID to prevent session fixation
                session_regenerate_id(true);

                // Reset CSRF token after regeneration
                $_SESSION['csrf_token']  = bin2hex(random_bytes(32));
                $_SESSION['_initiated']  = true;

                $_SESSION['user_id']  = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role']     = $user['role'];
                
                log_activity($pdo, "User Login", "User logged into the system", "login");
                
                // Check for potential redirect after login (from QR code scan)
                $redirect_url = $_GET['redirect'] ?? '';
                if (!empty($redirect_url) && strpos($redirect_url, '.php') !== false) {
                    header("Location: " . $redirect_url);
                    exit;
                }

                if ($user['role'] === 'tm') {
                    header("Location: index.php");
                } else {
                    header("Location: dashboard.php");
                }
                exit;
            }
        } else {
            $error = "Invalid username or password.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NLB Seller Map Portal - Login</title>
    <link rel="manifest" href="assets/manifest.json">
    <meta name="theme-color" content="#0072ff">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="assets/img/logo1.png">
    <link rel="stylesheet" href="assets/css/style.css">
    <script>
        (function() {
            const savedTheme = localStorage.getItem("theme");
            if (savedTheme === "dark") {
                document.documentElement.classList.add("dark-mode");
            }
        })();

        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('sw.js');
            });
        }
    </script>
</head>
<body>
    <div class="container auth-card" style="max-width: 400px; margin-top: 5rem;">
        <div style="text-align: center; margin-bottom: 2rem;">
            <img src="assets/img/Logo.png" alt="NLB Logo" style="max-width: 120px; height: auto; filter: drop-shadow(0 0 15px rgba(255, 255, 255, 0.2));">
            <h1 style="margin-top: 1.5rem; font-size: 2rem; font-weight: 800;">NLB Portal</h1>
            <p class="subtitle">Secure Seller Management System</p>
        </div>

        <?php if ($error): ?>
            <div class="message error" style="display: block;"><?php echo $error; ?></div>
        <?php endif; ?>

        <form action="<?php echo htmlspecialchars($_SERVER['REQUEST_URI']); ?>" method="POST">
            <?php csrf_input(); ?>
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" placeholder="Your username" required autocomplete="username">
            </div>

            <div class="form-group">
                <label for="password" style="margin-bottom: 0.5rem; display: block;">Password</label>
                <input type="password" id="password" name="password" placeholder="Your password" required autocomplete="current-password">
            </div>

            <button type="submit" class="btn-submit" style="margin-top: 1rem;">Sign In</button>
            
            <div style="text-align: right; margin-top: 0.8rem;">
                <a href="forgot_password.php" style="color: var(--text-muted); text-decoration: none; font-size: 0.82rem; font-weight: 500; transition: 0.3s; opacity: 0.8;" onmouseover="this.style.color='var(--secondary-color)'; this.style.opacity='1'" onmouseout="this.style.color='var(--text-muted)'; this.style.opacity='0.8'">Forgot Password?</a>
            </div>
            
            <div style="text-align: center; margin-top: 2rem; border-top: 1px solid rgba(255, 255, 255, 0.1); padding-top: 1.5rem;">
                <p style="color: var(--text-muted); font-size: 0.9rem;">
                    Don't have an account? 
                    <a href="signup.php" style="color: var(--secondary-color); text-decoration: none; font-weight: 700; border-bottom: 2px solid transparent; transition: 0.3s;" onmouseover="this.style.borderColor='var(--secondary-color)'" onmouseout="this.style.borderColor='transparent'">
                        Create One Now
                    </a>
                </p>
            </div>
        </form>
    </div>
    <?php include 'includes/footer.php'; ?>
</body>
</html>
