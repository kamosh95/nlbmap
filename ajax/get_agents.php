<?php
require_once '../includes/security.php';
require_once '../includes/db_config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['role'])) {
    echo json_encode([]);
    exit;
}

if (isset($_GET['dealer_code'])) {
    $dealer_code = $_GET['dealer_code'];
    try {
        $stmt = $pdo->prepare("SELECT agent_code, name FROM agents WHERE dealer_code = ? ORDER BY agent_code ASC");
        $stmt->execute([$dealer_code]);
        $agents = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($agents);
    } catch (PDOException $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
} elseif (isset($_GET['get_dealers'])) {
    // Return dealers filtered by TM's assigned districts
    try {
        $role = $_SESSION['role'];
        $user_id = $_SESSION['user_id'] ?? 0;

        $assigned = '';
        if ($role === 'tm') {
            $u_stmt = $pdo->prepare("SELECT assigned_districts FROM users WHERE id = ?");
            $u_stmt->execute([$user_id]);
            $assigned = $u_stmt->fetchColumn() ?? '';
        }

        if (!empty($assigned) && $role === 'tm') {
            $districts = array_filter(array_map('trim', explode(',', $assigned)));
            if (!empty($districts)) {
                $placeholders = implode(',', array_fill(0, count($districts), '?'));
                $stmt = $pdo->prepare("SELECT dealer_code, name, district FROM dealers WHERE district IN ($placeholders) ORDER BY dealer_code ASC");
                $stmt->execute($districts);
                $dealers = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode($dealers);
                exit;
            }
        }

        // Admin / no restriction — return all
        $dealers = $pdo->query("SELECT dealer_code, name, district FROM dealers ORDER BY dealer_code ASC")->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($dealers);
    } catch (PDOException $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
} else {
    echo json_encode([]);
}
?>
