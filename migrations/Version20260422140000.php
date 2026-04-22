<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260422140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Introduce normalized tags for documents with a Many-to-Many join table.';
    }

    public function up(Schema $schema): void
    {
        $schemaManager = $this->connection->createSchemaManager();

        if (!$schemaManager->tablesExist(['tag'])) {
            $this->connection->executeStatement('CREATE TABLE tag (
                id_tag INT AUTO_INCREMENT NOT NULL,
                nom_tag VARCHAR(100) NOT NULL,
                UNIQUE INDEX UNIQ_2B5C6B2D4F8E6A4F (nom_tag),
                PRIMARY KEY(id_tag)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        }

        if (!$schemaManager->tablesExist(['document_tag'])) {
            $this->connection->executeStatement('CREATE TABLE document_tag (
                document_id INT NOT NULL,
                tag_id INT NOT NULL,
                INDEX IDX_6D7A7E0E1C7E6B5B (document_id),
                INDEX IDX_6D7A7E0E89D9B6F3 (tag_id),
                PRIMARY KEY(document_id, tag_id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

            $this->connection->executeStatement('ALTER TABLE document_tag ADD CONSTRAINT FK_6D7A7E0E1C7E6B5B FOREIGN KEY (document_id) REFERENCES document (id_document) ON DELETE CASCADE');
            $this->connection->executeStatement('ALTER TABLE document_tag ADD CONSTRAINT FK_6D7A7E0E89D9B6F3 FOREIGN KEY (tag_id) REFERENCES tag (id_tag) ON DELETE CASCADE');

            $documents = $this->connection->fetchAllAssociative('SELECT id_document, tags FROM document WHERE tags IS NOT NULL AND TRIM(tags) <> ""');
            $createdTags = [];

            foreach ($documents as $document) {
                $documentId = (int) $document['id_document'];
                $rawTags = preg_split('/[;,\n]+/', (string) $document['tags']) ?: [];

                foreach ($rawTags as $rawTag) {
                    $tagName = trim($rawTag);

                    if ($tagName === '') {
                        continue;
                    }

                    $normalized = mb_strtolower($tagName);

                    if (!isset($createdTags[$normalized])) {
                        $tagId = $this->connection->fetchOne('SELECT id_tag FROM tag WHERE LOWER(nom_tag) = :name LIMIT 1', [
                            'name' => $normalized,
                        ]);

                        if (!$tagId) {
                            $this->connection->executeStatement('INSERT INTO tag (nom_tag) VALUES (:name)', [
                                'name' => $tagName,
                            ]);

                            $tagId = (int) $this->connection->lastInsertId();
                        }

                        $createdTags[$normalized] = (int) $tagId;
                    }

                    $tagId = $createdTags[$normalized];
                    $this->connection->executeStatement('INSERT IGNORE INTO document_tag (document_id, tag_id) VALUES (:document_id, :tag_id)', [
                        'document_id' => $documentId,
                        'tag_id' => $tagId,
                    ]);
                }
            }
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS document_tag');
        $this->addSql('DROP TABLE IF EXISTS tag');
    }
}