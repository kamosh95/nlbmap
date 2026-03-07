<?php
require_once 'includes/security.php';
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

require_once 'includes/db_config.php';
require_once 'includes/get_nav.php';

// Pagination
$limit = 50;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Search
$search = $_GET['search'] ?? '';
$where = "1=1";
$params = [];

if ($search) {
    $where = "(username LIKE ? OR action LIKE ? OR details LIKE ? OR entity_type LIKE ? OR ip_address LIKE ?)";
    $params = ["%$search%", "%$search%", "%$search%", "%$search%", "%$search%"];
}

// Total records
$total_stmt = $pdo->prepare("SELECT COUNT(*) FROM activity_log WHERE $where");
$total_stmt->execute($params);
$total_rows = $total_stmt->fetchColumn();
$total_pages = ceil($total_rows / $limit);

// Fetch logs
$stmt = $pdo->prepare("SELECT * FROM activity_log WHERE $where ORDER BY created_at DESC LIMIT $limit OFFSET $offset");
$stmt->execute($params);
$logs = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Log - NLB Seller Map Portal</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .log-table {
            width: 100%;
            border-collapse: collapse;
            background: var(--card-bg);
            border: 1px solid var(--glass-border);
            border-radius: 16px;
            overflow: hidden;
            margin-top: 1rem;
        }
        .log-table th, .log-table td {
            padding: 1.2rem;
            text-align: left;
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }
        .log-table th {
            background: rgba(255,255,255,0.02);
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 1px;
        }
        .log-table tr:hover {
            background: rgba(255,255,255,0.02);
        }
        .type-badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .type-seller { background: rgba(14, 165, 233, 0.15); color: #0ea5e9; }
        .type-dealer { background: rgba(255, 204, 0, 0.15); color: #ffcc00; }
        .type-agent { background: rgba(74, 222, 128, 0.15); color: #4ade80; }
        .type-general { background: rgba(255, 255, 255, 0.1); color: var(--text-muted); }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 2rem;
        }
        .page-link {
            padding: 8px 16px;
            background: var(--nav-bg);
            border: 1px solid var(--glass-border);
            border-radius: 8px;
            color: var(--text-main);
            text-decoration: none;
            transition: 0.3s;
        }
        .page-link.active {
            background: var(--secondary-color);
            border-color: var(--secondary-color);
        }
        .page-link:hover:not(.active) {
            background: rgba(255,255,255,0.1);
        }
    </style>
</head>
<body>
    <div class="container wide">
        <div class="page-header">
            <img src="assets/img/Logo.png" alt="NLB Logo">
            <div class="page-header-text">
                <h1>NLB Seller Map Portal</h1>
                <p>System Activity Log &nbsp;·&nbsp; Logged in as <b><?php echo e($_SESSION['username']); ?></b></p>
            </div>
        </div>

        <div class="nav-bar">
            <?php echo render_nav($pdo, $_SESSION['role']); ?>
        </div>

        <div class="search-container" style="margin: 2rem 0; display: flex; justify-content: space-between; align-items: center;">
            <form action="" method="GET" style="display: flex; gap: 10px; max-width: 500px; flex: 1;">
                <input type="text" name="search" value="<?php echo e($search); ?>" placeholder="Search logs (User, Action, Details...)" style="flex: 1; padding: 0.8rem 1.2rem; background: var(--input-bg); border: 1px solid var(--glass-border); color: var(--text-main); border-radius: 12px;">
                <button type="submit" class="btn-submit" style="margin-top: 0; width: auto; padding: 0.8rem 1.5rem;">Search</button>
                <?php if ($search): ?>
                    <a href="activity_log.php" class="btn-delete" style="padding: 0.8rem 1.2rem; text-decoration: none; display: flex; align-items: center;">Clear</a>
                <?php endif; ?>
            </form>
            <div style="color: var(--text-muted); font-size: 0.85rem;">
                Showing <?php echo number_format(count($logs)); ?> of <?php echo number_format($total_rows); ?> events
            </div>
        </div>

        <div style="overflow-x: auto;">
            <table class="log-table">
                <thead>
                    <tr>
                        <th>Timestamp</th>
                        <th>User</th>
                        <th>Role</th>
                        <th>Type</th>
                        <th>Action</th>
                        <th>Details</th>
                        <th>IP Address</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; color: var(--text-muted); padding: 3rem;">No activity logs found matching your criteria.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td style="font-size: 0.85rem; color: var(--text-muted); white-space: nowrap;">
                                    <?php echo date('M j, Y - H:i:s', strtotime($log['created_at'])); ?>
                                </td>
                                <td style="font-weight: 600; color: var(--text-main);"><?php echo e($log['username']); ?></td>
                                <td>
                                    <span class="role-badge badge-<?php echo e($log['role']); ?>" style="font-size: 0.65rem;"><?php echo e($log['role']); ?></span>
                                </td>
                                <td>
                                    <span class="type-badge type-<?php echo e($log['entity_type']); ?>">
                                        <?php echo e(ucfirst($log['entity_type'])); ?>
                                    </span>
                                </td>
                                <td style="font-weight: 500;"><?php echo e($log['action']); ?></td>
                                <td style="font-size: 0.85rem; color: var(--text-muted); line-height: 1.4;"><?php echo e($log['details']); ?></td>
                                <td style="font-family: monospace; font-size: 0.75rem; color: var(--text-muted);"><?php echo e($log['ip_address']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <?php if ($i == 1 || $i == $total_pages || ($i >= $page - 2 && $i <= $page + 2)): ?>
                        <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>" class="page-link <?php echo ($i == $page) ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php elseif ($i == 2 || $i == $total_pages - 1): ?>
                        <span style="color: var(--text-muted); align-self: center;">...</span>
                    <?php endif; ?>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php include 'includes/footer.php'; ?>
</body>
</html>
