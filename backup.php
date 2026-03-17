<?php
require_once 'includes/security.php';
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}
require_once 'includes/db_config.php';
require_once 'includes/get_nav.php';

$message = '';
$status  = '';

// ── Database Dump via PDO ────────────────────────────────────────────────────
function generate_sql_dump(PDO $pdo, string $dbname): string {
    $sql  = "-- NLB Seller Map - Database Backup\n";
    $sql .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
    $sql .= "-- Database: $dbname\n\n";
    $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

    foreach ($tables as $table) {
        // Drop + Create
        $create = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_ASSOC);
        $sql .= "-- Table: $table\n";
        $sql .= "DROP TABLE IF EXISTS `$table`;\n";
        $sql .= $create['Create Table'] . ";\n\n";

        // Rows
        $rows = $pdo->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
        if (!$rows) { continue; }

        $cols = '`' . implode('`, `', array_keys($rows[0])) . '`';
        $sql .= "INSERT INTO `$table` ($cols) VALUES\n";
        $chunks = [];
        foreach ($rows as $row) {
            $vals = array_map(function($v) use ($pdo) {
                return $v === null ? 'NULL' : $pdo->quote($v);
            }, array_values($row));
            $chunks[] = '(' . implode(', ', $vals) . ')';
        }
        $sql .= implode(",\n", $chunks) . ";\n\n";
    }

    $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";
    return $sql;
}

// ── ZIP Builder ───────────────────────────────────────────────────────────────
function add_dir_to_zip(ZipArchive $zip, string $dir, string $base): void {
    $skip = ['vendor', '.git', 'backup_tmp'];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)) as $file) {
        if ($file->isDir()) continue;
        $real = $file->getRealPath();
        // Skip vendor and git directories
        foreach ($skip as $s) {
            if (strpos($real, DIRECTORY_SEPARATOR . $s . DIRECTORY_SEPARATOR) !== false) continue 2;
        }
        $rel = ltrim(str_replace($base, '', $real), DIRECTORY_SEPARATOR);
        $zip->addFile($real, str_replace('\\', '/', $rel));
    }
}

// ── Handle Download ───────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['do_backup'])) {
    verify_csrf_token($_POST['csrf_token'] ?? '');

    if (!class_exists('ZipArchive')) {
        $message = "❌ PHP ZipArchive extension is not enabled on this server.";
        $status  = 'error';
    } else {
        $timestamp  = date('Ymd_His');
        $tmp_dir    = sys_get_temp_dir();
        $zip_path   = $tmp_dir . DIRECTORY_SEPARATOR . "SellerMap_backup_$timestamp.zip";
        $sql_path   = $tmp_dir . DIRECTORY_SEPARATOR . "seller_map_$timestamp.sql";
        $site_root  = realpath(__DIR__);

        // 1. Write SQL dump
        $dbname = 'seller_map';
        try {
            file_put_contents($sql_path, generate_sql_dump($pdo, $dbname));
        } catch (Exception $e) {
            $message = "❌ DB dump failed: " . $e->getMessage();
            $status  = 'error';
        }

        if (!$message) {
            // 2. Build ZIP
            $zip = new ZipArchive();
            if ($zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
                // Add SQL dump
                $zip->addFile($sql_path, "database/seller_map_$timestamp.sql");
                // Add all site files
                add_dir_to_zip($zip, $site_root, $site_root);
                $zip->close();
                @unlink($sql_path);

                // 3. Stream download
                header('Content-Type: application/zip');
                header('Content-Disposition: attachment; filename="SellerMap_backup_' . $timestamp . '.zip"');
                header('Content-Length: ' . filesize($zip_path));
                header('Cache-Control: no-cache');
                readfile($zip_path);
                @unlink($zip_path);
                log_activity($pdo, "System Backup", "Full backup downloaded by admin", "admin");
                exit;
            } else {
                $message = "❌ Failed to create ZIP archive.";
                $status  = 'error';
            }
        }
    }
}

