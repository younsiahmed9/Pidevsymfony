-- ==================== BEGIN 01_extend_fintrack_schema_for_friend_features.sql ====================
-- Phase 1 (safe): extend current FinTrack schema to support friend features.
-- Target DB: fintrack (existing app uses table `utilisateur` as source of truth).
-- MariaDB 10.4+ compatible.

START TRANSACTION;

-- 1) Extend existing utilisateur table with optional profile/security fields.
ALTER TABLE utilisateur
    ADD COLUMN IF NOT EXISTS full_name VARCHAR(120) NULL AFTER prenom,
    ADD COLUMN IF NOT EXISTS profile_photo VARCHAR(512) NULL AFTER full_name,
    ADD COLUMN IF NOT EXISTS fingerprint_template MEDIUMBLOB NULL AFTER profile_photo,
    ADD COLUMN IF NOT EXISTS face_template MEDIUMBLOB NULL AFTER fingerprint_template,
    ADD COLUMN IF NOT EXISTS is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER role,
    ADD COLUMN IF NOT EXISTS legacy_user_id BIGINT NULL AFTER is_active;

-- Keep mapping stable when migrating users from friend dump.
CREATE UNIQUE INDEX IF NOT EXISTS uk_utilisateur_legacy_user_id ON utilisateur (legacy_user_id);

-- 2) Create admin profile table (1:1 with utilisateur).
CREATE TABLE IF NOT EXISTS admins (
    user_id BIGINT NOT NULL,
    admin_code VARCHAR(40) DEFAULT NULL,
    PRIMARY KEY (user_id),
    CONSTRAINT fk_admins_utilisateur FOREIGN KEY (user_id)
        REFERENCES utilisateur (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3) Create client profile table (1:1 with utilisateur).
CREATE TABLE IF NOT EXISTS clients (
    user_id BIGINT NOT NULL,
    cin VARCHAR(20) DEFAULT NULL,
    phone VARCHAR(30) DEFAULT NULL,
    PRIMARY KEY (user_id),
    CONSTRAINT fk_clients_utilisateur FOREIGN KEY (user_id)
        REFERENCES utilisateur (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4) Create email verification table.
CREATE TABLE IF NOT EXISTS email_verification (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT NOT NULL,
    token VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL DEFAULT NULL,
    used TINYINT(1) DEFAULT 0,
    PRIMARY KEY (id),
    KEY idx_email_verification_user_id (user_id),
    CONSTRAINT fk_email_verification_utilisateur FOREIGN KEY (user_id)
        REFERENCES utilisateur (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5) Create messaging table.
CREATE TABLE IF NOT EXISTS messages (
    id BIGINT NOT NULL AUTO_INCREMENT,
    sender_id BIGINT NOT NULL,
    receiver_id BIGINT NOT NULL,
    content TEXT NOT NULL,
    `timestamp` DATETIME DEFAULT CURRENT_TIMESTAMP,
    is_read TINYINT(1) DEFAULT 0,
    PRIMARY KEY (id),
    KEY idx_messages_sender_id (sender_id),
    KEY idx_messages_receiver_id (receiver_id),
    CONSTRAINT fk_messages_sender_utilisateur FOREIGN KEY (sender_id)
        REFERENCES utilisateur (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_messages_receiver_utilisateur FOREIGN KEY (receiver_id)
        REFERENCES utilisateur (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;
-- ===================== END 01_extend_fintrack_schema_for_friend_features.sql =====================

-- ==================== BEGIN 02_merge_friend_dump_data_into_fintrack.sql ====================
-- Phase 2: merge friend dump data into current FinTrack schema.
-- IMPORTANT:
-- 1) Run script 01 first.
-- 2) Import friend's dump into a SEPARATE database, for example: fintrack_friend.
-- 3) Then run this script.
-- 4) Backup DB before running.

-- This script assumes source dump exists in: fintrack_friend
-- Target database should be your current application DB (fintrack).

START TRANSACTION;

-- A) Merge users -> utilisateur.
-- NOTE: password_hash from friend app is likely NOT Symfony-compatible.
--       Imported users may need password reset before login in this app.
INSERT INTO utilisateur (
    email,
    nom,
    prenom,
    password,
    role,
    solde,
    created_at,
    updated_at,
    full_name,
    profile_photo,
    fingerprint_template,
    face_template,
    is_active,
    legacy_user_id
)
SELECT
    u.email,
    LEFT(
        CASE
            WHEN u.full_name IS NULL OR TRIM(u.full_name) = '' THEN 'User'
            WHEN INSTR(TRIM(u.full_name), ' ') = 0 THEN TRIM(u.full_name)
            ELSE TRIM(SUBSTRING(TRIM(u.full_name), INSTR(TRIM(u.full_name), ' ') + 1))
        END,
        100
    ) AS nom,
    LEFT(
        CASE
            WHEN u.full_name IS NULL OR TRIM(u.full_name) = '' THEN 'Unknown'
            ELSE SUBSTRING_INDEX(TRIM(u.full_name), ' ', 1)
        END,
        100
    ) AS prenom,
    u.password_hash,
    LOWER(u.role),
    '0.00',
    COALESCE(u.created_at, NOW()),
    COALESCE(u.updated_at, NOW()),
    u.full_name,
    u.profile_photo,
    u.fingerprint_template,
    u.face_template,
    COALESCE(u.is_active, 1),
    u.id
FROM fintrack_friend.users u
ON DUPLICATE KEY UPDATE
    full_name = VALUES(full_name),
    profile_photo = VALUES(profile_photo),
    fingerprint_template = VALUES(fingerprint_template),
    face_template = VALUES(face_template),
    is_active = VALUES(is_active),
    legacy_user_id = VALUES(legacy_user_id),
    updated_at = VALUES(updated_at);

-- B) Merge admin profiles.
INSERT INTO admins (user_id, admin_code)
SELECT
    uf.id,
    a.admin_code
