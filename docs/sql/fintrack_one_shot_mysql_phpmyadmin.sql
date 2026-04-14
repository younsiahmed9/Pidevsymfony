-- FinTrack ONE-SHOT SQL (MySQL/phpMyAdmin)
-- Date: 2026-04-14
--
-- This script is intended to run in ONE import in phpMyAdmin
-- on top of an existing fintrack dump.
-- It adds only advanced integration structures:
--   1) chat_conversations
--   2) chat_messages
--   3) mail_delivery_log
--
-- Face ID is already supported through users.face_template.

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";
SET NAMES utf8mb4;

USE `fintrack`;

START TRANSACTION;

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

SELECT 'chat_conversations' AS table_name, COUNT(*) AS rows_count FROM chat_conversations
UNION ALL
SELECT 'chat_messages', COUNT(*) FROM chat_messages
UNION ALL
SELECT 'mail_delivery_log', COUNT(*) FROM mail_delivery_log;
