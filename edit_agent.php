<?php
require_once 'includes/security.php';
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'tm'])) {
    header("Location: login.php");
    exit;
}

require_once 'includes/db_config.php';
require_once 'includes/get_nav.php';

$id = (int)($_GET['id'] ?? 0);
$agent = null;

if ($id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM agents WHERE id = ?");
    $stmt->execute([$id]);
    $agent = $stmt->fetch();
    
    // Fetch addresses
    $addr_stmt = $pdo->prepare("SELECT * FROM agent_addresses WHERE agent_id = ?");
    $addr_stmt->execute([$id]);
    $addresses = $addr_stmt->fetchAll();
    
    // Fetch locations
    $loc_stmt = $pdo->prepare("SELECT * FROM agent_locations WHERE agent_id = ?");
    $loc_stmt->execute([$id]);
    $locations = $loc_stmt->fetchAll();
    
    $dealers = $pdo->query("SELECT dealer_code, name FROM dealers ORDER BY dealer_code ASC")->fetchAll();
}

if (!$agent) {
    header("Location: view_agents.php");
    exit;
}

$message = '';
$status = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token($_POST['csrf_token'] ?? '');
    
    $dealer_code = $_POST['dealer_code'] ?? '';
    $agent_code = $_POST['agent_code'] ?? '';
    $name = $_POST['name'] ?? '';
    $nic_old = $_POST['nic_old'] ?? '';
    $nic_new = $_POST['nic_new'] ?? '';
    $birthday = $_POST['birthday'] ?? '';
    $province = $_POST['province_text'] ?? $_POST['province'] ?? '';
    $district = $_POST['district_text'] ?? $_POST['district'] ?? '';
    $ds_division = $_POST['ds_division_text'] ?? $_POST['ds_division'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $remarks = $_POST['remarks'] ?? '';
    
    $photo_path = $agent['photo'];
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $tmp_name = $_FILES['photo']['tmp_name'];
        $orig_name = $_FILES['photo']['name'];
        if (is_allowed_file($orig_name, $tmp_name)) {
            $ext = pathinfo($orig_name, PATHINFO_EXTENSION);
            $filename = 'agent_' . preg_replace('/[^a-zA-Z0-9]/', '', $agent_code) . '_' . uniqid() . '.' . $ext;
            $target = 'uploads/' . $filename;
            if (move_uploaded_file($tmp_name, $target)) {
                if ($photo_path && file_exists($photo_path)) unlink($photo_path);
                $photo_path = $target;
            }
        }
    }

    try {
        $pdo->beginTransaction();
        
        $check_stmt = $pdo->prepare("SELECT id FROM agents WHERE agent_code = ? AND id != ?");
        $check_stmt->execute([$agent_code, $id]);
        if ($check_stmt->fetch()) {
            throw new Exception("Agent Code '{$agent_code}' already exists for another agent.");
        }
        
        $upd = $pdo->prepare("UPDATE agents SET dealer_code=?, agent_code=?, name=?, nic_old=?, nic_new=?, birthday=?, province=?, district=?, ds_division=?, phone=?, photo=?, remarks=? WHERE id=?");
        $upd->execute([$dealer_code, $agent_code, $name, $nic_old, $nic_new, $birthday ?: null, $province, $district, $ds_division, $phone, $photo_path, $remarks, $id]);
        
        // Refresh addresses
        $pdo->prepare("DELETE FROM agent_addresses WHERE agent_id=?")->execute([$id]);
        if (isset($_POST['addresses']) && is_array($_POST['addresses'])) {
            $addr_stmt = $pdo->prepare("INSERT INTO agent_addresses (agent_id, address_text) VALUES (?, ?)");
            foreach ($_POST['addresses'] as $addr) {
                if (!empty($addr)) {
                    $addr_stmt->execute([$id, $addr]);
                }
            }
        }
        
        // Refresh locations
        $pdo->prepare("DELETE FROM agent_locations WHERE agent_id=?")->execute([$id]);
        if (isset($_POST['locations']) && is_array($_POST['locations'])) {
            $loc_stmt = $pdo->prepare("INSERT INTO agent_locations (agent_id, location_link) VALUES (?, ?)");
            foreach ($_POST['locations'] as $loc) {
                if (!empty($loc)) {
                    $loc_stmt->execute([$id, $loc]);
                }
            }
        }
        
        $pdo->commit();
        log_activity($pdo, "Updated Agent", "Code: $agent_code, Name: $name", "agent");
        header("Location: view_agents.php?msg=Agent updated successfully");
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "Error: " . $e->getMessage();
        $status = 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Agent - NLB</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="container wide">
        <div class="nav-bar" style="margin-bottom: 2rem;">
            <div class="nav-brand">
                <img src="assets/img/Logo.png" alt="NLB Logo">
                <div><h1>Edit Agent</h1></div>
            </div>
            <?php echo render_nav($pdo, $_SESSION['role']); ?>
        </div>

        <div class="form-panel">
            <form method="POST" enctype="multipart/form-data" id="agentForm">
                <?php csrf_input(); ?>
                <div class="form-group">
                    <label>Assign Dealer *</label>
                    <select name="dealer_code" required style="width: 100%; padding: 0.75rem; background: var(--nav-bg); border: 2px solid var(--glass-border); border-radius: 12px; color: white;">
                        <?php foreach($dealers as $d): ?>
                            <option value="<?php echo e($d['dealer_code']); ?>" <?php if($d['dealer_code'] === $agent['dealer_code']) echo 'selected'; ?>>
                                <?php echo e($d['dealer_code'] . " - " . $d['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                    <div class="form-group">
                        <label>Agent Code *</label>
                        <input type="text" name="agent_code" value="<?php echo e($agent['agent_code']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Full Name *</label>
                        <input type="text" name="name" value="<?php echo e($agent['name']); ?>" required>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                    <div class="form-group">
                        <label>Birthday</label>
                        <input type="date" name="birthday" value="<?php echo e($agent['birthday']); ?>">
                    </div>
                    <div class="form-group">
                        <label>Phone</label>
                        <input type="tel" name="phone" value="<?php echo e($agent['phone']); ?>">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                    <div class="form-group">
                        <label>NIC Old</label>
                        <input type="text" name="nic_old" value="<?php echo e($agent['nic_old']); ?>">
                    </div>
                    <div class="form-group">
                        <label>NIC New</label>
                        <input type="text" name="nic_new" value="<?php echo e($agent['nic_new']); ?>">
                    </div>
                </div>

                <div style="background: rgba(0,212,255,0.03); padding: 1.5rem; border-radius: 18px; border: 1px solid rgba(0,212,255,0.1); margin-bottom: 2rem;">
                    <label style="color: #00d4ff; display: flex; align-items: center; gap: 8px; font-weight: 600; margin-bottom: 1.5rem;">📍 Location Details</label>
                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                        <input type="hidden" name="province_text" id="province_text" value="<?php echo e($agent['province']); ?>">
                        <input type="hidden" name="district_text" id="district_text" value="<?php echo e($agent['district']); ?>">
                        <input type="hidden" name="ds_division_text" id="ds_division_text" value="<?php echo e($agent['ds_division']); ?>">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label style="font-size: 0.85rem; color: var(--text-muted);">Province (Selected: <?php echo e($agent['province']); ?>)</label>
                            <select id="province" name="province" onchange="loadDistricts()" style="width: 100%; padding: 0.75rem; background: var(--nav-bg); border: 2px solid var(--glass-border); border-radius: 12px; color: white;">
                                <option value="">-- Select to Change --</option>
                            </select>
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label style="font-size: 0.85rem; color: var(--text-muted);">District (Selected: <?php echo e($agent['district']); ?>)</label>
                            <select id="district" name="district" disabled onchange="loadDSDivisions()" style="width: 100%; padding: 0.75rem; background: var(--nav-bg); border: 2px solid var(--glass-border); border-radius: 12px; color: white;">
                                <option value="">-- Select to Change --</option>
                            </select>
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label style="font-size: 0.85rem; color: var(--text-muted);">DS Division (Selected: <?php echo e($agent['ds_division']); ?>)</label>
                            <select id="ds_division" name="ds_division" disabled style="width: 100%; padding: 0.75rem; background: var(--nav-bg); border: 2px solid var(--glass-border); border-radius: 12px; color: white;">
                                <option value="">-- Select to Change --</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>📝 Remarks / Comments</label>
                    <textarea name="remarks" id="remarks" placeholder="Enter any additional remarks here..."><?php echo e($agent['remarks']); ?></textarea>
                </div>

                <div class="form-group">
                    <label>📷 Agent Photo</label>
                    <?php if($agent['photo']): ?>
                        <div style="margin-bottom: 10px;">
                            <img src="<?php echo e($agent['photo']); ?>" style="width: 100px; height: 100px; border-radius: 12px; object-fit: cover; border: 2px solid var(--secondary-color);">
                            <p style="font-size: 0.75rem; color: var(--text-muted);">Current Photo</p>
                        </div>
                    <?php endif; ?>
                    <input type="file" name="photo" id="photo" accept="image/*" onchange="previewFile()">
                    <div id="photo_preview_wrap" style="display:none; margin-top: 10px;">
                        <img id="photo_preview" style="width: 100px; height: 100px; border-radius: 12px; object-fit: cover; border: 2px solid #4ade80;">
                        <p style="font-size: 0.75rem; color: #4ade80;">New Photo Preview</p>
                    </div>
                </div>

                <div class="form-group">
                     <label>Addresses</label>
                     <div id="address-container">
                        <?php foreach($addresses as $a): ?>
                            <div class="dynamic-field-group">
                                <textarea name="addresses[]"><?php echo e($a['address_text']); ?></textarea>
                            </div>
                        <?php endforeach; ?>
                     </div>
                     <button type="button" class="btn-add-more" onclick="addAddress()">+ Add Another</button>
                </div>

                <div class="form-group">
                     <label>Map Links</label>
                     <div id="location-container">
                        <?php foreach($locations as $l): ?>
                            <div class="dynamic-field-group">
                                <input type="text" name="locations[]" value="<?php echo e($l['location_link']); ?>">
                            </div>
                        <?php endforeach; ?>
                     </div>
                     <button type="button" class="btn-add-more" onclick="addLocation()">+ Add Another</button>
                </div>

                <button type="submit" class="btn-submit">Update Agent</button>
            </form>
        </div>
    </div>

    <script>
        let locationData = [];
        window.addEventListener('DOMContentLoaded', () => {
            fetch('data/data.json').then(res => res.json()).then(data => {
                locationData = data;
                populateProvinces();
            });
        });

        function populateProvinces() {
            const provSelect = document.getElementById('province');
            locationData.forEach((p, index) => {
                provSelect.innerHTML += `<option value="${index}">${p.province}</option>`;
            });
        }

        function loadDistricts() {
            const provIndex = document.getElementById('province').value;
            const distSelect = document.getElementById('district');
            const dsSelect = document.getElementById('ds_division');
            distSelect.innerHTML = '<option value="">-- Select District --</option>';
            dsSelect.innerHTML = '<option value="">-- Select DS Division --</option>';
            dsSelect.disabled = true;
            if (provIndex === "") { distSelect.disabled = true; return; }
            locationData[provIndex].districts.forEach((d, index) => {
                distSelect.innerHTML += `<option value="${index}">${d.district}</option>`;
            });
            distSelect.disabled = false;
        }

        function loadDSDivisions() {
            const provIndex = document.getElementById('province').value;
            const distIndex = document.getElementById('district').value;
            const dsSelect = document.getElementById('ds_division');
            dsSelect.innerHTML = '<option value="">-- Select DS Division --</option>';
            if (distIndex === "") { dsSelect.disabled = true; return; }
            locationData[provIndex].districts[distIndex].ds_divisions.forEach(ds => {
                dsSelect.innerHTML += `<option value="${ds.ds_division}">${ds.ds_division}</option>`;
            });
            dsSelect.disabled = false;
        }

        function previewFile() {
            const preview = document.getElementById('photo_preview');
            const wrap = document.getElementById('photo_preview_wrap');
            const file = document.getElementById('photo').files[0];
            const reader = new FileReader();
            reader.onloadend = function() {
                preview.src = reader.result;
                wrap.style.display = 'block';
            }
            if (file) reader.readAsDataURL(file);
            else { preview.src = ""; wrap.style.display = 'none'; }
        }

        function addAddress() {
            const div = document.createElement('div');
            div.className = 'dynamic-field-group';
            div.innerHTML = '<textarea name="addresses[]" placeholder="Enter Address"></textarea>';
            document.getElementById('address-container').appendChild(div);
        }

        function addLocation() {
            const div = document.createElement('div');
            div.className = 'dynamic-field-group';
            div.innerHTML = '<input type="text" name="locations[]" placeholder="Enter Link">';
            document.getElementById('location-container').appendChild(div);
        }

        document.getElementById('agentForm').addEventListener('submit', function() {
            const provSelect = document.getElementById('province');
            const distSelect = document.getElementById('district');
            const dsSelect = document.getElementById('ds_division');
            if(provSelect.value !== "") document.getElementById('province_text').value = provSelect.options[provSelect.selectedIndex].text;
            if(distSelect.value !== "") document.getElementById('district_text').value = distSelect.options[distSelect.selectedIndex].text;
            if(dsSelect.value !== "") document.getElementById('ds_division_text').value = dsSelect.options[dsSelect.selectedIndex].text;
        });
    </script>
    <?php include 'includes/footer.php'; ?>
</body>
</html>
