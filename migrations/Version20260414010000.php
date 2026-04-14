<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260414010000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add chat conversation and message tables for user/support messaging';
    }

    public function up(Schema $schema): void
    {
        $schemaManager = $this->connection->createSchemaManager();

        if (!$schemaManager->tablesExist(['chat_conversations'])) {
            $this->addSql('CREATE TABLE IF NOT EXISTS chat_conversations (
                id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
                user_a_id BIGINT UNSIGNED NOT NULL,
                user_b_id BIGINT UNSIGNED NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_chat_conversations_user_a (user_a_id),
                INDEX idx_chat_conversations_user_b (user_b_id),
                PRIMARY KEY(id),
                CONSTRAINT fk_chat_conversations_user_a FOREIGN KEY (user_a_id) REFERENCES users (id) ON DELETE CASCADE,
                CONSTRAINT fk_chat_conversations_user_b FOREIGN KEY (user_b_id) REFERENCES users (id) ON DELETE CASCADE
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        }

        if (!$schemaManager->tablesExist(['chat_messages'])) {
            $this->addSql('CREATE TABLE IF NOT EXISTS chat_messages (
                id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
                conversation_id BIGINT UNSIGNED NOT NULL,
                sender_id BIGINT UNSIGNED NOT NULL,
                recipient_id BIGINT UNSIGNED NOT NULL,
                body LONGTEXT NOT NULL,
                is_read TINYINT(1) NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_chat_messages_created_at (created_at),
                INDEX idx_chat_messages_conversation (conversation_id),
                INDEX idx_chat_messages_sender (sender_id),
                INDEX idx_chat_messages_recipient (recipient_id),
                PRIMARY KEY(id),
                CONSTRAINT fk_chat_messages_conversation FOREIGN KEY (conversation_id) REFERENCES chat_conversations (id) ON DELETE CASCADE,
                CONSTRAINT fk_chat_messages_sender FOREIGN KEY (sender_id) REFERENCES users (id) ON DELETE CASCADE,
                CONSTRAINT fk_chat_messages_recipient FOREIGN KEY (recipient_id) REFERENCES users (id) ON DELETE CASCADE
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS chat_messages');
        $this->addSql('DROP TABLE IF EXISTS chat_conversations');
    }
}