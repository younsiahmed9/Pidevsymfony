<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260502213000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add moderation tracking fields to users table';
    }

    public function up(Schema $schema): void
    {
        $usersTable = $schema->getTable('users');

        if (!$usersTable->hasColumn('moderation_warning_count')) {
            $this->addSql('ALTER TABLE users ADD moderation_warning_count INT NOT NULL DEFAULT 0');
        }

        if (!$usersTable->hasColumn('moderation_blocked_at')) {
            $this->addSql('ALTER TABLE users ADD moderation_blocked_at DATETIME DEFAULT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        $usersTable = $schema->getTable('users');

        if ($usersTable->hasColumn('moderation_warning_count')) {
            $this->addSql('ALTER TABLE users DROP moderation_warning_count');
        }

        if ($usersTable->hasColumn('moderation_blocked_at')) {
            $this->addSql('ALTER TABLE users DROP moderation_blocked_at');
        }
    }
}
