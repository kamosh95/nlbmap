<?php
/**
 * NLB Seller Map - Automated Daily Database Backup Script
 * Run via Windows Task Scheduler at midnight (12:00 AM)
 * Usage: C:\xampp\php\php.exe C:\xampp\htdocs\SellerMap\cron_db_backup.php
 */

// Only allow CLI execution (not browser)
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die("403 Forbidden: This script can only be run from command line.");
}

// ── Config ───────────────────────────────────────────────────────────────────
define('DB_HOST',   'localhost');
define('DB_NAME',   'seller_map');
define('DB_USER',   'root');
define('DB_PASS',   'SriLanka_4321');

define('BACKUP_DIR',     __DIR__ . DIRECTORY_SEPARATOR . 'backups' . DIRECTORY_SEPARATOR . 'db');
define('KEEP_DAYS',      7);   // Keep last 7 days of backups
define('LOG_FILE',       __DIR__ . DIRECTORY_SEPARATOR . 'backups' . DIRECTORY_SEPARATOR . 'backup_log.txt');

// ── Helpers ──────────────────────────────────────────────────────────────────
function log_msg(string $msg): void {
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
    echo $line;
    file_put_contents(LOG_FILE, $line, FILE_APPEND | LOCK_EX);
}

function generate_sql_dump(PDO $pdo): string {
    $sql  = "-- NLB Seller Map - Automated Database Backup\n";
    $sql .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
    $sql .= "-- Database:  " . DB_NAME . "\n\n";
    $sql .= "SET NAMES utf8mb4;\n";
    $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

    foreach ($tables as $table) {
        $create = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_ASSOC);
        $sql .= "-- --------------------------------------------------------\n";
        $sql .= "-- Table: `$table`\n";
        $sql .= "-- --------------------------------------------------------\n";
        $sql .= "DROP TABLE IF EXISTS `$table`;\n";
        $sql .= $create['Create Table'] . ";\n\n";

        $rows = $pdo->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
        if (empty($rows)) {
            $sql .= "-- (no data)\n\n";
            continue;
        }

        $cols = '`' . implode('`, `', array_keys($rows[0])) . '`';
        $chunks = [];
        foreach ($rows as $row) {
            $vals = array_map(function($v) use ($pdo) {
                return $v === null ? 'NULL' : $pdo->quote($v);
            }, array_values($row));
            $chunks[] = '(' . implode(', ', $vals) . ')';
        }
        // Insert in batches of 100
        foreach (array_chunk($chunks, 100) as $batch) {
            $sql .= "INSERT INTO `$table` ($cols) VALUES\n";
            $sql .= implode(",\n", $batch) . ";\n";
        }
        $sql .= "\n";
    }

    $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";
    return $sql;
}

function delete_old_backups(int $keep_days): int {
    $deleted = 0;
    $cutoff  = time() - ($keep_days * 86400);
    foreach (glob(BACKUP_DIR . DIRECTORY_SEPARATOR . '*.sql.gz') as $file) {
        if (filemtime($file) < $cutoff) {
            @unlink($file);
            $deleted++;
        }
    }
    // Also check uncompressed
    foreach (glob(BACKUP_DIR . DIRECTORY_SEPARATOR . '*.sql') as $file) {
        if (filemtime($file) < $cutoff) {
            @unlink($file);
            $deleted++;
        }
    }
    return $deleted;
}

// ── Main ─────────────────────────────────────────────────────────────────────
log_msg("=== Backup started ===");

// Create backup directory
if (!is_dir(BACKUP_DIR)) {
    mkdir(BACKUP_DIR, 0755, true);
    log_msg("Created backup directory: " . BACKUP_DIR);
}

// Connect to DB
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    log_msg("Database connected: " . DB_NAME);
} catch (PDOException $e) {
    log_msg("ERROR: DB connection failed - " . $e->getMessage());
    exit(1);
}

// Generate dump
try {
    $sql_content = generate_sql_dump($pdo);
    $row_count   = $pdo->query("SELECT COUNT(*) FROM counters")->fetchColumn();
    log_msg("Dump generated. Counters: $row_count records.");
} catch (Exception $e) {
    log_msg("ERROR: Dump failed - " . $e->getMessage());
    exit(1);
}

// Save file
$filename    = 'seller_map_' . date('Y-m-d') . '.sql';
$backup_path = BACKUP_DIR . DIRECTORY_SEPARATOR . $filename;

// Compress if zlib available
if (function_exists('gzencode')) {
    $backup_path .= '.gz';
    file_put_contents($backup_path, gzencode($sql_content, 9));
    log_msg("Saved (gzip): $backup_path (" . round(filesize($backup_path) / 1024, 1) . " KB)");
} else {
    file_put_contents($backup_path, $sql_content);
    log_msg("Saved (plain): $backup_path (" . round(filesize($backup_path) / 1024, 1) . " KB)");
}

// Purge old backups
$deleted = delete_old_backups(KEEP_DAYS);
if ($deleted > 0) {
    log_msg("Purged $deleted old backup(s) older than " . KEEP_DAYS . " days.");
}

log_msg("=== Backup completed successfully ===");
exit(0);
