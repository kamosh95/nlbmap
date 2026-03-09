<?php
require_once 'includes/security.php';
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}
require_once 'includes/db_config.php';
require_once 'includes/get_nav.php';

$dealers = $pdo->query("SELECT * FROM dealers ORDER BY dealer_code ASC")->fetchAll();

$msg = $_GET['msg'] ?? '';
$err = $_GET['err'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>View Dealers - NLB</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .toolbar {
            display: flex;
            gap: 1rem;
            align-items: center;
            flex-wrap: wrap;
            margin-bottom: 1.5rem;
            background: var(--nav-bg);
            padding: 1rem 1.5rem;
            border-radius: 16px;
            border: 1px solid var(--glass-border);
        }
        .toolbar-left { flex: 1; }
        .search-input {
            width: 100%;
            padding: 0.7rem 1rem 0.7rem 2.8rem;
            background: var(--input-bg);
            border: 2px solid var(--glass-border);
            border-radius: 12px;
            color: var(--text-main);
            font-size: 0.95rem;
            font-family: 'Outfit', sans-serif;
            outline: none;
            transition: border-color 0.3s;
            box-sizing: border-box;
        }
        .search-input:focus { border-color: var(--secondary-color); }
        .search-wrap { position: relative; }
        .search-icon { position: absolute; left: 0.9rem; top: 50%; transform: translateY(-50%); font-size: 1rem; opacity: 0.5; pointer-events: none; }
        .sort-btn {
            display: flex; align-items: center; gap: 6px;
            padding: 0.65rem 1.1rem;
            background: rgba(255,255,255,0.05);
            border: 2px solid var(--glass-border);
            border-radius: 12px;
            color: var(--text-main);
            font-size: 0.85rem;
            font-family: 'Outfit', sans-serif;
            cursor: pointer;
            transition: all 0.3s;
            white-space: nowrap;
        }
        .sort-btn:hover, .sort-btn.active { border-color: var(--secondary-color); color: var(--secondary-color); }
        .result-count {
            font-size: 0.82rem;
            color: var(--text-muted);
            padding: 0 0.5rem;
            white-space: nowrap;
        }
        .dealer-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(230px, 1fr)); gap: 1.5rem; }
        .dealer-card { background: var(--card-bg); border: 1px solid var(--glass-border); border-radius: 20px; padding: 1.5rem; transition: 0.3s; }
        .dealer-card:hover { transform: translateY(-4px); box-shadow: 0 12px 35px rgba(0,0,0,0.3); }
        .dealer-card.hidden { display: none; }
        .dealer-photo { width: 100%; height: 200px; object-fit: cover; border-radius: 12px; margin-bottom: 1rem; border: 2px solid var(--glass-border); }
        .dealer-info h3 { margin: 0 0 0.5rem 0; color: var(--secondary-color); }
        .dealer-info p { margin: 0.25rem 0; font-size: 0.9rem; color: var(--text-muted); }
        .address-box { margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--glass-border); }
        .empty-state { text-align: center; padding: 4rem 2rem; color: var(--text-muted); grid-column: 1/-1; }
        .empty-state .icon { font-size: 3rem; margin-bottom: 1rem; opacity: 0.4; }
        .code-badge {
            display: inline-block;
            background: rgba(var(--secondary-rgb, 0, 114, 255), 0.1);
            border: 1px solid var(--secondary-color);
            color: var(--secondary-color);
            border-radius: 8px;
            padding: 2px 10px;
            font-size: 0.8rem;
            font-weight: 600;
            letter-spacing: 0.5px;
            margin-bottom: 0.5rem;
        }
    </style>
