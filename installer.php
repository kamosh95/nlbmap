<?php
require_once 'includes/security.php';

// Step handling
$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
$message = '';
$status = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF
    verify_csrf_token($_POST['csrf_token'] ?? '');
    
    if ($step === 1) {
        // Step 1: Database Connection & Table Creation
        $db_host = $_POST['db_host'];
        $db_name = $_POST['db_name'];
        $db_user = $_POST['db_user'];
        $db_pass = $_POST['db_pass'];

        try {
            // Force connection test
            $pdo = new PDO("mysql:host=$db_host", $db_user, $db_pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Create Database
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `$db_name`");
            
            // Generate db_config.php (Optimized - No migrations in config)
            $config_content = "<?php\n" .
                             "// Database Configuration\n" .
                             "\$host = '$db_host';\n" .
                             "\$dbname = '$db_name';\n" .
                             "\$username = '$db_user';\n" .
                             "\$password = '$db_pass';\n\n" .
                             "try {\n" .
                             "    \$pdo = new PDO(\"mysql:host=\$host;dbname=\$dbname\", \$username, \$password);\n" .
                             "    \$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);\n" .
                             "    \$pdo->exec(\"SET names utf8mb4\");\n" .
                             "} catch (PDOException \$e) {\n" .
                             "    die(\"Critical Error: Could not connect to the database. Please check your configuration.\");\n" .
                             "}\n" .
                             "?>";
            
            if (file_put_contents('includes/db_config.php', $config_content)) {
                
                // 1. Create Users Table
                $pdo->exec("CREATE TABLE IF NOT EXISTS `users` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `username` varchar(50) UNIQUE NOT NULL,
                    `full_name` varchar(100) DEFAULT NULL,
                    `emp_no` varchar(50) DEFAULT NULL,
                    `mobile_no` varchar(20) DEFAULT NULL,
                    `email` varchar(100) DEFAULT NULL,
                    `password` varchar(255) NOT NULL,
                    `role` enum('admin', 'moderator', 'mkt', 'tm', 'user') DEFAULT 'tm',
                    `status` enum('pending', 'active', 'suspended') DEFAULT 'pending',
                    `assigned_districts` text DEFAULT NULL,
                    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

                // 2. Create Counters Table (Consolidated)
                $pdo->exec("CREATE TABLE IF NOT EXISTS `counters` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `dealer_code` varchar(50) DEFAULT NULL,
                    `agent_code` varchar(50) DEFAULT NULL,
                    `seller_code` varchar(50) DEFAULT NULL,
                    `seller_name` varchar(100) DEFAULT NULL,
                    `title` varchar(20) DEFAULT NULL,
                    `nic_type` varchar(10) DEFAULT NULL,
                    `nic_old` varchar(20) DEFAULT NULL,
                    `nic_new` varchar(20) DEFAULT NULL,
                    `joined_year` varchar(4) DEFAULT NULL,
                    `counter_state` varchar(20) DEFAULT 'NLB',
                    `board_comment` text DEFAULT NULL,
                    `opening_hours` varchar(100) DEFAULT NULL,
                    `seller_image` varchar(255) DEFAULT NULL,
                    `birthday` date DEFAULT NULL,
                    `province` varchar(100) DEFAULT NULL,
                    `district` varchar(100) DEFAULT NULL,
                    `ds_division` varchar(100) DEFAULT NULL,
                    `gn_division` text DEFAULT NULL,
                    `approvals_json` text DEFAULT NULL,
                    `address` text DEFAULT NULL,
                    `address2` text DEFAULT NULL,
                    `phone` varchar(20) DEFAULT NULL,
                    `sales_method` varchar(50) DEFAULT NULL,
                    `location_link` text DEFAULT NULL,
                    `lat_cached` DECIMAL(10,7) DEFAULT NULL,
                    `lng_cached` DECIMAL(10,7) DEFAULT NULL,
                    `image_front` varchar(255) DEFAULT NULL,
                    `image_side` varchar(255) DEFAULT NULL,
                    `image_inside` varchar(255) DEFAULT NULL,
                    `image_rear` varchar(255) DEFAULT NULL,
                    `added_by` varchar(50) DEFAULT 'Unknown',
                    `reg_number` varchar(50) DEFAULT NULL,
                    `remarks` text DEFAULT NULL,
                    `status` varchar(20) DEFAULT 'Active',
                    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    INDEX idx_dealer (dealer_code),
                    INDEX idx_agent (agent_code),
                    UNIQUE INDEX idx_seller (seller_code),
                    INDEX idx_nic (nic_new)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

                $pdo->exec("CREATE TABLE IF NOT EXISTS `navigation` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `label` varchar(100) NOT NULL,
                    `url` varchar(255) NOT NULL,
                    `role_access` enum('all', 'admin', 'moderator', 'mkt', 'tm', 'user') DEFAULT 'all',
                    `nav_group` varchar(100) DEFAULT 'Main',
                    `sort_order` int(11) DEFAULT 0,
                    PRIMARY KEY (`id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

                // 4. Create Custom Fields Tables
                $pdo->exec("CREATE TABLE IF NOT EXISTS `custom_fields` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `field_label` varchar(100) NOT NULL,
                    `field_type` varchar(50) DEFAULT 'text',
                    `field_name` varchar(50) NOT NULL,
                    `placeholder` varchar(255) DEFAULT '',
                    `default_value` varchar(255) DEFAULT '',
                    `is_required` tinyint(1) DEFAULT 0,
                    `sort_order` int(11) DEFAULT 0,
                    `visible_for` varchar(50) DEFAULT 'all',
                    `display_section` varchar(50) DEFAULT 'additional',
                    `field_options` text DEFAULT NULL,
                    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `field_name` (`field_name`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

                $pdo->exec("CREATE TABLE IF NOT EXISTS `counter_custom_values` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `counter_id` int(11) NOT NULL,
                    `field_id` int(11) NOT NULL,
                    `field_value` text DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    KEY `counter_id` (`counter_id`),
                    KEY `field_id` (`field_id`),
                    FOREIGN KEY (`counter_id`) REFERENCES `counters`(`id`) ON DELETE CASCADE,
                    FOREIGN KEY (`field_id`) REFERENCES `custom_fields`(`id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

                // 5. Schema Tables (Settings, History, Dealers, Agents)
                $pdo->exec("CREATE TABLE IF NOT EXISTS `settings` (`setting_key` VARCHAR(50) NOT NULL, `setting_value` TEXT, PRIMARY KEY (`setting_key`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
                $pdo->exec("INSERT IGNORE INTO `settings` VALUES ('enable_location', '1');");
                $pdo->exec("CREATE TABLE IF NOT EXISTS `transfer_history` (`id` int(11) NOT NULL AUTO_INCREMENT, `counter_id` int(11) NOT NULL, `old_dealer_code` varchar(50), `new_dealer_code` varchar(50), `old_agent_code` varchar(50), `new_agent_code` varchar(50), `changed_by` varchar(50), `changed_at` timestamp DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (`id`), FOREIGN KEY (`counter_id`) REFERENCES `counters`(`id`) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
                $pdo->exec("CREATE TABLE IF NOT EXISTS `dealers` (`id` int(11) NOT NULL AUTO_INCREMENT, `dealer_code` varchar(50) UNIQUE NOT NULL, `name` varchar(100) NOT NULL, `nic_old` varchar(20), `nic_new` varchar(20), `birthday` date, `province` varchar(100), `district` varchar(100), `phone` varchar(20), `photo` varchar(255), `remarks` text DEFAULT NULL, `created_at` timestamp DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
                $pdo->exec("CREATE TABLE IF NOT EXISTS `dealer_addresses` (`id` int(11) NOT NULL AUTO_INCREMENT, `dealer_id` int(11) NOT NULL, `address_type` varchar(50) DEFAULT 'Office', `address_text` text NOT NULL, PRIMARY KEY (`id`), FOREIGN KEY (`dealer_id`) REFERENCES `dealers`(`id`) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
                $pdo->exec("CREATE TABLE IF NOT EXISTS `dealer_locations` (`id` int(11) NOT NULL AUTO_INCREMENT, `dealer_id` int(11) NOT NULL, `location_link` text NOT NULL, `lat_cached` DECIMAL(10,7) DEFAULT NULL, `lng_cached` DECIMAL(10,7) DEFAULT NULL, PRIMARY KEY (`id`), FOREIGN KEY (`dealer_id`) REFERENCES `dealers`(`id`) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
                $pdo->exec("CREATE TABLE IF NOT EXISTS `agents` (`id` int(11) NOT NULL AUTO_INCREMENT, `agent_code` varchar(50) UNIQUE NOT NULL, `dealer_code` varchar(50) NOT NULL, `name` varchar(100) NOT NULL, `nic_old` varchar(20), `nic_new` varchar(20), `birthday` date, `province` varchar(100), `district` varchar(100), `ds_division` varchar(100), `phone` varchar(20), `photo` varchar(255), `remarks` text DEFAULT NULL, `created_at` timestamp DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
                $pdo->exec("CREATE TABLE IF NOT EXISTS `agent_addresses` (`id` int(11) NOT NULL AUTO_INCREMENT, `agent_id` int(11) NOT NULL, `address_text` text NOT NULL, PRIMARY KEY (`id`), FOREIGN KEY (`agent_id`) REFERENCES `agents`(`id`) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
                $pdo->exec("CREATE TABLE IF NOT EXISTS `agent_locations` (`id` int(11) NOT NULL AUTO_INCREMENT, `agent_id` int(11) NOT NULL, `location_link` text NOT NULL, `lat_cached` DECIMAL(10,7) DEFAULT NULL, `lng_cached` DECIMAL(10,7) DEFAULT NULL, PRIMARY KEY (`id`), FOREIGN KEY (`agent_id`) REFERENCES `agents`(`id`) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
                
                // 6. Activity Log Table
                $pdo->exec("CREATE TABLE IF NOT EXISTS `activity_log` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `username` varchar(50) NOT NULL,
                    `role` varchar(20) DEFAULT NULL,
                    `action` varchar(100) NOT NULL,
                    `details` text DEFAULT NULL,
                    `entity_type` varchar(20) DEFAULT 'general',
                    `ip_address` varchar(45) DEFAULT NULL,
                    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

                // 7. Password Reset Table
                $pdo->exec("CREATE TABLE IF NOT EXISTS `password_resets` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `email` VARCHAR(100) NOT NULL,
                    `token` VARCHAR(100) NOT NULL,
                    `expires_at` DATETIME NOT NULL,
                    INDEX (`email`),
                    INDEX (`token`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
                
                // 8. Migrations for existing databases (Add missing columns)
                try {
                    $pdo->exec("ALTER TABLE `navigation` ADD COLUMN `nav_group` VARCHAR(100) DEFAULT 'Main' AFTER `role_access`;");
                } catch (Exception $e) {}
                
                try {
                    $pdo->exec("ALTER TABLE `counters` ADD COLUMN `board_comment` TEXT DEFAULT NULL AFTER `counter_state`;");
                } catch (Exception $e) {}

                try {
                    $pdo->exec("ALTER TABLE `counters` ADD COLUMN `joined_year` VARCHAR(4) DEFAULT NULL AFTER `nic_new`;");
                } catch (Exception $e) {}

                try {
                    $pdo->exec("ALTER TABLE `counters` ADD COLUMN `approvals_json` TEXT DEFAULT NULL AFTER `gn_division`;");
                } catch (Exception $e) {}

                try {
                    $pdo->exec("ALTER TABLE `counters` ADD COLUMN `opening_hours` VARCHAR(100) DEFAULT NULL AFTER `counter_state`;");
                } catch (Exception $e) {}

                try {
                    $pdo->exec("ALTER TABLE `counters` ADD COLUMN `remarks` TEXT DEFAULT NULL AFTER `reg_number`;");
                } catch (Exception $e) {}

                try {
                    $pdo->exec("ALTER TABLE `counters` ADD COLUMN `status` VARCHAR(20) DEFAULT 'Active' AFTER `remarks`;");
                } catch (Exception $e) {}

                try {
                    $pdo->exec("ALTER TABLE `counters` ADD COLUMN `address2` TEXT DEFAULT NULL AFTER `address`;");
                } catch (Exception $e) {}

                try {
                    $pdo->exec("ALTER TABLE `counters` ADD COLUMN `location_link` TEXT DEFAULT NULL AFTER `sales_method`;");
                } catch (Exception $e) {}

                try {
                    $pdo->exec("ALTER TABLE `dealers` ADD COLUMN `remarks` TEXT DEFAULT NULL AFTER `photo`;");
                } catch (Exception $e) {}

                try {
                    $pdo->exec("ALTER TABLE `agents` ADD COLUMN `remarks` TEXT DEFAULT NULL AFTER `photo`;");
                } catch (Exception $e) {}

                try {
                    $pdo->exec("ALTER TABLE `agents` ADD COLUMN `province` VARCHAR(100) DEFAULT NULL AFTER `birthday`;");
                } catch (Exception $e) {}

                try {
                    $pdo->exec("ALTER TABLE `counters` ADD COLUMN `title` VARCHAR(20) DEFAULT NULL AFTER `seller_name`;");
                } catch (Exception $e) {}

                try {
                    $pdo->exec("ALTER TABLE `agents` ADD COLUMN `district` VARCHAR(100) DEFAULT NULL AFTER `province`;");
                } catch (Exception $e) {}

                try {
                    $pdo->exec("ALTER TABLE `agents` ADD COLUMN `ds_division` VARCHAR(100) DEFAULT NULL AFTER `district`;");
                } catch (Exception $e) {}

                try {
                    $pdo->exec("ALTER TABLE `counters` ADD COLUMN `lat_cached` DECIMAL(10,7) DEFAULT NULL AFTER `location_link`;");
                } catch (Exception $e) {}

                try {
                    $pdo->exec("ALTER TABLE `counters` ADD COLUMN `lng_cached` DECIMAL(10,7) DEFAULT NULL AFTER `lat_cached`;");
                } catch (Exception $e) {}

                try {
                    $pdo->exec("ALTER TABLE `dealer_locations` ADD COLUMN `lat_cached` DECIMAL(10,7) DEFAULT NULL;");
                } catch (Exception $e) {}

                try {
                    $pdo->exec("ALTER TABLE `dealer_locations` ADD COLUMN `lng_cached` DECIMAL(10,7) DEFAULT NULL;");
                } catch (Exception $e) {}

                try {
                    $pdo->exec("ALTER TABLE `agent_locations` ADD COLUMN `lat_cached` DECIMAL(10,7) DEFAULT NULL;");
                } catch (Exception $e) {}

                try {
                    $pdo->exec("ALTER TABLE `agent_locations` ADD COLUMN `lng_cached` DECIMAL(10,7) DEFAULT NULL;");
                } catch (Exception $e) {}

                try {
                    $pdo->exec("ALTER TABLE `counters` ADD COLUMN `image_rear` VARCHAR(255) DEFAULT NULL AFTER `image_inside`;");
                } catch (Exception $e) {}

                try {
                    $pdo->exec("ALTER TABLE `counters` ADD UNIQUE INDEX `idx_seller` (`seller_code`);");
                } catch (Exception $e) {}

                // 9. Seed Default Navigation Links
                $pdo->exec("INSERT INTO `navigation` (label, url, role_access, nav_group, sort_order)
                    SELECT * FROM (
                        SELECT 'Home 🏠'                  AS label, 'dashboard.php'      AS url, 'all'       AS role_access, 'Main' AS nav_group, 1 AS sort_order UNION ALL
                        SELECT 'Location (Map View) 📍',            'map_view.php',              'all',             'Main',             2               UNION ALL
                        SELECT 'Scan QR 🔍',                      'scanner.php',               'all',             'Main',             3               UNION ALL
                        SELECT 'Dealer Entry 🏢',                   'add_dealer.php',            'tm',              'Details Entry 📝', 1               UNION ALL
                        SELECT 'Agent Entry 👤',                    'add_agent.php',             'tm',              'Details Entry 📝', 2               UNION ALL
                        SELECT 'Counter Seller Entry 📝',           'index.php',                 'tm',              'Details Entry 📝', 3               UNION ALL
                        SELECT 'Mobile Seller Entry 📱',           'mobile_seller.php',         'tm',              'Details Entry 📝', 4               UNION ALL
                        SELECT 'Sales Point Entry 🏪',             'sales_point.php',           'tm',              'Details Entry 📝', 5               UNION ALL
                        SELECT 'View Dealers 📂',                 'view_dealers.php',          'tm',              'View Details 📊',  1               UNION ALL
                        SELECT 'View Agents 📂',                  'view_agents.php',           'tm',              'View Details 📊',  2               UNION ALL
                        SELECT 'Ticket Counter View',             'dashboard.php?type=Ticket Counter', 'all',       'View Details 📊',  3               UNION ALL
                        SELECT 'Mobile Sales View',               'dashboard.php?type=Mobile Sales', 'all',         'View Details 📊',  4               UNION ALL
                        SELECT 'Sales Point View',                'dashboard.php?type=Sales Point', 'all',          'View Details 📊',  5               UNION ALL
                        SELECT 'User Management 👤',              'manage_users.php',          'admin',           'Main',             10              UNION ALL
                        SELECT 'Menu Settings ⚙️',                'manage_nav.php',            'admin',           'Main',             11              UNION ALL
                        SELECT 'Form Field Manager 🛠️',           'admin_fields.php',          'admin',           'Main',             12              UNION ALL
                        SELECT 'Bulk Image Upload 🖼️',           'bulk_image_upload.php',     'admin',           'Main',             13              UNION ALL
                        SELECT 'Import CSV Data 📤',              'import_csv.php',            'admin',           'Main',             14              UNION ALL
                        SELECT 'Contact Us 📞',                  'contact_us.php',            'all',             'Main',             30              UNION ALL
                        SELECT 'Activity Log 📜',                'activity_log.php',          'admin',           'Main',             40              UNION ALL
                        SELECT 'System Backup 💾',               'backup.php',                'admin',           'Main',             41              UNION ALL
                        SELECT 'Manage Duplicates 🚨',           'manage_duplicates.php',     'admin',           'Main',             42              UNION ALL
                        SELECT 'Prize Announcements 🏆',         'prize_announcements.php',   'moderator',       'Main',             50              UNION ALL
                        SELECT 'Data Analysis 📊',               'analytics.php',             'tm',              'View Details 📊',  10
                    ) AS tmp
                    WHERE NOT EXISTS (SELECT 1 FROM `navigation` LIMIT 1);");

                // 10. Prize Announcements table
                try {
                    $pdo->exec("CREATE TABLE IF NOT EXISTS `prize_announcements` (`id` int(11) NOT NULL AUTO_INCREMENT, `agent_code` varchar(50) NOT NULL, `title` varchar(255) DEFAULT 'Big Prize Winner!', `description` text DEFAULT NULL, `photo_1` varchar(255) DEFAULT NULL, `photo_2` varchar(255) DEFAULT NULL, `photo_3` varchar(255) DEFAULT NULL, `photo_4` varchar(255) DEFAULT NULL, `is_active` tinyint(1) DEFAULT 1, `created_by` varchar(50) DEFAULT NULL, `created_at` timestamp DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
                } catch (Exception $e) {}

                // 11. Migration: alter role enums to add 'mkt' (for existing databases)
                try {
                    $pdo->exec("ALTER TABLE `users` MODIFY `role` enum('admin','moderator','mkt','tm','user') DEFAULT 'tm';");
                } catch (Exception $e) {}
                try {
                    $pdo->exec("ALTER TABLE `navigation` MODIFY `role_access` enum('all','admin','moderator','mkt','tm','user') DEFAULT 'all';");
                } catch (Exception $e) {}

                // 12. Create uploads directory
                $uploads_dir = __DIR__ . '/uploads';
                if (!is_dir($uploads_dir)) {
                    mkdir($uploads_dir, 0755, true);
                    file_put_contents($uploads_dir . '/.htaccess',
                        "Options -Indexes\n" .
                        "<FilesMatch '(?i)\.(php|php3|php4|php5|phtml|pl|py|jsp|asp|sh|cgi)$'>\n" .
                        "    Require all denied\n" .
                        "</FilesMatch>\n"
                    );
                }

                // Create uploads/prizes sub-directory
                $prizes_dir = __DIR__ . '/uploads/prizes';
                if (!is_dir($prizes_dir)) {
                    mkdir($prizes_dir, 0755, true);
                }

                header("Location: installer.php?step=2");
                exit;

            } else {
                $message = "Error: System could not write to 'includes/db_config.php'. Check folder permissions.";
                $status = "error";
            }
        } catch (PDOException $e) {
            $message = "Connection Failure: " . $e->getMessage();
            $status = "error";
        }
    } elseif ($step === 2) {
        // Step 2: Administrator Setup
        if (!file_exists('includes/db_config.php')) {
            header("Location: installer.php?step=1");
            exit;
        }

        require_once 'includes/db_config.php';
        $admin_user = $_POST['admin_user'];
        $admin_pass = $_POST['admin_pass'];
        
        if (strlen($admin_pass) < 6) {
            $message = "Security Error: Password must be at least 6 characters long.";
            $status = "error";
        } else {
            $hashed_pass = password_hash($admin_pass, PASSWORD_DEFAULT);
            try {
                $stmt = $pdo->prepare("INSERT INTO users (username, full_name, emp_no, password, role, status) VALUES (?, 'System Administrator', 'ADMIN-001', ?, 'admin', 'active') 
                                     ON DUPLICATE KEY UPDATE password = ?, role = 'admin', status = 'active'");
                $stmt->execute([$admin_user, $hashed_pass, $hashed_pass]);
                
                $message = "Installer: System configuration finalized.";
                $status = "success";
                $step = 3;
            } catch (PDOException $e) {
                $message = "Database Error: " . $e->getMessage();
                $status = "error";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Setup - NLB Seller Map</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="assets/img/logo1.png">
    <link rel="stylesheet" href="assets/css/style.css">
    <script>
        (function() {
            const savedTheme = localStorage.getItem("theme");
            if (savedTheme === "dark" || !savedTheme) {
                document.documentElement.classList.add("dark-mode");
            }
        })();
    </script>
    <style>
        .step-indicator { 
            display: flex; 
            justify-content: space-between; 
            margin-bottom: 2.5rem; 
            background: var(--nav-bg);
            padding: 1.25rem;
            border-radius: 16px;
            border: 1px solid var(--glass-border);
        }
        .step { 
            flex: 1; 
            text-align: center; 
            color: var(--text-muted); 
            font-size: 0.8rem; 
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 500;
        }
        .step.active { 
            color: var(--secondary-color); 
            font-weight: 700; 
        }
        .installer-container { 
            animation: slideUp 0.8s cubic-bezier(0.16, 1, 0.3, 1); 
        }
        .setup-card {
            background: var(--card-bg);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            padding: 1.5rem 2rem;
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            box-shadow: 0 40px 100px -20px rgba(0, 0, 0, 0.4);
        }
    </style>
</head>
<body>
    <div class="container" style="max-width: 500px; background: transparent; border: none; box-shadow: none; backdrop-filter: none; padding: 0;">
        <div class="installer-container" style="margin-top: 2rem;">
            <div style="text-align: center; margin-bottom: 1.5rem;">
                <img src="assets/img/Logo.png" alt="NLB Logo" style="max-width: 100px; filter: var(--white-shadow);">
            </div>
            
            <div class="setup-card">
                <h1>System Installer</h1>
                <p class="subtitle">Initialize your NLB Seller Map Portal</p>

                <div class="step-indicator">
                    <div class="step <?php echo $step === 1 ? 'active' : ''; ?>">1. Database</div>
                    <div class="step <?php echo $step === 2 ? 'active' : ''; ?>">2. Security</div>
                    <div class="step <?php echo $step === 3 ? 'active' : ''; ?>">3. Ready</div>
                </div>

                <?php if ($message): ?>
                    <div class="message <?php echo $status; ?>" style="display: block;">
                        <?php echo ($status === 'success' ? '✅ ' : '❌ ') . $message; ?>
                    </div>
                <?php endif; ?>

                <?php if ($step === 1): ?>
                    <form method="POST">
                        <?php csrf_input(); ?>
                        <div class="form-group">
                            <label>Database Host</label>
                            <input type="text" name="db_host" value="localhost" placeholder="e.g. localhost" required>
                        </div>
                        <div class="form-group">
                            <label>Database Name</label>
                            <input type="text" name="db_name" value="seller_map" placeholder="e.g. seller_map" required>
                        </div>
                        <div class="form-group">
                            <label>Username</label>
                            <input type="text" name="db_user" value="root" placeholder="e.g. root" required>
                        </div>
                        <div class="form-group">
                            <label>Password</label>
                            <input type="password" name="db_pass" placeholder="Database password (optional)">
                        </div>
                        <button type="submit" class="btn-submit">
                            ⚡ Initialize System
                        </button>
                        <p style="text-align: center; margin-top: 1.5rem; color: var(--text-muted); font-size: 0.75rem; line-height: 1.6;">
                            This process will automatically create the necessary database tables and seed initial configuration.
                        </p>
                    </form>
                <?php elseif ($step === 2): ?>
                    <form method="POST">
                        <?php csrf_input(); ?>
                        <div class="form-group">
                            <label>Admin Username</label>
                            <input type="text" name="admin_user" value="admin" required>
                        </div>
                        <div class="form-group">
                            <label>Admin Password</label>
                            <input type="password" name="admin_pass" required minlength="6" placeholder="Minimum 6 characters">
                        </div>
                        <button type="submit" class="btn-submit">
                            🛡️ Finalize Security Setup
                        </button>
                    </form>
                <?php elseif ($step === 3): ?>
                    <div style="text-align: center; padding: 1rem 0;">
                        <div style="font-size: 5rem; margin-bottom: 1.5rem; animation: pulse 2s infinite;">🚀</div>
                        <h3 style="color: var(--secondary-color); font-size: 1.5rem; margin-bottom: 0.5rem;">Setup Complete!</h3>
                        <p style="color: var(--text-muted); margin-bottom: 2rem;">The lottery data collection portal is now configured and ready for production.</p>
                        
                        <div class="message warning" style="display: block; font-size: 0.8rem; padding: 15px; border-radius: 12px; margin-bottom: 2rem;">
                            <strong>Security Alert:</strong> Please delete <code>installer.php</code> from your server root immediately to prevent unauthorized reconfiguration.
                        </div>
                        
                        <a href="login.php" class="btn-submit" style="display: block; text-decoration: none; padding: 1rem;">
                            Go to Portal Login
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php include 'includes/footer.php'; ?>
</body>
</html>
