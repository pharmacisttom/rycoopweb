-- =========================================================================
-- RYCOOPWEB — Rollback 030: Board Term Management & Committee Architecture
-- Drops tables created by Migration 030 in safe foreign key dependency order.
-- Strictly for manual recovery / isolated testing only.
-- MUST NEVER be discovered or run automatically by forward migration runners.
-- =========================================================================

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `board_term_group_members`;
DROP TABLE IF EXISTS `board_term_groups`;
DROP TABLE IF EXISTS `board_term_members`;
DROP TABLE IF EXISTS `board_terms`;

SET FOREIGN_KEY_CHECKS = 1;
