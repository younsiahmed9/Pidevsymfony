<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260422234114 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create releve table for account statement history';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE releve (
            id INT AUTO_INCREMENT NOT NULL, 
            compte_id INT NOT NULL, 
            date_generation DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', 
            chemin_fichier LONGTEXT NOT NULL, 
            solde_au_moment NUMERIC(10, 2) NOT NULL, 
            nombre_credits_au_moment INT NOT NULL, 
            type_releve VARCHAR(255) DEFAULT \'compte_complet\' NOT NULL, 
            metadonnees LONGTEXT, 
            INDEX idx_releve_compte (compte_id), 
            INDEX idx_releve_date (date_generation), 
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        
        $this->addSql('ALTER TABLE releve ADD CONSTRAINT FK_4E6D2B20DFED6674 FOREIGN KEY (compte_id) REFERENCES compte (id_compte) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE releve DROP FOREIGN KEY FK_4E6D2B20DFED6674');
        $this->addSql('DROP TABLE releve');
    }
}
