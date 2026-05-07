<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260411153000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add transfer fee, execution, exchange cache and risk logging tables';
    }

    public function up(Schema $schema): void
    {
        $schemaManager = $this->connection->createSchemaManager();

        if (!$schemaManager->tablesExist(['transfer_fee_rule'])) {
            $this->connection->executeStatement('CREATE TABLE IF NOT EXISTS transfer_fee_rule (
            id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
            name VARCHAR(100) NOT NULL,
            transfer_type VARCHAR(20) NOT NULL DEFAULT "TRANSFERT",
            window_days INT NOT NULL DEFAULT 5,
            min_count INT NOT NULL DEFAULT 4,
            base_fee_rate NUMERIC(8, 5) NOT NULL DEFAULT 0.01000,
            discounted_fee_rate NUMERIC(8, 5) NOT NULL DEFAULT 0.00500,
            fixed_fee NUMERIC(15, 2) NOT NULL DEFAULT 0.00,
            currency VARCHAR(10) NOT NULL DEFAULT "TND",
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            priority INT NOT NULL DEFAULT 100,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        }

        if (!$schemaManager->tablesExist(['transfer_fee_event'])) {
            $this->connection->executeStatement('CREATE TABLE IF NOT EXISTS transfer_fee_event (
            id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
            transaction_id BIGINT DEFAULT NULL,
            virement_programme_id BIGINT DEFAULT NULL,
            source_card_id BIGINT NOT NULL,
            dest_card_id BIGINT NOT NULL,
            amount NUMERIC(15, 2) NOT NULL,
            currency VARCHAR(10) NOT NULL,
            base_fee_rate NUMERIC(8, 5) NOT NULL,
            applied_fee_rate NUMERIC(8, 5) NOT NULL,
            fixed_fee NUMERIC(15, 2) NOT NULL DEFAULT 0.00,
            fee_amount NUMERIC(15, 2) NOT NULL,
            transfer_count_in_window INT NOT NULL DEFAULT 0,
            window_days INT NOT NULL DEFAULT 5,
            rule_name VARCHAR(100) DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_fee_event_tx (transaction_id),
            INDEX idx_fee_event_vp (virement_programme_id),
            INDEX idx_fee_event_source_date (source_card_id, created_at),
            PRIMARY KEY(id),
            CONSTRAINT fk_fee_event_tx FOREIGN KEY (transaction_id) REFERENCES `transaction` (id) ON DELETE SET NULL,
            CONSTRAINT fk_fee_event_vp FOREIGN KEY (virement_programme_id) REFERENCES virement_programme (id) ON DELETE SET NULL,
            CONSTRAINT fk_fee_event_source_card FOREIGN KEY (source_card_id) REFERENCES carte_virtuelle (id),
            CONSTRAINT fk_fee_event_dest_card FOREIGN KEY (dest_card_id) REFERENCES carte_virtuelle (id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        }

        if (!$schemaManager->tablesExist(['transfer_execution_log'])) {
            $this->connection->executeStatement('CREATE TABLE IF NOT EXISTS transfer_execution_log (
            id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
            virement_programme_id BIGINT NOT NULL,
            execution_type VARCHAR(20) NOT NULL DEFAULT "AUTO",
            status VARCHAR(20) NOT NULL DEFAULT "SUCCESS",
            scheduled_for DATETIME NOT NULL,
            executed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            transaction_id BIGINT DEFAULT NULL,
            amount NUMERIC(15, 2) NOT NULL,
            currency VARCHAR(10) NOT NULL,
            fee_amount NUMERIC(15, 2) NOT NULL DEFAULT 0.00,
            error_code VARCHAR(50) DEFAULT NULL,
            error_message LONGTEXT DEFAULT NULL,
            INDEX idx_exec_log_vp_date (virement_programme_id, executed_at),
            INDEX idx_exec_log_status_date (status, executed_at),
            PRIMARY KEY(id),
            CONSTRAINT fk_exec_log_vp FOREIGN KEY (virement_programme_id) REFERENCES virement_programme (id) ON DELETE CASCADE,
            CONSTRAINT fk_exec_log_tx FOREIGN KEY (transaction_id) REFERENCES `transaction` (id) ON DELETE SET NULL
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        }

        if (!$schemaManager->tablesExist(['exchange_rate_cache'])) {
            $this->connection->executeStatement('CREATE TABLE IF NOT EXISTS exchange_rate_cache (
            id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
            provider VARCHAR(50) NOT NULL,
            base_currency VARCHAR(10) NOT NULL,
            quote_currency VARCHAR(10) NOT NULL,
            rate NUMERIC(18, 8) NOT NULL,
            fetched_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            expires_at DATETIME NOT NULL,
            raw_payload JSON DEFAULT NULL,
            UNIQUE INDEX uq_provider_pair (provider, base_currency, quote_currency),
            INDEX idx_fx_expires (expires_at),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        }

        if (!$schemaManager->tablesExist(['transfer_risk_log'])) {
            $this->connection->executeStatement('CREATE TABLE IF NOT EXISTS transfer_risk_log (
            id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
            transaction_id BIGINT DEFAULT NULL,
            virement_programme_id BIGINT DEFAULT NULL,
            transfer_kind VARCHAR(20) NOT NULL,
            ip_address VARCHAR(45) DEFAULT NULL,
            country_code VARCHAR(8) DEFAULT NULL,
            country_name VARCHAR(100) DEFAULT NULL,
            city VARCHAR(120) DEFAULT NULL,
            latitude NUMERIC(10, 7) DEFAULT NULL,
            longitude NUMERIC(10, 7) DEFAULT NULL,
            risk_score INT NOT NULL DEFAULT 0,
            decision VARCHAR(20) NOT NULL DEFAULT "ALLOW",
            reason LONGTEXT DEFAULT NULL,
            provider VARCHAR(50) DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_risk_log_tx (transaction_id),
            INDEX idx_risk_log_vp (virement_programme_id),
            INDEX idx_risk_log_date (created_at),
            PRIMARY KEY(id),
            CONSTRAINT fk_risk_log_tx FOREIGN KEY (transaction_id) REFERENCES `transaction` (id) ON DELETE SET NULL,
            CONSTRAINT fk_risk_log_vp FOREIGN KEY (virement_programme_id) REFERENCES virement_programme (id) ON DELETE SET NULL
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        }

        $existingRule = $this->connection->fetchOne('SELECT COUNT(*) FROM transfer_fee_rule WHERE name = :name', [
            'name' => '4 transfers in 5 days discount',
        ]);

        if ((int) $existingRule === 0) {
            $this->addSql("INSERT INTO transfer_fee_rule
            (name, transfer_type, window_days, min_count, base_fee_rate, discounted_fee_rate, fixed_fee, currency, is_active, priority)
            VALUES
            ('4 transfers in 5 days discount', 'TRANSFERT', 5, 4, 0.01000, 0.00500, 0.00, 'TND', 1, 10)");
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS transfer_risk_log');
        $this->addSql('DROP TABLE IF EXISTS exchange_rate_cache');
        $this->addSql('DROP TABLE IF EXISTS transfer_execution_log');
        $this->addSql('DROP TABLE IF EXISTS transfer_fee_event');
        $this->addSql('DROP TABLE IF EXISTS transfer_fee_rule');
    }
}
