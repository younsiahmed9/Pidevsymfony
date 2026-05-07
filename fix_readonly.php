<?php
// Fix all readonly classes in Doctrine for PHP 8.1 compatibility
$files = [
    'vendor/doctrine/dbal/src/Schema/TableConfiguration.php',
    'vendor/doctrine/dbal/src/Schema/PrimaryKeyConstraint.php',
    'vendor/doctrine/dbal/src/Schema/Name/UnqualifiedName.php',
    'vendor/doctrine/dbal/src/Schema/Name/Identifier.php',
    'vendor/doctrine/dbal/src/Schema/Name/GenericName.php',
    'vendor/doctrine/dbal/src/Schema/Name/Parser/UnqualifiedNameParser.php',
    'vendor/doctrine/dbal/src/Schema/Metadata/ViewMetadataRow.php',
    'vendor/doctrine/dbal/src/Schema/Metadata/TableMetadataRow.php',
    'vendor/doctrine/dbal/src/Schema/Metadata/TableColumnMetadataRow.php',
    'vendor/doctrine/dbal/src/Schema/Metadata/SequenceMetadataRow.php',
    'vendor/doctrine/dbal/src/Schema/Metadata/SchemaMetadataRow.php',
    'vendor/doctrine/dbal/src/Schema/Metadata/PrimaryKeyConstraintColumnRow.php',
    'vendor/doctrine/dbal/src/Schema/Metadata/IndexColumnMetadataRow.php',
    'vendor/doctrine/dbal/src/Schema/Metadata/ForeignKeyConstraintColumnMetadataRow.php',
    'vendor/doctrine/dbal/src/Schema/Metadata/DatabaseMetadataRow.php',
    'vendor/doctrine/dbal/src/Schema/Introspection/MetadataProcessor/ViewMetadataProcessor.php',
    'vendor/doctrine/dbal/src/Schema/Introspection/MetadataProcessor/SequenceMetadataProcessor.php',
    'vendor/doctrine/dbal/src/Schema/Introspection/MetadataProcessor/PrimaryKeyConstraintColumnMetadataProcessor.php',
    'vendor/doctrine/dbal/src/Schema/Introspection/MetadataProcessor/IndexColumnMetadataProcessor.php',
    'vendor/doctrine/dbal/src/Schema/Introspection/MetadataProcessor/ForeignKeyConstraintColumnMetadataProcessor.php',
    'vendor/doctrine/dbal/src/Schema/Introspection/IntrospectingSchemaProvider.php',
    'vendor/doctrine/dbal/src/Schema/Index/IndexedColumn.php',
    'vendor/doctrine/dbal/src/Schema/DefaultExpression/CurrentTimestamp.php',
    'vendor/doctrine/dbal/src/Schema/DefaultExpression/CurrentTime.php',
    'vendor/doctrine/dbal/src/Schema/DefaultExpression/CurrentDate.php',
    'vendor/doctrine/dbal/src/Query/Union.php',
    'vendor/doctrine/dbal/src/Query/Join.php',
    'vendor/doctrine/dbal/src/Query/From.php',
    'vendor/doctrine/dbal/src/Query/ForUpdate.php',
    'vendor/doctrine/dbal/src/Query/CommonTableExpression.php',
    'vendor/doctrine/dbal/src/Platforms/SQLServer/SQLServerMetadataProvider.php',
    'vendor/doctrine/dbal/src/Platforms/Oracle/OracleMetadataProvider.php',
    'vendor/doctrine/dbal/src/Platforms/Db2/Db2MetadataProvider.php',
    'vendor/doctrine/dbal/src/Platforms/SQLite/SQLiteMetadataProvider/ForeignKeyConstraintDetails.php',
    'vendor/doctrine/dbal/src/Platforms/SQLite/SQLiteMetadataProvider.php',
    'vendor/doctrine/dbal/src/Platforms/MySQL/DefaultTableOptions.php',
    'vendor/doctrine/dbal/src/Platforms/PostgreSQL/PostgreSQLMetadataProvider.php',
];

$fixed = 0;
$errors = [];

foreach ($files as $file) {
    if (!file_exists($file)) {
        $errors[] = "File not found: $file";
        continue;
    }
    
    $content = file_get_contents($file);
    
    // Replace "final readonly class" with "final class"
    $newContent = str_replace('final readonly class', 'final class', $content);
    
    if ($newContent !== $content) {
        file_put_contents($file, $newContent);
        $fixed++;
        echo "[OK] Fixed: $file\n";
    } else {
        echo "[SKIP] No changes: $file\n";
    }
}

echo "\n✅ Fixed: $fixed files\n";
if (!empty($errors)) {
    echo "\n⚠️  Errors:\n";
    foreach ($errors as $error) {
        echo "  - $error\n";
    }
}
?>
