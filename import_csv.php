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
    
    if ($file['error'] === UPLOAD_ERR_OK) {
        $tmp_name = $file['tmp_name'];
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        
        if (strtolower($ext) === 'csv') {
            if (($handle = fopen($tmp_name, "r")) !== FALSE) {
                // Skip header row
                $header = fgetcsv($handle, 1000, ",");
                
                while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                    if (count($data) < 3) continue; // Skip empty or invalid rows
                    
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
</head>
<body>
    <div class="container">
        <div class="page-header">
            <img src="assets/img/Logo.png" alt="NLB Logo">
            <div class="page-header-text">
                <h1>Data Import Manager</h1>
                <p>Bulk upload sellers via CSV file</p>
            </div>
        </div>

        <div class="nav-bar">
            <?php echo render_nav($pdo, $_SESSION['role']); ?>
        </div>

        <?php if ($message): ?>
            <div class="message <?php echo $status; ?>" style="display: block;">
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
            <h2 style="margin-bottom: 1.5rem; font-size: 1.4rem;">📤 Upload CSV File</h2>
            <p style="color: var(--text-muted); margin-bottom: 2rem; font-size: 0.9rem;">
                Please ensure your CSV file follows the required format. You can download the template below if you haven't yet.
            </p>

            <form action="" method="POST" enctype="multipart/form-data">
                <?php csrf_input(); ?>
                <div class="form-group">
                    <label>Select CSV File</label>
                    <input type="file" name="csv_file" accept=".csv" required style="padding: 1rem; background: rgba(255,255,255,0.05); border: 2px dashed var(--glass-border); border-radius: 12px; width: 100%; color: var(--text-main);">
                </div>
                
                <button type="submit" class="btn-submit" style="margin-top: 1rem;">
                    ⚡ Start Bulk Import
                </button>
            </form>

            <div style="margin-top: 2rem; padding-top: 2rem; border-top: 1px solid var(--glass-border);">
                <h3 style="font-size: 1rem; margin-bottom: 1rem;">📝 Instructions & Template</h3>
                <ul style="color: var(--text-muted); font-size: 0.85rem; line-height: 1.8; margin-bottom: 1.5rem;">
                    <li>Keep the column headers exactly as they are in the template.</li>
                    <li><strong>Seller Code</strong>: This is used to identify existing sellers. If the code exists, the record will be updated.</li>
                    <li><strong>Dates</strong>: Use YYYY-MM-DD format (e.g., 1990-05-30).</li>
                    <li><strong>Sales Method</strong>: Use 'Ticket Counter' or 'Mobile Sales'.</li>
                </ul>
                <a href="sample_data.csv" download class="btn-submit" style="display: inline-block; padding: 0.6rem 1.2rem; background: rgba(255,255,255,0.1); font-size: 0.85rem; text-decoration: none; width: auto;">
                    📥 Download CSV Template
                </a>
            </div>
        </div>
    </div>
    <?php include 'includes/footer.php'; ?>
</body>
</html>
