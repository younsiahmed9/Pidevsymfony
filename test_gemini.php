<?php
/**
 * Test rapide de la clé API Gemini
 * Lance depuis le terminal: php test_gemini.php
 */

// Charge les variables d'environnement
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/.env.local.php';

$apiKey = getenv('GEMINI_API_KEY');

if (empty($apiKey)) {
    die("❌ GEMINI_API_KEY non définie dans .env.local\n");
}

echo "🔍 Test de la clé API Gemini...\n";
echo "Clé: " . substr($apiKey, 0, 15) . "...\n\n";

// Test 1: Récupérer la liste des modèles
echo "1️⃣ Récupération de la liste des modèles disponibles...\n";
$url = "https://generativelanguage.googleapis.com/v1/models?key=" . $apiKey;

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    $data = json_decode($response, true);
    echo "✅ Modèles disponibles:\n";
    if (isset($data['models'])) {
        foreach ($data['models'] as $model) {
            echo "   - " . $model['name'] . "\n";
        }
    }
    echo "\n";
} else {
    echo "❌ Erreur HTTP " . $httpCode . "\n";
    echo "Réponse: " . $response . "\n\n";
}

// Test 2: Appel simple à gemini-2.0-flash
echo "2️⃣ Test d'appel à gemini-2.0-flash...\n";
$url = "https://generativelanguage.googleapis.com/v1/models/gemini-2.0-flash:generateContent?key=" . $apiKey;

$payload = [
    'contents' => [
        [
            'parts' => [
                ['text' => 'Dis bonjour en français dans un style formel.']
            ]
        ]
    ],
    'generationConfig' => [
        'temperature' => 0.7,
        'maxOutputTokens' => 100
    ]
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    $data = json_decode($response, true);
    if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
        echo "✅ Réponse reçue:\n";
        echo "   " . $data['candidates'][0]['content']['parts'][0]['text'] . "\n\n";
    }
} else {
    echo "❌ Erreur HTTP " . $httpCode . "\n";
    $data = json_decode($response, true);
    if (isset($data['error']['message'])) {
        echo "Erreur: " . $data['error']['message'] . "\n\n";
    } else {
        echo "Réponse: " . $response . "\n\n";
    }
}

echo "✅ Test terminé!\n";
