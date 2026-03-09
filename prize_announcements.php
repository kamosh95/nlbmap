<?php
require_once 'includes/security.php';
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'moderator', 'mkt'])) {
    header("Location: login.php");
    exit;
}
require_once 'includes/db_config.php';
require_once 'includes/get_nav.php';

// Create table if not exists
$pdo->exec("CREATE TABLE IF NOT EXISTS `prize_announcements` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `agent_code` varchar(50) NOT NULL,
    `title` varchar(255) DEFAULT 'Big Prize Winner!',
    `description` text DEFAULT NULL,
    `photo_1` varchar(255) DEFAULT NULL,
    `photo_2` varchar(255) DEFAULT NULL,
    `photo_3` varchar(255) DEFAULT NULL,
    `photo_4` varchar(255) DEFAULT NULL,
    `is_active` tinyint(1) DEFAULT 1,
    `created_by` varchar(50) DEFAULT NULL,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// Auto-register nav link if not present
try {
    $nav_check = $pdo->query("SELECT COUNT(*) FROM navigation WHERE url = 'prize_announcements.php'")->fetchColumn();
    if ($nav_check == 0) {
        $pdo->exec("INSERT INTO navigation (label, url, role_access, nav_group, sort_order) 
                    VALUES ('Prize Announcements 🏆', 'prize_announcements.php', 'moderator', 'Main', 50)");
    }
} catch (Exception $e) {}


$message = '';
$status  = '';
$upload_dir = 'uploads/prizes/';
if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

// Handle DELETE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    verify_csrf_token($_POST['csrf_token'] ?? '');
    $del_id = (int)($_POST['del_id'] ?? 0);
    // Remove files
    $row = $pdo->prepare("SELECT photo_1,photo_2,photo_3,photo_4 FROM prize_announcements WHERE id=?");
    $row->execute([$del_id]);
    $old = $row->fetch();
    if ($old) {
        foreach (['photo_1','photo_2','photo_3','photo_4'] as $p) {
            if ($old[$p] && file_exists($old[$p])) unlink($old[$p]);
        }
    }
    $pdo->prepare("DELETE FROM prize_announcements WHERE id=?")->execute([$del_id]);
    $message = '✅ Announcement deleted.';
    $status = 'success';
}

// Handle TOGGLE status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle') {
    verify_csrf_token($_POST['csrf_token'] ?? '');
    $tog_id = (int)($_POST['tog_id'] ?? 0);
    $pdo->prepare("UPDATE prize_announcements SET is_active = IF(is_active=1, 0, 1) WHERE id=?")->execute([$tog_id]);
    header("Location: prize_announcements.php");
    exit;
}

// Handle ADD
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    verify_csrf_token($_POST['csrf_token'] ?? '');
    $agent_code  = trim($_POST['agent_code'] ?? '');
    $title       = trim($_POST['title'] ?? 'Big Prize Winner!');
    $description = trim($_POST['description'] ?? '');

    if (empty($agent_code)) {
        $message = 'Please select an agent.';
        $status = 'error';
    } else {
        $photos = [];
        for ($i = 1; $i <= 4; $i++) {
            $key = "photo_$i";
            $photos[$key] = null;
            if (isset($_FILES[$key]) && $_FILES[$key]['error'] === UPLOAD_ERR_OK) {
                $tmp  = $_FILES[$key]['tmp_name'];
                $orig = $_FILES[$key]['name'];
                if (is_allowed_file($orig, $tmp)) {
                    $ext  = pathinfo($orig, PATHINFO_EXTENSION);
                    $fname = 'prize_' . preg_replace('/[^a-z0-9]/i', '', $agent_code) . '_' . $i . '_' . uniqid() . '.' . $ext;
                    $dest = $upload_dir . $fname;
                    if (move_uploaded_file($tmp, $dest)) {
                        $photos[$key] = $dest;
                    }
                }
            }
        }

        $stmt = $pdo->prepare("INSERT INTO prize_announcements (agent_code, title, description, photo_1, photo_2, photo_3, photo_4, is_active, created_by) VALUES (?,?,?,?,?,?,?,1,?)");
        $stmt->execute([$agent_code, $title, $description, $photos['photo_1'], $photos['photo_2'], $photos['photo_3'], $photos['photo_4'], $_SESSION['username']]);
        $message = '✅ Prize announcement saved! It will now appear on all sellers under this agent when they scan their QR code.';
        $status = 'success';
        log_activity($pdo, "Added Prize Announcement", "Agent: $agent_code, Title: $title", "general");
    }
}

