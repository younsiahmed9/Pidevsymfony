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
