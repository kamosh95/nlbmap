<?php
require_once '../includes/security.php';
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

require_once '../includes/db_config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    // Verify CSRF
    verify_csrf_token($_POST['csrf_token'] ?? '');
    
    $id = $_POST['id'];
    
    // First get the details to log and delete files
    $stmt = $pdo->prepare("SELECT seller_code, seller_name, image_front, image_side, image_inside FROM counters WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    
    if ($row) {
        log_activity($pdo, "Deleted Seller Record", "Code: {$row['seller_code']}, Name: {$row['seller_name']}", "seller");
        
        foreach (['image_front', 'image_side', 'image_inside'] as $img) {
            if ($row[$img] && file_exists($row[$img])) {
                unlink($row[$img]);
            }
        }
    }
    
    // Delete from database
    $stmt = $pdo->prepare("DELETE FROM counters WHERE id = ?");
    $stmt->execute([$id]);
}

header("Location: ../dashboard.php");
exit;
?>
