<?php
require_once 'includes/security.php';
if (!isset($_SESSION['role'])) {
    header("Location: login.php");
    exit;
}
require_once 'includes/db_config.php';
require_once 'includes/get_nav.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us - NLB</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .contact-wrapper {
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 1.5rem 1rem;
        }

        .contact-card {
            background: var(--card-bg);
            backdrop-filter: blur(25px);
            -webkit-backdrop-filter: blur(25px);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            padding: 1.5rem;
            width: 100%;
            max-width: 480px;
            box-shadow: 0 20px 40px -10px rgba(0,0,0,0.4);
            position: relative;
            overflow: hidden;
            animation: fadeInScale 0.5s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .contact-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
            background: var(--accent-gradient);
        }

        @keyframes fadeInScale {
            from { opacity: 0; transform: scale(0.97) translateY(8px); }
            to   { opacity: 1; transform: scale(1)    translateY(0); }
        }

        .contact-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 1.25rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--glass-border);
        }

        .contact-icon {
            width: 44px;
            height: 44px;
            background: rgba(0, 114, 255, 0.12);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            border: 1px solid var(--glass-border);
            flex-shrink: 0;
        }

        .contact-title { margin: 0; font-size: 1.1rem; font-weight: 700; color: var(--text-main); }
        .contact-sub   { margin: 2px 0 0; font-size: 0.75rem; color: var(--text-muted); font-weight: 400; }

        .info-row {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 0.65rem 0.85rem;
            border-radius: 12px;
            border: 1px solid var(--glass-border);
            background: rgba(255,255,255,0.02);
            margin-bottom: 0.5rem;
            transition: all 0.25s ease;
        }

        .info-row:last-child { margin-bottom: 0; }

        .info-row:hover {
            background: rgba(255,255,255,0.05);
            border-color: rgba(0,114,255,0.3);
            transform: translateX(4px);
        }

        .info-emoji {
            font-size: 1rem;
            width: 26px;
            text-align: center;
            flex-shrink: 0;
        }

        .info-body { flex: 1; min-width: 0; }

        .info-label {
            display: block;
            font-size: 0.6rem;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            color: var(--text-muted);
            font-weight: 600;
            line-height: 1;
            margin-bottom: 2px;
        }

        .info-value {
            font-size: 0.82rem;
            color: var(--text-main);
            font-weight: 500;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .info-value a {
            color: var(--secondary-color);
            text-decoration: none;
        }

        .contact-foot {
            margin-top: 1rem;
            padding-top: 0.75rem;
            border-top: 1px solid var(--glass-border);
            font-size: 0.7rem;
            color: var(--text-muted);
            text-align: center;
            opacity: 0.5;
        }
    </style>
</head>
<body>
    <div class="container wide">
        <div class="nav-bar" style="margin-bottom: 1.5rem;">
            <div class="nav-brand">
                <img src="assets/img/Logo.png" alt="NLB Logo">
                <div>
                    <h1>NLB Map Portal</h1>
                    <p style="color: var(--text-muted); font-size: 0.75rem; margin: 0; opacity: 0.8;">IT Helpdesk &nbsp;·&nbsp; NLB</p>
                </div>
            </div>
            <?php echo render_nav($pdo, $_SESSION['role']); ?>
        </div>

        <div class="contact-wrapper">
            <div class="contact-card">

                <div class="contact-header">
                    <div class="contact-icon">⚙️</div>
                    <div>
                        <h2 class="contact-title">IT Support</h2>
                        <p class="contact-sub">Information Technology Division · NLB</p>
                    </div>
                </div>

                <div class="info-row">
                    <span class="info-emoji">🏢</span>
                    <div class="info-body">
                        <span class="info-label">Organization</span>
                        <span class="info-value">National Lotteries Board</span>
                    </div>
                </div>

                <div class="info-row">
                    <span class="info-emoji">📞</span>
                    <div class="info-body">
                        <span class="info-label">Telephone / Ext</span>
                        <span class="info-value">(+94) 114 669 422 &nbsp;/&nbsp; Ext: 8322</span>
                    </div>
                </div>

                <div class="info-row">
                    <span class="info-emoji">📞</span>
                    <div class="info-body">
                        <span class="info-label">Telephone / Ext</span>
                        <span class="info-value">(+94) 114 669 425 &nbsp;/&nbsp; Ext: 8325</span>
                    </div>
                </div>

                <div class="info-row">
                    <span class="info-emoji">📧</span>
                    <div class="info-body">
                        <span class="info-label">Direct Email</span>
                        <span class="info-value"><a href="mailto:kamosh@nlb.lk">kamosh@nlb.lk</a></span>
                    </div>
                </div>

                <div class="contact-foot">
                    National Lotteries Board &copy; <?php echo date('Y'); ?>
                </div>
            </div>
        </div>
    </div>
    <?php include 'includes/footer.php'; ?>
</body>
</html>
