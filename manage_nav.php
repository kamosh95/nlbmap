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

// Handle Delete (Now via POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    verify_csrf_token($_POST['csrf_token'] ?? '');
    $stmt = $pdo->prepare("DELETE FROM navigation WHERE id = ?");
    $stmt->execute([$_POST['delete_id']]);
    $message = "Link removed successfully!";
    $status = "success";
}

// Handle Add/Edit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['label'])) {
    verify_csrf_token($_POST['csrf_token'] ?? '');
    $label = $_POST['label'];
    $url = $_POST['url'];
    $role = $_POST['role_access'];
    $nav_group = $_POST['nav_group'] ?? 'Main';
    $order = (int)$_POST['sort_order'];

    if (isset($_POST['id']) && !empty($_POST['id'])) {
        $stmt = $pdo->prepare("UPDATE navigation SET label = ?, url = ?, role_access = ?, nav_group = ?, sort_order = ? WHERE id = ?");
        $stmt->execute([$label, $url, $role, $nav_group, $order, $_POST['id']]);
        $message = "Navigation link updated!";
    } else {
        $stmt = $pdo->prepare("INSERT INTO navigation (label, url, role_access, nav_group, sort_order) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$label, $url, $role, $nav_group, $order]);
        $message = "New link added!";
    }
    $status = "success";
}

