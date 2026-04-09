<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260407232616 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE utilisateur (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(50) NOT NULL, nom VARCHAR(50) NOT NULL, prenom VARCHAR(50) NOT NULL, password VARCHAR(50) NOT NULL, role VARCHAR(50) NOT NULL, solde VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE email_verification DROP FOREIGN KEY email_verification_ibfk_1');
        $this->addSql('ALTER TABLE messages DROP FOREIGN KEY messages_ibfk_2');
        $this->addSql('ALTER TABLE messages DROP FOREIGN KEY messages_ibfk_1');
        $this->addSql('DROP TABLE email_verification');
        $this->addSql('DROP TABLE messages');
        $this->addSql('ALTER TABLE admins DROP FOREIGN KEY fk_admins_user');
        $this->addSql('ALTER TABLE admins ADD CONSTRAINT FK_A2E0150FA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE carte_virtuelle DROP FOREIGN KEY fk_carte_portefeuille');
        $this->addSql('DROP INDEX idx_carte_type ON carte_virtuelle');
        $this->addSql('ALTER TABLE carte_virtuelle DROP FOREIGN KEY fk_carte_portefeuille');
        $this->addSql('ALTER TABLE carte_virtuelle CHANGE solde solde NUMERIC(15, 2) DEFAULT 0 NOT NULL, CHANGE plafond plafond NUMERIC(15, 2) DEFAULT 1000 NOT NULL, CHANGE type type VARCHAR(20) DEFAULT \'NORMAL\' NOT NULL');
        $this->addSql('ALTER TABLE carte_virtuelle ADD CONSTRAINT FK_2EF4B275513EC3CA FOREIGN KEY (portefeuille_id) REFERENCES portefeuille (id)');
        $this->addSql('DROP INDEX uq_carte_numero ON carte_virtuelle');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_2EF4B275158BD4C0 ON carte_virtuelle (numero_carte)');
        $this->addSql('DROP INDEX idx_carte_portefeuille ON carte_virtuelle');
        $this->addSql('CREATE INDEX IDX_2EF4B275513EC3CA ON carte_virtuelle (portefeuille_id)');
        $this->addSql('ALTER TABLE carte_virtuelle ADD CONSTRAINT fk_carte_portefeuille FOREIGN KEY (portefeuille_id) REFERENCES portefeuille (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE clients DROP FOREIGN KEY fk_clients_user');
        $this->addSql('ALTER TABLE clients ADD CONSTRAINT FK_C82E74A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE portefeuille DROP FOREIGN KEY fk_portefeuille_utilisateur');
        $this->addSql('DROP INDEX idx_portefeuille_utilisateur ON portefeuille');
        $this->addSql('ALTER TABLE portefeuille ADD user_id INT NOT NULL, DROP utilisateur_id, CHANGE solde_total solde_total NUMERIC(15, 2) DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE portefeuille ADD CONSTRAINT FK_2955FFFEA76ED395 FOREIGN KEY (user_id) REFERENCES utilisateur (id)');
        $this->addSql('CREATE INDEX IDX_2955FFFEA76ED395 ON portefeuille (user_id)');
        $this->addSql('ALTER TABLE transaction DROP FOREIGN KEY fk_transaction_source');
        $this->addSql('ALTER TABLE transaction DROP FOREIGN KEY fk_transaction_dest');
        $this->addSql('DROP INDEX idx_transaction_date ON transaction');
        $this->addSql('DROP INDEX idx_transaction_type ON transaction');
        $this->addSql('DROP INDEX idx_transaction_statut ON transaction');
        $this->addSql('ALTER TABLE transaction DROP FOREIGN KEY fk_transaction_source');
        $this->addSql('ALTER TABLE transaction DROP FOREIGN KEY fk_transaction_dest');
        $this->addSql('ALTER TABLE transaction CHANGE type type VARCHAR(30) NOT NULL, CHANGE statut statut VARCHAR(20) DEFAULT \'SUCCESS\' NOT NULL, CHANGE description description LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE transaction ADD CONSTRAINT FK_723705D1720FDE16 FOREIGN KEY (carte_source_id) REFERENCES carte_virtuelle (id)');
        $this->addSql('ALTER TABLE transaction ADD CONSTRAINT FK_723705D1284B7CCD FOREIGN KEY (carte_dest_id) REFERENCES carte_virtuelle (id)');
        $this->addSql('DROP INDEX idx_transaction_source ON transaction');
        $this->addSql('CREATE INDEX IDX_723705D1720FDE16 ON transaction (carte_source_id)');
        $this->addSql('DROP INDEX idx_transaction_dest ON transaction');
        $this->addSql('CREATE INDEX IDX_723705D1284B7CCD ON transaction (carte_dest_id)');
        $this->addSql('ALTER TABLE transaction ADD CONSTRAINT fk_transaction_source FOREIGN KEY (carte_source_id) REFERENCES carte_virtuelle (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE transaction ADD CONSTRAINT fk_transaction_dest FOREIGN KEY (carte_dest_id) REFERENCES carte_virtuelle (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE users CHANGE fingerprint_template fingerprint_template MEDIUMBLOB DEFAULT NULL, CHANGE face_template face_template MEDIUMBLOB DEFAULT NULL, CHANGE role role ENUM(\'ADMIN\',\'CLIENT\') NOT NULL, CHANGE is_active is_active TINYINT(1) NOT NULL, CHANGE created_at created_at TIMESTAMP NOT NULL, CHANGE updated_at updated_at TIMESTAMP NOT NULL');
        $this->addSql('DROP INDEX uk_users_email ON users');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1483A5E9E7927C74 ON users (email)');
        $this->addSql('ALTER TABLE virement_programme DROP FOREIGN KEY fk_virement_utilisateur');
        $this->addSql('ALTER TABLE virement_programme DROP FOREIGN KEY fk_virement_carte_dest');
        $this->addSql('ALTER TABLE virement_programme DROP FOREIGN KEY fk_virement_carte_source');
        $this->addSql('DROP INDEX idx_virement_prochaine_exec ON virement_programme');
        $this->addSql('DROP INDEX idx_virement_utilisateur ON virement_programme');
        $this->addSql('DROP INDEX idx_virement_statut ON virement_programme');
        $this->addSql('ALTER TABLE virement_programme DROP FOREIGN KEY fk_virement_carte_dest');
        $this->addSql('ALTER TABLE virement_programme DROP FOREIGN KEY fk_virement_carte_source');
        $this->addSql('ALTER TABLE virement_programme ADD user_id INT NOT NULL, DROP utilisateur_id, CHANGE frequence frequence VARCHAR(30) DEFAULT \'UNE_FOIS\' NOT NULL, CHANGE statut statut VARCHAR(20) DEFAULT \'PENDING\' NOT NULL, CHANGE description description LONGTEXT DEFAULT NULL, CHANGE error_message error_message LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE virement_programme ADD CONSTRAINT FK_AF8A218DA76ED395 FOREIGN KEY (user_id) REFERENCES utilisateur (id)');
        $this->addSql('ALTER TABLE virement_programme ADD CONSTRAINT FK_AF8A218D720FDE16 FOREIGN KEY (carte_source_id) REFERENCES carte_virtuelle (id)');
        $this->addSql('ALTER TABLE virement_programme ADD CONSTRAINT FK_AF8A218D284B7CCD FOREIGN KEY (carte_dest_id) REFERENCES carte_virtuelle (id)');
        $this->addSql('CREATE INDEX IDX_AF8A218DA76ED395 ON virement_programme (user_id)');
        $this->addSql('DROP INDEX idx_virement_carte_source ON virement_programme');
        $this->addSql('CREATE INDEX IDX_AF8A218D720FDE16 ON virement_programme (carte_source_id)');
        $this->addSql('DROP INDEX idx_virement_carte_dest ON virement_programme');
        $this->addSql('CREATE INDEX IDX_AF8A218D284B7CCD ON virement_programme (carte_dest_id)');
        $this->addSql('ALTER TABLE virement_programme ADD CONSTRAINT fk_virement_carte_dest FOREIGN KEY (carte_dest_id) REFERENCES carte_virtuelle (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE virement_programme ADD CONSTRAINT fk_virement_carte_source FOREIGN KEY (carte_source_id) REFERENCES carte_virtuelle (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE email_verification (id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL, user_id BIGINT UNSIGNED NOT NULL, token VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, expires_at DATETIME DEFAULT NULL, used TINYINT(1) DEFAULT 0, INDEX user_id (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE messages (id BIGINT AUTO_INCREMENT NOT NULL, sender_id BIGINT UNSIGNED NOT NULL, receiver_id BIGINT UNSIGNED NOT NULL, content TEXT CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, timestamp DATETIME DEFAULT CURRENT_TIMESTAMP, is_read TINYINT(1) DEFAULT 0, INDEX sender_id (sender_id), INDEX receiver_id (receiver_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE email_verification ADD CONSTRAINT email_verification_ibfk_1 FOREIGN KEY (user_id) REFERENCES users (id) ON UPDATE CASCADE ON DELETE CASCADE');
        $this->addSql('ALTER TABLE messages ADD CONSTRAINT messages_ibfk_2 FOREIGN KEY (receiver_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE messages ADD CONSTRAINT messages_ibfk_1 FOREIGN KEY (sender_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('DROP TABLE utilisateur');
        $this->addSql('DROP TABLE messenger_messages');
        $this->addSql('ALTER TABLE admins DROP FOREIGN KEY FK_A2E0150FA76ED395');
        $this->addSql('ALTER TABLE admins ADD CONSTRAINT fk_admins_user FOREIGN KEY (user_id) REFERENCES users (id) ON UPDATE CASCADE ON DELETE CASCADE');
        $this->addSql('ALTER TABLE carte_virtuelle DROP FOREIGN KEY FK_2EF4B275513EC3CA');
        $this->addSql('ALTER TABLE carte_virtuelle DROP FOREIGN KEY FK_2EF4B275513EC3CA');
        $this->addSql('ALTER TABLE carte_virtuelle CHANGE solde solde NUMERIC(15, 2) DEFAULT \'0.00\' NOT NULL, CHANGE plafond plafond NUMERIC(15, 2) DEFAULT \'1000.00\' NOT NULL, CHANGE type type ENUM(\'NORMAL\', \'GOLD\', \'SILVER\') DEFAULT \'NORMAL\' NOT NULL');
        $this->addSql('ALTER TABLE carte_virtuelle ADD CONSTRAINT fk_carte_portefeuille FOREIGN KEY (portefeuille_id) REFERENCES portefeuille (id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX idx_carte_type ON carte_virtuelle (type)');
        $this->addSql('DROP INDEX idx_2ef4b275513ec3ca ON carte_virtuelle');
        $this->addSql('CREATE INDEX idx_carte_portefeuille ON carte_virtuelle (portefeuille_id)');
        $this->addSql('DROP INDEX uniq_2ef4b275158bd4c0 ON carte_virtuelle');
        $this->addSql('CREATE UNIQUE INDEX uq_carte_numero ON carte_virtuelle (numero_carte)');
        $this->addSql('ALTER TABLE carte_virtuelle ADD CONSTRAINT FK_2EF4B275513EC3CA FOREIGN KEY (portefeuille_id) REFERENCES portefeuille (id)');
        $this->addSql('ALTER TABLE clients DROP FOREIGN KEY FK_C82E74A76ED395');
        $this->addSql('ALTER TABLE clients ADD CONSTRAINT fk_clients_user FOREIGN KEY (user_id) REFERENCES users (id) ON UPDATE CASCADE ON DELETE CASCADE');
        $this->addSql('ALTER TABLE portefeuille DROP FOREIGN KEY FK_2955FFFEA76ED395');
        $this->addSql('DROP INDEX IDX_2955FFFEA76ED395 ON portefeuille');
        $this->addSql('ALTER TABLE portefeuille ADD utilisateur_id BIGINT UNSIGNED NOT NULL, DROP user_id, CHANGE solde_total solde_total NUMERIC(15, 2) DEFAULT \'0.00\' NOT NULL');
        $this->addSql('ALTER TABLE portefeuille ADD CONSTRAINT fk_portefeuille_utilisateur FOREIGN KEY (utilisateur_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX idx_portefeuille_utilisateur ON portefeuille (utilisateur_id)');
        $this->addSql('ALTER TABLE transaction DROP FOREIGN KEY FK_723705D1720FDE16');
        $this->addSql('ALTER TABLE transaction DROP FOREIGN KEY FK_723705D1284B7CCD');
        $this->addSql('ALTER TABLE transaction DROP FOREIGN KEY FK_723705D1720FDE16');
        $this->addSql('ALTER TABLE transaction DROP FOREIGN KEY FK_723705D1284B7CCD');
        $this->addSql('ALTER TABLE transaction CHANGE type type ENUM(\'DEPOT\', \'RETRAIT\', \'TRANSFERT\', \'VIREMENT_PROGRAMME\') NOT NULL, CHANGE statut statut ENUM(\'SUCCESS\', \'FAILED\', \'PENDING\') DEFAULT \'SUCCESS\' NOT NULL, CHANGE description description TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE transaction ADD CONSTRAINT fk_transaction_source FOREIGN KEY (carte_source_id) REFERENCES carte_virtuelle (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE transaction ADD CONSTRAINT fk_transaction_dest FOREIGN KEY (carte_dest_id) REFERENCES carte_virtuelle (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX idx_transaction_date ON transaction (date)');
        $this->addSql('CREATE INDEX idx_transaction_type ON transaction (type)');
        $this->addSql('CREATE INDEX idx_transaction_statut ON transaction (statut)');
        $this->addSql('DROP INDEX idx_723705d1720fde16 ON transaction');
        $this->addSql('CREATE INDEX idx_transaction_source ON transaction (carte_source_id)');
        $this->addSql('DROP INDEX idx_723705d1284b7ccd ON transaction');
        $this->addSql('CREATE INDEX idx_transaction_dest ON transaction (carte_dest_id)');
        $this->addSql('ALTER TABLE transaction ADD CONSTRAINT FK_723705D1720FDE16 FOREIGN KEY (carte_source_id) REFERENCES carte_virtuelle (id)');
        $this->addSql('ALTER TABLE transaction ADD CONSTRAINT FK_723705D1284B7CCD FOREIGN KEY (carte_dest_id) REFERENCES carte_virtuelle (id)');
        $this->addSql('ALTER TABLE users CHANGE fingerprint_template fingerprint_template MEDIUMBLOB DEFAULT NULL, CHANGE face_template face_template MEDIUMBLOB DEFAULT NULL, CHANGE role role ENUM(\'ADMIN\', \'CLIENT\') NOT NULL, CHANGE is_active is_active TINYINT(1) DEFAULT 1 NOT NULL, CHANGE created_at created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CHANGE updated_at updated_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('DROP INDEX uniq_1483a5e9e7927c74 ON users');
        $this->addSql('CREATE UNIQUE INDEX uk_users_email ON users (email)');
        $this->addSql('ALTER TABLE virement_programme DROP FOREIGN KEY FK_AF8A218DA76ED395');
        $this->addSql('ALTER TABLE virement_programme DROP FOREIGN KEY FK_AF8A218D720FDE16');
        $this->addSql('ALTER TABLE virement_programme DROP FOREIGN KEY FK_AF8A218D284B7CCD');
        $this->addSql('DROP INDEX IDX_AF8A218DA76ED395 ON virement_programme');
        $this->addSql('ALTER TABLE virement_programme DROP FOREIGN KEY FK_AF8A218D720FDE16');
        $this->addSql('ALTER TABLE virement_programme DROP FOREIGN KEY FK_AF8A218D284B7CCD');
        $this->addSql('ALTER TABLE virement_programme ADD utilisateur_id BIGINT UNSIGNED NOT NULL, DROP user_id, CHANGE frequence frequence ENUM(\'UNE_FOIS\', \'QUOTIDIEN\', \'HEBDOMADAIRE\', \'MENSUEL\') DEFAULT \'UNE_FOIS\' NOT NULL, CHANGE statut statut ENUM(\'PENDING\', \'PROCESSING\', \'COMPLETED\', \'FAILED\', \'CANCELLED\') DEFAULT \'PENDING\' NOT NULL, CHANGE description description TEXT DEFAULT NULL, CHANGE error_message error_message TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE virement_programme ADD CONSTRAINT fk_virement_utilisateur FOREIGN KEY (utilisateur_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE virement_programme ADD CONSTRAINT fk_virement_carte_dest FOREIGN KEY (carte_dest_id) REFERENCES carte_virtuelle (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE virement_programme ADD CONSTRAINT fk_virement_carte_source FOREIGN KEY (carte_source_id) REFERENCES carte_virtuelle (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX idx_virement_prochaine_exec ON virement_programme (prochaine_execution)');
        $this->addSql('CREATE INDEX idx_virement_utilisateur ON virement_programme (utilisateur_id)');
        $this->addSql('CREATE INDEX idx_virement_statut ON virement_programme (statut)');
        $this->addSql('DROP INDEX idx_af8a218d720fde16 ON virement_programme');
        $this->addSql('CREATE INDEX idx_virement_carte_source ON virement_programme (carte_source_id)');
        $this->addSql('DROP INDEX idx_af8a218d284b7ccd ON virement_programme');
        $this->addSql('CREATE INDEX idx_virement_carte_dest ON virement_programme (carte_dest_id)');
        $this->addSql('ALTER TABLE virement_programme ADD CONSTRAINT FK_AF8A218D720FDE16 FOREIGN KEY (carte_source_id) REFERENCES carte_virtuelle (id)');
        $this->addSql('ALTER TABLE virement_programme ADD CONSTRAINT FK_AF8A218D284B7CCD FOREIGN KEY (carte_dest_id) REFERENCES carte_virtuelle (id)');
    }
}
