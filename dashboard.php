<?php
require_once 'includes/security.php';
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'moderator', 'tm', 'mkt', 'user'])) {
    header("Location: login.php");
    exit;
}


require_once 'includes/db_config.php';
require_once 'includes/get_nav.php';

$search = isset($_GET['search']) ? $_GET['search'] : '';
$type_filter = isset($_GET['type']) ? $_GET['type'] : ''; 
$prov_filter = $_GET['province'] ?? '';
$dist_filter = $_GET['district'] ?? '';
$dealer_filter = $_GET['dealer'] ?? '';
$agent_filter = $_GET['agent'] ?? '';

$page = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? (int)$_GET['page'] : 1;
$limit = 50;
$offset = ($page - 1) * $limit;

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

// Count total
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM counters WHERE $where");
$countStmt->execute($params);
$total_records = $countStmt->fetchColumn();

// Fetch records
$stmt = $pdo->prepare("SELECT * FROM counters WHERE $where ORDER BY district ASC, created_at DESC LIMIT $limit OFFSET $offset");
$stmt->execute($params);
$records = $stmt->fetchAll();

$total_pages = ceil($total_records / $limit);

// Fetch filter options
$provinces = $pdo->query("SELECT DISTINCT province FROM counters WHERE province IS NOT NULL AND province != '' ORDER BY province")->fetchAll(PDO::FETCH_COLUMN);
$districts = [];
if ($prov_filter) {
    $d_stmt = $pdo->prepare("SELECT DISTINCT district FROM counters WHERE province = ? AND district IS NOT NULL AND district != '' ORDER BY district");
    $d_stmt->execute([$prov_filter]);
    $districts = $d_stmt->fetchAll(PDO::FETCH_COLUMN);
} else {
    $districts = $pdo->query("SELECT DISTINCT district FROM counters WHERE district IS NOT NULL AND district != '' ORDER BY district")->fetchAll(PDO::FETCH_COLUMN);
}

$sales_types = $pdo->query("SELECT DISTINCT sales_method FROM counters WHERE sales_method IS NOT NULL AND sales_method != '' ORDER BY sales_method")->fetchAll(PDO::FETCH_COLUMN);
// Group Ticket Counter and Sales Booth conceptually if needed, but the search logic handles it.
// To keep it simple, we'll show unique values from DB.

$dealers_list = $pdo->query("SELECT DISTINCT dealer_code FROM counters WHERE dealer_code IS NOT NULL AND dealer_code != '' ORDER BY dealer_code")->fetchAll(PDO::FETCH_COLUMN);
$agents_list = [];
if ($dealer_filter) {
    $a_stmt = $pdo->prepare("SELECT DISTINCT agent_code FROM counters WHERE dealer_code = ? AND agent_code IS NOT NULL AND agent_code != '' ORDER BY agent_code");
    $a_stmt->execute([$dealer_filter]);
    $agents_list = $a_stmt->fetchAll(PDO::FETCH_COLUMN);
} else {
    $agents_list = $pdo->query("SELECT DISTINCT agent_code FROM counters WHERE agent_code IS NOT NULL AND agent_code != '' ORDER BY agent_code")->fetchAll(PDO::FETCH_COLUMN);
}

