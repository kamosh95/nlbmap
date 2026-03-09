<?php
require_once '../includes/security.php';
require_once '../includes/db_config.php';

if (!isset($_SESSION['role'])) {
    die("Access denied");
}

$prov_filter = $_GET['province'] ?? '';
$dist_filter = $_GET['district'] ?? '';

$where = "1=1";
$params = [];
if($prov_filter) { $where .= " AND a.province = ?"; $params[] = $prov_filter; }
if($dist_filter) { $where .= " AND a.district = ?"; $params[] = $dist_filter; }

$stmt = $pdo->prepare("SELECT a.*, d.name as dealer_name FROM agents a LEFT JOIN dealers d ON a.dealer_code = d.dealer_code WHERE $where ORDER BY a.agent_code ASC");
$stmt->execute($params);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

$filename = "agents_report_" . date('Ymd_His') . ".csv";

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