$links = $pdo->query("SELECT * FROM navigation ORDER BY 
    CASE WHEN nav_group = 'Main' THEN 0 ELSE 1 END,
    nav_group ASC, 
    sort_order ASC")->fetchAll();
    
// Get unique groups for helper
$existing_groups = $pdo->query("SELECT DISTINCT nav_group FROM navigation WHERE nav_group != 'Main'")->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menu Management - NLB Portal</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .settings-grid {
            display: grid;
            grid-template-columns: 350px 1fr;
            gap: 2rem;
            align-items: start;
        }
        .help-text {
            font-size: 0.75rem;
            color: var(--text-muted);
            margin-top: 5px;
            line-height: 1.4;
        }
        .role-tag {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            letter-spacing: 0.3px;
        }
        .tag-all { background: var(--card-bg); color: var(--text-main); border: 1px solid var(--glass-border); }
        .tag-admin { background: rgba(239, 68, 68, 0.15); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.2); }
        .tag-moderator { background: rgba(59, 130, 246, 0.15); color: #60a5fa; border: 1px solid rgba(59, 130, 246, 0.2); }
        .tag-tm { background: rgba(34, 197, 94, 0.15); color: #4ade80; border: 1px solid rgba(34, 197, 94, 0.2); }
        .tag-user { background: rgba(167, 139, 250, 0.15); color: #c084fc; border: 1px solid rgba(167, 139, 250, 0.2); }
        
        .nav-item-card { transition: all 0.3s ease; }
        .nav-item-card:hover { background: rgba(255, 255, 255, 0.05); }
        
        .action-btn {
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: white;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            background: rgba(255,255,255,0.05);
        }
        .btn-edit-small:hover { background: #0072ff; border-color: #0072ff; transform: scale(1.1); }
        .btn-delete-small:hover { background: #ef4444; border-color: #ef4444; transform: scale(1.1); }
        
        .order-badge {
            width: 28px;
            height: 28px;
            background: rgba(255, 204, 0, 0.1);
            color: #ffcc00;
            border: 1px solid rgba(255, 204, 0, 0.3);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            font-weight: 700;
        }
        
        @media (max-width: 768px) {
            .settings-grid { gap: 1rem; }
            .card { padding: 1.5rem 1.25rem; }
            .action-btn { width: 44px; height: 44px; font-size: 1.2rem; } /* Larger touch targets */
            .table-wrapper td { padding: 0.85rem !important; }
            .order-badge { width: 34px; height: 34px; font-size: 0.9rem; }
        }

        .group-suggestion {
            display: inline-block;
            padding: 6px 10px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--glass-border);
            border-radius: 8px;
            font-size: 0.75rem;
            cursor: pointer;
            margin-right: 6px;
            margin-top: 8px;
            transition: all 0.2s;
        }
        .group-suggestion:hover {
            background: var(--secondary-color);
            color: black;
            border-color: var(--secondary-color);
        }
    </style>
</head>
<body>
    <div class="container wide">
        <div class="nav-bar">
            <div class="nav-brand">
                <img src="assets/img/Logo.png" alt="NLB Logo">
                <div>
                    <h1>Menu Configurator</h1>
                    <p style="color: var(--text-muted); font-size: 0.75rem; margin: 0;">Organize and protect portal links</p>
                </div>
            </div>
            <?php echo render_nav($pdo, $_SESSION['role']); ?>
        </div>

        <?php if ($message): ?>
            <div class="message <?php echo $status; ?>" style="display: block; margin-bottom: 2rem;">
                <?php echo ($status === 'success' ? '✅ ' : '❌ ') . $message; ?>
            </div>
        <?php endif; ?>

        <div class="settings-grid">
            <!-- Sidebar Form -->
            <div class="card" style="position: sticky; top: 20px;">
                <h2 id="form-title" style="font-size: 1.2rem; margin-bottom: 1.5rem; color: var(--secondary-color);">✨ Quick Link Creator</h2>
                <form id="nav-form" method="POST">
                    <?php csrf_input(); ?>
                    <input type="hidden" name="id" id="nav-id">
                    
                    <div class="form-group">
                        <label>Menu Label</label>
                        <input type="text" name="label" id="nav-label" placeholder="e.g. Activity Log" required>
                        <p class="help-text">Text displayed in the navigation bar.</p>
                    </div>

                    <div class="form-group">
                        <label>Target Page (URL)</label>
                        <input type="text" name="url" id="nav-url" placeholder="e.g. reports.php" required>
                        <p class="help-text">The filename or address to open.</p>
                    </div>

                    <div class="form-group">
                        <label>Permissions Access</label>
                        <select name="role_access" id="nav-role" style="width:100%; padding:12px; background:var(--input-bg); border:1px solid var(--glass-border); color:var(--text-main); border-radius:12px;">
                            <option value="all">🌍 Everyone (Public)</option>
                            <option value="admin">🔒 Administrators Only</option>
                            <option value="moderator">🛡️ Admins & Moderators</option>
                            <option value="tm">👤 Field Users (TMs)</option>
                            <option value="user">👀 Viewers (Users)</option>
                        </select>
                        <p class="help-text">Select who can see this menu item.</p>
                    </div>

                    <div class="form-group">
                        <label>Menu Group (Dropdown)</label>
                        <input type="text" name="nav_group" id="nav-group" value="Main" placeholder="e.g. Details Entry 📝">
                        <p class="help-text">Items with the same group name will be automatically grouped into a dropdown in the nav bar. Use 'Main' for a standalone link.</p>
                        <div id="group-suggestions">
                            <?php foreach($existing_groups as $g): ?>
                                <span class="group-suggestion" onclick="document.getElementById('nav-group').value = '<?php echo addslashes($g); ?>'"><?php echo htmlspecialchars($g); ?></span>
                            <?php endforeach; ?>
                            <span class="group-suggestion" onclick="document.getElementById('nav-group').value = 'Main'">Main</span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Sort Order</label>
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <input type="number" name="sort_order" id="nav-order" value="0" style="width: 80px;">
                            <p class="help-text">Lower numbers appear first within their group.</p>
                        </div>
                    </div>

                    <button type="submit" class="btn-submit" style="margin-top: 1rem;">💾 Save Link</button>
                    <button type="button" id="cancelBtn" onclick="resetForm()" style="display: none; width: 100%; margin-top: 10px; background: rgba(255,255,255,0.05); border: 1px solid var(--glass-border); padding: 12px; border-radius: 12px; color: white; cursor: pointer; font-weight: 500;">Cancel Edit</button>
                </form>
            </div>

            <!-- List Content -->
            <div class="table-wrapper" style="border-radius: 20px; overflow: hidden; background: var(--card-bg);">
                <div style="padding: 1.5rem; background: var(--nav-bg); border-bottom: 1px solid var(--glass-border); display: flex; justify-content: space-between; align-items: center;">
                    <h3 style="margin: 0; font-size: 1.1rem; font-weight: 600;">📂 Current Menu Architecture</h3>
                    <span style="font-size: 0.8rem; color: var(--text-muted); background: var(--input-bg); padding: 4px 12px; border-radius: 20px;"><?php echo count($links); ?> Active Links</span>
                </div>
                <table style="min-width: 100%;">
                    <thead>
                        <tr>
                            <th style="width: 60px; text-align: center;">Pos</th>
                            <th>Label</th>
                            <th>Target URL</th>
                            <th>Group</th>
                            <th>Visibility</th>
                            <th style="text-align: right; padding-right: 2rem;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($links as $link): ?>
                        <tr class="nav-item-card">
                            <td data-label="Position" style="text-align: center;">
                                <div class="order-badge" style="margin: 0 auto;"><?php echo $link['sort_order']; ?></div>
                            </td>
                            <td data-label="Label" style="font-weight: 600; color: var(--text-main); font-size: 0.95rem;">
                                <?php echo htmlspecialchars($link['label']); ?>
                            </td>
                            <td data-label="URL">
                                <code style="background: rgba(0, 114, 255, 0.08); color: #60a5fa; padding: 4px 10px; border-radius: 8px; font-size: 0.8rem; border: 1px solid rgba(0, 114, 255, 0.15); font-family: 'Courier New', monospace;">
                                    <?php echo htmlspecialchars($link['url']); ?>
                                </code>
                            </td>
                            <td data-label="Group" style="color: var(--text-muted); font-size: 0.85rem;">
                                <?php 
                                    $grp = $link['nav_group'] ?? 'Main';
                                    if ($grp === 'Main') {
                                        echo '<span style="opacity: 0.5;">None (Main)</span>';
                                    } else {
                                        echo '📂 ' . htmlspecialchars($grp);
                                    }
                                ?>
                            </td>
                            <td data-label="Visibility">
                                <?php 
                                    $rc = 'tag-all'; $rl = 'Everyone';
                                    if($link['role_access'] === 'admin') { $rc = 'tag-admin'; $rl = 'ADMIN'; }
                                    elseif($link['role_access'] === 'moderator') { $rc = 'tag-moderator'; $rl = 'MODERATOR'; }
                                    elseif($link['role_access'] === 'tm') { $rc = 'tag-tm'; $rl = 'FIELD USER'; }
                                    elseif($link['role_access'] === 'user') { $rc = 'tag-user'; $rl = 'VIEWER'; }
                                ?>
                                <span class="role-tag <?php echo $rc; ?>"><?php echo $rl; ?></span>
                            </td>
                            <td data-label="Actions" style="padding-right: 1.5rem;">
                                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                                    <button onclick='editLink(<?php echo json_encode($link); ?>)' class="action-btn btn-edit-small" title="Edit Link">✏️</button>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Permanently remove this link?')">
                                        <?php csrf_input(); ?>
                                        <input type="hidden" name="delete_id" value="<?php echo $link['id']; ?>">
                                        <button type="submit" class="action-btn btn-delete-small" title="Remove Link">🗑️</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if (empty($links)): ?>
                    <div style="padding: 4rem 2rem; text-align: center; color: var(--text-muted);">
                        <div style="font-size: 3rem; margin-bottom: 1.5rem; opacity: 0.3;">📂</div>
                        <p style="font-size: 1.1rem;">No navigation links found. Create your first link above!</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php include 'includes/footer.php'; ?>
    <script>
        function editLink(data) {
            const formTitle = document.getElementById('form-title');
            const cancelBtn = document.getElementById('cancelBtn');
            
            formTitle.innerHTML = "📝 Updating: <span style='color: white;'>" + data.label + "</span>";
            document.getElementById('nav-id').value = data.id;
            document.getElementById('nav-label').value = data.label;
            document.getElementById('nav-url').value = data.url;
            document.getElementById('nav-role').value = data.role_access;
            document.getElementById('nav-group').value = data.nav_group || 'Main';
            document.getElementById('nav-order').value = data.sort_order;
            cancelBtn.style.display = 'block';
            
            document.getElementById('nav-label').focus();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function resetForm() {
            document.getElementById('form-title').innerText = "✨ Quick Link Creator";
            document.getElementById('nav-form').reset();
            document.getElementById('nav-id').value = "";
            document.getElementById('cancelBtn').style.display = 'none';
        }
    </script>
</body>
</html>