</head>
<body>
    <div class="container wide">
        <div class="nav-bar" style="margin-bottom: 2rem;">
            <div class="nav-brand">
                <img src="assets/img/Logo.png" alt="NLB Logo">
                <div>
                    <h1>Dealer Directory</h1>
                    <p style="color: var(--text-muted); font-size: 0.75rem; margin: 0; opacity: 0.8;">
                        Registered Dealers &nbsp;·&nbsp; Logged in as <span class="role-badge badge-<?php echo $_SESSION['role']; ?>" style="padding: 2px 8px; font-size: 0.65rem;"><?php echo e($_SESSION['username']); ?></span>
                    </p>
                </div>
            </div>
            <?php echo render_nav($pdo, $_SESSION['role']); ?>
        </div>

        <?php if ($msg): ?>
            <div class="message success" style="display:block; margin-bottom:1rem;">✅ <?php echo e($msg); ?></div>
        <?php endif; ?>
        <?php if ($err): ?>
            <div class="message error" style="display:block; margin-bottom:1rem;">❌ <?php echo e($err); ?></div>
        <?php endif; ?>

        <!-- Toolbar: Search + Sort -->
        <div class="toolbar">
            <div class="toolbar-left">
                <div class="search-wrap">
                    <span class="search-icon">🔍</span>
                    <input type="text" class="search-input" id="searchInput" placeholder="Search by name, code, NIC, province or district...">
                </div>
            </div>
            <button class="sort-btn active" id="sortAsc" onclick="setSort('asc')">🔼 Code A→Z</button>
            <button class="sort-btn" id="sortDesc" onclick="setSort('desc')">🔽 Code Z→A</button>
            <span class="result-count" id="resultCount"><?php echo count($dealers); ?> dealers</span>
        </div>

        <div class="dealer-grid" id="dealerGrid">
            <?php foreach ($dealers as $d):
                $addresses = $pdo->prepare("SELECT address_text FROM dealer_addresses WHERE dealer_id = ?");
                $addresses->execute([$d['id']]);
                $locs = $pdo->prepare("SELECT location_link FROM dealer_locations WHERE dealer_id = ?");
                $locs->execute([$d['id']]);
                $addrList = $addresses->fetchAll();
                $locList  = $locs->fetchAll();
            ?>
            <div class="dealer-card"
                 data-name="<?php echo strtolower(e($d['name'])); ?>"
                 data-code="<?php echo strtolower(e($d['dealer_code'])); ?>"
                 data-nic="<?php echo strtolower(e($d['nic_new'] ?: $d['nic_old'])); ?>"
                 data-province="<?php echo strtolower(e($d['province'])); ?>"
                 data-district="<?php echo strtolower(e($d['district'])); ?>">

                <?php if($d['photo']): ?>
                    <img src="<?php echo e($d['photo']); ?>" class="dealer-photo" alt="Dealer Photo">
                <?php endif; ?>

                <div class="dealer-info">
                    <div class="code-badge"><?php echo e($d['dealer_code']); ?></div>
                    <h3><?php echo e($d['name']); ?></h3>
                    <p><strong>NIC:</strong> <?php echo e($d['nic_new'] ?: $d['nic_old']); ?></p>
                    <p><strong>Province:</strong> <?php echo e($d['province']); ?> (<?php echo e($d['district']); ?>)</p>
                    <?php if($d['phone']): ?><p><strong>Phone:</strong> <?php echo e($d['phone']); ?></p><?php endif; ?>

                    <div class="address-box">
                        <?php if ($addrList): ?>
                            <p><strong>Addresses:</strong></p>
                            <?php foreach($addrList as $a): ?>
                                <p style="color: var(--text-main); font-size: 0.85rem;">📍 <?php echo e($a['address_text']); ?></p>
                            <?php endforeach; ?>
                        <?php endif; ?>

                        <?php if ($locList): ?>
                            <p style="margin-top:0.5rem;"><strong>Locations:</strong></p>
                            <?php foreach($locList as $l): ?>
                                <a href="<?php echo e($l['location_link']); ?>" target="_blank" style="display:block; font-size:0.8rem; color:var(--secondary-color); overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">🔗 View on Maps</a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <div style="margin-top: 1.5rem; display: flex; flex-wrap: wrap; gap: 10px; border-top: 1px solid rgba(255,255,255,0.05); padding-top: 1rem;">
                        <a href="view_agents.php?dealer=<?php echo urlencode($d['dealer_code']); ?>" style="flex:1; min-width: 80px; text-align:center; text-decoration:none; background:rgba(16,185,129,0.1); color:#10b981; border:1px solid rgba(16,185,129,0.2); padding:8px; border-radius:10px; font-size:0.85rem; font-weight:500;">👥 Agents</a>
                        <a href="edit_dealer.php?id=<?php echo $d['id']; ?>" style="flex:1; min-width: 80px; text-align:center; text-decoration:none; background:rgba(59,130,246,0.1); color:#60a5fa; border:1px solid rgba(59,130,246,0.2); padding:8px; border-radius:10px; font-size:0.85rem; font-weight:500;">✏️ Edit</a>
                        <form method="POST" action="ajax/delete_entity.php" style="flex:1; min-width: 80px;" onsubmit="return confirm('Delete this dealer permanently?')">
                            <?php csrf_input(); ?>
                            <input type="hidden" name="id" value="<?php echo $d['id']; ?>">
                            <input type="hidden" name="type" value="dealer">
                            <button type="submit" style="width:100%; cursor:pointer; background:rgba(239,68,68,0.1); color:#f87171; border:1px solid rgba(239,68,68,0.2); padding:8px; border-radius:10px; font-size:0.85rem; font-weight:500;">🗑️ Delete</button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>

            <div id="emptyState" class="empty-state" style="display:none;">
                <div class="icon">🔍</div>
                <p>No dealers found matching your search.</p>
            </div>
        </div>
    </div>

    <script>
        let currentSort = 'asc';

        function setSort(dir) {
            currentSort = dir;
            document.getElementById('sortAsc').classList.toggle('active', dir === 'asc');
            document.getElementById('sortDesc').classList.toggle('active', dir === 'desc');
            applyFilterAndSort();
        }

        function applyFilterAndSort() {
            const query = document.getElementById('searchInput').value.toLowerCase().trim();
            const grid  = document.getElementById('dealerGrid');
            const cards = Array.from(grid.querySelectorAll('.dealer-card'));

            // Filter
            let visible = [];
            cards.forEach(card => {
                const searchable = [
                    card.dataset.name,
                    card.dataset.code,
                    card.dataset.nic,
                    card.dataset.province,
                    card.dataset.district
                ].join(' ');

                const match = !query || searchable.includes(query);
                card.classList.toggle('hidden', !match);
                if (match) visible.push(card);
            });

            // Sort visible cards by dealer_code
            visible.sort((a, b) => {
                const ca = a.dataset.code;
                const cb = b.dataset.code;
                return currentSort === 'asc'
                    ? ca.localeCompare(cb, undefined, {numeric: true, sensitivity: 'base'})
                    : cb.localeCompare(ca, undefined, {numeric: true, sensitivity: 'base'});
            });

            // Re-append in sorted order (only visible ones get moved to front)
            const emptyState = document.getElementById('emptyState');
            visible.forEach(card => grid.insertBefore(card, emptyState));

            // Update count
            document.getElementById('resultCount').textContent = visible.length + ' dealer' + (visible.length !== 1 ? 's' : '');
            emptyState.style.display = visible.length === 0 ? 'block' : 'none';
        }

        document.getElementById('searchInput').addEventListener('input', applyFilterAndSort);
    </script>
    <?php include 'includes/footer.php'; ?>
</body>
</html>
