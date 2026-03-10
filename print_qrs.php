<?php
require_once 'includes/security.php';
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'moderator', 'tm', 'mkt', 'user'])) {
    header("Location: login.php");
    exit;
}

require_once 'includes/db_config.php';

$search = isset($_GET['search']) ? $_GET['search'] : '';
$type_filter = isset($_GET['type']) ? $_GET['type'] : ''; 
$prov_filter = $_GET['province'] ?? '';
$dist_filter = $_GET['district'] ?? '';
$dealer_filter = $_GET['dealer'] ?? '';
$agent_filter = $_GET['agent'] ?? '';

$where = "1=1";
$params = [];

if ($type_filter) {
    if ($type_filter === 'Ticket Counter') {
        $where .= " AND (sales_method = 'Ticket Counter' OR sales_method = 'Sales Booth' OR sales_method IS NULL OR sales_method = '')";
    } elseif ($type_filter === 'Mobile Sales') {
        $where .= " AND sales_method LIKE 'Mobile Sales%'";
    } elseif ($type_filter === 'Sales Point') {
        $where .= " AND sales_method LIKE 'Sales Point%'";
    } else {
        $where .= " AND sales_method = ?";
        $params[] = $type_filter;
    }
}

if ($search) {
    $where .= " AND (dealer_code LIKE ? OR agent_code LIKE ? OR seller_name LIKE ? OR seller_code LIKE ? OR nic_old LIKE ? OR nic_new LIKE ? OR reg_number LIKE ?)";
    $term = "%$search%";
    $params = array_merge($params, [$term, $term, $term, $term, $term, $term, $term]);
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

// Fetch records without pagination for printing
$stmt = $pdo->prepare("SELECT * FROM counters WHERE $where ORDER BY dealer_code ASC, agent_code ASC, created_at DESC");
$stmt->execute($params);
$records = $stmt->fetchAll();

$current_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]" . dirname($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print QR Codes - NLB Seller Map</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { 
            font-family: 'Outfit', sans-serif; 
            background: #fff; 
            color: #000; 
            margin: 0; 
            padding: 20px; 
        }
        .header-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #eee;
        }
        .grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); 
            gap: 20px; 
        }
        .qr-card { 
            border: 2px dashed #ccc; 
            padding: 15px; 
            text-align: center; 
            page-break-inside: avoid; 
            border-radius: 12px; 
        }
        .qr-card img { 
            width: 140px; 
            height: 140px; 
            margin-bottom: 10px;
            border: 1px solid #eee;
            border-radius: 8px;
            padding: 4px;
        }
        .qr-title { 
            font-weight: 700; 
            font-size: 15px; 
            margin-bottom: 5px; 
            white-space: nowrap; 
            overflow: hidden; 
            text-overflow: ellipsis;
        }
        .qr-meta { 
            font-size: 12px; 
            color: #555; 
            line-height: 1.4;
        }
        .btn-print {
            padding: 10px 24px; 
            background: #0072ff; 
            color: #fff; 
            border: none; 
            border-radius: 8px; 
            cursor: pointer; 
            font-size: 15px; 
            font-weight: bold;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-print:hover {
            background: #0056b3;
        }
        @media print {
            .no-print { display: none !important; }
            body { padding: 0; }
            .header-bar { border-bottom: none; margin-bottom: 10px; padding-bottom: 0; }
            .qr-card { border: 1px solid #777; }
        }
    </style>
</head>
<body>
    <div class="header-bar no-print">
        <div>
            <h2 style="margin:0;">QR Codes Print View</h2>
            <p style="margin:5px 0 0 0; color:#555;"><?php echo count($records); ?> records found for printing.</p>
        </div>
        <button class="btn-print" onclick="window.print()">🖨️ Print Now</button>
    </div>

    <div class="grid">
        <?php foreach($records as $row): 
            $view_url = $current_url . "/view_public.php?id=" . $row['id'];
            $qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" . urlencode($view_url);
        ?>
            <div class="qr-card">
                <img src="<?php echo htmlspecialchars($qr_url); ?>" alt="QR Code">
                <div class="qr-title" title="<?php echo htmlspecialchars($row['seller_name']); ?>"><?php echo htmlspecialchars($row['seller_name']); ?></div>
                <div class="qr-meta"><strong>Reg No:</strong> <?php echo htmlspecialchars($row['reg_number'] ?: 'N/A'); ?></div>
                <div class="qr-meta"><strong>Dealer:</strong> <?php echo htmlspecialchars($row['dealer_code']); ?></div>
                <div class="qr-meta"><strong>Agent:</strong> <?php echo htmlspecialchars($row['agent_code']); ?></div>
            </div>
        <?php endforeach; ?>
    </div>
    
    <?php if(empty($records)): ?>
        <p style="text-align:center; color:#777; margin-top:50px;">No records available to print based on the current filters.</p>
    <?php endif; ?>

</body>
</html>
