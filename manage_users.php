<?php
require_once 'includes/security.php';
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

require_once 'includes/db_config.php';
require_once 'includes/get_nav.php';
require_once 'includes/mail_helper.php';

$message = '';
$status = '';

// ── Get available districts from dealers table ──
$all_districts = [];
try {
    $d_stmt = $pdo->query("SELECT DISTINCT district FROM dealers WHERE district != '' ORDER BY district ASC");
    $all_districts = $d_stmt->fetchAll(PDO::FETCH_COLUMN);
} catch(PDOException $e) {}

// ── Handle User Creation ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    verify_csrf_token($_POST['csrf_token'] ?? '');
    $new_user = trim($_POST['new_username']);
    $new_pass = $_POST['new_password'];
    $new_role = $_POST['new_role'];
    $new_districts = !empty($_POST['new_districts']) ? implode(',', $_POST['new_districts']) : '';

    if (!empty($new_user) && !empty($new_pass)) {
        $check = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
        $check->execute([$new_user]);
        if ($check->fetchColumn() > 0) {
            $message = "Error: The username '$new_user' is already taken. Please choose another.";
            $status = "error";
        } else {
            $hashed_pass = password_hash($new_pass, PASSWORD_DEFAULT);
            $f_name = $_POST['new_full_name'] ?? '';
            $e_no = $_POST['new_emp_no'] ?? '';
            $m_no = $_POST['new_mobile_no'] ?? '';
            $e_mail = $_POST['new_email'] ?? '';
            
            try {
                $stmt = $pdo->prepare("INSERT INTO users (username, full_name, emp_no, mobile_no, email, password, role, assigned_districts, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active')");
                $stmt->execute([$new_user, $f_name, $e_no, $m_no, $e_mail, $hashed_pass, $new_role, $new_districts]);
                $message = "User Account for '$new_user' has been established and activated.";
                $status = "success";
            } catch (PDOException $e) {
                $message = "System Error: Unable to save user to database. " . $e->getMessage();
                $status = "error";
            }
        }
    }
}

// ── Handle District Assignment Update ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_districts'])) {
    verify_csrf_token($_POST['csrf_token'] ?? '');
    $uid = (int)$_POST['user_id'];
    $districts = !empty($_POST['districts']) ? implode(',', $_POST['districts']) : '';
    try {
        $stmt = $pdo->prepare("UPDATE users SET assigned_districts = ? WHERE id = ?");
        $stmt->execute([$districts, $uid]);
        $message = "District assignments updated successfully!";
        $status = "success";
    } catch (PDOException $e) {
        $message = "Error: " . $e->getMessage();
        $status = "error";
    }
}

// ── Handle User Deletion ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    verify_csrf_token($_POST['csrf_token'] ?? '');
    $delete_id = $_POST['delete_id'];
    if ($delete_id != ($_SESSION['user_id'] ?? 0)) {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$delete_id]);
        $message = "User account permanently removed.";
        $status = "success";
    } else {
        $message = "Security Protocol: You cannot terminate your own active session.";
        $status = "error";
    }
}

