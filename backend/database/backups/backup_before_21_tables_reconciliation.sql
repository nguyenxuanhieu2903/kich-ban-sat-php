-- Database Backup: booking_project_php
-- Timestamp: 2026-11-20 18:43:14

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `audit_logs`;
CREATE TABLE `audit_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned DEFAULT NULL,
  `action` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_id` bigint unsigned NOT NULL,
  `old_values` json DEFAULT NULL,
  `new_values` json DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_audit_logs_entity` (`entity_type`,`entity_id`,`created_at`),
  KEY `idx_audit_logs_user` (`user_id`,`created_at`),
  CONSTRAINT `fk_audit_logs_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `booking_price_snapshots`;
CREATE TABLE `booking_price_snapshots` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `session_id` bigint unsigned NOT NULL,
  `game_price_id` bigint unsigned DEFAULT NULL,
  `promotion_id` bigint unsigned DEFAULT NULL,
  `original_base_price` bigint unsigned NOT NULL,
  `discount_amount` bigint unsigned NOT NULL DEFAULT '0',
  `final_price` bigint unsigned NOT NULL,
  `required_deposit_per_player` bigint unsigned NOT NULL DEFAULT '50000',
  `total_deposit_expected` bigint unsigned NOT NULL,
  `remaining_balance_expected` bigint unsigned NOT NULL,
  `snapshot_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_booking_price_snapshots_session` (`session_id`),
  KEY `idx_bps_game_price` (`game_price_id`),
  KEY `idx_bps_promotion` (`promotion_id`),
  CONSTRAINT `fk_bps_game_price` FOREIGN KEY (`game_price_id`) REFERENCES `game_prices` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_bps_promotion` FOREIGN KEY (`promotion_id`) REFERENCES `promotions` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_bps_session` FOREIGN KEY (`session_id`) REFERENCES `sessions` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `chk_bps_amounts` CHECK (((`original_base_price` >= 0) and (`discount_amount` >= 0) and (`final_price` >= 0) and (`required_deposit_per_player` >= 0)))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_categories_slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `categories` (`id`, `name`, `slug`, `description`, `created_at`, `updated_at`) VALUES ('1', 'Trinh thám / Suy luận', 'trinh-tham-suy-luan', 'Tập trung tìm manh mối, giải mã án mạng logic', '2026-11-20 18:02:05', '2026-11-20 18:02:05');
INSERT INTO `categories` (`id`, `name`, `slug`, `description`, `created_at`, `updated_at`) VALUES ('2', 'Kinh dị / Giật gân', 'kinh-di-giat-gan', 'Không khí u ám, yếu tố tâm linh hoặc rùng rợn', '2026-11-20 18:02:05', '2026-11-20 18:02:05');
INSERT INTO `categories` (`id`, `name`, `slug`, `description`, `created_at`, `updated_at`) VALUES ('3', 'Cổ trang / Cung đấu', 'co-trang-cung-dau', 'Bối cảnh hoàng cung, gia tộc lịch sử', '2026-11-20 18:02:05', '2026-11-20 18:02:05');
INSERT INTO `categories` (`id`, `name`, `slug`, `description`, `created_at`, `updated_at`) VALUES ('4', 'Tình cảm / Tâm lý', 'tinh-cam-tam-ly', 'Chiều sâu nhân vật, câu chuyện xúc động', '2026-11-20 18:02:05', '2026-11-20 18:02:05');
INSERT INTO `categories` (`id`, `name`, `slug`, `description`, `created_at`, `updated_at`) VALUES ('5', 'Hài hước / Giải trí', 'hai-huoc-giai-tri', 'Không khí vui vẻ, tương tác đồng đội nhẹ nhàng', '2026-11-20 18:02:05', '2026-11-20 18:02:05');