FROM fintrack_friend.admins a
JOIN utilisateur uf ON uf.legacy_user_id = a.user_id
ON DUPLICATE KEY UPDATE admin_code = VALUES(admin_code);

-- C) Merge client profiles.
INSERT INTO clients (user_id, cin, phone)
SELECT
    uf.id,
    c.cin,
    c.phone
FROM fintrack_friend.clients c
JOIN utilisateur uf ON uf.legacy_user_id = c.user_id
ON DUPLICATE KEY UPDATE
    cin = VALUES(cin),
    phone = VALUES(phone);

-- D) Merge email verification records.
INSERT INTO email_verification (id, user_id, token, created_at, expires_at, used)
SELECT
    ev.id,
    uf.id,
    ev.token,
    ev.created_at,
    ev.expires_at,
    ev.used
FROM fintrack_friend.email_verification ev
JOIN utilisateur uf ON uf.legacy_user_id = ev.user_id
ON DUPLICATE KEY UPDATE
    user_id = VALUES(user_id),
    token = VALUES(token),
    created_at = VALUES(created_at),
    expires_at = VALUES(expires_at),
    used = VALUES(used);

-- E) Merge messages with sender/receiver id remapping.
INSERT INTO messages (id, sender_id, receiver_id, content, `timestamp`, is_read)
SELECT
    m.id,
    us.id,
    ur.id,
    m.content,
    m.`timestamp`,
    m.is_read
FROM fintrack_friend.messages m
JOIN utilisateur us ON us.legacy_user_id = m.sender_id
JOIN utilisateur ur ON ur.legacy_user_id = m.receiver_id
ON DUPLICATE KEY UPDATE
    sender_id = VALUES(sender_id),
    receiver_id = VALUES(receiver_id),
    content = VALUES(content),
    `timestamp` = VALUES(`timestamp`),
    is_read = VALUES(is_read);

COMMIT;

-- Optional cleanup after successful merge and validation:
-- DROP DATABASE fintrack_friend;
-- ===================== END 02_merge_friend_dump_data_into_fintrack.sql =====================

-- ==================== BEGIN 03_validate_friend_merge.sql ====================
-- Phase 3: validation checks after running scripts 01 and 02.
-- Run this on target DB (fintrack).

-- 1) High-level counts.
SELECT 'utilisateur_total' AS metric, COUNT(*) AS value FROM utilisateur
UNION ALL
SELECT 'utilisateur_legacy_mapped', COUNT(*) FROM utilisateur WHERE legacy_user_id IS NOT NULL
UNION ALL
SELECT 'admins_total', COUNT(*) FROM admins
UNION ALL
SELECT 'clients_total', COUNT(*) FROM clients
UNION ALL
SELECT 'email_verification_total', COUNT(*) FROM email_verification
UNION ALL
SELECT 'messages_total', COUNT(*) FROM messages;

