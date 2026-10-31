/*
 Navicat Premium Dump SQL

 Source Server         : Test Server
 Source Server Type    : MySQL
 Source Server Version : 100432 (10.4.32-MariaDB)
 Source Schema         : rayongcoop_db
*/

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- Table structure for roles
-- ----------------------------
DROP TABLE IF EXISTS `roles`;
CREATE TABLE `roles`  (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `slug` varchar(50) NOT NULL,
  `description` varchar(255) NULL DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

INSERT INTO `roles` VALUES (1, 'Super Admin', 'super_admin', 'ผู้ดูแลระบบสูงสุด', '2026-09-02 13:28:41', '2026-09-02 13:28:41');
INSERT INTO `roles` VALUES (2, 'Staff', 'staff', 'เจ้าหน้าที่', '2026-09-02 13:28:41', '2026-09-02 13:28:41');
INSERT INTO `roles` VALUES (12, 'สมาชิกสหกรณ์', 'member', 'สมาชิก', '2026-10-05 13:53:13', '2026-10-05 13:53:13');
INSERT INTO `roles` VALUES (13, 'ผู้ดูแลระบบสมาชิก', 'member_admin', 'ผู้ดูแลระบบสมาชิก', '2026-10-05 20:06:06', '2026-10-05 20:06:06');

-- ----------------------------
-- Table structure for users
-- ----------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users`  (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `uuid` varchar(36) NOT NULL,
  `name` varchar(100) NOT NULL,
  `department` varchar(191) NULL DEFAULT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `avatar` varchar(255) NULL DEFAULT NULL,
  `status` enum('active','inactive','suspended') NOT NULL DEFAULT 'active',
  `two_factor_secret` varchar(255) NULL DEFAULT NULL,
  `two_factor_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `two_factor_confirmed_at` datetime NULL DEFAULT NULL,
  `two_factor_last_used_slice` bigint NULL DEFAULT NULL,
  `session_version` int UNSIGNED NOT NULL DEFAULT 1,
  `last_login_at` datetime NULL DEFAULT NULL,
  `last_login_ip` varchar(45) NULL DEFAULT NULL,
  `created_by` bigint UNSIGNED NULL DEFAULT NULL,
  `updated_by` bigint UNSIGNED NULL DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp(),
  `deleted_at` datetime NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

INSERT INTO `users` VALUES (1, '00000000-0000-0000-0000-000000000001', 'ผู้ดูแลระบบสูงสุด (Super Admin)', NULL, 'admin', 'pharmacisttom@gmail.com', '$2y$10$N6g8rruX8Un9mFLecz9g2eGSiDVXG3Fo73fReJfdRV8Uh1JLRCJl.', NULL, 'active', NULL, 0, NULL, NULL, 1, '2026-09-29 21:59:45', '127.0.0.1', NULL, NULL, '2026-09-02 13:28:43', '2026-10-26 21:02:35', NULL);
INSERT INTO `users` VALUES (2, '3ad82cbe-1460-46fe-a966-76490ff6b5a1', 'นางสุธา วงศ์ภู่ดี', NULL, '3219900100959', 'member-00004@members.invalid', '$2y$10$tGTZlS815cBIolpwmhoOYuT0iUnTpehQOm7cnI0e3eWILISP/0SkO', NULL, 'active', NULL, 0, NULL, NULL, 1, '2026-10-05 14:14:00', '127.0.0.1', NULL, NULL, '2026-10-05 13:53:14', '2026-10-05 14:28:59', NULL);
INSERT INTO `users` VALUES (3, 'd4ecbef7-4327-46f2-a6ad-7678cd3d56e4', 'นางรัชนี พุ่มเจริญ', NULL, '3209900034158', 'member-00006@members.invalid', '$2y$10$L4VEYthMc/mY0NlOSvD/7uJgqPEAXl/3mYmRvoj2dwgCEJvha30/e', NULL, 'active', NULL, 0, NULL, NULL, 1, '2026-10-05 14:14:00', '127.0.0.1', NULL, NULL, '2026-10-05 13:53:14', '2026-10-05 14:28:59', NULL);
INSERT INTO `users` VALUES (4, '38f36b57-69bc-4637-aaf4-c9f0d51a385b', 'นางสาวอุษา อิศรางกูร ณ อยุธยา', NULL, '3210500011110', 'member-00008@members.invalid', '$2y$10$18XS/XGsbYCTgX.x2Xk9Pubesil5caFz2XcPTglTundo/nfOyEEW2', NULL, 'active', NULL, 0, NULL, NULL, 1, NULL, NULL, NULL, NULL, '2026-10-05 13:53:14', '2026-10-05 14:28:59', NULL);
INSERT INTO `users` VALUES (2510, 'ee876817-e2fe-437a-a733-988f68cf3471', 'ผู้ดูแลระบบสมาชิก', NULL, 'memberadmin', 'memberadmin@member-admin.invalid', '$2y$10$2P067tMd9iZLono/n4e.KuYA3AZV1zxsDzU5BbPQtJUCcnWNn9jwS', NULL, 'active', NULL, 0, NULL, NULL, 1, '2026-10-05 21:12:30', '127.0.0.1', NULL, NULL, '2026-10-05 20:06:28', '2026-10-05 21:12:30', NULL);

-- ----------------------------
-- Table structure for user_roles
-- ----------------------------
DROP TABLE IF EXISTS `user_roles`;
CREATE TABLE `user_roles`  (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint UNSIGNED NOT NULL,
  `role_id` bigint UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

INSERT INTO `user_roles` VALUES (1, 1, 1, '2026-09-29 21:55:32');
INSERT INTO `user_roles` VALUES (2, 2, 12, '2026-10-05 13:53:14');
INSERT INTO `user_roles` VALUES (3, 3, 12, '2026-10-05 13:53:14');
INSERT INTO `user_roles` VALUES (4, 4, 12, '2026-10-05 13:53:14');
INSERT INTO `user_roles` VALUES (5, 2510, 13, '2026-10-05 20:06:28');

-- ----------------------------
-- Table structure for members
-- ----------------------------
DROP TABLE IF EXISTS `members`;
CREATE TABLE `members`  (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `uuid` varchar(36) NOT NULL,
  `user_id` bigint UNSIGNED NULL DEFAULT NULL,
  `member_no` varchar(20) NOT NULL,
  `id_card` varchar(20) NOT NULL,
  `prefix` varchar(20) NULL DEFAULT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `department` varchar(150) NULL DEFAULT NULL,
  `position` varchar(100) NULL DEFAULT NULL,
  `phone` varchar(20) NULL DEFAULT NULL,
  `email` varchar(190) NULL DEFAULT NULL,
  `address` text NULL,
  `join_date` date NULL DEFAULT NULL,
  `avatar` varchar(255) NULL DEFAULT NULL,
  `status` enum('active','resigned','suspended') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

INSERT INTO `members` VALUES (1, '7c49182f-8ded-4514-a25c-7f8ac89c893c', 2, '00004', '3219900100959', NULL, 'นางสุธา วงศ์ภู่ดี', '', 'บำนาญรพ.ระยอง', NULL, NULL, NULL, NULL, NULL, NULL, 'active', '2026-10-05 13:53:14', '2026-10-05 13:53:14');
INSERT INTO `members` VALUES (2, 'a9ce778e-8904-4842-ae63-006654decac8', 3, '00006', '3209900034158', NULL, 'นางรัชนี พุ่มเจริญ', '', 'บำนาญสสจ.ระยอง', NULL, NULL, NULL, NULL, NULL, NULL, 'active', '2026-10-05 13:53:14', '2026-10-05 13:53:14');
INSERT INTO `members` VALUES (3, '5abdbb8b-0689-4037-a1fc-f59c555650c2', 4, '00008', '3210500011110', NULL, 'นางสาวอุษา อิศรางกูร ณ อยุธยา', '', 'บำนาญสสจ.ระยอง', NULL, NULL, NULL, NULL, NULL, NULL, 'active', '2026-10-05 13:53:14', '2026-10-05 13:53:14');

-- ----------------------------
-- Table structure for member_dividends
-- ----------------------------
DROP TABLE IF EXISTS `member_dividends`;
CREATE TABLE `member_dividends`  (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `member_id` bigint UNSIGNED NOT NULL,
  `year` smallint UNSIGNED NOT NULL,
  `details_json` longtext NOT NULL,
  `imported_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

INSERT INTO `member_dividends` VALUES (1, 1, 2567, '{\"year\":2567,\"id_card\":\"3219900100959\",\"member_no\":\"00004\",\"name\":\"นางสุธา วงศ์ภู่ดี\",\"department\":\"บำนาญรพ.ระยอง\",\"income\":{\"เงินปันผล\":\"19357.55\",\"เงินเฉลี่ยคืน\":\"0.00\",\"เงินของชำร่วย\":\"500.00\",\"เงินรางวัลสมาชิก\":\"0.00\"},\"deductions\":{\"สสธท.\":\"12040.00\",\"กสธท.2\":\"0.00\",\"กสธท.3\":\"0.00\",\"กสธท.4\":\"0.00\",\"สส.ชสอ\":\"0.00\",\"กรมบังคับคดี\":\"0.00\"},\"total_income\":\"19857.55\",\"total_deductions\":\"12040.00\",\"net\":\"7817.55\",\"received\":\"7817.55\",\"dividend_rate\":\"5.30\",\"refund_rate\":\"8.00\",\"source\":\"เงินปันผลเฉลี่ยคืนปี2567ลงเว็ป.xlsx\"}', '2026-10-05 14:01:38');
INSERT INTO `member_dividends` VALUES (2, 2, 2567, '{\"year\":2567,\"id_card\":\"3209900034158\",\"member_no\":\"00006\",\"name\":\"นางรัชนี พุ่มเจริญ\",\"department\":\"บำนาญสสจ.ระยอง\",\"income\":{\"เงินปันผล\":\"48265.09\",\"เงินเฉลี่ยคืน\":\"0.00\",\"เงินของชำร่วย\":\"500.00\",\"เงินรางวัลสมาชิก\":\"0.00\"},\"deductions\":{\"สสธท.\":\"6020.00\",\"กสธท.2\":\"0.00\",\"กสธท.3\":\"0.00\",\"กสธท.4\":\"0.00\",\"สส.ชสอ\":\"0.00\",\"กรมบังคับคดี\":\"0.00\"},\"total_income\":\"48765.09\",\"total_deductions\":\"6020.00\",\"net\":\"42745.09\",\"received\":\"42745.09\",\"dividend_rate\":\"5.30\",\"refund_rate\":\"8.00\",\"source\":\"เงินปันผลเฉลี่ยคืนปี2567ลงเว็ป.xlsx\"}', '2026-10-05 14:01:39');
INSERT INTO `member_dividends` VALUES (3, 3, 2567, '{\"year\":2567,\"id_card\":\"3210500011110\",\"member_no\":\"00008\",\"name\":\"นางสาวอุษา อิศรางกูร ณ อยุธยา\",\"department\":\"บำนาญสสจ.ระยอง\",\"income\":{\"เงินปันผล\":\"145097.48\",\"เงินเฉลี่ยคืน\":\"0.00\",\"เงินของชำร่วย\":\"500.00\",\"เงินรางวัลสมาชิก\":\"0.00\"},\"deductions\":{\"สสธท.\":\"6020.00\",\"กสธท.2\":\"0.00\",\"กสธท.3\":\"0.00\",\"กสธท.4\":\"0.00\",\"สส.ชสอ\":\"0.00\",\"กรมบังคับคดี\":\"0.00\"},\"total_income\":\"145597.48\",\"total_deductions\":\"6020.00\",\"net\":\"139577.48\",\"received\":\"139577.48\",\"dividend_rate\":\"5.30\",\"refund_rate\":\"8.00\",\"source\":\"เงินปันผลเฉลี่ยคืนปี2567ลงเว็ป.xlsx\"}', '2026-10-05 14:01:39');

INSERT INTO `member_dividends` VALUES (4, 1, 2568, '{\"year\":2568,\"id_card\":\"3219900100959\",\"member_no\":\"00004\",\"name\":\"นางสุธา วงศ์ภู่ดี\",\"department\":\"บำนาญรพ.ระยอง\",\"income\":{\"เงินปันผล\":\"21000.00\",\"เงินเฉลี่ยคืน\":\"500.00\",\"เงินของชำร่วย\":\"500.00\",\"เงินรางวัลสมาชิก\":\"0.00\"},\"deductions\":{\"สสธท.\":\"12000.00\",\"กสธท.2\":\"0.00\",\"กสธท.3\":\"0.00\",\"กสธท.4\":\"0.00\",\"สส.ชสอ\":\"0.00\",\"กรมบังคับคดี\":\"0.00\"},\"total_income\":\"22000.00\",\"total_deductions\":\"12000.00\",\"net\":\"10000.00\",\"received\":\"10000.00\",\"dividend_rate\":\"5.25\",\"refund_rate\":\"8.00\",\"source\":\"เงินปันผลเฉลี่ยคืนปี2568.xlsx\"}', '2026-10-05 14:02:00');
INSERT INTO `member_dividends` VALUES (5, 2, 2568, '{\"year\":2568,\"id_card\":\"3209900034158\",\"member_no\":\"00006\",\"name\":\"นางรัชนี พุ่มเจริญ\",\"department\":\"บำนาญสสจ.ระยอง\",\"income\":{\"เงินปันผล\":\"50000.00\",\"เงินเฉลี่ยคืน\":\"1000.00\",\"เงินของชำร่วย\":\"500.00\",\"เงินรางวัลสมาชิก\":\"0.00\"},\"deductions\":{\"สสธท.\":\"6000.00\",\"กสธท.2\":\"0.00\",\"กสธท.3\":\"0.00\",\"กสธท.4\":\"0.00\",\"สส.ชสอ\":\"0.00\",\"กรมบังคับคดี\":\"0.00\"},\"total_income\":\"51500.00\",\"total_deductions\":\"6000.00\",\"net\":\"45500.00\",\"received\":\"45500.00\",\"dividend_rate\":\"5.25\",\"refund_rate\":\"8.00\",\"source\":\"เงินปันผลเฉลี่ยคืนปี2568.xlsx\"}', '2026-10-05 14:02:00');
INSERT INTO `member_dividends` VALUES (6, 3, 2568, '{\"year\":2568,\"id_card\":\"3210500011110\",\"member_no\":\"00008\",\"name\":\"นางสาวอุษา อิศรางกูร ณ อยุธยา\",\"department\":\"บำนาญสสจ.ระยอง\",\"income\":{\"เงินปันผล\":\"150000.00\",\"เงินเฉลี่ยคืน\":\"2000.00\",\"เงินของชำร่วย\":\"500.00\",\"เงินรางวัลสมาชิก\":\"0.00\"},\"deductions\":{\"สสธท.\":\"6000.00\",\"กสธท.2\":\"0.00\",\"กสธท.3\":\"0.00\",\"กสธท.4\":\"0.00\",\"สส.ชสอ\":\"0.00\",\"กรมบังคับคดี\":\"0.00\"},\"total_income\":\"152500.00\",\"total_deductions\":\"6000.00\",\"net\":\"146500.00\",\"received\":\"146500.00\",\"dividend_rate\":\"5.25\",\"refund_rate\":\"8.00\",\"source\":\"เงินปันผลเฉลี่ยคืนปี2568.xlsx\"}', '2026-10-05 14:02:00');
