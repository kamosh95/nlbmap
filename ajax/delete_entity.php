<?php
require_once '../includes/security.php';
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

require_once '../includes/db_config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token($_POST['csrf_token'] ?? '');
    
    $id = (int)($_POST['id'] ?? 0);
    $type = $_POST['type'] ?? ''; // 'dealer' or 'agent'
    
    if ($id > 0) {
        try {
            if ($type === 'dealer') {
                $stmt = $pdo->prepare("SELECT name, photo FROM dealers WHERE id = ?");
                $stmt->execute([$id]);
                $row = $stmt->fetch();
                if ($row) {
                    log_activity($pdo, "Deleted Dealer", "Name: {$row['name']}", "dealer");
                    if ($row['photo'] && file_exists($row['photo'])) {
                        unlink($row['photo']);
                    }
                }
                
                $pdo->prepare("DELETE FROM dealers WHERE id = ?")->execute([$id]);
                header("Location: ../view_dealers.php?msg=Dealer deleted successfully");
            } else if ($type === 'agent') {
                $stmt = $pdo->prepare("SELECT name, photo FROM agents WHERE id = ?");
                $stmt->execute([$id]);
                $row = $stmt->fetch();
                if ($row) {
                    log_activity($pdo, "Deleted Agent", "Name: {$row['name']}", "agent");
                    if ($row['photo'] && file_exists($row['photo'])) {
                        unlink($row['photo']);
                    }
                }

                $pdo->prepare("DELETE FROM agents WHERE id = ?")->execute([$id]);
                header("Location: ../view_agents.php?msg=Agent deleted successfully");
            }
        } catch (PDOException $e) {
            header("Location: ../dashboard.php?err=" . urlencode($e->getMessage()));
        }
    }
}
exit;
