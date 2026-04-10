<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260409230436 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE categorie (id_categorie INT AUTO_INCREMENT NOT NULL, nom_categorie VARCHAR(100) NOT NULL, description LONGTEXT DEFAULT NULL, icon VARCHAR(50) DEFAULT NULL, couleur VARCHAR(7) DEFAULT NULL, created_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_497DD634DD8CA775 (nom_categorie), PRIMARY KEY (id_categorie)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE compte (id_compte INT AUTO_INCREMENT NOT NULL, numero_compte VARCHAR(50) NOT NULL, type_compte VARCHAR(50) NOT NULL, solde NUMERIC(15, 2) NOT NULL, taux_interet NUMERIC(5, 2) DEFAULT NULL, plafond_decouvert NUMERIC(15, 2) DEFAULT NULL, date_creation DATE NOT NULL, etat VARCHAR(20) NOT NULL, id_utilisateur BIGINT NOT NULL, UNIQUE INDEX UNIQ_CFF652609731415A (numero_compte), INDEX IDX_CFF6526050EAE44 (id_utilisateur), PRIMARY KEY (id_compte)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE credit (id_credit INT AUTO_INCREMENT NOT NULL, montant NUMERIC(15, 2) NOT NULL, taux_interet NUMERIC(5, 2) NOT NULL, duree_mois INT NOT NULL, mensualite NUMERIC(15, 2) DEFAULT NULL, date_debut DATE NOT NULL, status VARCHAR(20) NOT NULL, id_compte INT NOT NULL, INDEX IDX_1CC16EFEE9C1E78D (id_compte), PRIMARY KEY (id_credit)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE document (id_document INT AUTO_INCREMENT NOT NULL, titre VARCHAR(255) NOT NULL, type_document VARCHAR(100) NOT NULL, chemin_fichier VARCHAR(500) NOT NULL, taille_fichier INT DEFAULT NULL, date_document DATE DEFAULT NULL, date_echeance DATE DEFAULT NULL, statut ENUM(\'valide\',\'expire\',\'a_renouveler\',\'archive\') NOT NULL DEFAULT \'valide\', description LONGTEXT DEFAULT NULL, tags VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, id_utilisateur BIGINT NOT NULL, id_categorie INT DEFAULT NULL, id_dossier INT DEFAULT NULL, INDEX IDX_D8698A7650EAE44 (id_utilisateur), INDEX IDX_D8698A76C9486A13 (id_categorie), INDEX IDX_D8698A76E3D54947 (id_dossier), PRIMARY KEY (id_document)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE dossier_document (id_dossier INT AUTO_INCREMENT NOT NULL, nom_dossier VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, id_utilisateur BIGINT NOT NULL, INDEX IDX_F029680150EAE44 (id_utilisateur), PRIMARY KEY (id_dossier)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE echeance (id_echeance INT AUTO_INCREMENT NOT NULL, titre VARCHAR(255) NOT NULL, date_echeance DATE NOT NULL, date_rappel DATE DEFAULT NULL, statut ENUM(\'pending\',\'notified\',\'completed\',\'overdue\') NOT NULL DEFAULT \'pending\', description LONGTEXT DEFAULT NULL, montant NUMERIC(15, 2) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, id_document INT NOT NULL, id_utilisateur BIGINT NOT NULL, INDEX IDX_40D9893B88B266E3 (id_document), INDEX IDX_40D9893B50EAE44 (id_utilisateur), PRIMARY KEY (id_echeance)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE utilisateur (id BIGINT AUTO_INCREMENT NOT NULL, email VARCHAR(255) NOT NULL, nom VARCHAR(100) NOT NULL, prenom VARCHAR(100) NOT NULL, password VARCHAR(255) NOT NULL, role ENUM(\'admin\',\'user\') NOT NULL DEFAULT \'user\', solde NUMERIC(15, 2) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_1D1C63B3E7927C74 (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE compte ADD CONSTRAINT FK_CFF6526050EAE44 FOREIGN KEY (id_utilisateur) REFERENCES utilisateur (id)');
        $this->addSql('ALTER TABLE credit ADD CONSTRAINT FK_1CC16EFEE9C1E78D FOREIGN KEY (id_compte) REFERENCES compte (id_compte)');
        $this->addSql('ALTER TABLE document ADD CONSTRAINT FK_D8698A7650EAE44 FOREIGN KEY (id_utilisateur) REFERENCES utilisateur (id)');
        $this->addSql('ALTER TABLE document ADD CONSTRAINT FK_D8698A76C9486A13 FOREIGN KEY (id_categorie) REFERENCES categorie (id_categorie)');
        $this->addSql('ALTER TABLE document ADD CONSTRAINT FK_D8698A76E3D54947 FOREIGN KEY (id_dossier) REFERENCES dossier_document (id_dossier)');
        $this->addSql('ALTER TABLE dossier_document ADD CONSTRAINT FK_F029680150EAE44 FOREIGN KEY (id_utilisateur) REFERENCES utilisateur (id)');
        $this->addSql('ALTER TABLE echeance ADD CONSTRAINT FK_40D9893B88B266E3 FOREIGN KEY (id_document) REFERENCES document (id_document)');
        $this->addSql('ALTER TABLE echeance ADD CONSTRAINT FK_40D9893B50EAE44 FOREIGN KEY (id_utilisateur) REFERENCES utilisateur (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE compte DROP FOREIGN KEY FK_CFF6526050EAE44');
        $this->addSql('ALTER TABLE credit DROP FOREIGN KEY FK_1CC16EFEE9C1E78D');
        $this->addSql('ALTER TABLE document DROP FOREIGN KEY FK_D8698A7650EAE44');
        $this->addSql('ALTER TABLE document DROP FOREIGN KEY FK_D8698A76C9486A13');
        $this->addSql('ALTER TABLE document DROP FOREIGN KEY FK_D8698A76E3D54947');
        $this->addSql('ALTER TABLE dossier_document DROP FOREIGN KEY FK_F029680150EAE44');
        $this->addSql('ALTER TABLE echeance DROP FOREIGN KEY FK_40D9893B88B266E3');
        $this->addSql('ALTER TABLE echeance DROP FOREIGN KEY FK_40D9893B50EAE44');
        $this->addSql('DROP TABLE categorie');
        $this->addSql('DROP TABLE compte');
        $this->addSql('DROP TABLE credit');
        $this->addSql('DROP TABLE document');
        $this->addSql('DROP TABLE dossier_document');
        $this->addSql('DROP TABLE echeance');
        $this->addSql('DROP TABLE utilisateur');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
