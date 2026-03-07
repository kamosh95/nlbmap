<?php
require_once 'includes/security.php';
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

require_once 'includes/db_config.php';
require_once 'includes/get_nav.php';

$message = '';
$status  = '';
$editing = null;

// --- Ensure tables exist on first visit ---
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `custom_fields` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `field_label`   varchar(100) NOT NULL,
        `field_type`    varchar(50)  DEFAULT 'text',
        `field_name`    varchar(50)  NOT NULL,
        `placeholder`   varchar(255) DEFAULT '',
        `default_value` varchar(255) DEFAULT '',
        `is_required`   tinyint(1)   DEFAULT 0,
        `sort_order`    int(11)      DEFAULT 0,
        `visible_for`   varchar(50)  DEFAULT 'all',
        `field_options` text         DEFAULT NULL,
        `display_section` varchar(50) DEFAULT 'additional',
        `created_at`    timestamp    DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `field_name` (`field_name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `counter_custom_values` (
        `id`          int(11) NOT NULL AUTO_INCREMENT,
        `counter_id`    int(11) NOT NULL,
        `field_id`    int(11) NOT NULL,
        `field_value` text    DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `counter_id` (`counter_id`),
        KEY `field_id` (`field_id`),
        FOREIGN KEY (`counter_id`) REFERENCES `counters`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`field_id`) REFERENCES `custom_fields`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `settings` (
        `setting_key` VARCHAR(50) NOT NULL,
        `setting_value` TEXT DEFAULT NULL,
        PRIMARY KEY (`setting_key`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    $pdo->exec("INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`) VALUES ('enable_location', '1');");
} catch (PDOException $e) { /* tables already exist */ }

// --- AJAX: Reorder fields ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reorder') {
    verify_csrf_token($_POST['csrf_token'] ?? '');
    $order = json_decode($_POST['order'] ?? '[]', true);
    if (is_array($order)) {
        $upd = $pdo->prepare("UPDATE custom_fields SET sort_order=? WHERE id=?");
        foreach ($order as $pos => $fid) {
            $upd->execute([$pos, (int)$fid]);
        }
    }
    header('Content-Type: application/json');
    echo json_encode(['ok' => true]);
    exit;
}

// --- POST Handler ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token($_POST['csrf_token'] ?? '');

    // ADD / UPDATE FIELD
    if (isset($_POST['save_field'])) {
        $label      = trim($_POST['field_label']);
        $type       = $_POST['field_type'];
        $placeholder= trim($_POST['placeholder']);
        $default    = trim($_POST['default_value']);
        $required   = isset($_POST['is_required']) ? 1 : 0;
        $order      = (int)($_POST['sort_order'] ?? 0);
        $visible_for= $_POST['visible_for'] ?? 'all';
        $options     = trim($_POST['field_options'] ?? '');
        $section    = $_POST['display_section'] ?? 'additional';
        $edit_id    = (int)($_POST['edit_id'] ?? 0);

        // Auto-generate field_name from label (slug)
        $field_name = strtolower(preg_replace('/[^a-z0-9]+/i', '_', $label));
        $field_name = trim($field_name, '_');

        if (empty($label)) {
            $message = "Field Label is required.";
            $status  = 'error';
        } else {
            try {
                if ($edit_id > 0) {
                    // On edit, keep original field_name (don't regenerate – would break existing data)
                    $stmt = $pdo->prepare("UPDATE custom_fields SET
                        field_label=?, field_type=?, placeholder=?,
                        default_value=?, is_required=?, sort_order=?,
                        visible_for=?, field_options=?, display_section=?
                        WHERE id=?");
                    $stmt->execute([$label, $type, $placeholder, $default, $required, $order, $visible_for, $options, $section, $edit_id]);
                    $message = "✅ Field updated successfully.";
                    $status  = 'success';
                } else {
                    // Ensure unique field_name
                    $check = $pdo->prepare("SELECT COUNT(*) FROM custom_fields WHERE field_name = ?");
                    $check->execute([$field_name]);
                    if ($check->fetchColumn() > 0) {
                        $field_name .= '_' . time();
                    }
                    $stmt = $pdo->prepare("INSERT INTO custom_fields
                        (field_label, field_type, field_name, placeholder, default_value, is_required, sort_order, visible_for, field_options, display_section)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$label, $type, $field_name, $placeholder, $default, $required, $order, $visible_for, $options, $section]);
                    $message = "✅ New field added successfully.";
                    $status  = 'success';
                }
            } catch (PDOException $e) {
                $message = "Database error: " . $e->getMessage();
                $status  = 'error';
            }
        }
    }

    // DELETE FIELD
    if (isset($_POST['delete_field'])) {
        $del_id = (int)$_POST['delete_id'];
        try {
            $pdo->prepare("DELETE FROM custom_fields WHERE id=?")->execute([$del_id]);
            $message = "✅ Field deleted.";
            $status  = 'success';
        } catch (PDOException $e) {
            $message = "Error: " . $e->getMessage();
            $status  = 'error';
        }
    }

    // TOGGLE LOCATION SETTING
    if (isset($_POST['toggle_location'])) {
        $stmt_loc = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'enable_location'");
        $current = $stmt_loc->fetchColumn();
        $new_val = ($current === '1') ? '0' : '1';
        $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'enable_location'")->execute([$new_val]);
        $message = "✅ System settings updated.";
        $status  = 'success';
    }
}

