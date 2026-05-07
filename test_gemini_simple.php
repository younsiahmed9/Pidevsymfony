<?php
/**
 * Test simple de la clé API Gemini (sans dépendances Composer)
 * Lance depuis le terminal: php test_gemini_simple.php
 */

// Charge les variables du fichier .env.local
$envFile = __DIR__ . '/.env.local';
if (!file_exists($envFile)) {
    die("❌ Fichier .env.local non trouvé\n");
}

$envContent = file_get_contents($envFile);
preg_match('/GEMINI_API_KEY\s*=\s*(.+)/', $envContent, $matches);

if (!isset($matches[1])) {
    die("❌ GEMINI_API_KEY non trouvée dans .env.local\n");
}

$apiKey = trim(str_replace(['"', "'"], '', $matches[1]));

if (empty($apiKey)) {
    die("❌ GEMINI_API_KEY est vide\n");
}

echo "🔍 Test de la clé API Gemini (cURL)\n";
echo "Clé: " . substr($apiKey, 0, 15) . "...\n\n";

// Test 1: Vérifier la clé avec ListModels
echo "1️⃣ Récupération de la liste des modèles...\n";
$url = "https://generativelanguage.googleapis.com/v1/models?key=" . urlencode($apiKey);

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    echo "❌ Erreur cURL: " . $curlError . "\n";
    exit(1);
}

if ($httpCode === 200) {
    $data = json_decode($response, true);
    echo "✅ Connexion réussie! Modèles disponibles:\n";
    if (isset($data['models'])) {
        $modelNames = [];
        foreach ($data['models'] as $model) {
            $displayName = str_replace('models/', '', $model['name']);
            $modelNames[] = $displayName;
            echo "   • $displayName\n";
        }
        echo "\n";
        
        // Vérifier si gemini-2.0-flash existe
        if (in_array('gemini-2.0-flash', $modelNames)) {
            echo "✅ gemini-2.0-flash est disponible\n";
        }
        if (in_array('gemini-1.5-flash', $modelNames)) {
            echo "✅ gemini-1.5-flash est disponible\n";
        }
    }
    echo "\n";
} else {
    echo "❌ Erreur HTTP " . $httpCode . "\n";
    $jsonResponse = json_decode($response, true);
    if (isset($jsonResponse['error'])) {
        echo "Erreur API: " . $jsonResponse['error']['message'] . "\n";
    } else {
        echo "Réponse brute:\n" . substr($response, 0, 500) . "\n";
    }
    exit(1);
}

// Test 2: Appel simple à gemini-2.0-flash
echo "2️⃣ Test d'appel à gemini-2.0-flash:generateContent...\n";
$url = "https://generativelanguage.googleapis.com/v1/models/gemini-2.0-flash:generateContent?key=" . urlencode($apiKey);

$payload = [
    'contents' => [
        [
            'parts' => [
                ['text' => 'Réponds uniquement par "OK" en une seule ligne.']
            ]
        ]
    ]
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    echo "❌ Erreur cURL: " . $curlError . "\n";
    exit(1);
}

if ($httpCode === 200) {
    $data = json_decode($response, true);
    if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
        $text = $data['candidates'][0]['content']['parts'][0]['text'];
        echo "✅ Réponse reçue:\n";
        echo "   \"" . substr($text, 0, 100) . "\"\n\n";
    } else {
        echo "⚠️ Format de réponse inattendu\n";
        print_r($data);
    }
} else {
    echo "❌ Erreur HTTP " . $httpCode . "\n";
    $data = json_decode($response, true);
    if (isset($data['error'])) {
        echo "Erreur API: " . $data['error']['message'] . "\n";
    } else {
        echo "Réponse: " . substr($response, 0, 300) . "\n";
    }
    exit(1);
}

echo "✅ TEST RÉUSSI: L'API Gemini fonctionne correctement!\n";
