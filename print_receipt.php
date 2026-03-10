<?php
require_once 'includes/security.php';
require_once 'includes/db_config.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) die("Invalid ID");

// Fetch record
$stmt = $pdo->prepare("SELECT * FROM counters WHERE id = ?");
$stmt->execute([$id]);
$rec = $stmt->fetch();
if (!$rec) die("Record not found");

// Fetch custom fields
$stmt = $pdo->prepare("SELECT cf.field_label, cv.field_value FROM counter_custom_values cv JOIN custom_fields cf ON cv.field_id = cf.id WHERE cv.counter_id = ? ORDER BY cf.sort_order ASC");
$stmt->execute([$id]);
$custom_fields = $stmt->fetchAll();

// QR Code
$current_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]" . dirname($_SERVER['PHP_SELF']);
$view_url = $current_url . "/view_public.php?id=" . $id;
$qr_api = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" . urlencode($view_url);

// Approvals Logic
$approval_list = [];
if (!empty($rec['approvals_json'])) {
    $appro_data = json_decode($rec['approvals_json'], true) ?: [];
    if (isset($appro_data['selected']) && is_array($appro_data['selected'])) {
        foreach ($appro_data['selected'] as $app) {
            $year = $appro_data['years'][$app] ?? '';
            $approval_list[] = htmlspecialchars($app) . ($year ? " ($year)" : "");
        }
    }
    if (!empty($appro_data['other_comment'])) {
        $approval_list[] = "Other: " . htmlspecialchars($appro_data['other_comment']);
    }
    if (!empty($appro_data['no_approval_note'])) {
        $approval_list[] = "Note: " . htmlspecialchars($appro_data['no_approval_note']);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Full Details - <?php echo htmlspecialchars($rec['reg_number']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <style>
        :root {
            --bg-dark: #0f172a;
            --secondary-color: #00d4ff;
            --text-main: #f1f5f9;
            --text-muted: #94a3b8;
            --glass-bg: rgba(255, 255, 255, 0.03);
            --glass-border: rgba(255, 255, 255, 0.1);
        }
        body {
            font-family: 'Outfit', sans-serif;
            background: var(--bg-dark);
            color: var(--text-main);
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 2rem;
            min-height: 100vh;
        }
        #pdf-content {
            background: #fff;
            color: #000;
            width: 100%;
            max-width: 800px;
            padding: 50px;
            border-radius: 0;
            box-shadow: 0 20px 50px rgba(0,0,0,0.3);
        }
        .header { 
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            border-bottom: 3px solid #00d4ff;
            padding-bottom: 20px;
        }
        .header-left img { height: 70px; }
        .header-right { text-align: right; }
        .header-right h1 { font-size: 1.6rem; margin: 0; color: #0f172a; text-transform: uppercase; letter-spacing: 1px; }
        .header-right p { margin: 5px 0 0; color: #64748b; font-weight: 600; }
        
        .top-summary {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .reg-num-box { 
            background: #f8fafc; 
            border: 2px dashed #00d4ff; 
            padding: 20px; 
            text-align: center; 
            border-radius: 12px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .reg-num-box label { font-size: 0.8rem; color: #64748b; text-transform: uppercase; font-weight: 700; margin-bottom: 5px; }
        .reg-num-box span { font-size: 2.2rem; font-weight: 900; color: #0f172a; font-family: monospace; }
        
        .qr-section { text-align: center; }
        .qr-section img { border: 1px solid #e2e8f0; padding: 10px; border-radius: 12px; width: 120px; }
        
        .details-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            margin-bottom: 30px;
        }
        
        .section-title {
            font-size: 0.9rem;
            font-weight: 800;
            color: #00d4ff;
            text-transform: uppercase;
            margin-bottom: 15px;
            border-bottom: 2px solid #f1f5f9;
            padding-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #f1f5f9;
            font-size: 0.95rem;
        }
        .detail-row:last-child { border-bottom: none; }
        .label { color: #64748b; font-weight: 600; flex-shrink: 0; }
        .value { font-weight: 700; text-align: right; color: #1e293b; }
        
        .full-row {
            grid-column: 1 / -1;
            background: #f8fafc;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .full-row .label { display: block; margin-bottom: 10px; color: #00d4ff; font-size: 0.8rem; text-transform: uppercase; }
        .full-row .value { text-align: left; white-space: pre-wrap; font-size: 1rem; color: #334155; }

        .photo-gallery {
            grid-column: 1 / -1;
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-top: 20px;
        }
        .photo-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 5px;
            text-align: center;
        }
        .photo-box img {
            width: 100%;
            height: 120px;
            object-fit: cover;
            border-radius: 5px;
            margin-bottom: 5px;
        }
        .photo-box label {
            font-size: 0.7rem;
            color: #64748b;
            font-weight: 700;
            text-transform: uppercase;
        }

        .approval-badges {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            justify-content: flex-end;
        }
        .app-badge {
            background: #00d4ff;
            color: #fff;
            padding: 4px 10px;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 700;
        }

        .controls {
            margin-top: 30px;
            display: flex;
            gap: 15px;
            position: sticky;
            bottom: 20px;
            z-index: 100;
        }
        .btn {
            padding: 14px 28px;
            border-radius: 14px;
            border: none;
            font-weight: 700;
            cursor: pointer;
            font-family: 'Outfit', sans-serif;
            text-decoration: none;
            transition: 0.3s;
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
        }
        .btn-download { background: var(--secondary-color); color: #000; }
        .btn-print { background: #fff; color: #000; }
        .btn-back { background: rgba(255,255,255,0.1); color: #fff; backdrop-filter: blur(10px); border: 1px solid var(--glass-border); }

        @media print {
            .controls { display: none; }
            body { background: #fff; padding: 0; }
            #pdf-content { box-shadow: none; max-width: 100%; padding: 20px; }
            .btn { display: none; }
        }
    </style>
</head>
<body>

    <div id="pdf-content">
        <div class="header">
            <div class="header-left">
                <img src="assets/img/Logo.png" alt="NLB Logo">
            </div>
            <div class="header-right">
                <h1>Seller Registration</h1>
                <p>National Lotteries Board - Sri Lanka</p>
            </div>
        </div>

        <div class="top-summary">
            <div class="reg-num-box">
                <label>Registered Serial Number</label>
                <span><?php echo htmlspecialchars($rec['reg_number']); ?></span>
            </div>
            <div class="qr-section">
                <img src="<?php echo $qr_api; ?>" alt="QR Code">
                <p style="font-size: 0.6rem; color: #64748b; margin-top: 5px; font-weight: 700;">SCAN FOR E-VERIFICATION</p>
            </div>
        </div>

        <div class="details-grid">
            <!-- NETWORK SECTION -->
            <div>
                <div class="section-title">🏢 Network Hierarchy</div>
                <div class="detail-row">
                    <span class="label">Dealer Code:</span>
                    <span class="value"><?php echo htmlspecialchars($rec['dealer_code']); ?></span>
                </div>
                <div class="detail-row">
                    <span class="label">Agent Code:</span>
                    <span class="value"><?php echo htmlspecialchars($rec['agent_code']); ?></span>
                </div>
                <div class="detail-row">
                    <span class="label">District:</span>
                    <span class="value"><?php echo htmlspecialchars($rec['district']); ?></span>
                </div>
                <div class="detail-row">
                    <span class="label">Province:</span>
                    <span class="value"><?php echo htmlspecialchars($rec['province']); ?></span>
                </div>
            </div>

            <!-- SELLER SECTION -->
            <div>
                <div class="section-title">👤 Seller Identity</div>
                <div class="detail-row">
                    <span class="label">Full Name:</span>
                    <span class="value"><?php echo htmlspecialchars($rec['seller_name']); ?></span>
                </div>
                <div class="detail-row">
                    <span class="label">Seller Code:</span>
                    <span class="value"><?php echo htmlspecialchars($rec['seller_code']); ?></span>
                </div>
                <div class="detail-row">
                    <span class="label">NIC Type:</span>
                    <span class="value"><?php echo strtoupper($rec['nic_type'] ?? 'N/A'); ?></span>
                </div>
                <div class="detail-row">
                    <span class="label">NIC Number:</span>
                    <span class="value"><?php echo htmlspecialchars($rec['nic_new'] ?: $rec['nic_old']); ?></span>
                </div>
                <div class="detail-row">
                    <span class="label">Birthday:</span>
                    <span class="value"><?php echo htmlspecialchars($rec['birthday'] ?: 'N/A'); ?></span>
                </div>
                <div class="detail-row">
                    <span class="label">Phone:</span>
                    <span class="value"><?php echo htmlspecialchars($rec['phone'] ?: 'N/A'); ?></span>
                </div>
            </div>

            <!-- LOCATION SECTION -->
            <div class="full-row">
                <span class="label">📍 Physical Address</span>
                <span class="value"><?php 
                    echo htmlspecialchars($rec['address']); 
                    if(!empty($rec['address2'])) echo "\n" . htmlspecialchars($rec['address2']);
                ?></span>
            </div>

            <?php if (!empty($rec['location_link'])): ?>
            <div class="detail-row" style="grid-column: 1 / -1;">
                <span class="label">🌐 Maps Link:</span>
                <span class="value" style="font-size: 0.75rem; text-decoration: underline; color: #0072ff;"><?php echo htmlspecialchars($rec['location_link']); ?></span>
            </div>
            <?php endif; ?>

            <!-- ADMINISTRATIVE SECTION -->
            <div>
                <div class="section-title">📋 Administrative Info</div>
                <div class="detail-row">
                    <span class="label">Joined Year:</span>
                    <span class="value"><?php echo htmlspecialchars($rec['joined_year'] ?: 'N/A'); ?></span>
                </div>
                <div class="detail-row">
                    <span class="label">Sales Method:</span>
                    <span class="value"><?php echo htmlspecialchars($rec['sales_method'] ?: 'Ticket Counter'); ?></span>
                </div>
                <div class="detail-row">
                    <span class="label">Board State:</span>
                    <span class="value"><?php echo htmlspecialchars($rec['counter_state'] ?: 'NLB'); ?></span>
                </div>
                <div class="detail-row">
                    <span class="label">Status:</span>
                    <span class="value" style="color: <?php echo $rec['status'] === 'Active' ? '#10b981' : '#f59e0b'; ?>"><?php echo htmlspecialchars($rec['status']); ?></span>
                </div>
            </div>

            <!-- OPERATIONS SECTION -->
            <div>
                <div class="section-title">⏲️ Operational Details</div>
                <div class="detail-row">
                    <span class="label">Opening Hours:</span>
                    <span class="value"><?php echo htmlspecialchars($rec['opening_hours'] ?: 'N/A'); ?></span>
                </div>
                <div class="detail-row">
                    <span class="label">DS Division:</span>
                    <span class="value"><?php echo htmlspecialchars($rec['ds_division'] ?: 'N/A'); ?></span>
                </div>
                <div class="detail-row">
                    <span class="label">GN Division:</span>
                    <span class="value" style="font-size: 0.8rem;"><?php echo htmlspecialchars($rec['gn_division'] ?: 'N/A'); ?></span>
                </div>
                <div class="detail-row">
                    <span class="label">Approvals:</span>
                    <div class="approval-badges">
                        <?php if (empty($approval_list)): ?>
                            <span class="value">N/A</span>
                        <?php else: ?>
                            <?php foreach($approval_list as $app): ?>
                                <span class="app-badge"><?php echo $app; ?></span>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- CUSTOM FIELDS -->
            <?php if(!empty($custom_fields)): ?>
            <div style="grid-column: 1 / -1;">
                <div class="section-title">⚙️ Additional Field Records</div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0 40px;">
                    <?php foreach($custom_fields as $cf): ?>
                    <div class="detail-row">
                        <span class="label"><?php echo htmlspecialchars($cf['field_label']); ?>:</span>
                        <span class="value"><?php echo htmlspecialchars($cf['field_value']); ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- COMMENTS SECTION -->
            <?php if(!empty($rec['remarks']) || !empty($rec['board_comment'])): ?>
            <div class="full-row">
                <span class="label">📝 Remarks & Comments</span>
                <span class="value"><?php 
                    if($rec['board_comment']) echo "<b>Board Comment:</b> " . htmlspecialchars($rec['board_comment']) . "\n\n";
                    if($rec['remarks']) echo "<b>General Remarks:</b> " . htmlspecialchars($rec['remarks']);
                ?></span>
            </div>
            <?php endif; ?>

            <!-- PHOTO GALLERY -->
            <div style="grid-column: 1 / -1; margin-top: 10px;">
                <div class="section-title">📸 Visual Evidence (Attachment)</div>
                <div class="photo-gallery">
                    <div class="photo-box">
                        <img src="<?php echo $rec['seller_image'] ?: 'assets/img/placeholder.png'; ?>" onerror="this.src='assets/img/placeholder.png'">
                        <label>Seller Photo</label>
                    </div>
                    <div class="photo-box">
                        <img src="<?php echo $rec['image_front'] ?: 'assets/img/placeholder.png'; ?>" onerror="this.src='assets/img/placeholder.png'">
                        <label>Front View</label>
                    </div>
                    <div class="photo-box">
                        <img src="<?php echo $rec['image_side'] ?: 'assets/img/placeholder.png'; ?>" onerror="this.src='assets/img/placeholder.png'">
                        <label>Side View</label>
                    </div>
                    <div class="photo-box">
                        <img src="<?php echo $rec['image_inside'] ?: 'assets/img/placeholder.png'; ?>" onerror="this.src='assets/img/placeholder.png'">
                        <label>Inside View</label>
                    </div>
                </div>
            </div>
        </div>

        <div style="margin-top: 50px; font-size: 0.7rem; color: #94a3b8; text-align: center; border-top: 2px solid #f1f5f9; padding-top: 20px; font-weight: 500;">
            <b>ELECTRONIC SECURITY STATEMENT:</b> This document is a digital representation of the registration data stored in the NLB Seller Map Database. 
            The authenticity can be verified by scanning the QR code above.<br>
            Generated on: <b><?php echo date("F j, Y, g:i a"); ?></b> by <b><?php echo htmlspecialchars($rec['added_by']); ?></b>
        </div>
    </div>

    <div class="controls">
        <button onclick="downloadPDF()" class="btn btn-download">💾 Download Full PDF</button>
        <button onclick="window.print()" class="btn btn-print">🖨️ Print Document</button>
        <a href="dashboard.php" class="btn btn-back">🔙 Dashboard</a>
    </div>

    <script>
        function downloadPDF() {
            const element = document.getElementById('pdf-content');
            const opt = {
                margin: 0.3,
                filename: 'NLB_Full_Report_<?php echo $rec['reg_number']; ?>.pdf',
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { scale: 3, useCORS: true, logging: false },
                jsPDF: { unit: 'in', format: 'a4', orientation: 'portrait' }
            };
            
            // Show loading state
            const btn = document.querySelector('.btn-download');
            const originalText = btn.innerHTML;
            btn.innerHTML = '⏳ Generating...';
            btn.disabled = true;

            html2pdf().set(opt).from(element).save().then(() => {
                btn.innerHTML = originalText;
                btn.disabled = false;
            });
        }
    </script>
</body>
</html>
