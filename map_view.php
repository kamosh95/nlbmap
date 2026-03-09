<?php
require_once 'includes/security.php';
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'moderator', 'tm', 'mkt', 'user'])) {
    header("Location: login.php");
    exit;
}

require_once 'includes/db_config.php';

// Helper to expand short URLs and get coordinates
// Helper to expand short URLs and get coordinates
function getCoords($url) {
    if (empty($url)) return [null, null];
    $lat = null; $lng = null;
    
    // 1. Resolve short URLs (maps.app.goo.gl / goo.gl)
    if (strpos($url, 'goo.gl') !== false || strpos($url, 'maps.app.goo.gl') !== false) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_exec($ch);
        $resolved = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        curl_close($ch);
        if ($resolved) $url = $resolved;
    }

    $url = urldecode($url);

    // 2. High Priority: Extract Pin Location ( !3d...!4d... pattern )
    // This is the most accurate as it points to the actual pin, not the map center.
    if (preg_match('/!3d([0-9.-]+)!4d([0-9.-]+)/', $url, $m)) {
        $lat = (float)$m[1];
        $lng = (float)$m[2];
    } 
    // 3. Standard patterns: @lat,lng or q=lat,lng or ll=lat,lng or search/lat,lng
    else if (preg_match('/(?:@|q=|query=|place\/|dir\/|search\/|ll=)([0-9.-]+),\s*([0-9.-]+)/', $url, $m)) {
        $lat = (float)$m[1];
        $lng = (float)$m[2];
    }
    // 4. Fallback: Any comma-separated pair (for raw input like "6.9, 79.8")
    else if (preg_match('/([0-9.-]+),\s*([0-9.-]+)/', $url, $m)) {
        $lat = (float)$m[1];
        $lng = (float)$m[2];
    }

    // 5. Sri Lanka Logic: If coords are likely swapped (SL is Lat: 5-10, Lon: 79-82)
    if ($lat !== null && $lng !== null) {
        // If Lat looks like Longitude and Longitude looks like Latitude, SWAP THEM
        if (($lat > 70 && $lat < 83) && ($lng > 5 && $lng < 11)) {
            $tmp = $lat;
            $lat = $lng;
            $lng = $tmp;
        }
        
        // Ignore obviously wrong coordinates (outside Sri Lanka region basically)
        // This prevents capturing random numbers from a complex URL
        if ($lat < 5 || $lat > 11 || $lng < 79 || $lng > 83) {
            // Keep it but maybe it's not SL? In this portal, it SHOULD be SL.
            // If it's totally crazy (like 123.456), we discard.
            if ($lat > 90 || $lat < -90 || $lng > 180 || $lng < -180) {
                return [null, null];
            }
        }
    }

    return [$lat, $lng];
}

$locations = [];

// 1. Fetch Sellers (Counters)
$stmt = $pdo->query("SELECT id, seller_name, seller_code, location_link, dealer_code, agent_code, image_front, sales_method FROM counters WHERE (status = 'Active' OR status IS NULL) AND location_link IS NOT NULL AND location_link != ''");
foreach ($stmt->fetchAll() as $row) {
    list($lat, $lng) = getCoords($row['location_link']);
    if ($lat !== null) {
        $locations[] = [
            'type' => 'seller',
            'id' => $row['id'],
            'name' => $row['seller_name'],
            'code' => $row['seller_code'],
            'sub_info' => "Agent: " . ($row['agent_code'] ?: 'N/A') . " | Dealer: " . $row['dealer_code'],
            'lat' => $lat, 'lng' => $lng,
            'photo' => $row['image_front'],
            'sales_method' => $row['sales_method']
        ];
    }
}