// ── Estimate sizes ────────────────────────────────────────────────────────────
function dir_size(string $dir): int {
    $size = 0;
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)) as $f) {
        $size += $f->getSize();
    }
    return $size;
}
function fmt_bytes(int $bytes): string {
    if ($bytes >= 1073741824) return round($bytes / 1073741824, 2) . ' GB';
    if ($bytes >= 1048576)    return round($bytes / 1048576,    2) . ' MB';
    return round($bytes / 1024, 1) . ' KB';
}

$uploads_size = is_dir(__DIR__ . '/uploads') ? dir_size(__DIR__ . '/uploads') : 0;

$table_count  = $pdo->query("SHOW TABLES")->rowCount();
$counter_count = $pdo->query("SELECT COUNT(*) FROM counters")->fetchColumn();

// Last backup from activity log
$last_backup = null;
try {
    $lb = $pdo->query("SELECT created_at FROM activity_log WHERE action='System Backup' ORDER BY created_at DESC LIMIT 1")->fetch();
    $last_backup = $lb ? $lb['created_at'] : null;
} catch(Exception $e){}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Backup - NLB Seller Map</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="assets/img/logo1.png">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .backup-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        @media(max-width:700px){ .backup-grid { grid-template-columns: 1fr; } }

        .bk-card {
            background: var(--card-bg);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            padding: 1.75rem;
        }
        .bk-card h3 {
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--text-muted);
            margin-bottom: 1rem;
        }
        .bk-stat {
            font-size: 2rem;
            font-weight: 800;
            background: var(--gold-gradient);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .bk-label {
            font-size: 0.8rem;
            color: var(--text-muted);
            margin-top: 4px;
        }
        .backup-hero {
            background: linear-gradient(135deg, rgba(0,114,255,0.08), rgba(0,212,255,0.08));
            border: 1px solid rgba(0,212,255,0.2);
            border-radius: 24px;
            padding: 3rem 2rem;
            text-align: center;
            margin-bottom: 2rem;
        }
        .backup-hero h2 {
            font-size: 1.5rem;
            color: var(--secondary-color);
            margin-bottom: 0.5rem;
        }
        .backup-hero p {
            color: var(--text-muted);
            font-size: 0.9rem;
            margin-bottom: 2rem;
        }
        .btn-backup {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            padding: 1rem 2.5rem;
            background: linear-gradient(135deg, #0072ff, #00d4ff);
            color: #000;
            font-weight: 800;
            font-size: 1.1rem;
            border: none;
            border-radius: 16px;
            cursor: pointer;
            font-family: 'Outfit', sans-serif;
            transition: 0.3s;
            box-shadow: 0 10px 30px rgba(0,114,255,0.3);
        }
        .btn-backup:hover { transform: translateY(-3px); box-shadow: 0 15px 40px rgba(0,114,255,0.4); }
        .btn-backup:active { transform: translateY(0); }
        .btn-backup:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }

        .info-list { list-style: none; }
        .info-list li {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 0;
            border-bottom: 1px solid rgba(255,255,255,0.04);
            font-size: 0.88rem;
            color: var(--text-muted);
        }
        .info-list li:last-child { border-bottom: none; }
        .info-list li span.tick { color: #4ade80; }
        .info-list li span.key  { color: var(--text-main); font-weight: 600; margin-left: auto; }

        .progress-bar-wrap {
            display: none;
            margin-top: 1.5rem;
            background: rgba(255,255,255,0.05);
            border-radius: 100px;
            overflow: hidden;
            height: 8px;
        }
        .progress-bar {
            height: 100%;
            background: linear-gradient(90deg, #0072ff, #00d4ff);
            border-radius: 100px;
            animation: progressAnim 3s ease-in-out infinite;
        }
        @keyframes progressAnim {
            0%   { width: 5%; }
            50%  { width: 80%; }
            100% { width: 95%; }
        }
    </style>
</head>
<body>
<div class="container wide">

    <!-- Header -->
    <div class="nav-bar" style="margin-bottom: 2rem;">
        <div class="nav-brand">
            <img src="assets/img/Logo.png" alt="NLB Logo">
            <div>
                <h1>NLB Seller Map Portal</h1>
                <p style="color:var(--text-muted);font-size:0.75rem;margin:0;opacity:0.8;">
                    System Backup &nbsp;·&nbsp;
                    <span class="role-badge badge-admin" style="padding:2px 8px;font-size:0.65rem;"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                </p>
            </div>
        </div>
        <?php echo render_nav($pdo, $_SESSION['role']); ?>
    </div>

    <?php if ($message): ?>
        <div class="message <?php echo $status; ?>" style="display:block;margin-bottom:1.5rem;"><?php echo $message; ?></div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="backup-grid">
        <div class="bk-card">
            <h3>📊 Database</h3>
            <div class="bk-stat"><?php echo number_format($counter_count); ?></div>
            <div class="bk-label">Counter records across <?php echo $table_count; ?> tables</div>
        </div>
        <div class="bk-card">
            <h3>🖼️ Uploads Folder</h3>
            <div class="bk-stat"><?php echo fmt_bytes($uploads_size); ?></div>
            <div class="bk-label">Images stored on server</div>
        </div>
    </div>

    <!-- Main Backup Panel -->
    <div class="backup-hero">
        <div style="font-size:3.5rem;margin-bottom:1rem;filter:drop-shadow(0 0 20px rgba(0,212,255,0.4));">💾</div>
        <h2>Full Site Backup</h2>
        <p>
            Downloads a single <strong>.zip</strong> file containing all PHP files, uploaded images,
            and a complete <strong>SQL dump</strong> of the database.
        </p>

        <form method="POST" id="backupForm" onsubmit="startProgress()">
            <?php csrf_input(); ?>
            <input type="hidden" name="do_backup" value="1">
            <button type="submit" class="btn-backup" id="backupBtn">
                <span>⬇️</span> Download Full Backup
            </button>
        </form>

        <div class="progress-bar-wrap" id="progressWrap">
            <div class="progress-bar"></div>
        </div>
        <p id="progressLabel" style="display:none;color:var(--text-muted);font-size:0.82rem;margin-top:1rem;">
            ⏳ Generating backup... Please wait, do not close this page.
        </p>
    </div>

    <!-- What's included -->
    <div class="bk-card">
        <h3>📦 Backup Includes</h3>
        <ul class="info-list">
            <li><span class="tick">✅</span> All PHP source files <span class="key">*.php</span></li>
            <li><span class="tick">✅</span> CSS, JS, assets <span class="key">assets/</span></li>
            <li><span class="tick">✅</span> Uploaded images <span class="key">uploads/</span></li>
            <li><span class="tick">✅</span> Location data JSON <span class="key">data/</span></li>
            <li><span class="tick">✅</span> Full database SQL dump <span class="key">database/*.sql</span></li>
            <li><span class="tick">❌</span> vendor/ folder excluded <span class="key">(re-run composer install)</span></li>
            <li><span class="tick">❌</span> .git/ folder excluded</li>
        </ul>

        <?php if ($last_backup): ?>
        <div style="margin-top:1.25rem;padding:0.75rem 1rem;background:rgba(74,222,128,0.08);border:1px solid rgba(74,222,128,0.2);border-radius:10px;font-size:0.82rem;color:#4ade80;">
            🕓 Last backup: <strong><?php echo date('Y-m-d H:i', strtotime($last_backup)); ?></strong>
        </div>
        <?php else: ?>
        <div style="margin-top:1.25rem;padding:0.75rem 1rem;background:rgba(251,191,36,0.08);border:1px solid rgba(251,191,36,0.2);border-radius:10px;font-size:0.82rem;color:#fbbf24;">
            ⚠️ No backup has been taken yet. It is recommended to take a backup regularly.
        </div>
        <?php endif; ?>
    </div>

</div>

<script>
    function startProgress() {
        const btn = document.getElementById('backupBtn');
        const wrap = document.getElementById('progressWrap');
        const label = document.getElementById('progressLabel');
        btn.disabled = true;
        btn.innerHTML = '<span>⏳</span> Generating...';
        wrap.style.display = 'block';
        label.style.display = 'block';
        // Re-enable after 60s (in case something went wrong)
        setTimeout(() => {
            btn.disabled = false;
            btn.innerHTML = '<span>⬇️</span> Download Full Backup';
            wrap.style.display = 'none';
            label.style.display = 'none';
        }, 60000);
    }
</script>

<?php include 'includes/footer.php'; ?>
</body>
</html>