// --- Load for Edit ---
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM custom_fields WHERE id=?");
    $stmt->execute([(int)$_GET['edit']]);
    $editing = $stmt->fetch();
}

// --- Fetch Settings ---
$enable_location = true;
try {
    $settings = $pdo->query("SELECT setting_key, setting_value FROM settings")->fetchAll(PDO::FETCH_KEY_PAIR);
    $enable_location = ($settings['enable_location'] ?? '1') === '1';
} catch (PDOException $e) { /* Settings table naturally created elsewhere */ }

// --- Fetch all fields ---
$fields = $pdo->query("SELECT * FROM custom_fields ORDER BY sort_order ASC, id ASC")->fetchAll();

$type_labels = [
    'text'     => '📝 Text',
    'number'   => '🔢 Number',
    'tel'      => '📞 Phone (Tel)',
    'email'    => '📧 Email',
    'textarea' => '📄 Paragraph',
    'date'     => '📅 Date',
    'radio'    => '🔘 Radio Buttons',
    'checkbox' => '✅ Tick Box',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Form Field Manager - NLB Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="assets/img/logo1.png">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .mgr-grid {
            display: grid;
            grid-template-columns: 380px 1fr;
            gap: 2rem;
            align-items: start;
        }
        .sidebar-card {
            background: var(--card-bg);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            padding: 2rem;
            position: sticky;
            top: 20px;
        }
        .sidebar-card h2 {
            font-size: 1.1rem;
            color: var(--secondary-color);
            margin-bottom: 1.5rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid var(--glass-border);
        }
        .field-row-card {
            background: var(--card-bg);
            border: 1px solid var(--glass-border);
            border-radius: 14px;
            padding: 1rem 1.25rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            transition: all 0.3s;
            margin-bottom: 0.75rem;
        }
        .field-row-card:hover { background: rgba(255,255,255,0.05); }
        .field-icon {
            width: 42px; height: 42px;
            border-radius: 12px;
            background: rgba(0,114,255,0.12);
            border: 1px solid rgba(0,114,255,0.2);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.2rem; flex-shrink: 0;
        }
        .field-info { flex: 1; min-width: 0; }
        .field-info .f-label { font-weight: 600; font-size: 0.95rem; color: var(--text-main); }
        .field-info .f-meta  { font-size: 0.75rem; color: var(--text-muted); margin-top: 2px; }
        .badge-type {
            font-size: 0.65rem; padding: 2px 8px;
            border-radius: 20px; font-weight: 600;
            background: rgba(0,114,255,0.15);
            color: #60a5fa;
            border: 1px solid rgba(0,114,255,0.3);
        }
        .badge-req {
            font-size: 0.65rem; padding: 2px 8px;
            border-radius: 20px; font-weight: 600;
            background: rgba(239,68,68,0.12);
            color: #f87171;
            border: 1px solid rgba(239,68,68,0.3);
            margin-left: 4px;
        }
        .field-actions { display: flex; gap: 6px; flex-shrink: 0; }
        .btn-action-edit {
            padding: 5px 14px; border-radius: 8px; font-size: 0.8rem;
            background: rgba(59,130,246,0.12); color: #60a5fa;
            border: 1px solid rgba(59,130,246,0.3);
            text-decoration: none; transition: 0.3s;
            display: inline-flex; align-items: center; gap: 4px;
        }
        .btn-action-edit:hover { background: rgba(59,130,246,0.25); }
        .btn-action-del {
            padding: 5px 14px; border-radius: 8px; font-size: 0.8rem;
            background: rgba(239,68,68,0.1); color: #f87171;
            border: 1px solid rgba(239,68,68,0.25);
            cursor: pointer; transition: 0.3s;
            display: inline-flex; align-items: center; gap: 4px;
        }
        .btn-action-del:hover { background: rgba(239,68,68,0.25); }
        .empty-state {
            text-align: center; padding: 3rem 2rem;
            color: var(--text-muted);
        }
        .empty-state .icon { font-size: 3rem; margin-bottom: 1rem; }
        .type-icon { font-size: 1.1rem; }
        .form-select {
            width: 100%; padding: 12px 14px;
            background: var(--input-bg); border: 1px solid var(--glass-border);
            color: var(--text-main); border-radius: 12px; outline: none;
            font-family: 'Outfit', sans-serif; font-size: 0.9rem;
            transition: border-color 0.3s;
        }
        .form-select:focus { border-color: var(--secondary-color); }
        .sort-badge {
            font-size: 0.7rem; padding: 2px 8px; border-radius: 20px;
            background: rgba(255,255,255,0.06); color: var(--text-muted);
            border: 1px solid var(--glass-border);
            margin-left: 4px;
        }
        @media (max-width: 900px) {
            .mgr-grid { grid-template-columns: 1fr; }
            .sidebar-card { position: static; }
        }
    </style>
</head>
<body>
<div class="container wide">

    <!-- Nav -->
    <div class="nav-bar">
        <div class="nav-brand">
            <img src="assets/img/Logo.png" alt="NLB Logo">
            <div>
                <h1>Form Field Manager</h1>
                <p style="color: var(--text-muted); font-size: 0.75rem; margin: 0;">Customize data collection fields</p>
            </div>
        </div>
        <?php echo render_nav($pdo, $_SESSION['role']); ?>
    </div>

    <?php if ($message): ?>
    <div class="message <?php echo $status; ?>" style="display: block; margin-bottom: 1.5rem;">
        <?php echo $message; ?>
    </div>
    <?php endif; ?>

    <div class="mgr-grid">

        <!-- ── LEFT: Add / Edit Form ── -->
        <div class="sidebar-card">
            <h2><?php echo $editing ? '✏️ Edit Field' : '➕ Add New Field'; ?></h2>

            <form method="POST" id="fieldForm">
                <?php csrf_input(); ?>
                <?php if ($editing): ?>
                    <input type="hidden" name="edit_id" value="<?php echo $editing['id']; ?>">
                <?php endif; ?>

                <div class="form-group">
                    <label>Field Label <span style="color:#f87171;">*</span></label>
                    <input type="text" name="field_label" id="field_label"
                           value="<?php echo htmlspecialchars($editing['field_label'] ?? ''); ?>"
                           placeholder="e.g. Telephone Number" required
                           oninput="autoSlug(this.value)">
                    <p style="font-size:0.72rem; color: var(--text-muted); margin-top:4px;">
                        Slug: <code id="slug_preview" style="color:var(--secondary-color);">—</code>
                    </p>
                </div>

                <div class="form-group">
                    <label>Field Type</label>
                    <select name="field_type" class="form-select" id="field_type" onchange="toggleDefault(this.value)">
                        <?php foreach ($type_labels as $val => $lbl): ?>
                        <option value="<?php echo $val; ?>" <?php echo ($editing['field_type'] ?? 'text') === $val ? 'selected' : ''; ?>>
                            <?php echo $lbl; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Placeholder Text</label>
                    <input type="text" name="placeholder"
                           value="<?php echo htmlspecialchars($editing['placeholder'] ?? ''); ?>"
                           placeholder="e.g. Enter phone number"
                           id="input_placeholder">
                </div>

                <div class="form-group" id="default_wrap">
                    <label>Default Value
                        <span style="font-size:0.72rem; color:var(--text-muted); font-weight:400;">
                            (Pre-filled for every new entry)
                        </span>
                    </label>
                    <input type="text" name="default_value" id="default_value"
                           value="<?php echo htmlspecialchars($editing['default_value'] ?? ''); ?>"
                           placeholder="e.g. +94">
                </div>

                <div class="form-group">
                    <label>Visible For (Sales Method Grouping)</label>
                    <select name="visible_for" class="form-select">
                        <option value="all" <?php echo ($editing['visible_for'] ?? 'all') === 'all' ? 'selected' : ''; ?>>🌍 All Methods</option>
                        <option value="Ticket Counter" <?php echo ($editing['visible_for'] ?? '') === 'Ticket Counter' ? 'selected' : ''; ?>>🏪 Ticket Counter</option>
                        <option value="Mobile Sales" <?php echo ($editing['visible_for'] ?? '') === 'Mobile Sales' ? 'selected' : ''; ?>>🛵 Mobile Sales</option>
                    </select>
                </div>

                <div class="form-group" id="options_wrap" style="display:none;">
                    <label>Radio Options (Comma separated)</label>
                    <input type="text" name="field_options" id="field_options"
                           value="<?php echo htmlspecialchars($editing['field_options'] ?? ''); ?>"
                           placeholder="Yes, No, Maybe">
                </div>

                <div class="form-group">
                    <label>Placement / Section</label>
                    <select name="display_section" class="form-select">
                        <option value="additional" <?php echo ($editing['display_section'] ?? 'additional') === 'additional' ? 'selected' : ''; ?>>📍 Additional Section (Bottom)</option>
                        <option value="main" <?php echo ($editing['display_section'] ?? '') === 'main' ? 'selected' : ''; ?>>⭐ Main Section (Top)</option>
                    </select>
                </div>

                <div class="form-group" style="display:flex; align-items:center; gap:10px;">
                    <label style="margin:0; display:flex; align-items:center; gap:10px; cursor:pointer;">
                        <div style="position:relative;">
                            <input type="checkbox" name="is_required" id="is_required"
                                   <?php echo ($editing['is_required'] ?? 0) ? 'checked' : ''; ?>
                                   style="width:18px; height:18px; cursor:pointer; accent-color: var(--secondary-color);">
                        </div>
                        <span>Mark as Required Field</span>
                    </label>
                </div>

                <div class="form-group">
                    <label>Sort Order
                        <span style="font-size:0.72rem; color:var(--text-muted); font-weight:400;">(lower = first)</span>
                    </label>
                    <input type="number" name="sort_order"
                           value="<?php echo htmlspecialchars($editing['sort_order'] ?? count($fields)); ?>"
                           min="0" placeholder="0">
                </div>

                <div style="display:flex; gap:10px; margin-top:0.5rem;">
                    <button type="submit" name="save_field" class="btn-submit" style="flex:1; margin-top:0;">
                        <?php echo $editing ? '💾 Save Changes' : '✨ Add Field'; ?>
                    </button>
                    <?php if ($editing): ?>
                    <a href="admin_fields.php" class="btn-delete" style="text-decoration:none; display:flex; align-items:center; padding:0.8rem 1rem; border-radius:12px;">Cancel</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- ── RIGHT: Field List ── -->
        <div>
            <!-- System Settings Block -->
            <div class="field-row-card" style="margin-bottom:2rem; background:rgba(255, 204, 0, 0.05); border-color:var(--secondary-color);">
                <div class="field-icon" style="background:rgba(255, 204, 0, 0.2); border-color:var(--secondary-color);">📍</div>
                <div class="field-info">
                    <div class="f-label" style="font-size:1rem;">Administrative Location Fields</div>
                    <div class="f-meta">Toggle the Province, District, DS, and GN Division 4-step dropdown feature.</div>
                </div>
                <div class="field-actions">
                    <form method="POST" style="margin:0;">
                        <?php csrf_input(); ?>
                        <input type="hidden" name="toggle_location" value="1">
                        <label class="toggle-switch" style="display:flex; align-items:center; cursor:pointer;" onclick="this.closest('form').submit()">
                            <input type="checkbox" name="enable_location" value="1" <?php echo $enable_location ? 'checked' : ''; ?> style="pointer-events:none; width:18px; height:18px; accent-color:var(--secondary-color);">
                            <span style="margin-left:8px; font-weight:600; font-size:0.9rem; color:var(--text-main);"><?php echo $enable_location ? 'Enabled' : 'Disabled'; ?></span>
                        </label>
                    </form>
                </div>
            </div>
            <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:1.25rem;">
                <h2 style="font-size:1.1rem; margin:0;">
                    📋 Active Form Fields
                    <span style="font-size:0.8rem; color:var(--text-muted); font-weight:400; margin-left:8px;">
                        (<?php echo count($fields); ?> custom fields)
                    </span>
                </h2>
            </div>

            <?php if (empty($fields)): ?>
            <div class="empty-state card">
                <div class="icon">🔧</div>
                <p style="font-size:1rem; font-weight:600; margin-bottom:0.5rem;">No custom fields yet</p>
                <p style="font-size:0.85rem;">Add your first field using the form on the left. <br>For example, add a <strong>Tel No</strong> field with <code>+94</code> as default value.</p>
            </div>
            <?php else: ?>

            <?php
            $type_icons = [
                'text'     => '📝',
                'number'   => '🔢',
                'tel'      => '📞',
                'email'    => '📧',
                'textarea' => '📄',
                'date'     => '📅',
                'radio'    => '🔘',
                'checkbox' => '✅',
            ];
            ?>

            <?php foreach ($fields as $f): ?>
            <div class="field-row-card" draggable="true" data-id="<?php echo $f['id']; ?>">
                <!-- Drag Handle -->
                <div class="drag-handle" title="Drag to reorder">⠿</div>
                <div class="field-icon">
                    <?php echo $type_icons[$f['field_type']] ?? '📝'; ?>
                </div>
                <div class="field-info">
                    <div class="f-label">
                        <?php echo htmlspecialchars($f['field_label']); ?>
                        <span class="badge-type"><?php echo strtoupper($f['field_type']); ?></span>
                        <span class="badge-type" style="background:rgba(255,255,255,0.05); color:var(--text-muted); border-color:var(--glass-border); text-transform:none;">
                            <?php echo $f['visible_for'] === 'all' ? '🌍 All' : ($f['visible_for'] === 'Ticket Counter' ? '🏪 Counter' : '🛵 Mobile'); ?>
                        </span>
                        <span class="badge-type" style="background:rgba(255,193,7,0.1); color:#ffc107; border-color:rgba(255,193,7,0.3); text-transform:none;">
                            <?php echo $f['display_section'] === 'main' ? '⭐ Main Section' : '📍 Additional'; ?>
                        </span>
                        <?php if ($f['is_required']): ?>
                        <span class="badge-req">REQUIRED</span>
                        <?php endif; ?>
                        <span class="sort-badge" id="ord-<?php echo $f['id']; ?>">#<?php echo $f['sort_order']; ?></span>
                    </div>
                    <div class="f-meta">
                        <span>Key: <code><?php echo htmlspecialchars($f['field_name']); ?></code></span>
                        <?php if ($f['placeholder']): ?>
                        &nbsp;·&nbsp; Placeholder: "<?php echo htmlspecialchars($f['placeholder']); ?>"
                        <?php endif; ?>
                        <?php if ($f['default_value'] !== ''): ?>
                        &nbsp;·&nbsp; Default: <strong style="color:var(--secondary-color);"><?php echo htmlspecialchars($f['default_value']); ?></strong>
                        <?php endif; ?>
                        <?php if ($f['field_type'] === 'radio' && $f['field_options']): ?>
                        <div style="margin-top:5px; font-size:0.75rem;">
                            Options: <code style="background:rgba(255,255,255,0.03); padding:2px 6px; border-radius:4px;"><?php echo htmlspecialchars($f['field_options']); ?></code>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="field-actions">
                    <a href="admin_fields.php?edit=<?php echo $f['id']; ?>" class="btn-action-edit">✏️ Edit</a>
                    <form method="POST" style="display:inline;"
                          onsubmit="return confirm('Delete field \'<?php echo htmlspecialchars(addslashes($f['field_label'])); ?>\'? All stored data for this field will also be removed.')">
                        <?php csrf_input(); ?>
                        <input type="hidden" name="delete_id" value="<?php echo $f['id']; ?>">
                        <button type="submit" name="delete_field" class="btn-action-del">🗑️ Delete</button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>

            <!-- Preview Notice -->
            <div style="margin-top:1.5rem; padding:1rem 1.25rem; background:rgba(0,114,255,0.06); border:1px solid rgba(0,114,255,0.2); border-radius:14px; font-size:0.82rem; color:var(--text-muted);">
                💡 These fields will appear in the <strong style="color:var(--text-main);">Details Entry</strong> page and the <strong style="color:var(--text-main);">Edit Record</strong> page automatically.
            </div>
            <?php endif; ?>
        </div>

    </div><!-- end mgr-grid -->
</div>
<?php include 'includes/footer.php'; ?>

<script>
function autoSlug(val) {
    const slug = val.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_|_$/g, '');
    document.getElementById('slug_preview').textContent = slug || '—';
}
function toggleDefault(type) {
    const ph = document.getElementById('input_placeholder');
    const optWrap = document.getElementById('options_wrap');
    
    // Show/hide options field for radio buttons
    if (type === 'radio') {
        optWrap.style.display = 'block';
    } else {
        optWrap.style.display = 'none';
    }

    if (type === 'tel') {
        document.getElementById('default_value').placeholder = 'e.g. +94';
        ph.placeholder = 'e.g. 07X XXX XXXX';
    } else if (type === 'date') {
        document.getElementById('default_value').placeholder = 'e.g. 2026-01-01';
    } else if (type === 'email') {
        document.getElementById('default_value').placeholder = 'e.g. example@nlb.lk';
        ph.placeholder = 'e.g. seller@email.com';
    } else {
        document.getElementById('default_value').placeholder = 'Pre-filled value';
        ph.placeholder = 'Placeholder text';
    }
}
// Init slug for editing mode
const lbl = document.getElementById('field_label');
if (lbl && lbl.value) autoSlug(lbl.value);
toggleDefault(document.getElementById('field_type').value);

