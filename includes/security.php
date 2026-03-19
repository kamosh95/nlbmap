<?php
/**
 * Security Helper Functions for NLB Seller Map Portal
 */

// Set common timezone
date_default_timezone_set('Asia/Colombo');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {

    // Detect HTTPS properly (works for reverse proxies, cPanel, Cloudflare too)
    $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
                || (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on')
                || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);

    // Use ini_set for broader hosting compatibility
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.cookie_secure', $is_https ? '1' : '0');
    ini_set('session.cookie_path', '/');
    ini_set('session.cookie_lifetime', '0');

    session_start();

    // Regenerate session ID on first load to prevent fixation attacks
    if (empty($_SESSION['_initiated'])) {
        session_regenerate_id(true);
        $_SESSION['_initiated'] = true;
    }
}

/**
 * Set Security Headers
 */
function set_security_headers() {
    header("X-Frame-Options: SAMEORIGIN");
    header("X-Content-Type-Options: nosniff");
    header("X-XSS-Protection: 1; mode=block");
    header("Referrer-Policy: strict-origin-when-cross-origin");
    // Updated CSP to allow Leaflet styles and icons
    header("Content-Security-Policy: default-src 'self'; script-src 'self' https://fonts.googleapis.com https://unpkg.com 'unsafe-inline'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://unpkg.com; font-src 'self' data: https://fonts.gstatic.com; img-src 'self' data: https:; connect-src 'self';");
}

