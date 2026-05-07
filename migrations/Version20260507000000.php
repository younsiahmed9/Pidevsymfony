<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260507000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create signature, archive_record, signatory, signature_policy, and draft tables for document management';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE draft (
            id_draft INT AUTO_INCREMENT NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            title VARCHAR(255) NOT NULL,
            type_document VARCHAR(100) DEFAULT NULL,
            chemin_fichier VARCHAR(500) DEFAULT NULL,
            mime_type VARCHAR(150) DEFAULT NULL,
            original_filename VARCHAR(255) DEFAULT NULL,
            file_size INT DEFAULT NULL,
            checksum VARCHAR(64) DEFAULT NULL,
            description LONGTEXT DEFAULT NULL,
            is_ready TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            id_categorie INT DEFAULT NULL,
            id_dossier INT DEFAULT NULL,
            INDEX idx_draft_user (user_id),
            INDEX idx_draft_ready (is_ready),
            PRIMARY KEY(id_draft)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");

        $this->addSql("CREATE TABLE signature_policy (
            id_signature_policy INT AUTO_INCREMENT NOT NULL,
            name VARCHAR(100) NOT NULL,
            type VARCHAR(50) NOT NULL,
            description LONGTEXT DEFAULT NULL,
            requires_human_validation TINYINT(1) NOT NULL DEFAULT 0,
            require_2fa TINYINT(1) NOT NULL DEFAULT 0,
            provider VARCHAR(100) DEFAULT NULL,
            is_default TINYINT(1) NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            config JSON DEFAULT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY(id_signature_policy)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");

        $this->addSql("CREATE TABLE signature (
            id_signature INT AUTO_INCREMENT NOT NULL,
            document_id INT NOT NULL,
            signer_id BIGINT UNSIGNED NOT NULL,
            signature_type VARCHAR(50) NOT NULL,
            status VARCHAR(50) NOT NULL,
            signature_value VARCHAR(500) DEFAULT NULL,
            certificate_data LONGTEXT DEFAULT NULL,
            signature_proof_url VARCHAR(500) DEFAULT NULL,
            signed_at DATETIME DEFAULT NULL,
            document_hash_before VARCHAR(64) DEFAULT NULL,
            document_hash_after VARCHAR(64) DEFAULT NULL,
            signing_order INT NOT NULL DEFAULT 1,
            signature_policy VARCHAR(100) DEFAULT NULL,
            callback_url VARCHAR(500) DEFAULT NULL,
            provider_reference VARCHAR(255) DEFAULT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            rejection_reason LONGTEXT DEFAULT NULL,
            INDEX idx_signature_document (document_id),
            INDEX idx_signature_signer (signer_id),
            INDEX idx_signature_status (status),
            PRIMARY KEY(id_signature)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");

        $this->addSql("CREATE TABLE signatory (
            id_signatory INT AUTO_INCREMENT NOT NULL,
            document_id INT NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            email VARCHAR(255) DEFAULT NULL,
            signing_order INT NOT NULL DEFAULT 1,
            role VARCHAR(50) NOT NULL DEFAULT 'approver',
            status VARCHAR(50) NOT NULL DEFAULT 'pending',
            invited_at DATETIME NOT NULL,
            notified_at DATETIME DEFAULT NULL,
            signed_at DATETIME DEFAULT NULL,
            reminder_count INT NOT NULL DEFAULT 0,
            INDEX idx_signatory_document (document_id),
            INDEX idx_signatory_user (user_id),
            INDEX idx_signatory_status (status),
            PRIMARY KEY(id_signatory)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");

        $this->addSql("CREATE TABLE archive_record (
            id_archive_record INT AUTO_INCREMENT NOT NULL,
            document_id INT NOT NULL,
            archived_by_id BIGINT UNSIGNED DEFAULT NULL,
            archive_type VARCHAR(50) NOT NULL,
            archive_reason LONGTEXT DEFAULT NULL,
            retention_until DATE DEFAULT NULL,
            archived_at DATETIME NOT NULL,
            document_hash_at_archive VARCHAR(64) DEFAULT NULL,
            timestamp_token VARCHAR(500) DEFAULT NULL,
            restore_allowed TINYINT(1) NOT NULL DEFAULT 1,
            restore_count INT NOT NULL DEFAULT 0,
            restored_at DATETIME DEFAULT NULL,
            restored_by_id INT DEFAULT NULL,
            INDEX idx_archive_document (document_id),
            INDEX idx_archive_type (archive_type),
            UNIQUE INDEX uniq_archive_document (document_id),
            PRIMARY KEY(id_archive_record)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");

        $this->addSql("ALTER TABLE signature ADD CONSTRAINT FK_SIGNATURE_DOCUMENT FOREIGN KEY (document_id) REFERENCES document (id_document) ON DELETE CASCADE");
        $this->addSql("ALTER TABLE signature ADD CONSTRAINT FK_SIGNATURE_SIGNER FOREIGN KEY (signer_id) REFERENCES users (id) ON DELETE CASCADE");

        $this->addSql("ALTER TABLE signatory ADD CONSTRAINT FK_SIGNATORY_DOCUMENT FOREIGN KEY (document_id) REFERENCES document (id_document) ON DELETE CASCADE");
        $this->addSql("ALTER TABLE signatory ADD CONSTRAINT FK_SIGNATORY_USER FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE");

        $this->addSql("ALTER TABLE archive_record ADD CONSTRAINT FK_ARCHIVE_DOCUMENT FOREIGN KEY (document_id) REFERENCES document (id_document) ON DELETE CASCADE");
        $this->addSql("ALTER TABLE archive_record ADD CONSTRAINT FK_ARCHIVE_USER FOREIGN KEY (archived_by_id) REFERENCES users (id) ON DELETE SET NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE archive_record DROP FOREIGN KEY FK_ARCHIVE_USER");
        $this->addSql("ALTER TABLE archive_record DROP FOREIGN KEY FK_ARCHIVE_DOCUMENT");
        $this->addSql("ALTER TABLE signatory DROP FOREIGN KEY FK_SIGNATORY_USER");
        $this->addSql("ALTER TABLE signatory DROP FOREIGN KEY FK_SIGNATORY_DOCUMENT");
        $this->addSql("ALTER TABLE signature DROP FOREIGN KEY FK_SIGNATURE_SIGNER");
        $this->addSql("ALTER TABLE signature DROP FOREIGN KEY FK_SIGNATURE_DOCUMENT");
        $this->addSql("ALTER TABLE draft DROP FOREIGN KEY FK_DRAFT_DOSSIER");
        $this->addSql("ALTER TABLE draft DROP FOREIGN KEY FK_DRAFT_CATEGORIE");
        $this->addSql("ALTER TABLE draft DROP FOREIGN KEY FK_DRAFT_USER");

        $this->addSql("DROP TABLE archive_record");
        $this->addSql("DROP TABLE signatory");
        $this->addSql("DROP TABLE signature");
        $this->addSql("DROP TABLE signature_policy");
        $this->addSql("DROP TABLE draft");
    }
}