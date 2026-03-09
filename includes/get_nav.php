<?php
// Function to generate navigation links based on user role
function get_navigation_links($pdo, $user_role) {
    $sql = "SELECT * FROM navigation WHERE role_access = 'all' ";
    
    if ($user_role === 'admin') {
        $sql .= "OR role_access IN ('admin', 'moderator', 'user', 'tm')";
    } elseif ($user_role === 'moderator') {
        $sql .= "OR role_access IN ('moderator', 'user')";
    } elseif ($user_role === 'mkt') {
        $sql .= "OR role_access IN ('moderator')";
    } elseif ($user_role === 'tm') {
        $sql .= "OR role_access = 'tm'";
    } elseif ($user_role === 'user') {
        $sql .= "OR role_access = 'user'";
    }

    $sql .= " ORDER BY sort_order ASC";
    return $pdo->query($sql)->fetchAll();
}

// Function to render the navigation links HTML
function render_nav($pdo, $current_role) {
    if (!isset($pdo)) {
        require_once __DIR__ . '/db_config.php';
    }
    
    $links = get_navigation_links($pdo, $current_role);
    
    $groups = [];
    $main_html = '';
    $admin_html = '';

    // Step 1: Organize links into groups or main
    foreach ($links as $link) {
        $url = $link['url'];
        $label = $link['label'];
        $group_name = $link['nav_group'] ?? 'Main';

        // Special handling for Scan QR if it's in the Main group
        if ($url === 'scanner.php' && ($group_name === 'Main' || empty($group_name))) {
            $main_html .= '<a href="scanner.php" class="nav-scan-btn" style="background: rgba(255, 204, 0, 0.15); border: 1px solid var(--secondary-color); border-radius: 10px; color: var(--secondary-color); font-weight: 700; display: flex; align-items: center; gap: 8px;">📷 Scan QR</a>';
            continue;
        }

        // Auto-group admin links if they are tagged as admin access and in Main
        if ($current_role === 'admin' && $link['role_access'] === 'admin' && $group_name === 'Main') {
            $admin_html .= '<a href="' . htmlspecialchars($url) . '">' . htmlspecialchars($label) . '</a>';
            continue;
        }

        if ($group_name !== 'Main' && !empty($group_name)) {
            // Grouped Link
            if (!isset($groups[$group_name])) {
                $groups[$group_name] = '';
            }
            $groups[$group_name] .= '<a href="' . htmlspecialchars($url) . '"><span>' . htmlspecialchars($label) . '</span></a>';
        } else {
            // Standalone Link
            $main_html .= '<a href="' . htmlspecialchars($url) . '">' . htmlspecialchars($label) . '</a>';
        }
    }

    // Step 2: Build Dropdowns for Groups
    $grouped_dropdowns_html = '';
    foreach ($groups as $group_label => $items_html) {
        if (!empty($items_html)) {
            $dropdown  = '<div class="dropdown">';
            $dropdown .= '<button class="dropdown-btn" onclick="toggleGenericDropdown(this, event)">' . htmlspecialchars($group_label) . '</button>';
            $dropdown .= '<div class="dropdown-content">' . $items_html . '</div>';
            $dropdown .= '</div>';
            $grouped_dropdowns_html .= $dropdown;
        }
    }
    
    // Final assembly: Groups first, then standalone main links, then admin
    $nav_content = $grouped_dropdowns_html . $main_html;

    // If admin links exist and role is admin, wrap in dropdown
    if (!empty($admin_html) && $current_role === 'admin') {
        $dropdown  = '<div class="dropdown" id="adminDropdown">';
        $dropdown .= '<button class="dropdown-btn" id="adminDropBtn" onclick="toggleGenericDropdown(this, event)">Admin Panel ⚙️</button>';
        $dropdown .= '<div class="dropdown-content" id="adminDropContent">' . $admin_html . '</div>';
        $dropdown .= '</div>';
        $nav_content .= $dropdown;
    }
    
    // Hamburger toggle button (visible only on mobile via CSS)
    $toggle = '
        <button class="nav-toggle" id="navToggle" onclick="this.classList.toggle(\'open\'); document.getElementById(\'navLinks\').classList.toggle(\'open\');" aria-label="Menu">
            <span></span><span></span><span></span>
        </button>';

    // Theme toggle button
    $theme_toggle = '<button class="theme-toggle" id="themeToggle" title="Toggle Theme">🌓</button>';
    
    // Return toggle + nav links wrapped with id for JS targeting
    $nav_html = $toggle . '<div class="nav-links" id="navLinks">' . $nav_content . $theme_toggle . '<a href="logout.php" class="logout-btn">Logout</a></div>';
    
    // Add theme management + dropdown click script
    $nav_html .= '
    <script>
        (function() {
            const themeToggle = document.getElementById("themeToggle");
            const body = document.body;
            
            // Check for saved theme preference
            const savedTheme = localStorage.getItem("theme");
            if (savedTheme === "dark" || !savedTheme) {
                body.classList.add("dark-mode");
                themeToggle.innerHTML = "🌙";
            } else {
                themeToggle.innerHTML = "☀️";
            }

            themeToggle.addEventListener("click", () => {
                body.classList.toggle("dark-mode");
                const isDark = body.classList.contains("dark-mode");
                localStorage.setItem("theme", isDark ? "dark" : "light");
                themeToggle.innerHTML = isDark ? "🌙" : "☀️";
                
                // Add a nice animation effect
                themeToggle.style.transform = "scale(1.2) rotate(360deg)";
                setTimeout(() => {
                    themeToggle.style.transform = "";
                }, 300);
            });
        })();

        // Generic dropdown toggle
        function toggleGenericDropdown(btn, e) {
            e.stopPropagation();
            const content = btn.nextElementSibling;
            if (!content) return;
            
            // Close other dropdowns
            document.querySelectorAll(".dropdown-content").forEach(d => {
                if (d !== content) d.classList.remove("open");
            });
            document.querySelectorAll(".dropdown-btn").forEach(b => {
                if (b !== btn) b.classList.remove("active");
            });

            const isOpen = content.classList.contains("open");
            content.classList.toggle("open", !isOpen);
            btn.classList.toggle("active", !isOpen);
        }

        // Close dropdown when clicking anywhere outside it
        document.addEventListener("click", function(e) {
            if (!e.target.closest(".dropdown")) {
                document.querySelectorAll(".dropdown-content").forEach(d => d.classList.remove("open"));
                document.querySelectorAll(".dropdown-btn").forEach(b => b.classList.remove("active"));
            }
        });
    </script>';
    
    return $nav_html;
}
?>
