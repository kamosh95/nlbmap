<?php
require_once 'includes/security.php';
if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'moderator' && $_SESSION['role'] !== 'tm')) {
    header("Location: login.php");
    exit;
}

require_once 'includes/db_config.php';
require_once 'includes/get_nav.php';

// Fetch custom fields
$custom_fields = $pdo->query("SELECT * FROM custom_fields ORDER BY sort_order ASC, id ASC")->fetchAll();

// Fetch all dealers
$dealers = $pdo->query("SELECT dealer_code, name FROM dealers ORDER BY dealer_code ASC")->fetchAll();

$message = '';
$status = '';
$record = null;

if (isset($_GET['id'])) {
    $stmt = $pdo->prepare("SELECT * FROM counters WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $record = $stmt->fetch();
}

if (!$record) {
    header("Location: dashboard.php");
    exit;
}

// Fetch agents for current dealer
$current_agents = [];
$cv_map = [];
if ($record && !empty($record['dealer_code'])) {
    $stmt = $pdo->prepare("SELECT agent_code, name FROM agents WHERE dealer_code = ? ORDER BY agent_code ASC");
    $stmt->execute([$record['dealer_code']]);
    $current_agents = $stmt->fetchAll();

    // Load existing custom field values for this record
    $cv_stmt = $pdo->prepare("SELECT field_id, field_value FROM counter_custom_values WHERE counter_id=?");
    $cv_stmt->execute([$record['id']]);
    foreach ($cv_stmt->fetchAll() as $cv) {
        $cv_map[$cv['field_id']] = $cv['field_value'];
    }
}

// Fetch System Settings
$enable_location = true;
try {
    $settings = $pdo->query("SELECT setting_key, setting_value FROM settings")->fetchAll(PDO::FETCH_KEY_PAIR);
    $enable_location = ($settings['enable_location'] ?? '1') === '1';
} catch (PDOException $e) { /* settings table might not exist yet */ }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF
    verify_csrf_token($_POST['csrf_token'] ?? '');
    
    $dealer_code = $_POST['dealer_code'] ?? '';
    $agent_code = $_POST['agent_code'] ?? '';
    $seller_code = $_POST['seller_code'] ?? '';
    $seller_name = $_POST['seller_name'] ?? '';
    $sales_method = $_POST['sales_method'] ?? '';
    $location_link = $_POST['location_link'] ?? '';

    $imgs = [
        'seller' => $record['seller_image'],
        'front'  => $record['image_front'],
        'side'   => $record['image_side'],
        'inside' => $record['image_inside']
    ];

    foreach (['seller', 'front', 'side', 'inside'] as $key) {
        $file_key = ($key === 'seller') ? 'seller_image' : 'image_' . $key;
        if (isset($_FILES[$file_key]) && $_FILES[$file_key]['error'] === UPLOAD_ERR_OK) {
            $tmp_name = $_FILES[$file_key]['tmp_name'];
            $orig_name = $_FILES[$file_key]['name'];
            if (is_allowed_file($orig_name, $tmp_name)) {
                $extension = pathinfo($orig_name, PATHINFO_EXTENSION);
                $nic_new = $_POST['nic_new'] ?? '';
                $base_name = !empty($nic_new) ? 'NIC_' . preg_replace('/[^a-zA-Z0-9]/', '', $nic_new) : preg_replace('/[^a-zA-Z0-9]/', '_', $seller_code);
                $file_name = $base_name . '_' . $key . '_' . uniqid() . '.' . $extension;
                $target_path = 'uploads/' . $file_name;
                if (move_uploaded_file($tmp_name, $target_path)) {
                    $imgs[$key] = $target_path;
                }
            }
        }
    }

    try {
        // Check if agent or dealer code has changed
        if ($record['agent_code'] !== $agent_code || $record['dealer_code'] !== $dealer_code) {
            $hist_stmt = $pdo->prepare("INSERT INTO transfer_history (counter_id, old_dealer_code, new_dealer_code, old_agent_code, new_agent_code, changed_by) VALUES (?, ?, ?, ?, ?, ?)");
            $hist_stmt->execute([
                $record['id'],
                $record['dealer_code'],
                $dealer_code,
                $record['agent_code'],
                $agent_code,
                $_SESSION['username'] ?? 'unknown'
            ]);
        }

        $stmt = $pdo->prepare("UPDATE counters SET 
            dealer_code = ?, 
            agent_code = ?, 
            seller_code = ?, 
            seller_name = ?, 
            nic_type = ?,
            nic_old = ?,
            nic_new = ?,
            seller_image = ?,
            birthday = ?,
            sales_method = ?, 
            location_link = ?,
            province = ?,
            district = ?,
            ds_division = ?,
            gn_division = ?,
            image_front = ?,
            image_side = ?,
            image_inside = ?,
            address = ?,
            phone = ?,
            status = ?
            WHERE id = ?");
        
        $stmt->execute([
            $dealer_code,
            $agent_code,
            $seller_code,
            $seller_name,
            $_POST['nic_type'] ?? '',
            $_POST['nic_old'] ?? '',
            $_POST['nic_new'] ?? '',
            $imgs['seller'],
            !empty($_POST['birthday']) ? $_POST['birthday'] : null,
            $sales_method,
            $location_link,
            $_POST['province'] ?? null,
            $_POST['district'] ?? null,
            $_POST['ds_division'] ?? null,
            $_POST['gn_division'] ?? null,
            $imgs['front'],
            $imgs['side'],
            $imgs['inside'],
            $_POST['address'] ?? '',
            $_POST['phone'] ?? '',
            $_POST['status'] ?? 'Active',
            $record['id']
        ]);

        // Save custom field values (upsert)
        foreach ($custom_fields as $cf) {
            $cf_val = $_POST['cf_' . $cf['field_name']] ?? '';
            $chk = $pdo->prepare("SELECT id FROM counter_custom_values WHERE counter_id=? AND field_id=?");
            $chk->execute([$record['id'], $cf['id']]);
            if ($chk->fetch()) {
                $pdo->prepare("UPDATE counter_custom_values SET field_value=? WHERE counter_id=? AND field_id=?")
                    ->execute([$cf_val, $record['id'], $cf['id']]);
            } else {
                $pdo->prepare("INSERT INTO counter_custom_values (counter_id, field_id, field_value) VALUES (?,?,?)")
                    ->execute([$record['id'], $cf['id'], $cf_val]);
            }
        }
        
        $message = "Record updated successfully!";
        $status = 'success';
        log_activity($pdo, "Updated Seller Record", "Code: $seller_code, Name: $seller_name", "seller");
        
        // Refresh record data
        $stmt = $pdo->prepare("SELECT * FROM counters WHERE id = ?");
        $stmt->execute([$record['id']]);
        $record = $stmt->fetch();
        
    } catch (PDOException $e) {
        $message = "Database error: " . $e->getMessage();
        $status = 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Record - NLB</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="container wide">
        <!-- Page Header -->
        <div class="page-header">
            <img src="assets/img/Logo.png" alt="NLB Logo">
            <div class="page-header-text">
                <h1>NLB Seller Map Portal</h1>
                <p>Edit Seller Record &nbsp;·&nbsp; Logged in as <b><?php echo htmlspecialchars($_SESSION['username']); ?></b></p>
            </div>
        </div>

        <!-- Nav Bar -->
        <div class="nav-bar" style="margin-bottom: 2rem;">
            <?php echo render_nav($pdo, $_SESSION['role']); ?>
        </div>

        <?php if ($message): ?>
            <div class="message <?php echo $status; ?>" style="display: block;">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <?php if ($record && !empty($record['reg_number'])): ?>
        <div style="background: rgba(255, 204, 0, 0.1); border: 1px solid var(--secondary-color); padding: 0.75rem 1.5rem; border-radius: 12px; margin-bottom: 1.5rem; display: flex; align-items: center; justify-content: space-between;">
            <div style="font-weight: 700; color: var(--secondary-color); font-size: 1.1rem; letter-spacing: 0.5px;">
                <span style="opacity: 0.7; font-size: 0.8rem; text-transform: uppercase; margin-right: 10px;">Registered Number:</span>
                <?php echo htmlspecialchars($record['reg_number']); ?>
            </div>
            <div style="font-size: 0.75rem; opacity: 0.8;">
                Seller Code: <?php echo htmlspecialchars($record['seller_code']); ?>
            </div>
        </div>
        <?php endif; ?>

        <form action="" method="POST" enctype="multipart/form-data">
            <?php 
                csrf_input(); 
                $main_fields = array_filter($custom_fields, fn($f) => ($f['display_section'] ?? 'additional') === 'main');
                $additional_fields = array_filter($custom_fields, fn($f) => ($f['display_section'] ?? 'additional') === 'additional');

                // Helper to render custom fields (Edit context)
                function render_cf_group($fields, $cv_map) {
                    $in_row = false; $row_count = 0;
                    foreach ($fields as $cf) {
                        $cf_id = 'cf_' . $cf['field_name'];
                        $cf_val = $cv_map[$cf['id']] ?? $cf['default_value'];
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
                        if ($is_tel && preg_match('/^(\+\d{1,4})/', $cf_val, $m)) { $prefix = $m[1]; }
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
                                    <input type="tel" id="<?php echo $cf_id; ?>" name="<?php echo $cf_id; ?>" value="<?php echo htmlspecialchars($cf_val); ?>">
                                </div>
                            <?php elseif ($is_textarea): ?>
                                <textarea id="<?php echo $cf_id; ?>" name="<?php echo $cf_id; ?>" style="min-height:80px; resize:vertical;"><?php echo htmlspecialchars($cf_val); ?></textarea>
                            <?php elseif ($cf['field_type'] === 'radio'): 
                                $opts = explode(',', $cf['field_options'] ?? ''); ?>
                                <div class="radio-group" style="grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));">
                                    <?php foreach ($opts as $opt): $opt = trim($opt); if (!$opt) continue; $safe_opt = htmlspecialchars($opt); $opt_id = $cf_id . '_' . preg_replace('/[^a-z0-9]/', '', strtolower($opt)); ?>
                                        <input type="radio" id="<?php echo $opt_id; ?>" name="<?php echo $cf_id; ?>" value="<?php echo $safe_opt; ?>" <?php echo ($cf_val === $opt) ? 'checked' : ''; ?> <?php echo $cf['is_required'] ? 'required' : ''; ?>>
                                        <label for="<?php echo $opt_id; ?>" class="radio-pill"><span><?php echo $safe_opt; ?></span></label>
                                    <?php endforeach; ?>
                                </div>
                            <?php elseif ($cf['field_type'] === 'checkbox'): ?>
                                <div style="display: flex; align-items: center; gap: 10px; padding: 10px 14px; background: rgba(255,255,255,0.03); border: 1px solid var(--glass-border); border-radius: 12px; cursor: pointer;" onclick="document.getElementById('<?php echo $cf_id; ?>').click()">
                                    <input type="checkbox" id="<?php echo $cf_id; ?>" name="<?php echo $cf_id; ?>" value="1" <?php echo ($cf_val === '1' || strtolower($cf_val) === 'checked') ? 'checked' : ''; ?> style="width: 20px; height: 20px; accent-color: var(--secondary-color); cursor: pointer;">
                                    <span style="font-size: 0.9rem; color: var(--text-main);"><?php echo htmlspecialchars($cf['placeholder'] ?: 'Yes / Enabled'); ?></span>
                                </div>
                            <?php else: ?>
                                <input type="<?php echo htmlspecialchars($cf['field_type']); ?>" id="<?php echo $cf_id; ?>" name="<?php echo $cf_id; ?>" value="<?php echo htmlspecialchars($cf_val); ?>">
                            <?php endif; ?>
                        </div>
                        <?php
                    }
                    if ($in_row) echo '</div>';
                }
            ?>


            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label>Dealer Code</label>
                    <select name="dealer_code" id="dealer_code" required onchange="loadAgents('dealer_code', 'agent_code')">
                        <option value="">-- Select Dealer --</option>
                        <?php foreach ($dealers as $d): ?>
                            <option value="<?php echo htmlspecialchars($d['dealer_code']); ?>" <?php echo $record['dealer_code'] === $d['dealer_code'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($d['dealer_code'] . ' - ' . $d['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Agent Code</label>
                    <select name="agent_code" id="agent_code" required>
                        <option value="">-- Select Agent --</option>
                        <?php foreach ($current_agents as $a): ?>
                            <option value="<?php echo htmlspecialchars($a['agent_code']); ?>" <?php echo $record['agent_code'] === $a['agent_code'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($a['agent_code'] . ' - ' . $a['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label>Seller Code</label>
                    <input type="text" id="seller_code" name="seller_code" value="<?php echo htmlspecialchars($record['seller_code']); ?>" readonly style="background: rgba(255,255,255,0.02); cursor: not-allowed; border-color: rgba(255,255,255,0.05);" required>
                </div>
                <div class="form-group">
                    <label>Seller Name</label>
                    <input type="text" name="seller_name" value="<?php echo htmlspecialchars($record['seller_name']); ?>" required>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label>NIC Type</label>
                    <div class="radio-group" style="margin-top: 5px;">
                        <input type="radio" id="nt_old" name="nic_type" value="old" <?php echo ($record['nic_type'] === 'old') ? 'checked' : ''; ?> onchange="handleNicTypeChange()">
                        <label for="nt_old" class="radio-pill">
                            <span>📄 Old NIC</span>
                        </label>

                        <input type="radio" id="nt_new" name="nic_type" value="new" <?php echo ($record['nic_type'] === 'new') ? 'checked' : ''; ?> onchange="handleNicTypeChange()">
                        <label for="nt_new" class="radio-pill">
                            <span>🪪 New NIC</span>
                        </label>
                    </div>
                </div>
                <div class="form-group">
                    <label for="nic_number" id="nic_label">NIC Number</label>
                    <input type="text" id="nic_number" name="nic_input" value="<?php echo htmlspecialchars($record['nic_type'] === 'old' ? $record['nic_old'] : ($record['nic_new'] ?: ($record['nic_input'] ?? ''))); ?>" required oninput="handleNicInput()">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label for="nic_new_display">Generated New NIC</label>
                    <input type="text" id="nic_new_display" name="nic_new" value="<?php echo htmlspecialchars($record['nic_new']); ?>" readonly style="background: rgba(255,255,255,0.02); cursor: not-allowed; border-color: rgba(255,255,255,0.05);">
                    <input type="hidden" id="nic_old_val" name="nic_old" value="<?php echo htmlspecialchars($record['nic_old']); ?>">
                </div>
                <div class="form-group">
                    <label for="birthday">🎂 Birthday</label>
                    <div style="position: relative;">
                        <input type="date" id="birthday" name="birthday" value="<?php echo htmlspecialchars($record['birthday']); ?>" style="width: 100%; padding: 0.75rem 1rem; background: var(--nav-bg); border: 2px solid var(--glass-border); border-radius: 12px; color: var(--text-main); font-weight: 600; font-family: 'Outfit', sans-serif; transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
                    </div>
                </div>
            </div>

            <?php if ($enable_location): ?>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label>Province Name / ID (පළාත)</label>
                    <input type="text" name="province" value="<?php echo htmlspecialchars($record['province'] ?? ''); ?>" placeholder="Enter Province">
                </div>
                <div class="form-group">
                    <label>District Name / ID (දිස්ත්‍රික්කය)</label>
                    <input type="text" name="district" value="<?php echo htmlspecialchars($record['district'] ?? ''); ?>" placeholder="Enter District">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label>DS Division Name / ID (ප්‍රාදේශීය ලේකම් කොට්ඨාසය)</label>
                    <input type="text" name="ds_division" value="<?php echo htmlspecialchars($record['ds_division'] ?? ''); ?>" placeholder="Enter DS Division">
                </div>
                <div class="form-group">
                    <label>GN Division(s)</label>
                    <input type="text" name="gn_division" value="<?php echo htmlspecialchars($record['gn_division'] ?? ''); ?>" placeholder="Comma separated list">
                </div>
            </div>
            <?php endif; ?>

            <?php if (!empty($main_fields)) render_cf_group($main_fields, $cv_map ?? []); ?>



            <div class="form-group" style="margin-bottom: 2rem;">
                <label>🏠 Address (ලිපිනය)</label>
                <textarea name="address" placeholder="Enter Full Address"><?php echo htmlspecialchars($record['address'] ?? ''); ?></textarea>
            </div>

            <div class="form-group" style="margin-bottom: 1.5rem;">
                <label>📞 Telephone No (දුරකථන අංකය)</label>
                <div class="phone-input-wrap">
                    <div class="phone-prefix"><span class="flag">🇱🇰</span></div>
                    <input type="tel" name="phone" value="<?php echo htmlspecialchars($record['phone'] ?? ''); ?>" placeholder="07X XXX XXXX" pattern="[0-9]{9,12}">
                </div>
            </div>

            <div class="form-group">
                <label>Sales Method</label>
                <div class="radio-group" style="margin-top: 5px;">
                    <input type="radio" id="sm_booth" name="sales_method" value="Ticket Counter" <?php echo ($record['sales_method'] === 'Ticket Counter' || $record['sales_method'] === 'Sales Booth') ? 'checked' : ''; ?> onchange="syncCustomFields()">
                    <label for="sm_booth" class="radio-pill">
                        <span>🏪 Ticket Counter</span>
                    </label>

                        <input type="radio" id="sm_mobile" name="sales_method" value="Mobile Sales" <?php echo ($record['sales_method'] === 'Mobile Sales' || $record['sales_method'] === 'Mobile Sale') ? 'checked' : ''; ?> onchange="syncCustomFields()">
                        <label for="sm_mobile" class="radio-pill">
                            <span>🚶 Mobile Sales</span>
                        </label>
                </div>
            </div>

            <div class="form-group">
                <label>Account Status</label>
                <select name="status" style="width: 100%; padding: 0.8rem 1.2rem; background: var(--input-bg); border: 1px solid var(--glass-border); color: var(--text-main); border-radius: 12px; margin-top: 5px;">
                    <option value="Active" <?php echo ($record['status'] ?? 'Active') === 'Active' ? 'selected' : ''; ?>>✅ Active</option>
                    <option value="Inactive" <?php echo ($record['status'] ?? 'Active') === 'Inactive' ? 'selected' : ''; ?>>❌ Inactive</option>
                </select>
            </div>


            <div style="margin-top:1rem; padding-top:1rem; border-top:1px solid var(--glass-border);">
                <?php if (!empty($additional_fields)): ?>
                <p style="font-size:0.8rem; color:var(--text-muted); margin-bottom:1rem;">⚙️ Additional Fields</p>
                <?php render_cf_group($additional_fields, $cv_map ?? []); ?>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label>Location Link (Google Maps)</label>
                <div class="location-input-group" style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <input type="text" id="location_link" name="location_link" value="<?php echo htmlspecialchars($record['location_link']); ?>" placeholder="Use full URL containing coords for Map View" style="flex: 1; min-width: 200px;">
                    <div style="display: flex; gap: 10px; flex: 1; min-width: 200px;">
                        <button type="button" onclick="getLocationLink()" style="flex: 1; background: rgba(0, 212, 255, 0.1); border: 1px solid rgba(0, 212, 255, 0.25); color: #00d4ff; border-radius: 12px; padding: 0.8rem; cursor: pointer; font-weight: 600; white-space: nowrap; transition: 0.3s;" onmouseover="this.style.background='rgba(0, 212, 255, 0.2)'" onmouseout="this.style.background='rgba(0, 212, 255, 0.1)'" title="Get Current GPS Location">📍 Get GPS</button>
                        <a href="https://maps.google.com" target="_blank" style="flex: 1; background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); color: var(--text-main); border-radius: 12px; padding: 0.8rem; text-decoration: none; display: flex; align-items: center; justify-content: center; cursor: pointer; font-weight: 600; transition: 0.3s;" onmouseover="this.style.background='rgba(255, 255, 255, 0.1)'" onmouseout="this.style.background='rgba(255, 255, 255, 0.05)'" title="Open Google Maps manually">🗺️ Maps</a>
                    </div>
                </div>
                <p id="location_status" style="font-size: 0.75rem; color: var(--text-muted); margin-top: 5px;">Use full URL containing coords for Map View.</p>
            </div>

            <div class="responsive-image-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.5rem; margin-top: 1.5rem; margin-bottom: 2rem;">
                <div class="form-group">
                    <label>📷 Seller Photo</label>
                    <div style="display: flex; gap: 1rem; align-items: center; background: rgba(255,255,255,0.03); padding: 10px; border-radius: 12px; border: 1px solid var(--glass-border);">
                        <?php if (isset($record['seller_image']) && $record['seller_image']): ?>
                            <img src="<?php echo $record['seller_image']; ?>" style="width: 60px; height: 60px; border-radius: 8px; object-fit: cover; border: 2px solid var(--secondary-color); flex-shrink: 0;">
                        <?php endif; ?>
                        <input type="file" name="seller_image" accept="image/*" style="font-size: 0.8rem; width: 100%;">
                    </div>
                </div>
                <div class="form-group">
                    <label>🏠 Counter Front View</label>
                    <div style="display: flex; gap: 1rem; align-items: center; background: rgba(255,255,255,0.03); padding: 10px; border-radius: 12px; border: 1px solid var(--glass-border);">
                        <?php if (isset($record['image_front']) && $record['image_front']): ?>
                            <img src="<?php echo $record['image_front']; ?>" style="width: 60px; height: 60px; border-radius: 8px; object-fit: cover; border: 2px solid var(--secondary-color); flex-shrink: 0;">
                        <?php endif; ?>
                        <input type="file" name="image_front" accept="image/*" style="font-size: 0.8rem; width: 100%;">
                    </div>
                </div>
                <div class="form-group">
                    <label>📐 Counter Side View</label>
                    <div style="display: flex; gap: 1rem; align-items: center; background: rgba(255,255,255,0.03); padding: 10px; border-radius: 12px; border: 1px solid var(--glass-border);">
                        <?php if (isset($record['image_side']) && $record['image_side']): ?>
                            <img src="<?php echo $record['image_side']; ?>" style="width: 60px; height: 60px; border-radius: 8px; object-fit: cover; border: 2px solid var(--secondary-color); flex-shrink: 0;">
                        <?php endif; ?>
                        <input type="file" name="image_side" accept="image/*" style="font-size: 0.8rem; width: 100%;">
                    </div>
                </div>
                <div class="form-group">
                    <label>🖼️ Counter Inside View</label>
                    <div style="display: flex; gap: 1rem; align-items: center; background: rgba(255,255,255,0.03); padding: 10px; border-radius: 12px; border: 1px solid var(--glass-border);">
                        <?php if (isset($record['image_inside']) && $record['image_inside']): ?>
                            <img src="<?php echo $record['image_inside']; ?>" style="width: 60px; height: 60px; border-radius: 8px; object-fit: cover; border: 2px solid var(--secondary-color); flex-shrink: 0;">
                        <?php endif; ?>
                        <input type="file" name="image_inside" accept="image/*" style="font-size: 0.8rem; width: 100%;">
                    </div>
                </div>
            </div>

            <button type="submit" class="btn-submit">Update Record</button>
            <a href="dashboard.php" style="display: block; text-align: center; margin-top: 1rem; color: var(--text-muted); text-decoration: none; font-size: 0.9rem;">Discard Changes</a>
        </form>

        <?php
        // Fetch Transfer History
        try {
            $hist_stmt = $pdo->prepare("SELECT * FROM transfer_history WHERE counter_id = ? ORDER BY changed_at DESC");
            $hist_stmt->execute([$record['id']]);
            $history = $hist_stmt->fetchAll();
            
            if (count($history) > 0): ?>
                <div style="margin-top: 3rem; background: var(--card-bg); border: 1px solid var(--glass-border); border-radius: 16px; padding: 2rem;">
                    <h3 style="margin-bottom: 1.5rem; color: var(--text-main); font-size: 1.25rem;">🔄 Dealership & Agent Transfer History</h3>
                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse; text-align: left; background: rgba(0,0,0,0.2); border-radius: 12px; overflow: hidden;">
                            <thead>
                                <tr style="background: rgba(255,255,255,0.05); border-bottom: 1px solid rgba(255,255,255,0.1);">
                                    <th style="padding: 1rem; font-weight: 600; color: var(--text-muted);">Date & Time</th>
                                    <th style="padding: 1rem; font-weight: 600; color: var(--text-muted);">Changes (Old ➔ New)</th>
                                    <th style="padding: 1rem; font-weight: 600; color: var(--text-muted);">Changed By</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($history as $h): ?>
                                    <tr style="border-bottom: 1px solid rgba(255,255,255,0.05); transition: background 0.3s ease;">
                                        <td style="padding: 1rem; color: var(--text-main);"><?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($h['changed_at']))); ?></td>
                                        <td style="padding: 1rem;">
                                            <?php if ($h['old_dealer_code'] !== $h['new_dealer_code']): ?>
                                                <div style="margin-bottom: 4px;">
                                                    <span style="color: var(--text-muted); font-size: 0.85rem;">Dealer: </span>
                                                    <span style="color: #f87171; font-weight: 600;"><?php echo htmlspecialchars($h['old_dealer_code']); ?></span>
                                                    <span style="color: var(--text-muted);"> ➔ </span>
                                                    <span style="color: #011809ff; font-weight: 600;"><?php echo htmlspecialchars($h['new_dealer_code']); ?></span>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($h['old_agent_code'] !== $h['new_agent_code']): ?>
                                                <div>
                                                    <span style="color: var(--text-muted); font-size: 0.85rem;">Agent: </span>
                                                    <span style="color: #f87171; font-weight: 600;"><?php echo htmlspecialchars($h['old_agent_code']); ?></span>
                                                    <span style="color: var(--text-muted);"> ➔ </span>
                                                    <span style="color: #011809ff; font-weight: 600;"><?php echo htmlspecialchars($h['new_agent_code']); ?></span>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td style="padding: 1rem; color: var(--text-muted);"><?php echo htmlspecialchars($h['changed_by']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif;
        } catch (PDOException $e) {
            // Table might not exist yet if db_config hasn't been run
        }
        ?>
    </div>
    <?php include 'includes/footer.php'; ?>
    <script>
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

        function syncCustomFields() {
            const methodEl = document.querySelector('input[name="sales_method"]:checked');
            if (!methodEl) return;
            
            let method = methodEl.value;
            // Map legacy naming for consistency
            if (method === 'Sales Booth') method = 'Ticket Counter';
            
            const fields = document.querySelectorAll('.custom-field-group');
            
            fields.forEach(f => {
                let visibleFor = f.dataset.visibleFor;
                if (visibleFor === 'Sales Booth') visibleFor = 'Ticket Counter';
                
                if (visibleFor === 'all' || visibleFor === method) {
                    f.style.display = '';
                    const input = f.querySelector('input, textarea, select');
                    if (input && input.dataset.wasRequired === 'true') {
                        input.required = true;
                    }
                } else {
                    f.style.display = 'none';
                    const input = f.querySelector('input, textarea, select');
                    if (input) {
                        if (input.required) input.dataset.wasRequired = 'true';
                        input.required = false;
                    }
                }
            });
        }
        window.onload = function() {
            handleNicTypeChange();
            syncCustomFields();
        };
    </script>

    <script>
        // Camera Logic
        let currentStream = null;
        let targetInputId = null;

        async function openCamera(inputId) {
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                alert('Camera Error: Your browser does not support camera access or you are not using a secure connection (HTTPS). Camera access is restricted on non-secure sites.');
                return;
            }
            targetInputId = inputId;
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
                
                closeCamera();
            }, 'image/jpeg', 0.9);
        }

        function handleNicInput() {
            const typeEl = document.querySelector('input[name="nic_type"]:checked');
            if (!typeEl) return;
            const type = typeEl.value;
            const val = document.getElementById('nic_number').value.trim().toUpperCase();
            const nicNewBox = document.getElementById('nic_new_display');
            const nicOldHidden = document.getElementById('nic_old_val');
            const sellerCodeBox = document.getElementById('seller_code');
            
            if (type === 'old') {
                if (val.length >= 9) {
                    const year = "19" + val.substring(0, 2);
                    const rest = val.substring(2, 5);
                    const serial = val.substring(5, 9);
                    const newNic = year + rest + "0" + serial;
                    nicNewBox.value = newNic;
                    if (sellerCodeBox) sellerCodeBox.value = newNic;
                    nicOldHidden.value = val;
                    extractBirthday(newNic);
                }
            } else {
                if (val.length === 12) {
                    nicNewBox.value = val;
                    if (sellerCodeBox) sellerCodeBox.value = val;
                    nicOldHidden.value = '';
                    extractBirthday(val);
                }
            }
        }

        function extractBirthday(nic12) {
            if (nic12.length !== 12) return;
            const year = parseInt(nic12.substring(0, 4));
            let days = parseInt(nic12.substring(4, 7));
            if (days > 500) days -= 500;
            const months = [
                { days: 31 }, { days: 29 }, { days: 31 }, { days: 30 },
                { days: 31 }, { days: 30 }, { days: 31 }, { days: 31 },
                { days: 30 }, { days: 31 }, { days: 30 }, { days: 31 }
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
                agentSelect.innerHTML = '<option value="">-- Select Dealer --</option>';
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

    </script>
    <?php include 'includes/footer.php'; ?>
</body>
</html>