// ── Drag & Drop Sort ──
(function() {
    const CSRF_TOKEN = '<?php echo $_SESSION['csrf_token'] ?? ''; ?>';
    let dragSrc = null;

    function attachDrag(card) {
        card.addEventListener('dragstart', function(e) {
            dragSrc = card;
            card.classList.add('dragging');
            e.dataTransfer.effectAllowed = 'move';
        });
        card.addEventListener('dragend', function() {
            card.classList.remove('dragging');
            document.querySelectorAll('.field-row-card').forEach(c => c.classList.remove('drag-over'));
            saveOrder();
        });
        card.addEventListener('dragover', function(e) {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            if (dragSrc && dragSrc !== card) {
                card.classList.add('drag-over');
            }
        });
        card.addEventListener('dragleave', function() {
            card.classList.remove('drag-over');
        });
        card.addEventListener('drop', function(e) {
            e.preventDefault();
            card.classList.remove('drag-over');
            if (dragSrc && dragSrc !== card) {
                const list   = card.parentNode;
                const cards  = [...list.querySelectorAll('.field-row-card')];
                const srcIdx = cards.indexOf(dragSrc);
                const dstIdx = cards.indexOf(card);
                if (srcIdx < dstIdx) {
                    card.after(dragSrc);
                } else {
                    card.before(dragSrc);
                }
            }
        });
    }

    document.querySelectorAll('.field-row-card[draggable]').forEach(attachDrag);

    function saveOrder() {
        const ids = [...document.querySelectorAll('.field-row-card[data-id]')].map(c => c.dataset.id);
        // Update visible badges
        ids.forEach((id, i) => {
            const badge = document.getElementById('ord-' + id);
            if (badge) badge.textContent = '#' + i;
        });
        // Show saving toast
        showToast('⏳ Saving order...');
        const form = new FormData();
        form.append('action',     'reorder');
        form.append('csrf_token', CSRF_TOKEN);
        form.append('order',      JSON.stringify(ids));
        fetch('admin_fields.php', { method: 'POST', body: form })
            .then(r => r.json())
            .then(d => { if (d.ok) showToast('✅ Order saved!', 'success'); })
            .catch(() => showToast('❌ Save failed', 'error'));
    }

    function showToast(msg, type = 'info') {
        let t = document.getElementById('sortToast');
        if (!t) {
            t = document.createElement('div');
            t.id = 'sortToast';
            t.style.cssText = 'position:fixed;bottom:24px;right:24px;padding:12px 20px;border-radius:12px;font-size:0.85rem;font-weight:600;z-index:9999;transition:opacity 0.3s;opacity:0;';
            document.body.appendChild(t);
        }
        t.textContent = msg;
        t.style.background = type === 'success' ? 'rgba(74,222,128,0.15)' : type === 'error' ? 'rgba(248,113,113,0.15)' : 'rgba(96,165,250,0.15)';
        t.style.border     = type === 'success' ? '1px solid rgba(74,222,128,0.4)' : type === 'error' ? '1px solid rgba(248,113,113,0.4)' : '1px solid rgba(96,165,250,0.3)';
        t.style.color      = type === 'success' ? '#4ade80' : type === 'error' ? '#f87171' : '#60a5fa';
        t.style.opacity    = '1';
        clearTimeout(t._timer);
        t._timer = setTimeout(() => { t.style.opacity = '0'; }, 2000);
    }
})();
</script>
    <?php include 'includes/footer.php'; ?>
</body>
</html>
