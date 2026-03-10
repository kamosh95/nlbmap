<?php
require_once 'includes/security.php';
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

require_once 'includes/db_config.php';
require_once 'includes/get_nav.php';

$message = '';
$status = '';
$results = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_bulk'])) {
    verify_csrf_token($_POST['csrf_token'] ?? '');
    
    $source_dir = $_POST['source_dir'] ?? '';
    
    if (!is_dir($source_dir)) {
        $message = "Invalid directory path!";
        $status = 'error';
    } else {
        $files = scandir($source_dir);
        $processed = 0;
        $errors = 0;
        
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;
            
            $full_path = $source_dir . DIRECTORY_SEPARATOR . $file;
            if (is_dir($full_path)) continue;
            
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) continue;
            
            $code = pathinfo($file, PATHINFO_FILENAME); // File name without extension (e.g., A001)
            
            // Try matching with Dealer first
            $stmt = $pdo->prepare("SELECT id, dealer_code, name FROM dealers WHERE dealer_code = ?");
            $stmt->execute([$code]);
            $dealer = $stmt->fetch();
            
            if ($dealer) {
                $new_filename = 'dealer_' . preg_replace('/[^a-zA-Z0-9]/', '', $code) . '_' . uniqid() . '.' . $ext;
                $target = 'uploads/' . $new_filename;
                
                if (copy($full_path, $target)) {
                    $upd = $pdo->prepare("UPDATE dealers SET photo = ? WHERE id = ?");
                    $upd->execute([$target, $dealer['id']]);
                    $results[] = "ID: {$dealer['dealer_code']} (Dealer) - Image updated: $file";
                    $processed++;
                } else {
                    $errors++;
                }
                continue;
            }
            
            // Try matching with Agent
            $stmt = $pdo->prepare("SELECT id, agent_code, name FROM agents WHERE agent_code = ?");
            $stmt->execute([$code]);
            $agent = $stmt->fetch();
            
            if ($agent) {
                $new_filename = 'agent_' . preg_replace('/[^a-zA-Z0-9]/', '', $code) . '_' . uniqid() . '.' . $ext;
                $target = 'uploads/' . $new_filename;
                
                if (copy($full_path, $target)) {
                    $upd = $pdo->prepare("UPDATE agents SET photo = ? WHERE id = ?");
                    $upd->execute([$target, $agent['id']]);
                    $results[] = "ID: {$agent['agent_code']} (Agent) - Image updated: $file";
                    $processed++;
                } else {
                    $errors++;
                }
                continue;
            }
            
            $results[] = "<span style='color: #ffaa00;'>Skip: $file (Code '$code' not found in system)</span>";
        }
        
        if ($processed > 0) {
            $message = "Finished! Processed $processed images.";
            $status = 'success';
            log_activity($pdo, "Bulk Image Upload", "Processed $processed files from $source_dir", "system");
        } else {
            $message = "No matching records found for the files in the directory.";
            $status = 'error';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="dark-mode">
<head>
    <meta charset="UTF-8">
    <title>Bulk Image Upload - NLB</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .result-box {
            background: rgba(15, 23, 42, 0.4);
            border: 1px solid var(--glass-border);
            border-radius: 12px;
            padding: 1.5rem;
            margin-top: 1.5rem;
            max-height: 400px;
            overflow-y: auto;
            font-family: monospace;
            font-size: 0.9rem;
        }
        .result-item {
            padding: 4px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }
    </style>
</head>
<body>
    <div class="container wide">
        <div class="nav-bar" style="margin-bottom: 2rem;">
            <div class="nav-brand">
                <img src="assets/img/Logo.png" alt="NLB Logo">
                <div><h1>Bulk Image Upload</h1></div>
            </div>
            <?php echo render_nav($pdo, $_SESSION['role']); ?>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $status; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="form-panel">
            <h3 style="margin-bottom: 1rem;">Upload Agent/Dealer Images in Bulk</h3>
            <p style="font-size: 0.9rem; color: var(--text-muted); margin-bottom: 1.5rem;">
                Point to a directory on the server containing images named exactly as the <b>Agent Code</b> or <b>Dealer Code</b>.<br>
                Example: <code>A001.jpg</code> will be assigned to Dealer <code>A001</code>.
            </p>

            <form method="POST">
                <?php csrf_input(); ?>
                <div class="form-group">
                    <label>Source Directory Path (Absolute Path on Server)</label>
                    <input type="text" name="source_dir" placeholder="e.g. C:\Users\Downloads\Images" required style="width: 100%;">
                    <small style="color: var(--text-muted);">Ensure the web server has read access to this folder.</small>
                </div>

                <div style="margin-top: 1.5rem;">
                    <button type="submit" name="process_bulk" class="btn-submit">Start Processing</button>
                </div>
            </form>

            <?php if (!empty($results)): ?>
                <div class="result-box">
                    <h4 style="margin-bottom: 10px; color: var(--secondary-color);">Processing Logs:</h4>
                    <?php foreach ($results as $res): ?>
                        <div class="result-item"><?php echo $res; ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <?php include 'includes/footer.php'; ?>
</body>
</html>
