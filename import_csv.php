<?php
require_once 'includes/security.php';
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

require_once 'includes/db_config.php';
require_once 'includes/get_nav.php';

$message = '';
$status = '';
$import_stats = ['total' => 0, 'inserted' => 0, 'updated' => 0, 'errors' => 0];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    verify_csrf_token($_POST['csrf_token'] ?? '');
    
    $file = $_FILES['csv_file'];
    $import_type = $_POST['import_type'] ?? 'seller';
    
    if ($file['error'] === UPLOAD_ERR_OK) {
        $tmp_name = $file['tmp_name'];
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        
        if (strtolower($ext) === 'csv') {
            if (($handle = fopen($tmp_name, "r")) !== FALSE) {
                // Skip header row
                $header = fgetcsv($handle, 1000, ",");
                
                while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                    // Skip empty rows completely
                    if (empty(array_filter($data))) continue;
                    
                    if ($import_type === 'dealer') {
                        if (count($data) < 2) continue;
                        $dealer_code = trim($data[0] ?? '');
                        $name = trim($data[1] ?? '');
                        
                        if (empty($dealer_code)) continue;
                        
                        $import_stats['total']++;
                        try {
                            $stmt = $pdo->prepare("SELECT id FROM dealers WHERE dealer_code = ?");
                            $stmt->execute([$dealer_code]);
                            $existing = $stmt->fetch();
                            
                            if ($existing) {
                                $pdo->prepare("UPDATE dealers SET name = ? WHERE id = ?")->execute([$name, $existing['id']]);
                                $import_stats['updated']++;
                            } else {
                                $pdo->prepare("INSERT INTO dealers (dealer_code, name) VALUES (?, ?)")->execute([$dealer_code, $name]);
                                $import_stats['inserted']++;
                            }
                        } catch (PDOException $e) {
                            $import_stats['errors']++;
                        }
                        
                    } elseif ($import_type === 'agent') {
                        if (count($data) < 3) continue;
                        $dealer_code = trim($data[0] ?? '');
                        $agent_code = trim($data[1] ?? '');
                        $name = trim($data[2] ?? '');
                        $remarks = trim($data[3] ?? '');
                        
                        if (empty($agent_code)) continue;
                        
                        $import_stats['total']++;
                        try {
                            $stmt = $pdo->prepare("SELECT id FROM agents WHERE agent_code = ?");
                            $stmt->execute([$agent_code]);
                            $existing = $stmt->fetch();
                            
                            if ($existing) {
                                $pdo->prepare("UPDATE agents SET dealer_code = ?, name = ?, remarks = ? WHERE id = ?")->execute([$dealer_code, $name, $remarks, $existing['id']]);
                                $import_stats['updated']++;
                            } else {
                                $pdo->prepare("INSERT INTO agents (dealer_code, agent_code, name, remarks) VALUES (?, ?, ?, ?)")->execute([$dealer_code, $agent_code, $name, $remarks]);
                                $import_stats['inserted']++;
                            }
                        } catch (PDOException $e) {
                            $import_stats['errors']++;
                        }
                        
                    } else {
                        // Seller Import (existing logic)
                        if (count($data) < 3) continue; // Skip if less than required
                        
                        $import_stats['total']++;
                        
                        $dealer_code   = trim($data[0] ?? '');
                        $agent_code    = trim($data[1] ?? '');
                        $seller_code   = trim($data[2] ?? '');
                        $seller_name   = trim($data[3] ?? '');
                        $nic_type      = strtolower(trim($data[4] ?? ''));
                        $nic_old       = trim($data[5] ?? '');
                        $nic_new       = trim($data[6] ?? '');
                        $birthday      = trim($data[7] ?? '');
                        $province      = trim($data[8] ?? '');
                        $district      = trim($data[9] ?? '');
                        $ds_division   = trim($data[10] ?? '');
                        $gn_division   = trim($data[11] ?? '');
                        $address       = trim($data[12] ?? '');
                        $sales_method  = trim($data[13] ?? '');
                        $location_link = trim($data[14] ?? '');

                        // Validation for sales method
                        if (stripos($sales_method, 'booth') !== false) $sales_method = 'Ticket Counter';
                        if (stripos($sales_method, 'counter') !== false) $sales_method = 'Ticket Counter';
                        if (stripos($sales_method, 'mobile') !== false) $sales_method = 'Mobile Sales';

                        try {
                            // Check if exists
                            $stmt = $pdo->prepare("SELECT id FROM counters WHERE seller_code = ?");
                            $stmt->execute([$seller_code]);
                            $existing = $stmt->fetch();

                            if ($existing) {
                                // Update
                                $update_sql = "UPDATE counters SET 
                                    dealer_code = ?, agent_code = ?, seller_name = ?, nic_type = ?, 
                                    nic_old = ?, nic_new = ?, birthday = ?, province = ?, 
                                    district = ?, ds_division = ?, gn_division = ?, address = ?, 
                                    sales_method = ?, location_link = ? 
                                    WHERE id = ?";
                                $pdo->prepare($update_sql)->execute([
                                    $dealer_code, $agent_code, $seller_name, $nic_type,
                                    $nic_old, $nic_new, $birthday ?: null, $province,
                                    $district, $ds_division, $gn_division, $address,
                                    $sales_method, $location_link, $existing['id']
                                ]);
                                $import_stats['updated']++;
                            } else {
                                // Insert
                                $insert_sql = "INSERT INTO counters (
                                    dealer_code, agent_code, seller_code, seller_name, nic_type, 
                                    nic_old, nic_new, birthday, province, district, 
                                    ds_division, gn_division, address, sales_method, 
                                    location_link, added_by
                                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                                $pdo->prepare($insert_sql)->execute([
                                    $dealer_code, $agent_code, $seller_code, $seller_name, $nic_type,
                                    $nic_old, $nic_new, $birthday ?: null, $province, $district,
                                    $ds_division, $gn_division, $address, $sales_method,
                                    $location_link, $_SESSION['username']
                                ]);
                                $import_stats['inserted']++;
                            }
                        } catch (PDOException $e) {
                            $import_stats['errors']++;
                        }
                    }
                }
                fclose($handle);
                $message = "Import processing completed!";
                $status = 'success';
            } else {
                $message = "Error: Could not open the uploaded file.";
                $status = 'error';
            }
        } else {
            $message = "Error: Please upload a valid CSV file.";
            $status = 'error';
        }
    } else {
        $message = "Error: File upload failed.";
        $status = 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import Data - NLB</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <script>
        function updateInstructions() {
            var type = document.getElementById("import_type").value;
            document.getElementById("inst_dealer").style.display = type === 'dealer' ? 'block' : 'none';
            document.getElementById("inst_agent").style.display = type === 'agent' ? 'block' : 'none';
            document.getElementById("inst_seller").style.display = type === 'seller' ? 'block' : 'none';
        }
        window.onload = updateInstructions;
    </script>
</head>
<body>
    <div class="container wide">
        <div class="nav-bar">
            <div class="nav-brand">
                <img src="assets/img/Logo.png" alt="NLB Logo">
                <div>
                    <h1>Data Import Manager</h1>
                    <p style="color: var(--text-muted); font-size: 0.75rem; margin: 0;">Bulk upload Dealers, Agents, or Sellers via CSV file</p>
                </div>
            </div>
            <?php echo render_nav($pdo, $_SESSION['role']); ?>
        </div>

        <?php if ($message): ?>
            <div class="message <?php echo $status; ?>" style="display: block; margin-bottom: 2rem;">
                <strong><?php echo $message; ?></strong><br>
                <div style="margin-top: 10px; font-size: 0.9rem;">
                    Total Rows: <?php echo $import_stats['total']; ?> | 
                    Inserted: <?php echo $import_stats['inserted']; ?> | 
                    Updated: <?php echo $import_stats['updated']; ?> | 
                    Errors: <?php echo $import_stats['errors']; ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="setup-card" style="margin-top: 2rem; padding: 2rem; background: var(--card-bg); border-radius: 20px; border: 1px solid var(--glass-border);">
            <h2 style="margin-bottom: 1.5rem; font-size: 1.4rem; color: var(--secondary-color);">📤 Upload CSV File</h2>
            <p style="color: var(--text-muted); margin-bottom: 2rem; font-size: 0.9rem;">
                Please ensure your CSV file follows the required format for the selected import type.
            </p>

            <form action="" method="POST" enctype="multipart/form-data">
                <?php csrf_input(); ?>
                
                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label style="font-weight: 600;">Import Data Type</label>
                    <select name="import_type" id="import_type" onchange="updateInstructions()" required style="padding: 1rem; background: var(--input-bg); border: 2px solid var(--glass-border); border-radius: 12px; width: 100%; color: var(--text-main); font-family: 'Outfit', sans-serif;">
                        <option value="dealer">Dealers List</option>
                        <option value="agent">Agents List</option>
                        <option value="seller" selected>Counters / Sellers</option>
                    </select>
                </div>
                
                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label style="font-weight: 600;">Select CSV File</label>
                    <input type="file" name="csv_file" accept=".csv" required style="padding: 1rem; background: rgba(255,255,255,0.05); border: 2px dashed var(--glass-border); border-radius: 12px; width: 100%; color: var(--text-main);">
                </div>
                
                <button type="submit" class="btn-submit" style="margin-top: 1rem; display: inline-flex; align-items: center; justify-content: center; gap: 8px;">
                    ⚡ Start Bulk Import
                </button>
            </form>

            <div style="margin-top: 2rem; padding-top: 2rem; border-top: 1px solid var(--glass-border);">
                <h3 style="font-size: 1rem; margin-bottom: 1rem; font-weight: 600;">📝 CSV Formatting Instructions</h3>
                
                <!-- Dealer Instructions -->
                <div id="inst_dealer" style="display: none; color: var(--text-muted); font-size: 0.85rem; line-height: 1.8; margin-bottom: 1.5rem; background: rgba(255,255,255,0.03); padding: 15px; border-radius: 12px;">
                    <strong style="color: var(--text-main);">Dealer CSV Structure (in this exact order):</strong>
                    <ol style="margin-top: 5px; margin-left: 20px;">
                        <li><strong>Dealer Code</strong> (e.g., D001) - <i>Required, used to identify or update existing dealers.</i></li>
                        <li><strong>Name</strong> (e.g., Sunil Perera)</li>
                    </ol>
                    <p style="margin-top: 10px; opacity: 0.8;"><i>Note: Always include a header row (it will be skipped).</i></p>
                </div>

                <!-- Agent Instructions -->
                <div id="inst_agent" style="display: none; color: var(--text-muted); font-size: 0.85rem; line-height: 1.8; margin-bottom: 1.5rem; background: rgba(255,255,255,0.03); padding: 15px; border-radius: 12px;">
                    <strong style="color: var(--text-main);">Agent CSV Structure (in this exact order):</strong>
                    <ol style="margin-top: 5px; margin-left: 20px;">
                        <li><strong>Dealer Code</strong> (e.g., D001)</li>
                        <li><strong>Agent Code</strong> (e.g., A001) - <i>Required, used to identify or update existing agents.</i></li>
                        <li><strong>Name</strong> (e.g., Nimal Fernando)</li>
                        <li><strong>Remarks</strong> (Optional text)</li>
                    </ol>
                    <p style="margin-top: 10px; opacity: 0.8;"><i>Note: Always include a header row (it will be skipped).</i></p>
                </div>

                <!-- Seller Instructions -->
                <div id="inst_seller" style="display: none; color: var(--text-muted); font-size: 0.85rem; line-height: 1.8; margin-bottom: 1.5rem; background: rgba(255,255,255,0.03); padding: 15px; border-radius: 12px;">
                    <strong style="color: var(--text-main);">Seller/Counter CSV Structure:</strong>
                    <ul style="margin-top: 5px; margin-left: 20px;">
                        <li>Keep the column headers exactly as they are in the seller template.</li>
                        <li><strong>Seller Code</strong>: This is used to identify existing sellers. If the code exists, the record will be updated.</li>
                        <li><strong>Dates</strong>: Use YYYY-MM-DD format (e.g., 1990-05-30).</li>
                        <li><strong>Sales Method</strong>: Use 'Ticket Counter' or 'Mobile Sales'.</li>
                    </ul>
                    <a href="sample_data.csv" download class="btn-submit" style="display: inline-block; padding: 0.6rem 1.2rem; background: rgba(255,255,255,0.1); font-size: 0.85rem; text-decoration: none; width: auto; margin-top: 10px;">
                        📥 Download Seller CSV Template
                    </a>
                </div>
            </div>
        </div>
    </div>
    <?php include 'includes/footer.php'; ?>
</body>
</html>
