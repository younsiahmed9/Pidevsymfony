<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260414013000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add mail delivery audit table for Brevo traces';
    }

    public function up(Schema $schema): void
    {
        $schemaManager = $this->connection->createSchemaManager();

        if (!$schemaManager->tablesExist(['mail_delivery_log'])) {
            $this->addSql('CREATE TABLE IF NOT EXISTS mail_delivery_log (
                id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
                user_id BIGINT UNSIGNED DEFAULT NULL,
                channel VARCHAR(30) NOT NULL DEFAULT "EMAIL",
                mail_template VARCHAR(120) NOT NULL,
                recipient_email VARCHAR(190) NOT NULL,
                subject VARCHAR(255) NOT NULL,
                status VARCHAR(20) NOT NULL,
                provider VARCHAR(40) NOT NULL DEFAULT "BREVO",
                payload LONGTEXT DEFAULT NULL,
                error_message LONGTEXT DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_mail_delivery_recipient (recipient_email),
                INDEX idx_mail_delivery_status (status),
                INDEX idx_mail_delivery_created_at (created_at),
                INDEX idx_mail_delivery_user (user_id),
                PRIMARY KEY(id),
                CONSTRAINT fk_mail_delivery_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS mail_delivery_log');
    }
}