$stats_stmt = $pdo->prepare("SELECT 
    CASE 
        WHEN sales_method = 'Ticket Counter' OR sales_method = 'Sales Booth' OR sales_method IS NULL OR sales_method = '' THEN 'Ticket Counter'
        WHEN sales_method LIKE 'Mobile Sales%' THEN 'Mobile Sales'
        WHEN sales_method LIKE 'Sales Point%' THEN 'Sales Point'
        ELSE sales_method 
    END AS group_method, 
    COUNT(*) as count 
    FROM counters WHERE $where 
    GROUP BY group_method");
$stats_stmt->execute($params);
$stats_data = $stats_stmt->fetchAll(PDO::FETCH_ASSOC);
$overall_total = array_sum(array_column($stats_data, 'count'));

// Calculate specific counts for Dealers and Agents in results
$dealer_count_stmt = $pdo->prepare("SELECT COUNT(DISTINCT dealer_code) FROM counters WHERE $where AND dealer_code IS NOT NULL AND dealer_code != ''");
$dealer_count_stmt->execute($params);
$dealer_total = $dealer_count_stmt->fetchColumn();

$agent_count_stmt = $pdo->prepare("SELECT COUNT(DISTINCT agent_code) FROM counters WHERE $where AND agent_code IS NOT NULL AND agent_code != ''");
$agent_count_stmt->execute($params);
$agent_total = $agent_count_stmt->fetchColumn();

// Export Link
$export_url = "ajax/export_sellers.php?" . http_build_query($_GET);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - NLB Seller Map</title>
    <link rel="manifest" href="assets/manifest.json">
    <meta name="theme-color" content="#0072ff">
    <link rel="icon" type="image/png" href="assets/img/logo1.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .district-header {
            background: rgba(0, 114, 255, 0.1);
            color: var(--secondary-color);
            font-weight: 800;
            padding: 1rem 1.5rem;
            font-size: 1.1rem;
            text-transform: uppercase;
            letter-spacing: 2px;
            border-left: 5px solid var(--secondary-color);
            margin: 1.5rem 0 0.5rem 0;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .dashboard-row {
            cursor: pointer;
            transition: all 0.2s;
        }
        .dashboard-row:hover {
            background: rgba(255, 255, 255, 0.05) !important;
            transform: scale(1.002);
        }
        .status-badge {
            padding: 4px 12px;
            border-radius: 50px;
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
        }
        .status-active { background: rgba(16, 185, 129, 0.1); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.2); }
        .status-inactive { background: rgba(239, 68, 68, 0.1); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.2); }
        .status-incomplete { background: rgba(251, 191, 36, 0.1); color: #fbbf24; border: 1px solid rgba(251, 191, 36, 0.3); }
        
        /* Modal Styles */
        .details-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.85);
            z-index: 9999;
            backdrop-filter: blur(15px);
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .modal-body {
            background: var(--bg-dark);
            width: 100%;
            max-width: 900px;
            max-height: 90vh;
            border-radius: 24px;
            border: 1px solid var(--glass-border);
            overflow-y: auto;
            position: relative;
            box-shadow: 0 50px 100px -20px rgba(0,0,0,0.7);
            animation: modalPop 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        }
        @keyframes modalPop {
            from { opacity: 0; transform: scale(0.9) translateY(20px); }
            to { opacity: 1; transform: scale(1) translateY(0); }
        }
        .modal-header {
            padding: 2rem;
            background: linear-gradient(135deg, var(--primary-color), var(--accent-gradient));
            position: sticky;
            top: 0;
            z-index: 10;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .modal-close-btn {
            background: rgba(255,255,255,0.1);
            border: none;
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: 0.3s;
        }
        .modal-close-btn:hover { background: rgba(239, 68, 68, 0.8); }
        
        .modal-content-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            padding: 2rem;
        }
        .detail-item {
            background: rgba(255,255,255,0.02);
            padding: 1.25rem;
            border-radius: 16px;
            border: 1px solid var(--glass-border);
        }
        .detail-label {
            font-size: 0.7rem;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 0.5rem;
        }
        .detail-value {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-main);
        }
        .modal-gallery {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            padding: 0 2rem 2rem;
        }
        .gallery-box {
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid var(--glass-border);
            aspect-ratio: 1;
        }
        .gallery-box img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
    </style>
