<?php

require_once 'vendor/autoload.php';

use App\Service\SmartPromotionService;
use Doctrine\DBAL\Connection;
use App\Entity\User;

// Script de test pour promotions avec période courte
echo "Test des promotions intelligentes (période = 1 jour)\n";
echo "================================================\n\n";

// Simulation d'un utilisateur (à adapter selon votre système)
$userId = 1; // Remplacez par l'ID d'un utilisateur réel

// Connexion à la base de données (à adapter selon votre config)
$connection = Connection::create([
    'dbname' => 'votre_base',
    'user' => 'votre_user',
    'password' => 'votre_password',
    'host' => 'localhost',
    'driver' => 'pdo_mysql',
]);

$promotionService = new SmartPromotionService($connection);

// Test avec période de 1 jour
$result = $promotionService->preview(
    $userId,
    periodDays: 1, // 1 jour au lieu de 30
    minReduction: 10.0,
    maxReduction: 50.0
);

echo "Règles appliquées:\n";
echo "- Période: {$result['rules']['period_days']} jours\n";
echo "- Réduction min: {$result['rules']['min_reduction']}%\n";
echo "- Réduction max: {$result['rules']['max_reduction']}%\n\n";

echo "Produits éligibles: {$result['total']}\n\n";

foreach ($result['products'] as $product) {
    echo "Produit: {$product['nom']}\n";
    echo "- ID: {$product['id']}\n";
    echo "- Catégorie: {$product['categorie']}\n";
    echo "- Jours sans vente: {$product['jours_sans_vente']}\n";
    echo "- Prix actuel: {$product['prix_actuel']} DT\n";
    echo "- Réduction: {$product['reduction_pourcentage']}%\n";
    echo "- Nouveau prix: {$product['nouveau_prix']} DT\n";
    echo "--------------------------------\n";
}

echo "\nTest terminé!\n";