// Load agents list
$agents_list = $pdo->query("SELECT agent_code, name, dealer_code FROM agents ORDER BY agent_code ASC")->fetchAll();

// Load existing announcements
$announcements = $pdo->query("SELECT pa.*, a.name as agent_name FROM prize_announcements pa LEFT JOIN agents a ON pa.agent_code = a.agent_code ORDER BY pa.created_at DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prize Announcements - NLB</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="assets/img/logo1.png">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .prize-layout { display: grid; grid-template-columns: 420px 1fr; gap: 2rem; align-items: start; }
        .prize-card { background: var(--card-bg); border: 1px solid var(--glass-border); border-radius: 20px; padding: 2rem; }
        .prize-card h2 { font-size: 1.1rem; color: var(--secondary-color); margin-bottom: 1.5rem; padding-bottom: 0.75rem; border-bottom: 1px solid var(--glass-border); }

        .ann-card { background: var(--card-bg); border: 1px solid var(--glass-border); border-radius: 18px; overflow: hidden; margin-bottom: 1.5rem; }
        .ann-header { display: flex; align-items: center; gap: 1rem; padding: 1.25rem 1.5rem; background: rgba(255,255,255,0.02); border-bottom: 1px solid var(--glass-border); }
        .ann-header-info { flex: 1; }
        .ann-header-info h3 { margin: 0; font-size: 1rem; color: var(--text-main); }
        .ann-header-info p { margin: 3px 0 0; font-size: 0.78rem; color: var(--text-muted); }
        .ann-photos { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; padding: 1rem 1.5rem; }
        .ann-photo { width: 100%; aspect-ratio: 1; border-radius: 10px; object-fit: cover; border: 1px solid var(--glass-border); cursor: pointer; transition: 0.3s; }
        .ann-photo:hover { transform: scale(1.05); }
        .ann-photo-empty { width: 100%; aspect-ratio: 1; border-radius: 10px; background: rgba(255,255,255,0.03); border: 1px dashed var(--glass-border); display: flex; align-items: center; justify-content: center; font-size: 1.5rem; color: var(--text-muted); }
        .ann-footer { display: flex; gap: 10px; padding: 1rem 1.5rem; border-top: 1px solid var(--glass-border); flex-wrap: wrap; }

        .badge-active { background: rgba(74,222,128,0.1); color: #4ade80; border: 1px solid rgba(74,222,128,0.3); padding: 3px 12px; border-radius: 20px; font-size: 0.72rem; font-weight: 700; }
        .badge-inactive { background: rgba(239,68,68,0.1); color: #f87171; border: 1px solid rgba(239,68,68,0.3); padding: 3px 12px; border-radius: 20px; font-size: 0.72rem; font-weight: 700; }
        .agent-badge { background: rgba(0,114,255,0.12); color: #60a5fa; border: 1px solid rgba(0,114,255,0.25); padding: 3px 10px; border-radius: 20px; font-size: 0.72rem; font-weight: 700; }
        
        .photo-upload-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem; }
        .photo-upload-box { position: relative; }
        .photo-upload-box label { display: flex; flex-direction: column; align-items: center; justify-content: center; height: 120px; border: 2px dashed var(--glass-border); border-radius: 14px; cursor: pointer; transition: 0.3s; font-size: 0.8rem; color: var(--text-muted); gap: 6px; }
        .photo-upload-box label:hover { border-color: var(--secondary-color); color: var(--secondary-color); }
        .photo-upload-box label span { font-size: 2rem; }
        .photo-upload-box input[type=file] { display: none; }
        .photo-preview { width: 100%; height: 120px; border-radius: 12px; object-fit: cover; display: none; }

        .lightbox { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.93); z-index: 9999; align-items: center; justify-content: center; }
        .lightbox.open { display: flex; }
        .lightbox img { max-width: 90vw; max-height: 90vh; border-radius: 12px; }
        .lightbox-close { position: absolute; top: 20px; right: 30px; font-size: 2.5rem; color: #fff; cursor: pointer; opacity: 0.7; }
        .lightbox-close:hover { opacity: 1; }

        @media (max-width: 900px) { .prize-layout { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<div class="container wide">
    <div class="nav-bar" style="margin-bottom: 2rem;">
        <div class="nav-brand">
            <img src="assets/img/Logo.png" alt="NLB Logo">
            <div>
                <h1>🏆 Prize Announcements</h1>
                <p style="color: var(--text-muted); font-size: 0.75rem; margin: 0;">Upload big prize winner photos linked to agents</p>
            </div>
        </div>
        <?php echo render_nav($pdo, $_SESSION['role']); ?>
    </div>

    <?php if ($message): ?>
        <div class="message <?php echo $status; ?>" style="display: block; margin-bottom: 1.5rem;"><?php echo $message; ?></div>
    <?php endif; ?>

    <div class="prize-layout">

        <!-- LEFT: Upload Form -->
        <div class="prize-card" style="position: sticky; top: 20px;">
            <h2>🏆 Add New Prize Announcement</h2>
            <form method="POST" enctype="multipart/form-data">
                <?php csrf_input(); ?>
                <input type="hidden" name="action" value="add">

                <div class="form-group">
                    <label>Select Agent *</label>
                    <select name="agent_code" required style="width: 100%; padding: 0.75rem; background: var(--input-bg); border: 2px solid var(--glass-border); border-radius: 12px; color: var(--text-main); font-family: 'Outfit'; font-size: 0.9rem;">
                        <option value="">-- Select Agent --</option>
                        <?php foreach ($agents_list as $ag): ?>
                            <option value="<?php echo htmlspecialchars($ag['agent_code']); ?>">
                                <?php echo htmlspecialchars($ag['agent_code'] . ' — ' . $ag['name'] . ' (' . $ag['dealer_code'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Announcement Title</label>
                    <input type="text" name="title" value="🏆 Big Prize Winner!" placeholder="e.g. Big Prize Winner – March 2026">
                </div>

                <div class="form-group">
                    <label>Description (Optional)</label>
                    <textarea name="description" placeholder="e.g. Congratulations to all sellers under this agent for contributing to this win!" style="height: 80px;"></textarea>
                </div>

                <div class="form-group">
                    <label>📷 Prize Photos (Up to 4)</label>
                    <div class="photo-upload-row">
                        <?php for ($i = 1; $i <= 4; $i++): ?>
                        <div class="photo-upload-box">
                            <label for="photo_<?php echo $i; ?>" onclick="document.getElementById('photo_<?php echo $i; ?>').click(); return false;">
                                <img id="prev_<?php echo $i; ?>" class="photo-preview">
                                <span id="icon_<?php echo $i; ?>">📸</span>
                                <span id="lbl_<?php echo $i; ?>">Photo <?php echo $i; ?></span>
                            </label>
                            <input type="file" id="photo_<?php echo $i; ?>" name="photo_<?php echo $i; ?>" accept="image/*" onchange="prevPhoto(<?php echo $i; ?>, this)">
                        </div>
                        <?php endfor; ?>
                    </div>
                </div>

                <button type="submit" class="btn-submit" style="margin-top: 0.5rem;">🚀 Publish Announcement</button>
            </form>
        </div>

        <!-- RIGHT: Existing Announcements -->
        <div>
            <h2 style="font-size: 1.1rem; margin-bottom: 1.5rem; color: var(--secondary-color);">
                📋 Published Announcements
                <span style="font-size: 0.8rem; font-weight: 400; color: var(--text-muted); margin-left: 8px;">(<?php echo count($announcements); ?> total)</span>
            </h2>

            <?php if (empty($announcements)): ?>
                <div style="text-align: center; padding: 4rem; color: var(--text-muted);">
                    <div style="font-size: 3rem; margin-bottom: 1rem;">🏆</div>
                    <p>No announcements yet. Add one using the form on the left.</p>
                </div>
            <?php endif; ?>

            <?php foreach ($announcements as $ann): ?>
            <div class="ann-card">
                <div class="ann-header">
                    <div style="font-size: 2rem;">🏆</div>
                    <div class="ann-header-info">
                        <h3><?php echo htmlspecialchars($ann['title']); ?></h3>
                        <p>
                            <span class="agent-badge"><?php echo htmlspecialchars($ann['agent_code']); ?></span>
                            &nbsp;<?php echo htmlspecialchars($ann['agent_name'] ?? ''); ?>
                            &nbsp;·&nbsp; Added by <b><?php echo htmlspecialchars($ann['created_by']); ?></b>
                            &nbsp;·&nbsp; <?php echo date('d M Y', strtotime($ann['created_at'])); ?>
                        </p>
                    </div>
                    <span class="<?php echo $ann['is_active'] ? 'badge-active' : 'badge-inactive'; ?>">
                        <?php echo $ann['is_active'] ? '🟢 Active' : '⭕ Hidden'; ?>
                    </span>
                </div>

                <?php if ($ann['description']): ?>
                    <p style="padding: 0.75rem 1.5rem 0; font-size: 0.85rem; color: var(--text-muted);"><?php echo htmlspecialchars($ann['description']); ?></p>
                <?php endif; ?>

                <div class="ann-photos">
                    <?php for ($i = 1; $i <= 4; $i++): $photo = $ann["photo_$i"]; ?>
                        <?php if ($photo): ?>
                            <img src="<?php echo htmlspecialchars($photo); ?>" class="ann-photo" onclick="openLightbox(this.src)" title="Photo <?php echo $i; ?>">
                        <?php else: ?>
                            <div class="ann-photo-empty">+</div>
                        <?php endif; ?>
                    <?php endfor; ?>
                </div>

                <div class="ann-footer">
                    <!-- Toggle Active -->
                    <form method="POST" style="margin:0;">
                        <?php csrf_input(); ?>
                        <input type="hidden" name="action" value="toggle">
                        <input type="hidden" name="tog_id" value="<?php echo $ann['id']; ?>">
                        <button type="submit" class="btn-submit" style="margin: 0; padding: 0.4rem 1rem; font-size: 0.8rem; background: <?php echo $ann['is_active'] ? 'rgba(239,68,68,0.1)' : 'rgba(74,222,128,0.1)'; ?>; color: <?php echo $ann['is_active'] ? '#f87171' : '#4ade80'; ?>; border: 1px solid <?php echo $ann['is_active'] ? 'rgba(239,68,68,0.3)' : 'rgba(74,222,128,0.3)'; ?>;">
                            <?php echo $ann['is_active'] ? '⭕ Deactivate' : '🟢 Activate'; ?>
                        </button>
                    </form>
                    <!-- Delete -->
                    <form method="POST" style="margin:0;" onsubmit="return confirm('Delete this announcement?');">
                        <?php csrf_input(); ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="del_id" value="<?php echo $ann['id']; ?>">
                        <button type="submit" class="btn-submit" style="margin: 0; padding: 0.4rem 1rem; font-size: 0.8rem; background: rgba(239,68,68,0.1); color: #f87171; border: 1px solid rgba(239,68,68,0.3);">🗑️ Delete</button>
                    </form>
                    <p style="font-size: 0.72rem; color: var(--text-muted); margin: auto 0;">
                        🔗 Appears on QR scans for all sellers under <b><?php echo htmlspecialchars($ann['agent_code']); ?></b>
                    </p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Lightbox -->
<div id="lightbox" class="lightbox" onclick="closeLightbox()">
    <span class="lightbox-close" onclick="closeLightbox()">✕</span>
    <img id="lightbox-img" src="">
</div>

<script>
function prevPhoto(num, input) {
    const file = input.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onloadend = () => {
        const prev = document.getElementById('prev_' + num);
        prev.src = reader.result;
        prev.style.display = 'block';
        document.getElementById('icon_' + num).style.display = 'none';
        document.getElementById('lbl_' + num).style.display = 'none';
    };
    reader.readAsDataURL(file);
}

function openLightbox(src) {
    const lb = document.getElementById('lightbox');
    document.getElementById('lightbox-img').src = src;
    lb.classList.add('open');
}
function closeLightbox() {
    document.getElementById('lightbox').classList.remove('open');
}
</script>
<?php include 'includes/footer.php'; ?>
</body>
</html>