</head>
<body>
    <div class="container wide">
        <div class="nav-bar">
            <div class="nav-brand">
                <img src="assets/img/Logo.png" alt="NLB Logo">
                <div>
                    <h1>NLB Map Portal</h1>
                    <p style="color: var(--text-muted); font-size: 0.75rem; margin: 0; opacity: 0.8;">
                        Logged in as <span class="role-badge badge-<?php echo $_SESSION['role']; ?>"><?php echo $_SESSION['username']; ?></span>
                    </p>
                </div>
            </div>
            <?php echo render_nav($pdo, $_SESSION['role']); ?>
        </div>

        <div style="margin-bottom: 2rem; background: rgba(0,0,0,0.15); padding: 0.75rem 1rem; border-radius: 12px; border: 1px solid var(--glass-border);">
            <form action="" method="GET" style="display: flex; align-items: center; gap: 8px; flex-wrap: nowrap;">
                <!-- Search Box -->
                <div style="flex: 1; min-width: 180px;">
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search Seller..." style="width: 100%; padding: 0.55rem 0.8rem; font-size: 0.8rem; border-radius: 8px;">
                    <?php if($type_filter): ?><input type="hidden" name="type" value="<?php echo htmlspecialchars($type_filter); ?>"><?php endif; ?>
                </div>
                
                <!-- Dropdowns -->
                <select name="type" onchange="this.form.submit()" style="padding: 0.45rem; background: var(--input-bg); border: 1px solid var(--glass-border); border-radius: 6px; color: var(--text-main); font-family: 'Outfit', sans-serif; font-size: 0.75rem; width: 100px;">
                    <option value="">-- Type --</option>
                    <option value="Ticket Counter" <?php echo $type_filter === 'Ticket Counter' ? 'selected' : ''; ?>>Ticket Counter</option>
                    <option value="Mobile Sales" <?php echo $type_filter === 'Mobile Sales' ? 'selected' : ''; ?>>Mobile Sales</option>
                    <option value="Sales Point" <?php echo $type_filter === 'Sales Point' ? 'selected' : ''; ?>>Sales Point</option>
                </select>

                <select name="province" onchange="this.form.submit()" style="padding: 0.45rem; background: var(--input-bg); border: 1px solid var(--glass-border); border-radius: 6px; color: var(--text-main); font-family: 'Outfit', sans-serif; font-size: 0.75rem; width: 100px;">
                    <option value="">-- Province --</option>
                    <?php foreach($provinces as $p): ?>
                        <option value="<?php echo htmlspecialchars($p); ?>" <?php echo $prov_filter === $p ? 'selected' : ''; ?>><?php echo htmlspecialchars($p); ?></option>
                    <?php endforeach; ?>
                </select>

                <select name="district" onchange="this.form.submit()" style="padding: 0.45rem; background: var(--input-bg); border: 1px solid var(--glass-border); border-radius: 6px; color: var(--text-main); font-family: 'Outfit', sans-serif; font-size: 0.75rem; width: 100px;">
                    <option value="">-- District --</option>
                    <?php foreach($districts as $d): ?>
                        <option value="<?php echo htmlspecialchars($d); ?>" <?php echo $dist_filter === $d ? 'selected' : ''; ?>><?php echo htmlspecialchars($d); ?></option>
                    <?php endforeach; ?>
                </select>

                <select name="dealer" onchange="this.form.submit()" style="padding: 0.45rem; background: var(--input-bg); border: 1px solid var(--glass-border); border-radius: 6px; color: var(--text-main); font-family: 'Outfit', sans-serif; font-size: 0.75rem; width: 100px;">
                    <option value="">-- Dealer --</option>
                    <?php foreach($dealers_list as $dl): ?>
                        <option value="<?php echo htmlspecialchars($dl); ?>" <?php echo $dealer_filter === $dl ? 'selected' : ''; ?>><?php echo htmlspecialchars($dl); ?></option>
                    <?php endforeach; ?>
                </select>

                <select name="agent" onchange="this.form.submit()" style="padding: 0.45rem; background: var(--input-bg); border: 1px solid var(--glass-border); border-radius: 6px; color: var(--text-main); font-family: 'Outfit', sans-serif; font-size: 0.75rem; width: 100px;">
                    <option value="">-- Agent --</option>
                    <?php foreach($agents_list as $al): ?>
                        <option value="<?php echo htmlspecialchars($al); ?>" <?php echo $agent_filter === $al ? 'selected' : ''; ?>><?php echo htmlspecialchars($al); ?></option>
                    <?php endforeach; ?>
                </select>

                <!-- Action Buttons -->
                <button type="submit" class="btn-submit" style="margin:0; width:auto; padding: 0.45rem 0.7rem; font-size: 0.75rem; height: 30px;">🔍</button>
                
                <?php if ($search || $type_filter || $prov_filter || $dist_filter || $dealer_filter || $agent_filter): ?>
                    <a href="dashboard.php" class="btn-delete" style="padding: 0.45rem; text-decoration: none; font-size: 0.7rem; border-radius: 6px; background: rgba(255,255,255,0.05); height: 30px; line-height: 16px; width: 30px; text-align: center; display: flex; align-items: center; justify-content: center;">✖</a>
                <?php endif; ?>

                <div style="width: 1px; height: 20px; background: var(--glass-border); margin: 0 4px;"></div>

                <a href="<?php echo $export_url; ?>" class="btn-submit" style="margin:0; background: #10b981; text-decoration: none; display: flex; align-items: center; justify-content: center; gap: 4px; padding: 0.45rem 0.7rem; font-size: 0.75rem; white-space: nowrap; height: 30px;">
                    📊 Export
                </a>
            </form>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; margin-bottom: 2rem;">
            <div class="stat-item" style="background: rgba(255,255,255,0.03); border: 1px solid var(--glass-border); padding: 1rem; border-radius: 16px; text-align: center;">
                <div style="font-size: 0.7rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 0.5rem;">Overall Summary</div>
                <div style="display: flex; justify-content: space-around; align-items: baseline; gap: 10px;">
                    <div>
                        <div style="font-size: 1.4rem; font-weight: 800; color: #10b981;"><?php echo $dealer_total; ?></div>
                        <div style="font-size: 0.6rem; color: var(--text-muted);">Dealers</div>
                    </div>
                    <div>
                        <div style="font-size: 1.4rem; font-weight: 800; color: #60a5fa;"><?php echo $agent_total; ?></div>
                        <div style="font-size: 0.6rem; color: var(--text-muted);">Agents</div>
                    </div>
                    <div>
                        <div style="font-size: 1.8rem; font-weight: 800; color: var(--secondary-color);"><?php echo $overall_total; ?></div>
                        <div style="font-size: 0.65rem; color: var(--text-muted); font-weight: 700;">Total Sellers</div>
                    </div>
                </div>
            </div>
            <?php foreach($stats_data as $s): 
                $label = $s['group_method'] ?: 'Uncategorized';
                $color = 'var(--text-main)';
                if(stripos($label, 'Ticket Counter') !== false || stripos($label, 'Sales Booth') !== false) $color = '#60a5fa';
                else if(stripos($label, 'Mobile Sales') !== false) $color = '#fbbf24';
                else if(stripos($label, 'Sales Point') !== false) $color = '#a78bfa';
            ?>
            <div class="stat-item" style="background: rgba(255,255,255,0.03); border: 1px solid var(--glass-border); padding: 1.2rem; border-radius: 16px; text-align: center;">
                <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 0.5rem;"><?php echo htmlspecialchars($label); ?></div>
                <div style="font-size: 1.8rem; font-weight: 800; color: <?php echo $color; ?>;"><?php echo $s['count']; ?></div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Dealer / Agent</th>
                        <th>Seller Details</th>
                        <th>Status</th>
                        <th>QR Code</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($records)): ?>
                        <tr><td colspan="4" style="text-align: center;">No records found.</td></tr>
                    <?php else: 
                        $current_district = '';
                        foreach ($records as $row): 
                            if ($row['district'] !== $current_district): 
                                $current_district = $row['district']; ?>
                                <tr>
                                    <td colspan="4" style="padding:0; border:none;">
                                        <div class="district-header">📍 <?php echo htmlspecialchars($current_district ?: 'Unassigned District'); ?></div>
                                    </td>
                                </tr>
                            <?php endif; 
                            $status = $row['status'] ?? 'Active';
                        ?>
                            <tr class="dashboard-row" onclick="showSellerDetails(<?php echo htmlspecialchars(json_encode($row)); ?>)">
                                <td data-label="Dealer/Agent">
                                    <div style="font-weight: 700; color: var(--secondary-color);"><?php echo htmlspecialchars($row['dealer_code']); ?></div>
                                    <div style="font-size: 0.8rem; opacity: 0.7;"><?php echo htmlspecialchars($row['agent_code']); ?></div>
                                </td>
                                <td data-label="Seller Details">
                                    <div style="font-weight: 600; font-size: 1.1rem;"><?php echo htmlspecialchars($row['seller_name']); ?></div>
                                    <div style="font-size: 0.75rem; opacity: 0.6;">#<?php echo htmlspecialchars($row['reg_number'] ?: 'N/A'); ?></div>
                                </td>
                                <td data-label="Status">
                                    <?php 
                                        $badge_class = 'status-inactive';
                                        if($status === 'Active') $badge_class = 'status-active';
                                        if($status === 'Incomplete') $badge_class = 'status-incomplete';
                                    ?>
                                    <span class="status-badge <?php echo $badge_class; ?>">
                                        <?php echo $status; ?>
                                    </span>
                                </td>
                                <td data-label="QR Code" onclick="event.stopPropagation()">
                                    <?php 
                                        $current_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]" . dirname($_SERVER['PHP_SELF']);
                                        $view_url = $current_url . "/view_public.php?id=" . $row['id'];
                                        $qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=" . urlencode($view_url);
                                    ?>
                                    <img src="<?php echo $qr_url; ?>" style="width:45px; height:45px; background:white; padding:2px; border-radius:8px; cursor:pointer;" onclick="openQRLightbox('<?php echo $qr_url; ?>', '<?php echo htmlspecialchars(addslashes($row['reg_number'] ?? '')); ?>', '<?php echo htmlspecialchars(addslashes($row['seller_name'] ?? '')); ?>')">
                                </td>
                            </tr>
                        <?php endforeach; 
                    endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($total_pages > 1): ?>
        <div style="display: flex; justify-content: center; gap: 8px; margin-top: 1rem;">
            <span style="padding: 8px 16px; background: rgba(0, 114, 255, 0.1); border-radius: 8px; color: var(--text-muted);">
                Page <?php echo $page; ?> of <?php echo $total_pages; ?>
            </span>
        </div>
        <?php endif; ?>
    </div>

    <!-- Details Modal -->
    <div id="sellerModal" class="details-modal" onclick="closeSellerModal(event)">
        <div class="modal-body" onclick="event.stopPropagation()">
            <div id="modalLoading" style="padding: 3rem; text-align: center; color: var(--secondary-color); font-weight: 600;">
                <div style="font-size: 2rem; margin-bottom: 1rem; animation: pulse 1.5s infinite;">⏳</div> Loading Seller Details...
            </div>
            
            <div id="modalContent" style="display: none;">
                <div class="modal-header">
                    <div>
                        <h2 id="modalSellerName" style="color:#fff; margin:0; font-size: 1.5rem;">Seller Name</h2>
                        <div style="display: flex; gap: 10px; align-items: center; margin-top: 5px;">
                            <span id="modalRegNum" style="background: rgba(255, 204, 0, 0.2); color: var(--secondary-color); padding: 4px 10px; border-radius: 6px; font-weight: 700; font-size: 0.85rem;"></span>
                            <span id="modalBoard" style="background: rgba(255,255,255,0.1); color: #fff; padding: 4px 10px; border-radius: 6px; font-weight: 700; font-size: 0.85rem; border: 1px solid rgba(255,255,255,0.2);"></span>
                        </div>
                    </div>
                    <button class="modal-close-btn" onclick="closeSellerModal(event)">&times;</button>
                </div>
                
                <div class="modal-content-grid">
                    <div class="detail-item">
                        <div class="detail-label">Seller Code</div>
                        <div class="detail-value" id="modalSellerCode"></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Dealer / Agent</div>
                        <div class="detail-value" id="modalCodes"></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">NIC (Type / No)</div>
                        <div id="modalNICInfo" style="font-weight: 600; color: var(--text-main);"></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">🎂 Birthday</div>
                        <div class="detail-value" id="modalBirthday"></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">📅 Joined Year</div>
                        <div class="detail-value" id="modalJoinedYear"></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Sales Method</div>
                        <div class="detail-value" id="modalMethod"></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">📞 Phone</div>
                        <div class="detail-value" id="modalPhone"></div>
                    </div>
                    <div class="detail-item" style="grid-column: 1 / -1; display: none;" id="boardCommentRow">
                        <div class="detail-label">📋 Board Comment / Sales Detail</div>
                        <div class="detail-value" id="modalBoardComment"></div>
                    </div>
                    <div class="detail-item" style="grid-column: 1 / -1;">
                        <div class="detail-label">🏠 Full Address</div>
                        <div class="detail-value" id="modalAddress"></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">🗺️ Province / District</div>
                        <div class="detail-value" id="modalProvDist"></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">📍 Division / GN</div>
                        <div class="detail-value" id="modalDivGN"></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Added By / Date</div>
                        <div id="modalAddedBy" style="font-size: 0.9rem; color: var(--text-muted);"></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Status</div>
                        <div id="modalStatus"></div>
                    </div>
                    
                    <div class="detail-item" style="grid-column: 1 / -1; background: rgba(251, 191, 36, 0.05); border-color: rgba(251, 191, 36, 0.2);">
                        <div class="detail-label" style="color: #fbbf24;">📝 Remarks / Comments</div>
                        <div class="detail-value" id="modalRemarks" style="font-size: 0.95rem; font-weight: 400; white-space: pre-wrap;"></div>
                    </div>

                    <!-- Custom Fields Container -->
                    <div id="customFieldsWrapper" style="grid-column: 1 / -1; display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 2rem;">
                        <!-- Custom fields injected here -->
                    </div>
                </div>

                <div style="padding: 0 2rem 1rem;">
                    <h3 style="color:var(--text-muted); font-size:0.75rem; text-transform:uppercase; letter-spacing: 1px;">📍 Google Maps Link</h3>
                    <a id="modalMapBtn" href="" target="_blank" class="btn-submit" style="width:auto; display:inline-flex; align-items:center; gap:8px; background: rgba(0, 212, 255, 0.1); border: 1px solid rgba(0, 212, 255, 0.3); color: #00d4ff;">🗺️ Open in Google Maps</a>
                </div>

                <div style="padding: 0 2rem 1rem;">
                    <h3 style="color:var(--text-muted); font-size:0.75rem; text-transform:uppercase; letter-spacing: 1px;">📸 Photos</h3>
                    <div class="modal-gallery" id="modalGallery"></div>
                </div>
                
                <div style="padding: 2rem; border-top: 1px solid var(--glass-border); display: flex; flex-wrap: wrap; gap: 10px; background: rgba(255,255,255,0.01);">
                    <a id="modalEditBtn" href="" class="btn-submit" style="flex:1; min-width: 120px; background:rgba(255,255,255,0.05); color:#fff; border:1px solid var(--glass-border);">✏️ Edit Record</a>
                    <?php if ($_SESSION['role'] === 'admin'): ?>
                    <form id="modalDeleteForm" action="ajax/delete_record.php" method="POST" style="flex:1; min-width: 120px; display:flex; margin:0; padding:0;" onsubmit="return confirm('Are you sure you want to delete this record? This action cannot be undone.');">
                        <?php csrf_input(); ?>
                        <input type="hidden" name="id" id="modalDeleteId" value="">
                        <button type="submit" class="btn-submit" style="flex:1; width:100%; height:100%; background:rgba(239, 68, 68, 0.1); color:#ef4444; border:1px solid rgba(239, 68, 68, 0.3);">🗑️ Delete</button>
                    </form>
                    <?php endif; ?>
                    <button onclick="closeSellerModal(event)" class="btn-submit" style="flex:1; min-width: 120px;">Close</button>
                </div>
            </div>
        </div>
    </div>



    <script>
        function showSellerDetails(rowData) {
            const modal = document.getElementById('sellerModal');
            const loading = document.getElementById('modalLoading');
            const content = document.getElementById('modalContent');
            
            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
            loading.style.display = 'block';
            content.style.display = 'none';

            // Fetch FULL details via AJAX including custom fields
            fetch('ajax/get_record_details.php?id=' + rowData.id)
                .then(res => {
                    if (!res.ok) throw new Error("HTTP " + res.status);
                    return res.json();
                })
                .then(data => {
                    if (!data || data.error) {
                        alert(data ? data.error : "Unknown data error");
                        closeSellerModal();
                        return;
                    }

                    const rec = data.record;
                    document.getElementById('modalSellerName').innerText = rec.seller_name;
                    document.getElementById('modalRegNum').innerText = '#' + (rec.reg_number || 'N/A');
                    document.getElementById('modalBoard').innerText = (rec.counter_state || 'NLB') + ' Board';
                    document.getElementById('modalSellerCode').innerText = rec.seller_code || 'N/A';
                    document.getElementById('modalCodes').innerText = `${rec.dealer_code} / ${rec.agent_code}`;
                    
                    let nicDisplay = "";
                    if (rec.nic_old && rec.nic_new && rec.nic_old !== rec.nic_new) {
                        nicDisplay = `${rec.nic_old} (Old) ➜ ${rec.nic_new} (New)`;
                    } else {
                        nicDisplay = rec.nic_new || rec.nic_old || 'N/A';
                        if (rec.nic_type) nicDisplay = rec.nic_type.toUpperCase() + ": " + nicDisplay;
                    }
                    document.getElementById('modalNICInfo').innerText = nicDisplay;
                    
                    document.getElementById('modalBirthday').innerText = rec.birthday || 'N/A';
                    document.getElementById('modalJoinedYear').innerText = rec.joined_year || 'N/A';
                    document.getElementById('modalMethod').innerText = rec.sales_method || 'N/A';
                    document.getElementById('modalPhone').innerText = rec.phone || 'N/A';
                    document.getElementById('modalRemarks').innerText = rec.remarks || '-';
                    
                    const boardCommentRow = document.getElementById('boardCommentRow');
                    if (rec.board_comment) {
                        document.getElementById('modalBoardComment').innerText = rec.board_comment;
                        boardCommentRow.style.display = 'block';
                    } else {
                        boardCommentRow.style.display = 'none';
                    }
                    document.getElementById('modalAddress').innerText = (rec.address || '') + (rec.address2 ? '\n' + rec.address2 : '');
                    document.getElementById('modalProvDist').innerText = `${rec.province || 'N/A'} / ${rec.district || 'N/A'}`;
                    document.getElementById('modalDivGN').innerText = `${rec.ds_division || 'N/A'} / ${rec.gn_division || 'N/A'}`;
                    
                    const timeAgo = new Date(rec.created_at).toLocaleString();
                    document.getElementById('modalAddedBy').innerHTML = `By <b>${rec.added_by || 'System'}</b> on <i>${timeAgo}</i>`;

                    const statusBox = document.getElementById('modalStatus');
                    let statusClass = 'status-inactive';
                    if (rec.status === 'Active') statusClass = 'status-active';
                    if (rec.status === 'Incomplete') statusClass = 'status-incomplete';
                    statusBox.innerHTML = `<span class="status-badge ${statusClass}">${rec.status || 'Active'}</span>`;

                    // Handle Custom Fields Injection
                    const customWrapper = document.getElementById('customFieldsWrapper');
                    customWrapper.innerHTML = '';
                    if (data.custom_fields && data.custom_fields.length > 0) {
                        data.custom_fields.forEach(cf => {
                            customWrapper.innerHTML += `
                                <div class="detail-item">
                                    <div class="detail-label">${cf.field_label}</div>
                                    <div class="detail-value">${cf.field_value || 'N/A'}</div>
                                </div>
                            `;
                        });
                    }

                    const mapBtn = document.getElementById('modalMapBtn');
                    if (rec.location_link) {
                        mapBtn.href = rec.location_link;
                        mapBtn.style.display = 'inline-flex';
                    } else {
                        mapBtn.style.display = 'none';
                    }

                    document.getElementById('modalEditBtn').href = 'edit_record.php?id=' + rec.id;
                    const deleteIdInput = document.getElementById('modalDeleteId');
                    if (deleteIdInput) {
                        deleteIdInput.value = rec.id;
                    }

                    const gallery = document.getElementById('modalGallery');
                    gallery.innerHTML = '';
                    const photos = [
                        { img: rec.seller_image, label: 'Profile' },
                        { img: rec.image_front, label: 'Front' },
                        { img: rec.image_side, label: 'Side' },
                        { img: rec.image_inside, label: 'Inside' }
                    ];
                    photos.forEach(p => {
                        if (p.img) {
                            gallery.innerHTML += `<div class="gallery-box" title="${p.label} View"><img src="${p.img}" loading="lazy" onclick="window.open(this.src)"></div>`;
                        }
                    });

                    loading.style.display = 'none';
                    content.style.display = 'block';
                })
                .catch(err => {
                    console.error("Fetch error for ID " + rowData.id + ":", err);
                    alert("Error loading details for ID " + rowData.id + ". Please check if 'get_record_details.php' exists in the root folder.");
                    closeSellerModal();
                });
        }

        function closeSellerModal(e) {
            document.getElementById('sellerModal').style.display = 'none';
            document.body.style.overflow = '';
        }

        function openQRLightbox(src, regNum, name) {
            event.stopPropagation();
            const largeQR = src.replace('size=100x100', 'size=300x300');
            const overlay = document.createElement('div');
            overlay.className = 'modal-overlay';
            overlay.style.display = 'flex';
            overlay.innerHTML = `
                <span class="modal-close" onclick="this.parentElement.remove()">&times;</span>
                <div class="modal-content" style="background:var(--card-bg); padding:2rem; border-radius:24px; text-align:center; border:1px solid var(--glass-border); backdrop-filter:blur(30px); max-width:400px; width:90%;">
                    <div style="font-size:1.5rem; font-weight:800; color:var(--secondary-color); margin-bottom:10px;">#${regNum}</div>
                    <div style="color:#fff; font-weight:600; margin-bottom:20px;">${name}</div>
                    <div style="background:white; padding:15px; display:inline-block; border-radius:15px; margin-bottom:20px;">
                        <img src="${largeQR}" style="display:block; width:220px; height:220px;">
                    </div>
                </div>
            `;
            document.body.appendChild(overlay);
        }
    </script>
    <?php include 'includes/footer.php'; ?>
</body>
</html>
