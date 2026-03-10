<?php
require_once 'includes/security.php';
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'tm'])) {
    header("Location: login.php");
    exit;
}
require_once 'includes/db_config.php';
require_once 'includes/get_nav.php';

$prov_filter = $_GET['province'] ?? '';
$dist_filter = $_GET['district'] ?? '';
$dealer_filter = $_GET['dealer'] ?? '';

$where = "1=1";
$params = [];
if($prov_filter) { $where .= " AND a.province = ?"; $params[] = $prov_filter; }
if($dist_filter) { $where .= " AND a.district = ?"; $params[] = $dist_filter; }
if($dealer_filter) { $where .= " AND a.dealer_code = ?"; $params[] = $dealer_filter; }

$stmt = $pdo->prepare("SELECT a.*, d.name as dealer_name FROM agents a LEFT JOIN dealers d ON a.dealer_code = d.dealer_code WHERE $where ORDER BY a.agent_code ASC");
$stmt->execute($params);
$agents = $stmt->fetchAll();

$provinces = $pdo->query("SELECT DISTINCT province FROM agents ORDER BY province")->fetchAll(PDO::FETCH_COLUMN);
$districts = [];
if($prov_filter) {
    $d_stmt = $pdo->prepare("SELECT DISTINCT district FROM agents WHERE province = ? ORDER BY district");
    $d_stmt->execute([$prov_filter]);
    $districts = $d_stmt->fetchAll(PDO::FETCH_COLUMN);
}

$all_dealers = $pdo->query("SELECT dealer_code, name FROM dealers ORDER BY dealer_code ASC")->fetchAll(PDO::FETCH_ASSOC);

$export_url = "ajax/export_agents.php?" . http_build_query($_GET);

$msg = $_GET['msg'] ?? '';
$err = $_GET['err'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>View Agents - NLB</title>
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
        .agent-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(230px, 1fr)); gap: 1.5rem; }
        .agent-card { background: var(--card-bg); border: 1px solid var(--glass-border); border-radius: 20px; padding: 1.5rem; transition: 0.3s; }
        .agent-card:hover { transform: translateY(-4px); box-shadow: 0 12px 35px rgba(0,0,0,0.3); }
        .agent-card.hidden { display: none; }
        .agent-photo { width: 100%; height: 200px; object-fit: cover; border-radius: 12px; margin-bottom: 1rem; border: 2px solid var(--glass-border); }
        .dealer-info h3 { margin: 0 0 0.5rem 0; color: var(--secondary-color); }
        .dealer-info p  { margin: 0.25rem 0; font-size: 0.9rem; color: var(--text-muted); }
        .empty-state { text-align: center; padding: 4rem 2rem; color: var(--text-muted); grid-column: 1/-1; }
        .empty-state .icon { font-size: 3rem; margin-bottom: 1rem; opacity: 0.4; }
        .code-badge {
            display: inline-block;
            background: rgba(74, 222, 128, 0.1);
            border: 1px solid #4ade80;
            color: #4ade80;
            border-radius: 8px;
            padding: 2px 10px;
            font-size: 0.8rem;
            font-weight: 600;
            letter-spacing: 0.5px;
            margin-bottom: 0.5rem;
        }
        .dealer-badge {
            display: inline-block;
            background: rgba(251,191,36,0.1);
            border: 1px solid #fbbf24;
            color: #fbbf24;
            border-radius: 8px;
            padding: 2px 8px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-left: 6px;
        }
    </style>
