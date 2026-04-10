<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260409000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Restructure compte and credit tables to simplified schema';
    }

    public function up(Schema $schema): void
    {
        // ── COMPTE ──────────────────────────────────────────────────────────────

        // 1. Rename date_ouverture → date_creation, statut → etat
        $this->addSql('ALTER TABLE compte RENAME COLUMN date_ouverture TO date_creation');
        $this->addSql('ALTER TABLE compte RENAME COLUMN statut TO etat');

        // 2. Add new column plafond_decouvert
        $this->addSql('ALTER TABLE compte ADD plafond_decouvert NUMERIC(15, 2) DEFAULT NULL');

        // 3. Tighten etat column length (was VARCHAR 50, now 20)
        $this->addSql('ALTER TABLE compte MODIFY etat VARCHAR(20) NOT NULL DEFAULT \'actif\'');

        // 4. Drop obsolete columns
        $this->addSql('ALTER TABLE compte
            DROP COLUMN devise,
            DROP COLUMN solde_initial,
            DROP COLUMN date_cloture,
            DROP COLUMN iban,
            DROP COLUMN bic,
            DROP COLUMN score_fiabilite,
            DROP COLUMN score_usage,
            DROP COLUMN created_at,
            DROP COLUMN updated_at
        ');

        // ── CREDIT ──────────────────────────────────────────────────────────────

        // 1. Drop FK on id_utilisateur (required before dropping the column)
        $this->addSql('ALTER TABLE credit DROP FOREIGN KEY FK_1CC16EFE50EAE44');
        // Drop the index that was on id_utilisateur
        $this->addSql('DROP INDEX IDX_1CC16EFE50EAE44 ON credit');

        // 2. Rename columns
        $this->addSql('ALTER TABLE credit RENAME COLUMN montant_demande TO montant');
        $this->addSql('ALTER TABLE credit RENAME COLUMN date_demande TO date_debut');
        $this->addSql('ALTER TABLE credit RENAME COLUMN statut TO status');

        // 3. Add new column mensualite
        $this->addSql('ALTER TABLE credit ADD mensualite NUMERIC(15, 2) DEFAULT NULL');

        // 4. Tighten status column length (was VARCHAR 50, now 20)
        $this->addSql('ALTER TABLE credit MODIFY status VARCHAR(20) NOT NULL DEFAULT \'en_attente\'');

        // 5. Drop obsolete columns
        $this->addSql('ALTER TABLE credit
            DROP COLUMN type_credit,
            DROP COLUMN montant_approuve,
            DROP COLUMN montant_restant,
            DROP COLUMN date_approbation,
            DROP COLUMN date_remboursement_debut,
            DROP COLUMN date_remboursement_fin,
            DROP COLUMN raison_rejet,
            DROP COLUMN created_at,
            DROP COLUMN updated_at,
            DROP COLUMN id_utilisateur
        ');
    }

    public function down(Schema $schema): void
    {
        // Restore compte
        $this->addSql('ALTER TABLE compte RENAME COLUMN date_creation TO date_ouverture');
        $this->addSql('ALTER TABLE compte RENAME COLUMN etat TO statut');
        $this->addSql('ALTER TABLE compte MODIFY statut VARCHAR(50) NOT NULL');
        $this->addSql('ALTER TABLE compte DROP COLUMN plafond_decouvert');
        $this->addSql('ALTER TABLE compte
            ADD COLUMN devise VARCHAR(10) NOT NULL DEFAULT \'TND\',
            ADD COLUMN solde_initial NUMERIC(15,2) NOT NULL DEFAULT 0,
            ADD COLUMN date_cloture DATE DEFAULT NULL,
            ADD COLUMN iban VARCHAR(50) DEFAULT NULL,
            ADD COLUMN bic VARCHAR(50) DEFAULT NULL,
            ADD COLUMN score_fiabilite INT DEFAULT NULL,
            ADD COLUMN score_usage INT DEFAULT NULL,
            ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ');

        // Restore credit
        $this->addSql('ALTER TABLE credit RENAME COLUMN montant TO montant_demande');
        $this->addSql('ALTER TABLE credit RENAME COLUMN date_debut TO date_demande');
        $this->addSql('ALTER TABLE credit RENAME COLUMN status TO statut');
        $this->addSql('ALTER TABLE credit MODIFY statut VARCHAR(50) NOT NULL');
        $this->addSql('ALTER TABLE credit DROP COLUMN mensualite');
        $this->addSql('ALTER TABLE credit
            ADD COLUMN type_credit VARCHAR(50) NOT NULL DEFAULT \'personnel\',
            ADD COLUMN montant_approuve NUMERIC(15,2) DEFAULT NULL,
            ADD COLUMN montant_restant NUMERIC(15,2) DEFAULT NULL,
            ADD COLUMN date_approbation DATE DEFAULT NULL,
            ADD COLUMN date_remboursement_debut DATE DEFAULT NULL,
            ADD COLUMN date_remboursement_fin DATE DEFAULT NULL,
            ADD COLUMN raison_rejet LONGTEXT DEFAULT NULL,
            ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            ADD COLUMN id_utilisateur BIGINT NOT NULL DEFAULT 1
        ');
        $this->addSql('ALTER TABLE credit ADD CONSTRAINT FK_1CC16EFE50EAE44 FOREIGN KEY (id_utilisateur) REFERENCES utilisateur (id)');
        $this->addSql('CREATE INDEX IDX_1CC16EFE50EAE44 ON credit (id_utilisateur)');
    }
}
