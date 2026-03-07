<?php
ob_start(); // Buffer all output
header('Content-Type: application/json');

require_once '../includes/security.php';
require_once '../includes/db_config.php';

// db_config.php is expected to establish the $pdo connection and set its attributes.
// If not, the following lines might be needed, but typically db_config handles this.
// $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
// $pdo->exec("SET names utf8mb4");

// db_config.php handles the $pdo connection and attributes.

try {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

    if (!$id) {
        ob_clean();
        echo json_encode(['error' => 'Invalid ID requested.']);
        exit;
    }

    // Get main record
    $stmt = $pdo->prepare("SELECT * FROM counters WHERE id = ?");
    $stmt->execute([$id]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$record) {
        ob_clean();
        echo json_encode(['error' => 'Seller details not found.']);
        exit;
    }

    // Get custom fields
    $custom_values = [];
    try {
        $stmt = $pdo->prepare("
            SELECT cf.field_label, cv.field_value 
            FROM counter_custom_values cv
            JOIN custom_fields cf ON cv.field_id = cf.id
            WHERE cv.counter_id = ?
            ORDER BY cf.sort_order ASC
        ");
        $stmt->execute([$id]);
        $custom_values = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Table might be missing, ignore
    }

    ob_clean(); // Clear any warnings/output before JSON
    echo json_encode([
        'record' => $record,
        'custom_fields' => $custom_values
    ], JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);

} catch (PDOException $e) {
    ob_clean();
    http_response_code(500);
    echo json_encode(['error' => 'Connection Failed: ' . $e->getMessage()]);
}
?>
