<?php
require_once '../includes/security.php';
require_once '../includes/db_config.php';

if (!isset($_SESSION['role'])) {
    die("Access denied");
}

$search = $_GET['search'] ?? '';

$where = "1=1";
$params = [];

if ($search) {
    $where .= " AND (dealer_code LIKE ? OR name LIKE ? OR nic_old LIKE ? OR nic_new LIKE ? OR province LIKE ? OR district LIKE ?)";
    $term = "%$search%";
    $params = array_merge($params, [$term, $term, $term, $term, $term, $term]);
}

$stmt = $pdo->prepare("
    SELECT d.*, 
           (SELECT GROUP_CONCAT(address_text SEPARATOR ' | ') FROM dealer_addresses WHERE dealer_id = d.id) as addresses,
           (SELECT GROUP_CONCAT(location_link SEPARATOR ' | ') FROM dealer_locations WHERE dealer_id = d.id) as location_link
    FROM dealers d 
    WHERE $where 
    ORDER BY d.dealer_code ASC
");
$stmt->execute($params);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

$filename = "dealers_report_" . date('Ymd_His') . ".csv";

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=' . $filename);

$output = fopen('php://output', 'w');

// BOM for Excel
fputs($output, $bom =( chr(0xEF) . chr(0xBB) . chr(0xBF) ));

// Header
if (!empty($records)) {
    fputcsv($output, array_keys($records[0]));
    foreach ($records as $row) {
        fputcsv($output, $row);
    }
} else {
    fputcsv($output, ['No data found']);
}

fclose($output);
exit;
