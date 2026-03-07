<?php
require_once '../includes/security.php';
require_once '../includes/db_config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'moderator', 'tm'])) {
        die("Unauthorized access.");
    }
    verify_csrf_token($_POST['csrf_token'] ?? '');
    
    $id = $_POST['id'] ?? null;
    $current_status = $_POST['status'] ?? 'Active';
    $new_status = ($current_status === 'Active') ? 'Inactive' : 'Active';
    
    if ($id) {
        try {
            $stmt = $pdo->prepare("UPDATE counters SET status = ? WHERE id = ?");
            if ($stmt->execute([$new_status, $id])) {
                log_activity($pdo, "Changed Seller Status", "ID: $id, New Status: $new_status", "seller");
                header("Location: ../dashboard.php?status_updated=1");
                exit;
            }
        } catch (PDOException $e) {
            die("Error updating status: " . $e->getMessage());
        }
    }
}
header("Location: ../dashboard.php");
exit;
