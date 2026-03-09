<?php
require_once '../includes/security.php';
require_once '../includes/db_config.php';

if (!isset($_SESSION['role'])) {
    die("Access denied");
}

$search = $_GET['search'] ?? '';
$type_filter = $_GET['type'] ?? '';
$prov_filter = $_GET['province'] ?? '';
$dist_filter = $_GET['district'] ?? '';
$dealer_filter = $_GET['dealer'] ?? '';
$agent_filter = $_GET['agent'] ?? '';

$where = "1=1";
$params = [];

if ($type_filter) {
    if ($type_filter === 'Ticket Counter') {
        $where .= " AND (sales_method = 'Ticket Counter' OR sales_method = 'Sales Booth' OR sales_method IS NULL OR sales_method = '')";
    } else {
        $where .= " AND sales_method = ?";
        $params[] = $type_filter;
    }
}

if ($prov_filter) {
    $where .= " AND province = ?";
    $params[] = $prov_filter;
}
if ($dist_filter) {
    $where .= " AND district = ?";
    $params[] = $dist_filter;
}
if ($dealer_filter) {
    $where .= " AND dealer_code = ?";
    $params[] = $dealer_filter;
}
if ($agent_filter) {
    $where .= " AND agent_code = ?";
    $params[] = $agent_filter;
}

if ($search) {
    $where .= " AND (dealer_code LIKE ? OR agent_code LIKE ? OR seller_name LIKE ? OR seller_code LIKE ? OR nic_old LIKE ? OR nic_new LIKE ? OR reg_number LIKE ?)";
    $term = "%$search%";
    $params = array_merge($params, [$term, $term, $term, $term, $term, $term, $term]);
}

$stmt = $pdo->prepare("SELECT * FROM counters WHERE $where ORDER BY district ASC, created_at DESC");
$stmt->execute($params);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

$filename = "sellers_report_" . date('Ymd_His') . ".csv";

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
