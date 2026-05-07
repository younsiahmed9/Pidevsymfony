<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260502203000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add OAuth provider linkage fields to users table';
    }

    public function up(Schema $schema): void
    {
        // Check if column exists before adding it
        $usersTable = $schema->getTable('users');
        if (!$usersTable->hasColumn('oauth_provider')) {
            $this->addSql('ALTER TABLE users ADD oauth_provider VARCHAR(20) DEFAULT NULL, ADD oauth_id VARCHAR(255) DEFAULT NULL');
        }
        
        // Create indexes if they don't exist
        if (!$usersTable->hasIndex('IDX_1483A5E9EA87A5A8')) {
            $this->addSql('CREATE INDEX IDX_1483A5E9EA87A5A8 ON users (oauth_provider)');
        }
        if (!$usersTable->hasIndex('UNIQ_1483A5E96CDE8892A5D3224A')) {
            $this->addSql('CREATE UNIQUE INDEX UNIQ_1483A5E96CDE8892A5D3224A ON users (oauth_provider, oauth_id)');
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_1483A5E96CDE8892A5D3224A ON users');
        $this->addSql('DROP INDEX IDX_1483A5E9EA87A5A8 ON users');
        $this->addSql('ALTER TABLE users DROP oauth_provider, DROP oauth_id');
    }
}
