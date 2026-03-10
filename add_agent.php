<?php
require_once 'includes/security.php';
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'tm'])) {
    header("Location: login.php");
    exit;
}

require_once 'includes/db_config.php';
require_once 'includes/get_nav.php';

$message = '';
$status = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token($_POST['csrf_token'] ?? '');
    
    $agent_code = $_POST['agent_code'] ?? '';
    $dealer_code = $_POST['dealer_code'] ?? '';
    $name = $_POST['name'] ?? '';
    $nic_old = $_POST['nic_old'] ?? '';
    $nic_new = $_POST['nic_new'] ?? '';
    $birthday = $_POST['birthday'] ?? '';
    $province = $_POST['province_text'] ?? '';
    $district = $_POST['district_text'] ?? '';
    $ds_division = $_POST['ds_division_text'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $remarks = $_POST['remarks'] ?? '';
    
    $photo_path = null;
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $tmp_name = $_FILES['photo']['tmp_name'];
        $orig_name = $_FILES['photo']['name'];
        if (is_allowed_file($orig_name, $tmp_name)) {
            $ext = pathinfo($orig_name, PATHINFO_EXTENSION);
            $filename = 'agent_' . preg_replace('/[^a-zA-Z0-9]/', '', $agent_code) . '_' . uniqid() . '.' . $ext;
            $target = 'uploads/' . $filename;
            if (move_uploaded_file($tmp_name, $target)) {
                $photo_path = $target;
            }
        }
    }

    try {
        $pdo->beginTransaction();
        
        // Check if agent_code already exists
        $check_stmt = $pdo->prepare("SELECT id FROM agents WHERE agent_code = ?");
        $check_stmt->execute([$agent_code]);
        if ($check_stmt->fetch()) {
            throw new Exception("Agent Code '{$agent_code}' already exists in the system. Please use a different code or edit the existing agent.");
        }
        
        $stmt = $pdo->prepare("INSERT INTO agents (agent_code, dealer_code, name, nic_old, nic_new, birthday, province, district, ds_division, phone, photo, remarks) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$agent_code, $dealer_code, $name, $nic_old, $nic_new, $birthday ?: null, $province, $district, $ds_division, $phone, $photo_path, $remarks]);
        $agent_id = $pdo->lastInsertId();
        
        // Addresses
        if (isset($_POST['addresses']) && is_array($_POST['addresses'])) {
            $addr_stmt = $pdo->prepare("INSERT INTO agent_addresses (agent_id, address_text) VALUES (?, ?)");
            foreach ($_POST['addresses'] as $addr) {
                if (!empty($addr)) {
                    $addr_stmt->execute([$agent_id, $addr]);
                }
            }
        }
        
        // Locations
        if (isset($_POST['locations']) && is_array($_POST['locations'])) {
            $loc_stmt = $pdo->prepare("INSERT INTO agent_locations (agent_id, location_link) VALUES (?, ?)");
            foreach ($_POST['locations'] as $loc) {
                if (!empty($loc)) {
                    $loc_stmt->execute([$agent_id, $loc]);
                }
            }
        }
        
        $pdo->commit();
        $message = "Agent added successfully!";
        $status = 'success';
        log_activity($pdo, "Added Agent", "Code: $agent_code, Name: $name", "agent");
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "Error: " . $e->getMessage();
        $status = 'error';
    }
}