/**
 * CSRF Protection Functions
 */
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token) {
    if (empty($token) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        // Regenerate a fresh token for next request
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        http_response_code(403);
        $back = isset($_SERVER['HTTP_REFERER']) ? htmlspecialchars($_SERVER['HTTP_REFERER']) : 'javascript:history.back()';
        die('
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Session Expired</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:Outfit,sans-serif;background:#0f172a;color:#e2e8f0;display:flex;align-items:center;justify-content:center;min-height:100vh;}
        .box{background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.1);border-radius:20px;padding:2.5rem 2rem;text-align:center;max-width:380px;width:90%;}
        .icon{font-size:3rem;margin-bottom:1rem;}
        h2{font-size:1.3rem;font-weight:700;margin-bottom:.5rem;color:#f8fafc;}
        p{font-size:.85rem;color:#94a3b8;margin-bottom:1.5rem;line-height:1.6;}
        a{display:inline-block;padding:.7rem 1.5rem;background:linear-gradient(135deg,#0072ff,#00d4ff);color:#fff;border-radius:12px;text-decoration:none;font-weight:600;font-size:.9rem;transition:.2s;}
        a:hover{opacity:.85;}
    </style>
</head>
<body>
    <div class="box">
        <div class="icon">⏱️</div>
        <h2>Session Expired</h2>
        <p>Your session has timed out or the form was submitted from an old page. Please go back and try again.</p>
        <a href="' . $back . '">← Go Back</a>
    </div>
</body>
</html>');
    }
    return true;
}

function csrf_input() {
    echo '<input type="hidden" name="csrf_token" value="' . generate_csrf_token() . '">';
}

/**
 * Enhanced Output Escaping
 */
function e($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * File Upload Security Check
 */
function is_allowed_file($filename, $tmp_path) {
    if (!$tmp_path || !file_exists($tmp_path)) return false;
    
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'heic', 'heif'];
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    
    // Fallback for files without extensions but appear to be valid images
    if (empty($ext)) {
        $info = @getimagesize($tmp_path);
        if ($info !== false) {
            $mime_map = [
                'image/jpeg' => 'jpg',
                'image/png'  => 'png',
                'image/gif'  => 'gif',
                'image/webp' => 'webp'
            ];
            $ext = $mime_map[$info['mime']] ?? '';
        }
    }

    if (!in_array($ext, $allowed_extensions)) {
        return false;
    }
    
    // Check MIME type
    $mime = '';
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $tmp_path);
        finfo_close($finfo);
    } 
    
    // Fallback to getimagesize for MIME if finfo failed or is missing
    if (empty($mime) || $mime === 'application/octet-stream') {
        $info = @getimagesize($tmp_path);
        if ($info !== false) {
            $mime = $info['mime'];
        }
    }
    
    if (empty($mime)) return false;

    $allowed_mimes = [
        'image/jpeg', 'image/png', 'image/webp', 'image/gif', 
        'image/pjpeg', 'image/x-png', 'image/jpg', 'image/jfif'
    ];
    
    // Special case for HEIC/HEIF which might report as various types
    if (in_array($ext, ['heic', 'heif'])) {
        $allowed_mimes[] = 'image/heic';
        $allowed_mimes[] = 'image/heif';
        $allowed_mimes[] = 'image/heic-sequence';
        $allowed_mimes[] = 'image/heif-sequence';
        $allowed_mimes[] = 'application/octet-stream'; // Often reported for HEIC if no finfo magic
    }

    return in_array($mime, $allowed_mimes);
}

/**
 * Rate Limiting (Simple)
 */
function check_rate_limit($action, $limit = 5, $window = 300) {
    $key = "rate_limit_" . $action;
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = ['count' => 0, 'start' => time()];
    }
    
    if (time() - $_SESSION[$key]['start'] > $window) {
        $_SESSION[$key] = ['count' => 1, 'start' => time()];
        return true;
    }
    
    $_SESSION[$key]['count']++;
    return $_SESSION[$key]['count'] <= $limit;
}

/**
 * Activity Logging
 */
function log_activity($pdo, $action, $details = null, $type = 'general') {
    if (!$pdo) return;
    $username = $_SESSION['username'] ?? 'guest';
    $role = $_SESSION['role'] ?? 'none';
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    try {
        $stmt = $pdo->prepare("INSERT INTO activity_log (username, role, action, details, entity_type, ip_address) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$username, $role, $action, $details, $type, $ip]);
    } catch (PDOException $e) {
        // Table may not exist yet or connection error
    }
}

/**
 * Image Compression/Resizing Helper
 */
function compress_image($source, $destination, $quality = 65, $max_width = 1200) {
    // Determine if it is an uploaded file or local file for fallback
    $is_uploaded = is_uploaded_file($source);
    
    $fallback = function($src, $dst) use ($is_uploaded) {
        return $is_uploaded ? move_uploaded_file($src, $dst) : copy($src, $dst);
    };

    if (!function_exists('imagecreatefromjpeg')) return $fallback($source, $destination);

    $info = getimagesize($source);
    if ($info === false) return $fallback($source, $destination);

    $mime = $info['mime'];
    switch ($mime) {
        case 'image/jpeg': $image = @imagecreatefromjpeg($source); break;
        case 'image/gif':  $image = @imagecreatefromgif($source); break;
        case 'image/png':  $image = @imagecreatefrompng($source); break;
        case 'image/webp': $image = @imagecreatefromwebp($source); break;
        default: return $fallback($source, $destination);
    }

    if (!$image) return $fallback($source, $destination);

    // Get original dimensions
    $width = imagesx($image);
    $height = imagesy($image);

    // Resize if necessary
    if ($width > $max_width) {
        $new_width = $max_width;
        $new_height = floor($height * ($max_width / $width));
        $tmp_img = imagecreatetruecolor($new_width, $new_height);
        
        // Preserve transparency for PNG/GIF/WEBP
        if ($mime != 'image/jpeg') {
            imagealphablending($tmp_img, false);
            imagesavealpha($tmp_img, true);
        }
        
        imagecopyresampled($tmp_img, $image, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
        imagedestroy($image);
        $image = $tmp_img;
    }

    $res = false;
    switch ($mime) {
        case 'image/png':
            // PNG compression is 0-9
            $res = imagepng($image, $destination, 7); 
            break;
        case 'image/webp':
            $res = imagewebp($image, $destination, $quality);
            break;
        case 'image/gif':
            $res = imagegif($image, $destination);
            break;
        default:
            $res = imagejpeg($image, $destination, $quality);
            break;
    }

    imagedestroy($image);
    return $res;
}

// Automatically set headers if this file is included
set_security_headers();
