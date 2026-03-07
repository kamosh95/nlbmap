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
    
    <!-- We will use the core Html5Qrcode library which has the best decoding capability -->
    <script src="https://unpkg.com/html5-qrcode"></script>
    
    <style>
        :root {
            --scanner-glow: rgba(0, 114, 255, 0.4);
        }

        .scanner-wrapper {
            max-width: 600px;
            margin: 0 auto;
            position: relative;
            padding: 1rem;
        }

        .scanner-glass-card {
            background: rgba(15, 23, 42, 0.8);
            backdrop-filter: blur(25px);
            -webkit-backdrop-filter: blur(25px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 32px;
            padding: 2.5rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            text-align: center;
        }

        .header-section {
            margin-bottom: 2rem;
        }

        .header-section h2 {
            font-size: 2.2rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
            background: linear-gradient(135deg, #fff 0%, #00d4ff 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .header-section p {
            color: var(--text-muted);
            font-size: 1rem;
            max-width: 450px;
            margin: 0 auto;
        }
        
        /* Reader Container */
        #reader {
            width: 100%;
            border-radius: 24px;
            overflow: hidden;
            background: #000;
            border: 2px solid rgba(255, 255, 255, 0.05);
            margin: 0 auto 1.5rem auto;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
            display: none; /* hidden until started */
        }
        
        #reader video {
            object-fit: cover;
            border-radius: 20px;
        }

        .fallback-container {
            display: none; /* hidden by default */
            flex-direction: column;
            gap: 1.5rem;
            padding: 1rem 0;
        }

        .scan-action-btn {
            display: inline-flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, rgba(0, 114, 255, 0.1) 0%, rgba(0, 212, 255, 0.1) 100%);
            color: #00d4ff;
            border: 2px dashed rgba(0, 212, 255, 0.4);
            padding: 2.5rem 2rem;
            border-radius: 20px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            width: 100%;
            position: relative;
        }

        .scan-action-btn:hover {
            background: linear-gradient(135deg, rgba(0, 114, 255, 0.2) 0%, rgba(0, 212, 255, 0.2) 100%);
            transform: translateY(-2px);
        }

        .custom-file-input {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
            z-index: 10;
        }

        .alert-box {
            background: rgba(245, 158, 11, 0.1);
            border: 1px solid rgba(245, 158, 11, 0.3);
            color: #fbbf24;
            padding: 1rem;
            border-radius: 12px;
            font-size: 0.9rem;
            text-align: left;
            display: flex;
            align-items: flex-start;
            gap: 10px;
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
            width: 100%;
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
            border-top: 3px solid #00d4ff;
            border-radius: 50%;
            margin: 2rem auto 0;
            animation: spin 1s linear infinite;
        }

        @keyframes spin { 100% { transform: rotate(360deg); } }
        
        .mode-switch-btn {
            background: transparent;
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: rgba(255, 255, 255, 0.7);
            padding: 0.75rem 1.5rem;
            border-radius: 50px;
            font-size: 0.9rem;
            cursor: pointer;
            margin-top: 1rem;
            transition: all 0.3s;
            font-weight: 600;
        }
        .mode-switch-btn:hover {
            color: #fff;
            border-color: rgba(255,255,255,0.4);
            background: rgba(255,255,255,0.05);
        }
    </style>
</head>
<body class="dark-mode"> <!-- Force dark mode style for scanner if needed, or rely on script -->
<div class="ambient-glow" style="top: -100px; right: -100px;"></div>
<div class="ambient-glow" style="bottom: -100px; left: -100px; background: radial-gradient(circle, rgba(0, 114, 255, 0.3) 0%, transparent 70%);"></div>

