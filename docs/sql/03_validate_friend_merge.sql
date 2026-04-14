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
