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

// Handle Deletion
if (isset($_POST['delete_id'])) {
    verify_csrf_token($_POST['csrf_token'] ?? '');
    $id = (int)$_POST['delete_id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM counters WHERE id = ?");
        $stmt->execute([$id]);
        $message = "Duplicate record ID $id removed successfully.";
        $status = 'success';
        log_activity($pdo, "Deleted Duplicate Seller", "ID: $id", "seller");
    } catch (PDOException $e) {
        $message = "Error: " . $e->getMessage();
        $status = 'error';
    }
}

// Handle Auto-Cleanup (Keep oldest, delete newer duplicates)
if (isset($_POST['auto_cleanup'])) {
    verify_csrf_token($_POST['csrf_token'] ?? '');
    try {
        // Find duplicate groups (by seller_name and agent_code)
        $stmt = $pdo->query("SELECT seller_name, agent_code, COUNT(*) as count FROM counters GROUP BY seller_name, agent_code HAVING count > 1");
        $dupes = $stmt->fetchAll();
        $deleted_count = 0;

        foreach ($dupes as $d) {
            // Get all IDs for this group, ordered by created_at (keep the oldest)
            $stmt2 = $pdo->prepare("SELECT id FROM counters WHERE seller_name = ? AND agent_code = ? ORDER BY created_at ASC");
            $stmt2->execute([$d['seller_name'], $d['agent_code']]);
            $ids = $stmt2->fetchAll(PDO::FETCH_COLUMN);
            
            array_shift($ids); // Remove the first one (oldest) from the deletion list
            
            if (!empty($ids)) {
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $pdo->prepare("DELETE FROM counters WHERE id IN ($placeholders)")->execute($ids);
                $deleted_count += count($ids);
            }
        }
        $message = "Auto-cleanup finished. Removed $deleted_count duplicate records.";
        $status = 'success';
        log_activity($pdo, "Auto-Cleanup Duplicates", "Removed $deleted_count records", "system");
    } catch (PDOException $e) {
        $message = "Error: " . $e->getMessage();
        $status = 'error';
    }
}

        // Handle Group Cleanup (Keep selected ID, delete others in the group)
        if (isset($_POST['keep_id'])) {
            verify_csrf_token($_POST['csrf_token'] ?? '');
            $keep_id = (int)$_POST['keep_id'];
            $name = $_POST['target_name'];
            $agent = $_POST['target_agent'];
            
            try {
                $stmt = $pdo->prepare("DELETE FROM counters WHERE seller_name = ? AND agent_code = ? AND id != ?");
                $stmt->execute([$name, $agent, $keep_id]);
                $count = $stmt->rowCount();
                $message = "Group cleaned. Kept ID $keep_id and removed $count other records.";
                $status = 'success';
                log_activity($pdo, "Cleaned Duplicate Group", "Kept ID: $keep_id, Removed: $count", "seller");
            } catch (PDOException $e) {
                $message = "Error: " . $e->getMessage();
                $status = 'error';
            }
        }

        // Fetch Duplicates for display
        // Criteria 1: Renamed duplicates (containing _DUP)
        $renamed_dupes = $pdo->query("SELECT * FROM counters WHERE seller_code LIKE '%_DUP%' ORDER BY seller_code ASC")->fetchAll();

        // Criteria 2: Name & Agent matches - GROUPED
        $stmt = $pdo->query("SELECT seller_name, agent_code, COUNT(*) as count FROM counters GROUP BY seller_name, agent_code HAVING count > 1");
        $dupe_groups = $stmt->fetchAll();
        $name_dupes_grouped = [];
        foreach ($dupe_groups as $group) {
            $stmt2 = $pdo->prepare("SELECT * FROM counters WHERE seller_name = ? AND agent_code = ? ORDER BY created_at ASC");
            $stmt2->execute([$group['seller_name'], $group['agent_code']]);
            $name_dupes_grouped[] = [
                'name' => $group['seller_name'],
                'agent' => $group['agent_code'],
                'records' => $stmt2->fetchAll()
            ];
        }

        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Manage Duplicates - NLB</title>
            <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
            <link rel="stylesheet" href="assets/css/style.css">
            <style>
                .dupe-card { background: var(--card-bg); border: 1px solid var(--glass-border); border-radius: 15px; margin-bottom: 2rem; padding: 1.5rem; }
                .dupe-badge { background: #ff4444; color: white; padding: 2px 8px; border-radius: 4px; font-size: 0.7rem; font-weight: bold; }
                table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
                th, td { text-align: left; padding: 12px; border-bottom: 1px solid rgba(255,255,255,0.05); }
                th { color: var(--text-muted); font-weight: 600; font-size: 0.85rem; text-transform: uppercase; }
                .btn-delete { background: #ff4444; color: white; border: none; padding: 5px 10px; border-radius: 6px; cursor: pointer; font-size: 0.8rem; }
                .btn-keep { background: var(--secondary-color); color: var(--dark); border: none; padding: 5px 10px; border-radius: 6px; cursor: pointer; font-size: 0.8rem; font-weight: 600; }
                .btn-auto { background: var(--secondary-color); color: var(--dark); border: none; padding: 10px 20px; border-radius: 10px; cursor: pointer; font-weight: 600; margin-bottom: 2rem; }
                .group-header { background: rgba(255,255,255,0.05); padding: 10px 15px; border-radius: 8px; margin-top: 2rem; border-left: 4px solid var(--secondary-color); }
            </style>
        </head>
        <body>
            <div class="container wide">
                <div class="nav-bar">
                    <div class="nav-brand">
                        <img src="assets/img/Logo.png" alt="NLB Logo">
                        <div>
                            <h1>Duplicate Manager</h1>
                            <p style="color: var(--text-muted); font-size: 0.75rem; margin: 0;">Clean up duplicate seller records from the system</p>
                        </div>
                    </div>
                    <?php echo render_nav($pdo, $_SESSION['role']); ?>
                </div>

                <?php if ($message): ?>
                    <div class="message <?php echo $status; ?>" style="display: block;"><?php echo $message; ?></div>
                <?php endif; ?>

                <div style="display: flex; justify-content: flex-end; gap: 1rem;">
                    <form method="POST" onsubmit="return confirm('Are you sure? This will keep the oldest record and delete all other duplicates automatically based on Name and Agent Code.')">
                         <?php csrf_input(); ?>
                         <button type="submit" name="auto_cleanup" class="btn-auto">⚡ Smart Auto-Cleanup All</button>
                    </form>
                </div>

                <div class="dupe-card">
                    <h2 style="font-size: 1.2rem; margin-bottom: 0.5rem;">🚨 System Identified Duplicates (by Seller Code)</h2>
                    <p style="color: var(--text-muted); font-size: 0.85rem; margin-bottom: 1rem;">These records were automatically renamed during migration to prevent database errors.</p>
                    
                    <?php if (empty($renamed_dupes)): ?>
                        <p style="padding: 2rem; text-align: center; color: var(--text-muted);">No records found with '_DUP' suffix.</p>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Code</th>
                                    <th>Name</th>
                                    <th>Agent</th>
                                    <th>District</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($renamed_dupes as $r): ?>
                                <tr>
                                    <td><?php echo $r['id']; ?></td>
                                    <td><span class="dupe-badge"><?php echo htmlspecialchars($r['seller_code']); ?></span></td>
                                    <td><?php echo htmlspecialchars($r['seller_name']); ?></td>
                                    <td><?php echo htmlspecialchars($r['agent_code']); ?></td>
                                    <td><?php echo htmlspecialchars($r['district']); ?></td>
                                    <td>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this record forever?')">
                                            <?php csrf_input(); ?>
                                            <input type="hidden" name="delete_id" value="<?php echo $r['id']; ?>">
                                            <button type="submit" class="btn-delete">🗑️ Delete</button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>

                <div class="dupe-card">
                    <h2 style="font-size: 1.2rem; margin-bottom: 0.5rem;">🔍 Potential Duplicates (Grouped)</h2>
                    <p style="color: var(--text-muted); font-size: 0.85rem; margin-bottom: 1rem;">Groups of records with the EXACT SAME Seller Name and Agent Code.</p>
                    
                    <?php if (empty($name_dupes_grouped)): ?>
                        <p style="padding: 2rem; text-align: center; color: var(--text-muted);">No grouped duplicates detected.</p>
                    <?php else: ?>
                        <?php foreach ($name_dupes_grouped as $group): ?>
                            <div class="group-header">
                                <strong><?php echo htmlspecialchars($group['name']); ?></strong> 
                                <span style="color: var(--text-muted); margin-left:10px;">(Agent: <?php echo htmlspecialchars($group['agent']); ?>)</span>
                            </div>
                            <table>
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Code</th>
                                        <th>NIC</th>
                                        <th>District</th>
                                        <th>Created At</th>
                                        <th style="text-align: right;">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($group['records'] as $n): ?>
                                    <tr>
                                        <td><?php echo $n['id']; ?></td>
                                        <td><?php echo htmlspecialchars($n['seller_code']); ?></td>
                                        <td><?php echo htmlspecialchars($n['nic_new'] ?: $n['nic_old']); ?></td>
                                        <td><?php echo htmlspecialchars($n['district']); ?></td>
                                        <td><?php echo $n['created_at']; ?></td>
                                        <td style="text-align: right; white-space: nowrap;">
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Keep this record and delete all other records in this group?')">
                                                <?php csrf_input(); ?>
                                                <input type="hidden" name="keep_id" value="<?php echo $n['id']; ?>">
                                                <input type="hidden" name="target_name" value="<?php echo htmlspecialchars($group['name']); ?>">
                                                <input type="hidden" name="target_agent" value="<?php echo htmlspecialchars($group['agent']); ?>">
                                                <button type="submit" class="btn-keep">✨ Keep & Clean</button>
                                            </form>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete ONLY this record?')">
                                                <?php csrf_input(); ?>
                                                <input type="hidden" name="delete_id" value="<?php echo $n['id']; ?>">
                                                <button type="submit" class="btn-delete" title="Delete only this one">🗑️</button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endforeach; ?>
                    <?php endif; ?>
            </div>
            <?php include 'includes/footer.php'; ?>
        </body>
        </html>