// Fetch dealers for dropdown
$dealers = $pdo->query("SELECT dealer_code, name FROM dealers ORDER BY dealer_code ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Agent - NLB</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .dynamic-field-group { margin-bottom: 1rem; position: relative; }
        .btn-add-more { background: rgba(255,255,255,0.1); border: 1px dashed var(--glass-border); color: var(--text-main); padding: 0.5rem; border-radius: 8px; cursor: pointer; margin-bottom: 1rem; width: 100%; transition: 0.3s; }
        .btn-add-more:hover { background: rgba(255,255,255,0.2); }
        
        .entry-layout { display: grid; grid-template-columns: 1fr 380px; gap: 2rem; align-items: start; }
        .form-panel { background: var(--card-bg); border: 1px solid var(--glass-border); border-radius: 20px; padding: 2rem; }
        .info-panel { display: flex; flex-direction: column; gap: 1.25rem; }
        .info-card { background: var(--card-bg); border: 1px solid var(--glass-border); border-radius: 18px; padding: 1.5rem; }
        .preview-row { display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid rgba(255,255,255,0.05); font-size: 0.85rem; }
        .preview-label { color: var(--text-muted); }
        .preview-val { color: var(--text-main); font-weight: 600; text-align: right; }
        .preview-photo { width: 100%; height: 150px; border-radius: 12px; object-fit: cover; margin-top: 10px; border: 1px solid var(--glass-border); display: none; }
        
        .btn-action { width: 100%; padding: 0.8rem; border-radius: 12px; border: none; font-family: 'Outfit', sans-serif; font-weight: 600; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; transition: 0.3s; margin-top: 0.5rem; }
        .btn-pdf { background: #ff4444; color: white; }
        .btn-whatsapp { background: #25D366; color: white; }
        
        @media (max-width: 900px) { .entry-layout { grid-template-columns: 1fr; } }
    </style>
    <!-- Include html2pdf.js -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
</head>
<body>
    <div class="container wide">
        <div class="nav-bar" style="margin-bottom: 2rem;">
            <div class="nav-brand">
                <img src="assets/img/Logo.png" alt="NLB Logo">
                <div>
                    <h1>Agent Management</h1>
                    <p style="color: var(--text-muted); font-size: 0.75rem; margin: 0; opacity: 0.8;">
                        Register a new Agent Profile &nbsp;·&nbsp; Logged in as <span class="role-badge badge-<?php echo $_SESSION['role']; ?>" style="padding: 2px 8px; font-size: 0.65rem;"><?php echo $_SESSION['username']; ?></span>
                    </p>
                </div>
            </div>
            <?php echo render_nav($pdo, $_SESSION['role']); ?>
        </div>

        <?php if ($message): ?>
            <div class="message <?php echo $status; ?>" style="display: block;"><?php echo $message; ?></div>
        <?php endif; ?>

        <div class="entry-layout">
            <div class="form-panel">
                <form method="POST" enctype="multipart/form-data" id="agentForm">
                    <?php csrf_input(); ?>
                    <div class="form-group">
                        <label>Assign Dealer *</label>
                        <select name="dealer_code" id="dealer_code" required onchange="updatePreview()" style="width: 100%; padding: 0.75rem; background: var(--nav-bg); border: 2px solid var(--glass-border); border-radius: 12px; color: white;">
                            <option value="">-- Select Dealer --</option>
                            <?php foreach ($dealers as $d): ?>
                                <option value="<?php echo htmlspecialchars($d['dealer_code']); ?>">
                                    <?php echo htmlspecialchars($d['dealer_code'] . " - " . $d['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                        <div class="form-group">
                            <label>Agent Code *</label>
                            <input type="text" name="agent_code" id="agent_code" required oninput="updatePreview()">
                        </div>
                        <div class="form-group">
                            <label>Full Name *</label>
                            <input type="text" name="name" id="name" required oninput="updatePreview()">
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                        <div class="form-group">
                            <label>NIC Type</label>
                            <div class="radio-group">
                                <input type="radio" id="nt_old" name="nic_type" value="old" checked onchange="handleNicTypeChange(); updatePreview();">
                                <label for="nt_old" class="radio-pill"><span>📄 Old NIC</span></label>
                                <input type="radio" id="nt_new" name="nic_type" value="new" onchange="handleNicTypeChange(); updatePreview();">
                                <label for="nt_new" class="radio-pill"><span>🪪 New NIC</span></label>
                            </div>
                        </div>
                        <div class="form-group">
                            <label id="nic_label">NIC Number *</label>
                            <input type="text" id="nic_number" name="nic_input" required oninput="handleNicInput(); updatePreview();">
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                        <div class="form-group">
                            <label>Generated New NIC</label>
                            <input type="text" id="nic_new_display" name="nic_new" readonly style="background: rgba(255,255,255,0.02);">
                            <input type="hidden" id="nic_old_val" name="nic_old">
                        </div>
                        <div class="form-group">
                            <label>🎂 Birthday (Auto)</label>
                            <input type="date" id="birthday" name="birthday" required oninput="updatePreview()">
                        </div>
                    </div>



                    <div class="form-group">
                        <label>Phone Number *</label>
                        <div class="phone-input-wrap">
                            <div class="phone-prefix"><span class="flag">🇱🇰</span><span class="phone-prefix-code">+94</span></div>
                            <input type="tel" name="phone" id="phone" required placeholder="712345678" oninput="updatePreview()">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Home / Office Addresses *</label>
                        <div id="address-container">
                            <div class="dynamic-field-group">
                                <textarea name="addresses[]" required placeholder="Enter Address 1" oninput="updatePreview()"></textarea>
                            </div>
                        </div>
                        <button type="button" class="btn-add-more" onclick="addAddress()">+ Add Another Address</button>
                    </div>

                    <div class="form-group" style="background: rgba(0,212,255,0.03); padding: 1.5rem; border-radius: 18px; border: 1px solid rgba(0,212,255,0.1); margin-bottom: 2rem;">
                        <label style="color: #00d4ff; display: flex; align-items: center; gap: 8px; font-weight: 600; margin-bottom: 1.5rem;">📍 Location Status</label>
                        
                        <!-- Province, District, DS Division Row -->
                        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">
                            <input type="hidden" name="province_text" id="province_text">
                            <input type="hidden" name="district_text" id="district_text">
                            <input type="hidden" name="ds_division_text" id="ds_division_text">
                            <div class="form-group" style="margin-bottom: 0;">
                                <label style="font-size: 0.85rem; color: var(--text-muted);">Province Name (පළාත) *</label>
                                <select name="province" id="province" required onchange="loadDistricts(); updatePreview()" style="width: 100%; padding: 0.75rem; background: var(--nav-bg); border: 2px solid var(--glass-border); border-radius: 12px; color: white;">
                                    <option value="">-- Select Province --</option>
                                </select>
                            </div>
                            <div class="form-group" style="margin-bottom: 0;">
                                <label style="font-size: 0.85rem; color: var(--text-muted);">District Name (දිස්ත්රික්කය) *</label>
                                <select name="district" id="district" required disabled onchange="loadDSDivisions(); updatePreview()" style="width: 100%; padding: 0.75rem; background: var(--nav-bg); border: 2px solid var(--glass-border); border-radius: 12px; color: white;">
                                    <option value="">-- Select District --</option>
                                </select>
                            </div>
                            <div class="form-group" style="margin-bottom: 0;">
                                <label style="font-size: 0.85rem; color: var(--text-muted);">DS Division Name (ප්රාදේශීය ලේකම් කොට්ඨාසය) *</label>
                                <select name="ds_division" id="ds_division" required disabled onchange="updatePreview()" style="width: 100%; padding: 0.75rem; background: var(--nav-bg); border: 2px solid var(--glass-border); border-radius: 12px; color: white;">
                                    <option value="">-- Select DS Division --</option>
                                </select>
                            </div>
                        </div>

                        <div id="location-container">
                            <div class="dynamic-field-group">
                                <div style="display: flex; gap: 10px; margin-bottom: 5px;">
                                    <input type="text" name="locations[]" id="location_link_0" required placeholder="Enter Google Maps Link 1" oninput="updatePreview()" style="flex: 1;">
                                    <button type="button" onclick="getLocationLink(0)" class="btn-gps" style="background: #00d4ff; color: #000; border: none; padding: 0.75rem 1rem; border-radius: 12px; font-weight: 600; cursor: pointer; white-space: nowrap;">📍 Get GPS</button>
                                </div>
                            </div>
                        </div>
                        <p id="location_status_0" style="font-size: 0.72rem; color: var(--text-muted); margin-top: 6px;">Use current GPS location or paste map link.</p>
                        <button type="button" class="btn-add-more" onclick="addLocation()" style="margin-top: 10px;">+ Add Another Location Link</button>
                    </div>

                    <div class="form-group">
                        <label>📝 Remarks / Comments</label>
                        <textarea name="remarks" id="remarks" placeholder="Enter any additional remarks here..." oninput="updatePreview()"></textarea>
                    </div>

                    <div class="form-group">
                        <label>📷 Agent Photo *</label>
                        <input type="file" name="photo" id="photo" accept="image/*" required onchange="previewFile()">
                    </div>

                    <button type="submit" class="btn-submit">Save Agent Details</button>
                </form>
            </div>

            <!-- Right: Preview Panel -->
            <div class="info-panel">
                <div class="info-card" id="previewPanel">
                    <h3>👁️ Live Preview</h3>
                    <div id="pdf-content" style="padding: 10px;">
                        <div style="text-align: center; margin-bottom: 15px;">
                            <img src="assets/img/Logo.png" style="height: 40px; margin-bottom: 5px;">
                            <div style="font-weight: 700; font-size: 1.1rem; color: var(--secondary-color);">Agent Registration</div>
                        </div>
                        
                        <div class="preview-row">
                            <span class="preview-label">Agent Code:</span>
                            <span class="preview-val" id="p_code">-</span>
                        </div>
                        <div class="preview-row">
                            <span class="preview-label">Dealer:</span>
                            <span class="preview-val" id="p_dealer">-</span>
                        </div>
                        <div class="preview-row">
                            <span class="preview-label">Name:</span>
                            <span class="preview-val" id="p_name">-</span>
                        </div>
                        <div class="preview-row">
                            <span class="preview-label">NIC:</span>
                            <span class="preview-val" id="p_nic">-</span>
                        </div>
                        <div class="preview-row">
                            <span class="preview-label">Birthday:</span>
                            <span class="preview-val" id="p_birthday">-</span>
                        </div>
                        <div class="preview-row">
                            <span class="preview-label">Area:</span>
                            <span class="preview-val" id="p_area">-</span>
                        </div>
                        <div class="preview-row">
                            <span class="preview-label">Phone:</span>
                            <span class="preview-val" id="p_phone">-</span>
                        </div>
                        <div class="preview-row" style="flex-direction: column; align-items: flex-start;">
                            <span class="preview-label">Address:</span>
                            <span class="preview-val" id="p_address" style="text-align: left; width: 100%; margin-top: 4px;">-</span>
                        </div>
                        <div class="preview-row" style="flex-direction: column; align-items: flex-start;">
                            <span class="preview-label">Remarks:</span>
                            <span class="preview-val" id="p_remarks" style="text-align: left; width: 100%; margin-top: 4px;">-</span>
                        </div>
                        
                        <img id="p_photo" class="preview-photo">
                    </div>

                    <button type="button" class="btn-action btn-pdf" onclick="downloadPDF()">
                        <span>📄</span> Download PDF
                    </button>
                    <button type="button" class="btn-action btn-whatsapp" onclick="shareWhatsApp()">
                        <span>💬</span> Share to WhatsApp
                    </button>
                </div>
                
                <div class="info-card">
                    <h3>💡 Quick Tips</h3>
                    <ul class="tip-list">
                        <li>✅ Ensure correct Dealer is selected.</li>
                        <li>✅ Check NIC digits carefully.</li>
                        <li>✅ Mobile numbers must be 9 digits after prefix.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <script>
        function handleNicTypeChange() {
            const type = document.querySelector('input[name="nic_type"]:checked').value;
            const input = document.getElementById('nic_number');
            const label = document.getElementById('nic_label');
            if (type === 'old') {
                label.textContent = 'Old NIC (e.g. 905123456V)';
                input.maxLength = 10;
            } else {
                label.textContent = 'New NIC (12 digits)';
                input.maxLength = 12;
            }
            handleNicInput();
        }

        function handleNicInput() {
            const type = document.querySelector('input[name="nic_type"]:checked').value;
            const val = document.getElementById('nic_number').value.trim().toUpperCase();
            const nicNewBox = document.getElementById('nic_new_display');
            const nicOldHidden = document.getElementById('nic_old_val');
            
            if (type === 'old' && val.length >= 9) {
                const year = "19" + val.substring(0, 2);
                const rest = val.substring(2, 5);
                const serial = val.substring(5, 9);
                const newNic = year + rest + "0" + serial;
                nicNewBox.value = newNic;
                nicOldHidden.value = val;
                extractBirthday(newNic);
            } else if (type === 'new' && val.length === 12) {
                nicNewBox.value = val;
                nicOldHidden.value = '';
                extractBirthday(val);
            }
        }

        function extractBirthday(nic12) {
            if (nic12.length !== 12) return;
            const year = parseInt(nic12.substring(0, 4));
            let days = parseInt(nic12.substring(4, 7));
            if (days > 500) days -= 500;
            const months = [{d:31},{d:29},{d:31},{d:30},{d:31},{d:30},{d:31},{d:31},{d:30},{d:31},{d:30},{d:31}];
            let m=0, d=days;
            for(let i=0; i<months.length; i++) {
                if(d <= months[i].d) { m=i+1; break; }
                d -= months[i].d;
            }
            document.getElementById('birthday').value = `${year}-${String(m).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
        }

        function addAddress() {
            const container = document.getElementById('address-container');
            const div = document.createElement('div');
            div.className = 'dynamic-field-group';
            div.innerHTML = '<textarea name="addresses[]" placeholder="Enter Another Address" oninput="updatePreview()"></textarea>';
            container.appendChild(div);
            updatePreview();
        }

        function addLocation() {
            const container = document.getElementById('location-container');
            const index = container.children.length;
            const div = document.createElement('div');
            div.className = 'dynamic-field-group';
            div.innerHTML = `
                <div style="display: flex; gap: 10px; margin-bottom: 5px;">
                    <input type="text" name="locations[]" id="location_link_${index}" placeholder="Enter Another Google Maps Link" oninput="updatePreview()" style="flex: 1;">
                    <button type="button" onclick="getLocationLink(${index})" class="btn-gps" style="background: #00d4ff; color: #000; border: none; padding: 0.75rem 1rem; border-radius: 12px; font-weight: 600; cursor: pointer; white-space: nowrap;">📍 Get GPS</button>
                </div>
                <p id="location_status_${index}" style="font-size: 0.72rem; color: var(--text-muted); margin-top: 6px;">Use current GPS location or paste map link.</p>
            `;
            container.appendChild(div);
            updatePreview();
        }

        function getLocationLink(index) {
            const statusText = document.getElementById(`location_status_${index}`);
            const linkInput = document.getElementById(`location_link_${index}`);
            
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
                    statusText.style.color = "#f87171";
                    switch(error.code) {
                        case error.PERMISSION_DENIED:
                            statusText.textContent = "User denied the request for Geolocation.";
                            break;
                        case error.POSITION_UNAVAILABLE:
                            statusText.textContent = "Location information is unavailable.";
                            break;
                        case error.TIMEOUT:
                            statusText.textContent = "The request to get user location timed out.";
                            break;
                        case error.UNKNOWN_ERROR:
                            statusText.textContent = "An unknown error occurred.";
                            break;
                    }
                },
                { enableHighAccuracy: true, timeout: 5000, maximumAge: 0 }
            );
        }

        function previewFile() {
            const preview = document.getElementById('p_photo');
            const file = document.getElementById('photo').files[0];
            const reader = new FileReader();

            reader.onloadend = function () {
                preview.src = reader.result;
                preview.style.display = 'block';
            }

            if (file) {
                reader.readAsDataURL(file);
            } else {
                preview.src = "";
                preview.style.display = 'none';
            }
        }

        function updatePreview() {
            document.getElementById('p_code').innerText = document.getElementById('agent_code').value || '-';
            
            const dealerSelect = document.getElementById('dealer_code');
            document.getElementById('p_dealer').innerText = dealerSelect.options[dealerSelect.selectedIndex].text !== '-- Select Dealer --' ? dealerSelect.options[dealerSelect.selectedIndex].text : '-';
            
            document.getElementById('p_name').innerText = document.getElementById('name').value || '-';
            document.getElementById('p_nic').innerText = document.getElementById('nic_new_display').value || document.getElementById('nic_number').value || '-';
            document.getElementById('p_birthday').innerText = document.getElementById('birthday').value || '-';
            
            const provSelect = document.getElementById('province');
            const distSelect = document.getElementById('district');
            const dsSelect = document.getElementById('ds_division');

            const provText = provSelect.options[provSelect.selectedIndex]?.text || '';
            const distText = distSelect.options[distSelect.selectedIndex]?.text || '';
            const dsText = dsSelect && dsSelect.selectedIndex >= 0 ? dsSelect.options[dsSelect.selectedIndex]?.text : '';
            
            let areaText = '';
            if (provText && provText !== '-- Select Province --') {
                areaText = provText;
                if (distText && distText !== '-- Select District --') areaText += `, ${distText}`;
                if (dsText && dsText !== '-- Select DS Division --') areaText += `, ${dsText}`;
            }
            document.getElementById('p_area').innerText = areaText || '-';
            
            document.getElementById('p_phone').innerText = document.getElementById('phone').value ? '+94 ' + document.getElementById('phone').value : '-';
            
            const addrs = Array.from(document.querySelectorAll('textarea[name="addresses[]"]'))
                .map(t => t.value.trim())
                .filter(v => v !== '')
                .join(' | ');
            document.getElementById('p_address').innerText = addrs || '-';

            document.getElementById('p_remarks').innerText = document.getElementById('remarks').value || '-';
        }

        function downloadPDF() {
            const element = document.getElementById('pdf-content');
            const opt = {
                margin: 0.5,
                filename: 'agent_' + (document.getElementById('agent_code').value || 'profile') + '.pdf',
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { scale: 2, useCORS: true },
                jsPDF: { unit: 'in', format: 'letter', orientation: 'portrait' }
            };
            html2pdf().set(opt).from(element).save();
        }

        function shareWhatsApp() {
            const code = document.getElementById('agent_code').value;
            const name = document.getElementById('name').value;
            const phone = document.getElementById('phone').value;
            const nic = document.getElementById('nic_new_display').value;
            
            const text = `*New Agent Registration*\n\n` +
                         `*Code:* ${code}\n` +
                         `*Name:* ${name}\n` +
                         `*NIC:* ${nic}\n` +
                         `*Phone:* +94 ${phone}\n\n` +
                         `Please find the details attached in the system.`;
            
            const whatsappUrl = `https://wa.me/?text=${encodeURIComponent(text)}`;
            window.open(whatsappUrl, '_blank');
        }
        
        let locationData = [];
        window.addEventListener('DOMContentLoaded', () => {
            fetch('data/data.json')
                .then(res => res.json())
                .then(data => {
                    locationData = data;
                    populateProvinces();
                });
        });

        function populateProvinces() {
            const provSelect = document.getElementById('province');
            provSelect.innerHTML = '<option value="">-- Select Province --</option>';
            locationData.forEach((p, index) => {
                provSelect.innerHTML += `<option value="${index}">${p.province}</option>`;
            });
        }

        function loadDistricts() {
            const provIndex = document.getElementById('province').value;
            const distSelect = document.getElementById('district');
            const dsSelect = document.getElementById('ds_division');

            distSelect.innerHTML = '<option value="">-- Select District --</option>';
            distSelect.disabled = true;

            if (dsSelect) {
                dsSelect.innerHTML = '<option value="">-- Select DS Division --</option>';
                dsSelect.disabled = true;
            }

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

            dsSelect.innerHTML = '<option value="">-- Select DS Division --</option>';
            dsSelect.disabled = true;

            if (provIndex === "" || distIndex === "") return;

            const dsDivisions = locationData[provIndex].districts[distIndex].ds_divisions;
            dsDivisions.forEach((ds, index) => {
                dsSelect.innerHTML += `<option value="${index}">${ds.ds_division}</option>`;
            });
            dsSelect.disabled = false;
        }

        document.getElementById('agentForm').addEventListener('submit', function(e) {
            const provSelect = document.getElementById('province');
            const distSelect = document.getElementById('district');
            const dsSelect = document.getElementById('ds_division');
            
            document.getElementById('province_text').value = provSelect.options[provSelect.selectedIndex]?.text || '';
            document.getElementById('district_text').value = distSelect.options[distSelect.selectedIndex]?.text || '';
            
            if (dsSelect && dsSelect.selectedIndex >= 0) {
                document.getElementById('ds_division_text').value = dsSelect.options[dsSelect.selectedIndex]?.text || '';
            }
        });

        // Initial run
        updatePreview();
    </script>
    <?php include 'includes/footer.php'; ?>
</body>
</html>
