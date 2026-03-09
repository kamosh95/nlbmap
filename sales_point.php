<?php
require_once 'includes/security.php';
if (!file_exists('includes/db_config.php')) {
    header("Location: installer.php");
    exit;
}
if (!isset($_SESSION['role'])) {
    header("Location: login.php");
    exit;
}

// Restricted Access: Admin and TM only for Details Entry
if ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'tm') {
    header("Location: dashboard.php");
    exit;
}
require_once 'includes/db_config.php';
require_once 'includes/get_nav.php';



// Fetch System Settings
$enable_location = true;
try {
    $settings = $pdo->query("SELECT setting_key, setting_value FROM settings")->fetchAll(PDO::FETCH_KEY_PAIR);
    $enable_location = ($settings['enable_location'] ?? '1') === '1';
} catch (PDOException $e) { /* settings table might not exist yet */ }

// Fetch custom fields
$custom_fields = $pdo->query("SELECT * FROM custom_fields ORDER BY sort_order ASC, id ASC")->fetchAll();

// Fetch dealers (filtered by TM's assigned districts)
require_once 'ajax/get_dealers_helper.php';
$dealers = get_dealers_for_user($pdo);

$message = '';
$status = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF
    verify_csrf_token($_POST['csrf_token'] ?? '');

    $dealer_code  = $_POST['dealer_code'] ?? '';
    $agent_code   = $_POST['agent_code'] ?? '';
    $seller_code  = $_POST['seller_code'] ?? '';
    $seller_name  = $_POST['seller_name'] ?? '';
    $sales_method = 'Sales Point'; // Fixed for Sales Point page
    $counter_state= $_POST['counter_state'] ?? 'NLB';
    $location_link= $_POST['location_link'] ?? '';

    $upload_dir  = 'uploads/';
    $image_paths = ['front' => '', 'side' => '', 'inside' => '', 'seller' => ''];

    $valid = true;
    foreach (['front', 'seller'] as $key) {
        if (isset($_FILES['image_' . $key]) && $_FILES['image_' . $key]['error'] === UPLOAD_ERR_OK) {
            $tmp_name  = $_FILES['image_' . $key]['tmp_name'];
            $orig_name = $_FILES['image_' . $key]['name'];
            
            // Validate File
            if (!is_allowed_file($orig_name, $tmp_name)) {
                $valid = false;
                $message = "Security Alert: Invalid file type detected for image_$key.";
                $status  = 'error';
                break;
            }

            $extension = pathinfo($orig_name, PATHINFO_EXTENSION);
            
            // Re-name logic
            if ($key === 'seller' && !empty($_POST['nic_new'])) {
                $base_name = 'NIC_' . preg_replace('/[^a-zA-Z0-9]/', '', $_POST['nic_new']);
            } else {
                $base_name = preg_replace('/[^a-zA-Z0-9]/', '_', $seller_code);
            }
            
            $file_name = $base_name . '_' . $key . '_' . uniqid() . '.' . $extension;
            $target_path = $upload_dir . $file_name;

            if (move_uploaded_file($tmp_name, $target_path)) {
                $image_paths[$key] = $target_path;
            } else {
                $valid = false;
                $message = "Image upload failed.";
                $status  = 'error';
                break;
            }
        }
    }

    if ($valid) {
        try {
            $is_draft = isset($_POST['is_draft']) && $_POST['is_draft'] == '1';
            $final_status = $is_draft ? 'Incomplete' : 'Active';

            $stmt = $pdo->prepare("INSERT INTO counters (dealer_code, agent_code, seller_code, seller_name, nic_type, nic_old, nic_new, counter_state, seller_image, birthday, sales_method, location_link, province, district, ds_division, gn_division, image_front, image_side, image_inside, added_by, address, phone, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $dealer_code, $agent_code, $seller_code, $seller_name,
                $_POST['nic_type'] ?? '', $_POST['nic_old'] ?? '', $_POST['nic_new'] ?? '', 
                $counter_state,
                $image_paths['seller'],
                !empty($_POST['birthday']) ? $_POST['birthday'] : null,
                $sales_method, $location_link,
                $_POST['province_text'] ?? '', $_POST['district_text'] ?? '', $_POST['ds_division_text'] ?? '', (is_array($_POST['gn_division'] ?? '') ? implode(', ', $_POST['gn_division']) : ($_POST['gn_division'] ?? '')),
                $image_paths['front'], $image_paths['side'], $image_paths['inside'],
                $_SESSION['username'],
                $_POST['address'] ?? '',
                $_POST['phone'] ?? '',
                $final_status
            ]);
            $new_counter_id = $pdo->lastInsertId();
            
            // Generate Running Number: SP + Year + ID padded to 5 digits
            $reg_number = "SP" . date('Y') . str_pad($new_counter_id, 5, '0', STR_PAD_LEFT);
            $update_reg = $pdo->prepare("UPDATE counters SET reg_number = ? WHERE id = ?");
            $update_reg->execute([$reg_number, $new_counter_id]);

            // Save custom field values
            foreach ($custom_fields as $cf) {
                $cf_val = $_POST['cf_' . $cf['field_name']] ?? '';
                $ins = $pdo->prepare("INSERT INTO counter_custom_values (counter_id, field_id, field_value) VALUES (?, ?, ?)");
                $ins->execute([$new_counter_id, $cf['id'], $cf_val]);
            }

            // Success Message with QR Code
            $current_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]" . dirname($_SERVER['PHP_SELF']);
            $edit_url = $current_url . "/view_public.php?id=" . $new_counter_id;
            $qr_api = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" . urlencode($edit_url);

            $message = "
                <div class='success-badge'>
                    <div style='font-size: 2rem; margin-bottom: 10px;'>✅</div>
                    <div style='font-weight: 700; font-size: 1.2rem; margin-bottom: 5px;'>Sales Point Registration Successful!</div>
                    <div style='background: rgba(255,255,255,0.1); padding: 10px; border-radius: 8px; margin: 15px 0;'>
                        <div style='font-size: 0.8rem; opacity: 0.8;'>REGISTERED NUMBER:</div>
                        <div style='font-size: 1.5rem; font-weight: 800; color: var(--secondary-color); font-family: monospace;'>$reg_number</div>
                    </div>
                    <div style='background: white; padding: 10px; display: inline-block; border-radius: 12px; margin-bottom: 15px;'>
                        <img src='$qr_api' alt='Registration QR' style='display: block;'>
                    </div>
                    <div style='font-size: 0.8rem; opacity: 0.7; margin-bottom: 20px;'>Scan this QR to view or update details in the future.</div>
                    <div style='display: flex; gap: 10px; justify-content: center;'>
                        <button onclick='window.print()' class='btn-action' style='background: var(--secondary-color); color: #000; border: none; padding: 8px 15px; border-radius: 8px; font-weight: 700; cursor: pointer;'>Print Receipt</button>
                        <a href='sales_point.php' class='btn-action' style='background: rgba(255,255,255,0.1); color: #fff; text-decoration: none; padding: 8px 15px; border-radius: 8px; font-weight: 600;'>Add New</a>
                    </div>
                </div>";
            $status  = 'success';
            log_activity($pdo, "Added Sales Point", "Reg: $reg_number, Name: $seller_name", "seller");
        } catch (PDOException $e) {
            $message = "Database error: " . $e->getMessage();
            $status  = 'error';
        }
    }
}

// ── Inline Edit: Search Record ──
$edit_record = null;
$edit_search_msg = '';
if (isset($_GET['search_seller'])) {
    $sq = trim($_GET['search_seller']);
    $stmt = $pdo->prepare("SELECT * FROM counters WHERE (seller_code = ? OR nic_old = ? OR nic_new = ?) AND added_by = ?");
    $stmt->execute([$sq, $sq, $sq, $_SESSION['username']]);
    $edit_record = $stmt->fetch();
    if (!$edit_record) {
        $edit_search_msg = "❌ No record found for Code/NIC \"" . htmlspecialchars($sq) . "\" added by you.";
    }
}