// 2. Fetch Dealers
$stmt = $pdo->query("SELECT d.id, d.name, d.dealer_code, d.photo, dl.location_link 
                     FROM dealers d JOIN dealer_locations dl ON d.id = dl.dealer_id");
foreach ($stmt->fetchAll() as $row) {
    list($lat, $lng) = getCoords($row['location_link']);
    if ($lat !== null) {
        $locations[] = [
            'type' => 'dealer',
            'id' => $row['id'],
            'name' => $row['name'],
            'code' => $row['dealer_code'],
            'sub_info' => 'Dealer Location',
            'lat' => $lat, 'lng' => $lng,
            'photo' => $row['photo']
        ];
    }
}

// 3. Fetch Agents
$stmt = $pdo->query("SELECT a.id, a.name, a.agent_code, a.dealer_code, a.phone, a.photo, al.location_link 
                     FROM agents a JOIN agent_locations al ON a.id = al.agent_id");
foreach ($stmt->fetchAll() as $row) {
    list($lat, $lng) = getCoords($row['location_link']);
    if ($lat !== null) {
        $locations[] = [
            'type' => 'agent',
            'id' => $row['id'],
            'name' => $row['name'],
            'code' => $row['agent_code'],
            'sub_info' => "Dealer: " . $row['dealer_code'] . " | Ph: " . $row['phone'],
            'lat' => $lat, 'lng' => $lng,
            'photo' => $row['photo']
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Location Map - NLB</title>
    <link rel="manifest" href="assets/manifest.json">
    <meta name="theme-color" content="#0072ff">
    <script>
        (function() {
            const savedTheme = localStorage.getItem("theme");
            if (savedTheme === "dark" || !savedTheme) {
                document.documentElement.classList.add("dark-mode");
                document.body.classList.add("dark-mode");
            }
        })();

        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('sw.js');
            });
        }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
    <style>
        body, html {
            margin: 0;
            padding: 0;
            height: 100vh;
            width: 100vw;
            overflow: hidden;
            background: #aad3df; /* Exact Voyager Sea Blue */
            display: flex;
            flex-direction: column;
        }
        body.dark-mode, html.dark-mode {
            background: #111111; /* Exact Dark Matter Background */
        }
        #map {
            flex: 1;
            width: 100%;
            z-index: 1;
            min-height: 0;
        }
        .leaflet-popup-content-wrapper {
            background: var(--dropdown-bg) !important;
            color: var(--text-main) !important;
            border: 1px solid var(--glass-border);
            backdrop-filter: blur(15px);
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            padding: 5px;
        }
        .leaflet-popup-tip {
            background: var(--dropdown-bg) !important;
        }
        .leaflet-popup-content {
            margin: 15px 20px;
            line-height: 1.4;
        }
        .floating-nav {
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 1000;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 10px;
            pointer-events: none;
        }
        .nav-row {
            display: flex;
            gap: 10px;
        }
        .floating-nav a, .floating-info, .search-box {
            pointer-events: auto;
            background: var(--nav-bg) !important;
            backdrop-filter: blur(20px) !important;
            -webkit-backdrop-filter: blur(20px) !important;
            padding: 10px 15px;
            border-radius: 12px;
            border: 1px solid var(--glass-border);
            color: var(--text-main);
            text-decoration: none;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }
        .search-box {
            display: flex;
            gap: 8px;
            padding: 6px;
            background: rgba(15, 23, 42, 0.85);
            border-radius: 12px;
            border: 1px solid var(--glass-border);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
        }
        .search-box select, .search-box input {
            background: var(--input-bg);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: var(--text-main);
            padding: 8px 10px;
            border-radius: 8px;
            font-size: 0.8rem;
            outline: none;
            transition: all 0.3s;
        }
        .search-box select {
            cursor: pointer;
            border-right: 1px solid rgba(255,255,255,0.1);
            font-weight: 500;
            color: var(--secondary-color);
        }
        .search-box input {
            width: 180px;
        }
        .search-box input:focus {
            background: rgba(255, 255, 255, 0.1);
            border-color: var(--secondary-color);
            width: 220px;
        }
        .floating-nav a:hover {
            border-color: var(--secondary-color);
            transform: translateY(-2px);
        }

        @media (max-width: 600px) {
            .floating-nav {
                flex-direction: column;
                align-items: stretch;
                width: calc(100% - 40px);
            }
            .search-box input {
                width: 100%;
            }
            .search-box input:focus {
                width: 100%;
            }
            .leaflet-bottom.leaflet-left {
                margin-bottom: 30px !important;
            }
        }
    </style>
</head>
<body>
    <div class="floating-nav">
        <div class="nav-row">
            <a href="dashboard.php" title="Back to Dashboard">
                <img src="assets/img/Logo.png" style="height: 20px;"> Back
            </a>
            
            <!-- Live Search / Filter -->
            <div class="search-box">
                <select id="searchType">
                    <option value="all">🔍 All Items</option>
                    <option value="dealer_only">🏢 Dealers</option>
                    <option value="agent_only">👤 Agents</option>
                    <option value="counter_only">🏪 Counter Sellers</option>
                    <option value="mobile_only">🚶 Mobile Sellers</option>
                    <option value="point_only">📍 Sales Points</option>
                </select>
                <input type="text" id="searchInput" placeholder="Find by Name or Code...">
            </div>
            <button class="theme-toggle" id="mapThemeToggle" style="pointer-events: auto; width: 38px; height: 38px; margin: 0;">🌓</button>
        </div>

        <div class="floating-info" style="color: var(--text-muted);">
            <span id="markerCount"><?php echo count($locations); ?></span> Results
        </div>
    </div>

    <div id="map"></div>

    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    
    <script>
        // Tight Sri Lanka Geographical Bounds
        var slBounds = L.latLngBounds(
            L.latLng(5.9, 79.5), // Southern tip
            L.latLng(9.85, 81.9)  // Northern tip
        );

        // Initialize map strictly isolated to Sri Lanka
        var map = L.map('map', {
            minZoom: 5,
            maxZoom: 18,
            zoomSnap: 0.1,
            zoomControl: false // Disable default top-left control
        });

        // Add Zoom Control to Bottom-Left
        L.control.zoom({
            position: 'bottomleft'
        }).addTo(map);

        // Initial fit
        map.fitBounds(slBounds, {
            padding: [20, 20],
            animate: false
        });

        // Tile Layers
        const darkTiles = 'https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png';
        const lightTiles = 'https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png';
        
        var isDark = document.body.classList.contains('dark-mode');
        
        var baseLayer = L.tileLayer(isDark ? darkTiles : lightTiles, {
            attribution: '&copy; NLB Seller Map Portal',
            subdomains: 'abcd'
        }).addTo(map);

        // Mask Layer
        var maskLayer = L.polygon([
            [[90, -180], [90, 180], [-90, 180], [-90, -180]],
            [[5.8, 79.5], [5.8, 82.0], [9.9, 82.0], [9.9, 79.5]]
        ], {
            color: isDark ? '#111111' : '#aad3df',
            fillColor: isDark ? '#111111' : '#aad3df',
            fillOpacity: 1.0,
            weight: 0,
            interactive: false
        }).addTo(map);

        // Theme Toggle for Map
        const themeToggle = document.getElementById("mapThemeToggle");
        themeToggle.innerHTML = isDark ? "🌙" : "☀️";
        
        themeToggle.addEventListener("click", () => {
            document.body.classList.toggle("dark-mode");
            isDark = document.body.classList.contains("dark-mode");
            localStorage.setItem("theme", isDark ? "dark" : "light");
            themeToggle.innerHTML = isDark ? "🌙" : "☀️";
            
            // Update Map
            baseLayer.setUrl(isDark ? darkTiles : lightTiles);
            maskLayer.setStyle({
                color: isDark ? '#111111' : '#aad3df',
                fillColor: isDark ? '#111111' : '#aad3df'
            });
            
            themeToggle.style.transform = "scale(1.2) rotate(360deg)";
            setTimeout(() => themeToggle.style.transform = "", 300);
        });

        // Initial Render
        var locations = <?php echo json_encode($locations); ?>;
        var markerLayer = L.layerGroup().addTo(map);
        
        function renderMarkers(data) {
            markerLayer.clearLayers();
            
            data.forEach(function(loc) {
                // Determine color based on type
                let color = "#0072ff"; // Default Seller (Blue)
                let iconChar = "🏪";
                if(loc.type === 'dealer') { color = "#ffcc00"; iconChar = "🏢"; }
                if(loc.type === 'agent') { color = "#4ade80"; iconChar = "👤"; }
                
                if(loc.type === 'seller') {
                    if(loc.sales_method === 'Mobile Sales') { color = "#a855f7"; iconChar = "🚶"; }
                    else if(loc.sales_method === 'Sales Point') { color = "#f97316"; iconChar = "📍"; }
                    else { color = "#0ea5e9"; iconChar = "🏪"; } // Counter Seller
                }

                const customIcon = L.divIcon({
                    className: 'custom-div-icon',
                    html: `<div style="background-color:${color}; width:30px; height:30px; border-radius:50%; border:2px solid white; display:flex; align-items:center; justify-content:center; font-size:16px; box-shadow:0 0 10px rgba(0,0,0,0.5);">${iconChar}</div>`,
                    iconSize: [30, 30],
                    iconAnchor: [15, 15]
                });

                var marker = L.marker([loc.lat, loc.lng], {icon: customIcon});
                
                var popupContent = `
                    <div style="min-width: 220px; font-family: 'Outfit', sans-serif; color: var(--text-main);">
                        <div style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1.2px; color: ${color}; font-weight: 800; margin-bottom: 4px; display: flex; align-items: center; gap: 5px;">
                            <span style="width: 8px; height: 8px; border-radius: 50%; background: ${color}; display: inline-block;"></span>
                            ${loc.type}
                        </div>
                        <div style="color: var(--text-main); font-weight: 700; font-size: 1.25rem; margin-bottom: 8px; line-height: 1.2;">${loc.name}</div>
                        
                        <div style="margin-bottom: 12px; display: flex; flex-direction: column; gap: 4px;">
                            <div style="font-size: 0.85rem; color: var(--text-muted);">
                                ID Code: <b style="color: var(--text-main);">${loc.code}</b>
                            </div>
                            <div style="font-size: 0.85rem; padding: 10px; background: var(--input-bg); border: 1px solid var(--glass-border); border-radius: 10px; color: var(--text-main); margin-top: 5px;">
                                ${loc.sub_info}
                            </div>
                        </div>

                        ${loc.photo ? `
                            <div style="position: relative; margin-top: 10px;">
                                <img src="${loc.photo}" onclick="openLightbox(this.src)" 
                                     style="width: 100%; height: 120px; object-fit: cover; border-radius: 10px; cursor: pointer; border: 1px solid var(--glass-border); box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
                                <div style="position: absolute; bottom: 8px; right: 8px; background: rgba(0,0,0,0.6); color: white; padding: 2px 6px; border-radius: 4px; font-size: 0.6rem; backdrop-filter: blur(4px);">🔍 Click to zoom</div>
                            </div>
                        ` : ''}

                        <hr style="border: 0; border-top: 1px solid var(--glass-border); margin: 12px 0;">
                        ${loc.type === 'seller' ? `
                            <a href="dashboard.php?search=${loc.code}" style="display: block; text-align: center; background: var(--accent-gradient); color: white; text-decoration: none; font-size: 0.85rem; font-weight: 600; padding: 8px; border-radius: 8px; margin-top: 5px;">View Full Details</a>
                        ` : ''}
                    </div>
                `;
                
                marker.bindPopup(popupContent).addTo(markerLayer);
            });

            document.getElementById('markerCount').innerText = data.length;
        }

        function applyFilter() {
            const type = document.getElementById('searchType').value;
            const query = document.getElementById('searchInput').value.toLowerCase().trim();

            const filtered = locations.filter(loc => {
                const matchesQuery = loc.name.toLowerCase().includes(query) || loc.code.toLowerCase().includes(query);
                
                if (type === "all") return matchesQuery;
                if (type === "dealer_only") return loc.type === 'dealer' && matchesQuery;
                if (type === "agent_only") return loc.type === 'agent' && matchesQuery;
                if (type === "counter_only") return (loc.type === 'seller' && (loc.sales_method === 'Ticket Counter' || !loc.sales_method || loc.sales_method === 'Sales Booth')) && matchesQuery;
                if (type === "mobile_only") return (loc.type === 'seller' && loc.sales_method === 'Mobile Sales') && matchesQuery;
                if (type === "point_only") return (loc.type === 'seller' && loc.sales_method === 'Sales Point') && matchesQuery;
                return false;
            });

            renderMarkers(filtered);
            if (filtered.length > 0) {
                var group = new L.featureGroup(markerLayer.getLayers());
                map.fitBounds(group.getBounds().pad(0.1));
            }
        }

        document.getElementById('searchInput').addEventListener('input', applyFilter);
        document.getElementById('searchType').addEventListener('change', applyFilter);

        // Initial Render
        renderMarkers(locations);

        // Force map resize after loading to fix grey areas/partial loading
        setTimeout(() => {
            map.invalidateSize();
        }, 500);

        window.addEventListener('resize', () => {
            map.invalidateSize();
        });

        // Lightbox functionality
        function openLightbox(src) {
            const overlay = document.createElement('div');
            overlay.className = 'modal-overlay';
            overlay.style.display = 'flex';
            overlay.innerHTML = `
                <span class="modal-close">&times;</span>
                <img src="${src}" class="modal-content">
            `;
            document.body.appendChild(overlay);
            
            overlay.onclick = function(e) {
                if (e.target !== document.querySelector('.modal-content')) {
                    document.body.removeChild(overlay);
                }
            };
        }
    </script>
    <?php include 'includes/footer.php'; ?>
</body>
</html>
