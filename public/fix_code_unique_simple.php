<?php

// Script simple pour modifier la colonne code_unique
$databaseUrl = 'mysql://root:@127.0.0.1:3306/fintrack';

try {
    $pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=fintrack', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Modification de la colonne code_unique...\n";
    $pdo->exec('ALTER TABLE produit MODIFY COLUMN code_unique VARCHAR(100) NULL');
    echo "Colonne code_unique modifiée avec succès pour autoriser NULL\n";
    
} catch (Exception $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}
