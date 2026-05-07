<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\ORMSetup;

try {
    // Charger les variables d'environnement depuis .env
    $envFile = __DIR__ . '/../.env';
    if (file_exists($envFile)) {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos($line, '#') === 0) continue;
            if (strpos($line, '=') === false) continue;
            
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            
            if (!isset($_ENV[$key])) {
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }
    }

    // Parser DATABASE_URL pour extraire les informations
    $databaseUrl = $_ENV['DATABASE_URL'] ?? 'mysql://root:@127.0.0.1:3306/fintrack';
    
    // Extraire correctement le nom de la base de données
    $path = parse_url($databaseUrl, PHP_URL_PATH);
    $dbname = ltrim($path, '/');
    
    $config = ORMSetup::createAttributeMetadataConfiguration(paths: [__DIR__.'/../src/Entity'], isDevMode: true);
    $connection = DriverManager::getConnection([
        'driver' => 'pdo_mysql',
        'host' => parse_url($databaseUrl, PHP_URL_HOST) ?? '127.0.0.1',
        'port' => parse_url($databaseUrl, PHP_URL_PORT) ?? '3306',
        'dbname' => $dbname,
        'user' => parse_url($databaseUrl, PHP_URL_USER) ?? 'root',
        'password' => parse_url($databaseUrl, PHP_URL_PASS) ?? '',
    ]);

    // Modifier la colonne code_unique pour autoriser NULL
    echo "Modification de la colonne code_unique...\n";
    $connection->executeStatement('ALTER TABLE produit MODIFY COLUMN code_unique VARCHAR(100) NULL');
    echo "Colonne code_unique modifiée avec succès pour autoriser NULL\n";

} catch (Exception $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}
