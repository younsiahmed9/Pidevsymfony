<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260507000230 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add is_starred column to chat_messages';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE chat_messages ADD is_starred TINYINT(1) NOT NULL DEFAULT 0');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE chat_messages DROP is_starred');
    }
}