// ── Inline Edit: Save Changes ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['inline_edit'])) {
    verify_csrf_token($_POST['csrf_token'] ?? '');

    $edit_id = (int)($_POST['edit_id'] ?? 0);
    // Security: only allow editing own records
    $check = $pdo->prepare("SELECT id, seller_image, image_front, image_side, image_inside FROM counters WHERE id = ? AND added_by = ?");
    $check->execute([$edit_id, $_SESSION['username']]);
    $existing = $check->fetch();

    if ($existing) {
        $upload_dir = 'uploads/';
        $imgs = [
            'seller' => $existing['seller_image'],
            'front'  => $existing['image_front'],
            'side'   => $existing['image_side'],
            'inside' => $existing['image_inside'],
        ];
        foreach (['front','side','inside','seller'] as $key) {
            if (isset($_FILES['ie_image_'.$key]) && $_FILES['ie_image_'.$key]['error'] === UPLOAD_ERR_OK) {
                $tmp  = $_FILES['ie_image_'.$key]['tmp_name'];
                $orig = $_FILES['ie_image_'.$key]['name'];
                if (is_allowed_file($orig, $tmp)) {
                    $ext  = pathinfo($orig, PATHINFO_EXTENSION);
                    $fname = 'EDIT_' . preg_replace('/[^a-zA-Z0-9]/', '_', $_POST['ie_seller_code'] ?? 'X') . "_$key\_" . uniqid() . ".$ext";
                    if (move_uploaded_file($tmp, $upload_dir . $fname)) {
                        $imgs[$key] = $upload_dir . $fname;
                    }
                }
            }
        }

        try {
            $upd = $pdo->prepare("UPDATE counters SET
                dealer_code=?, agent_code=?, seller_name=?,
                nic_type=?, nic_old=?, nic_new=?,
                counter_state=?, seller_image=?, birthday=?,
                sales_method=?, location_link=?,
                province=?, district=?, ds_division=?, gn_division=?,
                image_front=?, image_side=?, image_inside=?,
                address=?
                WHERE id=? AND added_by=?");
            $upd->execute([
                $_POST['ie_dealer_code'] ?? '', $_POST['ie_agent_code'] ?? '', $_POST['ie_seller_name'] ?? '',
                $_POST['ie_nic_type'] ?? '', $_POST['ie_nic_old'] ?? '', $_POST['ie_nic_new'] ?? '',
                $_POST['ie_counter_state'] ?? 'NLB',
                $imgs['seller'],
                !empty($_POST['ie_birthday']) ? $_POST['ie_birthday'] : null,
                $_POST['ie_sales_method'] ?? '', $_POST['ie_location_link'] ?? '',
                $_POST['ie_province'] ?? '', $_POST['ie_district'] ?? '', $_POST['ie_ds_division'] ?? '', (is_array($_POST['ie_gn_division'] ?? '') ? implode(', ', $_POST['ie_gn_division']) : ($_POST['ie_gn_division'] ?? '')),
                $imgs['front'], $imgs['side'], $imgs['inside'],
                $_POST['ie_address'] ?? '',
                $edit_id, $_SESSION['username']
            ]);

            // Update custom fields
            foreach ($custom_fields as $cf) {
                $cf_val = $_POST['ie_cf_' . $cf['field_name']] ?? '';
                $exists_cv = $pdo->prepare("SELECT id FROM counter_custom_values WHERE counter_id=? AND field_id=?");
                $exists_cv->execute([$edit_id, $cf['id']]);
                if ($exists_cv->fetch()) {
                    $pdo->prepare("UPDATE counter_custom_values SET field_value=? WHERE counter_id=? AND field_id=?")->execute([$cf_val, $edit_id, $cf['id']]);
                } else {
                    $pdo->prepare("INSERT INTO counter_custom_values (counter_id, field_id, field_value) VALUES (?,?,?)")->execute([$edit_id, $cf['id'], $cf_val]);
                }
            }

            $message = "✅ Record updated successfully!";
            $status  = 'success';
            // Reload the updated record to keep shown
            $check2 = $pdo->prepare("SELECT * FROM counters WHERE id=?");
            $check2->execute([$edit_id]);
            $edit_record = $check2->fetch();
        } catch (PDOException $e) {
            $message = "Error: " . $e->getMessage();
            $status  = 'error';
        }
    } else {
        $message = "❌ Permission denied. You can only edit records you added.";
        $status  = 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Point Entry - NLB Seller Map Portal</title>
    <link rel="manifest" href="assets/manifest.json">
    <meta name="theme-color" content="#0072ff">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="assets/img/logo1.png">
    <link rel="stylesheet" href="assets/css/style.css">
    <!-- Include html2pdf.js -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <style>
        /* ── Desktop Two-Panel Layout ── */
        .entry-layout {
            display: grid;
            grid-template-columns: 1fr 380px;
            gap: 2rem;
            align-items: start;
        }

        /* Left: Form Panel */
        .form-panel {
            background: var(--card-bg);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            padding: 2rem;
        }

        .form-panel h2 {
            font-size: 1.2rem;
            color: var(--secondary-color);
            margin-bottom: 1.5rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid var(--glass-border);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .field-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        /* Right: Info Panel */
        .info-panel {
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
        }

        .info-card {
            background: var(--card-bg);
            border: 1px solid var(--glass-border);
            border-radius: 18px;
            padding: 1.5rem;
        }

        .info-card h3 {
            font-size: 0.9rem;
            color: var(--secondary-color);
            margin-bottom: 1rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Live Preview Card */
        .preview-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            font-size: 0.85rem;
        }
        .preview-row:last-child { border-bottom: none; }
        .preview-label { color: var(--text-muted); }
        .preview-val { color: var(--text-main); font-weight: 600; text-align: right; max-width: 200px; word-break: break-word; }
        .preview-val.empty { color: rgba(255,255,255,0.2); font-weight: 400; font-style: italic; }
        
        .btn-action { width: 100%; padding: 0.8rem; border-radius: 12px; border: none; font-family: 'Outfit', sans-serif; font-weight: 600; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; transition: 0.3s; margin-top: 0.5rem; }
        .btn-pdf { background: #ff4444; color: white; }
        .btn-whatsapp { background: #25D366; color: white; }

        /* Image preview thumbnails */
        .img-preview-row {
            display: flex;
            gap: 8px;
            margin-top: 0.5rem;
        }
        .img-thumb {
            width: 72px;
            height: 72px;
            border-radius: 10px;
            object-fit: cover;
            border: 2px solid var(--glass-border);
            display: none;
        }
        .img-thumb-placeholder {
            width: 72px;
            height: 72px;
            border-radius: 10px;
            border: 2px dashed rgba(255,255,255,0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            color: rgba(255,255,255,0.15);
            flex-shrink: 0;
        }

        /* Stat bar */
        .stat-bar {
            display: flex;
            gap: 10px;
        }
        .stat-item {
            flex: 1;
            text-align: center;
            background: var(--nav-bg);
            border-radius: 12px;
            padding: 0.75rem 0.5rem;
        }
        .stat-num {
            font-size: 1.4rem;
            font-weight: 700;
            background: var(--gold-gradient);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .stat-label {
            font-size: 0.7rem;
            color: var(--text-muted);
            margin-top: 2px;
        }

        /* Tips list */
        .tip-list { list-style: none; }
        .tip-list li {
            font-size: 0.82rem;
            color: var(--text-muted);
            padding: 6px 0;
            border-bottom: 1px solid rgba(255,255,255,0.04);
            display: flex;
            gap: 8px;
            align-items: flex-start;
            line-height: 1.5;
        }
        .tip-list li:last-child { border-bottom: none; }
        .tip-list li span { flex-shrink: 0; }



        /* Radio pills */
        .radio-group { display: flex; gap: 10px; }
        .radio-pill {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 0.75rem 1rem;
            background: rgba(255,255,255,0.04);
            border: 1.5px solid rgba(255,255,255,0.08);
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 0.9rem;
        }
        .radio-pill input { display: none; }
        .radio-pill:has(input:checked) {
            background: rgba(0, 114, 255, 0.15);
            border-color: var(--secondary-color);
            color: var(--secondary-color);
            font-weight: 600;
        }

        /* Mobile: stack panels */
        @media (max-width: 900px) {
            .entry-layout { grid-template-columns: 1fr; }
            .info-panel { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        }
        @media (max-width: 580px) {
            .info-panel { grid-template-columns: 1fr; }
            .field-row { grid-template-columns: 1fr; }
            .stat-bar { flex-wrap: wrap; }
        }
    </style>
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => { navigator.serviceWorker.register('sw.js'); });
        }
    </script>
</head>
<body>
<div class="container wide">

    <!-- Page Header -->
    <div class="nav-bar" style="margin-bottom: 2rem;">
        <div class="nav-brand">
            <img src="assets/img/Logo.png" alt="NLB Logo">
            <div>
                <h1>NLB Seller Map Portal</h1>
                <p style="color: var(--text-muted); font-size: 0.75rem; margin: 0; opacity: 0.8;">
                    Ticket Counter Data Collection System &nbsp;·&nbsp; Logged in as <span class="role-badge badge-<?php echo $_SESSION['role']; ?>" style="padding: 2px 8px; font-size: 0.65rem;"><?php echo $_SESSION['username']; ?></span>
                </p>
            </div>
        </div>
        <?php echo render_nav($pdo, $_SESSION['role']); ?>
    </div>

    <?php if ($message): ?>
        <div class="message <?php echo $status; ?>" style="display: block; margin-bottom: 1.5rem;">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <!-- ── Quick Edit Search Bar ── -->
    <div style="margin-bottom: 1.25rem; display:flex; justify-content:flex-end;">
        <form method="GET" action="" style="display:flex; gap:0.5rem; align-items:center; margin:0; padding: 0.5rem 0.85rem; background:var(--card-bg); border:1px solid var(--glass-border); border-radius:12px; backdrop-filter:blur(10px);">
            <span style="font-size:0.8rem; font-weight:600; color:var(--text-muted); white-space:nowrap;">🔍</span>
            <input type="text" name="search_seller" value="<?php echo htmlspecialchars($_GET['search_seller'] ?? ''); ?>"
                placeholder="Search by Code or NIC…"
                style="width:260px; padding:0.4rem 0.8rem; background:var(--input-bg); border:1.5px solid var(--glass-border); border-radius:8px; color:var(--text-main); font-family:'Outfit',sans-serif; font-size:0.82rem;">
            <button type="submit" style="padding:0.4rem 0.9rem; background:var(--secondary-color); color:#000; font-weight:700; border:none; border-radius:8px; cursor:pointer; font-family:'Outfit',sans-serif; font-size:0.82rem; white-space:nowrap;">Search</button>
            <?php if (isset($_GET['search_seller'])): ?>
            <a href="index.php" style="padding:0.4rem 0.6rem; background:rgba(0,0,0,0.06); color:var(--text-muted); font-weight:600; border-radius:8px; text-decoration:none; font-size:0.8rem;">✖</a>
            <?php endif; ?>
        </form>
    </div>

    <?php if ($edit_search_msg): ?>
        <div class="message error" style="display:block; margin-bottom:1.5rem;"><?php echo $edit_search_msg; ?></div>
    <?php endif; ?>

    <?php if ($edit_record): ?>
    <!-- ── Inline Edit Panel ── -->
    <div class="card" style="margin-bottom: 2rem; border: 2px solid var(--secondary-color); padding: 1.5rem;">
        <h3 style="margin:0 0 1.25rem; color:var(--secondary-color); display:flex; align-items:center; gap:8px;">✏️ Editing Record: <span style="color:var(--text-main); font-style:italic;"><?php echo htmlspecialchars($edit_record['seller_code']); ?></span></h3>
        <form method="POST" action="index.php?search_seller=<?php echo urlencode($edit_record['seller_code']); ?>" enctype="multipart/form-data">
            <?php csrf_input(); ?>
            <input type="hidden" name="inline_edit" value="1">
            <input type="hidden" name="edit_id" value="<?php echo $edit_record['id']; ?>">
            <input type="hidden" name="ie_seller_code" value="<?php echo htmlspecialchars($edit_record['seller_code']); ?>">

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem; margin-bottom:1rem;">
                <div class="form-group" style="margin:0;">
                    <label>Counter State (Board)</label>
                    <select name="ie_counter_state">
                        <option value="NLB" <?php echo ($edit_record['counter_state'] ?? 'NLB') === 'NLB' ? 'selected' : ''; ?>>💙 NLB</option>
                        <option value="DLB" <?php echo ($edit_record['counter_state'] ?? '') === 'DLB' ? 'selected' : ''; ?>>❤️ DLB</option>
                    </select>
                </div>
                <div class="form-group" style="margin:0;">
                    <label>Dealer Code</label>
                    <select name="ie_dealer_code" id="ie_dealer_code" onchange="loadAgents('ie_dealer_code', 'ie_agent_code')">
                        <option value="">-- Select Dealer --</option>
                        <?php foreach ($dealers as $d): ?>
                            <option value="<?php echo htmlspecialchars($d['dealer_code']); ?>" <?php echo $edit_record['dealer_code'] === $d['dealer_code'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($d['dealer_code'] . ' - ' . $d['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="margin:0;">
                    <label>Agent Code</label>
                    <select name="ie_agent_code" id="ie_agent_code">
                        <option value="<?php echo htmlspecialchars($edit_record['agent_code']); ?>" selected><?php echo htmlspecialchars($edit_record['agent_code']); ?></option>
                    </select>
                </div>
                <div class="form-group" style="margin:0;">
                    <label>Seller Code (Read-only)</label>
                    <input type="text" value="<?php echo htmlspecialchars($edit_record['seller_code']); ?>" readonly style="opacity:0.5; cursor:not-allowed;">
                </div>
                <div class="form-group" style="margin:0;">
                    <label>Seller Name</label>
                    <input type="text" name="ie_seller_name" value="<?php echo htmlspecialchars($edit_record['seller_name']); ?>">
                </div>
                <div class="form-group" style="margin:0;">
                    <label>NIC Type</label>
                    <select name="ie_nic_type">
                        <option value="old" <?php echo $edit_record['nic_type']==='old'?'selected':''; ?>>Old NIC</option>
                        <option value="new" <?php echo $edit_record['nic_type']==='new'?'selected':''; ?>>New NIC</option>
                    </select>
                </div>
                <div class="form-group" style="margin:0;">
                    <label>NIC (Old Format)</label>
                    <input type="text" name="ie_nic_old" value="<?php echo htmlspecialchars($edit_record['nic_old']); ?>">
                </div>
                <div class="form-group" style="margin:0;">
                    <label>NIC (New Format)</label>
                    <input type="text" name="ie_nic_new" value="<?php echo htmlspecialchars($edit_record['nic_new']); ?>">
                </div>
                <div class="form-group" style="margin:0;">
                    <label>🎂 Birthday</label>
                    <input type="date" name="ie_birthday" value="<?php echo htmlspecialchars($edit_record['birthday'] ?? ''); ?>" style="width:100%; padding:0.75rem 1rem; background:var(--nav-bg); border:2px solid var(--glass-border); border-radius:12px; color:var(--text-main); font-family:'Outfit',sans-serif;">
                </div>
                <input type="hidden" name="ie_sales_method" value="Sales Point">
                <div class="form-group" style="margin:0;">
                    <label>📍 Location Link</label>
                    <input type="text" name="ie_location_link" value="<?php echo htmlspecialchars($edit_record['location_link']); ?>" placeholder="Google Maps URL">
                </div>
                <?php if ($enable_location): ?>
                <div class="form-group" style="margin:0;">
                    <label>Province</label>
                    <input type="text" name="ie_province" value="<?php echo htmlspecialchars($edit_record['province'] ?? ''); ?>">
                </div>
                <div class="form-group" style="margin:0;">
                    <label>District</label>
                    <input type="text" name="ie_district" value="<?php echo htmlspecialchars($edit_record['district'] ?? ''); ?>">
                </div>
                <div class="form-group" style="margin:0;">
                    <label>DS Division</label>
                    <input type="text" name="ie_ds_division" value="<?php echo htmlspecialchars($edit_record['ds_division'] ?? ''); ?>">
                </div>
                <div class="form-group" style="margin:0;">
                    <label>GN Division(s)</label>
                    <input type="text" name="ie_gn_division" value="<?php echo htmlspecialchars($edit_record['gn_division'] ?? ''); ?>" placeholder="Comma separated list">
                </div>
                <?php endif; ?>
            </div>

            <div class="form-group" style="margin-bottom: 1rem;">
                <label>🏠 Address (ලිපිනය)</label>
                <textarea name="ie_address" placeholder="Enter Full Address"><?php echo htmlspecialchars($edit_record['address'] ?? ''); ?></textarea>
            </div>

            <?php if (!empty($custom_fields)):
                $cv_stmt = $pdo->prepare("SELECT field_id, field_value FROM counter_custom_values WHERE counter_id=?");
                $cv_stmt->execute([$edit_record['id']]);
                $cv_map = [];
                foreach ($cv_stmt->fetchAll() as $row) $cv_map[$row['field_id']] = $row['field_value'];
            ?>
            <div style="margin-bottom:1rem; border-top:1px solid var(--glass-border); padding-top:1rem;">
                <p style="font-size:0.8rem; color:var(--text-muted); margin-bottom:0.75rem;">⚙️ Additional Fields</p>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
                <?php foreach ($custom_fields as $cf): ?>
                    <div class="form-group" style="margin:0; <?php echo $cf['field_type']==='textarea' ? 'grid-column:1/-1;' : ''; ?>">
                        <label><?php echo htmlspecialchars($cf['field_label']); ?></label>
                        <?php if ($cf['field_type'] === 'textarea'): ?>
                        <textarea name="ie_cf_<?php echo $cf['field_name']; ?>"><?php echo htmlspecialchars($cv_map[$cf['id']] ?? ''); ?></textarea>
                        <?php else: ?>
                        <input type="<?php echo $cf['field_type']; ?>" name="ie_cf_<?php echo $cf['field_name']; ?>" value="<?php echo htmlspecialchars($cv_map[$cf['id']] ?? ''); ?>">
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Image uploads (optional replacement) -->
            <div style="margin-bottom:1rem; border-top:1px solid var(--glass-border); padding-top:1rem;">
                <p style="font-size:0.8rem; color:var(--text-muted); margin-bottom:0.75rem;">📷 Replace Images (optional — leave empty to keep existing)</p>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem;">
                    <?php foreach (['seller'=>'Seller Photo','front'=>'Front View','side'=>'Side View','inside'=>'Inside View'] as $ik=>$il): ?>
                    <div class="form-group" style="margin:0;">
                        <label><?php echo $il; ?></label>
                        <input type="file" name="ie_image_<?php echo $ik; ?>" accept="image/*" style="padding:0.4rem; background:rgba(255,255,255,0.05); border:1.5px dashed var(--glass-border); border-radius:10px; width:100%; color:var(--text-main);">
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div style="display:flex; gap:1rem; margin-top:0.5rem;">
                <button type="submit" style="flex:1; padding:0.9rem; background:linear-gradient(135deg,#e6b800,#cc8400); color:#000; font-weight:700; border:none; border-radius:12px; cursor:pointer; font-size:1rem; font-family:'Outfit',sans-serif;">
                    💾 Save Changes
                </button>
                <a href="index.php" style="padding:0.9rem 1.5rem; background:var(--glass-border); color:var(--text-main); font-weight:600; border-radius:12px; text-decoration:none; display:flex; align-items:center; font-family:'Outfit',sans-serif;">Cancel</a>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <!-- Main Two-Panel Layout -->
    <div class="entry-layout">

        <!-- ── Left: Form Panel ── -->
        <div class="form-panel">
            <h2>📍 Sales Point Details Entry</h2>
            <form action="" method="POST" enctype="multipart/form-data" id="entryForm">
                <?php 
                csrf_input(); 
                
                // Helper to render custom fields by section
                function render_cf_group($fields) {
                    $in_row = false; $row_count = 0;
                    foreach ($fields as $cf) {
                        $cf_id = 'cf_' . $cf['field_name'];
                        $is_tel = ($cf['field_type'] === 'tel');
                        $is_textarea = ($cf['field_type'] === 'textarea');
                        $is_full = ($is_tel || $is_textarea);
                        
                        if ($is_full) {
                            if ($in_row) { echo '</div>'; $in_row = false; $row_count = 0; }
                        } else {
                            if (!$in_row) { echo '<div class="field-row">'; $in_row = true; $row_count = 0; }
                            elseif ($row_count >= 2) { echo '</div><div class="field-row">'; $row_count = 0; }
                        }
                        $row_count++;
                        
                        $prefix = '';
                        $cf_default = $cf['default_value'] ?? '';
                        if ($is_tel && preg_match('/^(\+\d{1,4})/', $cf_default, $m)) { $prefix = $m[1]; }
                        $flags = ['+94'=>'🇱🇰','+1'=>'🇺🇸','+44'=>'🇬🇧','+91'=>'🇮🇳'];
                        $flag = $flags[$prefix] ?? '📞';
                        ?>
                        <div class="form-group custom-field-group" data-visible-for="<?php echo htmlspecialchars($cf['visible_for'] ?? 'all'); ?>">
                            <label for="<?php echo $cf_id; ?>">
                                <?php echo htmlspecialchars($cf['field_label']); ?>
                                <?php if ($cf['is_required']): ?><span style="color:#f87171;">*</span><?php endif; ?>
                            </label>
                            <?php if ($is_tel): ?>
                                <div class="phone-input-wrap">
                                    <div class="phone-prefix"><span class="flag"><?php echo $flag; ?></span></div>
                                    <input type="tel" id="<?php echo $cf_id; ?>" name="<?php echo $cf_id; ?>" value="<?php echo htmlspecialchars($cf_default); ?>" required oninput="updatePreview()">
                                </div>
                            <?php elseif ($is_textarea): ?>
                                <textarea id="<?php echo $cf_id; ?>" name="<?php echo $cf_id; ?>" required oninput="updatePreview()"><?php echo htmlspecialchars($cf_default); ?></textarea>
                            <?php elseif ($cf['field_type'] === 'radio'): 
                                $opts = explode(',', $cf['field_options'] ?? ''); ?>
                                <div class="radio-group" style="grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));">
                                    <?php foreach ($opts as $opt): $opt = trim($opt); if (!$opt) continue; $safe_opt = htmlspecialchars($opt); $opt_id = $cf_id . '_' . preg_replace('/[^a-z0-9]/', '', strtolower($opt)); ?>
                                        <input type="radio" id="<?php echo $opt_id; ?>" name="<?php echo $cf_id; ?>" value="<?php echo $safe_opt; ?>" checked required onchange="updatePreview()">
                                        <label for="<?php echo $opt_id; ?>" class="radio-pill"><span><?php echo $safe_opt; ?></span></label>
                                    <?php endforeach; ?>
                                </div>
                            <?php elseif ($cf['field_type'] === 'checkbox'): ?>
                                <div style="display: flex; align-items: center; gap: 10px; padding: 10px 14px; background: rgba(255,255,255,0.03); border: 1px solid var(--glass-border); border-radius: 12px; cursor: pointer;" onclick="document.getElementById('<?php echo $cf_id; ?>').click()">
                                    <input type="checkbox" id="<?php echo $cf_id; ?>" name="<?php echo $cf_id; ?>" value="1" <?php echo ($cf_default === '1' || strtolower($cf_default) === 'checked') ? 'checked' : ''; ?> onchange="updatePreview()" style="width: 20px; height: 20px; accent-color: var(--secondary-color); cursor: pointer;">
                                    <span style="font-size: 0.9rem; color: var(--text-main);"><?php echo htmlspecialchars($cf['placeholder'] ?: 'Yes / Enabled'); ?></span>
                                </div>
                            <?php else: ?>
                                <input type="<?php echo htmlspecialchars($cf['field_type']); ?>" id="<?php echo $cf_id; ?>" name="<?php echo $cf_id; ?>" value="<?php echo htmlspecialchars($cf_default); ?>" required oninput="updatePreview()">
                            <?php endif; ?>
                        </div>
                        <?php
                    }
                    if ($in_row) echo '</div>';
                }

                $main_fields = array_filter($custom_fields, fn($f) => ($f['display_section'] ?? 'additional') === 'main');
                $additional_fields = array_filter($custom_fields, fn($f) => ($f['display_section'] ?? 'additional') === 'additional');
                ?>

                <div class="field-row">
                    <div class="form-group">
                        <label for="dealer_code">Dealer Code</label>
                        <select id="dealer_code" name="dealer_code" required onchange="loadAgents('dealer_code', 'agent_code'); updatePreview()">
                            <option value="">-- Select Dealer --</option>
                            <?php foreach ($dealers as $d): ?>
                                <option value="<?php echo htmlspecialchars($d['dealer_code']); ?>">
                                    <?php echo htmlspecialchars($d['dealer_code'] . ' - ' . $d['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="agent_code">Agent Code</label>
                        <select id="agent_code" name="agent_code" required onchange="updatePreview()">
                            <option value="">-- Select Dealer First --</option>
                        </select>
                    </div>
                </div>

                <div class="field-row">
                    <div class="form-group">
                        <label for="seller_code">Seller Code</label>
                        <input type="text" id="seller_code" name="seller_code" placeholder="Generated from NIC" readonly style="background: rgba(255,255,255,0.02); cursor: not-allowed; border-color: rgba(255,255,255,0.05);" required oninput="updatePreview()">
                    </div>
                    <div class="form-group">
                        <label for="seller_name">Seller Name</label>
                        <input type="text" id="seller_name" name="seller_name" placeholder="Full name" required oninput="updatePreview()">
                    </div>
                </div>

                <div class="field-row">
                    <div class="form-group">
                        <label>NIC Type</label>
                        <div class="radio-group" style="margin-top: 5px;">
                            <input type="radio" id="nt_old" name="nic_type" value="old" checked onchange="handleNicTypeChange(); updatePreview();">
                            <label for="nt_old" class="radio-pill">
                                <span>📄 Old NIC</span>
                            </label>

                            <input type="radio" id="nt_new" name="nic_type" value="new" onchange="handleNicTypeChange(); updatePreview();">
                            <label for="nt_new" class="radio-pill">
                                <span>🪪 New NIC</span>
                            </label>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="nic_number" id="nic_label">NIC Number</label>
                        <input type="text" id="nic_number" name="nic_input" placeholder="e.g. 905123456V" required oninput="handleNicInput(); updatePreview();">
                    </div>
                </div>



                <div class="field-row">
                    <div class="form-group">
                        <label for="nic_new_display">Generated New NIC</label>
                        <input type="text" id="nic_new_display" name="nic_new" placeholder="Auto-generated" readonly style="background: rgba(255,255,255,0.02); cursor: not-allowed; border-color: rgba(255,255,255,0.05);">
                        <input type="hidden" id="nic_old_val" name="nic_old">
                    </div>
                    <div class="form-group">
                        <label for="birthday">🎂 Birthday</label>
                        <div style="position: relative;">
                            <input type="date" id="birthday" name="birthday" required oninput="updatePreview()" style="width: 100%; padding: 0.75rem 1rem; background: var(--nav-bg); border: 2px solid var(--glass-border); border-radius: 12px; color: var(--text-main); font-weight: 600; font-family: 'Outfit', sans-serif; transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
                            <div id="bday-modern-hint" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); font-size: 0.8rem; color: var(--text-muted); pointer-events: none;">Selected</div>
                        </div>
                    </div>
                </div>
                <?php if ($enable_location): ?>
                <div class="field-row">
                    <div class="form-group">
                        <label for="province">Province Name (පළාත)</label>
                        <select id="province" name="province" required onchange="loadDistricts(); updatePreview()" style="width: 100%; padding: 0.75rem 1rem; background: var(--nav-bg); border: 2px solid var(--glass-border); border-radius: 12px; color: var(--text-main); font-weight: 600; font-family: 'Outfit', sans-serif; transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
                            <option value="">-- Select Province --</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="district">District Name (දිස්ත්‍රික්කය)</label>
                        <select id="district" name="district" required onchange="loadDSDivisions(); updatePreview()" disabled style="width: 100%; padding: 0.75rem 1rem; background: var(--nav-bg); border: 2px solid var(--glass-border); border-radius: 12px; color: var(--text-main); font-weight: 600; font-family: 'Outfit', sans-serif; transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
                            <option value="">-- Select District --</option>
                        </select>
                    </div>
                </div>

                <div class="field-row">
                    <div class="form-group">
                        <label for="ds_division">DS Division Name (ප්‍රාදේශීය ලේකම් කොට්ඨාසය)</label>
                        <select id="ds_division" name="ds_division" required onchange="loadGNDivisions(); updatePreview()" disabled style="width: 100%; padding: 0.75rem 1rem; background: var(--nav-bg); border: 2px solid var(--glass-border); border-radius: 12px; color: var(--text-main); font-weight: 600; font-family: 'Outfit', sans-serif; transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
                            <option value="">-- Select DS Division --</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="gn_division">GN Division Name (ග්‍රාම නිලධාරී කොට්ඨාසය)</label>
                        <select id="gn_division" name="gn_division" required onchange="updatePreview()" disabled style="width: 100%; padding: 0.75rem 1rem; background: var(--nav-bg); border: 2px solid var(--glass-border); border-radius: 12px; color: var(--text-main); font-weight: 600; font-family: 'Outfit', sans-serif; transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
                            <option value="">-- Select GN Division --</option>
                        </select>
                        <input type="hidden" id="province_text" name="province_text">
                        <input type="hidden" id="district_text" name="district_text">
                        <input type="hidden" id="ds_division_text" name="ds_division_text">
                    </div>
                </div>
                <?php endif; ?>

                <?php if (!empty($main_fields)): ?>
                <div style="margin-top:0.5rem; padding-top:1rem; border-top:1px solid var(--glass-border);">
                    <?php render_cf_group($main_fields); ?>
                </div>
                <?php endif; ?>

                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label for="address">🏠 Address (ලිපිනය) *</label>
                    <textarea id="address" name="address" required placeholder="Enter Full Address" oninput="updatePreview()"></textarea>
                </div>



                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label for="phone">📞 Telephone No (දුරකථන අංකය)</label>
                    <div class="phone-input-wrap">
                        <div class="phone-prefix"><span class="flag">🇱🇰</span></div>
                        <input type="tel" id="phone" name="phone" placeholder="07X XXX XXXX" oninput="updatePreview()" pattern="[0-9]{9,12}">
                    </div>
                </div>

                <input type="hidden" name="sales_method" value="Sales Point">

                <div style="margin-top:1.5rem;">
                    <?php if (!empty($additional_fields)): ?>
                    <p style="font-size:0.8rem; color:var(--text-muted); margin-bottom:1rem;">⚙️ Additional Fields</p>
                    <?php render_cf_group($additional_fields); ?>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="location_link">📍 Location Link (Google Maps) *</label>
                    <div style="display: flex; gap: 10px;">
                        <input type="text" id="location_link" name="location_link" required placeholder="Paste Google Maps link here" oninput="updatePreview()" style="flex: 1;">
                        <button type="button" onclick="getLocationLink()" style="background: rgba(0, 212, 255, 0.1); border: 1px solid rgba(0, 212, 255, 0.25); color: #00d4ff; border-radius: 12px; padding: 0 15px; cursor: pointer; font-weight: 600; white-space: nowrap; transition: 0.3s;" onmouseover="this.style.background='rgba(0, 212, 255, 0.2)'" onmouseout="this.style.background='rgba(0, 212, 255, 0.1)'" title="Get Current GPS Location">📍 Get GPS</button>
                        <a href="https://maps.google.com" target="_blank" style="background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); color: var(--text-main); border-radius: 12px; padding: 0 15px; text-decoration: none; display: flex; align-items: center; cursor: pointer; font-weight: 600; transition: 0.3s;" onmouseover="this.style.background='rgba(255, 255, 255, 0.1)'" onmouseout="this.style.background='rgba(255, 255, 255, 0.05)'" title="Open Google Maps manually">🗺️ Maps</a>
                    </div>
                    <p id="location_status" style="font-size: 0.75rem; color: var(--text-muted); margin-top: 5px;"></p>
                </div>



                <div class="form-group">
                    <label>📷 Counter & Seller Images</label>
                    <div class="upload-list">
                        <div class="upload-item" style="border: 1px dashed var(--secondary-color); background: rgba(0,114,255,0.03);" onclick="document.getElementById('image_seller').click()">
                            <div class="upload-info">
                                <div class="upload-title" style="color: var(--secondary-color); font-weight: 600;">Seller's Photo</div>
                                <div class="upload-subtitle">(විකුණුම්කරුගේ ඡායාරූපය - click to select)</div>
                            </div>
                            <span class="upload-status-icon" id="icon_seller">👤</span>
                            <input type="file" id="image_seller" name="image_seller" accept="image/*" required onchange="updateUI('seller')">
                        </div>
                        <div class="upload-item" onclick="document.getElementById('image_front').click()">
                            <div class="upload-info">
                                <div class="upload-title">Seller's Point Photo *</div>
                                <div class="upload-subtitle">(විකුණුම් ස්ථානයේ ඡායාරූපය - click to select)</div>
                            </div>
                            <span class="upload-status-icon" id="icon_front">📸</span>
                            <input type="file" id="image_front" name="image_front" accept="image/*" required onchange="updateUI('front')">
                        </div>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label>📝 Remarks / Comments</label>
                    <textarea name="remarks" id="remarks" placeholder="Enter any additional remarks here..." oninput="updatePreview()"></textarea>
                </div>

                <input type="hidden" name="is_draft" id="is_draft" value="0">
                <div style="margin-top: 1.5rem; display: flex; flex-direction: column; gap: 1rem;">
                    <div style="display: flex; gap: 1rem;">
                        <button type="submit" class="btn-submit" style="flex: 2;">
                            🚀 Submit Counter Details
                        </button>
                        <button type="reset" class="btn-submit" style="flex: 1; background: var(--glass-border); color: var(--text-main); display: flex; align-items: center; justify-content: center; gap: 8px;" onclick="setTimeout(updatePreview, 10);">
                            <span>🔄</span> Reset
                        </button>
                    </div>

                    <button type="button" onclick="submitAsDraft()" class="btn-submit" style="background: rgba(251, 191, 36, 0.1); color: #fbbf24; border: 1px solid rgba(251, 191, 36, 0.3); display: flex; align-items: center; justify-content: center; gap: 10px;">
                        📍 Pin Location Only (Counter Closed)
                    </button>
                    <p style="font-size: 0.7rem; color: var(--text-muted); text-align: center; margin-top: -5px;">Use this if the counter is closed. You can fill other details later from the dashboard.</p>
                </div>
            </form>
        </div>

        <!-- ── Right: Preview Panel ── -->
        <div class="info-panel">
            <div class="info-card" id="previewPanel">
                <h3>👁️ Live Preview</h3>
                <div id="pdf-content" style="padding: 10px;">
                    <div style="text-align: center; margin-bottom: 15px;">
                        <img src="assets/img/Logo.png" style="height: 40px; margin-bottom: 5px;">
                        <div style="font-weight: 700; font-size: 1.1rem; color: var(--secondary-color);">Counter Registration</div>
                    </div>

                    <div class="div-row">
                        <span class="div-label">Dealer:</span>
                        <span class="div-val" id="prev_dealer">-</span>
                    </div>
                    <div class="div-row">
                        <span class="div-label">Agent:</span>
                        <span class="div-val" id="prev_agent">-</span>
                    </div>
                    <div class="preview-row">
                        <span class="preview-label">Counter Code:</span>
                        <span class="preview-val" id="prev_code">-</span>
                    </div>
                    <div class="preview-row">
                        <span class="preview-label">Seller Name:</span>
                        <span class="preview-val" id="prev_name">-</span>
                    </div>
                    <div class="preview-row">
                        <span class="preview-label">NIC (New):</span>
                        <span class="preview-val" id="prev_nic_new">-</span>
                    </div>

                    <div class="preview-row">
                        <span class="preview-label">Birthday:</span>
                        <span class="preview-val" id="prev_birthday">-</span>
                    </div>
                    <?php if ($enable_location): ?>
                    <div class="preview-row">
                        <span class="preview-label">Province:</span>
                        <span class="preview-val" id="prev_province">-</span>
                    </div>
                    <div class="preview-row">
                        <span class="preview-label">District:</span>
                        <span class="preview-val" id="prev_district">-</span>
                    </div>
                    <div class="preview-row">
                        <span class="preview-label">DS Div:</span>
                        <span class="preview-val" id="prev_ds_division">-</span>
                    </div>
                    <div class="preview-row">
                        <span class="preview-label">GN Div:</span>
                        <span class="preview-val" id="prev_gn_division">-</span>
                    </div>
                    <?php endif; ?>
                    <div class="preview-row">
                        <span class="preview-label">Sales Method:</span>
                        <span class="preview-val" id="prev_method">-</span>
                    </div>
                    
                    <div id="prev_custom_fields_container"></div>

                    <div class="preview-row" style="flex-direction: column; align-items: flex-start;">
                        <span class="preview-label">Address:</span>
                        <span class="preview-val" id="prev_address" style="text-align: left; width: 100%; margin-top: 4px;">-</span>
                    </div>
                    <div class="preview-row" style="flex-direction: column; align-items: flex-start;">
                        <span class="preview-label">Location Link:</span>
                        <span class="preview-val" id="prev_location" style="text-align: left; width: 100%; margin-top: 4px; font-size: 0.7rem; color: var(--secondary-color); word-break: break-all;">-</span>
                    </div>
                    
                    <div style="margin-top: 10px; display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px;">
                        <div style="text-align:center;"><small>Seller Photo</small><img id="thumb_seller" style="width: 100%; height: 80px; object-fit: cover; border-radius: 8px; display: none; border: 1px solid var(--glass-border);"></div>
                        <div style="text-align:center;"><small>Point Photo</small><img id="thumb_front" style="width: 100%; height: 80px; object-fit: cover; border-radius: 8px; display: none; border: 1px solid var(--glass-border);"></div>
                    </div>
                </div>

                <button type="button" class="btn-action btn-pdf" onclick="downloadPDF()">
                    <span>📄</span> Download PDF
                </button>
                <button type="button" class="btn-action btn-whatsapp" onclick="shareWhatsApp()">
                    <span>💬</span> Share to WhatsApp
                </button>
            </div>

            <!-- Stats Card -->
            <div class="info-card">
                <h3>📊 My Session Stats</h3>
                <div class="stat-bar">
                    <?php
                        $myCount = $pdo->prepare("SELECT COUNT(*) FROM counters WHERE added_by = ?");
                        $myCount->execute([$_SESSION['username']]);
                        $myTotal = $myCount->fetchColumn();

                        $todayCount = $pdo->prepare("SELECT COUNT(*) FROM counters WHERE added_by = ? AND DATE(created_at) = CURDATE()");
                        $todayCount->execute([$_SESSION['username']]);
                        $todayTotal = $todayCount->fetchColumn();

                        $allTotal = $pdo->query("SELECT COUNT(*) FROM counters")->fetchColumn();
                    ?>
                    <div class="stat-item">
                        <div class="stat-num"><?php echo $myTotal; ?></div>
                        <div class="stat-label">My Total</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-num"><?php echo $todayTotal; ?></div>
                        <div class="stat-label">Today</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-num"><?php echo $allTotal; ?></div>
                        <div class="stat-label">All Records</div>
                    </div>
                </div>
            </div>

            <!-- Tips Card -->
            <div class="info-card">
                <h3>💡 Quick Tips</h3>
                <ul class="tip-list">
                    <li><span>📌</span> Dealer, Agent and Seller codes must be filled in before submitting.</li>
                    <li><span>📍</span> Open Google Maps, long-press your location, then copy the "Share" link.</li>
                    <li><span>📸</span> Images are optional but greatly help identify the booth.</li>
                    <li><span>✅</span> All data is tagged with your username automatically.</li>
                </ul>
            </div>

        </div><!-- end info-panel -->
    </div><!-- end entry-layout -->



    <?php include 'includes/footer.php'; ?>
</div><!-- end container wide -->

<script>
    function updatePreview() {
        const set = (id, val) => {
            const el = document.getElementById(id);
            if (val.trim()) {
                el.textContent = val;
                el.classList.remove('empty');
            } else {
                el.textContent = el.dataset.empty || 'Not entered';
                el.classList.add('empty');
            }
        };

        set('prev_dealer',  document.getElementById('dealer_code').value);
        set('prev_agent',   document.getElementById('agent_code').value);
        set('prev_code',    document.getElementById('seller_code').value);
        set('prev_name',    document.getElementById('seller_name').value);
        set('prev_nic_new', document.getElementById('nic_new_display').value || document.getElementById('nic_number').value);
        set('prev_birthday', document.getElementById('birthday').value);


        <?php if ($enable_location): ?>
        const getSelectedText = id => {
            const select = document.getElementById(id);
            if (!select || select.selectedIndex === -1 || select.value === "") return "";
            return select.options[select.selectedIndex].text;
        };
        set('prev_province', getSelectedText('province'));
        set('prev_district', getSelectedText('district'));
        set('prev_ds_division', getSelectedText('ds_division'));
        set('prev_gn_division', getSelectedText('gn_division'));
        <?php endif; ?>

        const loc = document.getElementById('location_link').value;
        document.getElementById('prev_location').innerText = loc || '-';
        
        // Address
        document.getElementById('prev_address').innerText = document.getElementById('address').value || '-';

        // Method
        const method = document.querySelector('input[name="sales_method"]');
        if (method) document.getElementById('prev_method').textContent = (method.type === 'radio') ? document.querySelector('input[name="sales_method"]:checked')?.value : method.value;

        // Custom fields live preview
        const cfContainer = document.getElementById('prev_custom_fields_container');
        cfContainer.innerHTML = '';
        const handledNames = new Set();
        document.querySelectorAll('.custom-field-group:not([style*="display: none"]) [id^="cf_"]').forEach(input => {
            const name = input.name;
            if (handledNames.has(name)) return;
            handledNames.add(name);

            const group = input.closest('.form-group');
            const labelCol = group.querySelector('label').innerText.replace('*', '').trim();
            let val = '';
            
            if (input.type === 'radio') {
                const checked = document.querySelector(`input[name="${name}"]:checked`);
                val = checked ? checked.value : '';
            } else {
                val = input.value ? input.value.trim() : '';
            }

            if (val) {
                const row = document.createElement('div');
                row.className = 'preview-row';
                row.innerHTML = `<span class="preview-label">${labelCol}:</span><span class="preview-val">${val}</span>`;
                cfContainer.appendChild(row);
            }
        });
    }

    function downloadPDF() {
        const element = document.getElementById('pdf-content');
        const opt = {
            margin: 0.5,
            filename: 'counter_' + (document.getElementById('seller_code').value || 'registration') + '.pdf',
            image: { type: 'jpeg', quality: 0.98 },
            html2canvas: { scale: 2, useCORS: true },
            jsPDF: { unit: 'in', format: 'letter', orientation: 'portrait' }
        };
        html2pdf().set(opt).from(element).save();
    }

    function shareWhatsApp() {
        const d_code = document.getElementById('dealer_code').value;
        const a_code = document.getElementById('agent_code').value;
        const s_code = document.getElementById('seller_code').value;
        const s_name = document.getElementById('seller_name').value;
        const s_phone = document.querySelector('[id^="cf_phone"]') ? document.querySelector('[id^="cf_phone"]').value : '';
        
        const text = `*New Sales Point Registration*\n\n` +
                     `*Dealer:* ${d_code}\n` +
                     `*Agent:* ${a_code}\n` +
                     `*Point Code:* ${s_code}\n` +
                     `*Name:* ${s_name}\n` +
                     (s_phone ? `*Phone:* +94 ${s_phone}\n` : '') +
                     `\nDetails submitted.`;
        
        const whatsappUrl = `https://wa.me/?text=${encodeURIComponent(text)}`;
        window.open(whatsappUrl, '_blank');
    }

    // Camera Logic
    let currentStream = null;
    let targetInputId = null;

    async function openCamera(target) {
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            alert('Camera Error: Your browser does not support camera access or you are not using a secure connection (HTTPS). Camera access is restricted on non-secure sites.');
            return;
        }
        targetInputId = 'image_' + target;
        const overlay = document.getElementById('cameraOverlay');
        const video = document.getElementById('cameraVideo');
        
        try {
            currentStream = await navigator.mediaDevices.getUserMedia({ 
                video: { facingMode: 'environment' }, 
                audio: false 
            });
            video.srcObject = currentStream;
            overlay.style.display = 'flex';
        } catch (err) {
            alert('Camera Error: ' + err.message);
        }
    }

    function closeCamera() {
        if (currentStream) {
            currentStream.getTracks().forEach(track => track.stop());
        }
        document.getElementById('cameraOverlay').style.display = 'none';
        currentStream = null;
    }

    function takeSnapshot() {
        const video = document.getElementById('cameraVideo');
        const canvas = document.getElementById('cameraCanvas');
        const context = canvas.getContext('2d');
        
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        
        context.drawImage(video, 0, 0, canvas.width, canvas.height);
        
        canvas.toBlob((blob) => {
            const file = new File([blob], "capture_" + Date.now() + ".jpg", { type: "image/jpeg" });
            const dataTransfer = new DataTransfer();
            dataTransfer.items.add(file);
            
            const input = document.getElementById(targetInputId);
            input.files = dataTransfer.files;
            
            const event = new Event('change');
            input.dispatchEvent(event);
            
            closeCamera();
        }, 'image/jpeg', 0.9);
    }

    function syncCustomFields() {
        const methodEl = document.querySelector('input[name="sales_method"]');
        if (!methodEl) return;
        
        let method = methodEl.name === 'sales_method' && methodEl.type === 'radio' 
            ? document.querySelector('input[name="sales_method"]:checked')?.value 
            : methodEl.value;
        if (!method) return;
        // Map legacy naming for consistent visibility check
        if (method === 'Sales Booth') method = 'Ticket Counter';
        
        const fields = document.querySelectorAll('.custom-field-group');
        
        fields.forEach(f => {
            let visibleFor = f.dataset.visibleFor;
            if (visibleFor === 'Sales Booth') visibleFor = 'Ticket Counter';
            
            if (visibleFor === 'all' || visibleFor === method) {
                f.style.display = '';
                // Re-enable required attribute if it was originally there
                const input = f.querySelector('input, textarea, select');
                if (input && input.dataset.wasRequired === 'true') {
                    input.required = true;
                }
            } else {
                f.style.display = 'none';
                // Disable required attribute when hidden to allow form submission
                const input = f.querySelector('input, textarea, select');
                if (input) {
                    if (input.required) input.dataset.wasRequired = 'true';
                    input.required = false;
                }
            }
        });
        updatePreview();
    }

    function updateUI(key) {
        const input = document.getElementById('image_' + key);
        const item  = input.closest('.upload-item');
        const icon  = document.getElementById('icon_' + key);
        const thumb = document.getElementById('thumb_' + key);
        const ph    = document.getElementById('ph_' + key);

        if (input.files && input.files[0]) {
            item.classList.add('selected');
            icon.innerText = '✅';
            item.querySelector('.upload-title').textContent = '✓ ' + input.files[0].name;
            item.querySelector('.upload-title').style.color = '#4ade80';

            // Show thumbnail preview
            const reader = new FileReader();
            reader.onload = e => {
                thumb.src = e.target.result;
                thumb.style.display = 'block';
                if (ph) ph.style.display = 'none';
            };
            reader.readAsDataURL(input.files[0]);
        }
    }
    window.onload = function() {
        handleNicTypeChange();
        syncCustomFields();
    };
    function handleNicTypeChange() {
        const typeEl = document.querySelector('input[name="nic_type"]:checked');
        if (!typeEl) return;
        const type = typeEl.value;
        const input = document.getElementById('nic_number');
        const label = document.getElementById('nic_label');
        const newDisplay = document.getElementById('nic_new_display');
        
        if (type === 'old') {
            label.textContent = 'Old NIC (9 digits + V/X)';
            input.placeholder = 'e.g. 905123456V';
            input.maxLength = 10;
            newDisplay.parentElement.style.display = 'block';
        } else {
            label.textContent = 'New NIC (12 digits)';
            input.placeholder = 'e.g. 199051203456';
            input.maxLength = 12;
            newDisplay.parentElement.style.display = 'none';
        }
        handleNicInput();
    }

    function handleNicInput() {
        const typeEl = document.querySelector('input[name="nic_type"]:checked');
        if (!typeEl) return;
        const type = typeEl.value;
        const val = document.getElementById('nic_number').value.trim().toUpperCase();
        const nicNewBox = document.getElementById('nic_new_display');
        const nicOldHidden = document.getElementById('nic_old_val');
        
        if (type === 'old') {
            if (val.length >= 9) {
                const year = "19" + val.substring(0, 2);
                const rest = val.substring(2, 5);
                const serial = val.substring(5, 9);
                const newNic = year + rest + "0" + serial;
                nicNewBox.value = newNic;
                document.getElementById('seller_code').value = newNic;
                nicOldHidden.value = val;
                
                extractBirthday(newNic);
                updatePreview();
            } else {
                nicNewBox.value = '';
                nicOldHidden.value = '';
            }
        } else {
            if (val.length === 12) {
                nicNewBox.value = val;
                document.getElementById('seller_code').value = val;
                nicOldHidden.value = '';
                extractBirthday(val);
                updatePreview();
            } else {
                nicNewBox.value = '';
            }
        }
    }

    function extractBirthday(nic12) {
        if (nic12.length !== 12) return;
        
        const year = parseInt(nic12.substring(0, 4));
        let days = parseInt(nic12.substring(4, 7));
        
        if (days > 500) days -= 500;
        
        const months = [
            { name: "Jan", days: 31 },
            { name: "Feb", days: 29 }, // NIC uses 366 day logic
            { name: "Mar", days: 31 },
            { name: "Apr", days: 30 },
            { name: "May", days: 31 },
            { name: "Jun", days: 30 },
            { name: "Jul", days: 31 },
            { name: "Aug", days: 31 },
            { name: "Sep", days: 30 },
            { name: "Oct", days: 31 },
            { name: "Nov", days: 30 },
            { name: "Dec", days: 31 }
        ];
        
        let month = 0;
        let day = days;
        
        for (let i = 0; i < months.length; i++) {
            if (day <= months[i].days) {
                month = i + 1;
                break;
            }
            day -= months[i].days;
        }
        
        const monthStr = month < 10 ? '0' + month : month;
        const dayStr = day < 10 ? '0' + day : day;
        
        document.getElementById('birthday').value = `${year}-${monthStr}-${dayStr}`;
    }
    
    function loadAgents(dealerSelectId, agentSelectId) {
        const dealerCode = document.getElementById(dealerSelectId).value;
        const agentSelect = document.getElementById(agentSelectId);
        
        if (!dealerCode) {
            agentSelect.innerHTML = '<option value="">-- Select Dealer First --</option>';
            return;
        }

        agentSelect.innerHTML = '<option value="">-- Loading --</option>';
        
        fetch('ajax/get_agents.php?dealer_code=' + encodeURIComponent(dealerCode))
            .then(res => res.json())
            .then(data => {
                agentSelect.innerHTML = '<option value="">-- Select Agent --</option>';
                data.forEach(agent => {
                    const option = document.createElement('option');
                    option.value = agent.agent_code;
                    option.textContent = agent.agent_code + " - " + agent.name;
                    agentSelect.appendChild(option);
                });
            })
            .catch(err => {
                console.error('Error loading agents:', err);
                agentSelect.innerHTML = '<option value="">-- Error Loading --</option>';
            });
    }

    function getLocationLink() {
        const statusText = document.getElementById('location_status');
        const linkInput = document.getElementById('location_link');
        
        if (!navigator.geolocation) {
            statusText.textContent = "Geolocation is not supported by your browser.";
            statusText.style.color = "#f87171";
            return;
        }

        statusText.textContent = "Locating... Please allow location access.";
        statusText.style.color = "#00d4ff";

        navigator.geolocation.getCurrentPosition(
            (position) => {
                const lat = position.coords.latitude;
                const lng = position.coords.longitude;
                linkInput.value = `https://www.google.com/maps?q=${lat},${lng}`;
                statusText.textContent = "Location captured successfully!";
                statusText.style.color = "#4ade80";
                updatePreview();
            },
            (error) => {
                let msg = "Unable to retrieve location.";
                if (error.code === 1) msg = "Access Denied by user or browser (Check device/browser settings).";
                else if (error.code === 2) msg = "Position unavailable (Check if device GPS/Location Services are ON).";
                else if (error.code === 3) msg = "Location request timed out.";
                
                if (window.location.protocol !== 'https:' && window.location.hostname !== 'localhost') {
                    msg += " Note: GPS strictly requires an HTTPS connection.";
                }

                statusText.textContent = msg;
                statusText.style.color = "#f87171";
                console.warn("Geolocation Error:", error);
            },
            { enableHighAccuracy: true }
        );
    }

    <?php if ($enable_location): ?>
    let locationData = [];

    // Load main location data from data.json on startup
    window.addEventListener('DOMContentLoaded', () => {
        fetch('data/data.json')
            .then(res => res.json())
            .then(data => {
                locationData = data;
                populateProvinces();
            })
            .catch(err => console.error("Could not load location data:", err));
    });

    function populateProvinces() {
        const provSelect = document.getElementById('province');
        if (!provSelect) return;
        provSelect.innerHTML = '<option value="">-- Select Province --</option>';
        locationData.forEach((p, index) => {
            provSelect.innerHTML += `<option value="${index}">${p.province}</option>`;
        });
    }

    function loadDistricts() {
        const provIndex = document.getElementById('province').value;
        const distSelect = document.getElementById('district');
        const dsSelect = document.getElementById('ds_division');
        const gnSelect = document.getElementById('gn_division');

        distSelect.innerHTML = '<option value="">-- Select District --</option>';
        dsSelect.innerHTML = '<option value="">-- Select DS Division --</option>';
        gnSelect.innerHTML = '<option value="">-- Select GN Division --</option>';
        
        distSelect.disabled = true;
        dsSelect.disabled = true;
        gnSelect.disabled = true;

        if (provIndex === "") return;

        const districts = locationData[provIndex].districts;
        districts.forEach((d, index) => {
            distSelect.innerHTML += `<option value="${index}">${d.district}</option>`;
        });
        distSelect.disabled = false;
    }

    function loadDSDivisions() {
        const provIndex = document.getElementById('province').value;
        const distIndex = document.getElementById('district').value;
        const dsSelect = document.getElementById('ds_division');
        const gnSelect = document.getElementById('gn_division');

        dsSelect.innerHTML = '<option value="">-- Select DS Division --</option>';
        gnSelect.innerHTML = '<option value="">-- Select GN Division --</option>';
        
        dsSelect.disabled = true;
        gnSelect.disabled = true;

        if (provIndex === "" || distIndex === "") return;

        const dsDivs = locationData[provIndex].districts[distIndex].ds_divisions;
        dsDivs.forEach((ds, index) => {
            dsSelect.innerHTML += `<option value="${index}">${ds.ds_division}</option>`;
        });
        dsSelect.disabled = false;
    }

    function loadGNDivisions() {
        const provIndex = document.getElementById('province').value;
        const distIndex = document.getElementById('district').value;
        const dsIndex = document.getElementById('ds_division').value;
        const gnSelect = document.getElementById('gn_division');

        gnSelect.innerHTML = '<option value="">-- Select GN Division --</option>';
        gnSelect.disabled = true;

        if (provIndex === "" || distIndex === "" || dsIndex === "") return;

        const gnDivs = locationData[provIndex].districts[distIndex].ds_divisions[dsIndex].gn_divisions;
        gnDivs.forEach((gn, index) => {
            gnSelect.innerHTML += `<option value="${gn}">${gn}</option>`;
        });
        gnSelect.disabled = false;
    }

    // Add submit event listener to populate hidden fields before form submission
    document.getElementById('entryForm').addEventListener('submit', function(e) {
        const provSelect = document.getElementById('province');
        const distSelect = document.getElementById('district');
        const dsSelect = document.getElementById('ds_division');
        
        if (provSelect) document.getElementById('province_text').value = provSelect.options[provSelect.selectedIndex]?.text || '';
        if (distSelect) document.getElementById('district_text').value = distSelect.options[distSelect.selectedIndex]?.text || '';
        if (dsSelect) document.getElementById('ds_division_text').value = dsSelect.options[dsSelect.selectedIndex]?.text || '';
    });
    <?php endif; ?>
</script>
</body>
</html>