<div class="container wide">
    <!-- Page Header -->
    <div class="nav-bar">
        <div class="nav-brand">
            <div style="display: flex; align-items: center; gap: 1.25rem;">
                <img src="assets/img/Logo.png" alt="NLB Logo">
                <div>
                    <h1>NLB Seller Map</h1>
                    <p>Logged in as <span class="role-badge badge-<?php echo $_SESSION['role']; ?>"><?php echo $_SESSION['username']; ?></span></p>
                </div>
            </div>
        </div>
        <?php echo render_nav($pdo, $_SESSION['role']); ?>
    </div>

    <div class="scanner-wrapper">
        <div class="scanner-glass-card">
            <div class="header-section">
                <h2>📷 QR Scanner</h2>
                <p>Scan seller QR codes to instantly access their secure records.</p>
            </div>
            
            <!-- ALERTS -->
            <div id="http-alert" class="alert-box" style="display:none; margin-bottom: 1.5rem;">
                <span style="font-size: 1.5rem;">⚠️</span>
                <div>
                    <strong>Live Camera Blocked by Browser</strong><br>
                    Your browser blocks live cameras on connections without an SSL certificate. Please use the image upload option below.
                </div>
            </div>

            <!-- LIVE READER CONTAINER -->
            <div id="reader"></div>
            
            <button id="switch-to-upload" class="mode-switch-btn" style="display:none;">Or upload an image instead</button>

            <!-- FALLBACK UPLOAD CONTAINER -->
            <div id="fallback-container" class="fallback-container">
                <div class="scan-action-btn">
                    <div style="font-size: 3rem; margin-bottom: 0.5rem; color:#00d4ff;">📸</div>
                    <span style="font-size: 1.1rem; color: #fff;">Take Photo / Upload Image</span>
                    <input type="file" accept="image/*" capture="environment" id="qr-input-file" class="custom-file-input">
                </div>
                
                <button id="switch-to-live" class="mode-switch-btn" style="display:none; margin: 0 auto;">Try Live Camera Again</button>
            </div>
            
            <div id="status-message" style="margin-top: 1.5rem; color: #ef4444; font-weight: 500; font-size: 0.9rem; display:none;"></div>
        </div>
    </div>
</div>

<!-- Success Redirect Overlay -->
<div id="successOverlay" class="scan-success-overlay">
    <div class="success-box">
        <div class="check-icon" id="successIcon">✓</div>
        <h3 id="successTitle" style="color: #fff; font-size: 1.5rem; margin-bottom: 0.5rem;">Code Identified</h3>
        <p id="targetUrl" style="color: var(--text-muted); font-size: 0.9rem;">Redirecting to record...</p>
        <div class="loader-ring" id="loaderRing"></div>
        <button id="cancelBtn" class="btn-cancel" style="display: none; background: transparent; border: none; cursor: pointer;">Try Again</button>
    </div>
</div>

