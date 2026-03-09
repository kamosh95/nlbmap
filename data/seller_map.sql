-- NLB Seller Map Portal Database Schema
-- Updated: 2026-03-09 (Added MKT role, prize_announcements table)
-- This file contains the complete updated schema for the system.

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- 1. Table structure for table `users`
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `emp_no` varchar(50) DEFAULT NULL,
  `mobile_no` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','moderator','mkt','tm','user') DEFAULT 'tm',
  `status` enum('pending','active','suspended') DEFAULT 'pending',
  `assigned_districts` text DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Table structure for table `counters`
CREATE TABLE IF NOT EXISTS `counters` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `dealer_code` varchar(50) DEFAULT NULL,
  `agent_code` varchar(50) DEFAULT NULL,
  `seller_code` varchar(50) DEFAULT NULL,
  `seller_name` varchar(100) DEFAULT NULL,
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
  `image_front` varchar(255) DEFAULT NULL,
  `image_side` varchar(255) DEFAULT NULL,
  `image_inside` varchar(255) DEFAULT NULL,
  `added_by` varchar(50) DEFAULT 'Unknown',
  `reg_number` varchar(50) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `status` varchar(20) DEFAULT 'Active',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX idx_dealer (dealer_code),
  INDEX idx_agent (agent_code),
  INDEX idx_seller (seller_code),
  INDEX idx_name (seller_name),
  INDEX idx_nic_new (nic_new),
  INDEX idx_sales_method (sales_method)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Table structure for table `dealers`
CREATE TABLE IF NOT EXISTS `dealers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `dealer_code` varchar(50) UNIQUE NOT NULL,
  `name` varchar(100) NOT NULL,
  `nic_old` varchar(20) DEFAULT NULL,
  `nic_new` varchar(20) DEFAULT NULL,
  `birthday` date DEFAULT NULL,
  `province` varchar(100) NOT NULL,
  `district` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Table structure for table `dealer_addresses`
CREATE TABLE IF NOT EXISTS `dealer_addresses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `dealer_id` int(11) NOT NULL,
  `address_type` varchar(50) DEFAULT 'Office',
  `address_text` text NOT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`dealer_id`) REFERENCES `dealers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Table structure for table `dealer_locations`
CREATE TABLE IF NOT EXISTS `dealer_locations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `dealer_id` int(11) NOT NULL,
  `location_link` text NOT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`dealer_id`) REFERENCES `dealers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Table structure for table `agents`
CREATE TABLE IF NOT EXISTS `agents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `agent_code` varchar(50) UNIQUE NOT NULL,
  `dealer_code` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `nic_old` varchar(20) DEFAULT NULL,
  `nic_new` varchar(20) DEFAULT NULL,
  `birthday` date DEFAULT NULL,
  `province` varchar(100) NOT NULL,
  `district` varchar(100) NOT NULL,
  `ds_division` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. Table structure for table `agent_addresses`
CREATE TABLE IF NOT EXISTS `agent_addresses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `agent_id` int(11) NOT NULL,
  `address_text` text NOT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`agent_id`) REFERENCES `agents`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8. Table structure for table `agent_locations`
CREATE TABLE IF NOT EXISTS `agent_locations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `agent_id` int(11) NOT NULL,
  `location_link` text NOT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`agent_id`) REFERENCES `agents`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 9. Table structure for table `navigation`
CREATE TABLE IF NOT EXISTS `navigation` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `label` varchar(100) NOT NULL,
  `url` varchar(255) NOT NULL,
  `role_access` enum('all','admin','moderator','mkt','tm','user') DEFAULT 'all',
  `nav_group` varchar(100) DEFAULT 'Main',
  `sort_order` int(11) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Dumping core navigation links
INSERT INTO `navigation` (`label`, `url`, `role_access`, `nav_group`, `sort_order`) VALUES
('Home 🏠', 'dashboard.php', 'all', 'Main', 1),
('Location (Map View) 📍', 'map_view.php', 'all', 'Main', 2),
('Scan QR 🔍', 'scanner.php', 'all', 'Main', 3),
('Dealer Entry 🏢', 'add_dealer.php', 'admin', 'Details Entry 📝', 1),
('Agent Entry 👤', 'add_agent.php', 'admin', 'Details Entry 📝', 2),
('Counter Seller Entry 📝', 'index.php', 'tm', 'Details Entry 📝', 3),
('Mobile Seller Entry 📱', 'mobile_seller.php', 'tm', 'Details Entry 📝', 4),
('Sales Point Entry 🏪', 'sales_point.php', 'tm', 'Details Entry 📝', 5),
('View Dealers 📂', 'view_dealers.php', 'admin', 'View Details 📊', 1),
('View Agents 📂', 'view_agents.php', 'admin', 'View Details 📊', 2),
('Ticket Counter View', 'dashboard.php?type=Ticket Counter', 'all', 'View Details 📊', 3),
('Mobile Sales View', 'dashboard.php?type=Mobile Sales', 'all', 'View Details 📊', 4),
('Sales Point View', 'dashboard.php?type=Sales Point', 'all', 'View Details 📊', 5),
('User Management 👤', 'manage_users.php', 'admin', 'Main', 10),
('Menu Settings ⚙️', 'manage_nav.php', 'admin', 'Main', 11),
('Form Field Manager 🛠️', 'admin_fields.php', 'admin', 'Main', 12),
('Import CSV Data 📤', 'import_csv.php', 'admin', 'Main', 13),
('Contact Us 📞', 'contact_us.php', 'all', 'Main', 30),
('Activity Log 📜', 'activity_log.php', 'admin', 'Main', 40),
('Prize Announcements 🏆', 'prize_announcements.php', 'moderator', 'Main', 50);

-- 10. Table structure for table `custom_fields`
CREATE TABLE IF NOT EXISTS `custom_fields` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 11. Table structure for table `counter_custom_values`
CREATE TABLE IF NOT EXISTS `counter_custom_values` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `counter_id` int(11) NOT NULL,
  `field_id` int(11) NOT NULL,
  `field_value` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `counter_id` (`counter_id`),
  KEY `field_id` (`field_id`),
  FOREIGN KEY (`counter_id`) REFERENCES `counters` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`field_id`) REFERENCES `custom_fields` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 12. Table structure for table `settings`
CREATE TABLE IF NOT EXISTS `settings` (
  `setting_key` VARCHAR(50) NOT NULL,
  `setting_value` TEXT DEFAULT NULL,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('enable_location', '1');

-- 13. Table structure for table `transfer_history`
CREATE TABLE IF NOT EXISTS `transfer_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `counter_id` int(11) NOT NULL,
  `old_dealer_code` varchar(50) DEFAULT NULL,
  `new_dealer_code` varchar(50) DEFAULT NULL,
  `old_agent_code` varchar(50) DEFAULT NULL,
  `new_agent_code` varchar(50) DEFAULT NULL,
  `changed_by` varchar(50) DEFAULT NULL,
  `changed_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`counter_id`) REFERENCES `counters` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 14. Table structure for table `activity_log`
CREATE TABLE IF NOT EXISTS `activity_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `role` varchar(20) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `details` text DEFAULT NULL,
  `entity_type` varchar(20) DEFAULT 'general',
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 15. Table structure for table `password_resets`
CREATE TABLE IF NOT EXISTS `password_resets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(100) NOT NULL,
  `token` varchar(100) NOT NULL,
  `expires_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  INDEX (`email`),
  INDEX (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 16. Table structure for table `prize_announcements`
CREATE TABLE IF NOT EXISTS `prize_announcements` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

COMMIT;
