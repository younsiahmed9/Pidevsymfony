-- ============================================================
-- FinTrack – Schema fix for compte & credit tables
-- Run this in phpMyAdmin → fintrack database → SQL tab
-- ============================================================

-- ── COMPTE ─────────────────────────────────────────────────

ALTER TABLE compte RENAME COLUMN date_ouverture TO date_creation;
ALTER TABLE compte RENAME COLUMN statut TO etat;
ALTER TABLE compte MODIFY etat VARCHAR(20) NOT NULL DEFAULT 'actif';
ALTER TABLE compte ADD COLUMN plafond_decouvert NUMERIC(15, 2) DEFAULT NULL;

ALTER TABLE compte
    DROP COLUMN devise,
    DROP COLUMN solde_initial,
    DROP COLUMN date_cloture,
    DROP COLUMN iban,
    DROP COLUMN bic,
    DROP COLUMN score_fiabilite,
    DROP COLUMN score_usage,
    DROP COLUMN created_at,
    DROP COLUMN updated_at;

-- ── CREDIT ─────────────────────────────────────────────────

ALTER TABLE credit DROP FOREIGN KEY FK_1CC16EFE50EAE44;
DROP INDEX IDX_1CC16EFE50EAE44 ON credit;

ALTER TABLE credit RENAME COLUMN montant_demande TO montant;
ALTER TABLE credit RENAME COLUMN date_demande TO date_debut;
ALTER TABLE credit RENAME COLUMN statut TO status;
ALTER TABLE credit MODIFY status VARCHAR(20) NOT NULL DEFAULT 'en_attente';
ALTER TABLE credit ADD COLUMN mensualite NUMERIC(15, 2) DEFAULT NULL;

ALTER TABLE credit
    DROP COLUMN type_credit,
    DROP COLUMN montant_approuve,
    DROP COLUMN montant_restant,
    DROP COLUMN date_approbation,
    DROP COLUMN date_remboursement_debut,
    DROP COLUMN date_remboursement_fin,
    DROP COLUMN raison_rejet,
    DROP COLUMN created_at,
    DROP COLUMN updated_at,
    DROP COLUMN id_utilisateur;

-- ── Mark migration as executed so Doctrine doesn't re-run it ──

INSERT IGNORE INTO doctrine_migration_versions (version, executed_at, execution_time)
VALUES ('DoctrineMigrations\\Version20260409000001', NOW(), 0);

-- Done!
