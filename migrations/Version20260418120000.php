<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260418120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add dedicated n8n scheduled transfer execution logs table';
    }

    public function up(Schema $schema): void
    {
        $schemaManager = $this->connection->createSchemaManager();

        if (!$schemaManager->tablesExist(['transfer_execution_log_n8n'])) {
            $this->addSql('CREATE TABLE IF NOT EXISTS transfer_execution_log_n8n (
                id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
                virement_programme_id BIGINT NOT NULL,
                execution_type VARCHAR(30) NOT NULL DEFAULT "AUTO_N8N",
                status VARCHAR(30) NOT NULL DEFAULT "SUCCESS",
                scheduled_for DATETIME NOT NULL,
                executed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                transaction_id BIGINT DEFAULT NULL,
                amount NUMERIC(15, 2) NOT NULL,
                currency VARCHAR(10) NOT NULL,
                fee_amount NUMERIC(15, 2) NOT NULL DEFAULT 0.00,
                error_code VARCHAR(50) DEFAULT NULL,
                error_message LONGTEXT DEFAULT NULL,
                payload LONGTEXT DEFAULT NULL,
                INDEX idx_exec_log_n8n_vp_date (virement_programme_id, executed_at),
                INDEX idx_exec_log_n8n_status_date (status, executed_at),
                PRIMARY KEY(id),
                CONSTRAINT fk_exec_log_n8n_vp FOREIGN KEY (virement_programme_id) REFERENCES virement_programme (id) ON DELETE CASCADE,
                CONSTRAINT fk_exec_log_n8n_tx FOREIGN KEY (transaction_id) REFERENCES `transaction` (id) ON DELETE SET NULL
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS transfer_execution_log_n8n');
    }
}
