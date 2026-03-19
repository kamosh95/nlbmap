<?php
require_once 'includes/db_config.php';
$stmt = $pdo->prepare("SELECT id FROM navigation WHERE url = 'backup.php'");
$stmt->execute();
if (!$stmt->fetch()) {
    $pdo->exec("INSERT INTO navigation (label, url, role_access, nav_group, sort_order) VALUES ('System Backup 💾', 'backup.php', 'admin', 'Main', 41)");
    echo "Added System Backup navigation link.\n";
} else {
    echo "System Backup link already exists.\n";
}
unlink(__FILE__);
?>
