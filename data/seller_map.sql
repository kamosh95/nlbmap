-- Database Dump

-- Table structure for `activity_log`
DROP TABLE IF EXISTS `activity_log`;
CREATE TABLE `activity_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `role` varchar(20) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `details` text,
  `entity_type` varchar(20) DEFAULT 'general',
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table structure for `agent_addresses`
DROP TABLE IF EXISTS `agent_addresses`;
CREATE TABLE `agent_addresses` (
  `id` int NOT NULL AUTO_INCREMENT,
  `agent_id` int NOT NULL,
  `address_text` text NOT NULL,
  PRIMARY KEY (`id`),
  KEY `agent_id` (`agent_id`),
  CONSTRAINT `agent_addresses_ibfk_1` FOREIGN KEY (`agent_id`) REFERENCES `agents` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Data for `agent_addresses`

-- Table structure for `agent_locations`
DROP TABLE IF EXISTS `agent_locations`;
CREATE TABLE `agent_locations` (
  `id` int NOT NULL AUTO_INCREMENT,
  `agent_id` int NOT NULL,
  `location_link` text NOT NULL,
  `lat_cached` decimal(10,7) DEFAULT NULL,
  `lng_cached` decimal(10,7) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `agent_id` (`agent_id`),
  CONSTRAINT `agent_locations_ibfk_1` FOREIGN KEY (`agent_id`) REFERENCES `agents` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Data for `agent_locations`

-- Table structure for `agents`
DROP TABLE IF EXISTS `agents`;
CREATE TABLE `agents` (
  `id` int NOT NULL AUTO_INCREMENT,
  `agent_code` varchar(50) NOT NULL,
  `dealer_code` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `nic_old` varchar(20) DEFAULT NULL,
  `nic_new` varchar(20) DEFAULT NULL,
  `birthday` date DEFAULT NULL,
  `province` varchar(100) DEFAULT NULL,
  `district` varchar(100) DEFAULT NULL,
  `ds_division` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `remarks` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `agent_code` (`agent_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Data for `agents`

