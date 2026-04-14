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