<script>
    const readerEl = document.getElementById('reader');
    const fallbackContainer = document.getElementById('fallback-container');
    const httpAlert = document.getElementById('http-alert');
    const statusMessage = document.getElementById('status-message');
    const fileInput = document.getElementById('qr-input-file');
    
    const btnSwitchToUpload = document.getElementById('switch-to-upload');
    const btnSwitchToLive = document.getElementById('switch-to-live');

    let html5QrCode;
    let isProcessing = false;
    
    function showMessage(msg, isError = true) {
        statusMessage.style.display = 'block';
        statusMessage.style.color = isError ? '#ef4444' : '#22c55e';
        statusMessage.innerText = msg;
    }

    function onScanSuccess(decodedText, decodedResult) {
        if (isProcessing) return;
        isProcessing = true;
        
        // Stop the live scanner if it's running
        if (html5QrCode && html5QrCode.isScanning) {
            html5QrCode.stop().catch(err => console.log(err));
        }
        
        console.log("QR Code matched:", decodedText);
        
        const overlay = document.getElementById('successOverlay');
        const targetText = document.getElementById('targetUrl');
        const title = document.getElementById('successTitle');
        const icon = document.getElementById('successIcon');
        const loader = document.getElementById('loaderRing');
        const cancelBtn = document.getElementById('cancelBtn');
        
        overlay.style.display = 'flex';
        title.innerText = "Code Identified";
        title.style.color = "#fff";
        icon.style.display = "inline-block";
        icon.innerText = "✓";
        loader.style.display = "block";
        cancelBtn.style.display = "none";
        
        if (decodedText.includes('edit_record.php') || decodedText.includes('view_public.php')) {
            targetText.innerText = "Accessing internal secure record...";
            setTimeout(() => { window.location.href = decodedText; }, 1000);
        } else {
            targetText.innerText = "External link detected. Opening...";
            setTimeout(() => { window.location.href = decodedText; }, 1500);
        }
    }

    function startLiveScanner() {
        readerEl.style.display = 'block';
        fallbackContainer.style.display = 'none';
        btnSwitchToUpload.style.display = 'inline-block';
        statusMessage.style.display = 'none';
        httpAlert.style.display = 'none';

        if (!html5QrCode) {
            html5QrCode = new Html5Qrcode("reader");
        }

        html5QrCode.start(
            { facingMode: "environment" },
            {
                fps: 10,
                qrbox: function(viewfinderWidth, viewfinderHeight) {
                    let minEdgeSize = Math.min(viewfinderWidth, viewfinderHeight);
                    let qrboxSize = Math.floor(minEdgeSize * 0.7);
                    return { width: qrboxSize, height: qrboxSize };
                },
                aspectRatio: 1.0
            },
            onScanSuccess,
            (errorMessage) => {
                // background scanning errors (happens constantly until a QR is found, ignore these)
            }
        ).catch((err) => {
            // Failed to start camera!
            console.error("Camera start failed:", err);
            readerEl.style.display = 'none';
            btnSwitchToUpload.style.display = 'none';
            fallbackContainer.style.display = 'flex';
            btnSwitchToLive.style.display = 'block';
            
            // Check if it's an insecure context error
            if (window.isSecureContext === false) {
                httpAlert.style.display = 'flex';
            } else {
                showMessage("Could not access live camera. Please allow camera permissions or simply use the upload option.");
            }
        });
    }

    // Initialize
    document.addEventListener("DOMContentLoaded", () => {
        // If they don't have mediaDevices, they can strictly only do file uploads
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            readerEl.style.display = 'none';
            fallbackContainer.style.display = 'flex';
            if (window.isSecureContext === false) {
                httpAlert.style.display = 'flex';
            }
        } else {
            // Try to start the live scanner automatically
            startLiveScanner();
        }
    });

    btnSwitchToUpload.addEventListener('click', () => {
        if (html5QrCode && html5QrCode.isScanning) {
            html5QrCode.stop().then(() => {
                readerEl.style.display = 'none';
                btnSwitchToUpload.style.display = 'none';
                fallbackContainer.style.display = 'flex';
                btnSwitchToLive.style.display = 'block';
            });
        } else {
            readerEl.style.display = 'none';
            btnSwitchToUpload.style.display = 'none';
            fallbackContainer.style.display = 'flex';
            btnSwitchToLive.style.display = 'block';
        }
    });

    btnSwitchToLive.addEventListener('click', () => {
        startLiveScanner();
    });

    // Handle File Upload Fallback
    fileInput.addEventListener('change', (e) => {
        if (e.target.files.length == 0) return;
        
        showMessage("Analyzing image...", false);
        
        const overlay = document.getElementById('successOverlay');
        const title = document.getElementById('successTitle');
        const icon = document.getElementById('successIcon');
        const targetText = document.getElementById('targetUrl');
        const loader = document.getElementById('loaderRing');
        const cancelBtn = document.getElementById('cancelBtn');

        overlay.style.display = 'flex';
        title.innerText = "Analyzing Image...";
        title.style.color = "#00d4ff";
        icon.style.display = "none";
        targetText.innerText = "Searching for a valid QR code in the picture.";
        loader.style.display = "block";
        cancelBtn.style.display = "none";
        
        if (!html5QrCode) {
            html5QrCode = new Html5Qrcode("reader");
        }

        const imageFile = e.target.files[0];
        
        // Scan the file using the robust html5-qrcode decoder
        html5QrCode.scanFileV2(imageFile, false)
            .then(decodedResult => {
                onScanSuccess(decodedResult.decodedText, null);
            })
            .catch(err => {
                // Failed to decode image
                title.innerText = "No QR Code Found";
                title.style.color = "#ef4444";
                icon.style.display = "inline-block";
                icon.innerText = "❌";
                loader.style.display = "none";
                targetText.innerText = "The image might be blurry or the QR code is not visible.";
                cancelBtn.style.display = "inline-block";
                
                showMessage(`No valid QR code found in this image. Please try again.`, true);
                fileInput.value = ""; // Reset input so they can select the same file again
            });
    });

    document.getElementById('cancelBtn').addEventListener('click', () => {
        document.getElementById('successOverlay').style.display = 'none';
    });
</script>
    <?php include 'includes/footer.php'; ?>
</body>
</html>
