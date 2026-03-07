<?php
require_once 'includes/security.php';
if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'moderator' && $_SESSION['role'] !== 'user')) {
    header("Location: login.php");
    exit;
}


require_once 'includes/db_config.php';
require_once 'includes/get_nav.php';

$search = isset($_GET['search']) ? $_GET['search'] : '';
$type_filter = isset($_GET['type']) ? $_GET['type'] : ''; // Filter by sales_method

$page = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? (int)$_GET['page'] : 1;
$limit = 50;
$offset = ($page - 1) * $limit;

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

if ($search) {
    $where .= " AND (dealer_code LIKE ? OR agent_code LIKE ? OR seller_name LIKE ? OR seller_code LIKE ? OR nic_old LIKE ? OR nic_new LIKE ? OR reg_number LIKE ?)";
    $term = "%$search%";
    $params = array_merge($params, [$term, $term, $term, $term, $term, $term, $term]);
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
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
        }
        .status-active { background: rgba(16, 185, 129, 0.2); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.3); }
        .status-inactive { background: rgba(239, 68, 68, 0.2); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.3); }
        
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

        <div class="search-container" style="margin-bottom: 2rem;">
            <form action="" method="GET" style="display: flex; gap: 10px; max-width: 500px;">
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search Seller Name, Code, NIC..." style="flex: 1;">
                <button type="submit" class="btn-submit" style="margin:0; width:auto;">🔍</button>
                <?php if ($search || $type_filter): ?>
                    <a href="dashboard.php" class="btn-delete" style="padding: 0.8rem; text-decoration: none;">Reset</a>
                <?php endif; ?>
            </form>
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
                                    <span class="status-badge <?php echo $status === 'Active' ? 'status-active' : 'status-inactive'; ?>">
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
                        <div class="detail-label">Sales Method</div>
                        <div class="detail-value" id="modalMethod"></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">📞 Phone</div>
                        <div class="detail-value" id="modalPhone"></div>
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
                
                <div style="padding: 2rem; border-top: 1px solid var(--glass-border); display: flex; gap: 10px; background: rgba(255,255,255,0.01);">
                    <a id="modalEditBtn" href="" class="btn-submit" style="flex:1; background:rgba(255,255,255,0.05); color:#fff; border:1px solid var(--glass-border);">✏️ Edit Record</a>
                    <button onclick="closeSellerModal(event)" class="btn-submit" style="flex:1;">Close</button>
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
                    document.getElementById('modalMethod').innerText = rec.sales_method || 'N/A';
                    document.getElementById('modalPhone').innerText = rec.phone || 'N/A';
                    document.getElementById('modalAddress').innerText = (rec.address || '') + (rec.address2 ? '\n' + rec.address2 : '');
                    document.getElementById('modalProvDist').innerText = `${rec.province || 'N/A'} / ${rec.district || 'N/A'}`;
                    document.getElementById('modalDivGN').innerText = `${rec.ds_division || 'N/A'} / ${rec.gn_division || 'N/A'}`;
                    
                    const timeAgo = new Date(rec.created_at).toLocaleString();
                    document.getElementById('modalAddedBy').innerHTML = `By <b>${rec.added_by || 'System'}</b> on <i>${timeAgo}</i>`;

                    const statusBox = document.getElementById('modalStatus');
                    statusBox.innerHTML = `<span class="status-badge ${rec.status === 'Active' ? 'status-active' : 'status-inactive'}">${rec.status || 'Active'}</span>`;

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