</head>
<body>
    <div class="container wide">
        <div class="nav-bar" style="margin-bottom: 2rem;">
            <div class="nav-brand">
                <img src="assets/img/Logo.png" alt="NLB Logo">
                <div>
                    <h1>Agent Directory</h1>
                    <p style="color: var(--text-muted); font-size: 0.75rem; margin: 0; opacity: 0.8;">
                        Registered Agents &nbsp;·&nbsp; Logged in as <span class="role-badge badge-<?php echo $_SESSION['role']; ?>" style="padding: 2px 8px; font-size: 0.65rem;"><?php echo e($_SESSION['username']); ?></span>
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

        <!-- Toolbar: Search + Filters -->
        <div class="toolbar" style="display: block;">
            <div style="display: flex; gap: 1rem; align-items: center; flex-wrap: wrap; margin-bottom: 1rem;">
                <div class="toolbar-left" style="flex: 2; min-width: 300px;">
                    <div class="search-wrap">
                        <span class="search-icon">🔍</span>
                        <input type="text" class="search-input" id="searchInput" placeholder="Quick search in results...">
                    </div>
                </div>
                <div style="flex: 1; display: flex; gap: 0.5rem;">
                    <button class="sort-btn active" id="sortAsc" onclick="setSort('asc')">🔼 A→Z</button>
                    <button class="sort-btn" id="sortDesc" onclick="setSort('desc')">🔽 Z→A</button>
                </div>
                <a href="<?php echo $export_url; ?>" class="sort-btn" style="background: #10b981; border-color: #10b981; color: #fff; text-decoration: none;">📊 Export Report</a>
            </div>
            
            <form method="GET" style="display: flex; gap: 1rem; align-items: center; border-top: 1px solid var(--glass-border); padding-top: 1rem; flex-wrap: wrap;">
                <select name="dealer" class="filter-select" onchange="this.form.submit()" style="padding: 0.65rem; background: var(--input-bg); border: 2px solid var(--glass-border); border-radius: 12px; color: var(--text-main); font-family: 'Outfit', sans-serif; flex: 1; min-width: 150px;">
                    <option value="">-- All Dealers --</option>
                    <?php foreach($all_dealers as $dlr): ?>
                        <option value="<?php echo e($dlr['dealer_code']); ?>" <?php echo $dealer_filter === $dlr['dealer_code'] ? 'selected' : ''; ?>>
                            <?php echo e($dlr['name']); ?> (<?php echo e($dlr['dealer_code']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <select name="province" class="filter-select" onchange="this.form.submit()" style="padding: 0.65rem; background: var(--input-bg); border: 2px solid var(--glass-border); border-radius: 12px; color: var(--text-main); font-family: 'Outfit', sans-serif; flex: 1; min-width: 150px;">
                    <option value="">-- All Provinces --</option>
                    <?php foreach($provinces as $p): ?>
                        <option value="<?php echo e($p); ?>" <?php echo $prov_filter === $p ? 'selected' : ''; ?>><?php echo e($p); ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="district" class="filter-select" onchange="this.form.submit()" style="padding: 0.65rem; background: var(--input-bg); border: 2px solid var(--glass-border); border-radius: 12px; color: var(--text-main); font-family: 'Outfit', sans-serif; flex: 1; min-width: 150px;">
                    <option value="">-- All Districts --</option>
                    <?php foreach($districts as $d): ?>
                        <option value="<?php echo e($d); ?>" <?php echo $dist_filter === $d ? 'selected' : ''; ?>><?php echo e($d); ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if($prov_filter || $dist_filter || $dealer_filter): ?>
                    <a href="view_agents.php" class="btn-delete" style="text-decoration: none; padding: 0.65rem 1.1rem; border-radius: 12px; font-size: 0.85rem; white-space: nowrap;">Reset Filters</a>
                <?php endif; ?>
                <span class="result-count" id="resultCount" style="white-space: nowrap;"><?php echo count($agents); ?> agents matched</span>
            </form>
        </div>

        <div class="agent-grid" id="agentGrid">
            <?php foreach ($agents as $a):
                $addresses = $pdo->prepare("SELECT address_text FROM agent_addresses WHERE agent_id = ?");
                $addresses->execute([$a['id']]);
                $locs = $pdo->prepare("SELECT location_link FROM agent_locations WHERE agent_id = ?");
                $locs->execute([$a['id']]);
                $addrList = $addresses->fetchAll();
                $locList  = $locs->fetchAll();
            ?>
            <div class="agent-card"
                 data-name="<?php echo strtolower(e($a['name'])); ?>"
                 data-code="<?php echo strtolower(e($a['agent_code'])); ?>"
                 data-dealer="<?php echo strtolower(e($a['dealer_code'])); ?>"
                 data-dealername="<?php echo strtolower(e($a['dealer_name'] ?? '')); ?>"
                 data-nic="<?php echo strtolower(e($a['nic_new'] ?: $a['nic_old'])); ?>"
                 data-province="<?php echo strtolower(e($a['province'])); ?>"
                 data-district="<?php echo strtolower(e($a['district'])); ?>">

                <?php if($a['photo']): ?>
                    <img src="<?php echo e($a['photo']); ?>" class="agent-photo" alt="Agent Photo">
                <?php endif; ?>

                <div class="dealer-info">
                    <div>
                        <span class="code-badge"><?php echo e($a['agent_code']); ?></span>
                        <span class="dealer-badge"><?php echo e($a['dealer_code']); ?></span>
                    </div>
                    <h3><?php echo e($a['name']); ?></h3>
                    <p><strong>Dealer:</strong> <?php echo e($a['dealer_name'] ?? $a['dealer_code']); ?></p>
                    <p><strong>NIC:</strong> <?php echo e($a['nic_new'] ?: $a['nic_old']); ?></p>
                    <?php if($a['phone']): ?><p><strong>Phone:</strong> <?php echo e($a['phone']); ?></p><?php endif; ?>
                    <p><strong>Province:</strong> <?php echo e($a['province']); ?> (<?php echo e($a['district']); ?>)</p>

                    <?php if ($addrList): ?>
                    <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--glass-border);">
                        <p style="font-weight:600; font-size:0.9rem;">📍 Addresses:</p>
                        <?php foreach($addrList as $addr): ?>
                            <p style="color:var(--text-muted); font-size:0.85rem; margin:0.2rem 0;">• <?php echo e($addr['address_text']); ?></p>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <?php if ($locList): ?>
                    <div style="margin-top:0.8rem;">
                        <p style="font-weight:600; font-size:0.9rem;">🗺️ Locations:</p>
                        <?php foreach($locList as $l): ?>
                            <a href="<?php echo e($l['location_link']); ?>" target="_blank" style="display:block; font-size:0.8rem; color:var(--secondary-color); text-decoration:none; margin:0.2rem 0;">🔗 View on Google Maps</a>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <div style="margin-top:1.5rem; display:flex; gap:10px; border-top:1px solid rgba(255,255,255,0.05); padding-top:1rem;">
                        <a href="edit_agent.php?id=<?php echo $a['id']; ?>" style="flex:1; text-align:center; text-decoration:none; background:rgba(59,130,246,0.1); color:#60a5fa; border:1px solid rgba(59,130,246,0.2); padding:8px; border-radius:10px; font-size:0.85rem; font-weight:500;">✏️ Edit</a>
                        <form method="POST" action="ajax/delete_entity.php" style="flex:1;" onsubmit="return confirm('Delete this agent permanently?')">
                            <?php csrf_input(); ?>
                            <input type="hidden" name="id" value="<?php echo $a['id']; ?>">
                            <input type="hidden" name="type" value="agent">
                            <button type="submit" style="width:100%; cursor:pointer; background:rgba(239,68,68,0.1); color:#f87171; border:1px solid rgba(239,68,68,0.2); padding:8px; border-radius:10px; font-size:0.85rem; font-weight:500;">🗑️ Delete</button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>

            <div id="emptyState" class="empty-state" style="display:none;">
                <div class="icon">🔍</div>
                <p>No agents found matching your search.</p>
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
            const grid  = document.getElementById('agentGrid');
            const cards = Array.from(grid.querySelectorAll('.agent-card'));

            let visible = [];
            cards.forEach(card => {
                const searchable = [
                    card.dataset.name,
                    card.dataset.code,
                    card.dataset.dealer,
                    card.dataset.dealername,
                    card.dataset.nic,
                    card.dataset.province,
                    card.dataset.district
                ].join(' ');

                const match = !query || searchable.includes(query);
                card.classList.toggle('hidden', !match);
                if (match) visible.push(card);
            });

            // Sort by agent_code
            visible.sort((a, b) => {
                const ca = a.dataset.code;
                const cb = b.dataset.code;
                return currentSort === 'asc'
                    ? ca.localeCompare(cb, undefined, {numeric: true, sensitivity: 'base'})
                    : cb.localeCompare(ca, undefined, {numeric: true, sensitivity: 'base'});
            });

            const emptyState = document.getElementById('emptyState');
            visible.forEach(card => grid.insertBefore(card, emptyState));

            document.getElementById('resultCount').textContent = visible.length + ' agent' + (visible.length !== 1 ? 's' : '');
            emptyState.style.display = visible.length === 0 ? 'block' : 'none';
        }

        document.getElementById('searchInput').addEventListener('input', applyFilterAndSort);
    </script>
    <?php include 'includes/footer.php'; ?>
</body>
</html>