-- 2) Integrity: legacy mapping should be unique (should return 0 rows).
SELECT legacy_user_id, COUNT(*) AS duplicates
FROM utilisateur
WHERE legacy_user_id IS NOT NULL
GROUP BY legacy_user_id
HAVING COUNT(*) > 1;

-- 3) Integrity: duplicate email risk check (should return 0 rows).
SELECT email, COUNT(*) AS duplicates
FROM utilisateur
GROUP BY email
HAVING COUNT(*) > 1;

-- 4) FK-like sanity checks for merged child tables (should all be 0).
SELECT 'admins_orphans' AS metric, COUNT(*) AS value
FROM admins a LEFT JOIN utilisateur u ON u.id = a.user_id
WHERE u.id IS NULL
UNION ALL
SELECT 'clients_orphans', COUNT(*)
FROM clients c LEFT JOIN utilisateur u ON u.id = c.user_id
WHERE u.id IS NULL
UNION ALL
SELECT 'email_verification_orphans', COUNT(*)
FROM email_verification ev LEFT JOIN utilisateur u ON u.id = ev.user_id
WHERE u.id IS NULL
UNION ALL
SELECT 'messages_sender_orphans', COUNT(*)
FROM messages m LEFT JOIN utilisateur u ON u.id = m.sender_id
WHERE u.id IS NULL
UNION ALL
SELECT 'messages_receiver_orphans', COUNT(*)
FROM messages m LEFT JOIN utilisateur u ON u.id = m.receiver_id
WHERE u.id IS NULL;

-- 5) Password compatibility audit hint.
-- Legacy imported users keep friend password hashes in utilisateur.password.
-- Use this list to decide forced reset policy.
SELECT id, email, legacy_user_id, role, created_at
FROM utilisateur
WHERE legacy_user_id IS NOT NULL
ORDER BY id DESC
LIMIT 100;
-- ===================== END 03_validate_friend_merge.sql =====================

-- ==================== BEGIN fintrack_new_tables_faceid_messaging_mailing.sql ====================
-- FinTrack integration additions (Face ID + Messaging + Mail history)
-- Date: 2026-04-14
-- Target DB: fintrack

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- Face ID
-- No new table required: Face ID is stored in users.face_template (MEDIUMBLOB)

-- 1) Conversations table
CREATE TABLE IF NOT EXISTS `chat_conversations` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_a_id` bigint(20) UNSIGNED NOT NULL,
  `user_b_id` bigint(20) UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_chat_conversations_user_a` (`user_a_id`),
  KEY `idx_chat_conversations_user_b` (`user_b_id`),
  CONSTRAINT `fk_chat_conversations_user_a` FOREIGN KEY (`user_a_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_chat_conversations_user_b` FOREIGN KEY (`user_b_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2) Messages table
CREATE TABLE IF NOT EXISTS `chat_messages` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `conversation_id` bigint(20) UNSIGNED NOT NULL,
  `sender_id` bigint(20) UNSIGNED NOT NULL,
  `recipient_id` bigint(20) UNSIGNED NOT NULL,
  `body` longtext NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_chat_messages_created_at` (`created_at`),
  KEY `idx_chat_messages_conversation` (`conversation_id`),
  KEY `idx_chat_messages_sender` (`sender_id`),
  KEY `idx_chat_messages_recipient` (`recipient_id`),
  CONSTRAINT `fk_chat_messages_conversation` FOREIGN KEY (`conversation_id`) REFERENCES `chat_conversations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_chat_messages_sender` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_chat_messages_recipient` FOREIGN KEY (`recipient_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3) Email delivery history table (Brevo traceability)
CREATE TABLE IF NOT EXISTS `mail_delivery_log` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `channel` varchar(30) NOT NULL DEFAULT 'EMAIL',
  `mail_template` varchar(120) NOT NULL,
  `recipient_email` varchar(190) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `status` varchar(20) NOT NULL,
  `provider` varchar(40) NOT NULL DEFAULT 'BREVO',
  `payload` longtext DEFAULT NULL,
  `error_message` longtext DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_mail_delivery_user` (`user_id`),
  KEY `idx_mail_delivery_recipient` (`recipient_email`),
  KEY `idx_mail_delivery_status` (`status`),
  KEY `idx_mail_delivery_created_at` (`created_at`),
  CONSTRAINT `fk_mail_delivery_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;
-- ===================== END fintrack_new_tables_faceid_messaging_mailing.sql =====================

-- ==================== BEGIN fintrack_full_integrated_master.sql ====================
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
-- ===================== END fintrack_full_integrated_master.sql =====================

