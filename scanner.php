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
    <title>Advanced QR Scanner - NLB Portal</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://unpkg.com/html5-qrcode"></script>
    <style>
        :root {
            --scanner-glow: rgba(255, 204, 0, 0.4);
            --scanner-active: #ffcc00;
        }

        .scanner-wrapper {
            max-width: 800px;
            margin: 0 auto;
            position: relative;
            padding: 2rem 1rem;
        }

        /* Ambient Glow Backgrounds */
        .ambient-glow {
            position: fixed;
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, var(--scanner-glow) 0%, transparent 70%);
            border-radius: 50%;
            filter: blur(60px);
            z-index: -1;
            opacity: 0.3;
            pointer-events: none;
        }

        .scanner-glass-card {
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(25px);
            -webkit-backdrop-filter: blur(25px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 32px;
            padding: 3rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            animation: floatAnim 6s ease-in-out infinite;
        }

        @keyframes floatAnim {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }

        .header-section {
            margin-bottom: 2.5rem;
            text-align: center;
        }

        .header-section h2 {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 0.75rem;
            background: linear-gradient(135deg, #fff 0%, #ffcc00 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .header-section p {
            color: var(--text-muted);
            font-size: 1.1rem;
            max-width: 450px;
            margin: 0 auto;
        }

        /* Specialized Scanner Area Styling */
        #reader {
            width: 100%;
            border-radius: 20px;
            overflow: hidden;
            border: 2px solid rgba(255, 255, 255, 0.05);
            background: #000;
            position: relative;
            box-shadow: 0 0 0 10px rgba(255, 255, 255, 0.02);
        }

        /* HTML5 QR Code Internal UI Fixes */
        #reader__status_span { display: none !important; }
        #reader__header_message { display: none !important; }
        
        #reader__dashboard_section_csr button {
            background: var(--gold-gradient) !important;
            color: #000 !important;
            border: none !important;
            padding: 12px 24px !important;
            border-radius: 14px !important;
            font-weight: 700 !important;
            cursor: pointer !important;
            margin: 8px !important;
            font-family: 'Outfit', sans-serif !important;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-size: 0.8rem !important;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
            box-shadow: 0 4px 15px rgba(255, 204, 0, 0.2) !important;
        }

        #reader__dashboard_section_csr button:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 8px 25px rgba(255, 204, 0, 0.4) !important;
        }

        #reader__camera_selection {
            background: rgba(30, 41, 59, 0.8) !important;
            color: #fff !important;
            border: 1px solid rgba(255, 255, 255, 0.1) !important;
            padding: 12px !important;
            border-radius: 12px !important;
            width: 80% !important;
            margin-bottom: 20px !important;
            outline: none !important;
        }

        /* Technical HUD Overlays */
        .scanner-hud {
            position: absolute;
            top: 20px;
            left: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            z-index: 10;
        }

        .status-dot {
            width: 8px;
            height: 8px;
            background: #22c55e;
            border-radius: 50%;
            box-shadow: 0 0 10px #22c55e;
            animation: pulseFade 1.5s infinite;
        }

        @keyframes pulseFade {
            0%, 100% { opacity: 0.4; }
            50% { opacity: 1; }
        }

        /* Success Display */
        .scan-success-overlay {
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(15, 23, 42, 0.95);
            z-index: 2000;
            display: none;
            backdrop-filter: blur(15px);
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 2rem;
            animation: fadeIn 0.4s ease-out;
        }

        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

        .success-box {
            max-width: 400px;
            padding: 3rem;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 40px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.5);
        }

        .check-icon {
            font-size: 5rem;
            margin-bottom: 1.5rem;
            display: inline-block;
            background: linear-gradient(135deg, #22c55e, #4ade80);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            filter: drop-shadow(0 10px 15px rgba(34, 197, 94, 0.3));
        }

        .loader-ring {
            width: 50px;
            height: 50px;
            border: 3px solid rgba(255, 255, 255, 0.1);
            border-top: 3px solid var(--secondary-color);
            border-radius: 50%;
            margin: 2rem auto 0;
            animation: spin 1s linear infinite;
        }

        @keyframes spin { 100% { transform: rotate(360deg); } }

        .btn-cancel {
            margin-top: 2rem;
            display: inline-block;
            color: var(--text-muted);
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9rem;
            border-bottom: 1px solid transparent;
            transition: 0.3s;
        }
        .btn-cancel:hover { color: #fff; border-color: #fff; }

    </style>
</head>
<body>
<div class="ambient-glow" style="top: -100px; right: -100px;"></div>
<div class="ambient-glow" style="bottom: -100px; left: -100px; background: radial-gradient(circle, rgba(0, 114, 255, 0.3) 0%, transparent 70%);"></div>

<div class="container wide">
    <!-- Page Header -->
    <div class="nav-bar">
        <div class="nav-brand">
            <div style="display: flex; align-items: center; gap: 1.25rem;">
                <img src="assets/img/Logo.png" alt="NLB Logo">
                <div>
                    <h1>NLB Seller Map Portal</h1>
                    <p>Logged in as <span class="role-badge badge-<?php echo $_SESSION['role']; ?>"><?php echo $_SESSION['username']; ?></span></p>
                </div>
            </div>
        </div>
        <?php echo render_nav($pdo, $_SESSION['role']); ?>
    </div>

    <div class="scanner-wrapper">
        <div class="scanner-glass-card">
            <div class="header-section">
                <h2>📷 Scanner</h2>
                <p>Position the seller's QR code within the scanning area to automatically open the record.</p>
            </div>

            <div style="position: relative;">
                <div class="scanner-hud">
                    <div class="status-dot"></div>
                    <span style="font-size: 0.7rem; color: #22c55e; font-weight: 700; text-transform: uppercase; letter-spacing: 1px;">Live Vision System</span>
                </div>
                <div id="reader"></div>
            </div>

            <div style="text-align: center; margin-top: 1.5rem;">
                <p style="font-size: 0.8rem; color: var(--text-muted);">Ensure valid lighting for faster detection</p>
            </div>
        </div>
    </div>
</div>

<!-- Success Redirect Overlay -->
<div id="successOverlay" class="scan-success-overlay">
    <div class="success-box">
        <div class="check-icon">✓</div>
        <h3 style="color: #fff; font-size: 1.5rem; margin-bottom: 0.5rem;">Code Identified</h3>
        <p id="targetUrl" style="color: var(--text-muted); font-size: 0.9rem;">Redirecting to record...</p>
        <div class="loader-ring"></div>
        <a href="scanner.php" class="btn-cancel">Cancel Redirect</a>
    </div>
</div>

<script>
    let isProcessing = false;

    function onScanSuccess(decodedText, decodedResult) {
        if (isProcessing) return;
        isProcessing = true;
        
        console.log(`Code matched = ${decodedText}`);
        
        const overlay = document.getElementById('successOverlay');
        const targetText = document.getElementById('targetUrl');
        
        overlay.style.display = 'flex';
        
        // Check if it's our internal link
        if (decodedText.includes('edit_record.php') || decodedText.includes('view_public.php')) {
            targetText.innerText = "Accessing seller secure record...";
            
            // Short delay for visual confirmation
            setTimeout(() => {
                window.location.href = decodedText;
            }, 1200);
        } else {
            targetText.innerText = "External link detected. Opening...";
            setTimeout(() => {
                window.location.href = decodedText;
            }, 2000);
        }
        
        // Stop scanning
        html5QrcodeScanner.clear();
    }

    let html5QrcodeScanner = new Html5QrcodeScanner(
        "reader", 
        { 
            fps: 15, 
            qrbox: {width: 280, height: 280},
            rememberLastUsedCamera: true,
            aspectRatio: 1.0
        },
        /* verbose= */ false);
    
    html5QrcodeScanner.render(onScanSuccess);

    // Style cleanup after library initialization
    setTimeout(() => {
        const video = document.querySelector('#reader video');
        if (video) video.style.borderRadius = "20px";
    }, 1000);
</script>
    <?php include 'includes/footer.php'; ?>
</body>
</html>
