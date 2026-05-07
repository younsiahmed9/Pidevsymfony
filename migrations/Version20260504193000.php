<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260504193000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Harden document management with soft delete, archive metadata and file version tracking';
    }

    public function up(Schema $schema): void
    {
        $documentTable = $schema->getTable('document');
        foreach ([
            'mime_type' => 'ALTER TABLE document ADD mime_type VARCHAR(150) DEFAULT NULL',
            'checksum' => 'ALTER TABLE document ADD checksum VARCHAR(64) DEFAULT NULL',
            'original_filename' => 'ALTER TABLE document ADD original_filename VARCHAR(255) DEFAULT NULL',
            'current_version_number' => 'ALTER TABLE document ADD current_version_number INT DEFAULT 1 NOT NULL',
            'archived_at' => 'ALTER TABLE document ADD archived_at DATETIME DEFAULT NULL',
            'deleted_at' => 'ALTER TABLE document ADD deleted_at DATETIME DEFAULT NULL',
        ] as $column => $sql) {
            if (!$documentTable->hasColumn($column)) {
                $this->addSql($sql);
            }
        }

        if (!$schema->hasTable('document_version')) {
            $this->addSql('CREATE TABLE document_version (
            id_document_version INT AUTO_INCREMENT NOT NULL,
            document_id INT NOT NULL,
            created_by_id BIGINT UNSIGNED DEFAULT NULL,
            version_number INT NOT NULL,
            filename VARCHAR(500) NOT NULL,
            original_filename VARCHAR(255) DEFAULT NULL,
            mime_type VARCHAR(150) DEFAULT NULL,
            file_size INT DEFAULT NULL,
            checksum VARCHAR(64) DEFAULT NULL,
            change_reason VARCHAR(255) DEFAULT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_7F7B02CDC33F7837 (document_id),
            INDEX IDX_7F7B02CDB03A8386 (created_by_id),
            UNIQUE INDEX uniq_document_version_number (document_id, version_number),
            PRIMARY KEY(id_document_version)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        }

        $versionTable = $schema->getTable('document_version');
        $this->addSql('ALTER TABLE document_version MODIFY created_by_id BIGINT UNSIGNED DEFAULT NULL');

        if (!$versionTable->hasForeignKey('FK_7F7B02CDC33F7837')) {
            $this->addSql('ALTER TABLE document_version ADD CONSTRAINT FK_7F7B02CDC33F7837 FOREIGN KEY (document_id) REFERENCES document (id_document) ON DELETE CASCADE');
        }
        if (!$versionTable->hasForeignKey('FK_7F7B02CDB03A8386')) {
            $this->addSql('ALTER TABLE document_version ADD CONSTRAINT FK_7F7B02CDB03A8386 FOREIGN KEY (created_by_id) REFERENCES users (id) ON DELETE SET NULL');
        }

        $documentTable = $schema->getTable('document');
        if (!$documentTable->hasIndex('IDX_DOCUMENT_DELETED_AT')) {
            $this->addSql('CREATE INDEX IDX_DOCUMENT_DELETED_AT ON document (deleted_at)');
        }
        if (!$documentTable->hasIndex('IDX_DOCUMENT_ARCHIVED_AT')) {
            $this->addSql('CREATE INDEX IDX_DOCUMENT_ARCHIVED_AT ON document (archived_at)');
        }
        if (!$documentTable->hasIndex('IDX_DOCUMENT_CHECKSUM')) {
            $this->addSql('CREATE INDEX IDX_DOCUMENT_CHECKSUM ON document (checksum)');
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE document_version');
        $this->addSql('DROP INDEX IDX_DOCUMENT_DELETED_AT ON document');
        $this->addSql('DROP INDEX IDX_DOCUMENT_ARCHIVED_AT ON document');
        $this->addSql('DROP INDEX IDX_DOCUMENT_CHECKSUM ON document');
        $this->addSql('ALTER TABLE document DROP mime_type, DROP checksum, DROP original_filename, DROP current_version_number, DROP archived_at, DROP deleted_at');
    }
}
