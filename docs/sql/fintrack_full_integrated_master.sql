-- FinTrack Full Integrated Master SQL
-- Date: 2026-04-14
-- Purpose: Build the complete integrated database (core project + advanced features)
-- DB: fintrack

-- IMPORTANT
-- 1) Import the full core dump of your project (the dump you shared: all existing tables/data).
-- 2) Then execute the advanced-features patch:
--    docs/sql/fintrack_new_tables_faceid_messaging_mailing.sql
--
-- This file documents the exact execution order.

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- Optional: create database if missing
CREATE DATABASE IF NOT EXISTS `fintrack` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `fintrack`;

-- ============================================================================
-- STEP A: CORE PROJECT DATABASE
-- ============================================================================
-- Run your full existing dump here (the complete dump you already have).
-- If you use mysql CLI, you can run:
--   mysql -u root -p fintrack < FULL_CORE_DUMP.sql
--
-- In phpMyAdmin:
--   Import FULL_CORE_DUMP.sql first.

-- ============================================================================
-- STEP B: ADVANCED FEATURES PATCH (already created in this repo)
-- ============================================================================
-- Run this after the core dump:
--   docs/sql/fintrack_new_tables_faceid_messaging_mailing.sql
--
-- It adds:
--   1) chat_conversations
--   2) chat_messages
--   3) mail_delivery_log
--
-- Face ID uses existing column:
--   users.face_template

COMMIT;
