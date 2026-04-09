<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260406150312 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE facture (id INT AUTO_INCREMENT NOT NULL, montant NUMERIC(10, 2) NOT NULL, date_facture DATE NOT NULL, date_echeance DATE NOT NULL, statut VARCHAR(20) NOT NULL, numero_facture VARCHAR(50) NOT NULL, id_service INT DEFAULT NULL, id_produit INT DEFAULT NULL, UNIQUE INDEX UNIQ_FE86641038D27AB1 (numero_facture), INDEX IDX_FE8664103F0033A2 (id_service), INDEX IDX_FE866410F7384557 (id_produit), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE produit (id INT AUTO_INCREMENT NOT NULL, nom_produit VARCHAR(100) NOT NULL, type_produit VARCHAR(50) NOT NULL, montant NUMERIC(10, 2) NOT NULL, code_unique VARCHAR(100) NOT NULL, statut VARCHAR(50) NOT NULL, date_creation DATE NOT NULL, UNIQUE INDEX UNIQ_29A5EC27CF240ED5 (code_unique), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE service (id INT AUTO_INCREMENT NOT NULL, nom_service VARCHAR(100) NOT NULL, type_service VARCHAR(50) NOT NULL, tarif NUMERIC(10, 2) NOT NULL, frequence VARCHAR(50) DEFAULT NULL, date_debut DATE DEFAULT NULL, date_fin DATE DEFAULT NULL, statut VARCHAR(50) NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE facture ADD CONSTRAINT FK_FE8664103F0033A2 FOREIGN KEY (id_service) REFERENCES service (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE facture ADD CONSTRAINT FK_FE866410F7384557 FOREIGN KEY (id_produit) REFERENCES produit (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE facture DROP FOREIGN KEY FK_FE8664103F0033A2');
        $this->addSql('ALTER TABLE facture DROP FOREIGN KEY FK_FE866410F7384557');
        $this->addSql('DROP TABLE facture');
        $this->addSql('DROP TABLE produit');
        $this->addSql('DROP TABLE service');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