// ── Handle User Status Update (Approve/Suspend) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    verify_csrf_token($_POST['csrf_token'] ?? '');
    $uid = (int)$_POST['user_id'];
    $new_status = $_POST['status'];
    try {
        // Fetch user info before updating
        $u_info = $pdo->prepare("SELECT email, username, full_name, status FROM users WHERE id = ?");
        $u_info->execute([$uid]);
        $user_to_update = $u_info->fetch();

        $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
        $stmt->execute([$new_status, $uid]);
        
        $message = "User status updated to '$new_status'.";
        $status = "success";

        // Email Notification Logic
        if ($new_status === 'active' && !empty($user_to_update['email'])) {
            $to_email = $user_to_update['email'];
            $to_name = $user_to_update['full_name'] ?: $user_to_update['username'];
            $subject = "NLB Portal Account Approved! 🎉";
            $login_url = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/login.php";
            
            // Professional HTML Email Template
            $email_body = '
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="UTF-8">
                <style>
                    body { font-family: Arial, sans-serif; background-color: #f4f7f6; margin: 0; padding: 0; }
                    .email-wrapper { max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 10px rgba(0,0,0,0.05); }
                    .header { background: linear-gradient(135deg, #10b981, #34d399); padding: 20px; text-align: center; color: #ffffff; }
                    .header h1 { margin: 0; font-size: 24px; font-weight: bold; }
                    .content { padding: 30px; color: #333333; line-height: 1.6; }
                    .content h2 { font-size: 20px; margin-top: 0; color: #10b981; }
                    .user-details { background-color: #f8fafc; border-left: 4px solid #10b981; padding: 15px; margin: 20px 0; border-radius: 4px; }
                    .btn-container { text-align: center; margin: 30px 0; }
                    .btn { display: inline-block; padding: 12px 30px; background-color: #10b981; color: #ffffff; text-decoration: none; border-radius: 6px; font-weight: bold; font-size: 16px; box-shadow: 0 4px 6px rgba(16,185,129,0.3); }
                    .footer { background-color: #f9f9f9; padding: 20px; text-align: center; color: #777777; font-size: 12px; border-top: 1px solid #eeeeee; }
                </style>
            </head>
            <body>
                <div class="email-wrapper">
                    <div class="header">
                        <h1>NLB Portal Access</h1>
                    </div>
                    <div class="content">
                        <h2>Account Approved! 🎉</h2>
                        <p>Hello <strong>' . htmlspecialchars($to_name) . '</strong>,</p>
                        <p>Great news! Your account on the NLB Seller Map Portal has been reviewed and <strong>approved</strong> by the administrator. You now have full access to the portal.</p>
                        
                        <div class="user-details">
                            <p style="margin: 0 0 5px 0;"><strong>Your Login Credentials:</strong></p>
                            <p style="margin: 0; font-family: monospace; font-size: 16px;">Username: <strong>' . htmlspecialchars($user_to_update['username']) . '</strong></p>
                        </div>

                        <div class="btn-container">
                            <a href="' . $login_url . '" class="btn">Login to Portal</a>
                        </div>
                        
                        <p style="font-size: 14px;">Welcome aboard! If you have any questions or need assistance, please contact the IT Helpdesk.</p>
                    </div>
                    <div class="footer">
                        <p>This is an automated message from the National Lotteries Board Information Technology Division.</p>
                        <p>&copy; ' . date('Y') . ' National Lotteries Board. All rights reserved.</p>
                    </div>
                </div>
            </body>
            </html>
            ';

            if (send_notification_email($to_email, $to_name, $subject, $email_body)) {
                $message .= " Notification email sent to $to_email.";
            } else {
                $message .= " (Note: Approval successful, but SMTP email notification failed. Please verify mail_config.php settings.)";
            }
        }
        
        log_activity($pdo, "User Status Update", "User ID $uid status set to $new_status", "admin");
    } catch (PDOException $e) {
        $message = "Error: " . $e->getMessage();
        $status = "error";
    }
}

$stmt = $pdo->query("SELECT * FROM users ORDER BY status DESC, created_at DESC");
$users = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Control Center - NLB Portal</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .management-grid {
            display: grid;
            grid-template-columns: 380px 1fr;
            gap: 2rem;
            align-items: start;
        }
        .user-card { transition: all 0.3s ease; }
        .user-card:hover { background: rgba(255, 255, 255, 0.05); }
        .help-text { font-size: 0.75rem; color: var(--text-muted); margin-top: 5px; line-height: 1.4; }
        .user-avatar {
            width: 36px; height: 36px;
            background: var(--accent-gradient);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 0.8rem; font-weight: 700; color: white; text-transform: uppercase;
            flex-shrink: 0;
        }
        .district-tag {
            display: inline-block;
            background: rgba(0, 212, 255, 0.1);
            border: 1px solid rgba(0, 212, 255, 0.25);
            color: #00d4ff;
            font-size: 0.68rem;
            font-weight: 600;
            padding: 2px 8px;
            border-radius: 20px;
            margin: 2px 2px;
        }
        .district-checkbox-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 6px;
            max-height: 200px;
            overflow-y: auto;
            padding: 10px;
            background: rgba(0,0,0,0.2);
            border-radius: 10px;
            border: 1px solid var(--glass-border);
            margin-top: 8px;
        }
        .district-checkbox-item {
            display: flex; align-items: center; gap: 8px;
            padding: 5px 8px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.8rem;
            transition: background 0.2s;
        }
        .district-checkbox-item:hover { background: rgba(255,255,255,0.05); }
        .district-checkbox-item input[type="checkbox"] { accent-color: #00d4ff; width: 14px; height: 14px; }

        .pending-row { background: rgba(255, 152, 0, 0.03); }
        .pending-row:hover { background: rgba(255, 152, 0, 0.08) !important; }

        /* Modal */
        .modal-overlay {
            display: none;
            position: fixed; inset: 0;
            background: rgba(0,0,0,0.6);
            backdrop-filter: blur(6px);
            z-index: 1000; align-items: center; justify-content: center;
        }
        .modal-overlay.active { display: flex; }
        .modal-box {
            background: var(--card-bg);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            padding: 2rem;
            width: min(480px, 95vw);
            max-height: 85vh;
            overflow-y: auto;
        }

        @media (max-width: 992px) { .management-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<div class="container wide">
    <div class="nav-bar">
        <div class="nav-brand">
            <img src="assets/img/Logo.png" alt="NLB Logo">
            <div>
                <h1>User Control Center</h1>
                <p style="color: var(--text-muted); font-size: 0.75rem; margin: 0;">Manage access, roles and district allocations</p>
            </div>
        </div>
        <?php echo render_nav($pdo, $_SESSION['role']); ?>
    </div>

    <?php if ($message): ?>
        <div class="message <?php echo $status; ?>" style="display: block; margin-bottom: 2rem;">
            <?php echo ($status === 'success' ? '✅ ' : '❌ ') . $message; ?>
        </div>
    <?php endif; ?>

    <div class="management-grid">
        <!-- Registration Sidebar -->
        <div style="display:flex; flex-direction:column; gap:1.5rem;">
            <div class="card" style="position: sticky; top: 20px;">
                <h2 style="font-size: 1.25rem; margin-bottom: 1.5rem; color: var(--secondary-color);">👤 New Member Portal</h2>
                <form method="POST">
                    <?php csrf_input(); ?>
                    <div class="form-group">
                        <label>Secure Username</label>
                        <input type="text" name="new_username" placeholder="Enter unique ID" required>
                    </div>

                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text" name="new_full_name" placeholder="Official Name" required>
                    </div>

                    <div class="form-grid" style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                        <div class="form-group">
                            <label>Emp No</label>
                            <input type="text" name="new_emp_no" placeholder="ID Number">
                        </div>
                        <div class="form-group">
                            <label>Mobile</label>
                            <input type="text" name="new_mobile_no" placeholder="07XXXXXXXX">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Email Address</label>
                        <input type="email" name="new_email" placeholder="official@nlb.lk">
                    </div>

                    <div class="form-group">
                        <label>System Password</label>
                        <input type="password" name="new_password" placeholder="••••••••" required>
                    </div>

                    <div class="form-group">
                        <label>Access Role</label>
                        <select name="new_role" id="new_role" onchange="toggleDistrictField()" style="width: 100%; padding: 12px; background: var(--input-bg); border: 1px solid var(--glass-border); color: var(--text-main); border-radius: 12px; outline: none;">
                            <option value="tm">👤 Field Operator (TM)</option>
                            <option value="mkt">🌟 Marketing (MKT)</option>
                            <option value="user">👀 Viewer (User)</option>
                            <option value="moderator">🛡️ System Moderator</option>
                            <option value="admin">⚔️ System Administrator</option>
                        </select>
                        <p class="help-text">Determines system-wide permissions.</p>
                    </div>

                    <!-- District Allocation (shown only for TM) -->
                    <div class="form-group" id="district_field">
                        <label>📍 Assign Districts</label>
                        <p class="help-text" style="margin-bottom:4px;">TM will only see dealers from selected districts.</p>
                        <div class="district-checkbox-grid">
                            <?php if (empty($all_districts)): ?>
                                <p style="color:var(--text-muted); font-size:0.78rem; grid-column:1/-1;">No districts found. Add dealers first.</p>
                            <?php else: ?>
                                <?php foreach ($all_districts as $dist): ?>
                                <label class="district-checkbox-item">
                                    <input type="checkbox" name="new_districts[]" value="<?php echo htmlspecialchars($dist); ?>">
                                    <?php echo htmlspecialchars($dist); ?>
                                </label>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <button type="submit" name="add_user" class="btn-submit" style="margin-top: 1rem;">🚀 Provision Account</button>
                </form>
            </div>
        </div>

        <!-- Directory Content -->
        <div class="table-wrapper" style="border-radius: 20px; overflow: hidden; background: var(--card-bg);">
            <div style="padding: 1.5rem; background: var(--nav-bg); border-bottom: 1px solid var(--glass-border); display: flex; justify-content: space-between; align-items: center;">
                <h3 style="margin: 0; font-size: 1.1rem; font-weight: 600;">📋 Authorized Personnel Directory</h3>
                <span style="font-size: 0.8rem; color: var(--text-muted); background: var(--input-bg); padding: 4px 12px; border-radius: 20px;"><?php echo count($users); ?> Total Accounts</span>
            </div>
            <div style="overflow-x:auto;">
            <table style="min-width: 100%;">
                <thead>
                    <tr>
                        <th style="padding-left: 1.5rem;">User Entity</th>
                        <th>Details</th>
                        <th>Security Level</th>
                        <th>Districts Allocated</th>
                        <th>Status</th>
                        <th style="text-align: right; padding-right: 2rem;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                    <tr class="user-card <?php echo $u['status'] === 'pending' ? 'pending-row' : ''; ?>">
                        <td style="padding-left: 1.5rem;">
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <div class="user-avatar" style="background: <?php echo $u['status'] === 'pending' ? '#ff9800' : 'var(--accent-gradient)'; ?>;">
                                    <?php echo substr($u['username'], 0, 1); ?>
                                </div>
                                <div>
                                    <div style="font-weight: 700; color: var(--text-main); font-size: 0.95rem;">
                                        <?php echo htmlspecialchars($u['username']); ?>
                                        <?php if ($u['id'] == ($_SESSION['user_id'] ?? 0)): ?>
                                            <span style="color: var(--secondary-color); font-size: 0.7rem;">(You)</span>
                                        <?php endif; ?>
                                    </div>
                                    <div style="font-size: 0.75rem; color: var(--text-muted);"><?php echo date('M d, Y', strtotime($u['created_at'])); ?></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div style="font-weight: 600; font-size: 0.85rem; color: var(--text-main);"><?php echo htmlspecialchars($u['full_name'] ?? 'N/A'); ?></div>
                            <div style="font-size: 0.75rem; color: var(--text-muted);">
                                <?php echo htmlspecialchars($u['emp_no'] ?? '-'); ?> | <?php echo htmlspecialchars($u['mobile_no'] ?? '-'); ?>
                            </div>
                        </td>
                        <td>
                            <span class="role-badge badge-<?php echo $u['role']; ?>"><?php echo strtoupper($u['role']); ?></span>
                        </td>
                        <td style="max-width:200px;">
                            <?php if ($u['role'] === 'tm' || $u['role'] === 'moderator'): ?>
                                <?php
                                $dists = array_filter(explode(',', $u['assigned_districts'] ?? ''));
                                if (!empty($dists)):
                                    foreach ($dists as $d): ?>
                                        <span class="district-tag"><?php echo htmlspecialchars(trim($d)); ?></span>
                                    <?php endforeach;
                                else: ?>
                                    <span style="color:var(--text-muted); font-size:0.75rem;">All areas</span>
                                <?php endif; ?>
                                <button onclick="openDistrictModal(<?php echo $u['id']; ?>, '<?php echo htmlspecialchars($u['username']); ?>', '<?php echo htmlspecialchars($u['assigned_districts'] ?? ''); ?>')"
                                    style="margin-left:5px; background:rgba(0,114,255,0.05); border:1px solid rgba(0,114,255,0.2); color:var(--secondary-color); border-radius:6px; padding:2px 6px; font-size:0.65rem; cursor:pointer;">
                                    Edit
                                </button>
                            <?php else: ?>
                                <span style="color:var(--text-muted); font-size:0.75rem;">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($u['status'] === 'pending'): ?>
                                <span style="background: rgba(255, 152, 0, 0.15); color: #ff9800; padding: 4px 10px; border-radius: 20px; font-size: 0.7rem; font-weight: 700; border: 1px solid rgba(255, 152, 0, 0.25);">PENDING</span>
                            <?php elseif ($u['status'] === 'active'): ?>
                                <span style="background: rgba(34, 197, 94, 0.15); color: #4ade80; padding: 4px 10px; border-radius: 20px; font-size: 0.7rem; font-weight: 700; border: 1px solid rgba(34, 197, 94, 0.25);">ACTIVE</span>
                            <?php else: ?>
                                <span style="background: rgba(239, 68, 68, 0.15); color: #f87171; padding: 4px 10px; border-radius: 20px; font-size: 0.7rem; font-weight: 700; border: 1px solid rgba(239, 68, 68, 0.25);">SUSPENDED</span>
                            <?php endif; ?>
                        </td>
                        <td style="padding-right: 1.5rem; text-align: right;">
                            <div style="display: flex; gap: 8px; justify-content: flex-end;">
                                <?php if ($u['id'] != ($_SESSION['user_id'] ?? 0)): ?>
                                    <?php if ($u['status'] === 'pending'): ?>
                                        <form method="POST" style="display:inline;">
                                            <?php csrf_input(); ?>
                                            <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                            <input type="hidden" name="status" value="active">
                                            <button type="submit" name="update_status" style="background:#22c55e; color:#fff; border:none; padding:6px 12px; border-radius:8px; font-size:0.75rem; font-weight:700; cursor:pointer;">Approve</button>
                                        </form>
                                    <?php elseif ($u['status'] === 'active'): ?>
                                        <form method="POST" style="display:inline;">
                                            <?php csrf_input(); ?>
                                            <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                            <input type="hidden" name="status" value="suspended">
                                            <button type="submit" name="update_status" style="background:rgba(239, 68, 68, 0.1); color:#ef4444; border:1px solid rgba(239,68,68,0.2); padding:6px 12px; border-radius:8px; font-size:0.75rem; font-weight:600; cursor:pointer;">Suspend</button>
                                        </form>
                                    <?php else: ?>
                                        <form method="POST" style="display:inline;">
                                            <?php csrf_input(); ?>
                                            <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                            <input type="hidden" name="status" value="active">
                                            <button type="submit" name="update_status" style="background:rgba(34, 197, 94, 0.1); color:#22c55e; border:1px solid rgba(34,197,94,0.2); padding:6px 12px; border-radius:8px; font-size:0.75rem; font-weight:600; cursor:pointer;">Re-activate</button>
                                        </form>
                                    <?php endif; ?>

                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Permanently remove this user?')">
                                        <?php csrf_input(); ?>
                                        <input type="hidden" name="delete_id" value="<?php echo $u['id']; ?>">
                                        <button type="submit" name="delete_user" style="background:transparent; color:#fff; border:1px solid rgba(255,255,255,0.1); padding:6px; border-radius:8px; cursor:pointer;" title="Delete">🗑️</button>
                                    </form>
                                <?php else: ?>
                                    <span style="font-size: 0.75rem; color: #4ade80; opacity: 0.8;">Admin Focus</span>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        </div>
    </div>
</div>

<!-- District Assignment Modal -->
<div class="modal-overlay" id="districtModal">
    <div class="modal-box">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem;">
            <h3 style="margin:0; font-size:1.1rem; color:var(--secondary-color);">📍 Assign Districts</h3>
            <button onclick="closeDistrictModal()" style="background:transparent; border:none; color:var(--text-muted); font-size:1.4rem; cursor:pointer; line-height:1;">×</button>
        </div>
        <p id="modal_username" style="font-size:0.85rem; color:var(--text-muted); margin-bottom:0.5rem;"></p>
        <form method="POST">
            <?php csrf_input(); ?>
            <input type="hidden" name="user_id" id="modal_user_id">
            <div class="form-group">
                <label>Select Districts</label>
                <p style="font-size:0.75rem; color:var(--text-muted); margin-bottom:4px;">Leave all unchecked to allow access to all districts.</p>
                <div class="district-checkbox-grid" id="modal_district_grid">
                    <?php foreach ($all_districts as $dist): ?>
                    <label class="district-checkbox-item">
                        <input type="checkbox" name="districts[]" class="modal-dist-cb" value="<?php echo htmlspecialchars($dist); ?>">
                        <?php echo htmlspecialchars($dist); ?>
                    </label>
                    <?php endforeach; ?>
                    <?php if (empty($all_districts)): ?>
                        <p style="color:var(--text-muted); font-size:0.78rem; grid-column:1/-1;">No districts found. Add dealers first.</p>
                    <?php endif; ?>
                </div>
            </div>
            <div style="display:flex; gap:1rem; margin-top:1.5rem;">
                <button type="submit" name="update_districts" class="btn-submit" style="flex:1;">💾 Save Assignment</button>
                <button type="button" onclick="closeDistrictModal()" style="flex:0.4; background:var(--glass-border); color:var(--text-main); border:none; border-radius:12px; cursor:pointer; font-weight:600;">Cancel</button>
            </div>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
<script>
    // Toggle district selector based on role
    function toggleDistrictField() {
        const role = document.getElementById('new_role').value;
        const distField = document.getElementById('district_field');
        distField.style.display = (role === 'tm' || role === 'moderator') ? 'block' : 'none';
    }
    toggleDistrictField();

    // District Modal
    function openDistrictModal(userId, username, currentDistricts) {
        document.getElementById('modal_user_id').value = userId;
        document.getElementById('modal_username').textContent = 'Editing: ' + username;

        const assigned = currentDistricts ? currentDistricts.split(',').map(d => d.trim()) : [];
        const checkboxes = document.querySelectorAll('.modal-dist-cb');
        checkboxes.forEach(cb => {
            cb.checked = assigned.includes(cb.value);
        });

        document.getElementById('districtModal').classList.add('active');
    }

    function closeDistrictModal() {
        document.getElementById('districtModal').classList.remove('active');
    }

    // Close on backdrop click
    document.getElementById('districtModal').addEventListener('click', function(e) {
        if (e.target === this) closeDistrictModal();
    });
</script>
</body>
</html>
