<?php
$host = 'localhost';
$dbname = 'seller_map';
$username = 'root';
$password = 'SriLanka_4321';
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Add backup nav entry
    $pdo->exec("INSERT IGNORE INTO navigation (label, url, role_access, nav_group, sort_order)
                VALUES ('System Backup 💾', 'backup.php', 'admin', 'Main', 45)");

    // Ensure image_rear column exists
    $pdo->exec("ALTER TABLE counters ADD COLUMN IF NOT EXISTS image_rear VARCHAR(255) DEFAULT NULL AFTER image_inside");

    echo "✅ Done! All updates applied successfully.<br><br>";
    echo "<strong>This file has been deleted automatically.</strong>";

    // Self-delete after success
    @unlink(__FILE__);

} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
