<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260422150213 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    private function dropForeignKeyIfExists(string $table, string $foreignKey): void
    {
        $exists = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = \'FOREIGN KEY\'',
            [$table, $foreignKey],
        );

        if ($exists > 0) {
            $this->connection->executeStatement(sprintf('ALTER TABLE %s DROP FOREIGN KEY %s', $table, $foreignKey));
        }
    }

    private function dropIndexIfExists(string $table, string $index): void
    {
        $exists = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?',
            [$table, $index],
        );

        if ($exists > 0) {
            try {
                $this->connection->executeStatement(sprintf('DROP INDEX %s ON %s', $index, $table));
            } catch (\Throwable) {
            }
        }
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->dropForeignKeyIfExists('carte_virtuelle', 'fk_carte_portefeuille');
        $this->dropIndexIfExists('carte_virtuelle', 'idx_carte_type');
        $this->addSql('ALTER TABLE carte_virtuelle CHANGE solde solde NUMERIC(15, 2) DEFAULT 0 NOT NULL, CHANGE plafond plafond NUMERIC(15, 2) DEFAULT 1000 NOT NULL, CHANGE type type VARCHAR(20) DEFAULT \'NORMAL\' NOT NULL');
        $this->addSql('ALTER TABLE carte_virtuelle ADD CONSTRAINT FK_2EF4B275513EC3CA FOREIGN KEY (portefeuille_id) REFERENCES portefeuille (id)');
        $this->dropIndexIfExists('carte_virtuelle', 'uq_carte_numero');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_2EF4B275158BD4C0 ON carte_virtuelle (numero_carte)');
        $this->dropIndexIfExists('carte_virtuelle', 'idx_carte_portefeuille');
        $this->addSql('CREATE INDEX IDX_2EF4B275513EC3CA ON carte_virtuelle (portefeuille_id)');
        $this->addSql('ALTER TABLE carte_virtuelle ADD CONSTRAINT fk_carte_portefeuille FOREIGN KEY (portefeuille_id) REFERENCES portefeuille (id) ON DELETE CASCADE');
        $this->dropForeignKeyIfExists('chat_conversations', 'fk_chat_conversations_user_b');
        $this->dropForeignKeyIfExists('chat_conversations', 'fk_chat_conversations_user_a');
        $this->addSql('ALTER TABLE chat_conversations CHANGE created_at created_at DATETIME NOT NULL, CHANGE updated_at updated_at DATETIME NOT NULL');
        $this->dropIndexIfExists('chat_conversations', 'idx_chat_conversations_user_a');
        $this->addSql('CREATE INDEX IDX_5813432E415F1F91 ON chat_conversations (user_a_id)');
        $this->dropIndexIfExists('chat_conversations', 'idx_chat_conversations_user_b');
        $this->addSql('CREATE INDEX IDX_5813432E53EAB07F ON chat_conversations (user_b_id)');
        $this->addSql('ALTER TABLE chat_conversations ADD CONSTRAINT fk_chat_conversations_user_b FOREIGN KEY (user_b_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE chat_conversations ADD CONSTRAINT fk_chat_conversations_user_a FOREIGN KEY (user_a_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->dropForeignKeyIfExists('chat_messages', 'fk_chat_messages_recipient');
        $this->dropForeignKeyIfExists('chat_messages', 'fk_chat_messages_sender');
        $this->dropForeignKeyIfExists('chat_messages', 'fk_chat_messages_conversation');
        $this->addSql('ALTER TABLE chat_messages CHANGE is_read is_read TINYINT(1) NOT NULL, CHANGE created_at created_at DATETIME NOT NULL');
        $this->dropIndexIfExists('chat_messages', 'idx_chat_messages_conversation');
        $this->addSql('CREATE INDEX IDX_EF20C9A69AC0396 ON chat_messages (conversation_id)');
        $this->dropIndexIfExists('chat_messages', 'idx_chat_messages_sender');
        $this->addSql('CREATE INDEX IDX_EF20C9A6F624B39D ON chat_messages (sender_id)');
        $this->dropIndexIfExists('chat_messages', 'idx_chat_messages_recipient');
        $this->addSql('CREATE INDEX IDX_EF20C9A6E92F8F78 ON chat_messages (recipient_id)');
        $this->addSql('ALTER TABLE chat_messages ADD CONSTRAINT fk_chat_messages_recipient FOREIGN KEY (recipient_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE chat_messages ADD CONSTRAINT fk_chat_messages_sender FOREIGN KEY (sender_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE chat_messages ADD CONSTRAINT fk_chat_messages_conversation FOREIGN KEY (conversation_id) REFERENCES chat_conversations (id) ON DELETE CASCADE');
        $this->dropForeignKeyIfExists('clients', 'fk_clients_user');
        $this->dropIndexIfExists('clients', 'idx_clients_city');
        $this->addSql('ALTER TABLE clients DROP city');
        $this->addSql('ALTER TABLE clients ADD CONSTRAINT FK_C82E74A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->dropForeignKeyIfExists('mail_delivery_log', 'fk_mail_delivery_user');
        $this->addSql('ALTER TABLE mail_delivery_log CHANGE channel channel VARCHAR(30) NOT NULL, CHANGE provider provider VARCHAR(40) NOT NULL, CHANGE created_at created_at DATETIME NOT NULL');
        $this->dropIndexIfExists('mail_delivery_log', 'idx_mail_delivery_user');
        $this->addSql('CREATE INDEX IDX_50467275A76ED395 ON mail_delivery_log (user_id)');
        $this->addSql('ALTER TABLE mail_delivery_log ADD CONSTRAINT fk_mail_delivery_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL');
        $this->dropForeignKeyIfExists('portefeuille', 'fk_portefeuille_user');
        $this->addSql('ALTER TABLE portefeuille CHANGE solde_total solde_total NUMERIC(15, 2) DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE portefeuille ADD CONSTRAINT FK_2955FFFEA76ED395 FOREIGN KEY (user_id) REFERENCES users (id)');
        $this->dropIndexIfExists('portefeuille', 'idx_portefeuille_user');
        $this->addSql('CREATE INDEX IDX_2955FFFEA76ED395 ON portefeuille (user_id)');
        $this->addSql('ALTER TABLE portefeuille ADD CONSTRAINT fk_portefeuille_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->dropForeignKeyIfExists('transaction', 'fk_transaction_dest');
        $this->dropForeignKeyIfExists('transaction', 'fk_transaction_source');
        $this->dropIndexIfExists('transaction', 'idx_transaction_type');
        $this->dropIndexIfExists('transaction', 'idx_transaction_statut');
        $this->dropIndexIfExists('transaction', 'idx_transaction_date');
        $this->addSql('ALTER TABLE transaction CHANGE type type VARCHAR(30) NOT NULL, CHANGE statut statut VARCHAR(20) DEFAULT \'SUCCESS\' NOT NULL, CHANGE description description LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE transaction ADD CONSTRAINT FK_723705D1720FDE16 FOREIGN KEY (carte_source_id) REFERENCES carte_virtuelle (id)');
        $this->addSql('ALTER TABLE transaction ADD CONSTRAINT FK_723705D1284B7CCD FOREIGN KEY (carte_dest_id) REFERENCES carte_virtuelle (id)');
        $this->dropIndexIfExists('transaction', 'idx_transaction_source');
        $this->addSql('CREATE INDEX IDX_723705D1720FDE16 ON transaction (carte_source_id)');
        $this->dropIndexIfExists('transaction', 'idx_transaction_dest');
        $this->addSql('CREATE INDEX IDX_723705D1284B7CCD ON transaction (carte_dest_id)');
        $this->addSql('ALTER TABLE transaction ADD CONSTRAINT fk_transaction_dest FOREIGN KEY (carte_dest_id) REFERENCES carte_virtuelle (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE transaction ADD CONSTRAINT fk_transaction_source FOREIGN KEY (carte_source_id) REFERENCES carte_virtuelle (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE users DROP solde, CHANGE fingerprint_template fingerprint_template MEDIUMBLOB DEFAULT NULL, CHANGE face_template face_template MEDIUMBLOB DEFAULT NULL, CHANGE role role ENUM(\'ADMIN\',\'CLIENT\') NOT NULL, CHANGE is_active is_active TINYINT(1) NOT NULL, CHANGE created_at created_at DATETIME NOT NULL, CHANGE updated_at updated_at DATETIME NOT NULL');
        $this->dropIndexIfExists('users', 'uk_users_email');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1483A5E9E7927C74 ON users (email)');
        $this->dropForeignKeyIfExists('virement_programme', 'fk_virement_carte_source');
        $this->dropForeignKeyIfExists('virement_programme', 'fk_virement_user');
        $this->dropForeignKeyIfExists('virement_programme', 'fk_virement_carte_dest');
        $this->dropIndexIfExists('virement_programme', 'idx_virement_prochaine_exec');
        $this->dropIndexIfExists('virement_programme', 'idx_virement_statut');
        $this->addSql('ALTER TABLE virement_programme CHANGE frequence frequence VARCHAR(30) DEFAULT \'UNE_FOIS\' NOT NULL, CHANGE statut statut VARCHAR(20) DEFAULT \'PENDING\' NOT NULL, CHANGE description description LONGTEXT DEFAULT NULL, CHANGE error_message error_message LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE virement_programme ADD CONSTRAINT FK_AF8A218DA76ED395 FOREIGN KEY (user_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE virement_programme ADD CONSTRAINT FK_AF8A218D720FDE16 FOREIGN KEY (carte_source_id) REFERENCES carte_virtuelle (id)');
        $this->addSql('ALTER TABLE virement_programme ADD CONSTRAINT FK_AF8A218D284B7CCD FOREIGN KEY (carte_dest_id) REFERENCES carte_virtuelle (id)');
        $this->dropIndexIfExists('virement_programme', 'idx_virement_user');
        $this->addSql('CREATE INDEX IDX_AF8A218DA76ED395 ON virement_programme (user_id)');
        $this->dropIndexIfExists('virement_programme', 'idx_virement_carte_source');
        $this->addSql('CREATE INDEX IDX_AF8A218D720FDE16 ON virement_programme (carte_source_id)');
        $this->dropIndexIfExists('virement_programme', 'idx_virement_carte_dest');
        $this->addSql('CREATE INDEX IDX_AF8A218D284B7CCD ON virement_programme (carte_dest_id)');
        $this->addSql('ALTER TABLE virement_programme ADD CONSTRAINT fk_virement_carte_source FOREIGN KEY (carte_source_id) REFERENCES carte_virtuelle (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE virement_programme ADD CONSTRAINT fk_virement_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE virement_programme ADD CONSTRAINT fk_virement_carte_dest FOREIGN KEY (carte_dest_id) REFERENCES carte_virtuelle (id) ON DELETE SET NULL');
        $this->dropIndexIfExists('messenger_messages', 'idx_messenger_queue');
        $this->addSql('CREATE INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 ON messenger_messages (queue_name, available_at, delivered_at, id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE carte_virtuelle DROP FOREIGN KEY FK_2EF4B275513EC3CA');
        $this->addSql('ALTER TABLE carte_virtuelle DROP FOREIGN KEY FK_2EF4B275513EC3CA');
        $this->addSql('ALTER TABLE carte_virtuelle CHANGE solde solde NUMERIC(15, 2) DEFAULT \'0.00\' NOT NULL, CHANGE plafond plafond NUMERIC(15, 2) DEFAULT \'1000.00\' NOT NULL, CHANGE type type ENUM(\'NORMAL\', \'GOLD\', \'SILVER\') DEFAULT \'NORMAL\' NOT NULL');
        $this->addSql('ALTER TABLE carte_virtuelle ADD CONSTRAINT fk_carte_portefeuille FOREIGN KEY (portefeuille_id) REFERENCES portefeuille (id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX idx_carte_type ON carte_virtuelle (type)');
        $this->addSql('DROP INDEX uniq_2ef4b275158bd4c0 ON carte_virtuelle');
        $this->addSql('CREATE UNIQUE INDEX uq_carte_numero ON carte_virtuelle (numero_carte)');
        $this->addSql('DROP INDEX idx_2ef4b275513ec3ca ON carte_virtuelle');
        $this->addSql('CREATE INDEX idx_carte_portefeuille ON carte_virtuelle (portefeuille_id)');
        $this->addSql('ALTER TABLE carte_virtuelle ADD CONSTRAINT FK_2EF4B275513EC3CA FOREIGN KEY (portefeuille_id) REFERENCES portefeuille (id)');
        $this->addSql('ALTER TABLE chat_conversations DROP FOREIGN KEY FK_5813432E415F1F91');
        $this->addSql('ALTER TABLE chat_conversations DROP FOREIGN KEY FK_5813432E53EAB07F');
        $this->addSql('ALTER TABLE chat_conversations CHANGE created_at created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CHANGE updated_at updated_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('DROP INDEX idx_5813432e415f1f91 ON chat_conversations');
        $this->addSql('CREATE INDEX idx_chat_conversations_user_a ON chat_conversations (user_a_id)');
        $this->addSql('DROP INDEX idx_5813432e53eab07f ON chat_conversations');
        $this->addSql('CREATE INDEX idx_chat_conversations_user_b ON chat_conversations (user_b_id)');
        $this->addSql('ALTER TABLE chat_conversations ADD CONSTRAINT FK_5813432E415F1F91 FOREIGN KEY (user_a_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE chat_conversations ADD CONSTRAINT FK_5813432E53EAB07F FOREIGN KEY (user_b_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE chat_messages DROP FOREIGN KEY FK_EF20C9A69AC0396');
        $this->addSql('ALTER TABLE chat_messages DROP FOREIGN KEY FK_EF20C9A6F624B39D');
        $this->addSql('ALTER TABLE chat_messages DROP FOREIGN KEY FK_EF20C9A6E92F8F78');
        $this->addSql('ALTER TABLE chat_messages CHANGE is_read is_read TINYINT(1) DEFAULT 0 NOT NULL, CHANGE created_at created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('DROP INDEX idx_ef20c9a6e92f8f78 ON chat_messages');
        $this->addSql('CREATE INDEX idx_chat_messages_recipient ON chat_messages (recipient_id)');
        $this->addSql('DROP INDEX idx_ef20c9a69ac0396 ON chat_messages');
        $this->addSql('CREATE INDEX idx_chat_messages_conversation ON chat_messages (conversation_id)');
        $this->addSql('DROP INDEX idx_ef20c9a6f624b39d ON chat_messages');
        $this->addSql('CREATE INDEX idx_chat_messages_sender ON chat_messages (sender_id)');
        $this->addSql('ALTER TABLE chat_messages ADD CONSTRAINT FK_EF20C9A69AC0396 FOREIGN KEY (conversation_id) REFERENCES chat_conversations (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE chat_messages ADD CONSTRAINT FK_EF20C9A6F624B39D FOREIGN KEY (sender_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE chat_messages ADD CONSTRAINT FK_EF20C9A6E92F8F78 FOREIGN KEY (recipient_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE clients DROP FOREIGN KEY FK_C82E74A76ED395');
        $this->addSql('ALTER TABLE clients ADD city VARCHAR(120) DEFAULT NULL');
        $this->addSql('ALTER TABLE clients ADD CONSTRAINT fk_clients_user FOREIGN KEY (user_id) REFERENCES users (id) ON UPDATE CASCADE ON DELETE CASCADE');
        $this->addSql('CREATE INDEX idx_clients_city ON clients (city)');
        $this->addSql('ALTER TABLE mail_delivery_log DROP FOREIGN KEY FK_50467275A76ED395');
        $this->addSql('ALTER TABLE mail_delivery_log CHANGE channel channel VARCHAR(30) DEFAULT \'EMAIL\' NOT NULL, CHANGE provider provider VARCHAR(40) DEFAULT \'BREVO\' NOT NULL, CHANGE created_at created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('DROP INDEX idx_50467275a76ed395 ON mail_delivery_log');
        $this->addSql('CREATE INDEX idx_mail_delivery_user ON mail_delivery_log (user_id)');
        $this->addSql('ALTER TABLE mail_delivery_log ADD CONSTRAINT FK_50467275A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('DROP INDEX idx_75ea56e0fb7336f0e3bd61ce16ba31dbbf396750 ON messenger_messages');
        $this->addSql('CREATE INDEX idx_messenger_queue ON messenger_messages (queue_name, available_at, delivered_at, id)');
        $this->addSql('ALTER TABLE portefeuille DROP FOREIGN KEY FK_2955FFFEA76ED395');
        $this->addSql('ALTER TABLE portefeuille DROP FOREIGN KEY FK_2955FFFEA76ED395');
        $this->addSql('ALTER TABLE portefeuille CHANGE solde_total solde_total NUMERIC(15, 2) DEFAULT \'0.00\' NOT NULL');
        $this->addSql('ALTER TABLE portefeuille ADD CONSTRAINT fk_portefeuille_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('DROP INDEX idx_2955fffea76ed395 ON portefeuille');
        $this->addSql('CREATE INDEX idx_portefeuille_user ON portefeuille (user_id)');
        $this->addSql('ALTER TABLE portefeuille ADD CONSTRAINT FK_2955FFFEA76ED395 FOREIGN KEY (user_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE transaction DROP FOREIGN KEY FK_723705D1720FDE16');
        $this->addSql('ALTER TABLE transaction DROP FOREIGN KEY FK_723705D1284B7CCD');
        $this->addSql('ALTER TABLE transaction DROP FOREIGN KEY FK_723705D1720FDE16');
        $this->addSql('ALTER TABLE transaction DROP FOREIGN KEY FK_723705D1284B7CCD');
        $this->addSql('ALTER TABLE transaction CHANGE type type ENUM(\'DEPOT\', \'RETRAIT\', \'TRANSFERT\', \'VIREMENT_PROGRAMME\') NOT NULL, CHANGE statut statut ENUM(\'SUCCESS\', \'FAILED\', \'PENDING\') DEFAULT \'SUCCESS\' NOT NULL, CHANGE description description TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE transaction ADD CONSTRAINT fk_transaction_dest FOREIGN KEY (carte_dest_id) REFERENCES carte_virtuelle (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE transaction ADD CONSTRAINT fk_transaction_source FOREIGN KEY (carte_source_id) REFERENCES carte_virtuelle (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX idx_transaction_type ON transaction (type)');
        $this->addSql('CREATE INDEX idx_transaction_statut ON transaction (statut)');
        $this->addSql('CREATE INDEX idx_transaction_date ON transaction (date)');
        $this->addSql('DROP INDEX idx_723705d1720fde16 ON transaction');
        $this->addSql('CREATE INDEX idx_transaction_source ON transaction (carte_source_id)');
        $this->addSql('DROP INDEX idx_723705d1284b7ccd ON transaction');
        $this->addSql('CREATE INDEX idx_transaction_dest ON transaction (carte_dest_id)');
        $this->addSql('ALTER TABLE transaction ADD CONSTRAINT FK_723705D1720FDE16 FOREIGN KEY (carte_source_id) REFERENCES carte_virtuelle (id)');
        $this->addSql('ALTER TABLE transaction ADD CONSTRAINT FK_723705D1284B7CCD FOREIGN KEY (carte_dest_id) REFERENCES carte_virtuelle (id)');
        $this->addSql('ALTER TABLE users ADD solde NUMERIC(15, 2) DEFAULT \'0.00\' NOT NULL, CHANGE fingerprint_template fingerprint_template MEDIUMBLOB DEFAULT NULL, CHANGE face_template face_template MEDIUMBLOB DEFAULT NULL, CHANGE role role ENUM(\'ADMIN\', \'CLIENT\') NOT NULL, CHANGE is_active is_active TINYINT(1) DEFAULT 1 NOT NULL, CHANGE created_at created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CHANGE updated_at updated_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('DROP INDEX uniq_1483a5e9e7927c74 ON users');
        $this->addSql('CREATE UNIQUE INDEX uk_users_email ON users (email)');
        $this->addSql('ALTER TABLE virement_programme DROP FOREIGN KEY FK_AF8A218DA76ED395');
        $this->addSql('ALTER TABLE virement_programme DROP FOREIGN KEY FK_AF8A218D720FDE16');
        $this->addSql('ALTER TABLE virement_programme DROP FOREIGN KEY FK_AF8A218D284B7CCD');
        $this->addSql('ALTER TABLE virement_programme DROP FOREIGN KEY FK_AF8A218DA76ED395');
        $this->addSql('ALTER TABLE virement_programme DROP FOREIGN KEY FK_AF8A218D720FDE16');
        $this->addSql('ALTER TABLE virement_programme DROP FOREIGN KEY FK_AF8A218D284B7CCD');
        $this->addSql('ALTER TABLE virement_programme CHANGE frequence frequence ENUM(\'UNE_FOIS\', \'QUOTIDIEN\', \'HEBDOMADAIRE\', \'MENSUEL\') DEFAULT \'UNE_FOIS\' NOT NULL, CHANGE statut statut ENUM(\'PENDING\', \'PROCESSING\', \'COMPLETED\', \'FAILED\', \'CANCELLED\') DEFAULT \'PENDING\' NOT NULL, CHANGE description description TEXT DEFAULT NULL, CHANGE error_message error_message TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE virement_programme ADD CONSTRAINT fk_virement_carte_source FOREIGN KEY (carte_source_id) REFERENCES carte_virtuelle (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE virement_programme ADD CONSTRAINT fk_virement_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE virement_programme ADD CONSTRAINT fk_virement_carte_dest FOREIGN KEY (carte_dest_id) REFERENCES carte_virtuelle (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX idx_virement_prochaine_exec ON virement_programme (prochaine_execution)');
        $this->addSql('CREATE INDEX idx_virement_statut ON virement_programme (statut)');
        $this->addSql('DROP INDEX idx_af8a218da76ed395 ON virement_programme');
        $this->addSql('CREATE INDEX idx_virement_user ON virement_programme (user_id)');
        $this->addSql('DROP INDEX idx_af8a218d720fde16 ON virement_programme');
        $this->addSql('CREATE INDEX idx_virement_carte_source ON virement_programme (carte_source_id)');
        $this->addSql('DROP INDEX idx_af8a218d284b7ccd ON virement_programme');
        $this->addSql('CREATE INDEX idx_virement_carte_dest ON virement_programme (carte_dest_id)');
        $this->addSql('ALTER TABLE virement_programme ADD CONSTRAINT FK_AF8A218DA76ED395 FOREIGN KEY (user_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE virement_programme ADD CONSTRAINT FK_AF8A218D720FDE16 FOREIGN KEY (carte_source_id) REFERENCES carte_virtuelle (id)');
        $this->addSql('ALTER TABLE virement_programme ADD CONSTRAINT FK_AF8A218D284B7CCD FOREIGN KEY (carte_dest_id) REFERENCES carte_virtuelle (id)');
    }
}
