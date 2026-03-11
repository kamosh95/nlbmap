<?php
require_once 'includes/security.php';
require_once 'includes/db_config.php';
require_once 'includes/get_nav.php';

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'mkt', 'tm'])) {
    header("Location: dashboard.php");
    exit;
}

$district_filter = $_GET['district'] ?? '';

// 1. Get List of Districts for dropdown
$districts = $pdo->query("SELECT DISTINCT district FROM counters WHERE district IS NOT NULL AND district != '' ORDER BY district")->fetchAll(PDO::FETCH_COLUMN);

// 2. Main Stats for chosen district
$stats = null;
if ($district_filter) {
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_entries,
            MIN(created_at) as first_entry,
            MAX(created_at) as last_entry,
            DATEDIFF(MAX(created_at), MIN(created_at)) + 1 as days_taken,
            COUNT(DISTINCT added_by) as active_users
        FROM counters 
        WHERE district = ?
    ");
    $stmt->execute([$district_filter]);
    $stats = $stmt->fetch();
}

// 3. Daily Entry Trends for Chart
$chart_data = [];
if ($district_filter) {
    $stmt = $pdo->prepare("
        SELECT DATE(created_at) as date, COUNT(*) as count 
        FROM counters 
        WHERE district = ? 
        GROUP BY DATE(created_at) 
        ORDER BY date ASC
    ");
    $stmt->execute([$district_filter]);
    $chart_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// 4. Comparison Table: Speed by District
$comparison = $pdo->query("
    SELECT 
        district, 
        COUNT(*) as total, 
        DATEDIFF(MAX(created_at), MIN(created_at)) + 1 as duration_days,
        ROUND(COUNT(*) / (DATEDIFF(MAX(created_at), MIN(created_at)) + 1), 2) as speed_per_day
    FROM counters 
    WHERE district IS NOT NULL AND district != ''
    GROUP BY district 
    ORDER BY total DESC
")->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics - NLB Seller Map</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .analytics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .stat-card {
            background: var(--card-bg);
            border: 1px solid var(--glass-border);
            padding: 25px;
            border-radius: 20px;
            text-align: center;
        }
        .stat-card h4 { color: var(--text-muted); font-size: 0.85rem; text-transform: uppercase; margin-bottom: 15px; }
        .stat-card .val { font-size: 2rem; font-weight: 800; color: var(--secondary-color); }
        .stat-card .sub { font-size: 0.8rem; color: var(--text-muted); margin-top: 10px; }
        
        .chart-container {
            background: var(--card-bg);
            border: 1px solid var(--glass-border);
            padding: 25px;
            border-radius: 20px;
            margin-top: 25px;
        }
        
        .table-card {
            background: var(--card-bg);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            margin-top: 25px;
            overflow: hidden;
        }
        .table-card table { width: 100%; border-collapse: collapse; }
        .table-card th { background: rgba(0, 114, 255, 0.1); color: var(--secondary-color); text-align: left; padding: 15px; font-size: 0.9rem; }
        .table-card td { padding: 15px; border-bottom: 1px solid var(--glass-border); color: var(--text-main); font-size: 0.9rem; }
        .table-card tr:last-child td { border-bottom: none; }
    </style>
</head>
<body class="dark-mode">

    <div class="container wide">
        <div class="nav-bar" style="margin-bottom: 2rem;">
            <div class="nav-brand">
                <img src="assets/img/Logo.png" alt="Logo">
                <div>
                    <h1>Data Analysis & Insights</h1>
                    <p>Track Registration Performance by District</p>
                </div>
            </div>
            <?php echo render_nav($pdo, $_SESSION['role']); ?>
        </div>

        <div style="background: var(--card-bg); padding: 20px; border-radius: 15px; border: 1px solid var(--glass-border); display: flex; align-items: center; gap: 15px;">
            <span style="font-weight: 600; color: var(--text-muted);">Select District:</span>
            <form action="" method="GET" style="display: flex; gap: 10px; margin: 0; flex: 1;">
                <select name="district" onchange="this.form.submit()" style="flex: 1; padding: 10px; border-radius: 10px; background: var(--nav-bg); color: #fff; border: 1px solid var(--glass-border);">
                    <option value="">-- Choose a District --</option>
                    <?php foreach($districts as $d): ?>
                        <option value="<?php echo htmlspecialchars($d); ?>" <?php echo $district_filter === $d ? 'selected' : ''; ?>><?php echo htmlspecialchars($d); ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn-submit" style="width: auto; padding: 10px 25px;">Analyze</button>
            </form>
        </div>

        <?php if ($stats && $stats['total_entries'] > 0): ?>
            <div class="analytics-grid">
                <div class="stat-card">
                    <h4>Total Registered</h4>
                    <div class="val"><?php echo number_format($stats['total_entries']); ?></div>
                    <div class="sub">Counter / Mobile Sellers</div>
                </div>
                <div class="stat-card">
                    <h4>Collection Duration</h4>
                    <div class="val"><?php echo $stats['days_taken']; ?> <span style="font-size: 1rem;">Days</span></div>
                    <div class="sub">From <?php echo date("M d", strtotime($stats['first_entry'])); ?> to <?php echo date("M d", strtotime($stats['last_entry'])); ?></div>
                </div>
                <div class="stat-card">
                    <h4>Entry Speed</h4>
                    <div class="val"><?php echo round($stats['total_entries'] / max(1, $stats['days_taken']), 1); ?></div>
                    <div class="sub">Average Entries Per Day</div>
                </div>
                <div class="stat-card">
                    <h4>Field Activity</h4>
                    <div class="val"><?php echo $stats['active_users']; ?></div>
                    <div class="sub">Unique Users Contributing</div>
                </div>
            </div>

            <div class="chart-container">
                <h3 style="margin-bottom: 20px; font-size: 1.1rem; color: #fff;">📈 Data Entry Trend for <?php echo htmlspecialchars($district_filter); ?></h3>
                <canvas id="districtChart" style="max-height: 400px;"></canvas>
            </div>
        <?php elseif ($district_filter): ?>
            <div class="message info" style="margin-top: 20px;">No registration data found for the selected district yet.</div>
        <?php endif; ?>

        <div class="table-card">
            <div style="padding: 20px; border-bottom: 1px solid var(--glass-border);">
                <h3 style="margin:0; font-size: 1.1rem; color: #fff;">📊 Performance Comparison (All Districts)</h3>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>District</th>
                        <th>Total Entries</th>
                        <th>Timeframe (Days)</th>
                        <th>Avg. Speed (Entries/Day)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($comparison as $c): ?>
                    <tr style="<?php echo $c['district'] === $district_filter ? 'background: rgba(0, 212, 255, 0.05); border-left: 4px solid #00d4ff;' : ''; ?>">
                        <td><b><?php echo htmlspecialchars($c['district']); ?></b></td>
                        <td><?php echo number_format($c['total']); ?></td>
                        <td><?php echo $c['duration_days']; ?></td>
                        <td>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <?php echo $c['speed_per_day']; ?>
                                <div style="width: 100px; height: 6px; background: rgba(255,255,255,0.05); border-radius: 10px; overflow: hidden;">
                                    <div style="height: 100%; width: <?php echo min(100, $c['speed_per_day'] * 5); ?>%; background: var(--gold-gradient);"></div>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    </div>

    <?php if (!empty($chart_data)): ?>
    <script>
        const ctx = document.getElementById('districtChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($chart_data, 'date')); ?>,
                datasets: [{
                    label: 'Registrations per Day',
                    data: <?php echo json_encode(array_column($chart_data, 'count')); ?>,
                    borderColor: '#00d4ff',
                    backgroundColor: 'rgba(0, 212, 255, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 4,
                    pointBackgroundColor: '#00d4ff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: 'rgba(255,255,255,0.05)' },
                        ticks: { color: '#94a3b8' }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { color: '#94a3b8' }
                    }
                }
            }
        });
    </script>
    <?php endif; ?>

</body>
</html>
