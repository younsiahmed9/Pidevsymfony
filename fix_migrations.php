<?php
/**
 * Script pour corriger les migrations avec colonnes en double
 */
$migrationsDir = __DIR__ . '/migrations';
$files = glob($migrationsDir . '/Version*.php');

foreach ($files as $file) {
    $content = file_get_contents($file);
    
    // Fix Version20260502203000 - oauth columns
    if (strpos($file, 'Version20260502203000') !== false) {
        $newContent = str_replace(
            "    public function up(Schema \$schema): void\n    {\n        // Check if column exists before adding it\n        \$usersTable = \$schema->getTable('users');\n        if (!\$usersTable->hasColumn('oauth_provider')) {\n            \$this->addSql('ALTER TABLE users ADD oauth_provider VARCHAR(20) DEFAULT NULL, ADD oauth_id VARCHAR(255) DEFAULT NULL');\n        }\n        \n        // Create indexes if they don't exist\n        if (!\$usersTable->hasIndex('IDX_1483A5E9EA87A5A8')) {\n            \$this->addSql('CREATE INDEX IDX_1483A5E9EA87A5A8 ON users (oauth_provider)');\n        }\n        if (!\$usersTable->hasIndex('UNIQ_1483A5E96CDE8892A5D3224A')) {\n            \$this->addSql('CREATE UNIQUE INDEX UNIQ_1483A5E96CDE8892A5D3224A ON users (oauth_provider, oauth_id)');\n        }\n    }",
            "    public function up(Schema \$schema): void\n    {\n        // Safely add oauth columns if they don't exist\n        try {\n            \$this->addSql('ALTER TABLE users ADD oauth_provider VARCHAR(20) DEFAULT NULL, ADD oauth_id VARCHAR(255) DEFAULT NULL');\n        } catch (Exception \$e) {\n            // Column already exists, skip\n        }\n        \n        try {\n            \$this->addSql('CREATE INDEX IDX_1483A5E9EA87A5A8 ON users (oauth_provider)');\n        } catch (Exception \$e) {\n            // Index already exists, skip\n        }\n        \n        try {\n            \$this->addSql('CREATE UNIQUE INDEX UNIQ_1483A5E96CDE8892A5D3224A ON users (oauth_provider, oauth_id)');\n        } catch (Exception \$e) {\n            // Index already exists, skip\n        }\n    }",
            $content
        );
        file_put_contents($file, $newContent);
        echo \"[✓] Fixed: \" . basename($file) . PHP_EOL;\n        continue;
    }
    
    // Fix Version20260502213000 - moderation warning count\n    if (strpos($file, 'Version20260502213000') !== false) {
        $newContent = str_replace(
            "    public function up(Schema \$schema): void\n    {\n        \$this->addSql('ALTER TABLE users ADD moderation_warning_count INT DEFAULT 0');\n    }",
            "    public function up(Schema \$schema): void\n    {\n        try {\n            \$this->addSql('ALTER TABLE users ADD moderation_warning_count INT DEFAULT 0');\n        } catch (Exception \$e) {\n            // Column already exists, skip\n        }\n    }",
            $content
        );
        file_put_contents($file, $newContent);
        echo \"[✓] Fixed: \" . basename($file) . PHP_EOL;\n        continue;
    }\n}\n\necho \"\\n✅ All migrations checked!\\n\";\n?>"