-- Table structure for `counter_custom_values`
DROP TABLE IF EXISTS `counter_custom_values`;
CREATE TABLE `counter_custom_values` (
  `id` int NOT NULL AUTO_INCREMENT,
  `counter_id` int NOT NULL,
  `field_id` int NOT NULL,
  `field_value` text,
  PRIMARY KEY (`id`),
  KEY `counter_id` (`counter_id`),
  KEY `field_id` (`field_id`),
  CONSTRAINT `counter_custom_values_ibfk_1` FOREIGN KEY (`counter_id`) REFERENCES `counters` (`id`) ON DELETE CASCADE,
  CONSTRAINT `counter_custom_values_ibfk_2` FOREIGN KEY (`field_id`) REFERENCES `custom_fields` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Data for `counter_custom_values`

-- Table structure for `counters`
DROP TABLE IF EXISTS `counters`;
CREATE TABLE `counters` (
  `id` int NOT NULL AUTO_INCREMENT,
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
  `board_comment` text,
  `opening_hours` varchar(100) DEFAULT NULL,
  `seller_image` varchar(255) DEFAULT NULL,
  `birthday` date DEFAULT NULL,
  `province` varchar(100) DEFAULT NULL,
  `district` varchar(100) DEFAULT NULL,
  `ds_division` varchar(100) DEFAULT NULL,
  `gn_division` text,
  `remarks` text,
  `approvals_json` text,
  `address` text,
  `address2` text,
  `phone` varchar(20) DEFAULT NULL,
  `sales_method` varchar(50) DEFAULT NULL,
  `location_link` text,
  `lat_cached` decimal(10,7) DEFAULT NULL,
  `lng_cached` decimal(10,7) DEFAULT NULL,
  `image_front` varchar(255) DEFAULT NULL,
  `image_side` varchar(255) DEFAULT NULL,
  `image_inside` varchar(255) DEFAULT NULL,
  `added_by` varchar(50) DEFAULT 'Unknown',
  `reg_number` varchar(50) DEFAULT NULL,
  `status` varchar(20) DEFAULT 'Active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_dealer` (`dealer_code`),
  KEY `idx_agent` (`agent_code`),
  KEY `idx_seller` (`seller_code`),
  KEY `idx_nic` (`nic_new`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Data for `counters`

-- Table structure for `custom_fields`
DROP TABLE IF EXISTS `custom_fields`;
CREATE TABLE `custom_fields` (
  `id` int NOT NULL AUTO_INCREMENT,
  `field_label` varchar(100) NOT NULL,
  `field_type` varchar(50) DEFAULT 'text',
  `field_name` varchar(50) NOT NULL,
  `placeholder` varchar(255) DEFAULT '',
  `default_value` varchar(255) DEFAULT '',
  `is_required` tinyint(1) DEFAULT '0',
  `sort_order` int DEFAULT '0',
  `visible_for` varchar(50) DEFAULT 'all',
  `display_section` varchar(50) DEFAULT 'additional',
  `field_options` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `field_name` (`field_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Data for `custom_fields`

-- Table structure for `dealer_addresses`
DROP TABLE IF EXISTS `dealer_addresses`;
CREATE TABLE `dealer_addresses` (
  `id` int NOT NULL AUTO_INCREMENT,
  `dealer_id` int NOT NULL,
  `address_type` varchar(50) DEFAULT 'Office',
  `address_text` text NOT NULL,
  PRIMARY KEY (`id`),
  KEY `dealer_id` (`dealer_id`),
  CONSTRAINT `dealer_addresses_ibfk_1` FOREIGN KEY (`dealer_id`) REFERENCES `dealers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Data for `dealer_addresses`

-- Table structure for `dealer_locations`
DROP TABLE IF EXISTS `dealer_locations`;
CREATE TABLE `dealer_locations` (
  `id` int NOT NULL AUTO_INCREMENT,
  `dealer_id` int NOT NULL,
  `location_link` text NOT NULL,
  `lat_cached` decimal(10,7) DEFAULT NULL,
  `lng_cached` decimal(10,7) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `dealer_id` (`dealer_id`),
  CONSTRAINT `dealer_locations_ibfk_1` FOREIGN KEY (`dealer_id`) REFERENCES `dealers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Data for `dealer_locations`

-- Table structure for `dealers`
DROP TABLE IF EXISTS `dealers`;
CREATE TABLE `dealers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `dealer_code` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `nic_old` varchar(20) DEFAULT NULL,
  `nic_new` varchar(20) DEFAULT NULL,
  `birthday` date DEFAULT NULL,
  `province` varchar(100) DEFAULT NULL,
  `district` varchar(100) DEFAULT NULL,
  `ds_division` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `remarks` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `dealer_code` (`dealer_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Data for `dealers`

-- Table structure for `navigation`
DROP TABLE IF EXISTS `navigation`;
CREATE TABLE `navigation` (
  `id` int NOT NULL AUTO_INCREMENT,
  `label` varchar(100) NOT NULL,
  `url` varchar(255) NOT NULL,
  `role_access` enum('all','admin','moderator','tm','user') DEFAULT 'all',
  `nav_group` varchar(100) DEFAULT 'Main',
  `sort_order` int DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=33 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Data for `navigation`
INSERT INTO `navigation` (`id`, `label`, `url`, `role_access`, `nav_group`, `sort_order`) VALUES ('2', 'GPS📍', 'map_view.php', 'all', 'Main', '2');
INSERT INTO `navigation` (`id`, `label`, `url`, `role_access`, `nav_group`, `sort_order`) VALUES ('3', 'Scan QR 🔍', 'scanner.php', 'all', 'Main', '3');
INSERT INTO `navigation` (`id`, `label`, `url`, `role_access`, `nav_group`, `sort_order`) VALUES ('4', 'Dealer Entry 🏢', 'add_dealer.php', 'tm', 'Details Entry 📝', '1');
INSERT INTO `navigation` (`id`, `label`, `url`, `role_access`, `nav_group`, `sort_order`) VALUES ('5', 'Agent Entry 👤', 'add_agent.php', 'tm', 'Details Entry 📝', '2');
INSERT INTO `navigation` (`id`, `label`, `url`, `role_access`, `nav_group`, `sort_order`) VALUES ('6', 'Counter Seller Entry 📝', 'index.php', 'tm', 'Details Entry 📝', '3');
INSERT INTO `navigation` (`id`, `label`, `url`, `role_access`, `nav_group`, `sort_order`) VALUES ('7', 'Mobile Seller Entry 📱', 'mobile_seller.php', 'tm', 'Details Entry 📝', '4');
INSERT INTO `navigation` (`id`, `label`, `url`, `role_access`, `nav_group`, `sort_order`) VALUES ('8', 'Sales Point Entry 🏪', 'sales_point.php', 'tm', 'Details Entry 📝', '5');
INSERT INTO `navigation` (`id`, `label`, `url`, `role_access`, `nav_group`, `sort_order`) VALUES ('9', 'View Dealers 📂', 'view_dealers.php', 'tm', 'View Details 📊', '1');
INSERT INTO `navigation` (`id`, `label`, `url`, `role_access`, `nav_group`, `sort_order`) VALUES ('10', 'View Agents 📂', 'view_agents.php', 'tm', 'View Details 📊', '2');
INSERT INTO `navigation` (`id`, `label`, `url`, `role_access`, `nav_group`, `sort_order`) VALUES ('11', 'Ticket Counter View', 'dashboard.php?type=Ticket Counter', 'all', 'View Details 📊', '3');
INSERT INTO `navigation` (`id`, `label`, `url`, `role_access`, `nav_group`, `sort_order`) VALUES ('12', 'Mobile Sales View', 'dashboard.php?type=Mobile Sales', 'all', 'View Details 📊', '4');
INSERT INTO `navigation` (`id`, `label`, `url`, `role_access`, `nav_group`, `sort_order`) VALUES ('13', 'Sales Point View', 'dashboard.php?type=Sales Point', 'all', 'View Details 📊', '5');
INSERT INTO `navigation` (`id`, `label`, `url`, `role_access`, `nav_group`, `sort_order`) VALUES ('14', 'User Management 👤', 'manage_users.php', 'admin', 'Main', '10');
INSERT INTO `navigation` (`id`, `label`, `url`, `role_access`, `nav_group`, `sort_order`) VALUES ('15', 'Menu Settings ⚙️', 'manage_nav.php', 'admin', 'Main', '11');
INSERT INTO `navigation` (`id`, `label`, `url`, `role_access`, `nav_group`, `sort_order`) VALUES ('16', 'Form Field Manager 🛠️', 'admin_fields.php', 'admin', 'Main', '12');
INSERT INTO `navigation` (`id`, `label`, `url`, `role_access`, `nav_group`, `sort_order`) VALUES ('17', 'Bulk Image Upload 🖼️', 'bulk_image_upload.php', 'admin', 'Main', '13');
INSERT INTO `navigation` (`id`, `label`, `url`, `role_access`, `nav_group`, `sort_order`) VALUES ('18', 'Import CSV Data 📤', 'import_csv.php', 'admin', 'Main', '14');
INSERT INTO `navigation` (`id`, `label`, `url`, `role_access`, `nav_group`, `sort_order`) VALUES ('19', 'Contact Us 📞', 'contact_us.php', 'all', 'Main', '30');
INSERT INTO `navigation` (`id`, `label`, `url`, `role_access`, `nav_group`, `sort_order`) VALUES ('20', 'Activity Log 📜', 'activity_log.php', 'admin', 'Main', '40');
INSERT INTO `navigation` (`id`, `label`, `url`, `role_access`, `nav_group`, `sort_order`) VALUES ('21', 'Prize Announcements 🏆', 'prize_announcements.php', 'admin', 'Main', '50');

-- Table structure for `password_resets`
DROP TABLE IF EXISTS `password_resets`;
CREATE TABLE `password_resets` (
  `id` int NOT NULL AUTO_INCREMENT,
  `email` varchar(100) NOT NULL,
  `token` varchar(100) NOT NULL,
  `expires_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `email` (`email`),
  KEY `token` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Data for `password_resets`

-- Table structure for `prize_announcements`
DROP TABLE IF EXISTS `prize_announcements`;
CREATE TABLE `prize_announcements` (
  `id` int NOT NULL AUTO_INCREMENT,
  `agent_code` varchar(50) NOT NULL,
  `title` varchar(255) DEFAULT 'Big Prize Winner!',
  `description` text,
  `photo_1` varchar(255) DEFAULT NULL,
  `photo_2` varchar(255) DEFAULT NULL,
  `photo_3` varchar(255) DEFAULT NULL,
  `photo_4` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_by` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Data for `prize_announcements`

-- Table structure for `settings`
DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings` (
  `setting_key` varchar(50) NOT NULL,
  `setting_value` text,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Data for `settings`
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES ('enable_location', '1');

-- Table structure for `transfer_history`
DROP TABLE IF EXISTS `transfer_history`;
CREATE TABLE `transfer_history` (
  `id` int NOT NULL AUTO_INCREMENT,
  `counter_id` int NOT NULL,
  `old_dealer_code` varchar(50) DEFAULT NULL,
  `new_dealer_code` varchar(50) DEFAULT NULL,
  `old_agent_code` varchar(50) DEFAULT NULL,
  `new_agent_code` varchar(50) DEFAULT NULL,
  `changed_by` varchar(50) DEFAULT NULL,
  `changed_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `counter_id` (`counter_id`),
  CONSTRAINT `transfer_history_ibfk_1` FOREIGN KEY (`counter_id`) REFERENCES `counters` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table structure for `users`
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `emp_no` varchar(50) DEFAULT NULL,
  `mobile_no` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','moderator','tm','user') DEFAULT 'tm',
  `status` enum('pending','active','suspended') DEFAULT 'pending',
  `assigned_districts` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Data for `users`
INSERT INTO `users` (`id`, `username`, `full_name`, `emp_no`, `mobile_no`, `email`, `password`, `role`, `status`, `assigned_districts`, `created_at`) VALUES ('1', 'admin', 'System Administrator', 'ADMIN-001', NULL, NULL, '$2y$10$Wltm9WX6tByjhAA4PdAsruGllL6jJ5XUFZGJ8IUgUp6Yi0SAY09AG', 'admin', 'active', NULL, '2026-03-07 11:37:52');