DROP TABLE IF EXISTS `dm_assignments`;
CREATE TABLE `dm_assignments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `game_room_id` bigint unsigned NOT NULL,
  `dm_profile_id` bigint unsigned NOT NULL,
  `status` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ASSIGNED',
  `assigned_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `confirmed_at` timestamp NULL DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `active_assigned_room_id` bigint unsigned GENERATED ALWAYS AS ((case when (`status` in (_utf8mb4'ASSIGNED',_utf8mb4'CONFIRMED')) then `game_room_id` else NULL end)) VIRTUAL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_active_dm_assignment` (`active_assigned_room_id`),
  KEY `idx_dm_assignments_dm_status` (`dm_profile_id`,`status`),
  KEY `idx_dm_assignments_room` (`game_room_id`),
  CONSTRAINT `fk_dm_assignments_dm` FOREIGN KEY (`dm_profile_id`) REFERENCES `dm_profiles` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_dm_assignments_room` FOREIGN KEY (`game_room_id`) REFERENCES `game_rooms` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `dm_availabilities`;
CREATE TABLE `dm_availabilities` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `dm_profile_id` bigint unsigned NOT NULL,
  `day_of_week` tinyint unsigned NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `specific_date` date DEFAULT NULL,
  `is_available` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_dm_avail_lookup` (`dm_profile_id`,`day_of_week`,`is_available`),
  CONSTRAINT `fk_dm_availabilities_dm` FOREIGN KEY (`dm_profile_id`) REFERENCES `dm_profiles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `chk_dm_avail_day` CHECK ((`day_of_week` between 0 and 6)),
  CONSTRAINT `chk_dm_avail_times` CHECK ((`start_time` < `end_time`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `dm_profiles`;
CREATE TABLE `dm_profiles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `experience_years` int unsigned NOT NULL DEFAULT '0',
  `bio` text COLLATE utf8mb4_unicode_ci,
  `rating_avg` decimal(3,2) NOT NULL DEFAULT '5.00',
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ACTIVE',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_dm_profiles_user_id` (`user_id`),
  CONSTRAINT `fk_dm_profiles_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `chk_dm_rating` CHECK (((`rating_avg` >= 0.00) and (`rating_avg` <= 5.00)))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `game_category_assignments`;
CREATE TABLE `game_category_assignments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `game_id` bigint unsigned NOT NULL,
  `category_id` bigint unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_game_category_assignments` (`game_id`,`category_id`),
  KEY `idx_gca_category_id` (`category_id`),
  CONSTRAINT `fk_gca_categories` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_gca_games` FOREIGN KEY (`game_id`) REFERENCES `games` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `game_configurations`;
CREATE TABLE `game_configurations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `game_id` bigint unsigned NOT NULL,
  `config_key` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `config_value` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_game_configurations_key` (`game_id`,`config_key`),
  CONSTRAINT `fk_game_configurations_games` FOREIGN KEY (`game_id`) REFERENCES `games` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `game_prices`;
CREATE TABLE `game_prices` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `game_id` bigint unsigned NOT NULL,
  `price_tier_name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `day_type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ALL',
  `time_from` time NOT NULL,
  `time_to` time NOT NULL,
  `base_price_per_player` bigint unsigned NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_game_prices_lookup` (`game_id`,`day_type`,`time_from`,`time_to`),
  CONSTRAINT `fk_game_prices_games` FOREIGN KEY (`game_id`) REFERENCES `games` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `chk_game_prices_amount` CHECK ((`base_price_per_player` >= 0)),
  CONSTRAINT `chk_game_prices_times` CHECK ((`time_from` < `time_to`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `game_room_status_histories`;
CREATE TABLE `game_room_status_histories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `game_room_id` bigint unsigned NOT NULL,
  `changed_by_user_id` bigint unsigned DEFAULT NULL,
  `from_status` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `to_status` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reason` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_grsh_room_created` (`game_room_id`,`created_at`),
  KEY `fk_grsh_user` (`changed_by_user_id`),
  CONSTRAINT `fk_grsh_room` FOREIGN KEY (`game_room_id`) REFERENCES `game_rooms` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_grsh_user` FOREIGN KEY (`changed_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `game_rooms`;
CREATE TABLE `game_rooms` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `room_code` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `creator_id` bigint unsigned NOT NULL,
  `game_id` bigint unsigned NOT NULL,
  `title` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `min_players` int unsigned NOT NULL DEFAULT '4',
  `max_players` int unsigned NOT NULL DEFAULT '8',
  `status` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'FORMING',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `formation_deadline` timestamp NOT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `cancel_reason` text COLLATE utf8mb4_unicode_ci,
  `version` bigint unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_game_rooms_code` (`room_code`),
  KEY `idx_game_rooms_creator_active` (`creator_id`,`is_active`),
  KEY `idx_game_rooms_game_status` (`game_id`,`status`),
  KEY `idx_game_rooms_status_deadline` (`status`,`formation_deadline`),
  CONSTRAINT `fk_game_rooms_creator` FOREIGN KEY (`creator_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_game_rooms_games` FOREIGN KEY (`game_id`) REFERENCES `games` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `chk_game_rooms_players` CHECK (((`min_players` > 0) and (`max_players` >= `min_players`)))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `game_rules`;
CREATE TABLE `game_rules` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `game_id` bigint unsigned NOT NULL,
  `rule_name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `rule_content` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `display_order` int unsigned NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_game_rules_game_order` (`game_id`,`display_order`),
  CONSTRAINT `fk_game_rules_games` FOREIGN KEY (`game_id`) REFERENCES `games` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `games`;
CREATE TABLE `games` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `min_players` int unsigned NOT NULL DEFAULT '4',
  `max_players` int unsigned NOT NULL DEFAULT '8',
  `duration_minutes` int unsigned NOT NULL DEFAULT '240',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_games_slug` (`slug`),
  KEY `idx_games_status_players` (`is_active`,`min_players`,`max_players`),
  CONSTRAINT `chk_games_duration` CHECK ((`duration_minutes` > 0)),
  CONSTRAINT `chk_games_players` CHECK (((`min_players` > 0) and (`max_players` >= `min_players`)))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `notification_recipient_states`;
CREATE TABLE `notification_recipient_states` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `notification_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT '0',
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_notification_recipient` (`notification_id`,`user_id`),
  KEY `idx_nrs_user_unread` (`user_id`,`is_read`,`created_at`),
  CONSTRAINT `fk_nrs_notification` FOREIGN KEY (`notification_id`) REFERENCES `notifications` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_nrs_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `sender_id` bigint unsigned DEFAULT NULL,
  `title` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `notification_type` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` json DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_notifications_created_at` (`created_at`),
  KEY `idx_notifications_sender` (`sender_id`),
  CONSTRAINT `fk_notifications_sender` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `participant_leave_requests`;
CREATE TABLE `participant_leave_requests` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `room_participant_id` bigint unsigned NOT NULL,
  `game_room_id` bigint unsigned NOT NULL,
  `reviewed_by_user_id` bigint unsigned DEFAULT NULL,
  `reason` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PENDING',
  `requested_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `pending_participant_id` bigint unsigned GENERATED ALWAYS AS ((case when (`status` = _utf8mb4'PENDING') then `room_participant_id` else NULL end)) VIRTUAL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pending_leave_request` (`pending_participant_id`),
  KEY `idx_plr_room_status` (`game_room_id`,`status`),
  KEY `idx_plr_participant` (`room_participant_id`),
  KEY `fk_plr_reviewer` (`reviewed_by_user_id`),
  CONSTRAINT `fk_plr_participant` FOREIGN KEY (`room_participant_id`) REFERENCES `room_participants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_plr_reviewer` FOREIGN KEY (`reviewed_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_plr_room` FOREIGN KEY (`game_room_id`) REFERENCES `game_rooms` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `participant_status_histories`;
CREATE TABLE `participant_status_histories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `room_participant_id` bigint unsigned NOT NULL,
  `from_status` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `to_status` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reason` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_psh_participant_created` (`room_participant_id`,`created_at`),
  CONSTRAINT `fk_psh_participant` FOREIGN KEY (`room_participant_id`) REFERENCES `room_participants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `payment_attempts`;
CREATE TABLE `payment_attempts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `player_payment_id` bigint unsigned NOT NULL,
  `attempt_number` int unsigned NOT NULL DEFAULT '1',
  `idempotency_key` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payment_method` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'SIMULATED',
  `amount` bigint unsigned NOT NULL,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'INITIATED',
  `gateway_request` json DEFAULT NULL,
  `gateway_response` json DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_payment_attempts_idempotency` (`idempotency_key`),
  KEY `idx_payment_attempts_payment_status` (`player_payment_id`,`status`),
  CONSTRAINT `fk_payment_attempts_payment` FOREIGN KEY (`player_payment_id`) REFERENCES `player_payments` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `chk_payment_attempts_amount` CHECK ((`amount` > 0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `payment_transactions`;
CREATE TABLE `payment_transactions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `payment_attempt_id` bigint unsigned NOT NULL,
  `transaction_reference` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` bigint unsigned NOT NULL,
  `currency` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'VND',
  `transacted_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `metadata` json DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_payment_transactions_attempt` (`payment_attempt_id`),
  UNIQUE KEY `uq_payment_transactions_reference` (`transaction_reference`),
  CONSTRAINT `fk_payment_transactions_attempt` FOREIGN KEY (`payment_attempt_id`) REFERENCES `payment_attempts` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `chk_payment_transactions_amount` CHECK ((`amount` > 0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `phinxlog`;
CREATE TABLE `phinxlog` (
  `version` bigint NOT NULL,
  `migration_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `start_time` timestamp NULL DEFAULT NULL,
  `end_time` timestamp NULL DEFAULT NULL,
  `breakpoint` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `phinxlog` (`version`, `migration_name`, `start_time`, `end_time`, `breakpoint`) VALUES ('20261120000100', 'CreateIdentityTables', '2026-11-20 18:01:32', '2026-11-20 18:01:32', '0');
INSERT INTO `phinxlog` (`version`, `migration_name`, `start_time`, `end_time`, `breakpoint`) VALUES ('20261120000200', 'CreateGameTables', '2026-11-20 18:01:32', '2026-11-20 18:01:32', '0');
INSERT INTO `phinxlog` (`version`, `migration_name`, `start_time`, `end_time`, `breakpoint`) VALUES ('20261120000300', 'CreateGameroomTables', '2026-11-20 18:01:32', '2026-11-20 18:01:32', '0');
INSERT INTO `phinxlog` (`version`, `migration_name`, `start_time`, `end_time`, `breakpoint`) VALUES ('20261120000400', 'CreateVenueAndSchedulingTables', '2026-11-20 18:01:32', '2026-11-20 18:01:32', '0');
INSERT INTO `phinxlog` (`version`, `migration_name`, `start_time`, `end_time`, `breakpoint`) VALUES ('20261120000500', 'CreatePricingTables', '2026-11-20 18:01:32', '2026-11-20 18:01:32', '0');
INSERT INTO `phinxlog` (`version`, `migration_name`, `start_time`, `end_time`, `breakpoint`) VALUES ('20261120000600', 'CreatePaymentTables', '2026-11-20 18:01:32', '2026-11-20 18:01:32', '0');
INSERT INTO `phinxlog` (`version`, `migration_name`, `start_time`, `end_time`, `breakpoint`) VALUES ('20261120000700', 'CreateNotificationTables', '2026-11-20 18:01:32', '2026-11-20 18:01:32', '0');
INSERT INTO `phinxlog` (`version`, `migration_name`, `start_time`, `end_time`, `breakpoint`) VALUES ('20261120000800', 'AddConstraintsAndIndexes', '2026-11-20 18:01:32', '2026-11-20 18:01:32', '0');

DROP TABLE IF EXISTS `physical_room_reservations`;
CREATE TABLE `physical_room_reservations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `physical_room_id` bigint unsigned NOT NULL,
  `session_id` bigint unsigned NOT NULL,
  `reservation_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'RESERVED',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `active_session_id` bigint unsigned GENERATED ALWAYS AS ((case when (`status` in (_utf8mb4'RESERVED',_utf8mb4'CONFIRMED')) then `session_id` else NULL end)) VIRTUAL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_session_active_reservation` (`active_session_id`),
  KEY `idx_prr_schedule_check` (`physical_room_id`,`reservation_date`,`status`,`start_time`,`end_time`),
  KEY `idx_prr_session` (`session_id`),
  KEY `idx_prr_overlap_covering` (`physical_room_id`,`reservation_date`,`status`,`start_time`,`end_time`),
  CONSTRAINT `fk_prr_physical_room` FOREIGN KEY (`physical_room_id`) REFERENCES `physical_rooms` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_prr_session` FOREIGN KEY (`session_id`) REFERENCES `sessions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `chk_prr_times` CHECK ((`start_time` < `end_time`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `physical_rooms`;
CREATE TABLE `physical_rooms` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `capacity` int unsigned NOT NULL DEFAULT '8',
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'AVAILABLE',
  `description` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_physical_rooms_code` (`code`),
  CONSTRAINT `chk_physical_rooms_capacity` CHECK ((`capacity` > 0))
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `physical_rooms` (`id`, `code`, `name`, `capacity`, `status`, `description`, `created_at`, `updated_at`) VALUES ('1', 'ROOM_01', 'Phòng Án Mạng 01', '8', 'AVAILABLE', 'Phòng chơi tiêu chuẩn phong cách cổ điển Châu Âu', '2026-11-20 18:02:05', '2026-11-20 18:02:05');
INSERT INTO `physical_rooms` (`id`, `code`, `name`, `capacity`, `status`, `description`, `created_at`, `updated_at`) VALUES ('2', 'ROOM_02', 'Phòng Án Mạng 02', '8', 'AVAILABLE', 'Phòng chơi phong cách Á Đông truyền thống', '2026-11-20 18:02:05', '2026-11-20 18:02:05');
INSERT INTO `physical_rooms` (`id`, `code`, `name`, `capacity`, `status`, `description`, `created_at`, `updated_at`) VALUES ('3', 'ROOM_VIP', 'Phòng VIP Hoàng Gia', '10', 'AVAILABLE', 'Phòng lớn chuyên dụng kịch bản đông người', '2026-11-20 18:02:05', '2026-11-20 18:02:05');

DROP TABLE IF EXISTS `player_payments`;
CREATE TABLE `player_payments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `room_participant_id` bigint unsigned NOT NULL,
  `session_id` bigint unsigned NOT NULL,
  `payment_type` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount_due` bigint unsigned NOT NULL,
  `amount_paid` bigint unsigned NOT NULL DEFAULT '0',
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PENDING',
  `deadline` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_player_payments_participant` (`room_participant_id`,`status`),
  KEY `idx_player_payments_session` (`session_id`,`payment_type`),
  CONSTRAINT `fk_player_payments_participant` FOREIGN KEY (`room_participant_id`) REFERENCES `room_participants` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_player_payments_session` FOREIGN KEY (`session_id`) REFERENCES `sessions` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `chk_player_payments_amounts` CHECK (((`amount_due` >= 0) and (`amount_paid` >= 0)))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `promotions`;
CREATE TABLE `promotions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `discount_type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `discount_value` bigint unsigned NOT NULL,
  `min_participants` int unsigned NOT NULL DEFAULT '1',
  `valid_from` timestamp NOT NULL,
  `valid_to` timestamp NOT NULL,
  `non_stackable` tinyint(1) NOT NULL DEFAULT '1',
  `usage_limit` int unsigned DEFAULT NULL,
  `times_used` int unsigned NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_promotions_code` (`code`),
  CONSTRAINT `chk_promotions_dates` CHECK ((`valid_from` <= `valid_to`)),
  CONSTRAINT `chk_promotions_value` CHECK ((`discount_value` >= 0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `refund_attempts`;
CREATE TABLE `refund_attempts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `refund_id` bigint unsigned NOT NULL,
  `attempt_number` int unsigned NOT NULL DEFAULT '1',
  `idempotency_key` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` bigint unsigned NOT NULL,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'INITIATED',
  `error_message` text COLLATE utf8mb4_unicode_ci,
  `executed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_refund_attempts_idempotency` (`idempotency_key`),
  KEY `idx_refund_attempts_refund` (`refund_id`),
  CONSTRAINT `fk_refund_attempts_refund` FOREIGN KEY (`refund_id`) REFERENCES `refunds` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `chk_refund_attempts_amount` CHECK ((`amount` > 0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `refunds`;
CREATE TABLE `refunds` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `player_payment_id` bigint unsigned NOT NULL,
  `room_participant_id` bigint unsigned NOT NULL,
  `processed_by_user_id` bigint unsigned DEFAULT NULL,
  `reason` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `refund_type` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'FULL',
  `refund_amount` bigint unsigned NOT NULL,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PENDING',
  `processed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_refunds_payment_status` (`player_payment_id`,`status`),
  KEY `idx_refunds_participant` (`room_participant_id`),
  KEY `idx_refunds_processor` (`processed_by_user_id`),
  CONSTRAINT `fk_refunds_participant` FOREIGN KEY (`room_participant_id`) REFERENCES `room_participants` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_refunds_payment` FOREIGN KEY (`player_payment_id`) REFERENCES `player_payments` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_refunds_processor` FOREIGN KEY (`processed_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `chk_refunds_amount` CHECK ((`refund_amount` > 0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `replacement_windows`;
CREATE TABLE `replacement_windows` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `game_room_id` bigint unsigned NOT NULL,
  `original_participant_id` bigint unsigned NOT NULL,
  `replacement_participant_id` bigint unsigned DEFAULT NULL,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'OPEN',
  `expires_at` timestamp NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_rw_room_status` (`game_room_id`,`status`,`expires_at`),
  KEY `idx_rw_replacement_id` (`replacement_participant_id`),
  KEY `fk_replacement_windows_orig` (`original_participant_id`),
  CONSTRAINT `fk_replacement_windows_orig` FOREIGN KEY (`original_participant_id`) REFERENCES `room_participants` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_replacement_windows_repl` FOREIGN KEY (`replacement_participant_id`) REFERENCES `room_participants` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_replacement_windows_room` FOREIGN KEY (`game_room_id`) REFERENCES `game_rooms` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `roles`;
CREATE TABLE `roles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `label` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_roles_name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `roles` (`id`, `name`, `label`, `description`, `created_at`, `updated_at`) VALUES ('1', 'ADMIN', 'Quản trị viên', 'Toàn quyền quản trị và điều phối hệ thống', '2026-11-20 18:02:05', '2026-11-20 18:02:21');
INSERT INTO `roles` (`id`, `name`, `label`, `description`, `created_at`, `updated_at`) VALUES ('2', 'DM', 'Quản trò (Dungeon Master)', 'Quản lý phiên chơi, kịch bản và dẫn truyện', '2026-11-20 18:02:05', '2026-11-20 18:02:21');
INSERT INTO `roles` (`id`, `name`, `label`, `description`, `created_at`, `updated_at`) VALUES ('3', 'PLAYER', 'Người chơi', 'Người dùng tham gia ghép đội, đặt phòng và chơi game', '2026-11-20 18:02:05', '2026-11-20 18:02:21');

DROP TABLE IF EXISTS `room_participants`;
CREATE TABLE `room_participants` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `game_room_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `role_in_room` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PLAYER',
  `status` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'JOINED',
  `deposit_amount` bigint unsigned NOT NULL DEFAULT '50000',
  `remaining_amount` bigint unsigned NOT NULL DEFAULT '0',
  `deposit_deadline` timestamp NULL DEFAULT NULL,
  `joined_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `left_at` timestamp NULL DEFAULT NULL,
  `cancel_reason` text COLLATE utf8mb4_unicode_ci,
  `version` bigint unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `active_user_id` bigint unsigned GENERATED ALWAYS AS ((case when (`status` in (_utf8mb4'JOINED',_utf8mb4'DEPOSIT_PENDING',_utf8mb4'DEPOSITED',_utf8mb4'REMAINING_PENDING',_utf8mb4'FULLY_PAID',_utf8mb4'LEAVE_REQUESTED')) then `user_id` else NULL end)) VIRTUAL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_room_active_participant` (`game_room_id`,`active_user_id`),
  KEY `idx_participants_user` (`user_id`,`status`),
  KEY `idx_participants_room_status` (`game_room_id`,`status`),
  CONSTRAINT `fk_room_participants_room` FOREIGN KEY (`game_room_id`) REFERENCES `game_rooms` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_room_participants_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `schedule_proposals`;
CREATE TABLE `schedule_proposals` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `game_room_id` bigint unsigned NOT NULL,
  `proposed_by_user_id` bigint unsigned NOT NULL,
  `proposed_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PROPOSED',
  `deadline` timestamp NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `proposed_game_room_id` bigint unsigned GENERATED ALWAYS AS ((case when (`status` = _utf8mb4'PROPOSED') then `game_room_id` else NULL end)) VIRTUAL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_active_proposed_schedule` (`proposed_game_room_id`),
  KEY `idx_schedule_proposals_room_status` (`game_room_id`,`status`),
  KEY `idx_schedule_proposals_proposer` (`proposed_by_user_id`),
  CONSTRAINT `fk_schedule_proposals_room` FOREIGN KEY (`game_room_id`) REFERENCES `game_rooms` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_schedule_proposals_user` FOREIGN KEY (`proposed_by_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `chk_schedule_proposals_times` CHECK ((`start_time` < `end_time`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `schedule_responses`;
CREATE TABLE `schedule_responses` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `schedule_proposal_id` bigint unsigned NOT NULL,
  `room_participant_id` bigint unsigned NOT NULL,
  `response` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `responded_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_schedule_responses_vote` (`schedule_proposal_id`,`room_participant_id`),
  KEY `idx_schedule_responses_participant` (`room_participant_id`),
  CONSTRAINT `fk_schedule_responses_participant` FOREIGN KEY (`room_participant_id`) REFERENCES `room_participants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_schedule_responses_proposal` FOREIGN KEY (`schedule_proposal_id`) REFERENCES `schedule_proposals` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `sessions`;
CREATE TABLE `sessions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `game_room_id` bigint unsigned NOT NULL,
  `dm_assignment_id` bigint unsigned DEFAULT NULL,
  `session_code` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `scheduled_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `actual_start_time` timestamp NULL DEFAULT NULL,
  `actual_end_time` timestamp NULL DEFAULT NULL,
  `status` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'SCHEDULED',
  `cancellation_reason` text COLLATE utf8mb4_unicode_ci,
  `version` bigint unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sessions_code` (`session_code`),
  KEY `idx_sessions_room` (`game_room_id`),
  KEY `idx_sessions_dm` (`dm_assignment_id`),
  KEY `idx_sessions_date_status` (`scheduled_date`,`status`),
  CONSTRAINT `fk_sessions_dm` FOREIGN KEY (`dm_assignment_id`) REFERENCES `dm_assignments` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_sessions_room` FOREIGN KEY (`game_room_id`) REFERENCES `game_rooms` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `chk_sessions_times` CHECK ((`start_time` < `end_time`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `user_profiles`;
CREATE TABLE `user_profiles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `display_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `avatar_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bio` text COLLATE utf8mb4_unicode_ci,
  `date_of_birth` date DEFAULT NULL,
  `gender` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_profiles_user_id` (`user_id`),
  CONSTRAINT `fk_user_profiles_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `user_roles`;
CREATE TABLE `user_roles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `role_id` bigint unsigned NOT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_roles_user_role` (`user_id`,`role_id`),
  KEY `idx_user_roles_role_id` (`role_id`),
  CONSTRAINT `fk_user_roles_roles` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_user_roles_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ACTIVE',
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_email` (`email`),
  KEY `idx_users_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
